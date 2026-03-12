/**
 * Webu AI Autopilot — single-prompt full website generation.
 * Orchestrates: scan → site plan → reuse/generate sections → create pages → design rules → layout refinement → preview.
 * Uses existing backend flow (ai-project-edit with full-website prompt); this module provides a typed client and execution log.
 */

import { scanCodebase, invalidateScanCache } from '../codebaseScanner';
import { generateSitePlan } from '../sitePlanner';
import type { SitePlan } from '../sitePlanner/types';
import { getDesignPatternsForType, inferWebsiteTypeFromPrompt, saveDesignMemory } from '../memory';

export interface AutopilotOptions {
  projectId: string;
  prompt: string;
  apiBase?: string;
  /** Callback when preview should reload */
  onReloadPreview?: () => Promise<void> | void;
}

export interface AutopilotExecutionLog {
  prompt: string;
  timestamp: number;
  scanSuccess: boolean;
  planSuccess: boolean;
  plan?: SitePlan;
  pagesCreated: string[];
  sectionsReused: string[];
  sectionsGenerated: string[];
  filesCreated: string[];
  filesUpdated: string[];
  summary: string;
  error?: string;
}

/**
 * Run full website generation via the backend (single endpoint).
 * The backend already runs: scan → plan → ensure sections → execute site plan.
 * This function calls POST /panel/projects/{projectId}/ai-project-edit with the prompt
 * and returns the result. Use this when you want to trigger "Create website for X" from the UI.
 */
export async function runAutopilot(options: AutopilotOptions): Promise<{
  success: boolean;
  summary: string;
  log: AutopilotExecutionLog;
  changes?: Array<{ path: string; op: string }>;
  error?: string;
}> {
  const { projectId, prompt, apiBase = '' } = options;
  const base = apiBase.replace(/\/$/, '');
  const timestamp = Date.now();

  const log: AutopilotExecutionLog = {
    prompt: prompt.trim(),
    timestamp,
    scanSuccess: false,
    planSuccess: false,
    pagesCreated: [],
    sectionsReused: [],
    sectionsGenerated: [],
    filesCreated: [],
    filesUpdated: [],
    summary: '',
  };

  const csrfToken =
    typeof document !== 'undefined'
      ? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
      : '';

  try {
    const scanResult = await scanCodebase({
      projectId,
      apiBase: base,
      forceRefresh: true,
    });
    log.scanSuccess = scanResult.success;
    if (!scanResult.success) {
      log.error = scanResult.error ?? 'Scan failed';
      return {
        success: false,
        summary: log.error,
        log,
        error: log.error,
      };
    }

    const websiteType = inferWebsiteTypeFromPrompt(prompt.trim());
    const patterns = getDesignPatternsForType(projectId, websiteType);
    const designPatternHints = patterns
      .slice(0, 5)
      .flatMap((p) => {
        const parts: string[] = [];
        if (p.homeSections && p.homeSections.length > 0) {
          parts.push(`${p.websiteType} home sections: ${p.homeSections.join(', ')}`);
        }
        if (p.pages && p.pages.length > 0) {
          parts.push(`Pages: ${p.pages.join(', ')}`);
        }
        return parts;
      })
      .filter(Boolean);
    const planResult = await generateSitePlan(projectId, prompt.trim(), {
      apiBase: base,
      designPatternHints: designPatternHints.length > 0 ? designPatternHints : undefined,
    });
    log.planSuccess = planResult.success;
    if (planResult.plan) {
      log.plan = planResult.plan;
      log.pagesCreated = planResult.plan.pages.map((p) => p.name);
    }
    if (!planResult.success) {
      log.error = planResult.error ?? 'Site plan failed';
      return {
        success: false,
        summary: log.error,
        log,
        error: log.error,
      };
    }

    const url = `${base}/panel/projects/${projectId}/ai-project-edit`;
    const body: { message: string; design_pattern_hints?: string[] } = { message: prompt.trim() };
    if (designPatternHints.length > 0) {
      body.design_pattern_hints = designPatternHints;
    }
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));

    if (data.changes && Array.isArray(data.changes)) {
      log.filesCreated = data.changes.filter((c: { op?: string }) => c.op === 'createFile').map((c: { path?: string }) => c.path);
      log.filesUpdated = data.changes.filter((c: { op?: string }) => c.op === 'updateFile').map((c: { path?: string }) => c.path);
    }
    if (data.created && Array.isArray(data.created)) {
      log.filesCreated = [...new Set([...log.filesCreated, ...data.created])];
    }
    if (data.updated && Array.isArray(data.updated)) {
      log.filesUpdated = [...new Set([...log.filesUpdated, ...data.updated])];
    }
    log.summary = data.summary ?? (data.success ? 'Website generated.' : data.error ?? 'Unknown error.');

    if (!res.ok || !data.success) {
      log.error = data.error ?? log.summary;
      return {
        success: false,
        summary: log.error ?? log.summary,
        log,
        changes: data.changes,
        error: log.error,
      };
    }

    invalidateScanCache(projectId);
    await options.onReloadPreview?.();

    if (log.plan && log.pagesCreated.length > 0) {
      const websiteType = inferWebsiteTypeFromPrompt(prompt.trim());
      const homePage = log.plan.pages.find((p) => p.name === 'home');
      saveDesignMemory(projectId, {
        websiteType,
        pages: log.pagesCreated,
        homeSections: homePage?.sections ?? [],
        confidenceScore: 0.6,
        reuseCount: 0,
      });
    }

    return {
      success: true,
      summary: log.summary,
      log,
      changes: data.changes,
    };
  } catch (err) {
    const errorMessage = err instanceof Error ? err.message : String(err);
    log.error = errorMessage;
    log.summary = errorMessage;
    return {
      success: false,
      summary: errorMessage,
      log,
      error: errorMessage,
    };
  }
}
