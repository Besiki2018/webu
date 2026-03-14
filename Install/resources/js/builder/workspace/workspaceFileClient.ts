import axios from 'axios';

import type { AiWorkspaceOperation } from '@/builder/codegen/aiWorkspaceOps';
import {
    shouldRefreshPreviewForAiWorkspaceOperation,
} from '@/builder/codegen/aiWorkspaceOps';
import type { WorkspaceManifest } from '@/builder/codegen/types';
import {
    normalizeWorkspaceManifest,
    WORKSPACE_MANIFEST_RELATIVE_PATH,
} from '@/builder/codegen/workspaceManifest';
import {
    buildWorkspaceFileRecords,
    buildWorkspaceFileSelectionMeta,
    createEmptyWorkspaceOperationLog,
    normalizeWorkspaceOperationLogDocument,
    WORKSPACE_OPERATION_LOG_RELATIVE_PATH,
    type WorkspaceFileActor,
    type WorkspaceFileRecord,
    type WorkspaceListFileEntry,
    type WorkspaceOperationLogDocument,
} from './workspaceFileState';

export interface WorkspaceFileClientOptions {
    onPreviewRefresh?: () => void | Promise<void>;
}

export interface WorkspaceFileListResult {
    files: WorkspaceFileRecord[];
    manifest: WorkspaceManifest;
    operationLog: WorkspaceOperationLogDocument;
}

export interface WorkspaceFileReadResult extends WorkspaceFileListResult {
    path: string;
    content: string;
    file: WorkspaceFileRecord | null;
}

export interface WorkspaceFileMutationResult extends WorkspaceFileListResult {
    changedPaths: string[];
}

interface WorkspaceContextPayload {
    actor: WorkspaceFileActor;
    source: string;
    operation_kind: string;
    previous_path?: string | null;
    preview_refresh_requested: boolean;
    reason?: string | null;
}

function normalizeText(value: string | null | undefined): string {
    return typeof value === 'string' ? value.trim() : '';
}

export function normalizeWorkspacePath(path: string): string {
    const rawSegments = normalizeText(path)
        .replace(/\\/g, '/')
        .split('/');
    const normalizedSegments: string[] = [];

    for (const segment of rawSegments) {
        const value = segment.trim();
        if (value === '' || value === '.') {
            continue;
        }

        if (value === '..') {
            return '';
        }

        normalizedSegments.push(value);
    }

    return normalizedSegments.join('/');
}

export function isAllowedWorkspacePath(path: string, options: { allowMetadata?: boolean } = {}): boolean {
    const normalized = normalizeWorkspacePath(path);
    if (normalized === '') {
        return false;
    }

    if (options.allowMetadata === true) {
        return normalized === WORKSPACE_MANIFEST_RELATIVE_PATH || normalized === WORKSPACE_OPERATION_LOG_RELATIVE_PATH;
    }

    return normalized === 'src'
        || normalized === 'public'
        || normalized.startsWith('src/')
        || normalized.startsWith('public/');
}

async function readWorkspaceJsonFile<T>(
    projectId: string,
    path: string,
    fallback: T,
): Promise<T> {
    try {
        const response = await axios.get<{ success?: boolean; content?: string }>(
            `/panel/projects/${projectId}/workspace/file`,
            {
                params: { path },
            },
        );
        const raw = typeof response.data?.content === 'string' ? response.data.content : '';
        if (raw.trim() === '') {
            return fallback;
        }

        return JSON.parse(raw) as T;
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 404) {
            return fallback;
        }

        throw error;
    }
}

async function loadWorkspaceMetadata(projectId: string): Promise<{
    manifest: WorkspaceManifest;
    operationLog: WorkspaceOperationLogDocument;
}> {
    const [manifestRaw, operationLogRaw] = await Promise.all([
        readWorkspaceJsonFile<Partial<WorkspaceManifest> | null>(projectId, WORKSPACE_MANIFEST_RELATIVE_PATH, null),
        readWorkspaceJsonFile<Partial<WorkspaceOperationLogDocument> | null>(projectId, WORKSPACE_OPERATION_LOG_RELATIVE_PATH, null),
    ]);

    return {
        manifest: normalizeWorkspaceManifest(manifestRaw),
        operationLog: normalizeWorkspaceOperationLogDocument(operationLogRaw ?? createEmptyWorkspaceOperationLog(projectId)),
    };
}

