import { useCallback, useRef, useState } from 'react';
import axios from 'axios';
import { resolveAiCommandLocale } from '@/lib/chatLocale';
import { buildAiPageContextPayload, hasExplicitAiPageTarget } from '@/lib/aiSiteEditorPageContext';
import type { SelectedTargetContext } from '@/builder/selectedTargetContext';
import type {
    BuilderBreakpoint,
    BuilderInteractionState,
    BuilderTargetAllowedUpdates,
    BuilderTargetResponsiveContext,
    BuilderTargetVariants,
} from '@/builder/editingState';

export interface PageStructure {
    id: number;
    slug: string;
    title: string;
    sections: Array<{
        id: string;
        type: string;
        label: string;
        editable_fields?: string[];
        props?: Record<string, unknown>;
    }>;
}

export interface AnalyzeResult {
    success: boolean;
    pages?: PageStructure[];
    global_components?: Array<{ id: string; label: string; editable_fields?: string[] }>;
    /** Registry component IDs the AI may add (insertSection). */
    available_components?: string[];
    error?: string;
}

export interface ChangeSet {
    operations: Array<Record<string, unknown>>;
    summary?: string[];
}

export interface ExecuteResult {
    success: boolean;
    replay?: boolean;
    change_set?: ChangeSet;
    page?: { id: number; slug: string; status: string };
    revision?: {
        id: number;
        version: number;
        published_at?: string | null;
        content_json?: unknown;
    };
    summary?: string[];
    /** Human-readable list of what was done (agent result log) */
    action_log?: string[];
    applied_changes?: Array<{
        op?: string;
        section_id?: string;
        component?: string;
        summary?: string[];
        old_value?: unknown;
        new_value?: unknown;
    }>;
    /** Section localIds to highlight in preview after apply */
    highlight_section_ids?: string[];
    /** Backend diagnostic trace for why a change did or did not apply */
    diagnostic_log?: string[];
    error?: string;
    /** Failure reason code for UI (e.g. page_not_found, site_operations_failed) */
    error_code?: string;
}

export interface InterpretResult {
    success: boolean;
    change_set?: ChangeSet;
    summary?: string[];
    error?: string;
}

/**
 * AI Site Editor: analyze page structure, interpret natural language commands,
 * and execute change sets against the CMS.
 */
