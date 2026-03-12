/**
 * In-memory log of AI tool executions for the current session.
 * Backend also logs each execution.
 */

export interface ToolExecutionLogEntry {
  tool: string;
  args: Record<string, unknown>;
  timestamp: string;
  success: boolean;
  error?: string;
  path?: string;
  userPrompt?: string;
}

const sessionLog: ToolExecutionLogEntry[] = [];

export function logExecution(entry: Omit<ToolExecutionLogEntry, 'timestamp'>): void {
  sessionLog.push({
    ...entry,
    timestamp: new Date().toISOString(),
  });
}

export function getExecutionLog(): ToolExecutionLogEntry[] {
  return [...sessionLog];
}

export function clearExecutionLog(): void {
  sessionLog.length = 0;
}
