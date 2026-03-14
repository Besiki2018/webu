import { describe, expect, it, vi } from 'vitest';

import {
    detectAiLayeredEditConflict,
    executeGraphEdit,
    type AiLayeredEditRevisionState,
} from '@/builder/ai/graphEditExecutor';

function createRevisionState(overrides: Partial<AiLayeredEditRevisionState> = {}): AiLayeredEditRevisionState {
    return {
        manifestUpdatedAt: '2026-03-14T00:00:00.000Z',
        activeGenerationRunId: 'run-1',
        builderStateCursor: {
            pageId: 1,
            pageSlug: 'home',
            stateVersion: 3,
            revisionVersion: 7,
        },
        pageId: 1,
        pageSlug: 'home',
        ...overrides,
    };
}

describe('graphEditExecutor', () => {
    it('detects stale builder cursor conflicts', () => {
        const conflict = detectAiLayeredEditConflict(
            createRevisionState({
                builderStateCursor: {
                    pageId: 1,
                    pageSlug: 'home',
                    stateVersion: 2,
                    revisionVersion: 6,
                },
            }),
            createRevisionState(),
        );

        expect(conflict).toMatchObject({
            reason: 'builder_state_advanced',
        });
    });

    it('normalizes structure change execution and exposes sync metadata', async () => {
        const execute = vi.fn(async () => ({
            success: true,
            change_set: {
                operations: [{
                    op: 'insertSection',
                    sectionId: 'pricing-1',
                }],
                summary: ['Added a pricing section.'],
            },
            summary: ['Added a pricing section.'],
            action_log: ['insertSection: pricing-1'],
            diagnostic_log: ['structure lane used'],
        }));

        const result = await executeGraphEdit({
            intent: 'structure_change',
            expectedRevision: createRevisionState(),
            getCurrentRevision: async () => createRevisionState(),
            execute,
        });

        expect(result).toMatchObject({
            status: 'applied',
            assistantKind: 'changed_page_structure',
            note: 'Added a pricing section.',
            details: ['insertSection: pricing-1'],
            hasUnsyncedOps: false,
        });
        expect(result.syncableChangeSet?.operations).toHaveLength(1);
        expect(execute).toHaveBeenCalledTimes(1);
    });

    it('normalizes page changes backed by workspace file edits', async () => {
        const result = await executeGraphEdit({
            intent: 'page_change',
            expectedRevision: createRevisionState(),
            getCurrentRevision: async () => createRevisionState(),
            execute: async () => ({
                success: true,
                summary: 'Created an about page.',
                files_changed: true,
                changes: [{
                    op: 'createFile',
                    path: 'src/pages/about/Page.tsx',
                }, {
                    op: 'updateFile',
                    path: 'src/App.tsx',
                }],
            }),
        });

        expect(result).toMatchObject({
            status: 'applied',
            assistantKind: 'changed_pages',
            note: 'Created an about page.',
            details: [
                'createFile: src/pages/about/Page.tsx',
                'updateFile: src/App.tsx',
            ],
            hasUnsyncedOps: true,
        });
    });
});
