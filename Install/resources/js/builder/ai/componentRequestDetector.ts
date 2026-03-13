/**
 * Part 1 — AI Component Request Detection.
 *
 * Detects when the user is asking for a component/section that does not exist in the
 * component registry. When not found, the caller can trigger the AI component generator.
 *
 * Example prompts: "Create pricing table", "Create testimonials slider", "Create team section",
 * "Create FAQ accordion", "Create feature comparison table".
 */

import { hasEntry } from '../componentRegistry';

// ---------------------------------------------------------------------------
// Output types
// ---------------------------------------------------------------------------

export interface ComponentRequestDetectionResult {
  /** True if the prompt looks like a request to create/add a component or section. */
  isComponentRequest: boolean;
  /** Raw phrase extracted (e.g. "pricing table", "testimonials slider"). */
  requestedPhrase?: string;
  /** Normalized slug for the requested component type (e.g. "pricing_table", "team_section"). */
  normalizedSlug?: string;
  /** True if a matching or substitute component exists in the registry. */
  existsInRegistry: boolean;
  /** Registry ID to use when existsInRegistry is true (e.g. webu_general_features_01). */
  registryId?: string;
  /** True when the user asked for a component that does not exist → caller should trigger component generator. */
  shouldTriggerGenerator: boolean;
}

// ---------------------------------------------------------------------------
// Request intent: phrases that indicate "create/add/build X"
// ---------------------------------------------------------------------------

const REQUEST_VERBS = [
  'create',
  'add',
  'build',
  'make',
  'generate',
  'insert',
  'need',
  'want',
  'give me',
  'i need',
  'i want',
  'we need',
  'can you create',
  'can you add',
  'add a',
  'add an',
];

// ---------------------------------------------------------------------------
// Known component/section phrases → normalized slug
// ---------------------------------------------------------------------------

/** Phrases that describe a component/section. Order matters: longer/more specific first. */
const COMPONENT_PHRASE_TO_SLUG: Array<{ phrases: string[]; slug: string }> = [
  { phrases: ['feature comparison table', 'comparison table', 'feature comparison'], slug: 'feature_comparison_table' },
  { phrases: ['pricing table', 'pricing grid', 'plans table', 'pricing section'], slug: 'pricing_table' },
  { phrases: ['testimonials slider', 'testimonial slider', 'reviews slider'], slug: 'testimonials_slider' },
  { phrases: ['faq accordion', 'faq accordions', 'accordion faq', 'expandable faq'], slug: 'faq_accordion' },
  { phrases: ['team section', 'team grid', 'team members', 'our team'], slug: 'team_section' },
  { phrases: ['stats section', 'numbers section', 'counter section'], slug: 'stats_section' },
  { phrases: ['logo strip', 'logo bar', 'partners strip', 'trust badges'], slug: 'logo_strip' },
  { phrases: ['contact form', 'contact section'], slug: 'contact_form' },
  { phrases: ['newsletter signup', 'email signup', 'subscribe section'], slug: 'newsletter_signup' },
  { phrases: ['pricing', 'plans'], slug: 'pricing' },
  { phrases: ['testimonials', 'reviews', 'social proof'], slug: 'testimonials' },
  { phrases: ['faq', 'faqs', 'questions'], slug: 'faq' },
  { phrases: ['team', 'team members'], slug: 'team' },
  { phrases: ['header', 'nav', 'navigation'], slug: 'header' },
  { phrases: ['hero', 'banner'], slug: 'hero' },
  { phrases: ['features', 'benefits'], slug: 'features' },
  { phrases: ['cta', 'call to action'], slug: 'cta' },
  { phrases: ['footer'], slug: 'footer' },
  { phrases: ['cards', 'card grid'], slug: 'cards' },
  { phrases: ['grid', 'product grid'], slug: 'grid' },
];

// ---------------------------------------------------------------------------
// Slug → registry ID (only for slugs that have a dedicated or substitute component)
// ---------------------------------------------------------------------------

/** Slugs that map to an existing registry component. Slugs not in this map → not in registry. */
const SLUG_TO_REGISTRY_ID: Record<string, string> = {
  header: 'webu_header_01',
  hero: 'webu_general_hero_01',
  features: 'webu_general_features_01',
  pricing: 'webu_general_features_01',
  cta: 'webu_general_cta_01',
  footer: 'webu_footer_01',
  cards: 'webu_general_cards_01',
  testimonials: 'webu_general_cards_01',
  faq: 'webu_general_cards_01',
  grid: 'webu_general_grid_01',
  navigation: 'webu_general_navigation_01',
};

// ---------------------------------------------------------------------------
// Parsing
// ---------------------------------------------------------------------------

function normalizePrompt(prompt: string): string {
  return prompt.toLowerCase().trim().replace(/\s+/g, ' ');
}

/**
 * Extracts the component/section phrase from a request (e.g. "create pricing table" → "pricing table").
 */
function extractRequestedPhrase(normalized: string): string | null {
  for (const verb of REQUEST_VERBS) {
    const prefix = verb + ' ';
    if (normalized.startsWith(prefix)) {
      const rest = normalized.slice(prefix.length).trim();
      if (rest.length > 0) return rest;
    }
  }
  if (/^(add|create|build|make|generate|insert)\s+/i.test(normalized)) {
    const match = normalized.match(/^(?:add|create|build|make|generate|insert)\s+(?:a\s+|an\s+)?(.+)$/i);
    if (match?.[1]) return match[1].trim();
  }
  return null;
}

/**
 * Maps a phrase like "pricing table" to a normalized slug (e.g. "pricing_table").
 */
function phraseToSlug(phrase: string): string | null {
  const normalized = normalizePrompt(phrase);
  for (const { phrases, slug } of COMPONENT_PHRASE_TO_SLUG) {
    for (const p of phrases) {
      if (normalized === p || normalized.includes(p)) return slug;
    }
  }
  return null;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Detects if the user prompt is asking for a component/section and whether that component
 * exists in the registry. When it does not exist, the caller should trigger the component generator.
 *
 * @param prompt — User message (e.g. "Create pricing table", "Add testimonials slider").
 * @returns Detection result with isComponentRequest, existsInRegistry, shouldTriggerGenerator, etc.
 */
export function detectComponentRequest(prompt: string): ComponentRequestDetectionResult {
  const normalized = normalizePrompt(prompt);
  if (normalized.length === 0) {
    return {
      isComponentRequest: false,
      existsInRegistry: false,
      shouldTriggerGenerator: false,
    };
  }

  const requestedPhrase = extractRequestedPhrase(normalized);
  if (!requestedPhrase) {
    return {
      isComponentRequest: false,
      existsInRegistry: false,
      shouldTriggerGenerator: false,
    };
  }

  const slug = phraseToSlug(requestedPhrase);
  if (!slug) {
    return {
      isComponentRequest: true,
      requestedPhrase,
      existsInRegistry: false,
      shouldTriggerGenerator: true,
    };
  }

  const registryId = SLUG_TO_REGISTRY_ID[slug];
  const existsInRegistry = !!registryId && hasEntry(registryId);

  return {
    isComponentRequest: true,
    requestedPhrase,
    normalizedSlug: slug,
    existsInRegistry,
    ...(registryId && existsInRegistry && { registryId }),
    shouldTriggerGenerator: !existsInRegistry,
  };
}

/**
 * Convenience: returns true only when the user asked for a component that does not exist,
 * so the caller should trigger the AI component generator.
 */
export function shouldTriggerComponentGenerator(prompt: string): boolean {
  return detectComponentRequest(prompt).shouldTriggerGenerator;
}
