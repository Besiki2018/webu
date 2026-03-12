/**
 * AI Executor Module – Lovable-style AI site editor.
 *
 * Single entry point for AI-driven changes. The executor runs the pipeline:
 *   User Prompt
 *   → Page Structure Analysis
 *   → Target Component Detection
 *   → Action Plan Generation
 *   → Action Execution
 *   → Builder State Update
 *   → Preview Re-render
 *   → Execution Result
 *
 * All modifications go through the backend (analyze → interpret → execute).
 * The executor only modifies: component parameters, page content, CMS entries,
 * media library, layout structure, theme/header/footer. It must never modify:
 * component source code, builder core logic, or system configuration.
 */

import { AGENT_TOOL_NAMES } from './aiSiteEditorTools';

/** Execution steps shown in the UI while the AI is working. */
export const AI_EXECUTION_STEPS = [
    'analyzing',
    'locating',
    'editing',
    'uploading',
    'updating_preview',
    'completed',
] as const;

export type AiExecutionStep = (typeof AI_EXECUTION_STEPS)[number];

/** Action types the executor can perform (maps to backend change set ops). */
export const EXECUTOR_ACTIONS = {
    updateComponentParameter: 'updateSection',
    createSection: 'insertSection',
    deleteSection: 'deleteSection',
    updatePageContent: 'updateSection',
    uploadMedia: 'replaceImage',
    updateGlobalHeader: 'updateGlobalComponent',
    updateGlobalFooter: 'updateGlobalComponent',
    refreshPreview: 'refreshPreview',
} as const;

/**
 * Tool names the AI agent uses. These are the only way the AI modifies the site.
 * Read: getCurrentPageStructure, getPageComponents, getEditableParameters.
 * Write: updateComponentParameter, createSection, deleteSection, uploadMedia,
 * updateGlobalHeader, updateGlobalFooter (via interpret → execute).
 * Client-only after execute: refreshPreview, scrollToComponent, highlightComponent.
 */
export { AGENT_TOOL_NAMES };
