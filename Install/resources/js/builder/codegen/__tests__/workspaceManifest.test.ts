import { describe, expect, it } from 'vitest';

import { createGeneratedProjectGraph } from '@/builder/codegen/projectGraph';
import { createWorkspacePlan } from '@/builder/codegen/workspacePlan';
import {
    buildWorkspaceManifestFromProjectGraph,
    isWorkspacePreviewReady,
    markManifestFileUserEdited,
    WORKSPACE_MANIFEST_RELATIVE_PATH,
} from '@/builder/codegen/workspaceManifest';

describe('codegen workspaceManifest', () => {
    it('builds workspace manifest entries from the project graph', () => {
        const graph = createGeneratedProjectGraph({
            projectId: 'project-1',
            name: 'Acme',
            rootDir: '/tmp/workspaces/project-1',
            generation: {
                runId: 'run-1',
                phase: 'ready',
                preview: {
                    ready: true,
                    status: 'ready',
                    buildId: 'preview-1',
                    previewUrl: '/preview/project-1',
                    artifactHash: 'artifact',
                    workspaceHash: 'workspace',
                },
            },
            pages: [{
                id: 'page-home',
                slug: 'home',
                title: 'Home',
                entryFilePath: 'src/pages/home/Page.tsx',
                sections: [{
                    id: 'section-hero',
                    localId: 'hero-1',
                    kind: 'hero',
                    registryKey: 'webu_general_hero_01',
                    props: {
                        title: 'Hero',
                    },
                }],
            }],
            files: [{
                id: 'file-home',
                path: 'src/pages/home/Page.tsx',
                kind: 'page',
                language: 'tsx',
                contents: 'export default function Page() { return null; }',
                source: 'ai',
                editState: 'ai-generated',
                ownerType: 'page',
                ownerId: 'page-home',
                pageIds: ['page-home'],
                componentIds: [],
                dependencies: ['react'],
            }],
        });

        const manifest = buildWorkspaceManifestFromProjectGraph(graph);
        expect(manifest.manifestPath).toBe(WORKSPACE_MANIFEST_RELATIVE_PATH);
        expect(manifest.activeGenerationRunId).toBe('run-1');
        expect(manifest.generatedPages[0]?.entryFilePath).toBe('src/pages/home/Page.tsx');
        expect(manifest.fileOwnership[0]?.editState).toBe('ai-generated');
        expect(isWorkspacePreviewReady(manifest)).toBe(true);

        const updated = markManifestFileUserEdited(manifest, 'src/pages/home/Page.tsx');
        expect(updated.fileOwnership[0]?.editState).toBe('user-edited');

        const plan = createWorkspacePlan(graph);
        expect(plan.fileOperations.some((operation) => operation.kind === 'write-manifest')).toBe(true);
        expect(plan.previewGate.allowPreview).toBe(true);
    });
});
