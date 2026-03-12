/**
 * Part 9 — AI Image Generation.
 *
 * Optional AI-generated images for sections (hero, CTA, etc.).
 * Use DALL·E, Stability, Replicate, or any provider that returns an image URL.
 * Insert the URL into component props (image, backgroundImage).
 */

// ---------------------------------------------------------------------------
// Provider (injected by app: backend proxy or SDK)
// ---------------------------------------------------------------------------

export interface GenerateImageOptions {
  /** Preferred size (e.g. "1024x1024", "1792x1024"). Provider-dependent. */
  size?: string;
  /** Style hint (e.g. "photorealistic", "illustration"). Provider-dependent. */
  style?: string;
}

/**
 * Provider function: takes a text prompt and returns the generated image URL.
 * The app wires this to DALL·E, Stability, Replicate, or its own backend.
 */
export type ImageGeneratorProvider = (
  prompt: string,
  options?: GenerateImageOptions
) => Promise<string>;

// ---------------------------------------------------------------------------
// Image prompt building (section + context → prompt for model)
// ---------------------------------------------------------------------------

export interface ImagePromptContext {
  /** Section or use case (hero, cta, background, product). */
  sectionType: 'hero' | 'cta' | 'background' | 'product' | 'general';
  /** Industry (e.g. furniture, fashion). */
  industry?: string | null;
  /** Design tone (e.g. modern, minimal). */
  tone?: string | null;
  /** Optional custom phrase to include. */
  customPhrase?: string | null;
}

/** Example hero prompt: "modern living room furniture interior" */
const SECTION_PROMPT_TEMPLATES: Record<string, string> = {
  hero: '{{tone}} {{industry}} interior scene, high quality, professional photography',
  cta: '{{tone}} {{industry}} background texture or scene, subtle, suitable for overlay text',
  background: '{{tone}} {{industry}} ambient background, abstract or scene',
  product: '{{tone}} {{industry}} product shot, clean background',
  general: '{{tone}} {{industry}} professional image',
};

function interpolate(template: string, context: ImagePromptContext): string {
  const tone = (context.tone ?? 'modern').replace(/\s+/g, ' ');
  const industry = (context.industry ?? 'general').replace(/\s+/g, ' ');
  let out = template.replace(/\{\{tone\}\}/gi, tone).replace(/\{\{industry\}\}/gi, industry);
  if (context.customPhrase?.trim()) {
    out = `${context.customPhrase.trim()}, ${out}`;
  }
  return out.replace(/\s+/g, ' ').trim();
}

/**
 * Builds an image generation prompt from context.
 * Example: { sectionType: 'hero', industry: 'furniture', tone: 'modern' } → "modern furniture interior scene, high quality, professional photography"
 */
export function buildImagePrompt(context: ImagePromptContext): string {
  const key = context.sectionType in SECTION_PROMPT_TEMPLATES ? context.sectionType : 'general';
  const template = SECTION_PROMPT_TEMPLATES[key] ?? SECTION_PROMPT_TEMPLATES.general;
  return interpolate(template, context);
}

/** Predefined hero-style prompt for quick use (e.g. "modern living room furniture interior"). */
export const HERO_IMAGE_PROMPT_EXAMPLE = 'modern living room furniture interior';

// ---------------------------------------------------------------------------
// Generate image (call provider)
// ---------------------------------------------------------------------------

/**
 * Generates an image via the provided provider and returns the image URL.
 * Use with DALL·E, Stability, Replicate, or any backend that returns a URL.
 */
export async function generateImage(
  prompt: string,
  provider: ImageGeneratorProvider,
  options?: GenerateImageOptions
): Promise<string> {
  return provider(prompt, options);
}

/**
 * Convenience: build prompt from context and generate. Returns URL or throws.
 */
export async function generateImageFromContext(
  context: ImagePromptContext,
  provider: ImageGeneratorProvider,
  options?: GenerateImageOptions
): Promise<string> {
  const prompt = buildImagePrompt(context);
  return generateImage(prompt, provider, options);
}

// ---------------------------------------------------------------------------
// Insert image URL into component props
// ---------------------------------------------------------------------------

/**
 * Inserts an image URL into component props. Merges into the given prop key (e.g. "image", "backgroundImage").
 * Returns a new props object so existing props are preserved.
 */
export function injectImageIntoProps(
  props: Record<string, unknown>,
  imageUrl: string,
  propKey: string = 'image'
): Record<string, unknown> {
  return { ...props, [propKey]: imageUrl };
}

/**
 * Injects image URL into the primary image prop for a component (e.g. hero → "image").
 * Use getPrimaryImagePropForComponent(componentKey) from smartImageSystem to get the key when needed.
 */
export function injectImageIntoPropsByKey(
  props: Record<string, unknown>,
  imageUrl: string,
  primaryPropKey: string
): Record<string, unknown> {
  return injectImageIntoProps(props, imageUrl, primaryPropKey);
}
