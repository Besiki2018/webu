import type { AiComponentCatalogEntry, AiComponentCatalogIndex } from './componentCatalog'
import { scoreComponentForSection, type ComponentScoreBreakdown } from './componentScoring'
import type { NormalizedBlueprintSection, ProjectBlueprint } from './blueprintTypes'
import { stableSerialize } from './stableSerialize'

export interface RetrievedComponentCandidate {
  entry: AiComponentCatalogEntry
  score: ComponentScoreBreakdown
}

export interface RetrieveComponentsForSectionInput {
  blueprint: ProjectBlueprint
  section: NormalizedBlueprintSection
  registryIndex: AiComponentCatalogIndex
  sectionIndex: number
  totalSections: number
  usedComponentKeys?: Set<string>
}

const SECTION_TYPE_COMPATIBILITY: Record<string, string[]> = {
  navigation: ['navigation', 'header'],
  header: ['header', 'navigation'],
  hero: ['hero', 'banner'],
  problem: ['features', 'cards', 'banner', 'content'],
  solution: ['features', 'cards', 'banner', 'content'],
  services: ['features', 'cards', 'grid'],
  doctors: ['cards', 'grid', 'testimonials'],
  appointment_booking: ['form', 'cta', 'banner'],
  features: ['features', 'cards', 'grid'],
  process: ['features', 'cards', 'grid'],
  case_studies: ['grid', 'cards', 'testimonials'],
  portfolio_gallery: ['grid', 'gallery', 'cards'],
  about: ['content', 'banner', 'cards'],
  skills: ['features', 'cards', 'grid'],
  pricing: ['cards', 'features', 'banner', 'grid'],
  testimonials: ['testimonials', 'cards'],
  reviews: ['testimonials'],
  faq: ['faq', 'cards'],
  cta: ['cta', 'banner', 'form', 'newsletter'],
  footer: ['footer', 'newsletter'],
  productGrid: ['productGrid', 'grid', 'cards'],
  categories: ['grid', 'cards', 'productGrid'],
  featured_products: ['productGrid', 'grid', 'cards'],
  product_demo: ['banner', 'media', 'features', 'cards'],
  cards: ['cards', 'grid'],
  grid: ['grid', 'cards'],
  gallery: ['grid', 'cards'],
  menu: ['grid', 'cards'],
  chef: ['content', 'banner'],
  contact: ['form', 'cta', 'banner'],
  location: ['content', 'banner', 'form'],
  reservation: ['form', 'cta', 'banner'],
  booking: ['form', 'cta', 'banner'],
  blog: ['grid', 'cards'],
  newsletter: ['newsletter', 'cta', 'footer'],
}

const MAX_COMPONENT_RETRIEVAL_CACHE_ENTRIES = 250
const componentRetrievalCache = new Map<string, RetrievedComponentCandidate[]>()

function rememberRetrievalCache(key: string, value: RetrievedComponentCandidate[]): void {
  if (componentRetrievalCache.has(key)) {
    componentRetrievalCache.delete(key)
  }

  componentRetrievalCache.set(key, value)

  if (componentRetrievalCache.size <= MAX_COMPONENT_RETRIEVAL_CACHE_ENTRIES) {
    return
  }

  const oldestKey = componentRetrievalCache.keys().next().value
  if (typeof oldestKey === 'string') {
    componentRetrievalCache.delete(oldestKey)
  }
}

function normalizeSectionType(sectionType: string): string {
  const trimmed = sectionType.trim()
  if (trimmed === 'product-grid') {
    return 'productGrid'
  }
  if (trimmed === 'product_grid') {
    return 'productGrid'
  }
  return trimmed
}

export function getCompatibleSectionTypes(sectionType: string): string[] {
  const normalized = normalizeSectionType(sectionType)
  return Array.from(new Set([normalized, ...(SECTION_TYPE_COMPATIBILITY[normalized] ?? [])]))
}

function buildRetrievalCacheKey(input: RetrieveComponentsForSectionInput): string {
  return stableSerialize({
    blueprint: {
      projectType: input.blueprint.projectType,
      businessType: input.blueprint.businessType,
      audience: input.blueprint.audience,
      tone: input.blueprint.tone,
      styleKeywords: input.blueprint.styleKeywords,
      pageGoal: input.blueprint.pageGoal,
      restrictions: input.blueprint.restrictions ?? {},
    },
    section: input.section,
    sectionIndex: input.sectionIndex,
    totalSections: input.totalSections,
    catalogKeys: input.registryIndex.entries.map((entry) => entry.componentKey),
    usedComponentKeys: [...(input.usedComponentKeys ?? new Set<string>())].sort(),
  })
}

function cloneRetrievedCandidate(candidate: RetrievedComponentCandidate): RetrievedComponentCandidate {
  return {
    entry: candidate.entry,
    score: {
      ...candidate.score,
    },
  }
}

export function __resetComponentRetrievalCacheForTests(): void {
  componentRetrievalCache.clear()
}

function collectCompatibleCatalogEntries(
  registryIndex: AiComponentCatalogIndex,
  compatibleSectionTypes: string[],
): AiComponentCatalogEntry[] {
  const compatibleCatalog: AiComponentCatalogEntry[] = []
  const seenComponentKeys = new Set<string>()

  compatibleSectionTypes.forEach((sectionType) => {
    const entries = registryIndex.bySectionType[normalizeSectionType(sectionType)] ?? []
    entries.forEach((entry) => {
      if (seenComponentKeys.has(entry.componentKey)) {
        return
      }

      seenComponentKeys.add(entry.componentKey)
      compatibleCatalog.push(entry)
    })
  })

  return compatibleCatalog.length > 0
    ? compatibleCatalog
    : [...registryIndex.entries]
}

export function retrieveComponentsForSection(input: RetrieveComponentsForSectionInput): RetrievedComponentCandidate[] {
  const cacheKey = buildRetrievalCacheKey(input)
  const cached = componentRetrievalCache.get(cacheKey)
  if (cached) {
    return cached.map(cloneRetrievedCandidate)
  }

  const compatibleSectionTypes = getCompatibleSectionTypes(input.section.sectionType)
  const effectiveCatalog = collectCompatibleCatalogEntries(input.registryIndex, compatibleSectionTypes)

  const ranked = effectiveCatalog
    .map((entry) => ({
      entry,
      score: scoreComponentForSection({
        entry,
        blueprint: input.blueprint,
        section: input.section,
        sectionIndex: input.sectionIndex,
        totalSections: input.totalSections,
        compatibleSectionTypes,
        usedComponentKeys: input.usedComponentKeys,
      }),
    }))
    .sort((left, right) => {
      if (right.score.total !== left.score.total) {
        return right.score.total - left.score.total
      }
      if (right.score.sectionCompatibility !== left.score.sectionCompatibility) {
        return right.score.sectionCompatibility - left.score.sectionCompatibility
      }
      if (right.entry.priorityScore !== left.entry.priorityScore) {
        return right.entry.priorityScore - left.entry.priorityScore
      }
      return left.entry.componentKey.localeCompare(right.entry.componentKey)
    })

  rememberRetrievalCache(cacheKey, ranked.map(cloneRetrievedCandidate))

  return ranked
}

export function retrieveBestComponentForSection(input: RetrieveComponentsForSectionInput): RetrievedComponentCandidate | null {
  return retrieveComponentsForSection(input)[0] ?? null
}
