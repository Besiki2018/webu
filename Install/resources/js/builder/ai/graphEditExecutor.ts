import {
    changeSetHasUnsyncedOperations,
    extractPreviewLayoutOverrides,
    getBuilderSyncableChangeSet,
    type AgentChangeSet,
    type PreviewLayoutOverrides,
} from '@/lib/agentChangeSet';
import type { BuilderBridgeStateCursor } from '@/builder/cms/chatBuilderMutationFlow';

export type AiLayeredEditExecutionStatus = 'applied' | 'noop' | 'failed' | 'conflicted';
export type GraphEditIntent = 'structure_change' | 'page_change';
export type GraphEditAssistantKind = 'changed_page_structure' | 'changed_pages' | 'could_not_safely_apply';

export interface AiLayeredEditRevisionState {
    manifestUpdatedAt: string | null;
    activeGenerationRunId: string | null;
    builderStateCursor: BuilderBridgeStateCursor | null;
    pageId: number | null;
    pageSlug: string | null;
}

export type AiLayeredEditConflictReason =
    | 'manifest_advanced'
    | 'builder_state_advanced'
    | 'page_context_changed';

export interface AiLayeredEditConflict {
    reason: AiLayeredEditConflictReason;
    message: string;
    expected: AiLayeredEditRevisionState;
    current: AiLayeredEditRevisionState;
}

export interface GraphChangeSetExecuteResponse {
    success: boolean;
    change_set?: AgentChangeSet | null;
    summary?: string[];
    action_log?: string[];
    diagnostic_log?: string[];
    error?: string;
}

export interface GraphPageExecuteResponse {
    success: boolean;
    summary?: string | string[];
    changes?: Array<{ path: string; op: string }>;
    created?: string[];
    updated?: string[];
    deleted?: string[];
    diagnostic_log?: string[];
    error?: string;
    no_change_reason?: string;
    files_changed?: boolean;
}

interface GraphEditExecutionInputBase {
    expectedRevision?: AiLayeredEditRevisionState | null;
    getCurrentRevision: () => Promise<AiLayeredEditRevisionState>;
}

export interface ExecuteStructureGraphEditInput extends GraphEditExecutionInputBase {
    intent: 'structure_change';
    execute: () => Promise<GraphChangeSetExecuteResponse>;
}

export interface ExecutePageGraphEditInput extends GraphEditExecutionInputBase {
    intent: 'page_change';
    execute: () => Promise<GraphPageExecuteResponse>;
}

export type ExecuteGraphEditInput =
    | ExecuteStructureGraphEditInput
    | ExecutePageGraphEditInput;

export interface GraphEditExecutionResult {
    status: AiLayeredEditExecutionStatus;
    intent: GraphEditIntent;
    assistantKind: GraphEditAssistantKind;
    summary: string | null;
    note: string | null;
    details: string[];
    diagnosticLog: string[];
    conflict: AiLayeredEditConflict | null;
    syncableChangeSet: AgentChangeSet | null;
    hasUnsyncedOps: boolean;
    previewOverrides: PreviewLayoutOverrides | null;
}

function normalizeText(value: string | null | undefined): string {
    return typeof value === 'string' ? value.trim() : '';
}

function normalizeNullableNumber(value: number | null | undefined): number | null {
    return typeof value === 'number' && Number.isFinite(value) ? value : null;
}

function normalizeCursor(cursor: BuilderBridgeStateCursor | null | undefined): BuilderBridgeStateCursor | null {
    if (!cursor) {
        return null;
    }

    return {
        pageId: normalizeNullableNumber(cursor.pageId),
        pageSlug: normalizeText(cursor.pageSlug) || null,
        stateVersion: normalizeNullableNumber(cursor.stateVersion),
        revisionVersion: normalizeNullableNumber(cursor.revisionVersion),
    };
}

function pageContextMatches(
    expected: AiLayeredEditRevisionState,
    current: AiLayeredEditRevisionState,
): boolean {
    const expectedPageId = normalizeNullableNumber(expected.pageId);
    const currentPageId = normalizeNullableNumber(current.pageId);

    if (expectedPageId !== null && currentPageId !== null) {
        return expectedPageId === currentPageId;
    }

    const expectedSlug = normalizeText(expected.pageSlug).toLowerCase();
    const currentSlug = normalizeText(current.pageSlug).toLowerCase();

    if (expectedSlug !== '' && currentSlug !== '') {
        return expectedSlug === currentSlug;
    }

    return true;
}

