/**
 * Section Planning Engine — prompt analysis → site structure.
 *
 * Part 3: Converts PromptAnalysisResult into an ordered site structure (sections with component + variant)
 * compatible with the builder data model. Output can be passed to buildTreeFromStructure().
 */

import { analyzePrompt, type PromptAnalysisResult, type SectionSlug } from './promptAnalyzer';
import type { SiteStructureSection } from '../aiSiteGeneration';
import {
  DEFAULT_HERO_REGISTRY_ID,
  DEFAULT_FEATURES_REGISTRY_ID,
  DEFAULT_FOOTER_REGISTRY_ID,
  getAllowedComponents,
  isValidComponent,
  resolveComponentRegistryKey,
} from '../componentRegistry';
import { normalizeProjectSiteType, type ProjectSiteType, type ProjectType } from '../projectTypes';
import { getAllowedComponentCatalog, type AiComponentCatalogEntry, type AiComponentLayoutType } from './componentCatalog';
import {
  detectProjectType,
  inferAiProjectTypeFromBuilderProjectType,
  mapAiProjectTypeToBuilderProjectType,
  type AiProjectType,
} from './projectTypeDetector';
import { selectComponentVariant } from './variantSelector';

// ---------------------------------------------------------------------------
// Output type (builder-compatible)
// ---------------------------------------------------------------------------

/** Same as SiteStructureSection: componentKey is registry ID (e.g. webu_header_01). */
export type PlannedSection = SiteStructureSection;

export interface SitePlanResult {
  /** Ordered sections for the builder; compatible with buildTreeFromStructure(). */
  sections: PlannedSection[];
  /** Canonical component ids available to the planner for this project. */
  available_components?: string[];
  /** Normalized project metadata for governance-aware callers. */
  project?: {
    type: ProjectSiteType;
  };
}

/** Human-readable section entry (e.g. for display). Component is short name; variant is schema variant id. */
export interface SitePlanDisplaySection {
  component: string;
  variant: string;
}

export interface AiSitePlanSection extends SiteStructureSection {
  layoutType: AiComponentLayoutType;
  label: string;
}

export interface AiSitePlanPage {
  name: string;
  sections: AiSitePlanSection[];
}

export interface AiSitePlan {
  projectType: AiProjectType;
  builderProjectType: ProjectType;
  pages: AiSitePlanPage[];
  available_components: string[];
  project: {
    type: ProjectSiteType;
  };
}

export interface AiSitePlannerInput {
  prompt: string;
  projectType?: AiProjectType | ProjectType | null;
  componentCatalog?: AiComponentCatalogEntry[] | null;
}

// ---------------------------------------------------------------------------
// Slug → registry component key (builder uses registry IDs)
// ---------------------------------------------------------------------------

const SLUG_TO_COMPONENT_KEY: Record<SectionSlug, string> = {
  header: 'webu_header_01',
  hero: 'webu_general_hero_01',
  features: 'webu_general_features_01',
  productGrid: 'webu_ecom_product_grid_01',
  pricing: 'webu_general_features_01',
  testimonials: 'webu_general_testimonials_01',
  cta: 'webu_general_cta_01',
  footer: 'webu_footer_01',
  navigation: 'webu_general_navigation_01',
  cards: 'webu_general_cards_01',
  grid: 'webu_general_grid_01',
  faq: 'faq_accordion_plus',
  contact: 'webu_general_form_wrapper_01',
  blog: 'webu_general_cards_01',
  menu: 'webu_general_cards_01',
  booking: 'webu_general_form_wrapper_01',
  gallery: 'webu_general_grid_01',
};

/** Default variant per registry component (schema variant ids). */
const DEFAULT_VARIANTS: Record<string, string> = {
  webu_header_01: 'header-1',
  webu_footer_01: 'footer-1',
  webu_general_hero_01: 'hero-1',
  webu_general_features_01: 'features-1',
  webu_general_cta_01: 'cta-1',
  webu_general_navigation_01: 'navigation-1',
  webu_general_cards_01: 'cards-1',
  webu_general_grid_01: 'grid-1',
  webu_general_testimonials_01: 'testimonials-1',
  faq_accordion_plus: 'faq-1',
};

/** Optional variant override by tone (e.g. modern → hero-2). */
const TONE_VARIANT_OVERRIDES: Record<string, Record<string, string>> = {
  modern: {
    webu_general_hero_01: 'hero-1',
    webu_general_features_01: 'features-2',
  },
  minimal: {
    webu_general_hero_01: 'hero-1',
    webu_general_cta_01: 'cta-1',
  },
  bold: {
    webu_general_hero_01: 'hero-1',
  },
};

/** Section slugs we can resolve to a registry component. Unmapped slugs are skipped. */
const RESOLVABLE_SLUGS = new Set<SectionSlug>(Object.keys(SLUG_TO_COMPONENT_KEY) as SectionSlug[]);

