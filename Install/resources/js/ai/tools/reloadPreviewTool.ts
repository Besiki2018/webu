/**
 * Request preview refresh so file changes are visible.
 * Backend returns success; client should call onReloadPreview when this tool runs.
 */

import { executeToolRequest } from './api';
import type { AiToolContext, ToolResult } from './types';

export const toolName = 'reloadPreview';

export async function execute(
  _args: Record<string, unknown>,
  context: AiToolContext
): Promise<ToolResult<{ reload_requested: true }>> {
  const result = await executeToolRequest(
    toolName,
    {},
    context
  );
  if (result.success && context.onReloadPreview) {
    context.onReloadPreview();
  }
  if (!result.success) {
    return { success: false, error: result.error ?? 'Reload request failed' };
  }
  return {
    success: true,
    data: { reload_requested: true },
  };
}
