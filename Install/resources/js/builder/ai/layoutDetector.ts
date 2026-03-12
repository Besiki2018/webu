/**
 * Layout Detection Engine — analyze screenshot/design image and detect layout blocks.
 *
 * Part 2 (Design-to-Builder): Detects section types (header, hero, features, testimonials, cta, footer)
 * and positions. Supports vision model (injected provider) or heuristic layout detection.
 * Output is builder-compatible and can be passed to the site planner / component registry.
 */

import type { SectionSlug } from './promptAnalyzer';
import type { ProjectType } from '../projectTypes';

// ---------------------------------------------------------------------------
// Output types
// ---------------------------------------------------------------------------

/** Vertical position of a detected block in the layout. */
export type LayoutPosition = 'top' | 'top-section' | 'middle' | 'bottom' | 'end';

/** One detected layout block: section type (maps to SectionSlug) and position. */
export interface DetectedLayoutBlock {
  type: SectionSlug;
  position: LayoutPosition;
}

export interface LayoutDetectionResult {
  blocks: DetectedLayoutBlock[];
}

// ---------------------------------------------------------------------------
// Vision provider (optional — app injects API that calls GPT-4V, Claude, etc.)
// ---------------------------------------------------------------------------

export interface LayoutDetectionVisionInput {
  /** Image as data URL (base64), blob URL, or fetchable image URL. */
  imageSource: string;
  projectType?: ProjectType;
  preferredStyle?: string;
}

/**
 * Vision provider: analyzes an image and returns detected layout blocks.
 * The app should pass a function that calls its vision API (e.g. OpenAI GPT-4V, Claude with image).
 */
export type LayoutVisionProvider = (
  input: LayoutDetectionVisionInput
) => Promise<DetectedLayoutBlock[]>;

// ---------------------------------------------------------------------------
// Heuristic detection (no vision API)
// ---------------------------------------------------------------------------

/** Default section sequence for heuristic when no vision result. Order matches typical landing layout. */
const DEFAULT_HEURISTIC_BLOCKS: DetectedLayoutBlock[] = [
  { type: 'header', position: 'top' },
  { type: 'hero', position: 'top-section' },
  { type: 'features', position: 'middle' },
  { type: 'testimonials', position: 'middle' },
  { type: 'cta', position: 'bottom' },
  { type: 'footer', position: 'end' },
];

/** Project-type variants: some types add or reorder sections (e.g. productGrid for ecommerce). */
const HEURISTIC_BY_PROJECT_TYPE: Partial<Record<ProjectType, DetectedLayoutBlock[]>> = {
  ecommerce: [
    { type: 'header', position: 'top' },
    { type: 'hero', position: 'top-section' },
    { type: 'productGrid', position: 'middle' },
    { type: 'features', position: 'middle' },
    { type: 'testimonials', position: 'middle' },
    { type: 'cta', position: 'bottom' },
    { type: 'footer', position: 'end' },
  ],
  saas: [
    { type: 'header', position: 'top' },
    { type: 'hero', position: 'top-section' },
    { type: 'features', position: 'middle' },
    { type: 'pricing', position: 'middle' },
    { type: 'testimonials', position: 'middle' },
    { type: 'cta', position: 'bottom' },
    { type: 'footer', position: 'end' },
  ],
  restaurant: [
    { type: 'header', position: 'top' },
    { type: 'hero', position: 'top-section' },
    { type: 'menu', position: 'middle' },
    { type: 'gallery', position: 'middle' },
    { type: 'cta', position: 'bottom' },
    { type: 'footer', position: 'end' },
  ],
};

/** Section slugs we treat as valid detection output (subset that maps to registry). */
const VALID_DETECTION_TYPES: SectionSlug[] = [
  'header',
  'hero',
  'productGrid',
  'features',
  'pricing',
  'testimonials',
  'cta',
  'footer',
  'navigation',
  'cards',
  'grid',
  'gallery',
  'menu',
  'contact',
  'booking',
  'faq',
  'blog',
];

function isValidSectionSlug(s: string): s is SectionSlug {
  return (VALID_DETECTION_TYPES as string[]).includes(s);
}