/** Part 13 — When planned component is not in registry, fallback to default hero / features / footer. */
const SLUG_TO_FALLBACK_KEY: Partial<Record<SectionSlug, string>> = {
  hero: DEFAULT_HERO_REGISTRY_ID,
  features: DEFAULT_FEATURES_REGISTRY_ID,
  pricing: DEFAULT_FEATURES_REGISTRY_ID,
  productGrid: 'webu_general_grid_01',
  testimonials: DEFAULT_FEATURES_REGISTRY_ID,
  cards: DEFAULT_FEATURES_REGISTRY_ID,
  grid: DEFAULT_FEATURES_REGISTRY_ID,
  faq: DEFAULT_FEATURES_REGISTRY_ID,
  contact: 'webu_general_form_wrapper_01',
  blog: DEFAULT_FEATURES_REGISTRY_ID,
  menu: DEFAULT_FEATURES_REGISTRY_ID,
  booking: 'webu_general_form_wrapper_01',
  cta: DEFAULT_FEATURES_REGISTRY_ID,
  gallery: DEFAULT_FEATURES_REGISTRY_ID,
  footer: DEFAULT_FOOTER_REGISTRY_ID,
};

const SLUG_TO_ALLOWED_FALLBACK_KEYS: Partial<Record<SectionSlug, readonly string[]>> = {
  header: ['webu_header_01'],
  hero: [DEFAULT_HERO_REGISTRY_ID],
  productGrid: ['webu_ecom_product_grid_01', 'webu_general_grid_01', DEFAULT_FEATURES_REGISTRY_ID],
  features: [DEFAULT_FEATURES_REGISTRY_ID, 'webu_general_cards_01'],
  pricing: [DEFAULT_FEATURES_REGISTRY_ID],
  testimonials: ['webu_general_testimonials_01', 'webu_general_cards_01'],
  cta: ['webu_general_cta_01', 'webu_general_form_wrapper_01'],
  footer: [DEFAULT_FOOTER_REGISTRY_ID],
  navigation: ['webu_general_navigation_01', 'webu_header_01'],
  cards: ['webu_general_cards_01'],
  grid: ['webu_general_grid_01', 'webu_general_cards_01'],
  faq: ['faq_accordion_plus', 'webu_general_cards_01'],
  contact: ['webu_general_form_wrapper_01', 'webu_general_cta_01'],
  blog: ['webu_general_cards_01'],
  menu: ['webu_general_cards_01'],
  booking: ['webu_general_form_wrapper_01', 'webu_general_cta_01'],
  gallery: ['webu_general_grid_01'],
};

/** Canonical order for sections (header first, footer last). */
const SECTION_ORDER: SectionSlug[] = [
  'navigation',
  'header',
  'hero',
  'productGrid',
  'grid',
  'gallery',
  'features',
  'pricing',
  'cards',
  'testimonials',
  'faq',
  'blog',
  'menu',
  'contact',
  'booking',
  'cta',
  'footer',
];

const PROJECT_LAYOUTS: Record<AiProjectType, AiComponentLayoutType[]> = {
  landing: ['header', 'hero', 'features', 'cta', 'footer'],
  business: ['header', 'hero', 'features', 'testimonials', 'cta', 'footer'],
  ecommerce: ['header', 'hero', 'product-grid', 'features', 'testimonials', 'cta', 'footer'],
  booking: ['header', 'hero', 'features', 'form', 'cta', 'footer'],
  portfolio: ['header', 'hero', 'grid', 'testimonials', 'cta', 'footer'],
  clinic: ['header', 'hero', 'features', 'form', 'faq', 'cta', 'footer'],
  restaurant: ['header', 'hero', 'grid', 'form', 'cta', 'footer'],
  saas: ['header', 'hero', 'features', 'testimonials', 'cta', 'footer'],
  blog: ['header', 'hero', 'grid', 'cta', 'footer'],
  education: ['header', 'hero', 'features', 'grid', 'cta', 'footer'],
};

const SECTION_SLUG_TO_LAYOUT_TYPE: Partial<Record<SectionSlug, AiComponentLayoutType>> = {
  header: 'header',
  navigation: 'navigation',
  hero: 'hero',
  productGrid: 'product-grid',
  features: 'features',
  pricing: 'features',
  testimonials: 'testimonials',
  cta: 'cta',
  footer: 'footer',
  faq: 'faq',
  contact: 'form',
  blog: 'grid',
  menu: 'grid',
  booking: 'form',
  gallery: 'grid',
  cards: 'cards',
  grid: 'grid',
};