async function maybeRefreshPreview(
    callback: WorkspaceFileClientOptions['onPreviewRefresh'],
    shouldRefresh: boolean,
): Promise<void> {
    if (!callback || !shouldRefresh) {
        return;
    }

    await callback();
}

export function createWorkspaceFileClient(projectId: string, options: WorkspaceFileClientOptions = {}) {
    const listFiles = async (): Promise<WorkspaceFileListResult> => {
        const [filesResponse, metadata] = await Promise.all([
            axios.get<{ success?: boolean; files?: WorkspaceListFileEntry[] }>(`/panel/projects/${projectId}/workspace/files`),
            loadWorkspaceMetadata(projectId),
        ]);

        const rawFiles = Array.isArray(filesResponse.data?.files) ? filesResponse.data.files : [];

        return {
            files: buildWorkspaceFileRecords(rawFiles, metadata.manifest, metadata.operationLog),
            manifest: metadata.manifest,
            operationLog: metadata.operationLog,
        };
    };

    const readFile = async (path: string): Promise<WorkspaceFileReadResult> => {
        const normalizedPath = normalizeWorkspacePath(path);
        if (!isAllowedWorkspacePath(normalizedPath)) {
            throw new Error(`Workspace path not allowed: ${path}`);
        }

        const [fileResponse, listResult] = await Promise.all([
            axios.get<{ success?: boolean; content?: string }>(`/panel/projects/${projectId}/workspace/file`, {
                params: { path: normalizedPath },
            }),
            listFiles(),
        ]);

        const content = typeof fileResponse.data?.content === 'string' ? fileResponse.data.content : '';

        return {
            ...listResult,
            path: normalizedPath,
            content,
            file: listResult.files.find((entry) => entry.path === normalizedPath) ?? null,
        };
    };

    const writeFile = async (
        path: string,
        content: string,
        input: {
            actor?: WorkspaceFileActor;
            source?: string;
            reason?: string | null;
        } = {},
    ): Promise<WorkspaceFileMutationResult> => {
        const normalizedPath = normalizeWorkspacePath(path);
        if (!isAllowedWorkspacePath(normalizedPath)) {
            throw new Error(`Workspace path not allowed: ${path}`);
        }

        const current = await axios.get<{ success?: boolean; content?: string }>(
            `/panel/projects/${projectId}/workspace/file`,
            {
                params: { path: normalizedPath },
            },
        ).catch((error) => {
            if (axios.isAxiosError(error) && error.response?.status === 404) {
                return null;
            }

            throw error;
        });
        const operationKind = typeof current?.data?.content === 'string' ? 'update_file' : 'create_file';
        const workspaceContext: WorkspaceContextPayload = {
            actor: input.actor ?? 'user',
            source: input.source ?? 'code_editor',
            operation_kind: operationKind,
            preview_refresh_requested: true,
            reason: input.reason ?? null,
        };

        await axios.post(`/panel/projects/${projectId}/workspace/file`, {
            path: normalizedPath,
            content,
            workspace_context: workspaceContext,
        });

        await maybeRefreshPreview(options.onPreviewRefresh, true);

        const next = await listFiles();
        return {
            ...next,
            changedPaths: [normalizedPath],
        };
    };

    const createFile = async (
        path: string,
        content = '',
        input: {
            actor?: WorkspaceFileActor;
            source?: string;
            reason?: string | null;
        } = {},
    ): Promise<WorkspaceFileMutationResult> => {
        return writeFile(path, content, {
            actor: input.actor ?? 'user',
            source: input.source ?? 'code_editor',
            reason: input.reason ?? null,
        });
    };

    const deleteFile = async (
        path: string,
        input: {
            actor?: WorkspaceFileActor;
            source?: string;
            reason?: string | null;
        } = {},
    ): Promise<WorkspaceFileMutationResult> => {
        const normalizedPath = normalizeWorkspacePath(path);
        if (!isAllowedWorkspacePath(normalizedPath)) {
            throw new Error(`Workspace path not allowed: ${path}`);
        }

        await axios.delete(`/panel/projects/${projectId}/workspace/file`, {
            data: {
                path: normalizedPath,
                workspace_context: {
                    actor: input.actor ?? 'user',
                    source: input.source ?? 'code_editor',
                    operation_kind: 'delete_file',
                    preview_refresh_requested: true,
                    reason: input.reason ?? null,
                } satisfies WorkspaceContextPayload,
            },
        });

        await maybeRefreshPreview(options.onPreviewRefresh, true);

        const next = await listFiles();
        return {
            ...next,
            changedPaths: [normalizedPath],
        };
    };

    const renameFile = async (
        fromPath: string,
        toPath: string,
        input: {
            actor?: WorkspaceFileActor;
            source?: string;
            reason?: string | null;
        } = {},
    ): Promise<WorkspaceFileMutationResult> => {
        const normalizedFrom = normalizeWorkspacePath(fromPath);
        const normalizedTo = normalizeWorkspacePath(toPath);
        if (!isAllowedWorkspacePath(normalizedFrom) || !isAllowedWorkspacePath(normalizedTo)) {
            throw new Error(`Workspace path not allowed: ${fromPath} -> ${toPath}`);
        }

        await axios.post(`/panel/projects/${projectId}/workspace/file/move`, {
            from_path: normalizedFrom,
            to_path: normalizedTo,
            workspace_context: {
                actor: input.actor ?? 'user',
                source: input.source ?? 'code_editor',
                operation_kind: 'move_file',
                previous_path: normalizedFrom,
                preview_refresh_requested: true,
                reason: input.reason ?? null,
            } satisfies WorkspaceContextPayload,
        });

        await maybeRefreshPreview(options.onPreviewRefresh, true);

        const next = await listFiles();
        return {
            ...next,
            changedPaths: [normalizedFrom, normalizedTo],
        };
    };

    const applyOperation = async (
        operation: AiWorkspaceOperation,
        input: {
            actor?: WorkspaceFileActor;
            source?: string;
        } = {},
    ): Promise<WorkspaceFileMutationResult> => {
        switch (operation.kind) {
            case 'create_file':
                return createFile(operation.path, operation.content, {
                    actor: input.actor ?? 'ai',
                    source: input.source ?? 'ai_workspace',
                    reason: operation.reason ?? null,
                });
            case 'update_file':
                return writeFile(operation.path, operation.content, {
                    actor: input.actor ?? 'ai',
                    source: input.source ?? 'ai_workspace',
                    reason: operation.reason ?? null,
                });
            case 'delete_file':
                return deleteFile(operation.path, {
                    actor: input.actor ?? 'ai',
                    source: input.source ?? 'ai_workspace',
                    reason: operation.reason ?? null,
                });
            case 'move_file':
                return renameFile(operation.fromPath, operation.toPath, {
                    actor: input.actor ?? 'ai',
                    source: input.source ?? 'ai_workspace',
                    reason: operation.reason ?? null,
                });
            case 'scaffold_project': {
                await axios.post(`/panel/projects/${projectId}/workspace/initialize`);
                await maybeRefreshPreview(options.onPreviewRefresh, shouldRefreshPreviewForAiWorkspaceOperation(operation));
                const next = await listFiles();
                return {
                    ...next,
                    changedPaths: [],
                };
            }
            case 'apply_patch_set': {
                let latest: WorkspaceFileMutationResult | null = null;
                const changedPaths = new Set<string>();
                for (const nested of operation.operations) {
                    latest = await applyOperation(nested, input);
                    latest.changedPaths.forEach((path) => {
                        changedPaths.add(path);
                    });
                }

                if (!latest) {
                    const next = await listFiles();
                    return {
                        ...next,
                        changedPaths: [],
                    };
                }

                await maybeRefreshPreview(options.onPreviewRefresh, shouldRefreshPreviewForAiWorkspaceOperation(operation));
                return {
                    ...latest,
                    changedPaths: Array.from(changedPaths),
                };
            }
            default: {
                const exhaustive: never = operation;
                throw new Error(`Unsupported workspace operation: ${String(exhaustive)}`);
            }
        }
    };

    return {
        listFiles,
        readFile,
        writeFile,
        createFile,
        deleteFile,
        renameFile,
        applyOperation,
        buildSelectionMeta: buildWorkspaceFileSelectionMeta,
    };
}
