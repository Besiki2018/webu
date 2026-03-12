/**
 * AI Component Generator for Webu.
 * Ensures a section component exists: if missing, generates it via the backend and creates
 * the file using the Agent Tools (createFile). Integrates with Site Planner and Design System.
 */

import { executeTool } from '../toolExecutor';
import type { AiToolContext } from '../tools/types';
import type {
  EnsureSectionExistsOptions,
  EnsureSectionExistsResult,
} from './types';

const CSRF_HEADER = 'X-CSRF-TOKEN';
const CSRF_SELECTOR = 'meta[name="csrf-token"]';

function getCsrfToken(): string {
  return document.querySelector<HTMLMetaElement>(CSRF_SELECTOR)?.content ?? '';
}

/**
 * Normalize section name to PascalCase ending with "Section".
 * e.g. "pricing" -> "PricingSection", "TestimonialsSection" -> "TestimonialsSection"
 */
export function normalizeSectionName(name: string): string {
  const parts = name
    .trim()
    .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
    .split(/[^a-zA-Z0-9]+/)
    .map((part) => part.trim())
    .filter(Boolean);

  if (parts.length === 0) return '';

  const normalizedParts = parts.filter((part, index) => {
    return !(index === parts.length - 1 && part.toLowerCase() === 'section');
  });

  if (normalizedParts.length === 0) return '';

  const base = normalizedParts
    .map((part) => (/^[A-Z0-9]{2,4}$/.test(part) ? part.toUpperCase() : `${part.charAt(0).toUpperCase()}${part.slice(1).toLowerCase()}`))
    .join('');

  return `${base}Section`;
}

/**
 * Call backend to generate component TSX. Returns content only if section does not exist.
 */
async function fetchGeneratedComponent(
  projectId: string,
  sectionName: string,
  userPrompt: string,
  apiBase: string
): Promise<{ success: boolean; already_exists?: boolean; path?: string; content?: string; error?: string }> {
  const base = apiBase.replace(/\/$/, '');
  const url = `${base}/panel/projects/${projectId}/ai/generate-component`;
  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      [CSRF_HEADER]: getCsrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
    body: JSON.stringify({
      section_name: sectionName,
      user_prompt: userPrompt || undefined,
    }),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) {
    return {
      success: false,
      error: json.error ?? 'Generate component request failed',
    };
  }
  return {
    success: json.success === true,
    already_exists: Boolean(json.already_exists),
    path: json.path,
    content: json.content,
    error: json.error,
  };
}

/**
 * Ensure a section component exists. If it is missing, generate it and create the file via Agent Tools.
 * Use before inserting a section into a page (e.g. after Site Planner returns a plan with sections).
 * After creating a file, scan cache is invalidated so the builder sees the new component.
 *
 * @param projectId - Project id
 * @param sectionName - Section name (e.g. "PricingSection" or "pricing")
 * @param context - Tool context (projectId, apiBase, userPrompt, onReloadPreview)
 * @param options - Optional overrides (userPrompt, onReloadPreview)
 */
export async function ensureSectionExists(
  projectId: string,
  sectionName: string,
  context: AiToolContext,
  options: EnsureSectionExistsOptions = {}
): Promise<EnsureSectionExistsResult> {
  const normalized = normalizeSectionName(sectionName);
  if (!normalized) {
    return { created: false, error: 'Invalid section name', reused: false };
  }

  const apiBase = options.apiBase ?? context.apiBase ?? '';
  const userPrompt = options.userPrompt ?? context.userPrompt ?? '';

  const apiResult = await fetchGeneratedComponent(
    projectId,
    normalized,
    userPrompt,
    apiBase
  );

  if (!apiResult.success) {
    return {
      created: false,
      error: apiResult.error ?? 'Failed to generate component',
      reused: false,
    };
  }

  if (apiResult.already_exists && apiResult.path) {
    return {
      created: false,
      path: apiResult.path,
      reused: true,
    };
  }

  if (!apiResult.path || !apiResult.content) {
    return {
      created: false,
      error: 'No content returned for new component',
      path: apiResult.path,
      reused: false,
    };
  }

  const toolCtx: AiToolContext = {
    projectId,
    apiBase,
    userPrompt: context.userPrompt,
    onReloadPreview: options.onReloadPreview ?? context.onReloadPreview,
  };

  const createResult = await executeTool(
    'createFile',
    { path: apiResult.path, content: apiResult.content },
    toolCtx
  );

  if (!createResult.success) {
    return {
      created: false,
      error: createResult.error ?? 'Failed to create file',
      path: apiResult.path,
      reused: false,
    };
  }

  return {
    created: true,
    path: apiResult.path,
    reused: false,
  };
}
