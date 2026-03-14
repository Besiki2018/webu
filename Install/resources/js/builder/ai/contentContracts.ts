import type { AiComponentCatalogEntry, AiComponentLayoutType } from './componentCatalog'
import type { BlueprintComponentSelection, ProjectBlueprint } from './blueprintTypes'

export interface SectionContentBrief {
  sectionType: string
  layoutType: AiComponentLayoutType
  businessType: string
  audience: string
  tone: string
  styleKeywords: string[]
  pageGoal: string
  messagingAngle: string
  proofPoints: string[]
  ctaIntent: string
}

export interface HeroSectionContent {
  kind: 'hero'
  eyebrow: string
  badgeText?: string
  title: string
  subtitle: string
  description: string
  primaryCtaLabel: string
  primaryCtaUrl: string
  secondaryCtaLabel?: string
  secondaryCtaUrl?: string
  imageAlt: string
  statValue?: string
  statUnit?: string
  statLabel?: string
}

export interface RepeaterContentItem {
  title?: string
  description?: string
  icon?: string
  link?: string
}

export interface FeaturesSectionContent {
  kind: 'features'
  title: string
  items: RepeaterContentItem[]
}

export interface PricingSectionContent {
  kind: 'pricing'
  title: string
  subtitle?: string
  items: RepeaterContentItem[]
}

export interface CollectionSectionContent {
  kind: 'collection'
  title: string
  subtitle?: string
  items: RepeaterContentItem[]
}

export interface TestimonialSectionContent {
  kind: 'testimonials'
  title: string
  items: Array<{
    user_name: string
    text: string
    rating?: number
  }>
}

export interface FaqSectionContent {
  kind: 'faq'
  title: string
  items: Array<{
    question: string
    answer: string
  }>
}

export interface CtaSectionContent {
  kind: 'cta'
  title: string
  subtitle: string
  buttonLabel: string
  buttonUrl: string
}

export interface FormSectionContent {
  kind: 'form'
  title: string
  subtitle: string
  submitLabel: string
  namePlaceholder: string
  emailPlaceholder: string
  messagePlaceholder: string
}

export interface NavigationSectionContent {
  kind: 'navigation'
  logoText: string
  logoFallback: string
  menuItems: Array<{ label: string; url: string }>
  ctaLabel: string
  ctaUrl: string
  menuDrawerFooterLabel?: string
  menuDrawerFooterUrl?: string
}

export interface FooterSectionContent {
  kind: 'footer'
  logoText: string
  logoFallback: string
  description: string
  subtitle?: string
  links: Array<{ label: string; url: string }>
  socialLinks?: Array<{ label: string; url: string }>
  copyright: string
  contactAddress: string
  newsletterHeading: string
  newsletterCopy: string
  newsletterPlaceholder: string
  newsletterButtonLabel: string
}

export interface ProductGridSectionContent {
  kind: 'product-grid'
  title: string
  subtitle: string
  addToCartLabel: string
  ctaLabel: string
  productCount: number
  productsPerPage: number
}

export interface BannerSectionContent {
  kind: 'banner'
  title: string
  subtitle: string
  ctaLabel: string
  ctaUrl: string
}

export interface GenericSectionContent {
  kind: 'generic'
  title: string
  subtitle: string
  body?: string
}

export type GeneratedSectionStructuredContent =
  | HeroSectionContent
  | FeaturesSectionContent
  | PricingSectionContent
  | CollectionSectionContent
  | TestimonialSectionContent
  | FaqSectionContent
  | CtaSectionContent
  | FormSectionContent
  | NavigationSectionContent
  | FooterSectionContent
  | ProductGridSectionContent
  | BannerSectionContent
  | GenericSectionContent

export interface GenerateSectionContentInput {
  prompt: string
  blueprint: ProjectBlueprint
  section: BlueprintComponentSelection
  catalogEntry?: AiComponentCatalogEntry | null
  brandName?: string | null
  sectionIndex: number
  totalSections: number
}

export interface GeneratedSectionContent {
  brief: SectionContentBrief
  content: GeneratedSectionStructuredContent
  props: Record<string, unknown>
}

export const DISALLOWED_PRODUCTION_COPY_PATTERNS = [
  /\blorem\b/i,
  /\bamazing\b/i,
  /\bwelcome\b/i,
  /\bbest solution\b/i,
  /^feature\s+\d+$/i,
  /^description for/i,
  /^ready to get started\??$/i,
  /^ready to start\??$/i,
  /^common questions$/i,
  /^trusted by customers$/i,
  /^contact us$/i,
  /^get started$/i,
  /^learn more$/i,
  /^subscribe$/i,
  /^your email$/i,
  /^your name$/i,
  /^email address$/i,
  /^project details$/i,
  /^submit$/i,
  /^quote text\.?$/i,
  /^author$/i,
  /^question\??$/i,
  /^answer\.?$/i,
] as const

function normalizeString(value: string): string {
  return value.trim().replace(/\s+/g, ' ')
}

export function containsDisallowedProductionCopy(value: string): boolean {
  const normalized = normalizeString(value)
  if (normalized === '') {
    return false
  }

  return DISALLOWED_PRODUCTION_COPY_PATTERNS.some((pattern) => pattern.test(normalized))
}

export function hasDisallowedProductionCopy(value: unknown): boolean {
  if (typeof value === 'string') {
    return containsDisallowedProductionCopy(value)
  }

  if (Array.isArray(value)) {
    return value.some((item) => hasDisallowedProductionCopy(item))
  }

  if (value && typeof value === 'object') {
    return Object.values(value as Record<string, unknown>).some((entry) => hasDisallowedProductionCopy(entry))
  }

  return false
}
