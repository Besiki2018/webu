/**
 * Read a file from the active project workspace.
 * Allowed: src/pages, src/components, src/sections, src/layouts, src/styles, public.
 */

import { executeToolRequest } from './api';
import type { AiToolContext, ToolResult } from './types';

export const toolName = 'readFile';

export interface ReadFileArgs {
  path: string;
  file_path?: string;
}

export async function execute(
  args: ReadFileArgs,
  context: AiToolContext
): Promise<ToolResult<{ path: string; content: string }>> {
  const path = (args.path ?? args.file_path ?? '').trim();
  if (!path) {
    return { success: false, error: 'path is required' };
  }
  const result = await executeToolRequest(
    toolName,
    { path },
    context
  );
  if (!result.success) {
    return { success: false, error: result.error ?? 'Read failed' };
  }
  return {
    success: true,
    data: result.data as { path: string; content: string },
  };
}
