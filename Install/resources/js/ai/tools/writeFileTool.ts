/**
 * Write content to a file (create or overwrite).
 * Allowed paths: src/pages, src/components, src/sections, src/layouts, src/styles, public.
 */

import { executeToolRequest } from './api';
import type { AiToolContext, ToolResult } from './types';

export const toolName = 'writeFile';

export interface WriteFileArgs {
  path: string;
  content: string;
  file_path?: string;
}

export async function execute(
  args: WriteFileArgs,
  context: AiToolContext
): Promise<ToolResult<{ path: string }>> {
  const path = (args.path ?? args.file_path ?? '').trim();
  const content = typeof args.content === 'string' ? args.content : '';
  if (!path) {
    return { success: false, error: 'path is required' };
  }
  if (content === '' && !path.endsWith('.gitkeep')) {
    return { success: false, error: 'content is required' };
  }
  const result = await executeToolRequest(
    toolName,
    { path, content },
    context
  );
  if (!result.success) {
    return { success: false, error: result.error ?? 'Write failed' };
  }
  return {
    success: true,
    data: result.data as { path: string },
  };
}
