import { analyzeLayout } from './layoutAnalyzer'
import { analyzeDesignConsistency } from './designConsistencyAnalyzer'
import { detectSectionGaps } from './sectionGapDetector'
import { AVAILABLE_VARIANTS_BY_COMPONENT } from './componentSelector'
import type { AiComponentCatalogEntry } from './componentCatalog'
import type { NormalizedBlueprintSection, ProjectBlueprint } from './blueprintTypes'
import type { PageType } from './uxRules'

export interface SectionInput {
  localId: string
  type: string
  props?: Record<string, unknown>
}

export interface ComponentScoringInput {
  sections: SectionInput[]
  pageType?: PageType
  theme?: { primaryColor?: string; fontFamily?: string }
}

export interface ComponentScore {
  sectionKind: string
  score: number
  maxScore: number
  reason?: string
  sectionId?: string
}

export interface ComponentScoringReport {
  scores: ComponentScore[]
  lowestSectionKind: string | null
  lowestScore: number
  summary: string
}

export interface ComponentScoreBreakdown {
  total: number
  sectionCompatibility: number
  projectTypeMatch: number
  businessTypeMatch: number
  styleKeywordMatch: number
  variantRichness: number
  schemaCompleteness: number
  mobileFriendliness: number
  sectionOrderCompatibility: number
  priority: number
  repetitionPenalty: number
}

export interface ScoreComponentForSectionInput {
  entry: AiComponentCatalogEntry
  blueprint: ProjectBlueprint
  section: NormalizedBlueprintSection
  sectionIndex: number
  totalSections: number
  compatibleSectionTypes: string[]
  usedComponentKeys?: Set<string>
}

const STOP_WORDS = new Set([
  'and',
  'the',
  'for',
  'with',
  'into',
  'from',
  'that',
  'this',
  'your',
  'their',
  'site',
  'page',
  'website',
  'landing',
])

const MAX_SCORE = 10

const VARIANT_SCORE: Record<string, number> = {
  'hero-4': 10,
  hero4: 10,
  'hero-3': 8,
  hero3: 8,
  'hero-2': 6,
  hero2: 6,
  'hero-1': 3,
  hero1: 3,
  'features-3': 10,
  features3: 10,
  'features-2': 7,
  features2: 7,
  'features-1': 4,
  features1: 4,
  'cta-3': 10,
  cta3: 10,
  'cta-2': 6,
  cta2: 6,
  'cta-1': 3,
  cta1: 3,
}

function normalizeSectionType(sectionType: string): string {
  const normalized = sectionType.trim()
  if (normalized === 'product-grid') {
    return 'productGrid'
  }
  return normalized
}

function getSectionKind(type: string): string {
  const normalizedType = (type || '').trim().toLowerCase()

  if (normalizedType.includes('header') || normalizedType.includes('nav')) return 'header'
  if (normalizedType.includes('footer')) return 'footer'
  if (normalizedType.includes('hero')) return 'hero'
  if (normalizedType.includes('feature')) return 'features'
  if (normalizedType.includes('cta')) return 'cta'
  if (normalizedType.includes('pricing')) return 'pricing'
  if (normalizedType.includes('testimonial') || normalizedType.includes('review')) return 'testimonials'
  if (normalizedType.includes('card')) return 'cards'
  if (normalizedType.includes('grid')) return 'grid'
  if (normalizedType.includes('newsletter')) return 'newsletter'

  return normalizedType || 'unknown'
}

function tokenize(value: string): string[] {
  return value
    .toLowerCase()
    .split(/[^a-z0-9]+/g)
    .map((token) => token.trim())
    .filter((token) => token.length > 1 && !STOP_WORDS.has(token))
}

function countOverlap(source: string[], target: string[]): number {
  if (source.length === 0 || target.length === 0) {
    return 0
  }

  const targetSet = new Set(target)
  return source.reduce((count, token) => count + (targetSet.has(token) ? 1 : 0), 0)
}

function getVariant(props: Record<string, unknown> | undefined): string {
  if (!props) return ''

  const value = props.variant ?? props.variantId
  return typeof value === 'string' ? value.trim().toLowerCase() : ''
}

function getVariantScore(variant: string): number {
  if (!variant) return 5
  return VARIANT_SCORE[variant] ?? 5
}

function hasKeyContent(props: Record<string, unknown> | undefined, sectionKind: string): boolean {
  if (!props) return false

  const hasTitle = [props.title, props.headline].some((value) => typeof value === 'string' && value.trim())
  const hasSubtitle = [props.subtitle, props.description].some((value) => typeof value === 'string' && value.trim())
  const hasCta = [props.buttonText, props.buttonLabel, props.cta].some((value) => typeof value === 'string' && value.trim())

  if (sectionKind === 'hero') return hasTitle && (hasSubtitle || hasCta)
  if (sectionKind === 'features') return hasTitle || (Array.isArray(props.items) && props.items.length > 0)
  if (sectionKind === 'cta') return (hasTitle || hasSubtitle) && hasCta

  return hasTitle || hasSubtitle || hasCta
}

