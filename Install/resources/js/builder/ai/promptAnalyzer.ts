/**
 * Prompt Analysis Engine — extract structured data from a natural-language prompt.
 *
 * Part 2: Detects site type, industry, required sections, design tone, and functional needs
 * using keyword/rule-based detection (no LLM). Output feeds AI website generation and structure selection.
 */

import type { ProjectType } from '../projectTypes';
import { getSectionsForProjectType } from './projectTypeIntegration';

// ---------------------------------------------------------------------------
// Output type
// ---------------------------------------------------------------------------

/** Section identifiers in short form (e.g. for mapping to registry componentKeys). */
export type SectionSlug =
  | 'header'
  | 'hero'
  | 'productGrid'
  | 'features'
  | 'pricing'
  | 'testimonials'
  | 'cta'
  | 'footer'
  | 'navigation'
  | 'cards'
  | 'grid'
  | 'faq'
  | 'contact'
  | 'blog'
  | 'menu'
  | 'booking'
  | 'gallery';

export interface PromptAnalysisResult {
  /** Detected project type (ecommerce, restaurant, saas, etc.). */
  projectType: ProjectType;
  /** Industry or vertical (e.g. furniture, fashion, tech). */
  industry: string | null;
  /** Design tone (e.g. modern, minimal, professional). */
  tone: string | null;
  /** Sections directly requested by the user prompt before any defaults are applied. */
  explicitSections: SectionSlug[];
  /** Ordered list of section slugs the site should include. */
  requiredSections: SectionSlug[];
  /** Functional requirements (e.g. contact form, cart, booking). */
  functionalNeeds: string[];
}

// ---------------------------------------------------------------------------
// Detection rules: prompt phrase → project type
// ---------------------------------------------------------------------------

const PROJECT_TYPE_RULES: Array<{ keywords: string[]; projectType: ProjectType }> = [
  { keywords: ['store', 'shop', 'ecommerce', 'e-commerce', 'online store', 'marketplace', 'sell online', 'products for sale'], projectType: 'ecommerce' },
  { keywords: ['restaurant', 'cafe', 'café', 'coffee shop', 'dining', 'food venue', 'menu'], projectType: 'restaurant' },
  { keywords: ['hotel', 'accommodation', 'lodging', 'resort', 'bed and breakfast'], projectType: 'hotel' },
  { keywords: ['portfolio', 'showcase', 'my work', 'projects', 'freelancer', 'creative work'], projectType: 'portfolio' },
  { keywords: ['startup', 'saas', 'software', 'app', 'tool', 'platform', 'product launch', 'ai tool', 'marketing tool'], projectType: 'saas' },
  { keywords: ['blog', 'articles', 'news', 'magazine'], projectType: 'blog' },
  { keywords: ['education', 'course', 'learning', 'school', 'training', 'tutorials'], projectType: 'education' },
  { keywords: ['business', 'company', 'corporate', 'agency', 'consulting'], projectType: 'business' },
  { keywords: ['landing', 'marketing', 'promo', 'campaign', 'lead generation'], projectType: 'landing' },
];

// ---------------------------------------------------------------------------
// Industry hints (prompt phrase → industry label)
// ---------------------------------------------------------------------------

const INDUSTRY_RULES: Array<{ keywords: string[]; industry: string }> = [
  { keywords: ['furniture', 'home decor', 'interior'], industry: 'furniture' },
  { keywords: ['fashion', 'clothing', 'apparel', 'boutique'], industry: 'fashion' },
  { keywords: ['tech', 'technology', 'software', 'it'], industry: 'tech' },
  { keywords: ['food', 'restaurant', 'catering', 'bakery'], industry: 'food' },
  { keywords: ['travel', 'tourism', 'vacation'], industry: 'travel' },
  { keywords: ['health', 'fitness', 'wellness', 'medical'], industry: 'health' },
  { keywords: ['beauty', 'cosmetics', 'skincare'], industry: 'beauty' },
  { keywords: ['real estate', 'property', 'housing'], industry: 'real estate' },
  { keywords: ['finance', 'banking', 'insurance'], industry: 'finance' },
  { keywords: ['education', 'course', 'learning'], industry: 'education' },
  { keywords: ['legal', 'law firm', 'attorney'], industry: 'legal' },
  { keywords: ['photography', 'photo', 'photographer'], industry: 'photography' },
];

// ---------------------------------------------------------------------------
// Tone hints
// ---------------------------------------------------------------------------

const TONE_RULES: Array<{ keywords: string[]; tone: string }> = [
  { keywords: ['modern', 'contemporary', 'sleek'], tone: 'modern' },
  { keywords: ['minimal', 'minimalist', 'clean', 'simple'], tone: 'minimal' },
  { keywords: ['bold', 'vibrant', 'striking'], tone: 'bold' },
  { keywords: ['professional', 'corporate', 'business'], tone: 'professional' },
  { keywords: ['playful', 'fun', 'friendly'], tone: 'playful' },
  { keywords: ['luxury', 'premium', 'high-end'], tone: 'luxury' },
  { keywords: ['creative', 'artistic', 'unique'], tone: 'creative' },
  { keywords: ['trustworthy', 'reliable', 'serious'], tone: 'trustworthy' },
];

// ---------------------------------------------------------------------------
// Section hints (prompt phrase → section slug)
// ---------------------------------------------------------------------------