export function useAiSiteEditor(projectId: string) {
    const [isAnalyzing, setIsAnalyzing] = useState(false);
    const [isInterpreting, setIsInterpreting] = useState(false);
    const [isExecuting, setIsExecuting] = useState(false);
    const [lastError, setLastError] = useState<string | null>(null);
    const analyzeRequestSeqRef = useRef(0);
    const interpretRequestSeqRef = useRef(0);
    const executeRequestSeqRef = useRef(0);

    const analyze = useCallback(async (locale?: string): Promise<AnalyzeResult> => {
        const requestSeq = analyzeRequestSeqRef.current + 1;
        analyzeRequestSeqRef.current = requestSeq;
        setIsAnalyzing(true);
        setLastError(null);
        try {
            const url = `/panel/projects/${projectId}/ai-site-editor/analyze`;
            const params = locale ? { locale } : {};
            const { data } = await axios.get<AnalyzeResult>(url, { params });
            return data;
        } catch (err) {
            const message = axios.isAxiosError(err)
                ? (err.response?.data?.error as string) || err.message
                : String(err);
            if (analyzeRequestSeqRef.current === requestSeq) {
                setLastError(message);
            }
            return { success: false, error: message };
        } finally {
            if (analyzeRequestSeqRef.current === requestSeq) {
                setIsAnalyzing(false);
            }
        }
    }, [projectId]);

    const interpret = useCallback(async (
        instruction: string,
        pageContext?: {
        page_slug?: string;
        page_id?: number;
        sections?: Array<{ id: string; type: string; label?: string; editable_fields?: string[]; props?: Record<string, unknown> }>;
        component_types?: string[];
        global_components?: Array<{ id: string; label?: string; editable_fields?: string[] }>;
        locale?: string;
        selected_target?: SelectedTargetContext | null;
        /** Recent edits in this session; AI uses this to resolve "it", "the title", "make it shorter", etc. */
        recent_edits?: string;
    }
    ): Promise<InterpretResult> => {
        const requestSeq = interpretRequestSeqRef.current + 1;
        interpretRequestSeqRef.current = requestSeq;
        setIsInterpreting(true);
        setLastError(null);
        try {
            const { data } = await axios.post<InterpretResult>(
                `/panel/projects/${projectId}/ai-interpret-command`,
                { instruction, page_context: pageContext ?? {} }
            );
            return data;
        } catch (err) {
            const message = axios.isAxiosError(err)
                ? (err.response?.data?.error as string) || err.message
                : String(err);
            if (interpretRequestSeqRef.current === requestSeq) {
                setLastError(message);
            }
            return { success: false, error: message };
        } finally {
            if (interpretRequestSeqRef.current === requestSeq) {
                setIsInterpreting(false);
            }
        }
    }, [projectId]);

    const execute = useCallback(async (
        changeSet: ChangeSet,
        options?: { page_id?: number; page_slug?: string; instruction?: string; publish?: boolean; locale?: string; selected_target?: SelectedTargetContext | null }
    ): Promise<ExecuteResult> => {
        const requestSeq = executeRequestSeqRef.current + 1;
        executeRequestSeqRef.current = requestSeq;
        setIsExecuting(true);
        setLastError(null);
        const payload = {
            change_set: changeSet,
            page_id: options?.page_id,
            page_slug: options?.page_slug,
            locale: options?.locale,
            instruction: options?.instruction,
            publish: options?.publish ?? false,
            selected_target: options?.selected_target ?? null,
        };
        try {
            const { data } = await axios.post<ExecuteResult>(
                `/panel/projects/${projectId}/ai-site-editor/execute`,
                payload
            );
            if (!data.success) {
                const message = data.error?.trim() || 'Failed to apply changes.';
                if (executeRequestSeqRef.current === requestSeq) {
                    setLastError(message);
                }
                return {
                    ...data,
                    error: message,
                    diagnostic_log: Array.isArray(data.diagnostic_log) ? data.diagnostic_log : undefined,
                };
            }
            return data;
        } catch (err) {
            if (axios.isAxiosError(err)) {
                const status = err.response?.status;
                const body = err.response?.data;
                console.error('[AI Site Editor] execute failed', {
                    status,
                    statusText: err.response?.statusText,
                    error: body?.error,
                    error_code: body?.error_code,
                    errors: body?.errors,
                    validation: body?.message,
                    sent: {
                        projectId,
                        page_id: payload.page_id,
                        page_slug: payload.page_slug,
                        operationsCount: changeSet?.operations?.length ?? 0,
                        hasSummary: Array.isArray(changeSet?.summary),
                    },
                });
            } else {
                console.error('[AI Site Editor] execute unexpected error', err);
            }
            const data = axios.isAxiosError(err) ? err.response?.data : undefined;
            const message = (data?.message as string) || (data?.error as string) || (axios.isAxiosError(err) ? err.message : String(err));
            const errorCode = data?.error_code as string | undefined;
            if (executeRequestSeqRef.current === requestSeq) {
                setLastError(message);
            }
            return {
                success: false,
                error: message,
                error_code: errorCode,
                diagnostic_log: Array.isArray(data?.diagnostic_log) ? data.diagnostic_log : undefined,
            };
        } finally {
            if (executeRequestSeqRef.current === requestSeq) {
                setIsExecuting(false);
            }
        }
    }, [projectId]);

    /**
     * Unified agent: single request to backend orchestrator.
     * Replaces split analyze → interpret → execute. Georgian-first, single planning state.
     */
    const runUnifiedEdit = useCallback(async (
        instruction: string,
        options?: {
            page_slug?: string;
            page_id?: number;
            publish?: boolean;
            locale?: string;
            selected_target?: SelectedTargetContext | null;
            recent_edits?: string;
        }
    ): Promise<ExecuteResult> => {
        const requestSeq = executeRequestSeqRef.current + 1;
        executeRequestSeqRef.current = requestSeq;
        setIsAnalyzing(true);
        setIsInterpreting(true);
        setIsExecuting(true);
        setLastError(null);
        try {
            const { data } = await axios.post<ExecuteResult>(
                `/panel/projects/${projectId}/unified-agent/edit`,
                {
                    instruction,
                    page_slug: options?.page_slug ?? null,
                    page_id: options?.page_id ?? null,
                    locale: options?.locale ?? resolveAiCommandLocale(instruction, options?.locale),
                    selected_target: options?.selected_target ?? null,
                    recent_edits: options?.recent_edits ?? null,
                    project_mode: 'builder',
                    publish: options?.publish ?? false,
                }
            );
            if (!data.success && requestSeq === executeRequestSeqRef.current) {
                setLastError(data.error ?? 'Request failed.');
            }
            return data;
        } catch (err) {
            const msg = axios.isAxiosError(err)
                ? (err.response?.data?.error as string) || err.message
                : String(err);
            if (requestSeq === executeRequestSeqRef.current) setLastError(msg);
            return {
                success: false,
                error: msg,
                error_code: (axios.isAxiosError(err) && err.response?.data?.error_code) as string | undefined,
                diagnostic_log: axios.isAxiosError(err) && Array.isArray(err.response?.data?.diagnostic_log)
                    ? err.response.data.diagnostic_log
                    : undefined,
            };
        } finally {
            if (requestSeq === executeRequestSeqRef.current) {
                setIsAnalyzing(false);
                setIsInterpreting(false);
                setIsExecuting(false);
            }
        }
    }, [projectId]);

    /**
     * Run full pipeline: analyze → interpret(instruction) → execute(change_set).
     * Resolves the exact requested page by page_id/page_slug before interpret/execute.
     */
    const interpretAndExecute = useCallback(async (
        instruction: string,
        options?: {
            page_slug?: string;
            page_id?: number;
            publish?: boolean;
            locale?: string;
            selected_target?: SelectedTargetContext | null;
            recent_edits?: string;
            selected_section_id?: string | null;
            selected_parameter_path?: string | null;
            selected_element_id?: string | null;
        }
    ): Promise<ExecuteResult> => {
        setLastError(null);
        const commandLocale = resolveAiCommandLocale(instruction, options?.locale);
        const analyzeResult = await analyze(commandLocale);
        if (!analyzeResult.success || !analyzeResult.pages?.length) {
            return {
                success: false,
                error: (analyzeResult as AnalyzeResult & { error?: string }).error ?? 'Could not load page structure.',
            };
        }
        const pageContext = buildAiPageContextPayload(analyzeResult, {
            pageId: options?.page_id ?? null,
            pageSlug: options?.page_slug ?? null,
            locale: commandLocale,
            selectedTarget: options?.selected_target ?? null,
            recentEdits: options?.recent_edits ?? null,
            selectedSectionId: options?.selected_section_id ?? null,
            selectedParameterPath: options?.selected_parameter_path ?? null,
            selectedElementId: options?.selected_element_id ?? null,
        });
        if (hasExplicitAiPageTarget({
            pageId: options?.page_id ?? null,
            pageSlug: options?.page_slug ?? null,
        }) && pageContext === null) {
            return {
                success: false,
                error: 'Selected page could not be resolved.',
            };
        }
        const pageSlug = typeof pageContext?.page_slug === 'string' ? pageContext.page_slug : 'home';
        const pageId = typeof pageContext?.page_id === 'number' ? pageContext.page_id : undefined;

        const interpretResult = await interpret(instruction, pageContext ?? undefined);
        if (!interpretResult.success || !interpretResult.change_set) {
            return {
                success: false,
                error: interpretResult.error ?? 'Could not interpret command.',
            };
        }

        return execute(interpretResult.change_set, {
            page_id: pageId,
            page_slug: pageSlug,
            locale: commandLocale,
            instruction,
            publish: options?.publish ?? false,
            selected_target: options?.selected_target ?? null,
        });
    }, [analyze, interpret, execute]);

    return {
        analyze,
        interpret,
        execute,
        runUnifiedEdit,
        interpretAndExecute,
        isAnalyzing,
        isInterpreting,
        isExecuting,
        isBusy: isAnalyzing || isInterpreting || isExecuting,
        lastError,
        clearError: useCallback(() => setLastError(null), []),
    };
}
