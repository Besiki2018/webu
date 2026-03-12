export type HeroVariant = 'hero-1' | 'hero-2' | 'hero-3' | 'hero-4' | 'hero-5' | 'hero-6' | 'hero-7';

export interface HeroStatAvatar {
  url: string;
  alt?: string;
}

export interface WebuHeroProps {
  variant?: HeroVariant;
  /** Main title (alias: headline) */
  headline?: string;
  title?: string;
  subheading?: string;
  subtitle?: string;
  eyebrow?: string;
  badgeText?: string;
  /** Primary CTA */
  ctaLabel?: string;
  ctaUrl?: string;
  /** Secondary CTA */
  ctaSecondaryLabel?: string;
  ctaSecondaryUrl?: string;
  imageUrl?: string;
  imageAlt?: string;
  /** Fallback for img alt when imageAlt and title/headline are empty (editable). */
  imageAltFallback?: string;
  overlayImageUrl?: string;
  overlayImageAlt?: string;
  statValue?: string;
  statUnit?: string;
  statLabel?: string;
  statAvatars?: HeroStatAvatar[];
  basePath?: string;
  className?: string;
  /** Inline styles */
  backgroundColor?: string;
  textColor?: string;
  backgroundImage?: string;
  alignment?: 'left' | 'center' | 'right';
  padding?: string;
  spacing?: string;
}
