import type { DetectedLayoutDomain, ProjectBlueprint } from '../blueprintTypes'
import type { DraftLayoutPlanSection } from './normalizeLayoutPlan'

export interface ScoredLayoutPlanSection extends DraftLayoutPlanSection {
  score: number
  scoreBreakdown: {
    domainRelevance: number
    projectTypeRelevance: number
    userIntentRelevance: number
    uxPriority: number
  }
}

const UX_PRIORITY_SCORE: Record<string, number> = {
  header: 12,
  hero: 11,
  services: 10,
  problem: 10,
  solution: 10,
  features: 9,
  product_demo: 9,
  pricing: 9,
  productGrid: 9,
  appointment_booking: 9,
  reservation: 9,
  menu: 9,
  categories: 8,
  featured_products: 8,
  doctors: 8,
  chef: 8,
  process: 8,
  case_studies: 8,
  portfolio_gallery: 8,
  testimonials: 7,
  reviews: 7,
  faq: 6,
  about: 6,
  skills: 6,
  gallery: 6,
  contact: 6,
  location: 6,
  cta: 6,
  footer: 5,
}

const DOMAIN_RELEVANCE_SCORE: Record<string, Record<string, number>> = {
  vet_clinic: {
    hero: 10,
    services: 9,
    doctors: 8,
    appointment_booking: 9,
    testimonials: 7,
    faq: 6,
    contact: 7,
    footer: 5,
  },
  restaurant: {
    hero: 10,
    menu: 9,
    chef: 8,
    gallery: 8,
    reservation: 9,
    reviews: 7,
    location: 7,
    footer: 5,
  },
  saas: {
    hero: 10,
    problem: 9,
    solution: 9,
    features: 9,
    product_demo: 8,
    pricing: 8,
    testimonials: 7,
    faq: 6,
    cta: 7,
    footer: 5,
  },
  agency: {
    hero: 10,
    services: 9,
    case_studies: 8,
    process: 7,
    testimonials: 7,
    contact: 7,
    footer: 5,
  },
  portfolio: {
    hero: 10,
    portfolio_gallery: 9,
    about: 8,
    skills: 7,
    contact: 7,
    footer: 5,
  },
  ecommerce: {
    hero: 10,
    productGrid: 9,
    categories: 8,
    featured_products: 8,
    testimonials: 7,
    faq: 6,
    footer: 5,
  },
  unknown: {},
}

const PROJECT_TYPE_RELEVANCE_SCORE: Record<ProjectBlueprint['projectType'], Record<string, number>> = {
  landing: {
    hero: 8,
    problem: 7,
    solution: 7,
    features: 7,
    testimonials: 6,
    faq: 5,
    cta: 6,
    footer: 5,
  },
  saas: DOMAIN_RELEVANCE_SCORE.saas,
  ecommerce: DOMAIN_RELEVANCE_SCORE.ecommerce,
  business: {
    hero: 8,
    services: 8,
    process: 7,
    testimonials: 7,
    faq: 6,
    contact: 7,
    cta: 6,
    footer: 5,
  },
  portfolio: DOMAIN_RELEVANCE_SCORE.portfolio,
  restaurant: DOMAIN_RELEVANCE_SCORE.restaurant,
}

const USER_INTENT_RULES: Array<{ keywords: string[]; sections: string[]; boost: number }> = [
  {
    keywords: ['appointment', 'book', 'booking', 'visit', 'schedule'],
    sections: ['appointment_booking', 'reservation', 'contact', 'cta'],
    boost: 4,
  },
  {
    keywords: ['doctor', 'vet', 'veterinary', 'pet', 'clinic'],
    sections: ['services', 'doctors', 'testimonials', 'faq'],
    boost: 4,
  },
  {
    keywords: ['menu', 'chef', 'dining', 'restaurant', 'reservation'],
    sections: ['menu', 'chef', 'gallery', 'reservation', 'reviews', 'location'],
    boost: 4,
  },
  {
    keywords: ['software', 'saas', 'analytics', 'dashboard', 'automation', 'platform'],
    sections: ['problem', 'solution', 'features', 'product_demo', 'pricing'],
    boost: 4,
  },
  {
    keywords: ['agency', 'marketing', 'consulting'],
    sections: ['services', 'case_studies', 'process', 'contact'],
    boost: 3,
  },
  {
    keywords: ['portfolio', 'designer', 'photographer', 'studio'],
    sections: ['portfolio_gallery', 'about', 'skills', 'contact'],
    boost: 4,
  },
  {
    keywords: ['shop', 'store', 'products', 'buy online', 'catalog'],
    sections: ['productGrid', 'categories', 'featured_products', 'testimonials'],
    boost: 4,
  },
]

function normalizeText(value: string): string {
  return value.toLowerCase().trim().replace(/\s+/g, ' ')
}

function buildIntentSource(blueprint: ProjectBlueprint, detectedDomain: DetectedLayoutDomain): string {
  return normalizeText([
    blueprint.sourcePrompt,
    blueprint.businessType,
    blueprint.audience,
    blueprint.pageGoal,
    blueprint.tone,
    detectedDomain.domain,
    ...blueprint.styleKeywords,
    ...detectedDomain.keywords,
  ].filter(Boolean).join(' '))
}

function scoreUserIntent(sectionType: string, intentSource: string): number {
  return USER_INTENT_RULES.reduce((total, rule) => {
    if (!rule.sections.includes(sectionType)) {
      return total
    }

    const matches = rule.keywords.filter((keyword) => intentSource.includes(keyword))
    if (matches.length === 0) {
      return total
    }

    return total + (rule.boost * matches.length)
  }, 0)
}

export function scoreLayoutSections(input: {
  blueprint: ProjectBlueprint
  detectedDomain: DetectedLayoutDomain
  sections: DraftLayoutPlanSection[]
}): ScoredLayoutPlanSection[] {
  const intentSource = buildIntentSource(input.blueprint, input.detectedDomain)
  const domainScores = DOMAIN_RELEVANCE_SCORE[input.detectedDomain.domain] ?? {}
  const projectTypeScores = PROJECT_TYPE_RELEVANCE_SCORE[input.blueprint.projectType] ?? {}

  return input.sections.map((section, index) => {
    const type = section.type.trim()
    const domainRelevance = domainScores[type] ?? 0
    const projectTypeRelevance = projectTypeScores[type] ?? 0
    const userIntentRelevance = scoreUserIntent(type, intentSource)
    const uxPriority = UX_PRIORITY_SCORE[type] ?? 4

    return {
      ...section,
      type,
      originalIndex: section.originalIndex ?? index,
      score: domainRelevance + projectTypeRelevance + userIntentRelevance + uxPriority,
      scoreBreakdown: {
        domainRelevance,
        projectTypeRelevance,
        userIntentRelevance,
        uxPriority,
      },
    }
  })
}
