/**
 * AI Website Generation — prompt → site structure → component selection → variant → props → builder state.
 *
 * Phase 8: User says "Create SaaS landing page" → AI returns structure (e.g. Header, Hero, Features, Pricing, CTA, Footer)
 * → this module builds a BuilderPageModel from that structure and optional variant/props per section.
 *
 * Process:
 *   prompt → site structure → component selection → variant selection → props generation → builder state creation
 */

import type { BuilderComponentInstance } from './core/types';
import type { ProjectType } from './projectTypes';
import { getEntry } from './registry/componentRegistry';
import { getDefaultProps } from './componentRegistry';
import type { BuilderSection } from './visual/treeUtils';

/** One section in the generated site structure (from AI or template). */
export interface SiteStructureSection {
  /** Registry component key (e.g. webu_header_01, webu_general_hero_01). */
  componentKey: string;
  /** Optional variant (e.g. header-1, hero-1). */
  variant?: string;
  /** Optional props override (merged with registry defaults). */
  props?: Record<string, unknown>;
}

export interface BuildTreeFromStructureInput {
  /** Project type for the generated page (e.g. saas, landing). */
  projectType: ProjectType;
  /** Ordered list of sections to generate. */
  structure: SiteStructureSection[];
  /** Optional: custom id generator (default: section slug + index). */
  generateId?: (componentKey: string, index: number) => string;
}

/**
 * Generates a stable id for a node from componentKey and index.
 * e.g. webu_header_01, 0 → "header-1"; webu_general_hero_01, 1 → "hero-2"
 */
function defaultGenerateId(componentKey: string, index: number): string {
  const slug = componentKey
    .replace(/^webu_/, '')
    .replace(/_01$/, '')
    .replace(/_/g, '-')
    .toLowerCase();
  const base = slug || 'section';
  return `${base}-${index + 1}`;
}

/**
 * Builds a builder component tree from a site structure.
 * For each section: resolve registry defaults, merge with optional props and variant, create node.
 * Does not apply to store; caller must setProjectType + setComponentTree.
 */
export function buildTreeFromStructure(input: BuildTreeFromStructureInput): BuilderComponentInstance[] {
  const { structure, generateId = defaultGenerateId } = input;
  const nodes: BuilderComponentInstance[] = [];

  for (let i = 0; i < structure.length; i++) {
    const section = structure[i]!;
    const { componentKey, variant, props: overrideProps } = section;
    const id = generateId(componentKey, i);
    const entry = getEntry(componentKey);
    const defaults = entry?.defaults && typeof entry.defaults === 'object'
      ? (entry.defaults as Record<string, unknown>)
      : getDefaultProps(componentKey);
    const mergedProps = { ...defaults, ...overrideProps };
    if (variant !== undefined) {
      mergedProps.variant = variant;
    }
    nodes.push({
      id,
      componentKey,
      ...(variant !== undefined && { variant }),
      props: mergedProps,
    });
  }

  return nodes;
}

/**
 * Converts a builder component tree to section drafts (BuilderSection[]) for Cms editing store.
 * Use after buildTreeFromStructure when applying to sectionsDraft.
 */
export function treeToSectionsDraft(tree: BuilderComponentInstance[]): BuilderSection[] {
  return tree.map((node) => {
    const props = node.props && typeof node.props === 'object' ? node.props : {};
    return {
      localId: node.id,
      type: node.componentKey,
      props,
      propsText: JSON.stringify(props),
      propsError: null,
      bindingMeta: null,
    };
  });
}

/** Default SaaS landing page structure (registry keys). Use when AI returns a high-level "saas_landing" template. */
export const DEFAULT_SAAS_LANDING_STRUCTURE: SiteStructureSection[] = [
  { componentKey: 'webu_header_01' },
  { componentKey: 'webu_general_hero_01' },
  { componentKey: 'webu_general_features_01' },
  { componentKey: 'webu_general_cta_01' },
  { componentKey: 'webu_footer_01' },
];

/** Default landing structure: Header, Hero, Features, CTA, Footer (no pricing/testimonials if not in registry). */
export const DEFAULT_LANDING_STRUCTURE: SiteStructureSection[] = [
  { componentKey: 'webu_header_01' },
  { componentKey: 'webu_general_hero_01' },
  { componentKey: 'webu_general_features_01' },
  { componentKey: 'webu_general_cta_01' },
  { componentKey: 'webu_footer_01' },
];

/** Default ecommerce structure (Phase 15): Header, Hero, Features, CTA, Footer. AI can override with industry-specific props (e.g. furniture store). */
export const DEFAULT_ECOMMERCE_STRUCTURE: SiteStructureSection[] = [
  { componentKey: 'webu_header_01' },
  { componentKey: 'webu_general_hero_01' },
  { componentKey: 'webu_general_features_01' },
  { componentKey: 'webu_general_cta_01' },
  { componentKey: 'webu_footer_01' },
];
