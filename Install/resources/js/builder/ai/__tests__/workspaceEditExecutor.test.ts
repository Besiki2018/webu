import { describe, expect, it } from 'vitest';

import {
    executeWorkspaceEdit,
} from '@/builder/ai/workspaceEditExecutor';
import type { AiLayeredEditRevisionState } from '@/builder/ai/graphEditExecutor';

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

describe('workspaceEditExecutor', () => {
    it('blocks stale workspace file edits when manifest moved ahead', async () => {
        const result = await executeWorkspaceEdit({
            intent: 'file_change',
            expectedRevision: createRevisionState({
                manifestUpdatedAt: '2026-03-13T23:59:00.000Z',
            }),
            getCurrentRevision: async () => createRevisionState(),
            execute: async () => ({
                success: true,
                changes: [{
                    op: 'updateFile',
                    path: 'src/App.tsx',
                }],
            }),
        });

        expect(result).toMatchObject({
            status: 'conflicted',
            assistantKind: 'could_not_safely_apply',
        });
    });

    it('summarizes workspace file mutations', async () => {
        const result = await executeWorkspaceEdit({
            intent: 'file_change',
            expectedRevision: createRevisionState(),
            getCurrentRevision: async () => createRevisionState(),
            execute: async () => ({
                success: true,
                summary: 'Updated the route and utility files.',
                files_changed: true,
                changes: [{
                    op: 'updateFile',
                    path: 'src/pages/home/Page.tsx',
                }, {
                    op: 'createFile',
                    path: 'src/utils/formatPrice.ts',
                }],
            }),
        });

        expect(result).toMatchObject({
            status: 'applied',
            assistantKind: 'modified_workspace_files',
            note: 'Updated the route and utility files.',
            changedPaths: [
                'src/pages/home/Page.tsx',
                'src/utils/formatPrice.ts',
            ],
            shouldRefreshWorkspace: true,
        });
    });

    it('summarizes regeneration requests separately', async () => {
        const result = await executeWorkspaceEdit({
            intent: 'regeneration_request',
            expectedRevision: createRevisionState(),
            getCurrentRevision: async () => createRevisionState(),
            execute: async () => ({
                success: true,
                summary: 'Regenerated workspace code from the canonical site state.',
                changed_paths: ['src/pages/home/Page.tsx'],
            }),
        });

        expect(result).toMatchObject({
            status: 'applied',
            assistantKind: 'regenerated_workspace',
            shouldRefreshWorkspace: true,
            details: ['src/pages/home/Page.tsx'],
        });
    });
});
