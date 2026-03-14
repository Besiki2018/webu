import type {
    AiLayeredEditConflict,
    AiLayeredEditExecutionStatus,
    AiLayeredEditRevisionState,
} from './graphEditExecutor';
import { detectAiLayeredEditConflict } from './graphEditExecutor';

export type WorkspaceEditIntent = 'file_change' | 'regeneration_request';
export type WorkspaceEditAssistantKind = 'modified_workspace_files' | 'regenerated_workspace' | 'could_not_safely_apply';

export interface WorkspaceFileEditResponse {
    success: boolean;
    summary?: string | null;
    changes?: Array<{ path: string; op: string }>;
    created?: string[];
    updated?: string[];
    deleted?: string[];
    diagnostic_log?: string[];
    error?: string;
    no_change_reason?: string;
    files_changed?: boolean;
}

export interface WorkspaceRegenerationResponse {
    success?: boolean;
    summary?: string | null;
    changed_paths?: string[];
    error?: string;
}

interface WorkspaceEditExecutionInputBase {
    expectedRevision?: AiLayeredEditRevisionState | null;
    getCurrentRevision: () => Promise<AiLayeredEditRevisionState>;
}

export interface ExecuteWorkspaceFileEditInput extends WorkspaceEditExecutionInputBase {
    intent: 'file_change';
    execute: () => Promise<WorkspaceFileEditResponse>;
}

export interface ExecuteWorkspaceRegenerationInput extends WorkspaceEditExecutionInputBase {
    intent: 'regeneration_request';
    execute: () => Promise<WorkspaceRegenerationResponse>;
}

export type ExecuteWorkspaceEditInput =
    | ExecuteWorkspaceFileEditInput
    | ExecuteWorkspaceRegenerationInput;

export interface WorkspaceEditExecutionResult {
    status: AiLayeredEditExecutionStatus;
    intent: WorkspaceEditIntent;
    assistantKind: WorkspaceEditAssistantKind;
    summary: string | null;
    note: string | null;
    details: string[];
    diagnosticLog: string[];
    conflict: AiLayeredEditConflict | null;
    changedPaths: string[];
    shouldRefreshWorkspace: boolean;
}

function normalizeText(value: string | null | undefined): string {
    return typeof value === 'string' ? value.trim() : '';
}

function normalizeDetails(details: Array<string | null | undefined>): string[] {
    return details
        .map((detail) => normalizeText(detail))
        .filter((detail) => detail !== '');
}

function buildWorkspaceChangeDetails(response: WorkspaceFileEditResponse): string[] {
    const changes = Array.isArray(response.changes) ? response.changes : [];
    if (changes.length > 0) {
        return changes
            .map((change) => {
                const op = normalizeText(change.op);
                const path = normalizeText(change.path);
                return op !== '' && path !== '' ? `${op}: ${path}` : '';
            })
            .filter(Boolean);
    }

    const created = Array.isArray(response.created) ? response.created.map((path) => `createFile: ${path}`) : [];
    const updated = Array.isArray(response.updated) ? response.updated.map((path) => `updateFile: ${path}`) : [];
    const deleted = Array.isArray(response.deleted) ? response.deleted.map((path) => `deleteFile: ${path}`) : [];

    return [...created, ...updated, ...deleted];
}

function createConflictResult(
    intent: WorkspaceEditIntent,
    conflict: AiLayeredEditConflict,
): WorkspaceEditExecutionResult {
    return {
        status: 'conflicted',
        intent,
        assistantKind: 'could_not_safely_apply',
        summary: null,
        note: conflict.message,
        details: [],
        diagnosticLog: [],
        conflict,
        changedPaths: [],
        shouldRefreshWorkspace: false,
    };
}

export async function executeWorkspaceEdit(input: ExecuteWorkspaceEditInput): Promise<WorkspaceEditExecutionResult> {
    const currentRevision = await input.getCurrentRevision();
    const conflict = detectAiLayeredEditConflict(input.expectedRevision, currentRevision);
    if (conflict) {
        return createConflictResult(input.intent, conflict);
    }

    if (input.intent === 'file_change') {
        const response = await input.execute();
        const details = buildWorkspaceChangeDetails(response);
        const diagnosticLog = normalizeDetails(response.diagnostic_log ?? []);
        const summary = normalizeText(response.summary) || null;
        const changedPaths = Array.from(new Set(
            details.map((detail) => detail.split(':').slice(1).join(':').trim()).filter(Boolean),
        ));

        if (!response.success) {
            return {
                status: 'failed',
                intent: input.intent,
                assistantKind: 'modified_workspace_files',
                summary: null,
                note: normalizeText(response.error) || 'The workspace file edit could not be applied.',
                details,
                diagnosticLog,
                conflict: null,
                changedPaths,
                shouldRefreshWorkspace: false,
            };
        }

        if (details.length === 0 && response.files_changed !== true) {
            return {
                status: 'noop',
                intent: input.intent,
                assistantKind: 'modified_workspace_files',
                summary,
                note: normalizeText(response.no_change_reason) || summary || 'No workspace file changes were necessary.',
                details: [],
                diagnosticLog,
                conflict: null,
                changedPaths: [],
                shouldRefreshWorkspace: false,
            };
        }

        return {
            status: 'applied',
            intent: input.intent,
            assistantKind: 'modified_workspace_files',
            summary,
            note: summary || 'Updated workspace files.',
            details,
            diagnosticLog,
            conflict: null,
            changedPaths,
            shouldRefreshWorkspace: true,
        };
    }

    const response = await input.execute();
    const changedPaths = Array.isArray(response.changed_paths)
        ? normalizeDetails(response.changed_paths)
        : [];
    const summary = normalizeText(response.summary) || null;

    if (response.success !== true) {
        return {
            status: 'failed',
            intent: input.intent,
            assistantKind: 'regenerated_workspace',
            summary: null,
            note: normalizeText(response.error) || 'Workspace regeneration failed.',
            details: changedPaths,
            diagnosticLog: [],
            conflict: null,
            changedPaths: [],
            shouldRefreshWorkspace: false,
        };
    }

    if (changedPaths.length === 0 && summary === null) {
        return {
            status: 'noop',
            intent: input.intent,
            assistantKind: 'regenerated_workspace',
            summary: null,
            note: 'Workspace code was already up to date.',
            details: [],
            diagnosticLog: [],
            conflict: null,
            changedPaths: [],
            shouldRefreshWorkspace: false,
        };
    }

    return {
        status: 'applied',
        intent: input.intent,
        assistantKind: 'regenerated_workspace',
        summary,
        note: summary || 'Regenerated workspace files from the canonical site state.',
        details: changedPaths,
        diagnosticLog: [],
        conflict: null,
        changedPaths,
        shouldRefreshWorkspace: true,
    };
}
