/**
 * Hero section variants — single source of truth for builder and codegen.
 */

export const HERO_VARIANTS = [
  'hero-1',
  'hero-2',
  'hero-3',
  'hero-4',
  'hero-5',
  'hero-6',
  'hero-7',
] as const;

export type HeroVariantId = (typeof HERO_VARIANTS)[number];

export const HERO_DEFAULT_VARIANT: HeroVariantId = 'hero-1';

export const HERO_VARIANT_OPTIONS = HERO_VARIANTS.map((value) => ({
  label: value
    .split('-')
    .map((s) => s.charAt(0).toUpperCase() + s.slice(1))
    .join(' '),
  value,
}));
