import { getPreviewGateDecision } from './generationPhases';
import {
    buildWorkspaceDependencyOperations,
    buildWorkspaceFileOperationsFromGraph,
    buildWriteManifestOperation,
    type WorkspaceDependencyOperation,
    type WorkspaceFileOperation,
} from './fileOperations';
import { collectGeneratedSections } from './projectGraph';
import { buildWorkspaceManifestFromProjectGraph, WORKSPACE_MANIFEST_RELATIVE_PATH } from './workspaceManifest';
import type { GeneratedProjectGraph, WorkspaceManifest } from './types';

export interface WorkspacePlan {
    projectId: string | null;
    rootDir: string | null;
    activeGenerationRunId: string | null;
    manifestPath: string;
    manifest: WorkspaceManifest;
    fileOperations: WorkspaceFileOperation[];
    dependencyOperations: WorkspaceDependencyOperation[];
    previewGate: ReturnType<typeof getPreviewGateDecision>;
    summary: {
        pageCount: number;
        sectionCount: number;
        componentCount: number;
        assetCount: number;
        fileCount: number;
        dependencyCount: number;
    };
}

/**
 * Integration point: future generation runners can emit a `WorkspacePlan`
 * before touching the filesystem, then execute `fileOperations` and write the
 * normalized manifest as the final bookkeeping step.
 */
export function createWorkspacePlan(graph: GeneratedProjectGraph): WorkspacePlan {
    const manifest = buildWorkspaceManifestFromProjectGraph(graph, {
        manifestPath: WORKSPACE_MANIFEST_RELATIVE_PATH,
    });

    return {
        projectId: graph.projectId,
        rootDir: graph.rootDir,
        activeGenerationRunId: graph.generation.runId,
        manifestPath: WORKSPACE_MANIFEST_RELATIVE_PATH,
        manifest,
        fileOperations: [
            ...buildWorkspaceFileOperationsFromGraph(graph),
            buildWriteManifestOperation(manifest),
        ],
        dependencyOperations: buildWorkspaceDependencyOperations(graph),
        previewGate: getPreviewGateDecision(graph.generation),
        summary: {
            pageCount: graph.pages.length,
            sectionCount: collectGeneratedSections(graph).length,
            componentCount: graph.components.length,
            assetCount: graph.assets.length,
            fileCount: graph.files.length,
            dependencyCount: graph.dependencies.length,
        },
    };
}
