import { describe, expect, it } from 'vitest';
import {
    attachBuilderBridgePageIdentity,
    builderBridgePagesMatch,
    parseBuilderBridgeChatCommand,
    parseBuilderBridgeCmsEvent,
    normalizeBuilderBridgePageIdentity,
    payloadTargetsBuilderBridgePage,
} from '@/builder/cms/embeddedBuilderBridgeContract';

describe('embeddedBuilderBridgeContract', () => {
    it('normalizes page identity from numeric strings', () => {
        expect(normalizeBuilderBridgePageIdentity({
            pageId: '42',
            pageSlug: ' Home ',
            pageTitle: ' Landing ',
        })).toEqual({
            pageId: 42,
            pageSlug: 'home',
            pageTitle: 'Landing',
        });
    });

    it('matches pages by id first and slug as fallback', () => {
        expect(builderBridgePagesMatch(
            { pageId: 9, pageSlug: 'home', pageTitle: null },
            { pageId: 9, pageSlug: 'about', pageTitle: null },
        )).toBe(true);

        expect(builderBridgePagesMatch(
            { pageId: null, pageSlug: 'pricing', pageTitle: null },
            { pageId: null, pageSlug: 'pricing', pageTitle: 'Pricing' },
        )).toBe(true);
    });

    it('rejects payloads for a different page but allows unscoped messages', () => {
        const currentPage = normalizeBuilderBridgePageIdentity({
            pageId: 7,
            pageSlug: 'home',
            pageTitle: 'Home',
        });

        expect(payloadTargetsBuilderBridgePage({ type: 'builder:state' }, currentPage)).toBe(true);
        expect(payloadTargetsBuilderBridgePage({ pageId: 7 }, currentPage)).toBe(true);
        expect(payloadTargetsBuilderBridgePage({ pageSlug: 'blog' }, currentPage)).toBe(false);
    });

    it('attaches normalized page identity onto outgoing messages', () => {
        expect(attachBuilderBridgePageIdentity({
            type: 'builder:ready',
        }, {
            pageId: 12,
            pageSlug: ' Home ',
            pageTitle: ' Home Page ',
        })).toEqual({
            type: 'builder:ready',
            pageId: 12,
            pageSlug: 'home',
            pageTitle: 'Home Page',
        });
    });

    it('validates and parses mutating chat commands with request ids', () => {
        expect(parseBuilderBridgeChatCommand({
            source: 'webu-chat-builder',
            type: 'builder:move-section',
            requestId: 'request-1',
            sectionLocalId: 'section-a',
            targetSectionLocalId: 'section-b',
            position: 'after',
            pageId: 7,
            pageSlug: 'home',
        })).toMatchObject({
            type: 'builder:move-section',
            requestId: 'request-1',
            sectionLocalId: 'section-a',
            targetSectionLocalId: 'section-b',
            position: 'after',
            pageId: 7,
            pageSlug: 'home',
        });
    });

    it('rejects malformed chat bridge payloads', () => {
        expect(parseBuilderBridgeChatCommand({
            source: 'webu-chat-builder',
            type: 'builder:move-section',
            requestId: '',
            sectionLocalId: 'section-a',
            targetSectionLocalId: 'section-b',
            position: 'after',
        })).toBeNull();
    });

    it('parses cms mutation result events with metadata', () => {
        expect(parseBuilderBridgeCmsEvent({
            source: 'webu-cms-builder',
            type: 'builder:mutation-result',
            requestId: 'request-1',
            mutation: 'move-section',
            success: true,
            changed: true,
            stateVersion: 5,
            revisionVersion: 9,
            structureHash: 'hash-1',
            pageId: 7,
            pageSlug: 'home',
        })).toMatchObject({
            type: 'builder:mutation-result',
            requestId: 'request-1',
            mutation: 'move-section',
            success: true,
            changed: true,
            stateVersion: 5,
            revisionVersion: 9,
            structureHash: 'hash-1',
            pageId: 7,
            pageSlug: 'home',
        });
    });
});
