/**
 * Props Generator — map generated content to component props.
 *
 * Part 6: Converts content from the content generator (HeroContentResult, FeaturesContentResult, etc.)
 * into builder component props (title, subtitle, buttonText, image, items with icons, etc.).
 */

import type {
  HeroContentResult,
  FeaturesContentResult,
  CtaContentResult,
  ProductHighlightsResult,
  FeatureItem,
  ProductHighlightItem,
} from './contentGenerator';

// ---------------------------------------------------------------------------
// Builder prop types (flat records for section draft / registry)
// ---------------------------------------------------------------------------

export interface HeroBuilderProps {
  title?: string;
  subtitle?: string;
  description?: string;
  eyebrow?: string;
  buttonText?: string;
  buttonLink?: string;
  secondaryButtonText?: string;
  secondaryButtonLink?: string;
  image?: string;
  imageAlt?: string;
  backgroundImage?: string;
  variant?: string;
  [key: string]: unknown;
}

export interface FeatureItemBuilderProps {
  icon?: string;
  title: string;
  description?: string;
}

export interface FeaturesBuilderProps {
  title?: string;
  items: FeatureItemBuilderProps[];
  variant?: string;
  [key: string]: unknown;
}

export interface CtaBuilderProps {
  title?: string;
  subtitle?: string;
  buttonLabel?: string;
  buttonUrl?: string;
  variant?: string;
  [key: string]: unknown;
}

export interface GridOrCardsItemBuilderProps {
  title?: string;
  name?: string;
  description?: string;
  image?: string;
  link?: string;
}

export interface GridBuilderProps {
  title?: string;
  items: GridOrCardsItemBuilderProps[];
  variant?: string;
  [key: string]: unknown;
}

// ---------------------------------------------------------------------------
// Default icons for feature items (lucide-style or generic; use as icon key or placeholder)
// ---------------------------------------------------------------------------

const DEFAULT_FEATURE_ICONS = [
  'Package',
  'Truck',
  'Award',
  'Shield',
  'Sparkles',
  'Zap',
  'Heart',
  'Star',
];

// ---------------------------------------------------------------------------
// Mappers: content result → builder props
// ---------------------------------------------------------------------------

export interface HeroPropsOptions {
  /** Optional image URL (e.g. from AI image or placeholder). */
  image?: string | null;
  /** Optional primary button link. Default '#' */
  buttonLink?: string;
  /** Optional secondary button link. */
  secondaryButtonLink?: string;
}

/**
 * Maps hero content to hero section builder props.
 */
export function contentToHeroProps(
  content: HeroContentResult,
  options: HeroPropsOptions = {}
): HeroBuilderProps {
  const { image = null, buttonLink = '#', secondaryButtonLink = '#' } = options;
  return {
    title: content.title,
    subtitle: content.subtitle,
    eyebrow: content.eyebrow ?? undefined,
    buttonText: content.cta,
    buttonLink,
    secondaryButtonText: content.ctaSecondary ?? undefined,
    secondaryButtonLink: content.ctaSecondary ? secondaryButtonLink : undefined,
    image: image ?? undefined,
    imageAlt: content.title ? `${content.title} — hero` : undefined,
  };
}

export interface FeaturesPropsOptions {
  /** Optional icon names per position (same length as items or empty). */
  icons?: string[];
}

/**
 * Maps features content to features section builder props.
 * Assigns default icons (Package, Truck, Award, …) when not provided.
 */
export function contentToFeaturesProps(
  content: FeaturesContentResult,
  options: FeaturesPropsOptions = {}
): FeaturesBuilderProps {
  const { icons = [] } = options;
  const items: FeatureItemBuilderProps[] = content.items.map((item: FeatureItem, index: number) => ({
    icon: icons[index] ?? DEFAULT_FEATURE_ICONS[index % DEFAULT_FEATURE_ICONS.length],
    title: item.title,
    description: item.description ?? '',
  }));
  return {
    title: content.title,
    items,
  };
}

/**
 * Maps CTA content to CTA section builder props.
 */
export function contentToCtaProps(
  content: CtaContentResult,
  options: { buttonUrl?: string } = {}
): CtaBuilderProps {
  return {
    title: content.title,
    subtitle: content.subtitle ?? undefined,
    buttonLabel: content.buttonLabel,
    buttonUrl: options.buttonUrl ?? '#',
  };
}

/**
 * Maps product highlights content to grid/cards-style builder props.
 * Grid and cards use items with title, description; cards also support image and link.
 */
export function contentToGridProps(
  content: ProductHighlightsResult,
  options: { titleDefault?: string } = {}
): GridBuilderProps {
  const items: GridOrCardsItemBuilderProps[] = content.items.map((item: ProductHighlightItem) => ({
    title: item.name,
    name: item.name,
    description: item.description ?? '',
  }));
  return {
    title: content.title ?? options.titleDefault ?? 'Highlights',
    items,
  };
}

/**
 * Same as contentToGridProps but for cards section (same item shape; cards may use image/link in UI).
 */
export function contentToCardsProps(
  content: ProductHighlightsResult,
  options: { titleDefault?: string } = {}
): GridBuilderProps {
  return contentToGridProps(content, { titleDefault: options.titleDefault ?? 'Featured' });
}
