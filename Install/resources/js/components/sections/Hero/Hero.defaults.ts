/**
 * Hero section component default props.
 * Each component must provide default props; used by builder, schema, and preview.
 */

import type { HeroVariantId } from './Hero.variants';

export interface HeroDefaultProps {
  eyebrow: string;
  badgeText: string;
  title: string;
  subtitle: string;
  description: string;
  alignment: string;
  buttonText: string;
  buttonLink: string;
  secondaryButtonText: string;
  secondaryButtonLink: string;
  image: string;
  imageAlt: string;
  imageAltFallback: string;
  overlayImageUrl: string;
  overlayImageAlt: string;
  backgroundImage: string;
  statValue: string;
  statUnit: string;
  statLabel: string;
  statAvatars: Array<{ url: string; alt?: string }>;
  variant?: HeroVariantId;
}

/** Default values applied when props are missing. Used by schema and mapBuilderProps. */
export const HERO_DEFAULTS: HeroDefaultProps = {
  eyebrow: '',
  badgeText: '',
  title: 'Build faster websites',
  subtitle: 'Create modern sites with Webu',
  description: '',
  alignment: 'left',
  buttonText: 'Get Started',
  buttonLink: '#',
  secondaryButtonText: '',
  secondaryButtonLink: '#',
  image: '',
  imageAlt: '',
  imageAltFallback: 'Hero',
  overlayImageUrl: '',
  overlayImageAlt: 'Overlay',
  backgroundImage: '',
  statValue: '',
  statUnit: '',
  statLabel: '',
  statAvatars: [],
  variant: 'hero-1',
};

/** Default props for Hero section (alias for HERO_DEFAULTS). */
export const HeroDefaults = HERO_DEFAULTS;
