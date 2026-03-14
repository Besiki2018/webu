export type AiWorkspaceOperationKind =
    | 'create_file'
    | 'update_file'
    | 'delete_file'
    | 'move_file'
    | 'scaffold_project'
    | 'apply_patch_set';

export interface AiWorkspaceOperationBase {
    kind: AiWorkspaceOperationKind;
    summary?: string | null;
    reason?: string | null;
    previewStrategy?: 'auto' | 'skip' | 'force';
}

export interface AiWorkspaceCreateFileOperation extends AiWorkspaceOperationBase {
    kind: 'create_file';
    path: string;
    content: string;
}

export interface AiWorkspaceUpdateFileOperation extends AiWorkspaceOperationBase {
    kind: 'update_file';
    path: string;
    content: string;
}

export interface AiWorkspaceDeleteFileOperation extends AiWorkspaceOperationBase {
    kind: 'delete_file';
    path: string;
}

export interface AiWorkspaceMoveFileOperation extends AiWorkspaceOperationBase {
    kind: 'move_file';
    fromPath: string;
    toPath: string;
}

export interface AiWorkspaceScaffoldProjectOperation extends AiWorkspaceOperationBase {
    kind: 'scaffold_project';
}

export interface AiWorkspaceApplyPatchSetOperation extends AiWorkspaceOperationBase {
    kind: 'apply_patch_set';
    operations: AiWorkspaceWriteOperation[];
}

export type AiWorkspaceWriteOperation =
    | AiWorkspaceCreateFileOperation
    | AiWorkspaceUpdateFileOperation
    | AiWorkspaceDeleteFileOperation
    | AiWorkspaceMoveFileOperation;

export type AiWorkspaceOperation =
    | AiWorkspaceWriteOperation
    | AiWorkspaceScaffoldProjectOperation
    | AiWorkspaceApplyPatchSetOperation;

export function normalizeAiWorkspaceOperationKind(value: string | null | undefined): AiWorkspaceOperationKind | null {
    switch ((value ?? '').trim().toLowerCase()) {
        case 'create_file':
        case 'update_file':
        case 'delete_file':
        case 'move_file':
        case 'scaffold_project':
        case 'apply_patch_set':
            return value!.trim().toLowerCase() as AiWorkspaceOperationKind;
        default:
            return null;
    }
}

export function collectAiWorkspaceOperationPaths(operation: AiWorkspaceOperation): string[] {
    switch (operation.kind) {
        case 'create_file':
        case 'update_file':
        case 'delete_file':
            return [operation.path];
        case 'move_file':
            return [operation.fromPath, operation.toPath];
        case 'apply_patch_set':
            return operation.operations.flatMap((entry) => collectAiWorkspaceOperationPaths(entry));
        case 'scaffold_project':
        default:
            return [];
    }
}

export function shouldRefreshPreviewForAiWorkspaceOperation(operation: AiWorkspaceOperation): boolean {
    if (operation.previewStrategy === 'skip') {
        return false;
    }

    if (operation.previewStrategy === 'force') {
        return true;
    }

    return operation.kind !== 'scaffold_project';
}
