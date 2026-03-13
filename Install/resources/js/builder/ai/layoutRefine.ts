/**
 * Part 12 — AI Improvement Loop: refine layout from user commands.
 *
 * Example commands: "Improve this layout", "Make hero more modern", "Add CTA section", "Make layout minimal".
 * System regenerates selected sections (variants or adds section).
 */

import type { BuilderComponentInstance } from '../core/types';
import type { BuilderSection } from '../visual/treeUtils';
import { sectionToComponentInstance } from '../types';
import { getEntry } from '../componentRegistry';
import { getVariantForStyle, type DesignStyle } from './designStyleAnalyzer';
import { treeToSectionsDraft } from '../aiSiteGeneration';
import { AVAILABLE_VARIANTS_BY_COMPONENT } from './componentSelector';

// ---------------------------------------------------------------------------
// Refine intent and parse result
// ---------------------------------------------------------------------------

export type RefineIntent =
  | 'improve_layout'
  | 'make_section_modern'
  | 'add_section'
  | 'make_layout_style';

export interface RefineParseResult {
  intent: RefineIntent;
  /** Section type to target (e.g. "hero", "cta") for make_section_modern. */
  targetSectionType?: string;
  /** Style to apply (e.g. "minimal", "modern") for make_layout_style or improve_layout. */
  style?: DesignStyle;
  /** Registry key to add (e.g. webu_general_cta_01) for add_section. */
  sectionKeyToAdd?: string;
}

// ---------------------------------------------------------------------------
// Command parsing (keyword-based)
// ---------------------------------------------------------------------------

const IMPROVE_PHRASES = ['improve', 'better', 'enhance', 'polish', 'refine'];
const MODERN_PHRASES = ['modern', 'contemporary', 'sleek'];
const MINIMAL_PHRASES = ['minimal', 'minimalist', 'clean', 'simple'];
const ADD_CTA_PHRASES = ['add cta', 'add call to action', 'add cta section', 'insert cta'];
const STYLE_BY_PHRASE: Array<{ phrases: string[]; style: DesignStyle }> = [
  { phrases: MINIMAL_PHRASES, style: 'minimal' },
  { phrases: MODERN_PHRASES, style: 'modern' },
  { phrases: ['corporate', 'professional'], style: 'corporate' },
  { phrases: ['startup'], style: 'startup' },
  { phrases: ['ecommerce', 'e-commerce'], style: 'ecommerce' },
  { phrases: ['dark'], style: 'dark' },
];
const SECTION_TYPE_BY_WORD: Record<string, string> = {
  hero: 'hero',
  features: 'features',
  cta: 'cta',
  footer: 'footer',
  header: 'header',
  testimonials: 'testimonials',
  pricing: 'pricing',
  cards: 'cards',
};
const SECTION_TYPE_TO_REGISTRY_KEY: Record<string, string> = {
  hero: 'webu_general_hero_01',
  features: 'webu_general_features_01',
  cta: 'webu_general_cta_01',
  footer: 'webu_footer_01',
  header: 'webu_header_01',
  testimonials: 'webu_general_cards_01',
  pricing: 'webu_general_features_01',
  cards: 'webu_general_cards_01',
};

/** Modern variant indices per component (0-based) for "make hero more modern". */
const MODERN_VARIANT_INDEX: Record<string, number> = {
  webu_general_hero_01: 2,
  webu_general_features_01: 1,
  webu_general_cta_01: 1,
  webu_general_cards_01: 1,
  webu_general_grid_01: 1,
};

function normalizeCommand(cmd: string): string {
  return cmd.toLowerCase().trim().replace(/\s+/g, ' ');
}

/**
 * Parses a user refine command into intent and optional target/style/sectionKey.
 */
