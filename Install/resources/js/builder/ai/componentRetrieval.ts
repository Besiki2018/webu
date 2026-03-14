import type { AiComponentCatalogEntry } from './componentCatalog'
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
  catalog: AiComponentCatalogEntry[]
  sectionIndex: number
  totalSections: number
  usedComponentKeys?: Set<string>
}

const SECTION_TYPE_COMPATIBILITY: Record<string, string[]> = {
  navigation: ['navigation', 'header'],
  header: ['header', 'navigation'],
  hero: ['hero', 'banner'],
  features: ['features', 'cards', 'grid'],
  pricing: ['cards', 'features', 'banner', 'grid'],
  testimonials: ['testimonials', 'cards'],
  faq: ['faq', 'cards'],
  cta: ['cta', 'banner', 'form', 'newsletter'],
  footer: ['footer', 'newsletter'],
  productGrid: ['productGrid', 'grid', 'cards'],
  cards: ['cards', 'grid'],
  grid: ['grid', 'cards'],
  gallery: ['grid', 'cards'],
  menu: ['grid', 'cards'],
  contact: ['form', 'cta', 'banner'],
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
  return trimmed
}

export function getCompatibleSectionTypes(sectionType: string): string[] {
  const normalized = normalizeSectionType(sectionType)
  return Array.from(new Set([normalized, ...(SECTION_TYPE_COMPATIBILITY[normalized] ?? [])]))
}

function isCompatibleCandidate(entry: AiComponentCatalogEntry, compatibleSectionTypes: string[]): boolean {
  const entrySectionType = normalizeSectionType(entry.sectionType)
  return compatibleSectionTypes.includes(entrySectionType)
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
    catalogKeys: input.catalog.map((entry) => entry.componentKey),
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

export function retrieveComponentsForSection(input: RetrieveComponentsForSectionInput): RetrievedComponentCandidate[] {
  const cacheKey = buildRetrievalCacheKey(input)
  const cached = componentRetrievalCache.get(cacheKey)
  if (cached) {
    return cached.map(cloneRetrievedCandidate)
  }

  const compatibleSectionTypes = getCompatibleSectionTypes(input.section.sectionType)
  const compatibleCatalog = input.catalog.filter((entry) => isCompatibleCandidate(entry, compatibleSectionTypes))
  const effectiveCatalog = compatibleCatalog.length > 0 ? compatibleCatalog : input.catalog

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
