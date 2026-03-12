/**
 * Part 12 — Prevent Duplicate Components.
 *
 * Before generating a component, check:
 * - existing component categories
 * - existing names
 * - existing capabilities
 *
 * If equivalent exists (e.g. PricingSection): do NOT create duplicate.
 * Instead: add variant — caller should add a new layout variant to the existing
 * component (e.g. update {Name}.variants.ts and the component’s variant handling).
 */

import type { ComponentSpec } from './componentSpecGenerator';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface ExistingComponentSummary {
  registryId: string;
  key: string;
  /** PascalCase component name (e.g. PricingSection). */
  componentName?: string;
  /** Category / slug prefix (e.g. pricing, testimonials). */
  category?: string;
  /** Prop keys or editable field keys (e.g. ['title', 'plans', 'price']). */
  capabilities?: string[];
}

export interface CheckDuplicateInput {
  /** Proposed component name (e.g. PricingSection). */
  componentName: string;
  /** Normalized slug (e.g. pricing_table). */
  slug?: string;
  /** Prop keys (from spec.props). */
  props?: string[];
}

export interface CheckDuplicateResult {
  /** True if an equivalent component already exists; do not create duplicate. */
  isDuplicate: boolean;
  /** 'create' = proceed with full generation; 'addVariant' = add variant to existing instead. */
  action: 'create' | 'addVariant';
  /** When isDuplicate: registry ID of the existing component. */
  existingRegistryId?: string;
  /** When isDuplicate: short key of the existing component (e.g. pricing). */
  existingKey?: string;
}

// ---------------------------------------------------------------------------
// Category / name derivation
// ---------------------------------------------------------------------------

/** Slug to category (pricing_table → pricing, team_section → team). */
export function getCategoryFromSlug(slug: string): string {
  const base = slug.split('_')[0] ?? slug;
  return base.toLowerCase();
}

/** Short key to likely component name (pricing → PricingSection, hero → HeroSection). */
export function keyToComponentName(key: string): string {
  const known: Record<string, string> = {
    header: 'Header',
    footer: 'Footer',
    hero: 'HeroSection',
    features: 'FeaturesSection',
    cta: 'CTASection',
    navigation: 'Navigation',
    cards: 'CardsSection',
    grid: 'GridSection',
    pricing: 'PricingSection',
    testimonials: 'TestimonialsSlider',
    team: 'TeamSection',
    faq: 'FaqAccordion',
    comparison: 'FeatureComparisonTable',
    stats: 'StatsSection',
    logoStrip: 'LogoStrip',
    contact: 'ContactFormSection',
    newsletter: 'NewsletterSignup',
  };
  const lower = key.toLowerCase();
  if (known[lower]) return known[lower];
  const pascal = key.charAt(0).toUpperCase() + key.slice(1).replace(/([A-Z])/g, '$1');
  return `${pascal}Section`;
}

/** Extract capabilities from schema (editableFields or fields). */
export function getCapabilitiesFromSchema(schema: Record<string, unknown> | null | undefined): string[] {
  if (!schema || typeof schema !== 'object') return [];
  const editable = schema.editableFields as Array<{ key?: string; path?: string }> | string[] | undefined;
  if (Array.isArray(editable)) {
    return editable.map((f) => (typeof f === 'string' ? f : (f.key ?? f.path ?? ''))).filter(Boolean);
  }
  const fields = schema.fields as Array<{ path?: string; key?: string }> | undefined;
  if (Array.isArray(fields)) {
    return fields.map((f) => (f.path ?? f.key ?? '')).filter(Boolean);
  }
  return [];
}

// ---------------------------------------------------------------------------
// Matching
// ---------------------------------------------------------------------------

function proposedCategory(input: CheckDuplicateInput): string | null {
  if (input.slug) return getCategoryFromSlug(input.slug);
  const name = input.componentName.replace(/Section$/, '').replace(/Slider$/, '').replace(/Accordion$/, '').replace(/Table$/, '');
  return name.charAt(0).toLowerCase() + name.slice(1);
}

