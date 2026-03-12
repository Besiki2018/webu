/**
 * Content Generation Engine — use AI API to generate text content for builder sections.
 *
 * Part 5: Builds prompts and parses model output into structured content (title, subtitle, cta,
 * features, product highlights). Caller supplies the AI provider (e.g. backend proxy or OpenAI client).
 */

import type { ProjectType } from '../projectTypes';

// ---------------------------------------------------------------------------
// Request & result types
// ---------------------------------------------------------------------------

export type ContentSectionType = 'hero' | 'features' | 'cta' | 'productHighlights' | 'general';

export interface GenerateContentInput {
  /** Section to generate (hero, features, cta, product highlights). */
  sectionType: ContentSectionType;
  /** Project type (ecommerce, saas, etc.). */
  projectType: ProjectType;
  /** Industry or vertical (e.g. furniture, fashion). */
  industry: string | null;
  /** Design tone (e.g. modern, minimal). */
  tone?: string | null;
  /** Brand or site name. Optional. */
  brandName?: string | null;
  /** Content language code (e.g. en, es). */
  language?: string;
}

/** Hero section content. */
export interface HeroContentResult {
  title: string;
  subtitle: string;
  cta: string;
  eyebrow?: string;
  ctaSecondary?: string;
}

/** Single feature item. */
export interface FeatureItem {
  title: string;
  description: string;
}

/** Features section content. */
export interface FeaturesContentResult {
  title: string;
  items: FeatureItem[];
}

/** CTA section content. */
export interface CtaContentResult {
  title: string;
  subtitle?: string;
  buttonLabel: string;
}

/** Product highlight item. */
export interface ProductHighlightItem {
  name: string;
  description: string;
}

/** Product highlights content. */
export interface ProductHighlightsResult {
  title?: string;
  items: ProductHighlightItem[];
}

export type GeneratedContentResult =
  | HeroContentResult
  | FeaturesContentResult
  | CtaContentResult
  | ProductHighlightsResult;

// ---------------------------------------------------------------------------
// AI provider (injected by caller: backend proxy or SDK)
// ---------------------------------------------------------------------------

export interface ContentGeneratorProviderOptions {
  /** Ask the model to return valid JSON only. */
  jsonMode?: boolean;
}

/**
 * Provider function: takes a prompt and returns the model's text response.
 * The app should pass a function that calls its AI API (e.g. POST /api/ai/generate or OpenAI client).
 */
export type ContentGeneratorProvider = (
  prompt: string,
  options?: ContentGeneratorProviderOptions
) => Promise<string>;

// ---------------------------------------------------------------------------
// Prompt building
// ---------------------------------------------------------------------------

export function buildPrompt(input: GenerateContentInput): string {
  const { sectionType, projectType, industry, tone, brandName, language = 'en' } = input;
  const langNote = language !== 'en' ? ` Write all content in language code "${language}".` : '';
  const industryPhrase = industry ? ` for a ${industry} ${projectType === 'ecommerce' ? 'store' : projectType === 'restaurant' ? 'venue' : 'business'}` : ` for a ${projectType} project`;
  const brandPhrase = brandName ? ` Brand name: ${brandName}.` : '';
  const tonePhrase = tone ? ` Tone: ${tone}.` : '';

  switch (sectionType) {
    case 'hero':
      return `Generate hero section content${industryPhrase}.${brandPhrase}${tonePhrase}${langNote}

Return a JSON object only, no markdown or explanation, with these exact keys:
- "title" (string): main headline, compelling and concise
- "subtitle" (string): supporting line, one sentence
- "cta" (string): primary button text, 1-3 words
- "eyebrow" (string, optional): small label above the title
- "ctaSecondary" (string, optional): secondary button text if needed

Example: {"title": "Beautiful Furniture for Modern Living", "subtitle": "Discover handcrafted pieces designed for comfort and style.", "cta": "Shop Now"}`;

    case 'features':
      return `Generate features section content${industryPhrase}.${brandPhrase}${tonePhrase}${langNote}

Return a JSON object only with:
- "title" (string): section heading
- "items" (array): 3-4 objects, each with "title" (string) and "description" (string, one sentence)`;

    case 'cta':
      return `Generate call-to-action section content${industryPhrase}.${brandPhrase}${tonePhrase}${langNote}

Return a JSON object only with:
- "title" (string): headline
- "subtitle" (string, optional): supporting text
- "buttonLabel" (string): button text, 1-4 words`;

    case 'productHighlights':
      return `Generate product highlights content${industryPhrase}.${brandPhrase}${tonePhrase}${langNote}

Return a JSON object only with:
- "title" (string, optional): section heading
- "items" (array): 3-6 objects, each with "name" (string) and "description" (string, one sentence)`;

    default:
      return `Generate short marketing content${industryPhrase}.${brandPhrase}${tonePhrase}${langNote}

Return a JSON object with "title", "subtitle", and "cta" strings.`;
  }
}

