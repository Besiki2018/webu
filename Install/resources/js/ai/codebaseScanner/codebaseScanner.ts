/**
 * Project codebase scanner for Webu AI.
 * Fetches structured project info (pages, sections, components, layouts, styles, page_structure)
 * from the backend and optionally caches it. Use before AI edits so the AI understands the project.
 *
 * Allowed dirs: src/pages, src/components, src/sections, src/layouts, src/styles, public.
 * Forbidden: builder-core, system, node_modules, server.
 */

import type { ProjectStructure, CodebaseScanOutput, ProjectComponentParameters, ScannedComponentEntry, ScannedComponentField } from './types';
import { EMPTY_STRUCTURE } from './types';

const CACHE_KEY_PREFIX = 'webu_ai_project_structure_';
const CACHE_MAX_AGE_MS = 5 * 60 * 1000; // 5 minutes

interface ScanCacheEntry {
  structure: ProjectStructure;
  fetchedAt: number;
}

const memoryCache = new Map<string, ScanCacheEntry>();

function cacheKey(projectId: string): string {
  return `${CACHE_KEY_PREFIX}${projectId}`;
}

function getCached(projectId: string): ProjectStructure | null {
  const entry = memoryCache.get(cacheKey(projectId));
  if (!entry) return null;
  if (Date.now() - entry.fetchedAt > CACHE_MAX_AGE_MS) {
    memoryCache.delete(cacheKey(projectId));
    return null;
  }
  return entry.structure;
}

function setCache(projectId: string, structure: ProjectStructure): void {
  memoryCache.set(cacheKey(projectId), {
    structure,
    fetchedAt: Date.now(),
  });
}

function normalizeComponentField(raw: unknown): ScannedComponentField | null {
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return null;
  const field = raw as Record<string, unknown>;
  if (typeof field.parameterName !== 'string' || typeof field.type !== 'string' || typeof field.title !== 'string') {
    return null;
  }

  return {
    parameterName: field.parameterName,
    type: field.type,
    title: field.title,
    ...(Object.prototype.hasOwnProperty.call(field, 'default') ? { default: field.default } : {}),
    ...(typeof field.format === 'string' ? { format: field.format } : {}),
  };
}

function normalizeComponentEntry(raw: unknown): ScannedComponentEntry | null {
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return null;
  const entry = raw as Record<string, unknown>;
  if (typeof entry.component !== 'string' || typeof entry.path !== 'string' || typeof entry.label !== 'string') {
    return null;
  }

  return {
    component: entry.component,
    path: entry.path,
    label: entry.label,
    fields: Array.isArray(entry.fields)
      ? entry.fields.map(normalizeComponentField).filter((field): field is ScannedComponentField => field !== null)
      : [],
    ...(entry.schema_json && typeof entry.schema_json === 'object' && !Array.isArray(entry.schema_json)
      ? { schema_json: entry.schema_json as Record<string, unknown> }
      : {}),
  };
}

function normalizeComponentBucket(raw: unknown): Record<string, ScannedComponentEntry> {
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return {};

  return Object.entries(raw as Record<string, unknown>).reduce<Record<string, ScannedComponentEntry>>((acc, [key, value]) => {
    const entry = normalizeComponentEntry(value);
    if (entry) {
      acc[key] = entry;
    }
    return acc;
  }, {});
}

function normalizeComponentParameters(raw: unknown): ProjectComponentParameters {
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) {
    return {
      sections: {},
      components: {},
      layouts: {},
    };
  }

  const source = raw as Record<string, unknown>;

  return {
    sections: normalizeComponentBucket(source.sections),
    components: normalizeComponentBucket(source.components),
    layouts: normalizeComponentBucket(source.layouts),
  };
}

/**
 * Invalidate cached structure for a project. Call after file create/update/delete
 * so the next scan returns fresh data.
 */
export function invalidateScanCache(projectId: string): void {
  memoryCache.delete(cacheKey(projectId));
}

/**
 * Normalize backend scan into ProjectStructure (page slugs, component names).
 */