function capabilityOverlap(proposed: string[], existing: string[]): boolean {
  if (proposed.length === 0 || existing.length === 0) return false;
  const set = new Set(existing.map((c) => c.toLowerCase()));
  const matchCount = proposed.filter((p) => set.has(p.toLowerCase())).length;
  const minOverlap = Math.min(3, Math.ceil(proposed.length / 2));
  return matchCount >= minOverlap;
}

/**
 * Checks whether the proposed component is a duplicate of an existing one.
 * Uses: existing component categories, names, and capabilities.
 *
 * @param input — Proposed spec summary (componentName, slug, props).
 * @param existingSummaries — List of existing components (from registry).
 * @returns Result with isDuplicate, action ('create' | 'addVariant'), and existing id/key when duplicate.
 */
export function checkDuplicateComponent(
  input: CheckDuplicateInput,
  existingSummaries: ExistingComponentSummary[]
): CheckDuplicateResult {
  const proposedName = input.componentName.trim();
  const proposedCategoryName = proposedCategory(input);
  const proposedCaps = input.props ?? [];

  for (const existing of existingSummaries) {
    const existingName = (existing.componentName ?? keyToComponentName(existing.key)).trim();
    const existingCategory = existing.category ?? getCategoryFromSlug(existing.key.replace(/([A-Z])/g, '_$1').toLowerCase().replace(/^_/, ''));

    if (existingName === proposedName) {
      return {
        isDuplicate: true,
        action: 'addVariant',
        existingRegistryId: existing.registryId,
        existingKey: existing.key,
      };
    }

    if (proposedCategoryName && existingCategory && proposedCategoryName === existingCategory) {
      return {
        isDuplicate: true,
        action: 'addVariant',
        existingRegistryId: existing.registryId,
        existingKey: existing.key,
      };
    }

    const caps = existing.capabilities ?? [];
    if (capabilityOverlap(proposedCaps, caps) && (proposedCaps.length >= 3 || caps.length >= 3)) {
      return {
        isDuplicate: true,
        action: 'addVariant',
        existingRegistryId: existing.registryId,
        existingKey: existing.key,
      };
    }
  }

  return { isDuplicate: false, action: 'create' };
}

/**
 * Builds existing component summaries from the builder registry.
 * Use this to pass existingSummaries into checkDuplicateComponent.
 *
 * @param getRegistryEntries — () => Array of { registryId, key, entry } (e.g. from REGISTRY_ID_TO_KEY + getEntry).
 */
export function buildExistingSummariesFromRegistry(
  getRegistryEntries: () => Array<{ registryId: string; key: string; entry: { schema?: Record<string, unknown> } | null }>
): ExistingComponentSummary[] {
  const entries = getRegistryEntries();
  return entries.map(({ registryId, key, entry }) => {
    const categoryFromKey = key.replace(/([A-Z])/g, '_$1').toLowerCase().replace(/^_/, '').split('_')[0] ?? key.toLowerCase();
    return {
      registryId,
      key,
      componentName: keyToComponentName(key),
      category: categoryFromKey,
      capabilities: entry?.schema ? getCapabilitiesFromSchema(entry.schema) : [],
    };
  });
}

/**
 * Convenience: check duplicate from a full ComponentSpec.
 */
export function checkDuplicateFromSpec(spec: ComponentSpec, existingSummaries: ExistingComponentSummary[]): CheckDuplicateResult {
  return checkDuplicateComponent(
    {
      componentName: spec.componentName,
      slug: spec.slug,
      props: spec.props,
    },
    existingSummaries
  );
}

/**
 * Builds existing summaries from the builder registry (REGISTRY_ID_TO_KEY + getEntry).
 * Call before generation to pass into checkDuplicateFromSpec.
 * Requires optional injection to avoid hard dependency on registry at module load.
 */
export function getExistingSummariesFromBuilderRegistry(
  registrySnapshot: { registryIdToKey: Record<string, string>; getEntry: (registryId: string) => { schema?: Record<string, unknown> } | null }
): ExistingComponentSummary[] {
  const { registryIdToKey, getEntry } = registrySnapshot;
  const entries = Object.entries(registryIdToKey).map(([registryId, key]) => ({
    registryId,
    key,
    entry: getEntry(registryId),
  }));
  return buildExistingSummariesFromRegistry(() =>
    entries.map((e) => ({ registryId: e.registryId, key: e.key, entry: e.entry }))
  );
}