const PREFERRED_COMPONENTS_BY_LAYOUT: Record<AiComponentLayoutType, readonly string[]> = {
  header: ['webu_header_01'],
  footer: ['webu_footer_01'],
  hero: ['webu_general_hero_01'],
  features: ['webu_general_features_01'],
  'product-grid': ['webu_ecom_product_grid_01', 'webu_general_grid_01'],
  testimonials: ['webu_general_testimonials_01', 'webu_general_cards_01'],
  cta: ['webu_general_cta_01', 'webu_general_banner_01'],
  faq: ['faq_accordion_plus', 'webu_general_cards_01'],
  form: ['webu_general_form_wrapper_01', 'webu_general_cta_01'],
  navigation: ['webu_general_navigation_01', 'webu_header_01'],
  grid: ['webu_general_grid_01', 'webu_general_cards_01'],
  cards: ['webu_general_cards_01'],
  banner: ['webu_general_banner_01', 'webu_general_cta_01'],
  content: ['webu_general_text_01'],
  media: ['webu_general_image_01', 'webu_general_video_01'],
  section: ['webu_general_section_01', DEFAULT_FEATURES_REGISTRY_ID],
};

function orderSlugs(slugs: SectionSlug[]): SectionSlug[] {
  const seen = new Set<SectionSlug>();
  const result: SectionSlug[] = [];
  for (const s of SECTION_ORDER) {
    if (slugs.includes(s) && !seen.has(s)) {
      result.push(s);
      seen.add(s);
    }
  }
  for (const s of slugs) {
    if (!seen.has(s)) result.push(s);
  }
  return result;
}

function canUseComponent(componentKey: string, allowedComponentTypes: Set<string>): boolean {
  const canonicalKey = resolveComponentRegistryKey(componentKey);
  if (!canonicalKey) {
    return false;
  }

  return allowedComponentTypes.has(canonicalKey) && isValidComponent(canonicalKey);
}

/** Resolve slug to a valid, allowed registry component key with deterministic fallbacks. */
function resolveComponentKey(slug: SectionSlug, allowedComponentTypes: Set<string>): string | null {
  const key = SLUG_TO_COMPONENT_KEY[slug];
  if (key && canUseComponent(key, allowedComponentTypes)) {
    return resolveComponentRegistryKey(key);
  }

  const fallback = SLUG_TO_FALLBACK_KEY[slug];
  if (fallback && canUseComponent(fallback, allowedComponentTypes)) {
    return resolveComponentRegistryKey(fallback);
  }

  const allowedFallbacks = SLUG_TO_ALLOWED_FALLBACK_KEYS[slug] ?? [];
  for (const candidate of allowedFallbacks) {
    if (canUseComponent(candidate, allowedComponentTypes)) {
      return resolveComponentRegistryKey(candidate);
    }
  }

  return null;
}

