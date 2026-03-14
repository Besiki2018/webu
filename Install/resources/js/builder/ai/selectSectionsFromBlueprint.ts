import type { BlueprintProjectType } from './blueprintTypes'
import type { NormalizedBlueprintSection, ProjectBlueprint } from './blueprintTypes'

const SECTION_ORDER = [
  'navigation',
  'header',
  'hero',
  'problem',
  'solution',
  'services',
  'menu',
  'product_demo',
  'productGrid',
  'categories',
  'featured_products',
  'portfolio_gallery',
  'case_studies',
  'process',
  'doctors',
  'chef',
  'about',
  'gallery',
  'features',
  'skills',
  'grid',
  'pricing',
  'cards',
  'testimonials',
  'reviews',
  'faq',
  'blog',
  'location',
  'contact',
  'appointment_booking',
  'reservation',
  'booking',
  'cta',
  'newsletter',
  'footer',
] as const

const EMERGENCY_FALLBACK_SECTION_MAP: Record<BlueprintProjectType, string[]> = {
  landing: ['header', 'hero', 'features', 'cta', 'footer'],
  saas: ['header', 'hero', 'features', 'pricing', 'cta', 'footer'],
  ecommerce: ['header', 'hero', 'productGrid', 'features', 'cta', 'footer'],
  business: ['header', 'hero', 'features', 'testimonials', 'cta', 'footer'],
  portfolio: ['header', 'hero', 'grid', 'cta', 'footer'],
  restaurant: ['header', 'hero', 'gallery', 'cta', 'footer'],
}

export interface SelectSectionsFromBlueprintResult {
  sections: NormalizedBlueprintSection[]
  usedEmergencyFallback: boolean
}

function buildEmergencyFallbackSections(projectType: BlueprintProjectType): NormalizedBlueprintSection[] {
  return (EMERGENCY_FALLBACK_SECTION_MAP[projectType] ?? EMERGENCY_FALLBACK_SECTION_MAP.landing)
    .map((sectionType, index) => ({
      sectionType,
      priority: (index + 1) * 10,
      required: ['header', 'hero', 'cta', 'footer'].includes(sectionType),
      contentBrief: {
        emphasis: sectionType,
        emergencyFallback: true,
      },
    }))
}

export function getEmergencyFallbackSections(projectType: BlueprintProjectType): NormalizedBlueprintSection[] {
  return buildEmergencyFallbackSections(projectType)
}

function orderSections(sections: NormalizedBlueprintSection[]): NormalizedBlueprintSection[] {
  const orderIndex = new Map<string, number>(SECTION_ORDER.map((sectionType, index) => [sectionType, index]))

  return [...sections].sort((left, right) => {
    const leftOrder = orderIndex.get(left.sectionType) ?? 999
    const rightOrder = orderIndex.get(right.sectionType) ?? 999
    if (leftOrder !== rightOrder) {
      return leftOrder - rightOrder
    }

    return left.priority - right.priority
  })
}

export function selectSectionsFromBlueprint(blueprint: ProjectBlueprint): SelectSectionsFromBlueprintResult {
  const seen = new Map<string, NormalizedBlueprintSection>()

  for (const section of blueprint.sections) {
    const sectionType = typeof section.sectionType === 'string' ? section.sectionType.trim() : ''
    if (sectionType === '') {
      continue
    }

    if (blueprint.restrictions?.noPricing && sectionType === 'pricing') {
      continue
    }

    if (blueprint.restrictions?.noTestimonials && sectionType === 'testimonials') {
      continue
    }

    const normalizedSection: NormalizedBlueprintSection = {
      sectionType,
      priority: Number.isFinite(section.priority) ? section.priority : 999,
      required: section.required === true,
      ...(section.contentBrief ? { contentBrief: section.contentBrief } : {}),
    }

    const existing = seen.get(sectionType)
    if (!existing) {
      seen.set(sectionType, normalizedSection)
      continue
    }

    seen.set(sectionType, {
      ...existing,
      priority: Math.min(existing.priority, normalizedSection.priority),
      required: existing.required || normalizedSection.required,
      contentBrief: existing.contentBrief ?? normalizedSection.contentBrief,
    })
  }

  const selectedSections = orderSections([...seen.values()])
  const withRequiredShell = [...selectedSections]

  for (const fallbackSection of buildEmergencyFallbackSections(blueprint.projectType)) {
    if (!withRequiredShell.some((section) => section.sectionType === fallbackSection.sectionType) && fallbackSection.required) {
      withRequiredShell.push(fallbackSection)
    }
  }

  const normalized = orderSections(withRequiredShell)
  if (normalized.length > 0) {
    return {
      sections: normalized,
      usedEmergencyFallback: false,
    }
  }

  return {
    sections: buildEmergencyFallbackSections(blueprint.projectType),
    usedEmergencyFallback: true,
  }
}