/** Map vision model output (e.g. "feature grid", "hero") to SectionSlug. */
function normalizeVisionSectionType(slug: string): string {
  const map: Record<string, SectionSlug> = {
    header: 'header',
    hero: 'hero',
    features: 'features',
    featuregrid: 'features',
    testimonials: 'testimonials',
    cta: 'cta',
    calltoaction: 'cta',
    footer: 'footer',
    productgrid: 'productGrid',
    pricing: 'pricing',
    navigation: 'navigation',
    cards: 'cards',
    grid: 'grid',
    gallery: 'gallery',
    menu: 'menu',
    contact: 'contact',
    booking: 'booking',
    faq: 'faq',
    blog: 'blog',
  };
  return map[slug] ?? slug;
}

/** Normalizes raw vision API output to DetectedLayoutBlock[] (exported for tests). */
export function normalizeVisionBlocks(raw: unknown): DetectedLayoutBlock[] {
  if (!Array.isArray(raw) || raw.length === 0) return [];
  const result: DetectedLayoutBlock[] = [];
  const positions: LayoutPosition[] = ['top', 'top-section', 'middle', 'bottom', 'end'];
  for (let i = 0; i < raw.length; i++) {
    const item = raw[i];
    if (!item || typeof item !== 'object') continue;
    const type = (item as { type?: string }).type;
    const position = (item as { position?: string }).position;
    if (typeof type !== 'string') continue;
    const slug = type.toLowerCase().replace(/\s+/g, '') as string;
    const normalizedType = normalizeVisionSectionType(slug);
    if (!isValidSectionSlug(normalizedType)) continue;
    const pos =
      typeof position === 'string' && positions.includes(position as LayoutPosition)
        ? (position as LayoutPosition)
        : i === 0
          ? 'top'
          : i === raw.length - 1
            ? 'end'
            : 'middle';
    result.push({ type: normalizedType as SectionSlug, position: pos });
  }
  return result;
}

/**
 * Returns heuristic layout blocks for the given project type.
 * Used when no vision provider is available or as fallback.
 */
export function getHeuristicLayout(projectType?: ProjectType): DetectedLayoutBlock[] {
  if (projectType && HEURISTIC_BY_PROJECT_TYPE[projectType]) {
    return [...HEURISTIC_BY_PROJECT_TYPE[projectType]!];
  }
  return [...DEFAULT_HEURISTIC_BLOCKS];
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

export interface DetectLayoutOptions {
  /** Optional vision API: when provided, image is sent for analysis first. */
  visionProvider?: LayoutVisionProvider | null;
  /** Project type: influences heuristic fallback and can be passed to vision. */
  projectType?: ProjectType;
  /** Preferred style hint (e.g. modern, minimal); can be passed to vision. */
  preferredStyle?: string;
}

/**
 * Analyzes a design image and returns detected layout blocks (section types + positions).
 * - If visionProvider is set: calls it with imageSource (data URL or URL) and normalizes the response.
 * - If vision fails or is not set: returns heuristic layout based on projectType.
 *
 * @param imageSource — Image as File (will be read as data URL), data URL string, or fetchable URL.
 * @param options — visionProvider, projectType, preferredStyle.
 * @returns LayoutDetectionResult with ordered blocks compatible with site planner.
 */
export async function detectLayoutFromImage(
  imageSource: File | string,
  options: DetectLayoutOptions = {}
): Promise<LayoutDetectionResult> {
  const { visionProvider = null, projectType, preferredStyle } = options;

  let imageUrl: string;
  if (typeof imageSource === 'string') {
    imageUrl = imageSource;
  } else {
    imageUrl = await fileToDataUrl(imageSource);
  }

  if (visionProvider) {
    try {
      const blocks = await visionProvider({
        imageSource: imageUrl,
        projectType,
        preferredStyle,
      });
      const normalized = normalizeVisionBlocks(blocks);
      if (normalized.length > 0) {
        return { blocks: normalized };
      }
    } catch {
      // Fall through to heuristic
    }
  }

  return {
    blocks: getHeuristicLayout(projectType),
  };
}

/**
 * Synchronous heuristic-only detection (no image, no vision).
 * Useful when you only have projectType and want a default layout.
 */
export function detectLayoutHeuristic(projectType?: ProjectType): LayoutDetectionResult {
  return {
    blocks: getHeuristicLayout(projectType),
  };
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function fileToDataUrl(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => {
      const result = reader.result;
      if (typeof result === 'string') resolve(result);
      else reject(new Error('FileReader did not return string'));
    };
    reader.onerror = () => reject(reader.error);
    reader.readAsDataURL(file);
  });
}
