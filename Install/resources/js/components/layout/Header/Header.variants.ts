/**
 * Header layout variants — single source of truth for builder and codegen.
 */

export const HEADER_VARIANTS = [
  'header-1',
  'header-2',
  'header-3',
  'header-4',
  'header-5',
  'header-6',
] as const;

export type HeaderVariantId = (typeof HEADER_VARIANTS)[number];

export const HEADER_DEFAULT_VARIANT: HeaderVariantId = 'header-1';

export const HEADER_VARIANT_OPTIONS = HEADER_VARIANTS.map((value) => ({
  label: value
    .split('-')
    .map((s) => s.charAt(0).toUpperCase() + s.slice(1))
    .join(' '),
  value,
}));
