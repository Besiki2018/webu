/**
 * Part 2 — Component Specification Generator.
 *
 * Generates a structured component specification from user prompt, project type, and design style.
 * Output is schema-ready: componentName, layoutType, props, variantTypes for use by the
 * AI component generator and registry.
 *
 * Part 12: Before generating a component, use generateComponentSpecWithDuplicateCheck so that
 * if an equivalent exists (same category/name/capabilities), action is 'addVariant' instead of creating a duplicate.
 */

import { detectComponentRequest } from './componentRequestDetector';
import { checkDuplicateFromSpec, type CheckDuplicateResult, type ExistingComponentSummary } from './duplicateComponentChecker';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface ComponentSpec {
  /** PascalCase name for the component (e.g. PricingSection). */
  componentName: string;
  /** Layout pattern: grid, stack, slider, accordion, table, cards. */
  layoutType: string;
  /** Prop keys the component should expose (schema-driven, editable). */
  props: string[];
  /** Layout variant ids for {Name}.variants.ts (e.g. cards, horizontal, minimal for pricing). */
  variantTypes: string[];
  /** Optional: normalized slug from request (e.g. pricing_table). */
  slug?: string;
  /** Optional: suggested registry ID prefix (e.g. webu_general_pricing_01). */
  suggestedRegistryId?: string;
}

export type DesignStyleInput =
  | 'modern'
  | 'minimal'
  | 'corporate'
  | 'startup'
  | 'ecommerce'
  | 'dark'
  | string;

// ---------------------------------------------------------------------------
// Slug → base spec (layout, props, variants)
// ---------------------------------------------------------------------------

const SLUG_TO_BASE_SPEC: Record<
  string,
  { componentName: string; layoutType: string; props: string[]; variantPrefix: string; variantCount: number }
> = {
  pricing_table: {
    componentName: 'PricingSection',
    layoutType: 'grid',
    props: ['title', 'plans', 'planName', 'price', 'features', 'ctaButton', 'variant', 'backgroundColor', 'textColor'],
    variantPrefix: 'pricing',
    variantCount: 3,
  },
  testimonials_slider: {
    componentName: 'TestimonialsSlider',
    layoutType: 'slider',
    props: ['title', 'items', 'quote', 'author', 'role', 'avatar', 'variant', 'backgroundColor', 'textColor'],
    variantPrefix: 'testimonials',
    variantCount: 3,
  },
  team_section: {
    componentName: 'TeamSection',
    layoutType: 'grid',
    props: ['title', 'subtitle', 'members', 'name', 'role', 'bio', 'image', 'socialLinks', 'variant', 'backgroundColor', 'textColor'],
    variantPrefix: 'team',
    variantCount: 3,
  },
  faq_accordion: {
    componentName: 'FaqAccordion',
    layoutType: 'accordion',
    props: ['title', 'subtitle', 'items', 'question', 'answer', 'variant', 'backgroundColor', 'textColor'],
    variantPrefix: 'faq',
    variantCount: 3,
  },
  feature_comparison_table: {
    componentName: 'FeatureComparisonTable',
    layoutType: 'table',
    props: ['title', 'plans', 'planName', 'features', 'featureLabel', 'checked', 'ctaButton', 'variant', 'backgroundColor', 'textColor'],
    variantPrefix: 'comparison',
    variantCount: 3,
  },
  stats_section: {
    componentName: 'StatsSection',
    layoutType: 'grid',
    props: ['title', 'items', 'value', 'label', 'variant', 'backgroundColor', 'textColor'],
    variantPrefix: 'stats',
    variantCount: 2,
  },
  logo_strip: {
    componentName: 'LogoStrip',
    layoutType: 'strip',
    props: ['title', 'logos', 'logoUrl', 'logoAlt', 'variant', 'backgroundColor'],
    variantPrefix: 'logoStrip',
    variantCount: 2,
  },
  contact_form: {
    componentName: 'ContactFormSection',
    layoutType: 'stack',
    props: ['title', 'subtitle', 'fields', 'submitLabel', 'successMessage', 'variant', 'backgroundColor', 'textColor'],
    variantPrefix: 'contact',
    variantCount: 2,
  },
  newsletter_signup: {
    componentName: 'NewsletterSignup',
    layoutType: 'stack',
    props: ['title', 'subtitle', 'placeholder', 'buttonLabel', 'variant', 'backgroundColor', 'textColor'],
    variantPrefix: 'newsletter',
    variantCount: 2,
  },
};

/**
 * Part 10 — Layout variant names for AI-generated components.
 * Stored in {Name}.variants.ts; e.g. pricing1 → cards, pricing2 → horizontal, pricing3 → minimal.
 * When defined, variantTypes use these ids instead of prefix1, prefix2.
 */
