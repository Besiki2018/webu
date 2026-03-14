import type { BlueprintProjectType, LayoutDomain } from '../blueprintTypes'

export interface SelectedLayoutTemplate {
  key: string
  sections: string[]
}

export const DOMAIN_LAYOUT_TEMPLATES: Record<Exclude<LayoutDomain, 'unknown'>, readonly string[]> = {
  vet_clinic: [
    'hero',
    'services',
    'doctors',
    'appointment_booking',
    'testimonials',
    'faq',
    'contact',
    'footer',
  ],
  restaurant: [
    'hero',
    'menu',
    'chef',
    'gallery',
    'reservation',
    'reviews',
    'location',
    'footer',
  ],
  saas: [
    'hero',
    'problem',
    'solution',
    'features',
    'product_demo',
    'pricing',
    'testimonials',
    'faq',
    'cta',
    'footer',
  ],
  agency: [
    'hero',
    'services',
    'case_studies',
    'process',
    'testimonials',
    'contact',
    'footer',
  ],
  portfolio: [
    'hero',
    'portfolio_gallery',
    'about',
    'skills',
    'contact',
    'footer',
  ],
  ecommerce: [
    'hero',
    'productGrid',
    'categories',
    'featured_products',
    'testimonials',
    'faq',
    'footer',
  ],
}

const PROJECT_TYPE_LAYOUT_TEMPLATES: Record<BlueprintProjectType, readonly string[]> = {
  landing: [
    'hero',
    'problem',
    'solution',
    'features',
    'testimonials',
    'faq',
    'cta',
    'footer',
  ],
  saas: DOMAIN_LAYOUT_TEMPLATES.saas,
  ecommerce: DOMAIN_LAYOUT_TEMPLATES.ecommerce,
  business: [
    'hero',
    'services',
    'process',
    'testimonials',
    'faq',
    'contact',
    'cta',
    'footer',
  ],
  portfolio: DOMAIN_LAYOUT_TEMPLATES.portfolio,
  restaurant: DOMAIN_LAYOUT_TEMPLATES.restaurant,
}

export function selectLayoutTemplate(
  domain: LayoutDomain,
  projectType: BlueprintProjectType,
): SelectedLayoutTemplate {
  if (domain !== 'unknown') {
    return {
      key: domain,
      sections: [...DOMAIN_LAYOUT_TEMPLATES[domain]],
    }
  }

  return {
    key: `project:${projectType}`,
    sections: [...PROJECT_TYPE_LAYOUT_TEMPLATES[projectType]],
  }
}
