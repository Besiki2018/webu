/**
 * Part 12 — Project Type Integration.
 *
 * Generated site must respect projectType. Each type has canonical sections and capabilities
 * (e.g. ecommerce: product grid, cart, filters; restaurant: menu, reservation, gallery; saas: features, pricing).
 */

import type { ProjectType } from '../projectTypes';
import type { SectionSlug } from './promptAnalyzer';

// ---------------------------------------------------------------------------
// Canonical sections per project type (single source of truth)
// ---------------------------------------------------------------------------

/** Sections to include by default when generating a site for this project type. */
export const PROJECT_TYPE_SECTIONS: Record<ProjectType, SectionSlug[]> = {
  ecommerce: ['header', 'hero', 'productGrid', 'features', 'cta', 'footer'],
  restaurant: ['header', 'hero', 'menu', 'gallery', 'booking', 'cta', 'footer'],
  saas: ['header', 'hero', 'features', 'pricing', 'testimonials', 'cta', 'footer'],
  landing: ['header', 'hero', 'features', 'cta', 'footer'],
  business: ['header', 'hero', 'features', 'cta', 'footer'],
  portfolio: ['header', 'hero', 'gallery', 'cta', 'footer'],
  hotel: ['header', 'hero', 'features', 'booking', 'cta', 'footer'],
  blog: ['header', 'hero', 'blog', 'cta', 'footer'],
  education: ['header', 'hero', 'features', 'cta', 'footer'],
};

/** Human-readable capabilities per project type (for UI/docs). Cart/filters are typically header or global. */
export const PROJECT_TYPE_CAPABILITIES: Record<ProjectType, string[]> = {
  ecommerce: ['product grid', 'cart', 'filters'],
  restaurant: ['menu', 'reservation', 'gallery'],
  saas: ['features', 'pricing', 'integrations'],
  landing: ['hero', 'features', 'cta'],
  business: ['hero', 'features', 'cta'],
  portfolio: ['gallery', 'work', 'cta'],
  hotel: ['features', 'booking', 'cta'],
  blog: ['blog', 'articles', 'cta'],
  education: ['features', 'cta'],
};

// ---------------------------------------------------------------------------
// API
// ---------------------------------------------------------------------------

/**
 * Returns the canonical section slugs for a project type. Used by prompt analyzer and site planner
 * so the generated site respects projectType (e.g. ecommerce gets productGrid, restaurant gets menu + gallery + booking).
 */
export function getSectionsForProjectType(projectType: ProjectType): SectionSlug[] {
  return [...(PROJECT_TYPE_SECTIONS[projectType] ?? PROJECT_TYPE_SECTIONS.landing)];
}

/** Returns capability labels for a project type (for display or hints). */
export function getCapabilitiesForProjectType(projectType: ProjectType): string[] {
  return PROJECT_TYPE_CAPABILITIES[projectType] ?? [];
}
