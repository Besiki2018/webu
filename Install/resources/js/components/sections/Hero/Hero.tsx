/**
 * Hero — single builder section component with variants.
 * Renders layout based on props.variant via explicit switch.
 *
 * Builder-compatible: receives all data via props from builder state; renders purely from props;
 * no hidden internal state for content. Example: <Hero {...componentProps} />.
 */

import { Hero1 } from '@/components/design-system/webu-hero/variants/hero-1';
import { Hero2 } from '@/components/design-system/webu-hero/variants/hero-2';
import { Hero3 } from '@/components/design-system/webu-hero/variants/hero-3';
import { Hero4 } from '@/components/design-system/webu-hero/variants/hero-4';
import { Hero5 } from '@/components/design-system/webu-hero/variants/hero-5';
import { Hero6 } from '@/components/design-system/webu-hero/variants/hero-6';
import { Hero7 } from '@/components/design-system/webu-hero/variants/hero-7';
import type { WebuHeroProps } from '@/components/design-system/webu-hero/types';
import { HERO_DEFAULT_VARIANT, type HeroVariantId } from './Hero.variants';

/**
 * Props for the Hero section.
 * Main editable: title, subtitle, description, buttonText, buttonLink, image, backgroundColor.
 * All content and style are editable via builder/Chat.
 */
export interface HeroProps extends WebuHeroProps {
  title?: string;
  subtitle?: string;
  description?: string;
  buttonText?: string;
  buttonLink?: string;
  image?: string;
  backgroundColor?: string;
}

export interface HeroSectionProps extends HeroProps {
  variant?: HeroVariantId;
}

/**
 * Renders the Hero section. Layout is chosen by props.variant (e.g. default | center | split styles).
 * All editable content comes from props (title, subtitle, description, buttonText, buttonLink, image, etc.).
 */
export function Hero(props: HeroSectionProps) {
  const variant = (props.variant ?? HERO_DEFAULT_VARIANT) as HeroVariantId;

  switch (variant) {
    case 'hero-2':
      return <Hero2 {...props} />;
    case 'hero-3':
      return <Hero3 {...props} />;
    case 'hero-4':
      return <Hero4 {...props} />;
    case 'hero-5':
      return <Hero5 {...props} />;
    case 'hero-6':
      return <Hero6 {...props} />;
    case 'hero-7':
      return <Hero7 {...props} />;
    case 'hero-1':
    default:
      return <Hero1 {...props} />;
  }
}

export default Hero;
