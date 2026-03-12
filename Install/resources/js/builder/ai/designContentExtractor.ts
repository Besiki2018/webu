/**
 * Design Content Extractor — extract titles, subtitles, buttons, images, text blocks from a design image.
 *
 * Part 5 (Design-to-Builder): Uses vision/OCR to read content from a screenshot or Figma export.
 * If text cannot be read clearly, AI generates similar content as fallback.
 */

import type { ContentGeneratorProvider } from './contentGenerator';
import { generateHeroContent } from './contentGenerator';
import type { ProjectType } from '../projectTypes';

// ---------------------------------------------------------------------------
// Output: extracted content for builder props
// ---------------------------------------------------------------------------

export interface ExtractedDesignContent {
  /** Main headline / title. */
  title?: string;
  /** Subheadline / supporting text. */
  subtitle?: string;
  /** Primary CTA / button label. */
  ctaText?: string;
  /** Secondary button label (if any). */
  ctaSecondary?: string;
  /** Small label above title (eyebrow). */
  eyebrow?: string;
  /** Image URLs or data URLs (hero image, logos, etc.). */
  images?: string[];
  /** Other text blocks (paragraphs, list items). */
  textBlocks?: string[];
}

// ---------------------------------------------------------------------------
// Extraction provider (vision / OCR — injected by caller)
// ---------------------------------------------------------------------------

export interface RawExtractionResult {
  /** Detected titles (e.g. main headline). */
  titles?: string[];
  /** Detected subtitles or taglines. */
  subtitles?: string[];
  /** Button / CTA labels. */
  buttons?: string[];
  /** Other text blocks in order. */
  textBlocks?: string[];
  /** Image URLs or data URLs found in design. */
  imageUrls?: string[];
  /** True if text was unclear or low confidence; caller may use AI fallback. */
  unclear?: boolean;
}

/**
 * Provider that extracts content from a design image (e.g. vision API, OCR).
 * Return unclear: true when text cannot be read clearly.
 */
export type DesignExtractionProvider = (
  imageSource: string,
  options?: { projectType?: ProjectType; language?: string }
) => Promise<RawExtractionResult>;

// ---------------------------------------------------------------------------
// Options and fallback
// ---------------------------------------------------------------------------

export interface ExtractContentFromDesignOptions {
  /** Vision/OCR provider. If omitted, only fallback or placeholders are used. */
  extractionProvider?: DesignExtractionProvider | null;
  /** AI content provider for fallback when extraction is empty or unclear. */
  contentGeneratorProvider?: ContentGeneratorProvider | null;
  projectType?: ProjectType;
  industry?: string | null;
  tone?: string | null;
  brandName?: string | null;
  language?: string;
}

const PLACEHOLDER_CONTENT: ExtractedDesignContent = {
  title: 'Your headline',
  subtitle: 'Supporting text for your message.',
  ctaText: 'Get started',
};

/** Normalize raw extraction (vision/OCR) to ExtractedDesignContent. Exported for tests. */
export function rawToExtracted(raw: RawExtractionResult): ExtractedDesignContent {
  const title = raw.titles?.[0]?.trim() || raw.titles?.[1]?.trim();
  const subtitle = raw.subtitles?.[0]?.trim() || raw.textBlocks?.[0]?.trim();
  const ctaText = raw.buttons?.[0]?.trim();
  const ctaSecondary = raw.buttons?.[1]?.trim();
  const eyebrow = raw.titles?.length ? undefined : undefined;
  const textBlocks = raw.textBlocks?.filter((t) => t?.trim()).map((t) => t!.trim()) ?? [];
  const images = raw.imageUrls?.filter(Boolean) ?? [];

  return {
    ...(title ? { title } : {}),
    ...(subtitle ? { subtitle } : {}),
    ...(ctaText ? { ctaText } : {}),
    ...(ctaSecondary ? { ctaSecondary } : {}),
    ...(eyebrow ? { eyebrow } : {}),
    ...(images.length > 0 ? { images } : {}),
    ...(textBlocks.length > 0 ? { textBlocks } : {}),
  };
}