const SECTION_RULES: Array<{ keywords: string[]; section: SectionSlug }> = [
  { keywords: ['header', 'nav', 'navigation', 'menu bar'], section: 'header' },
  { keywords: ['hero', 'banner', 'above the fold'], section: 'hero' },
  { keywords: ['product grid', 'products', 'catalog', 'shop grid'], section: 'productGrid' },
  { keywords: ['features', 'benefits', 'why us'], section: 'features' },
  { keywords: ['pricing', 'plans', 'subscription'], section: 'pricing' },
  { keywords: ['testimonials', 'reviews', 'social proof'], section: 'testimonials' },
  { keywords: ['cta', 'call to action', 'sign up', 'get started'], section: 'cta' },
  { keywords: ['footer', 'bottom'], section: 'footer' },
  { keywords: ['faq', 'questions', 'faqs'], section: 'faq' },
  { keywords: ['contact', 'contact form', 'get in touch'], section: 'contact' },
  { keywords: ['blog', 'articles'], section: 'blog' },
  { keywords: ['menu', 'food menu', 'dishes'], section: 'menu' },
  { keywords: ['booking', 'reservation', 'book now'], section: 'booking' },
  { keywords: ['gallery', 'portfolio grid', 'images'], section: 'gallery' },
  { keywords: ['cards', 'card grid'], section: 'cards' },
  { keywords: ['grid', 'layout grid'], section: 'grid' },
];

// ---------------------------------------------------------------------------
// Functional needs
// ---------------------------------------------------------------------------

const FUNCTIONAL_NEEDS_RULES: Array<{ keywords: string[]; need: string }> = [
  { keywords: ['contact form', 'contact us', 'get in touch', 'inquiry'], need: 'contact_form' },
  { keywords: ['cart', 'checkout', 'buy', 'purchase'], need: 'cart' },
  { keywords: ['booking', 'reservation', 'book now', 'schedule'], need: 'booking' },
  { keywords: ['search', 'search bar'], need: 'search' },
  { keywords: ['newsletter', 'subscribe', 'sign up'], need: 'newsletter' },
  { keywords: ['login', 'sign in', 'account'], need: 'auth' },
  { keywords: ['blog', 'articles'], need: 'blog' },
  { keywords: ['menu', 'food menu'], need: 'menu' },
];

const DEFAULT_PROJECT_TYPE: ProjectType = 'landing';

/** Default sections per project type (short slugs). Used when prompt does not specify sections. */
/** Default sections per project type (Part 12: from projectTypeIntegration). */
function getDefaultSectionsForProjectType(projectType: ProjectType): SectionSlug[] {
  return getSectionsForProjectType(projectType);
}

// ---------------------------------------------------------------------------
// Matching helpers
// ---------------------------------------------------------------------------

function normalizePrompt(prompt: string): string {
  return prompt.toLowerCase().trim().replace(/\s+/g, ' ');
}

function matchRules<T>(
  normalized: string,
  rules: Array<{ keywords: string[]; [k: string]: unknown }>,
  key: string
): T | null {
  for (const rule of rules) {
    const keywords = rule.keywords as string[];
    for (const kw of keywords) {
      if (normalized.includes(kw)) {
        return rule[key] as T;
      }
    }
  }
  return null;
}

function matchAllRules<T>(
  normalized: string,
  rules: Array<{ keywords: string[]; [k: string]: unknown }>,
  key: string
): T[] {
  const seen = new Set<string>();
  const result: T[] = [];
  for (const rule of rules) {
    const keywords = rule.keywords as string[];
    for (const kw of keywords) {
      if (normalized.includes(kw)) {
        const value = rule[key] as T;
        if (value != null && typeof value === 'string' && !seen.has(value)) {
          seen.add(value);
          result.push(value);
        }
      }
    }
  }
  return result;
}

/** Order section slugs by a canonical order; add any from prompt first in that order, then append extras. */
function orderSections(requested: SectionSlug[], projectType: ProjectType): SectionSlug[] {
  const canonical = getDefaultSectionsForProjectType(projectType);
  const ordered: SectionSlug[] = [];
  const added = new Set<SectionSlug>();
  for (const s of canonical) {
    if (requested.includes(s) || ordered.length === 0) {
      if (!added.has(s)) {
        ordered.push(s);
        added.add(s);
      }
    }
  }
  for (const s of requested) {
    if (!added.has(s)) {
      ordered.push(s);
      added.add(s);
    }
  }
  return ordered.length > 0 ? ordered : canonical;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Analyzes a natural-language prompt and returns structured data for site generation.
 * Uses keyword/rule-based detection: site type, industry, tone, required sections, functional needs.
 */
export function analyzePrompt(prompt: string): PromptAnalysisResult {
  const normalized = normalizePrompt(prompt);
  if (normalized.length === 0) {
    return {
      projectType: DEFAULT_PROJECT_TYPE,
      industry: null,
      tone: null,
      explicitSections: [],
      requiredSections: getDefaultSectionsForProjectType(DEFAULT_PROJECT_TYPE),
      functionalNeeds: [],
    };
  }

  const projectType = matchRules<ProjectType>(normalized, PROJECT_TYPE_RULES, 'projectType') ?? DEFAULT_PROJECT_TYPE;
  const industry = matchRules<string>(normalized, INDUSTRY_RULES, 'industry') ?? null;
  const tone = matchRules<string>(normalized, TONE_RULES, 'tone') ?? null;
  const sectionHits = matchAllRules<SectionSlug>(normalized, SECTION_RULES, 'section');
  const requiredSections = orderSections(
    sectionHits.length > 0 ? sectionHits : [...getDefaultSectionsForProjectType(projectType)],
    projectType
  );
  const functionalNeeds = matchAllRules<string>(normalized, FUNCTIONAL_NEEDS_RULES, 'need');

  return {
    projectType,
    industry,
    tone,
    explicitSections: sectionHits,
    requiredSections,
    functionalNeeds,
  };
}
