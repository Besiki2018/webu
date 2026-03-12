/**
 * AI tool executor: receives tool name + args, validates, runs the tool, returns result.
 * Use when the AI response includes a tool action (e.g. readFile, createFile, updateFile).
 */

import { logExecution } from './logs/executionLog';
import { invalidateScanCache } from './codebaseScanner';
import type { AiToolContext, ToolResult } from './tools/types';
import { execute as readFile } from './tools/readFileTool';
import { execute as writeFile } from './tools/writeFileTool';
import { execute as createFile } from './tools/createFileTool';
import { execute as updateFile } from './tools/updateFileTool';
import { execute as deleteFile } from './tools/deleteFileTool';
import { execute as listFiles } from './tools/listFilesTool';
import { execute as searchFiles } from './tools/searchFilesTool';
import { execute as reloadPreview } from './tools/reloadPreviewTool';

export const TOOL_NAMES = [
  'readFile',
  'writeFile',
  'createFile',
  'updateFile',
  'deleteFile',
  'listFiles',
  'searchFiles',
  'reloadPreview',
] as const;

export type ToolName = (typeof TOOL_NAMES)[number];

const TOOL_MAP: Record<ToolName, (args: Record<string, unknown>, ctx: AiToolContext) => Promise<ToolResult>> = {
  readFile: (a, c) => readFile({ path: String(a.path ?? a.file_path ?? '') }, c),
  writeFile: (a, c) => writeFile({ path: String(a.path ?? a.file_path ?? ''), content: String(a.content ?? '') }, c),
  createFile: (a, c) => createFile({ path: String(a.path ?? a.file_path ?? ''), content: String(a.content ?? '') }, c),
  updateFile: (a, c) => updateFile({ path: String(a.path ?? a.file_path ?? ''), content: String(a.content ?? '') }, c),
  deleteFile: (a, c) => deleteFile({ path: String(a.path ?? a.file_path ?? '') }, c),
  listFiles: (a, c) => listFiles({ max_files: typeof a.max_files === 'number' ? a.max_files : undefined }, c),
  searchFiles: (a, c) => searchFiles({ keyword: String(a.keyword ?? ''), max_results: typeof a.max_results === 'number' ? a.max_results : undefined }, c),
  reloadPreview: (a, c) => reloadPreview(a, c),
};

/**
 * Execute a single tool by name.
 *
 * @param tool - Tool name (readFile, writeFile, createFile, updateFile, deleteFile, listFiles, searchFiles, reloadPreview)
 * @param args - Tool arguments (e.g. { path, content } for writeFile)
 * @param context - Project and API context; pass onReloadPreview to trigger preview refresh after file changes
 * @returns Unified result { success, error?, data? }
 */
export async function executeTool(
  tool: string,
  args: Record<string, unknown>,
  context: AiToolContext
): Promise<ToolResult> {
  const normalized = tool.trim() as ToolName;
  if (!TOOL_NAMES.includes(normalized)) {
    logExecution({
      tool: normalized,
      args,
      success: false,
      error: `Unknown tool: ${tool}`,
      userPrompt: context.userPrompt,
    });
    return { success: false, error: `Unknown tool: ${tool}` };
  }

  const fn = TOOL_MAP[normalized];
  const result = await fn(args, context);

  if (result.success && ['writeFile', 'createFile', 'updateFile', 'deleteFile'].includes(normalized)) {
    invalidateScanCache(context.projectId);
    await context.onReloadPreview?.();
  }

  logExecution({
    tool: normalized,
    args,
    success: result.success,
    error: result.success ? undefined : result.error,
    path: args.path != null ? String(args.path) : args.file_path != null ? String(args.file_path) : undefined,
    userPrompt: context.userPrompt,
  });

  return result;
}

export type { AiToolContext, ToolResult } from './tools/types';
export { getExecutionLog, clearExecutionLog } from './logs/executionLog';
export type { ToolExecutionLogEntry } from './logs/executionLog';
