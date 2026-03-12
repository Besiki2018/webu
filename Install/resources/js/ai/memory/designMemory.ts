/**
 * AI Memory and Design Learning Engine for Webu.
 * Stores and retrieves patterns from successful websites for use by Site Planner and Design Intelligence.
 * Memory is advisory; current user prompt and project context remain primary.
 */

export interface DesignMemoryRecord {
  id: string;
  websiteType: string;
  pages: string[];
  homeSections?: string[];
  approvedDesignRules?: Record<string, unknown>;
  sectionOrder?: string[];
  sourceProjectId?: string;
  createdAt: number;
  confidenceScore: number;
  reuseCount: number;
}

export interface LayoutMemoryRecord {
  id: string;
  websiteType: string;
  spacingPattern?: string;
  typographyScale?: string;
  containerWidth?: string;
  sourceProjectId?: string;
  createdAt: number;
  confidenceScore: number;
}

const MEMORY_KEY_PREFIX = 'webu_design_memory_';
const LAYOUT_KEY = 'webu_layout_memory';
const MAX_RECORDS = 100;
const MIN_CONFIDENCE = 0.3;

function storageKey(projectId: string): string {
  return `${MEMORY_KEY_PREFIX}${projectId}`;
}

/**
 * Load design memory for a project (from localStorage or future .webu/memory).
 * Returns array of records sorted by confidence and reuse count.
 */
export function loadDesignMemory(projectId: string): DesignMemoryRecord[] {
  try {
    const key = storageKey(projectId);
    const raw = typeof localStorage !== 'undefined' ? localStorage.getItem(key) : null;
    if (!raw) return [];
    const parsed = JSON.parse(raw) as unknown;
    const list = Array.isArray(parsed) ? parsed : [];
    return list
      .filter((r): r is DesignMemoryRecord => r && typeof r === 'object' && typeof r.websiteType === 'string')
      .filter((r) => (r.confidenceScore ?? 0) >= MIN_CONFIDENCE)
      .sort((a, b) => (b.reuseCount ?? 0) - (a.reuseCount ?? 0) || (b.confidenceScore ?? 0) - (a.confidenceScore ?? 0))
      .slice(0, MAX_RECORDS);
  } catch {
    return [];
  }
}

/**
 * Save a design memory record (e.g. after user accepts a generated site).
 */
export function saveDesignMemory(projectId: string, record: Omit<DesignMemoryRecord, 'id' | 'createdAt'>): void {
  try {
    const list = loadDesignMemory(projectId);
    const newRecord: DesignMemoryRecord = {
      ...record,
      id: `dm_${Date.now()}_${Math.random().toString(36).slice(2, 9)}`,
      createdAt: Date.now(),
      confidenceScore: record.confidenceScore ?? 0.5,
      reuseCount: record.reuseCount ?? 0,
    };
    list.unshift(newRecord);
    const trimmed = list.slice(0, MAX_RECORDS);
    const key = storageKey(projectId);
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem(key, JSON.stringify(trimmed));
    }
  } catch {
    // ignore
  }
}

/**
 * Retrieve relevant design patterns for a website type (e.g. "restaurant", "saas").
 */
export function getDesignPatternsForType(projectId: string, websiteType: string): DesignMemoryRecord[] {
  const normalized = websiteType.trim().toLowerCase().replace(/\s+/g, '_');
  return loadDesignMemory(projectId).filter(
    (r) => r.websiteType.toLowerCase().replace(/\s+/g, '_') === normalized || r.websiteType.toLowerCase().includes(normalized)
  );
}

/**
 * Load layout memory (global, not per project).
 */
export function loadLayoutMemory(): LayoutMemoryRecord[] {
  try {
    const raw = typeof localStorage !== 'undefined' ? localStorage.getItem(LAYOUT_KEY) : null;
    if (!raw) return [];
    const parsed = JSON.parse(raw) as unknown;
    const list = Array.isArray(parsed) ? parsed : [];
    return list
      .filter((r): r is LayoutMemoryRecord => r && typeof r === 'object')
      .slice(0, 50);
  } catch {
    return [];
  }
}

/**
 * Save layout memory record.
 */
export function saveLayoutMemory(record: Omit<LayoutMemoryRecord, 'id' | 'createdAt'>): void {
  try {
    const list = loadLayoutMemory();
    const newRecord: LayoutMemoryRecord = {
      ...record,
      id: `lm_${Date.now()}_${Math.random().toString(36).slice(2, 9)}`,
      createdAt: Date.now(),
      confidenceScore: record.confidenceScore ?? 0.5,
    };
    list.unshift(newRecord);
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem(LAYOUT_KEY, JSON.stringify(list.slice(0, 50)));
    }
  } catch {
    // ignore
  }
}

/**
 * Infer website type from user prompt (simple keyword match).
 */
export function inferWebsiteTypeFromPrompt(prompt: string): string {
  const lower = prompt.toLowerCase();
  if (/\brestaurant\b|menu|reservation|dining/i.test(lower)) return 'restaurant';
  if (/\bsaas\b|software|pricing|subscription|startup/i.test(lower)) return 'saas';
  if (/\bportfolio\b|photography|agency|freelance/i.test(lower)) return 'portfolio';
  if (/\becommerce\b|e-commerce|shop|store|products/i.test(lower)) return 'ecommerce';
  if (/\bmedical\b|clinic|dental|doctor|health/i.test(lower)) return 'medical';
  if (/\bhotel\b|booking|reservation/i.test(lower)) return 'hotel';
  return 'business';
}
