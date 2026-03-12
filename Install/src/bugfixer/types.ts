/**
 * Canonical bug event shape after normalization.
 * All fields that might contain secrets must be redacted before storage.
 */
export type BugEvent = {
  bugId: string;
  timestamp: string;
  severity: 'low' | 'medium' | 'high' | 'critical';
  source: 'frontend' | 'backend' | 'ai' | 'e2e';
  tenantId?: string | null;
  websiteId?: string | null;
  userId?: string | null;
  route?: string | null;
  event: string;
  stack?: string | null;
  context?: Record<string, unknown>;
  dedupKey?: string;
  frequency?: number;
};

export type RawErrorSource =
  | { type: 'system_log'; level: string; message: string; context?: Record<string, unknown>; stack?: string }
  | { type: 'frontend'; message: string; stack?: string; url?: string; userId?: string }
  | { type: 'ai'; message: string; validationErrors?: unknown; output?: string }
  | { type: 'e2e'; message: string; stack?: string; spec?: string }
  | { type: 'performance'; message: string; timeout?: number; route?: string };
