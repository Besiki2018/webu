import { describe, expect, it } from 'vitest';
import {
    areBuilderStructureCollectionsEqual,
    buildBuilderBridgeEventSignature,
    isStaleBuilderBridgeState,
    resolvePendingBuilderStructureMutation,
    shouldIgnoreRevertingPendingStructureSnapshot,
} from '@/builder/cms/chatBuilderMutationFlow';

describe('chatBuilderMutationFlow', () => {
    it('ignores stale mutation acknowledgements for a different request id', () => {
        expect(resolvePendingBuilderStructureMutation({
            requestId: 'request-1',
            mutation: 'move-section',
            baseItems: [],
            previewItems: [],
        }, {
            requestId: 'request-2',
            success: true,
            changed: true,
        })).toEqual({
            status: 'ignore',
            errorMessage: null,
        });
    });

    it('keeps pending state until the authoritative snapshot arrives after a successful ack', () => {
        expect(resolvePendingBuilderStructureMutation({
            requestId: 'request-1',
            mutation: 'move-section',
            baseItems: [],
            previewItems: [],
        }, {
            requestId: 'request-1',
            success: true,
            changed: true,
        })).toEqual({
            status: 'keep-pending',
            errorMessage: null,
        });
    });

    it('clears pending state with an explicit error when mutation fails', () => {
        expect(resolvePendingBuilderStructureMutation({
            requestId: 'request-1',
            mutation: 'remove-section',
            baseItems: [],
            previewItems: null,
        }, {
            requestId: 'request-1',
            success: false,
            changed: false,
            error: 'Section not found',
        })).toEqual({
            status: 'clear-error',
            errorMessage: 'Section not found',
        });
    });

    it('builds stable signatures for ready and snapshot stale rejection', () => {
        expect(buildBuilderBridgeEventSignature({
            pageId: 20,
            pageSlug: 'pricing',
            stateVersion: 7,
            revisionVersion: 4,
            structureHash: 'abc123',
        })).toBe('20:pricing:7:4:abc123');
    });

    it('rejects older same-page builder state versions', () => {
        expect(isStaleBuilderBridgeState({
            pageId: 20,
            pageSlug: 'pricing',
            stateVersion: 7,
            revisionVersion: 4,
        }, {
            pageId: 20,
            pageSlug: 'pricing',
            stateVersion: 6,
            revisionVersion: 4,
        })).toBe(true);
    });

    it('allows other pages to manage their own builder cursor independently', () => {
        expect(isStaleBuilderBridgeState({
            pageId: 20,
            pageSlug: 'pricing',
            stateVersion: 7,
            revisionVersion: 4,
        }, {
            pageId: 21,
            pageSlug: 'contact',
            stateVersion: 1,
            revisionVersion: 1,
        })).toBe(false);
    });

    it('detects when an incoming snapshot would revert a pending optimistic mutation', () => {
        const baseItems = [{
            localId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            label: 'Hero',
            previewText: 'Hero',
            props: { title: 'Hero' },
        }];
        const previewItems = [{
            localId: 'hero-2',
            sectionKey: 'webu_general_hero_01',
            label: 'Hero',
            previewText: 'Hero 2',
            props: { title: 'Hero 2' },
        }];

        expect(shouldIgnoreRevertingPendingStructureSnapshot({
            requestId: 'request-1',
            mutation: 'remove-section',
            baseItems,
            previewItems,
            selectionSnapshot: null,
        }, baseItems)).toBe(true);

        expect(shouldIgnoreRevertingPendingStructureSnapshot({
            requestId: 'request-1',
            mutation: 'remove-section',
            baseItems,
            previewItems,
            selectionSnapshot: null,
        }, previewItems)).toBe(false);
    });

    it('compares structure collections by visible content instead of array identity', () => {
        expect(areBuilderStructureCollectionsEqual([{
            localId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            label: 'Hero',
            previewText: 'Hello',
            props: { title: 'Hello', cta: { label: 'Start' } },
        }], [{
            localId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            label: 'Hero',
            previewText: 'Hello',
            props: { title: 'Hello', cta: { label: 'Start' } },
        }])).toBe(true);
    });
});
