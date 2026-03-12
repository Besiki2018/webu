/**
 * Design Image Processor — extract and replace images from design.
 *
 * Part 7 (Design-to-Builder): Detect image blocks, crop hero images, extract background images.
 * Replace with: original (from design), AI-generated replacements, or stock images.
 */

// ---------------------------------------------------------------------------
// Detected image blocks (from vision/analysis)
// ---------------------------------------------------------------------------

export type ImageBlockRole = 'hero' | 'background' | 'general';

/** Bounds relative to design (0–1 or pixel coords). */
export interface ImageBounds {
  x: number;
  y: number;
  width: number;
  height: number;
  /** If true, x/y/width/height are normalized 0–1. */
  normalized?: boolean;
}

/** A detected image region in the design. */
export interface DetectedImageBlock {
  /** Role: hero, background, or general. */
  role: ImageBlockRole;
  /** Image data: URL (data URL or fetchable) from design, or extracted crop. */
  url?: string;
  /** Bounds in the design for cropping or reference. */
  bounds?: ImageBounds;
  /** Optional label (e.g. "Hero banner", "Background"). */
  label?: string;
}

/** Spec for hero crop (e.g. focus area or aspect). */
export interface HeroCropSpec {
  bounds: ImageBounds;
  /** Target aspect (e.g. "16/9", "1"). */
  aspectRatio?: string;
}

/** Spec for extracted background image. */
export interface BackgroundImageSpec {
  bounds: ImageBounds;
  url?: string;
}

// ---------------------------------------------------------------------------
// Processed output: image with chosen source
// ---------------------------------------------------------------------------

export type ImageReplacementSource = 'original' | 'aiGenerated' | 'stock';

export interface ProcessedImage {
  /** Final URL to use in builder (original, AI-generated, or stock). */
  url: string;
  /** Which source was used. */
  source: ImageReplacementSource;
  /** Role for placing in sections (hero, background, etc.). */
  role: ImageBlockRole;
  /** Optional index when multiple images share the same role. */
  index?: number;
}

// ---------------------------------------------------------------------------
// Providers (injected by app)
// ---------------------------------------------------------------------------

/**
 * Detects image blocks in a design image (vision API or heuristic).
 * Returns regions with role and optional url/bounds.
 */
export type ImageBlockDetector = (
  designImageSource: string,
  options?: { projectType?: string }
) => Promise<DetectedImageBlock[]>;

/**
 * Crops a hero image from the design (or from a URL) to the given spec.
 * Can be a no-op that returns the original URL if no server-side crop.
 */
export type HeroImageCropper = (
  imageSource: string,
  spec: HeroCropSpec
) => Promise<string>;

/**
 * Extracts a background image region from the design.
 */
export type BackgroundImageExtractor = (
  designImageSource: string,
  spec: BackgroundImageSpec
) => Promise<string>;

/**
 * Returns a stock image URL for a query (e.g. Unsplash, Pexels API).
 */
export type StockImageProvider = (
  query: string,
  options?: { role?: ImageBlockRole }
) => Promise<string>;

// ---------------------------------------------------------------------------
// Options for processing
// ---------------------------------------------------------------------------

export interface ProcessDesignImagesOptions {
  /** Pre-detected blocks; if omitted and detector provided, detector is called. */
  detectedBlocks?: DetectedImageBlock[];
  /** Prefer original design images when available. */
  useOriginals?: boolean;
  /** Prefer AI-generated replacements (hero, background). */
  useAiGenerated?: boolean;
  /** Prefer stock image replacements. */
  useStock?: boolean;
  /** Detection provider (if detectedBlocks not supplied). */
  detector?: ImageBlockDetector | null;
  /** AI image provider (e.g. DALL·E) for replacements. */
  aiImageProvider?: ((prompt: string) => Promise<string>) | null;
  /** Stock image provider (e.g. Unsplash) for replacements. */
  stockImageProvider?: StockImageProvider | null;
  /** Hero cropper (optional). */
  heroCropper?: HeroImageCropper | null;
  /** Background extractor (optional). */
  backgroundExtractor?: BackgroundImageExtractor | null;
  /** Context for AI/stock prompts. */
  industry?: string | null;
  tone?: string | null;
  projectType?: string;
}