// ---------------------------------------------------------------------------
// Response parsing
// ---------------------------------------------------------------------------

function extractJson(text: string): string {
  const trimmed = text.trim();
  const start = trimmed.indexOf('{');
  const end = trimmed.lastIndexOf('}');
  if (start === -1 || end === -1 || end <= start) return trimmed;
  return trimmed.slice(start, end + 1);
}

function parseHeroContent(data: Record<string, unknown>): HeroContentResult {
  return {
    title: typeof data.title === 'string' ? data.title : 'Welcome',
    subtitle: typeof data.subtitle === 'string' ? data.subtitle : '',
    cta: typeof data.cta === 'string' ? data.cta : 'Get started',
    eyebrow: typeof data.eyebrow === 'string' ? data.eyebrow : undefined,
    ctaSecondary: typeof data.ctaSecondary === 'string' ? data.ctaSecondary : undefined,
  };
}

function parseFeaturesContent(data: Record<string, unknown>): FeaturesContentResult {
  const items = Array.isArray(data.items)
    ? (data.items as unknown[])
        .filter((x): x is Record<string, unknown> => x != null && typeof x === 'object')
        .map((x) => ({
          title: typeof x.title === 'string' ? x.title : 'Feature',
          description: typeof x.description === 'string' ? x.description : '',
        }))
    : [];
  return {
    title: typeof data.title === 'string' ? data.title : 'Features',
    items: items.length > 0 ? items : [{ title: 'Quality', description: 'Built to last.' }, { title: 'Design', description: 'Thoughtfully crafted.' }, { title: 'Support', description: 'Here when you need us.' }],
  };
}

function parseCtaContent(data: Record<string, unknown>): CtaContentResult {
  return {
    title: typeof data.title === 'string' ? data.title : 'Ready to get started?',
    subtitle: typeof data.subtitle === 'string' ? data.subtitle : undefined,
    buttonLabel: typeof data.buttonLabel === 'string' ? data.buttonLabel : 'Get started',
  };
}

function parseProductHighlightsContent(data: Record<string, unknown>): ProductHighlightsResult {
  const items = Array.isArray(data.items)
    ? (data.items as unknown[])
        .filter((x): x is Record<string, unknown> => x != null && typeof x === 'object')
        .map((x) => ({
          name: typeof x.name === 'string' ? x.name : 'Item',
          description: typeof x.description === 'string' ? x.description : '',
        }))
    : [];
  return {
    title: typeof data.title === 'string' ? data.title : undefined,
    items: items.length > 0 ? items : [],
  };
}

function parseResponse(sectionType: ContentSectionType, raw: string): GeneratedContentResult {
  const json = extractJson(raw);
  let data: Record<string, unknown>;
  try {
    data = JSON.parse(json) as Record<string, unknown>;
  } catch {
    data = {};
  }
  switch (sectionType) {
    case 'hero':
      return parseHeroContent(data);
    case 'features':
      return parseFeaturesContent(data);
    case 'cta':
      return parseCtaContent(data);
    case 'productHighlights':
      return parseProductHighlightsContent(data);
    default:
      return parseHeroContent(data);
  }
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Generates section content using the provided AI provider.
 * Example: generateContent({ sectionType: 'hero', projectType: 'ecommerce', industry: 'furniture' }, provider)
 * → { title: "Beautiful Furniture for Modern Living", subtitle: "...", cta: "Shop Now" }
 */
export async function generateContent(
  input: GenerateContentInput,
  provider: ContentGeneratorProvider
): Promise<GeneratedContentResult> {
  const prompt = buildPrompt(input);
  const raw = await provider(prompt, { jsonMode: true });
  return parseResponse(input.sectionType, raw);
}

/**
 * Convenience: generate hero content (title, subtitle, cta).
 * Same as generateContent({ ...input, sectionType: 'hero' }, provider) with result cast to HeroContentResult.
 */
export async function generateHeroContent(
  input: Omit<GenerateContentInput, 'sectionType'>,
  provider: ContentGeneratorProvider
): Promise<HeroContentResult> {
  const result = await generateContent({ ...input, sectionType: 'hero' }, provider);
  return result as HeroContentResult;
}
