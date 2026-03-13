/**
 * Page Builder Generator — convert section plan into builder state.
 *
 * Part 7: Takes a section plan (from sitePlanner) and optional props per section,
 * and produces either a serializable page state { page: { sections } } or a
 * component tree (BuilderComponentInstance[]) for the builder store.
 */

import type { BuilderComponentInstance } from '../core/types';
import type { SitePlanResult, PlannedSection } from './sitePlanner';
import {
  DEFAULT_HERO_REGISTRY_ID,
  DEFAULT_FEATURES_REGISTRY_ID,
  DEFAULT_FOOTER_REGISTRY_ID,
} from '../registry/componentRegistry';
import {
  getDefaultProps,
  isValidComponent,
  resolveComponentRegistryKey,
} from '../componentRegistry';

// ---------------------------------------------------------------------------
// Builder state types (serializable page format)
// ---------------------------------------------------------------------------

export interface SectionInPage {
  id: string;
  component: string;
  variant: string;
  props: Record<string, unknown>;
}

export interface PageBuilderState {
  page: {
    sections: SectionInPage[];
  };
}

// ---------------------------------------------------------------------------
// Id and short-name helpers
// ---------------------------------------------------------------------------

function componentKeyToShortName(componentKey: string): string {
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

/** Part 13 — Resolve to a registry key when plan has an invalid componentKey (never emit non-registry components). */
function resolveToRegistryKey(componentKey: string, index: number, total: number): string {
  const canonicalKey = resolveComponentRegistryKey(componentKey);
  if (canonicalKey && isValidComponent(canonicalKey)) return canonicalKey;
  if (total <= 0) return DEFAULT_HERO_REGISTRY_ID;
  if (index === 0) return DEFAULT_HERO_REGISTRY_ID;
  if (index === total - 1) return DEFAULT_FOOTER_REGISTRY_ID;
  return DEFAULT_FEATURES_REGISTRY_ID;
}

// ---------------------------------------------------------------------------
// Section plan → builder state
// ---------------------------------------------------------------------------

export interface SectionPlanToBuilderStateOptions {
  /** Props per section index (0-based). Merged over plan section props. */
  propsByIndex?: Record<number, Record<string, unknown>>;
  /** Custom id generator. Default: "{shortName}-{index + 1}" */
  generateId?: (componentKey: string, index: number) => string;
}

/**
 * Converts a section plan into the serializable page builder state.
 * Part 13: Only emits sections whose component exists in the registry (uses same fallbacks as sectionPlanToComponentTree).
 * Example output: { page: { sections: [{ id: "section1", component: "header", variant: "header-1", props: {} }, ... ] } }
 */
export function sectionPlanToBuilderState(
  plan: SitePlanResult,
  options: SectionPlanToBuilderStateOptions = {}
): PageBuilderState {
  const { propsByIndex = {}, generateId = generateSectionId } = options;
  const total = plan.sections.length;
  const sections: SectionInPage[] = plan.sections.map((section: PlannedSection, index: number) => {
    const safeKey = resolveToRegistryKey(section.componentKey, index, total);
    const id = generateId(safeKey, index);
    const component = componentKeyToShortName(safeKey);
    const variant = section.variant ?? '';
    const props = { ...(section.props ?? {}), ...(propsByIndex[index] ?? {}) };
    return { id, component, variant, props };
  });
  return { page: { sections } };
}

/**
 * Converts a section plan into a component tree (BuilderComponentInstance[]) for the builder store.
 * Merges registry defaults with plan variant/props and optional propsByIndex.
 * Part 13: Only emits components that exist in the registry; invalid keys fall back to default hero/features/footer.
 * Use with setComponentTree() or treeToSectionsDraft() for Cms.
 */
export function sectionPlanToComponentTree(
  plan: SitePlanResult,
  options: SectionPlanToBuilderStateOptions = {}
): BuilderComponentInstance[] {
  const { propsByIndex = {}, generateId = generateSectionId } = options;
  const nodes: BuilderComponentInstance[] = [];
  const total = plan.sections.length;

  for (let i = 0; i < total; i++) {
    const section = plan.sections[i]!;
    const { componentKey, variant, props: planProps } = section;
    const safeKey = resolveToRegistryKey(componentKey, i, total);
    const defaults = getDefaultProps(safeKey);
    const id = generateId(safeKey, i);
    const overrideProps = { ...(planProps ?? {}), ...(propsByIndex[i] ?? {}) };
    const mergedProps = { ...defaults, ...overrideProps };
    if (variant !== undefined) {
      mergedProps.variant = variant;
    }
    nodes.push({
      id,
      componentKey: safeKey,
      ...(variant !== undefined && { variant }),
      props: mergedProps,
    });
  }

  return nodes;
}