// ---------------------------------------------------------------------------
// Default / fallbacks
// ---------------------------------------------------------------------------

const ROLE_ORDER: ImageBlockRole[] = ['hero', 'background', 'general'];

function chooseSource(
  block: DetectedImageBlock,
  options: ProcessDesignImagesOptions
): ImageReplacementSource {
  if (options.useOriginals !== false && block.url) return 'original';
  if (options.useAiGenerated && options.aiImageProvider) return 'aiGenerated';
  if (options.useStock && options.stockImageProvider) return 'stock';
  if (block.url) return 'original';
  if (options.aiImageProvider) return 'aiGenerated';
  if (options.stockImageProvider) return 'stock';
  return 'original';
}

async function resolveUrl(
  block: DetectedImageBlock,
  source: ImageReplacementSource,
  designSource: string,
  options: ProcessDesignImagesOptions,
  roleIndex: number
): Promise<string> {
  if (source === 'original' && block.url) return block.url;

  const industry = (options.industry ?? 'general').replace(/\s+/g, ' ');
  const tone = (options.tone ?? 'modern').replace(/\s+/g, ' ');
  const query = `${tone} ${industry} ${block.role}`.trim();

  if (source === 'aiGenerated' && options.aiImageProvider) {
    const prompt =
      block.role === 'hero'
        ? `${tone} ${industry} hero banner image, high quality`
        : block.role === 'background'
          ? `${tone} ${industry} background texture or scene`
          : `${tone} ${industry} professional image`;
    return options.aiImageProvider(prompt);
  }

  if (source === 'stock' && options.stockImageProvider) {
    return options.stockImageProvider(query, { role: block.role });
  }

  return block.url ?? '';
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Processes images from a design: detect blocks (if needed), then replace with
 * original URLs, AI-generated images, or stock images according to options.
 *
 * @param designImageSource — Design image as data URL or fetchable URL.
 * @param options — detectedBlocks, useOriginals/useAiGenerated/useStock, detector, aiImageProvider, stockImageProvider, heroCropper, backgroundExtractor, context.
 * @returns ProcessedImage[] with url, source, role (and index per role).
 */
export async function processDesignImages(
  designImageSource: string,
  options: ProcessDesignImagesOptions = {}
): Promise<ProcessedImage[]> {
  const {
    detectedBlocks: providedBlocks,
    detector = null,
    heroCropper = null,
    backgroundExtractor = null,
  } = options;

  let blocks: DetectedImageBlock[] = providedBlocks ?? [];

  if (blocks.length === 0 && detector) {
    try {
      blocks = await detector(designImageSource, { projectType: options.projectType });
    } catch {
      blocks = [];
    }
  }

  const result: ProcessedImage[] = [];
  const roleCounts: Record<ImageBlockRole, number> = {
    hero: 0,
    background: 0,
    general: 0,
  };

  for (const block of blocks) {
    const source = chooseSource(block, options);
    let url = await resolveUrl(block, source, designImageSource, options, roleCounts[block.role] ?? 0);

    if (url && block.role === 'hero' && heroCropper && block.bounds) {
      try {
        url = await heroCropper(url, { bounds: block.bounds, aspectRatio: '16/9' });
      } catch {
        // keep url
      }
    }

    if (url && block.role === 'background' && backgroundExtractor && block.bounds) {
      try {
        url = await backgroundExtractor(designImageSource, {
          bounds: block.bounds,
          url: block.url,
        });
      } catch {
        // keep url
      }
    }

    if (url) {
      roleCounts[block.role]++;
      result.push({
        url,
        source,
        role: block.role,
        index: roleCounts[block.role] - 1,
      });
    }
  }

  return result;
}

/**
 * Returns processed images grouped by role (hero, background, general).
 * Convenience for applying to section props (e.g. first hero URL → hero section image).
 */
export function groupProcessedImagesByRole(
  processed: ProcessedImage[]
): Record<ImageBlockRole, ProcessedImage[]> {
  const groups: Record<ImageBlockRole, ProcessedImage[]> = {
    hero: [],
    background: [],
    general: [],
  };
  for (const img of processed) {
    groups[img.role].push(img);
  }
  return groups;
}
