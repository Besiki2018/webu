import type {
    GeneratedDependency,
    GeneratedFile,
    GeneratedProjectGraph,
    GeneratedFileOwnerType,
    GeneratedFileSource,
    WorkspaceManifest,
} from './types';

export type WorkspaceFileOperationKind = 'ensure-directory' | 'write-file' | 'delete-file' | 'write-manifest';

export interface WorkspaceFileOperation {
    kind: WorkspaceFileOperationKind;
    path: string;
    contents?: string;
    ownerType?: GeneratedFileOwnerType;
    ownerId?: string | null;
    source?: GeneratedFileSource;
    reason?: string | null;
}

export interface WorkspaceDependencyOperation {
    kind: 'install-dependency';
    packageManager: GeneratedDependency['manager'];
    dependencyName: string;
    version: string;
    dependencyKind: GeneratedDependency['kind'];
}

function normalizePath(path: string): string {
    return path.trim().replace(/\\/g, '/');
}

function dirname(path: string): string {
    const normalized = normalizePath(path);
    const index = normalized.lastIndexOf('/');
    return index <= 0 ? '' : normalized.slice(0, index);
}

export function buildEnsureDirectoryOperation(path: string, reason: string | null = null): WorkspaceFileOperation {
    return {
        kind: 'ensure-directory',
        path: normalizePath(path),
        reason,
    };
}

export function buildWriteFileOperation(file: GeneratedFile): WorkspaceFileOperation {
    return {
        kind: 'write-file',
        path: normalizePath(file.path),
        contents: file.contents,
        ownerType: file.ownerType,
        ownerId: file.ownerId,
        source: file.source,
        reason: 'graph_file',
    };
}

export function buildDeleteFileOperation(path: string, reason: string | null = null): WorkspaceFileOperation {
    return {
        kind: 'delete-file',
        path: normalizePath(path),
        reason,
    };
}

export function buildWriteManifestOperation(manifest: WorkspaceManifest): WorkspaceFileOperation {
    return {
        kind: 'write-manifest',
        path: normalizePath(manifest.manifestPath),
        contents: JSON.stringify(manifest, null, 2),
        ownerType: 'project',
        ownerId: manifest.projectId,
        source: 'system',
        reason: 'workspace_manifest',
    };
}

export function buildWorkspaceDependencyOperations(graph: Pick<GeneratedProjectGraph, 'dependencies'>): WorkspaceDependencyOperation[] {
    return graph.dependencies.map((dependency) => ({
        kind: 'install-dependency',
        packageManager: dependency.manager,
        dependencyName: dependency.name,
        version: dependency.version,
        dependencyKind: dependency.kind,
    }));
}

export function buildWorkspaceFileOperationsFromGraph(graph: Pick<GeneratedProjectGraph, 'files'>): WorkspaceFileOperation[] {
    const operations: WorkspaceFileOperation[] = [];
    const seenDirectories = new Set<string>();

    graph.files.forEach((file) => {
        const directory = dirname(file.path);
        if (directory !== '' && !seenDirectories.has(directory)) {
            seenDirectories.add(directory);
            operations.push(buildEnsureDirectoryOperation(directory, 'graph_directory'));
        }

        operations.push(buildWriteFileOperation(file));
    });

    return operations;
}
