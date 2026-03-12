/**
 * Layout Structure Generator — convert section plan into builder structure.
 *
 * Part 6 (Design-to-Builder): Takes a section plan (componentKey + variant per section)
 * and produces the serializable builder state: page structure with sections array
 * (component short name, variant, id, props). Compatible with Cms sectionsDraft and builder store.
 *
 * Example result:
 *   page
 *    ├ header
 *    ├ hero
 *    ├ features
 *    ├ testimonials
 *    ├ cta
 *    └ footer
 *
 * Builder state: { sections: [ { component: "header", variant: "header-1" }, ... ] }
 */

import type { SiteStructureSection } from '../aiSiteGeneration';
import {
  getEntry,
  getRegistryKeyByComponentId,
  DEFAULT_HERO_REGISTRY_ID,
  DEFAULT_FEATURES_REGISTRY_ID,
  DEFAULT_FOOTER_REGISTRY_ID,
} from '../registry/componentRegistry';

// ---------------------------------------------------------------------------
// Input: section plan (from sectionMapper / variantMatcher or planner)
// ---------------------------------------------------------------------------

export interface LayoutBuilderSectionPlan {
  sections: SiteStructureSection[];
}

// ---------------------------------------------------------------------------
// Output: builder structure (serializable)
// ---------------------------------------------------------------------------

export interface LayoutBuilderSection {
  id: string;
  component: string;
  variant: string;
  props: Record<string, unknown>;
}

export interface LayoutBuilderResult {
  sections: LayoutBuilderSection[];
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function componentKeyToShortName(componentKey: string): string {
  const key = getRegistryKeyByComponentId(componentKey);
  if (key) return key;
  return componentKey
    .replace(/^webu_/, '')
    .replace(/_01$/, '')
    .replace(/general_/g, '')
    .replace(/_/g, '');
}

function generateSectionId(componentKey: string, index: number): string {
  const base = componentKeyToShortName(componentKey) || 'section';
  return `${base}-${index + 1}`;
}

function resolveToRegistryKey(componentKey: string, index: number, total: number): string {
  if (getEntry(componentKey)) return componentKey;
  if (total <= 0) return DEFAULT_HERO_REGISTRY_ID;
  if (index === 0) return DEFAULT_HERO_REGISTRY_ID;
  if (index === total - 1) return DEFAULT_FOOTER_REGISTRY_ID;
  return DEFAULT_FEATURES_REGISTRY_ID;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

export interface BuildLayoutFromPlanOptions {
  /** Optional props per section index (merged over plan section props). */
  propsByIndex?: Record<number, Record<string, unknown>>;
  /** Custom id generator. Default: "{shortName}-{index + 1}" */
  generateId?: (componentKey: string, index: number) => string;
}

/**
 * Converts a section plan into builder structure (sections with component, variant, id, props).
 * Only emits sections that exist in the registry; invalid keys fall back to default hero/features/footer (Part 13).
 *
 * @param plan — Section plan (e.g. from sectionMapper or planSite).
 * @param options — propsByIndex, generateId.
 * @returns LayoutBuilderResult with ordered sections ready for builder/Cms.
 */
export function buildLayoutFromPlan(
  plan: LayoutBuilderSectionPlan,
  options: BuildLayoutFromPlanOptions = {}
): LayoutBuilderResult {
  const { propsByIndex = {}, generateId = generateSectionId } = options;
  const total = plan.sections.length;
  const sections: LayoutBuilderSection[] = [];

  for (let i = 0; i < total; i++) {
    const section = plan.sections[i]!;
    const safeKey = resolveToRegistryKey(section.componentKey, i, total);
    const id = generateId(safeKey, i);
    const component = getRegistryKeyByComponentId(safeKey) ?? componentKeyToShortName(safeKey);
    const variant = section.variant ?? '';
    const props = { ...(section.props ?? {}), ...(propsByIndex[i] ?? {}) };

    sections.push({
      id,
      component,
      variant,
      props,
    });
  }

  return { sections };
}

/**
 * Returns a human-readable tree summary of the layout (e.g. for logging or UI).
 * Example: "page\n ├ header\n ├ hero\n ├ features\n └ footer"
 */
export function layoutToTreeSummary(result: LayoutBuilderResult): string {
  const lines = ['page'];
  result.sections.forEach((s, i) => {
    const branch = i === result.sections.length - 1 ? ' └ ' : ' ├ ';
    lines.push(`${branch}${s.component}${s.variant ? ` (${s.variant})` : ''}`);
  });
  return lines.join('\n');
}
