/**
 * Agent Tools Layer – named tools for the AI site-editing agent (Lovable/Codex-style).
 * The AI must inspect the current page before acting; all write operations go through
 * interpret(instruction) → execute(change_set). Client-only tools (refreshPreview,
 * scrollToComponent, highlightComponent) are implemented in the UI after execute.
 */

import type { AnalyzeResult, ChangeSet, ExecuteResult, PageStructure } from '@/hooks/useAiSiteEditor';

/** Canonical tool names used by the agent and backend. */
export const AGENT_TOOL_NAMES = {
    getCurrentPageStructure: 'getCurrentPageStructure',
    getPageComponents: 'getPageComponents',
    getEditableParameters: 'getEditableParameters',
    updateComponentParameter: 'updateComponentParameter',
    createSection: 'createSection',
    deleteSection: 'deleteSection',
    uploadMedia: 'uploadMedia',
    updateGlobalHeader: 'updateGlobalHeader',
    updateGlobalFooter: 'updateGlobalFooter',
    refreshPreview: 'refreshPreview',
    scrollToComponent: 'scrollToComponent',
    highlightComponent: 'highlightComponent',
} as const;

export type AgentToolsApi = {
    analyze: () => Promise<AnalyzeResult>;
    interpret: (instruction: string, pageContext?: Record<string, unknown>) => Promise<{ success: boolean; change_set?: ChangeSet; summary?: string[]; error?: string }>;
    execute: (changeSet: ChangeSet, options?: { page_id?: number; page_slug?: string; instruction?: string; publish?: boolean }) => Promise<ExecuteResult>;
};

/**
 * Create the named tools object for the AI agent.
 * Pipeline: Page Structure Scan → Target Detection → Change Plan → Action Execution → State Update → Live Preview Refresh.
 */
export function createAgentTools(projectId: string, api: AgentToolsApi) {
    return {
        /** getCurrentPageStructure: returns current page id, pages, sections, component tree, editable params, global components */
        async getCurrentPageStructure(): Promise<AnalyzeResult> {
            return api.analyze();
        },

        /** getPageComponents: sections for a page (explicit target or default first page) */
        getPageComponents(structure: AnalyzeResult, pageSlug?: string, pageId?: number): PageStructure['sections'] | undefined {
            const pages = structure.pages ?? [];
            const normalizedSlug = typeof pageSlug === 'string' && pageSlug.trim() !== '' ? pageSlug.trim().toLowerCase() : null;
            const hasExplicitTarget = typeof pageId === 'number' || normalizedSlug !== null;
            const page = typeof pageId === 'number'
                ? (pages.find((p) => p.id === pageId) ?? null)
                : null;
            const resolvedPage = page
                ?? (normalizedSlug ? pages.find((p) => p.slug.trim().toLowerCase() === normalizedSlug) ?? null : null)
                ?? (hasExplicitTarget ? null : (pages[0] ?? null));
            return resolvedPage?.sections;
        },

        /** getEditableParameters(componentId): editable fields for a section or global component */
        getEditableParameters(
            structure: AnalyzeResult,
            componentId: string
        ): string[] | undefined {
            const global = structure.global_components?.find((c) => c.id === componentId);
            if (global?.editable_fields?.length) return global.editable_fields;
            for (const page of structure.pages ?? []) {
                const section = page.sections?.find((s) => s.id === componentId);
                if (section?.editable_fields?.length) return section.editable_fields;
            }
            return undefined;
        },

        /**
         * updateComponentParameter / createSection / deleteSection / uploadMedia /
         * updateGlobalHeader / updateGlobalFooter: implemented via interpret(instruction) → execute(change_set).
         * Agent uses interpret() with page_context to get a ChangeSet, then execute() to apply.
         */
        async applyInstruction(
            instruction: string,
            pageContext?: Record<string, unknown>,
            options?: { page_id?: number; page_slug?: string; publish?: boolean }
        ): Promise<ExecuteResult> {
            const ir = await api.interpret(instruction, pageContext);
            if (!ir.success || !ir.change_set) {
                return { success: false, error: ir.error ?? 'Could not interpret command.' };
            }
            return api.execute(ir.change_set, {
                instruction,
                page_id: options?.page_id,
                page_slug: options?.page_slug,
                publish: options?.publish ?? false,
            });
        },

        // refreshPreview, scrollToComponent, highlightComponent: client-side only;
        // implemented in Chat.tsx (setPreviewRefreshTrigger, setAgentHighlightLocalId → InspectPreview scroll + highlight).
    };
}

export type AgentTools = ReturnType<typeof createAgentTools>;
