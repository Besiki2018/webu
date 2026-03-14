import { describe, expect, it } from 'vitest';

import { buildWorkspaceFileRecords, describeWorkspaceFileProvenance } from '@/builder/workspace/workspaceFileState';

describe('workspaceFileState', () => {
    it('builds workspace records with provenance and diff metadata from manifest and operation log', () => {
        const records = buildWorkspaceFileRecords([{
            path: 'src/pages/home/Page.tsx',
            name: 'Page.tsx',
            size: 320,
            is_dir: false,
            mod_time: '2026-03-10T12:00:00Z',
            is_editable: true,
            is_generated_projection: false,
            projection_role: 'page',
            projection_source: 'custom',
        }], {
            fileOwnership: [{
                path: 'src/pages/home/Page.tsx',
                kind: 'page',
                ownerType: 'page',
                ownerId: 'page-home',
                generatedBy: 'ai',
                editState: 'mixed',
                pageIds: ['page-home'],
                componentIds: [],
                activeGenerationRunId: null,
                checksum: 'after',
                sectionLocalIds: [],
                componentKeys: [],
                originatingPageId: 'page-home',
                originatingPageSlug: 'home',
                lastEditor: 'visual_builder',
                dirty: true,
                updatedAt: '2026-03-10T12:00:00Z',
                locked: false,
                templateOwned: false,
                lastOperationId: 'workspace-op-1',
                lastOperationKind: 'apply_patch_set',
            }],
        }, {
            entries: [{
                id: 'workspace-op-1',
                timestamp: '2026-03-10T12:00:00Z',
                actor: 'visual_builder',
                source: 'workspace_backed_builder_adapter',
                operation_kind: 'apply_patch_set',
                path: 'src/pages/home/Page.tsx',
                previous_path: null,
                reason: 'visual_builder_sync',
                preview_refresh_requested: true,
                before: { exists: true, checksum: 'before', size: 280, line_count: 10 },
                after: { exists: true, checksum: 'after', size: 320, line_count: 12 },
            }],
        });

        expect(records[0]).toEqual(expect.objectContaining({
            path: 'src/pages/home/Page.tsx',
            isEditable: true,
            provenance: expect.objectContaining({
                generatedBy: 'ai',
                editState: 'mixed',
                lastEditor: 'visual_builder',
                dirty: true,
                lastOperationKind: 'apply_patch_set',
            }),
            diff: expect.objectContaining({
                status: 'updated',
                operationKind: 'apply_patch_set',
                byteDelta: 40,
                lineDelta: 2,
            }),
        }));
        expect(describeWorkspaceFileProvenance(records[0]?.provenance)).toBe('Mixed');
    });
});