export function parseRefineCommand(command: string): RefineParseResult {
  const norm = normalizeCommand(command);
  if (norm.length === 0) {
    return { intent: 'improve_layout', style: 'modern' };
  }

  for (const phrase of ADD_CTA_PHRASES) {
    if (norm.includes(phrase)) {
      return {
        intent: 'add_section',
        sectionKeyToAdd: 'webu_general_cta_01',
      };
    }
  }

  for (const { phrases, style } of STYLE_BY_PHRASE) {
    for (const p of phrases) {
      if (norm.includes(p)) {
        if (norm.includes('layout') || norm.includes('page') || norm.includes('whole')) {
          return { intent: 'make_layout_style', style };
        }
        if (norm.includes('hero') || norm.includes('section')) {
          const target = norm.includes('hero') ? 'hero' : undefined;
          return {
            intent: 'make_section_modern',
            targetSectionType: target,
            style: p === 'modern' || p === 'contemporary' || p === 'sleek' ? 'modern' : style,
          };
        }
        return { intent: 'make_layout_style', style };
      }
    }
  }

  for (const phrase of IMPROVE_PHRASES) {
    if (norm.includes(phrase)) {
      return { intent: 'improve_layout', style: 'modern' };
    }
  }

  for (const [word, sectionType] of Object.entries(SECTION_TYPE_BY_WORD)) {
    if (norm.includes(word) && (norm.includes('modern') || norm.includes('change') || norm.includes('update'))) {
      return {
        intent: 'make_section_modern',
        targetSectionType: sectionType,
        style: 'modern',
      };
    }
  }

  return { intent: 'improve_layout', style: 'modern' };
}

// ---------------------------------------------------------------------------
// Apply refine to tree
// ---------------------------------------------------------------------------

function componentKeyToShortType(componentKey: string): string {
  const map: Record<string, string> = {
    webu_header_01: 'header',
    webu_footer_01: 'footer',
    webu_general_hero_01: 'hero',
    webu_general_features_01: 'features',
    webu_general_cta_01: 'cta',
    webu_general_cards_01: 'cards',
    webu_general_grid_01: 'grid',
  };
  return map[componentKey] ?? componentKey.replace(/^webu_/, '').replace(/_01$/, '');
}

function getModernVariant(componentKey: string): string {
  const variants = AVAILABLE_VARIANTS_BY_COMPONENT[componentKey];
  if (!variants?.length) return '';
  const idx = MODERN_VARIANT_INDEX[componentKey];
  if (idx != null && variants[idx]) return variants[idx]!;
  return variants[Math.min(1, variants.length - 1)] ?? variants[0] ?? '';
}

function applyStyleToTree(tree: BuilderComponentInstance[], style: DesignStyle): BuilderComponentInstance[] {
  const usedByComponent: Record<string, Set<string>> = {};
  return tree.map((node) => {
    const used = usedByComponent[node.componentKey] ?? new Set<string>();
    if (!usedByComponent[node.componentKey]) usedByComponent[node.componentKey] = used;
    const variant = getVariantForStyle(node.componentKey, style, used);
    if (variant) used.add(variant);
    const nextProps = { ...(node.props ?? {}), variant: variant || (node.props?.variant ?? node.variant) };
    return { ...node, props: nextProps, ...(variant && { variant }) };
  });
}

function applyMakeSectionModern(tree: BuilderComponentInstance[], targetSectionType?: string): BuilderComponentInstance[] {
  const targetKey =
    targetSectionType && SECTION_TYPE_TO_REGISTRY_KEY[targetSectionType]
      ? SECTION_TYPE_TO_REGISTRY_KEY[targetSectionType]
      : null;
  return tree.map((node) => {
    const shortType = componentKeyToShortType(node.componentKey);
    const match = !targetKey || node.componentKey === targetKey || shortType === targetSectionType;
    if (!match) return node;
    const modernVariant = getModernVariant(node.componentKey);
    if (!modernVariant) return node;
    const nextProps = { ...(node.props ?? {}), variant: modernVariant };
    return { ...node, props: nextProps, variant: modernVariant };
  });
}