function normalizeStructure(raw: Record<string, unknown>): ProjectStructure {
  const toList = (v: unknown): string[] =>
    Array.isArray(v) ? v.filter((x): x is string => typeof x === 'string') : [];
  const pagePaths = toList(raw.pages);
  const pages = pagePaths.map((p) => pathToPageSlug(p));
  return {
    pages,
    sections: toList(raw.sections).map((p) => pathToComponentName(p)),
    components: toList(raw.components).map((p) => pathToComponentName(p)),
    layouts: toList(raw.layouts).map((p) => pathToComponentName(p)),
    styles: toList(raw.styles),
    public: toList(raw.public),
    page_structure: (raw.page_structure && typeof raw.page_structure === 'object' && !Array.isArray(raw.page_structure))
      ? (raw.page_structure as Record<string, string[]>)
      : {},
    component_parameters: normalizeComponentParameters(raw.component_parameters),
    imports_sample: (raw.imports_sample && typeof raw.imports_sample === 'object') ? (raw.imports_sample as Record<string, string>) : {},
    file_contents: (raw.file_contents && typeof raw.file_contents === 'object') ? (raw.file_contents as Record<string, string>) : {},
  };
}

function pathToPageSlug(path: string): string {
  const match = path.match(/src\/pages\/([^/]+)\//);
  return match ? match[1] : path.replace(/\.(tsx|ts|jsx|js)$/, '').split('/').pop() ?? path;
}

function pathToComponentName(path: string): string {
  return path.replace(/.*\//, '').replace(/\.(tsx|ts|jsx|js)$/, '') || path;
}

export interface ScanOptions {
  projectId: string;
  apiBase?: string;
  forceRefresh?: boolean;
}

/**
 * Scan the project codebase and return structured info for AI context.
 * Never throws: on error returns success: false with empty structure.
 */
export async function scanCodebase(options: ScanOptions): Promise<CodebaseScanOutput> {
  const { projectId, apiBase = '', forceRefresh = false } = options;
  const base = apiBase.replace(/\/$/, '');

  if (!forceRefresh) {
    const cached = getCached(projectId);
    if (cached) {
      return { success: true, structure: cached, fromCache: true };
    }
  }

  try {
    const url = `${base}/panel/projects/${projectId}/workspace/structure`;
    const res = await fetch(url, {
      method: 'GET',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    });
    const json = await res.json().catch(() => ({}));

    if (json.success === true && json.structure) {
      const structure = normalizeStructure(json.structure);
      setCache(projectId, structure);
      return { success: true, structure, fromCache: false };
    }

    if (json.structure) {
      const structure = normalizeStructure(json.structure);
      return { success: false, error: json.error ?? 'Scan failed', structure };
    }

    return {
      success: false,
      error: json.error ?? 'Could not load project structure',
      structure: { ...EMPTY_STRUCTURE },
    };
  } catch {
    return {
      success: false,
      error: 'Could not load project structure',
      structure: { ...EMPTY_STRUCTURE },
    };
  }
}

/**
 * Build a short summary string of the project structure for inclusion in AI prompts.
 */
export function structureToContextSummary(structure: ProjectStructure): string {
  const lines: string[] = [];
  if (structure.pages.length) lines.push(`Pages: ${structure.pages.join(', ')}`);
  if (structure.sections.length) lines.push(`Sections: ${structure.sections.join(', ')}`);
  if (structure.components.length) lines.push(`Components: ${structure.components.join(', ')}`);
  if (structure.layouts.length) lines.push(`Layouts: ${structure.layouts.join(', ')}`);
  if (structure.styles.length) lines.push(`Styles: ${structure.styles.join(', ')}`);
  if (Object.keys(structure.page_structure).length) {
    const parts = Object.entries(structure.page_structure).map(
      ([page, imports]) => `${page}: [${imports.join(', ')}]`
    );
    lines.push(`Page imports: ${parts.join('; ')}`);
  }
  const editableSections = Object.values(structure.component_parameters.sections)
    .filter((entry) => entry.fields.length > 0)
    .slice(0, 8)
    .map((entry) => `${entry.component}: [${entry.fields.map((field) => field.parameterName).join(', ')}]`);
  if (editableSections.length) {
    lines.push(`Editable params: ${editableSections.join('; ')}`);
  }
  return lines.join('\n') || 'No project structure.';
}