function isEmpty(content: ExtractedDesignContent): boolean {
  return (
    !content.title &&
    !content.subtitle &&
    !content.ctaText &&
    !(content.images?.length) &&
    !(content.textBlocks?.length)
  );
}

async function fallbackGenerateContent(
  options: ExtractContentFromDesignOptions
): Promise<ExtractedDesignContent> {
  const provider = options.contentGeneratorProvider;
  if (!provider) return PLACEHOLDER_CONTENT;

  try {
    const result = await generateHeroContent(
      {
        projectType: options.projectType ?? 'landing',
        industry: options.industry ?? null,
        tone: options.tone ?? null,
        brandName: options.brandName ?? null,
        language: options.language ?? 'en',
      },
      provider
    );
    return {
      title: result.title,
      subtitle: result.subtitle,
      ctaText: result.cta,
      ...(result.eyebrow && { eyebrow: result.eyebrow }),
      ...(result.ctaSecondary && { ctaSecondary: result.ctaSecondary }),
    };
  } catch {
    return PLACEHOLDER_CONTENT;
  }
}

// ---------------------------------------------------------------------------
// File/URL to data URL
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

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Extracts titles, subtitles, buttons, images, and text blocks from a design image.
 * - If extractionProvider is set: calls it and normalizes the result.
 * - If extraction is empty or unclear (unclear: true): uses contentGeneratorProvider to generate
 *   similar content (hero-style title, subtitle, CTA). If no content provider, returns placeholders.
 *
 * @param imageSource — Design image as File, data URL, or fetchable URL.
 * @param options — extractionProvider, contentGeneratorProvider, projectType, language, etc.
 * @returns ExtractedDesignContent (title, subtitle, ctaText, images, textBlocks).
 */
export async function extractContentFromDesign(
  imageSource: File | string,
  options: ExtractContentFromDesignOptions = {}
): Promise<ExtractedDesignContent> {
  const {
    extractionProvider = null,
    contentGeneratorProvider = null,
    projectType = 'landing',
    language = 'en',
  } = options;

  let imageUrl: string;
  if (typeof imageSource === 'string') {
    imageUrl = imageSource;
  } else {
    imageUrl = await fileToDataUrl(imageSource);
  }

  let extracted: ExtractedDesignContent = {};

  if (extractionProvider) {
    try {
      const raw = await extractionProvider(imageUrl, { projectType, language });
      extracted = rawToExtracted(raw);
      if (raw.unclear || isEmpty(extracted)) {
        const fallback = await fallbackGenerateContent(options);
        extracted = {
          title: extracted.title || fallback.title,
          subtitle: extracted.subtitle || fallback.subtitle,
          ctaText: extracted.ctaText || fallback.ctaText,
          ctaSecondary: extracted.ctaSecondary ?? fallback.ctaSecondary,
          eyebrow: extracted.eyebrow ?? fallback.eyebrow,
          images: extracted.images?.length ? extracted.images : fallback.images,
          textBlocks: extracted.textBlocks?.length ? extracted.textBlocks : fallback.textBlocks,
        };
      }
    } catch {
      extracted = await fallbackGenerateContent(options);
    }
  } else {
    extracted = await fallbackGenerateContent(options);
  }

  if (isEmpty(extracted)) {
    return PLACEHOLDER_CONTENT;
  }

  return {
    title: extracted.title ?? PLACEHOLDER_CONTENT.title,
    subtitle: extracted.subtitle ?? PLACEHOLDER_CONTENT.subtitle,
    ctaText: extracted.ctaText ?? PLACEHOLDER_CONTENT.ctaText,
    ...(extracted.ctaSecondary && { ctaSecondary: extracted.ctaSecondary }),
    ...(extracted.eyebrow && { eyebrow: extracted.eyebrow }),
    ...(extracted.images?.length && { images: extracted.images }),
    ...(extracted.textBlocks?.length && { textBlocks: extracted.textBlocks }),
  };
}
