/**
 * Phase 13 — Smart Image System.
 *
 * Image fields support: upload, Unsplash, AI generation, media library.
 * Example: "Generate hero image" → AI or service returns URL → set on component image prop.
 */

import { getComponentSchema } from './componentRegistry';

// ---------------------------------------------------------------------------
// Image field sources (how the image was obtained)
// ---------------------------------------------------------------------------

export type ImageFieldSource = 'upload' | 'unsplash' | 'ai_generation' | 'media_library';

export const IMAGE_FIELD_SOURCES: ImageFieldSource[] = [
  'upload',
  'unsplash',
  'ai_generation',
  'media_library',
];

export const IMAGE_FIELD_SOURCE_LABELS: Record<ImageFieldSource, string> = {
  upload: 'Upload',
  unsplash: 'Unsplash',
  ai_generation: 'AI generation',
  media_library: 'Media library',
};

// ---------------------------------------------------------------------------
// Schema-aware image prop discovery
// ---------------------------------------------------------------------------

/** Props that typically hold image URLs (for fallback when schema doesn't declare type image). */
const IMAGE_LIKE_PROP_NAMES = new Set([
  'image',
  'backgroundImage',
  'logo_url',
  'logoUrl',
  'src',
  'imageUrl',
]);

/**
 * Returns the list of prop paths on a component that accept image values (URL or asset id).
 * Used by AI/UI to know where to set "Generate hero image" (e.g. hero → ['image', 'backgroundImage']).
 */
export function getImagePropPathsForComponent(componentKey: string): string[] {
  const schema = getComponentSchema(componentKey);
  if (!schema) {
    return [];
  }

  return Array.from(new Set(
    schema.fields
      .map((field) => field.path)
      .filter((path) => {
        const lastSegment = path.split('.').pop() ?? path;
        const field = schema.fields.find((candidate) => candidate.path === path);
        const type = (field?.type ?? '').toLowerCase();
        return type === 'image' || type === 'icon' || IMAGE_LIKE_PROP_NAMES.has(lastSegment);
      })
  ));
}

/**
 * Returns the primary image prop for a component (first image-type prop, or 'image').
 * Example: "Generate hero image" → set this prop to the generated URL.
 */
export function getPrimaryImagePropForComponent(componentKey: string): string | null {
  const paths = getImagePropPathsForComponent(componentKey);
  if (paths.length === 0) return null;
  return paths.includes('image') ? 'image' : paths[0] ?? null;
}

/** Payload for "set image" from any source: component + prop + URL (or asset id). */
export interface SetImagePayload {
  componentId: string;
  /** Prop path (e.g. 'image', 'backgroundImage'). Use getPrimaryImagePropForComponent if not specified. */
  path?: string;
  /** Image URL or media library asset id. */
  value: string;
  /** Optional: source for analytics or UI. */
  source?: ImageFieldSource;
}