export function detectAiLayeredEditConflict(
    expected: AiLayeredEditRevisionState | null | undefined,
    current: AiLayeredEditRevisionState,
): AiLayeredEditConflict | null {
    if (!expected) {
        return null;
    }

    if (!pageContextMatches(expected, current)) {
        return {
            reason: 'page_context_changed',
            message: 'The active page changed before the AI edit could be applied.',
            expected,
            current,
        };
    }

    if (
        normalizeText(expected.activeGenerationRunId) !== ''
        && normalizeText(current.activeGenerationRunId) !== ''
        && normalizeText(expected.activeGenerationRunId) !== normalizeText(current.activeGenerationRunId)
    ) {
        return {
            reason: 'manifest_advanced',
            message: 'Workspace generation moved ahead while preparing this AI edit.',
            expected,
            current,
        };
    }

    if (
        normalizeText(expected.manifestUpdatedAt) !== ''
        && normalizeText(current.manifestUpdatedAt) !== ''
        && normalizeText(expected.manifestUpdatedAt) !== normalizeText(current.manifestUpdatedAt)
    ) {
        return {
            reason: 'manifest_advanced',
            message: 'Workspace files changed after the AI request context was captured.',
            expected,
            current,
        };
    }

    const expectedCursor = normalizeCursor(expected.builderStateCursor);
    const currentCursor = normalizeCursor(current.builderStateCursor);
    if (!expectedCursor || !currentCursor) {
        return null;
    }

    if (
        expectedCursor.pageId !== null
        && currentCursor.pageId !== null
        && expectedCursor.pageId !== currentCursor.pageId
    ) {
        return {
            reason: 'page_context_changed',
            message: 'Builder selection moved to another page before the AI edit was applied.',
            expected,
            current,
        };
    }

    if (
        expectedCursor.stateVersion !== null
        && currentCursor.stateVersion !== null
        && currentCursor.stateVersion > expectedCursor.stateVersion
    ) {
        return {
            reason: 'builder_state_advanced',
            message: 'Builder state changed after the AI request context was captured.',
            expected,
            current,
        };
    }

    if (
        expectedCursor.revisionVersion !== null
        && currentCursor.revisionVersion !== null
        && currentCursor.revisionVersion > expectedCursor.revisionVersion
    ) {
        return {
            reason: 'builder_state_advanced',
            message: 'A newer page revision exists, so this AI edit would be stale.',
            expected,
            current,
        };
    }

    return null;
}

function normalizeDetails(details: Array<string | null | undefined>): string[] {
    return details
        .map((detail) => normalizeText(detail))
        .filter((detail) => detail !== '');
}

function buildFileOperationDetails(response: GraphPageExecuteResponse): string[] {
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
    intent: GraphEditIntent,
    conflict: AiLayeredEditConflict,
): GraphEditExecutionResult {
    return {
        status: 'conflicted',
        intent,
        assistantKind: 'could_not_safely_apply',
        summary: null,
        note: conflict.message,
        details: [],
        diagnosticLog: [],
        conflict,
        syncableChangeSet: null,
        hasUnsyncedOps: false,
        previewOverrides: null,
    };
}

export async function executeGraphEdit(input: ExecuteGraphEditInput): Promise<GraphEditExecutionResult> {
    const currentRevision = await input.getCurrentRevision();
    const conflict = detectAiLayeredEditConflict(input.expectedRevision, currentRevision);
    if (conflict) {
        return createConflictResult(input.intent, conflict);
    }

    if (input.intent === 'structure_change') {
        const response = await input.execute();
        const summary = normalizeDetails(response.summary ?? []);
        const actionLog = normalizeDetails(response.action_log ?? []);
        const details = actionLog.length > 0 ? actionLog : summary;
        const changeSet = response.change_set ?? null;
        const syncableChangeSet = getBuilderSyncableChangeSet(changeSet);
        const hasUnsyncedOps = changeSetHasUnsyncedOperations(changeSet);
        const previewOverrides = extractPreviewLayoutOverrides(changeSet);

        if (!response.success) {
            return {
                status: 'failed',
                intent: input.intent,
                assistantKind: 'changed_page_structure',
                summary: null,
                note: normalizeText(response.error) || 'The page structure change could not be applied.',
                details,
                diagnosticLog: normalizeDetails(response.diagnostic_log ?? []),
                conflict: null,
                syncableChangeSet,
                hasUnsyncedOps,
                previewOverrides,
            };
        }

        if ((changeSet?.operations?.length ?? 0) === 0 && details.length === 0) {
            return {
                status: 'noop',
                intent: input.intent,
                assistantKind: 'changed_page_structure',
                summary: null,
                note: 'No page-structure changes were necessary.',
                details: [],
                diagnosticLog: normalizeDetails(response.diagnostic_log ?? []),
                conflict: null,
                syncableChangeSet,
                hasUnsyncedOps,
                previewOverrides,
            };
        }

        return {
            status: 'applied',
            intent: input.intent,
            assistantKind: 'changed_page_structure',
            summary: summary[0] ?? null,
            note: summary[0] ?? 'Updated the workspace-backed page structure.',
            details,
            diagnosticLog: normalizeDetails(response.diagnostic_log ?? []),
            conflict: null,
            syncableChangeSet,
            hasUnsyncedOps,
            previewOverrides,
        };
    }

    const response = await input.execute();
    const details = buildFileOperationDetails(response);
    const summary = Array.isArray(response.summary)
        ? normalizeDetails(response.summary)[0] ?? null
        : normalizeText(response.summary) || null;
    const diagnosticLog = normalizeDetails(response.diagnostic_log ?? []);

    if (!response.success) {
        return {
            status: 'failed',
            intent: input.intent,
            assistantKind: 'changed_pages',
            summary: null,
            note: normalizeText(response.error) || 'The page change could not be applied.',
            details,
            diagnosticLog,
            conflict: null,
            syncableChangeSet: null,
            hasUnsyncedOps: true,
            previewOverrides: null,
        };
    }

    if (details.length === 0 && response.files_changed !== true) {
        return {
            status: 'noop',
            intent: input.intent,
            assistantKind: 'changed_pages',
            summary,
            note: normalizeText(response.no_change_reason) || summary || 'No page changes were necessary.',
            details: [],
            diagnosticLog,
            conflict: null,
            syncableChangeSet: null,
            hasUnsyncedOps: true,
            previewOverrides: null,
        };
    }

    return {
        status: 'applied',
        intent: input.intent,
        assistantKind: 'changed_pages',
        summary,
        note: summary || 'Updated page structure in the workspace-backed project.',
        details,
        diagnosticLog,
        conflict: null,
        syncableChangeSet: null,
        hasUnsyncedOps: true,
        previewOverrides: null,
    };
}