/** Get variant for a componentKey; optionally apply tone override. */
function getVariant(componentKey: string, tone: string | null): string {
  if (tone && TONE_VARIANT_OVERRIDES[tone]?.[componentKey]) {
    return TONE_VARIANT_OVERRIDES[tone][componentKey];
  }
  return DEFAULT_VARIANTS[componentKey] ?? '';
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Plans the site structure from prompt analysis.
 * Output sections are ordered (HEADER → HERO → … → FOOTER) and compatible with the builder:
 * componentKey = registry ID, variant = schema variant id.
 */
export function planSite(analysis: PromptAnalysisResult): SitePlanResult {
  const projectSiteType = normalizeProjectSiteType(analysis.projectType);
  const allowedComponents = getAllowedComponents(projectSiteType);
  const allowedComponentTypes = new Set(allowedComponents.map((component) => component.type));
  const ordered = orderSlugs(analysis.requiredSections);
  const sections: PlannedSection[] = [];

  for (const slug of ordered) {
    if (!RESOLVABLE_SLUGS.has(slug)) continue;
    const componentKey = resolveComponentKey(slug, allowedComponentTypes);
    if (!componentKey) continue;
    const variant = getVariant(componentKey, analysis.tone);
    sections.push({
      componentKey,
      ...(variant && { variant }),
    });
  }

  if (sections.length === 0) {
    const defaultSlugs: SectionSlug[] = ['header', 'hero', 'features', 'cta', 'footer'];
    for (const slug of defaultSlugs) {
      const componentKey = resolveComponentKey(slug, allowedComponentTypes);
      if (!componentKey) {
        continue;
      }

      const variant = getVariant(componentKey, analysis.tone);
      sections.push({
        componentKey,
        ...(variant && { variant }),
      });
    }
  }

  return {
    sections,
    available_components: allowedComponents.map((component) => component.type),
    project: {
      type: projectSiteType,
    },
  };
}

function orderLayoutTypes(layoutTypes: AiComponentLayoutType[]): AiComponentLayoutType[] {
  const seen = new Set<AiComponentLayoutType>();
  const ordered: AiComponentLayoutType[] = [];
  const defaultOrder = PROJECT_LAYOUTS.landing;

  for (const layoutType of [...defaultOrder, ...layoutTypes]) {
    if (!seen.has(layoutType)) {
      ordered.push(layoutType);
      seen.add(layoutType);
    }
  }

  return ordered;
}

function resolvePlannerProjectType(inputProjectType: AiSitePlannerInput['projectType'], prompt: string): {
  projectType: AiProjectType;
  builderProjectType: ProjectType;
  normalizedSiteType: ProjectSiteType;
} {
  if (typeof inputProjectType === 'string' && inputProjectType.trim() !== '') {
    const aiProjectType = inferAiProjectTypeFromBuilderProjectType(inputProjectType);
    const builderProjectType = mapAiProjectTypeToBuilderProjectType(aiProjectType);
    return {
      projectType: aiProjectType,
      builderProjectType,
      normalizedSiteType: normalizeProjectSiteType(inputProjectType),
    };
  }

  const detected = detectProjectType(prompt);
  return {
    projectType: detected.projectType,
    builderProjectType: detected.builderProjectType,
    normalizedSiteType: detected.siteType,
  };
}

function resolvePreferredComponentForLayout(
  layoutType: AiComponentLayoutType,
  catalog: AiComponentCatalogEntry[],
): AiComponentCatalogEntry | null {
  const preferredKeys = PREFERRED_COMPONENTS_BY_LAYOUT[layoutType] ?? [];
  for (const componentKey of preferredKeys) {
    const match = catalog.find((entry) => entry.componentKey === componentKey);
    if (match) {
      return match;
    }
  }

  return catalog.find((entry) => entry.layoutType === layoutType) ?? null;
}

export function planSiteFromPrompt(input: AiSitePlannerInput): AiSitePlan {
  const promptAnalysis = analyzePrompt(input.prompt);
  const { projectType, builderProjectType, normalizedSiteType } = resolvePlannerProjectType(input.projectType, input.prompt);
  const componentCatalog = input.componentCatalog ?? getAllowedComponentCatalog(projectType);
  const requiredLayoutTypes = promptAnalysis.requiredSections
    .map((slug) => SECTION_SLUG_TO_LAYOUT_TYPE[slug] ?? null)
    .filter((entry): entry is AiComponentLayoutType => entry !== null);
  const layoutTypes = orderLayoutTypes([
    ...(PROJECT_LAYOUTS[projectType] ?? PROJECT_LAYOUTS.landing),
    ...requiredLayoutTypes,
  ]);

  const sections: AiSitePlanSection[] = layoutTypes.flatMap((layoutType) => {
    const component = resolvePreferredComponentForLayout(layoutType, componentCatalog);
    if (!component) {
      return [];
    }

    const variant = selectComponentVariant({
      componentKey: component.componentKey,
      prompt: input.prompt,
      projectType,
      tone: promptAnalysis.tone,
    });

    return [{
      componentKey: component.componentKey,
      label: component.label,
      layoutType,
      ...(variant ? { variant } : {}),
    }];
  });

  if (promptAnalysis.requiredSections.includes('pricing')) {
    const pricingComponent = resolvePreferredComponentForLayout('features', componentCatalog);
    if (pricingComponent) {
      const pricingVariant = selectComponentVariant({
        componentKey: pricingComponent.componentKey,
        prompt: `${input.prompt} pricing`,
        projectType,
        tone: promptAnalysis.tone,
      });
      const footerIndex = sections.findIndex((section) => section.layoutType === 'footer');
      const pricingSection: AiSitePlanSection = {
        componentKey: pricingComponent.componentKey,
        label: pricingComponent.label,
        layoutType: 'features',
        ...(pricingVariant ? { variant: pricingVariant } : {}),
      };
      if (footerIndex >= 0) {
        sections.splice(footerIndex, 0, pricingSection);
      } else {
        sections.push(pricingSection);
      }
    }
  }

  return {
    projectType,
    builderProjectType,
    pages: [{
      name: 'home',
      sections,
    }],
    available_components: componentCatalog.map((component) => component.componentKey),
    project: {
      type: normalizedSiteType,
    },
  };
}

/**
 * Converts a site plan to the display format (component short name + variant).
 * Useful for UI or logging. Component names are derived from registry key (e.g. webu_header_01 → header).
 */
export function planToDisplayFormat(result: SitePlanResult): SitePlanDisplaySection[] {
  return result.sections.map((s) => {
    const short = s.componentKey
      .replace(/^webu_/, '')
      .replace(/_01$/, '')
      .replace(/_/g, '')
      .replace(/general/, '');
    const component = short || s.componentKey;
    const variant = s.variant ?? DEFAULT_VARIANTS[s.componentKey] ?? '';
    return { component, variant };
  });
}