function buildBusinessSignals(blueprint: ProjectBlueprint): string[] {
  const source = [
    blueprint.projectType,
    blueprint.businessType,
    blueprint.audience,
    blueprint.pageGoal,
    blueprint.tone,
    ...blueprint.styleKeywords,
  ].join(' ')
  const tags = new Set(tokenize(source))
  const normalized = source.toLowerCase()

  if (/(vet|veterin|clinic|medical|health|pet)/.test(normalized)) {
    ['medical', 'clinic', 'appointment', 'care', 'trust', 'pet', 'services'].forEach((tag) => tags.add(tag))
  }
  if (/(finance|fintech|account|bookkeep|wealth)/.test(normalized)) {
    ['finance', 'analytics', 'professional', 'trust', 'b2b', 'teams'].forEach((tag) => tags.add(tag))
  }
  if (/(saas|software|platform|product|app)/.test(normalized)) {
    ['saas', 'software', 'product', 'conversion', 'b2b'].forEach((tag) => tags.add(tag))
  }
  if (/(consult|agency|service|firm)/.test(normalized)) {
    ['services', 'professional', 'trust', 'lead'].forEach((tag) => tags.add(tag))
  }
  if (/(restaurant|cafe|dining|menu)/.test(normalized)) {
    ['menu', 'hospitality', 'reservation', 'food'].forEach((tag) => tags.add(tag))
  }
  if (/(portfolio|studio|design|creative)/.test(normalized)) {
    ['portfolio', 'gallery', 'creative', 'editorial', 'showcase'].forEach((tag) => tags.add(tag))
  }
  if (/(shop|store|ecommerce|catalog|product)/.test(normalized)) {
    ['retail', 'catalog', 'products', 'shop'].forEach((tag) => tags.add(tag))
  }
  if (/(book|booking|appointment|reservation)/.test(normalized)) {
    ['booking', 'appointment', 'reservation'].forEach((tag) => tags.add(tag))
  }

  return [...tags]
}

function resolveVariantCount(entry: AiComponentCatalogEntry): number {
  const explicitVariants = AVAILABLE_VARIANTS_BY_COMPONENT[entry.componentKey]?.length ?? 0
  const schemaVariants = entry.variants.length
  return Math.max(explicitVariants, schemaVariants)
}

function scoreSchemaCompleteness(entry: AiComponentCatalogEntry): number {
  const fieldTypes = new Set(entry.propsSchema.map((field) => field.type))
  const fieldPaths = entry.propsSchema.map((field) => field.path)
  let score = Math.min(entry.propsSchema.length, 10)

  if (fieldTypes.has('repeater') || fieldTypes.has('menu')) score += 3
  if (fieldTypes.has('image') || fieldTypes.has('video')) score += 2
  if (fieldTypes.has('link')) score += 2
  if (fieldTypes.has('richtext') || fieldTypes.has('text')) score += 2
  if (fieldPaths.some((path) => path.toLowerCase().includes('variant'))) score += 2

  return Math.min(score, 16)
}

function scoreSectionOrderCompatibility(
  requestedSectionType: string,
  entrySectionType: string,
  sectionIndex: number,
  totalSections: number,
): number {
  const requested = normalizeSectionType(requestedSectionType)
  const entry = normalizeSectionType(entrySectionType)
  const lastIndex = Math.max(totalSections - 1, 0)

  if ((requested === 'header' || requested === 'navigation') && sectionIndex === 0) return entry === requested ? 12 : 9
  if (requested === 'hero' && sectionIndex <= 1) return entry === 'hero' ? 12 : 9
  if (requested === 'footer' && sectionIndex === lastIndex) return entry === 'footer' ? 12 : 8
  if (requested === 'cta' && sectionIndex >= Math.max(lastIndex - 1, 0)) return ['cta', 'banner', 'form', 'newsletter'].includes(entry) ? 12 : 7
  if (['features', 'pricing', 'productGrid', 'grid', 'cards', 'testimonials', 'faq'].includes(requested)) {
    return sectionIndex > 0 && sectionIndex < lastIndex ? 8 : 4
  }

  return 5
}

