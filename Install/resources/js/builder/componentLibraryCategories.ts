/**
 * Component Library System — canonical categories for the builder component library.
 * Each component schema should set category to one of these (or a logical alias)
 * so the library UI can group components.
 *
 * Schema must include: category, projectTypes, capabilities.
 */

/** Canonical library category ids (schema.category). Use these for consistent library grouping. */
export const COMPONENT_LIBRARY_CATEGORIES = [
  'layout',
  'hero',
  'features',
  'pricing',
  'testimonials',
  'forms',
  'footers',
  'navigation',
  'ecommerce',
  'blog',
  'restaurant',
] as const;

export type ComponentLibraryCategory = (typeof COMPONENT_LIBRARY_CATEGORIES)[number];

/** Display labels for library UI (category id → label). */
export const COMPONENT_LIBRARY_CATEGORY_LABELS: Record<ComponentLibraryCategory, string> = {
  layout: 'Layout',
  hero: 'Hero',
  features: 'Features',
  pricing: 'Pricing',
  testimonials: 'Testimonials',
  forms: 'Forms',
  footers: 'Footers',
  navigation: 'Navigation',
  ecommerce: 'Ecommerce',
  blog: 'Blog',
  restaurant: 'Restaurant',
};

/** Category id → list of registry ids (populated from registry schemas). Use getRegistryIdsByCategory() for runtime. */
export function getCategoryLabel(category: string): string {
  const c = category?.toLowerCase();
  return COMPONENT_LIBRARY_CATEGORY_LABELS[c as ComponentLibraryCategory] ?? category;
}

export function isComponentLibraryCategory(value: string): value is ComponentLibraryCategory {
  return (COMPONENT_LIBRARY_CATEGORIES as readonly string[]).includes(value?.toLowerCase());
}
