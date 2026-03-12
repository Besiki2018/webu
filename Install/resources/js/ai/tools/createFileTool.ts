/**
 * Create a new file in the project workspace.
 * Backend maps createFile -> writeFile.
 */

import { executeToolRequest } from './api';
import type { AiToolContext, ToolResult } from './types';

export const toolName = 'createFile';

export interface CreateFileArgs {
  path: string;
  content: string;
  file_path?: string;
}

export async function execute(
  args: CreateFileArgs,
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
    return { success: false, error: result.error ?? 'Create failed' };
  }
  return {
    success: true,
    data: result.data as { path: string },
  };
}