const SLUG_LAYOUT_VARIANTS: Record<string, string[]> = {
  pricing_table: ['cards', 'horizontal', 'minimal'],
  testimonials_slider: ['carousel', 'grid', 'minimal'],
  team_section: ['cards', 'grid', 'bios'],
  faq_accordion: ['single', 'grouped', 'minimal'],
  feature_comparison_table: ['table', 'cards', 'minimal'],
  stats_section: ['grid', 'strip'],
  logo_strip: ['inline', 'grid'],
  contact_form: ['stack', 'split'],
  newsletter_signup: ['centered', 'inline'],
};

// ---------------------------------------------------------------------------
// Style / project type → layout tweaks
// ---------------------------------------------------------------------------

function layoutTweakForStyle(layoutType: string, style: DesignStyleInput): string {
  const s = String(style).toLowerCase();
  if (s === 'minimal' && (layoutType === 'grid' || layoutType === 'cards')) return 'stack';
  if (s === 'modern' && layoutType === 'stack') return 'grid';
  return layoutType;
}

function variantCountForStyle(baseCount: number, style: DesignStyleInput): number {
  const s = String(style).toLowerCase();
  if (s === 'minimal') return Math.max(1, baseCount - 1);
  if (s === 'corporate' || s === 'modern') return Math.min(4, baseCount + 1);
  return baseCount;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function slugToComponentName(slug: string): string {
  return slug
    .split('_')
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase())
    .join('');
}

function slugToRegistryId(slug: string): string {
  return `webu_general_${slug}_01`;
}

function normalizeSlugFromPrompt(prompt: string): string | null {
  const result = detectComponentRequest(prompt);
  if (result.normalizedSlug) return result.normalizedSlug;
  if (result.requestedPhrase) {
    const lower = result.requestedPhrase.toLowerCase().trim().replace(/\s+/g, '_');
    if (lower.length > 0) return lower;
  }
  return null;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

export interface GenerateComponentSpecInput {
  /** User prompt (e.g. "Create pricing table", "Add FAQ accordion"). */
  prompt: string;
  /** Project type (saas, landing, ecommerce, etc.). */
  projectType?: string;
  /** Design style (modern, minimal, corporate, etc.). */
  designStyle?: DesignStyleInput;
}

/**
 * Generates a component specification from user prompt, project type, and design style.
 * Uses Part 1 (componentRequestDetector) to normalize the request; then applies
 * slug-based templates and style/projectType tweaks.
 *
 * @param input — prompt, projectType, designStyle
 * @returns ComponentSpec with componentName, layoutType, props, variantTypes
 */
export function generateComponentSpec(input: GenerateComponentSpecInput): ComponentSpec {
  const { prompt, projectType = 'landing', designStyle = 'modern' } = input;
  const slug = normalizeSlugFromPrompt(prompt);
  const style = designStyle || 'modern';

  if (slug && SLUG_TO_BASE_SPEC[slug]) {
    const base = SLUG_TO_BASE_SPEC[slug]!;
    const layoutType = layoutTweakForStyle(base.layoutType, style);
    const count = variantCountForStyle(base.variantCount, style);
    const layoutNames = SLUG_LAYOUT_VARIANTS[slug];
    const variantTypes =
      layoutNames && layoutNames.length > 0
        ? layoutNames.slice(0, count)
        : Array.from({ length: count }, (_, i) => `${base.variantPrefix}${i + 1}`);
    return {
      componentName: base.componentName,
      layoutType,
      props: [...base.props],
      variantTypes,
      slug,
      suggestedRegistryId: slugToRegistryId(slug),
    };
  }

  // Fallback: generic section from slug or prompt
  const fallbackSlug = slug ?? 'custom_section';
  const componentName = slug ? slugToComponentName(slug) : 'CustomSection';
  return {
    componentName,
    layoutType: layoutTweakForStyle('stack', style),
    props: ['title', 'subtitle', 'content', 'variant', 'backgroundColor', 'textColor'],
    variantTypes: ['default1', 'default2'],
    slug: fallbackSlug,
    suggestedRegistryId: slug ? slugToRegistryId(slug) : undefined,
  };
}

/**
 * Part 12 — Generate spec and check for duplicate. Before generating a component folder,
 * call this. If duplicateResult.action === 'addVariant', do NOT create a new component;
 * instead add a variant to existingRegistryId / existingKey.
 *
 * @param input — prompt, projectType, designStyle
 * @param existingSummaries — from getExistingSummariesFromBuilderRegistry or buildExistingSummariesFromRegistry
 * @returns spec and duplicateResult; use duplicateResult to decide create vs addVariant
 */
export function generateComponentSpecWithDuplicateCheck(
  input: GenerateComponentSpecInput,
  existingSummaries: ExistingComponentSummary[]
): { spec: ComponentSpec; duplicateResult: CheckDuplicateResult } {
  const spec = generateComponentSpec(input);
  const duplicateResult = checkDuplicateFromSpec(spec, existingSummaries);
  return { spec, duplicateResult };
}
