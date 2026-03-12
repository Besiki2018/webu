/**
 * Delete a file from the project workspace.
 */

import { executeToolRequest } from './api';
import type { AiToolContext, ToolResult } from './types';

export const toolName = 'deleteFile';

export interface DeleteFileArgs {
  path: string;
  file_path?: string;
}

export async function execute(
  args: DeleteFileArgs,
  context: AiToolContext
): Promise<ToolResult<{ path: string }>> {
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
    return { success: false, error: result.error ?? 'Delete failed' };
  }
  return {
    success: true,
    data: result.data as { path: string },
  };
}