function generateNewSectionId(componentKey: string, existingIds: Set<string>): string {
  const base = componentKeyToShortType(componentKey);
  for (let i = 1; i <= 100; i++) {
    const id = `${base}-${i}`;
    if (!existingIds.has(id)) return id;
  }
  return `${base}-${Date.now()}`;
}

function applyAddSection(
  tree: BuilderComponentInstance[],
  sectionKeyToAdd: string,
  insertBeforeIndex?: number
): BuilderComponentInstance[] {
  if (!getEntry(sectionKeyToAdd)) return tree;
  const existingIds = new Set(tree.map((n) => n.id));
  const id = generateNewSectionId(sectionKeyToAdd, existingIds);
  const variants = AVAILABLE_VARIANTS_BY_COMPONENT[sectionKeyToAdd];
  const variant = variants?.[0] ?? '';
  const newNode: BuilderComponentInstance = {
    id,
    componentKey: sectionKeyToAdd,
    ...(variant && { variant }),
    props: { variant },
  };
  const insertAt =
    insertBeforeIndex != null
      ? Math.max(0, Math.min(insertBeforeIndex, tree.length))
      : tree.length - 1;
  const before = tree.slice(0, insertAt);
  const after = tree.slice(insertAt);
  return [...before, newNode, ...after];
}

/**
 * Applies a refine parse result to a copy of the tree; returns updated tree.
 */
export function applyRefineToTree(
  tree: BuilderComponentInstance[],
  parseResult: RefineParseResult
): BuilderComponentInstance[] {
  const { intent, targetSectionType, style, sectionKeyToAdd } = parseResult;

  switch (intent) {
    case 'improve_layout':
      return applyStyleToTree(tree, style ?? 'modern');
    case 'make_layout_style':
      return applyStyleToTree(tree, style ?? 'minimal');
    case 'make_section_modern':
      return applyMakeSectionModern(tree, targetSectionType);
    case 'add_section':
      if (sectionKeyToAdd) {
        const footerIndex = tree.findIndex((n) => n.componentKey === 'webu_footer_01');
        const insertAt = footerIndex >= 0 ? footerIndex : tree.length;
        return applyAddSection(tree, sectionKeyToAdd, insertAt);
      }
      return tree;
    default:
      return applyStyleToTree(tree, 'modern');
  }
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

export interface RefineLayoutFromCommandOptions {
  /** Optional: pre-parsed result (skip parseRefineCommand). */
  parseResult?: RefineParseResult;
}

export interface RefineLayoutFromCommandResult {
  tree: BuilderComponentInstance[];
  sectionsDraft: BuilderSection[];
}

/**
 * Parses the user command, applies the refine to the current tree, and returns updated tree + sectionsDraft.
 * Caller applies result via setSectionsDraft(result.sectionsDraft) so the canvas regenerates.
 */
export function refineLayoutFromCommand(
  tree: BuilderComponentInstance[],
  command: string,
  options: RefineLayoutFromCommandOptions = {}
): RefineLayoutFromCommandResult {
  const parseResult = options.parseResult ?? parseRefineCommand(command);
  const nextTree = applyRefineToTree(tree, parseResult);
  const sectionsDraft = treeToSectionsDraft(nextTree);
  return { tree: nextTree, sectionsDraft };
}

/**
 * Convenience: refine from sectionsDraft (Cms state). Converts to tree, refines, returns new sectionsDraft.
 */
export function refineSectionsFromCommand(
  sectionsDraft: BuilderSection[],
  command: string,
  options: RefineLayoutFromCommandOptions = {}
): { sectionsDraft: BuilderSection[] } {
  const tree = sectionsDraft.map((s) => sectionToComponentInstance(s));
  const result = refineLayoutFromCommand(tree, command, options);
  return { sectionsDraft: result.sectionsDraft };
}
