import { describe, expect, it } from 'vitest';

import {
    BUILDER_BRIDGE_VERSION,
    buildBuilderInsertNodeMessage,
    buildBuilderBridgeSelectionSignature,
    buildBuilderBridgeVisualStateSignature,
    buildBuilderReadyMessage,
    buildBuilderSelectTargetMessage,
    buildBuilderSyncStateMessage,
    builderBridgeMessageEchoesActor,
    builderBridgeEnvelopeTargetsProject,
    inspectBuilderBridgeEnvelope,
    parseBuilderBridgeEnvelope,
} from '@/lib/builderBridge';

describe('builderBridge', () => {
    const input = {
        source: 'chat' as const,
        projectId: 'project-1',
        page: {
            pageId: 42,
            pageSlug: 'home',
            pageTitle: 'Home',
        },
        requestId: 'request-1',
        timestamp: 12345,
    };

    it('round-trips select-target messages through the parser', () => {
        const message = buildBuilderSelectTargetMessage({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero',
            currentBreakpoint: 'desktop',
            currentInteractionState: 'normal',
        }, input);

        expect(parseBuilderBridgeEnvelope(message)).toEqual({
            ...message,
            payload: {
                target: {
                    pageId: null,
                    pageSlug: null,
                    pageTitle: null,
                    sectionLocalId: 'hero-1',
                    sectionKey: 'webu_general_hero_01',
                    componentType: 'webu_general_hero_01',
                    componentName: 'Hero',
                    parameterPath: null,
                    componentPath: null,
                    elementId: null,
                    selector: null,
                    textPreview: null,
                    props: null,
                    fieldLabel: null,
                    fieldGroup: null,
                    builderId: null,
                    parentId: null,
                    editableFields: [],
                    sectionId: null,
                    instanceId: null,
                    variants: null,
                    allowedUpdates: null,
                    currentBreakpoint: 'desktop',
                    currentInteractionState: 'normal',
                    responsiveContext: null,
                },
            },
        });
    });

    it('rejects malformed envelope metadata', () => {
        const message = buildBuilderSyncStateMessage({
            viewport: 'desktop',
            structureOpen: true,
        }, input);

        expect(parseBuilderBridgeEnvelope({
            ...message,
            source: 'unknown-source',
        })).toBeNull();

        expect(parseBuilderBridgeEnvelope({
            ...message,
            version: BUILDER_BRIDGE_VERSION + 1,
        })).toBeNull();

        expect(parseBuilderBridgeEnvelope({
            ...message,
            projectId: '',
        })).toBeNull();
    });

    it('returns a specific parse reason for malformed metadata', () => {
        const message = buildBuilderSyncStateMessage({
            viewport: 'desktop',
            structureOpen: true,
        }, input);

        expect(inspectBuilderBridgeEnvelope({
            ...message,
            source: 'unknown-source',
        })).toEqual({
            message: null,
            error: 'invalid-message-source',
        });
    });

    it('returns a specific parse reason for invalid ready payloads', () => {
        const message = buildBuilderReadyMessage({
            channel: 'sidebar',
            stateVersion: 1,
            structureHash: 'hash',
            revisionId: 1,
            revisionVersion: 1,
        }, input);

        expect(inspectBuilderBridgeEnvelope({
            ...message,
            payload: {
                ...message.payload,
                channel: 'unknown',
            },
        })).toEqual({
            message: null,
            error: 'invalid-ready-channel',
        });
    });

    it('round-trips insert-node messages with stable section local ids', () => {
        const message = buildBuilderInsertNodeMessage({
            sectionKey: 'webu_general_text_01',
            sectionLocalId: 'builder-section-1',
            afterSectionLocalId: 'hero-1',
            placement: 'after',
        }, input);

        expect(parseBuilderBridgeEnvelope(message)).toEqual({
            ...message,
            payload: {
                sectionKey: 'webu_general_text_01',
                sectionLocalId: 'builder-section-1',
                afterSectionLocalId: 'hero-1',
                targetSectionKey: null,
                placement: 'after',
                sections: undefined,
            },
        });
    });

    it('filters messages by project id', () => {
        const message = buildBuilderSyncStateMessage({
            viewport: 'desktop',
        }, input);

        const parsedMessage = parseBuilderBridgeEnvelope(message);
        expect(parsedMessage).not.toBeNull();
        expect(builderBridgeEnvelopeTargetsProject(parsedMessage!, 'project-1')).toBe(true);
        expect(builderBridgeEnvelopeTargetsProject(parsedMessage!, 'project-2')).toBe(false);
    });

    it('flags self-origin bridge envelopes for echo suppression', () => {
        const message = buildBuilderSyncStateMessage({
            viewport: 'desktop',
        }, input);

        expect(builderBridgeMessageEchoesActor(message, 'chat')).toBe(true);
        expect(builderBridgeMessageEchoesActor(message, 'sidebar')).toBe(false);
    });

    it('builds stable visual and selection signatures for echo guards', () => {
        expect(buildBuilderBridgeVisualStateSignature({
            pageId: 42,
            pageSlug: 'home',
            pageTitle: 'Home',
            viewport: 'desktop',
            interactionState: 'normal',
            structureOpen: true,
            sidebarMode: 'settings',
        })).toBe(JSON.stringify({
            type: 'BUILDER_SYNC_STATE',
            pageId: 42,
            pageSlug: 'home',
            pageTitle: 'Home',
            payload: {
                viewport: 'desktop',
                interactionState: 'normal',
                structureOpen: true,
                sidebarMode: 'settings',
            },
        }));

        expect(buildBuilderBridgeSelectionSignature({
            pageId: 42,
            pageSlug: 'home',
            pageTitle: 'Home',
            target: {
                pageId: 42,
                pageSlug: 'home',
                pageTitle: 'Home',
                sectionLocalId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                componentType: 'webu_general_hero_01',
                componentName: 'Hero',
                parameterPath: null,
                componentPath: null,
                elementId: null,
                selector: null,
                textPreview: null,
                props: null,
                fieldLabel: null,
                fieldGroup: null,
                builderId: 'hero-1::section',
                parentId: null,
                editableFields: ['subtitle', 'title'],
                sectionId: null,
                instanceId: null,
                variants: null,
                allowedUpdates: null,
                currentBreakpoint: 'desktop',
                currentInteractionState: 'normal',
                responsiveContext: null,
            },
        })).toBe(JSON.stringify({
            type: 'BUILDER_SELECT_TARGET',
            pageId: 42,
            pageSlug: 'home',
            pageTitle: 'Home',
            payload: {
                pageId: 42,
                pageSlug: 'home',
                pageTitle: 'Home',
                sectionLocalId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                componentType: 'webu_general_hero_01',
                componentName: 'Hero',
                parameterPath: null,
                componentPath: null,
                elementId: null,
                selector: null,
                textPreview: null,
                fieldLabel: null,
                fieldGroup: null,
                builderId: 'hero-1::section',
                parentId: null,
                editableFields: ['subtitle', 'title'],
                sectionId: null,
                instanceId: null,
                currentBreakpoint: 'desktop',
                currentInteractionState: 'normal',
            },
        }));
    });
});
