/**
 * List files in the project workspace (allowed dirs only).
 */

import { executeToolRequest } from './api';
import type { AiToolContext, ToolResult } from './types';

export const toolName = 'listFiles';

export interface FileEntry {
  path: string;
  name: string;
  size: number;
  is_dir: boolean;
  mod_time: string;
}

export interface ListFilesArgs {
  max_files?: number;
}

export async function execute(
  args: ListFilesArgs,
  context: AiToolContext
): Promise<ToolResult<{ files: FileEntry[] }>> {
  const result = await executeToolRequest(
    toolName,
    { max_files: args.max_files ?? 500 },
    context
  );
  if (!result.success) {
    return { success: false, error: result.error ?? 'List failed' };
  }
  const data = result.data as { files?: FileEntry[] };
  return {
    success: true,
    data: { files: data?.files ?? [] },
  };
}