export function scoreComponentForSection(input: ScoreComponentForSectionInput): ComponentScoreBreakdown {
  const requestedSectionType = normalizeSectionType(input.section.sectionType)
  const entrySectionType = normalizeSectionType(input.entry.sectionType)
  const businessSignals = buildBusinessSignals(input.blueprint)
  const entrySignals = [
    ...input.entry.categoryTags,
    ...input.entry.styleTags,
    ...input.entry.capabilities,
    ...tokenize(input.entry.label),
  ]
  const styleSignals = Array.from(new Set([
    input.blueprint.tone.toLowerCase(),
    ...input.blueprint.styleKeywords.map((keyword) => keyword.toLowerCase()),
  ]))

  const sectionCompatibility = entrySectionType === requestedSectionType
    ? 26
    : input.compatibleSectionTypes.includes(entrySectionType)
      ? 18
      : 0
  const projectTypeMatch = input.entry.projectTypesAllowed.includes(input.blueprint.projectType as typeof input.entry.projectTypesAllowed[number])
    ? 22
    : 8
  const businessTypeMatch = Math.min(countOverlap(businessSignals, entrySignals) * 4, 24)
  const styleKeywordMatch = Math.min(countOverlap(styleSignals, [...input.entry.styleTags, ...input.entry.variants.map((variant) => variant.label.toLowerCase())]) * 4, 20)
  const variantRichness = Math.min(resolveVariantCount(input.entry), 6) * 1.5
  const schemaCompleteness = scoreSchemaCompleteness(input.entry)
  const mobileFriendliness = (
    (input.entry.responsiveEnabled ? 6 : 0)
    + (input.entry.supportsResponsiveOverrides ? 3 : 0)
    + (input.entry.supportsVisibility ? 2 : 0)
  )
  const sectionOrderCompatibility = scoreSectionOrderCompatibility(
    requestedSectionType,
    entrySectionType,
    input.sectionIndex,
    input.totalSections,
  )
  const priority = Math.min(Math.max(input.entry.priorityScore, 0), 100) / 10
  const repetitionPenalty = input.usedComponentKeys?.has(input.entry.componentKey) ? 8 : 0
  const total = sectionCompatibility
    + projectTypeMatch
    + businessTypeMatch
    + styleKeywordMatch
    + variantRichness
    + schemaCompleteness
    + mobileFriendliness
    + sectionOrderCompatibility
    + priority
    - repetitionPenalty

  return {
    total,
    sectionCompatibility,
    projectTypeMatch,
    businessTypeMatch,
    styleKeywordMatch,
    variantRichness,
    schemaCompleteness,
    mobileFriendliness,
    sectionOrderCompatibility,
    priority,
    repetitionPenalty,
  }
}

export function scoreComponents(input: ComponentScoringInput): ComponentScoringReport {
  const sections = input.sections ?? []
  const pageType = (input.pageType ?? 'landing') as PageType
  const theme = input.theme
  const sectionKinds = sections.map((section) => getSectionKind(section.type))
  const layoutReport = analyzeLayout({ sections, sectionKinds })
  const designReport = analyzeDesignConsistency({ sections, theme, sectionKinds })
  const gapReport = detectSectionGaps({ sections, pageType, sectionKinds })

  const issueCountByKind: Record<string, number> = {}
  for (const issue of layoutReport.issues) {
    const sectionKind = issue.sectionKind ?? 'unknown'
    issueCountByKind[sectionKind] = (issueCountByKind[sectionKind] ?? 0) + 1
  }
  for (const issue of designReport.issues) {
    const sectionKind = issue.sectionKind ?? 'unknown'
    issueCountByKind[sectionKind] = (issueCountByKind[sectionKind] ?? 0) + 1
  }

  const scores: ComponentScore[] = []
  const seenKinds = new Set<string>()

  for (const section of sections) {
    const sectionKind = getSectionKind(section.type)
    if (seenKinds.has(sectionKind)) {
      continue
    }

    seenKinds.add(sectionKind)
    const variant = getVariant(section.props)
    let score = getVariantScore(variant)
    if (hasKeyContent(section.props, sectionKind) && score < MAX_SCORE) {
      score = Math.min(MAX_SCORE, score + 1)
    }

    const deductions = issueCountByKind[sectionKind] ?? 0
    score = Math.max(1, score - deductions)

    const reasons: string[] = []
    if (variant && getVariantScore(variant) <= 4) reasons.push('weak variant')
    if (!hasKeyContent(section.props, sectionKind)) reasons.push('missing content')
    if (deductions > 0) reasons.push('layout/design issues')

    scores.push({
      sectionKind,
      score,
      maxScore: MAX_SCORE,
      reason: reasons.length > 0 ? reasons.join('; ') : undefined,
      sectionId: section.localId,
    })
  }

  for (const missingSection of gapReport.missing) {
    if (seenKinds.has(missingSection.sectionKind)) {
      continue
    }

    seenKinds.add(missingSection.sectionKind)
    scores.push({
      sectionKind: missingSection.sectionKind,
      score: 0,
      maxScore: MAX_SCORE,
      reason: 'missing',
    })
  }

  let lowestScore = MAX_SCORE + 1
  let lowestSectionKind: string | null = null
  for (const score of scores) {
    if (score.score < lowestScore) {
      lowestScore = score.score
      lowestSectionKind = score.sectionKind
    }
  }

  if (lowestSectionKind === null && scores.length > 0) {
    lowestScore = Math.min(...scores.map((score) => score.score))
    lowestSectionKind = scores.find((score) => score.score === lowestScore)?.sectionKind ?? null
  }

  const summary = scores
    .filter((score) => ['hero', 'features', 'cta', 'testimonials', 'pricing'].includes(score.sectionKind))
    .sort((left, right) => left.sectionKind.localeCompare(right.sectionKind))
    .map((score) => `${score.sectionKind} ${score.score}/${score.maxScore}`)
    .join(', ') || 'No sections scored'

  return {
    scores,
    lowestSectionKind,
    lowestScore: lowestSectionKind === null ? 0 : lowestScore,
    summary,
  }
}
