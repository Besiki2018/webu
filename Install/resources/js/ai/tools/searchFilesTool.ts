/**
 * Search files in the project by keyword (path/name).
 */

import { executeToolRequest } from './api';
import type { AiToolContext, ToolResult } from './types';
import type { FileEntry } from './listFilesTool';

export const toolName = 'searchFiles';

export interface SearchFilesArgs {
  keyword: string;
  max_results?: number;
}

export async function execute(
  args: SearchFilesArgs,
  context: AiToolContext
): Promise<ToolResult<{ matches: FileEntry[] }>> {
  const keyword = typeof args.keyword === 'string' ? args.keyword.trim() : '';
  if (!keyword) {
    return { success: false, error: 'keyword is required' };
  }
  const result = await executeToolRequest(
    toolName,
    { keyword, max_results: args.max_results ?? 50 },
    context
  );
  if (!result.success) {
    return { success: false, error: result.error ?? 'Search failed' };
  }
  const data = result.data as { matches?: FileEntry[] };
  return {
    success: true,
    data: { matches: data?.matches ?? [] },
  };
}
