/**
 * Unified result shape for AI agent tools.
 */

export interface ToolResultSuccess<T = unknown> {
  success: true;
  data?: T;
}

export interface ToolResultError {
  success: false;
  error: string;
}

export type ToolResult<T = unknown> = ToolResultSuccess<T> | ToolResultError;

export interface AiToolContext {
  projectId: string;
  apiBase?: string;
  userPrompt?: string;
  onReloadPreview?: () => void | Promise<void>;
}

export type ToolExecuteFn<TArgs = Record<string, unknown>> = (
  args: TArgs,
  context: AiToolContext
) => Promise<ToolResult>;
