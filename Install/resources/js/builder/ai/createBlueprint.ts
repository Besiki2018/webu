import type { ProjectType } from '../projectTypes'
import { analyzePrompt, type SectionSlug } from './promptAnalyzer'
import { detectProjectType } from './projectTypeDetector'
import type { BlueprintProjectType, ProjectBlueprint, ProjectBlueprintSection } from './blueprintTypes'
import { clonePlainData, stableSerialize } from './stableSerialize'
import { enhanceBlueprintWithLayout } from './blueprint/enhanceBlueprintWithLayout'

export interface CreateBlueprintInput {
  prompt: string
  projectType?: ProjectType | string | null
}

const STYLE_KEYWORDS = [
  'modern',
  'minimal',
  'minimalist',
  'premium',
  'luxury',
  'clean',
  'sleek',
  'bold',
  'professional',
  'trustworthy',
  'playful',
  'editorial',
  'friendly',
  'elegant',
] as const

const AUDIENCE_HINTS = [
  'teams',
  'team',
  'customers',
  'customer',
  'clients',
  'client',
  'owners',
  'owner',
  'founders',
  'marketers',
  'developers',
  'parents',
  'patients',
  'guests',
  'diners',
  'shoppers',
  'travellers',
  'travelers',
  'pet owners',
  'finance teams',
] as const

const BUSINESS_HINTS = [
  'clinic',
  'vet',
  'veterinary',
  'store',
  'shop',
  'restaurant',
  'cafe',
  'studio',
  'agency',
  'company',
  'firm',
  'platform',
  'software',
  'app',
  'portfolio',
  'practice',
  'service',
  'services',
  'consulting',
  'consultancy',
] as const

const BUSINESS_TYPE_PHRASES = [
  'veterinary clinic',
  'vet clinic',
  'pet clinic',
  'pet care',
  'furniture store',
  'fashion store',
  'finance software',
  'finance platform',
  'finance saas',
  'saas platform',
  'restaurant',
  'design portfolio',
  'creative portfolio',
] as const

const PROJECT_TYPE_DEFAULT_SECTIONS: Record<BlueprintProjectType, string[]> = {
  landing: ['header', 'hero', 'features', 'cta', 'footer'],
  saas: ['header', 'hero', 'features', 'pricing', 'testimonials', 'faq', 'cta', 'footer'],
  ecommerce: ['header', 'hero', 'productGrid', 'features', 'testimonials', 'cta', 'footer'],
  business: ['header', 'hero', 'features', 'testimonials', 'faq', 'cta', 'footer'],
  portfolio: ['header', 'hero', 'grid', 'testimonials', 'cta', 'footer'],
  restaurant: ['header', 'hero', 'features', 'gallery', 'testimonials', 'cta', 'footer'],
}

const SECTION_PRIORITY_ORDER = [
  'navigation',
  'header',
  'hero',
  'productGrid',
  'grid',
  'gallery',
  'features',
  'pricing',
  'cards',
  'testimonials',
  'faq',
  'blog',
  'menu',
  'contact',
  'booking',
  'cta',
  'footer',
] as const

const MAX_BLUEPRINT_CACHE_ENTRIES = 100

type PromptDerivation = {
  normalizedPrompt: string
  promptAnalysis: ReturnType<typeof analyzePrompt>
  detectedProjectType: ReturnType<typeof detectProjectType>
}

const promptDerivationCache = new Map<string, PromptDerivation>()
const blueprintCache = new Map<string, ProjectBlueprint>()

function rememberCacheEntry<T>(cache: Map<string, T>, key: string, value: T): void {
  if (cache.has(key)) {
    cache.delete(key)
  }

  cache.set(key, value)

  if (cache.size <= MAX_BLUEPRINT_CACHE_ENTRIES) {
    return
  }

  const oldestKey = cache.keys().next().value
  if (typeof oldestKey === 'string') {
    cache.delete(oldestKey)
  }
}

function normalizePrompt(prompt: string): string {
  return prompt.toLowerCase().trim().replace(/\s+/g, ' ')
}

function getPromptDerivation(prompt: string): PromptDerivation {
  const normalizedPrompt = normalizePrompt(prompt)
  const cacheKey = normalizedPrompt
  const cached = promptDerivationCache.get(cacheKey)
  if (cached) {
    return cached
  }

  const derivation: PromptDerivation = {
    normalizedPrompt,
    promptAnalysis: analyzePrompt(prompt),
    detectedProjectType: detectProjectType(prompt),
  }

  rememberCacheEntry(promptDerivationCache, cacheKey, derivation)

  return derivation
}

function titleCase(value: string): string {
  return value
    .split(/\s+/)
    .filter(Boolean)
    .map((token) => token.charAt(0).toUpperCase() + token.slice(1))
    .join(' ')
}

function compactWhitespace(value: string): string {
  return value.trim().replace(/\s+/g, ' ')
}

function mapToBlueprintProjectType(projectType: string | null | undefined): BlueprintProjectType {
  const normalized = typeof projectType === 'string' ? projectType.trim().toLowerCase() : ''

  switch (normalized) {
    case 'saas':
      return 'saas'
    case 'ecommerce':
    case 'shop':
    case 'store':
      return 'ecommerce'
    case 'portfolio':
      return 'portfolio'
    case 'restaurant':
      return 'restaurant'
    case 'business':
    case 'clinic':
    case 'booking':
    case 'hotel':
    case 'blog':
    case 'education':
      return 'business'
    case 'landing':
    default:
      return 'landing'
  }
}

export function mapBuilderProjectTypeToBlueprintProjectType(projectType: ProjectType | string | null | undefined): BlueprintProjectType {
  return mapToBlueprintProjectType(projectType)
}

function extractStyleKeywords(normalizedPrompt: string): string[] {
  const detected = STYLE_KEYWORDS.filter((keyword) => normalizedPrompt.includes(keyword))
  if (detected.length > 0) {
    return Array.from(new Set(detected))
  }

  return []
}

function sanitizeBusinessTypeCandidate(candidate: string): string {
  let normalized = compactWhitespace(candidate.toLowerCase())

  for (const keyword of STYLE_KEYWORDS) {
    normalized = normalized.replace(new RegExp(`\\b${keyword}\\b`, 'g'), ' ')
  }

  normalized = normalized
    .replace(/\b(create|build|design|make|generate|website|site|landing|page|homepage|one-page|one page)\b/g, ' ')
    .replace(/\b(a|an|the)\b/g, ' ')

  return compactWhitespace(normalized)
}

function extractForPhrase(normalizedPrompt: string): string | null {
  const match = normalizedPrompt.match(/\bfor\s+([^.,;]+)/i)
  if (!match?.[1]) {
    return null
  }

  return compactWhitespace(match[1])
}

function classifyPhrase(phrase: string | null): 'audience' | 'business' | 'offer' | null {
  if (!phrase) {
    return null
  }

  if (AUDIENCE_HINTS.some((hint) => phrase.includes(hint))) {
    return 'audience'
  }

  if (BUSINESS_HINTS.some((hint) => phrase.includes(hint))) {
    return 'business'
  }

  return 'offer'
}

function extractBusinessType(
  normalizedPrompt: string,
  projectType: BlueprintProjectType,
  industry: string | null,
  forPhrase: string | null,
): string {
  const match = normalizedPrompt.match(/\b(?:create|build|design|make|generate)\s+(?:a|an)?\s*([^.,]+?)\s+(?:website|site|landing page|landing|homepage|page)\b/i)
  const classifiedForPhrase = classifyPhrase(forPhrase)

  for (const phrase of BUSINESS_TYPE_PHRASES) {
    if (normalizedPrompt.includes(phrase)) {
      return titleCase(phrase)
    }
  }

  const fromPrompt = sanitizeBusinessTypeCandidate(match?.[1] ?? '')
  if (fromPrompt !== '' && !['landing', 'saas', 'ecommerce', 'business', 'portfolio', 'restaurant'].includes(fromPrompt)) {
    return titleCase(fromPrompt)
  }

  if (classifiedForPhrase === 'business' && forPhrase) {
    return titleCase(forPhrase)
  }

  if (industry) {
    if (projectType === 'ecommerce') {
      return titleCase(`${industry} store`)
    }
    if (projectType === 'saas') {
      return titleCase(`${industry} software`)
    }

    return titleCase(industry)
  }

  switch (projectType) {
    case 'saas':
      return 'SaaS product'
    case 'ecommerce':
      return 'Online store'
    case 'portfolio':
      return 'Creative portfolio'
    case 'restaurant':
      return 'Restaurant'
    case 'business':
      return 'Business website'
    case 'landing':
    default:
      return 'Landing page'
  }
}

function extractAudience(
  projectType: BlueprintProjectType,
  forPhrase: string | null,
  businessType: string,
): string {
  const classifiedForPhrase = classifyPhrase(forPhrase)

  if (classifiedForPhrase === 'audience' && forPhrase) {
    return compactWhitespace(forPhrase)
  }

  switch (projectType) {
    case 'saas':
      return 'teams evaluating the product'
    case 'ecommerce':
      return `shoppers looking for ${businessType.toLowerCase()}`
    case 'portfolio':
      return `clients reviewing ${businessType.toLowerCase()} work`
    case 'restaurant':
      return `guests looking for ${businessType.toLowerCase()} experiences`
    case 'business':
      return `customers looking for ${businessType.toLowerCase()}`
    case 'landing':
    default:
      return `visitors interested in ${businessType.toLowerCase()}`
  }
}

function resolveTone(
  projectType: BlueprintProjectType,
  styleKeywords: string[],
  analyzedTone: string | null,
): string {
  if (styleKeywords.includes('premium') || styleKeywords.includes('luxury')) {
    return 'premium'
  }
  if (styleKeywords.includes('minimalist') || styleKeywords.includes('minimal') || styleKeywords.includes('clean')) {
    return 'minimal'
  }
  if (styleKeywords.includes('bold')) {
    return 'bold'
  }
  if (styleKeywords.includes('professional')) {
    return 'professional'
  }
  if (styleKeywords.includes('modern') || styleKeywords.includes('sleek')) {
    return 'modern'
  }
  if (analyzedTone && analyzedTone.trim() !== '') {
    return analyzedTone
  }

  switch (projectType) {
    case 'saas':
      return 'modern'
    case 'portfolio':
      return 'editorial'
    case 'restaurant':
      return 'warm'
    case 'business':
      return 'trustworthy'
    case 'ecommerce':
      return 'conversion-focused'
    case 'landing':
    default:
      return 'clear'
  }
}

function resolvePageGoal(
  projectType: BlueprintProjectType,
  businessType: string,
  audience: string,
  normalizedPrompt: string,
): string {
  if (projectType === 'saas') {
    return `Convert ${audience} into qualified demo or signup leads`
  }
  if (projectType === 'ecommerce') {
    return `Turn ${audience} into buyers for ${businessType.toLowerCase()}`
  }
  if (projectType === 'portfolio') {
    return `Showcase ${businessType.toLowerCase()} work and win inquiries`
  }
  if (projectType === 'restaurant') {
    return 'Drive reservations and highlight signature offerings'
  }
  if (normalizedPrompt.includes('premium')) {
    return `Build trust around premium ${businessType.toLowerCase()} services`
  }

  return `Build trust with ${audience} and generate the next inquiry`
}

function mapSectionSlugToBlueprintSection(section: SectionSlug): string {
  switch (section) {
    case 'productGrid':
      return 'productGrid'
    case 'blog':
      return 'blog'
    case 'menu':
      return 'menu'
    case 'booking':
      return 'booking'
    case 'gallery':
      return 'gallery'
    case 'contact':
      return 'contact'
    case 'cards':
      return 'cards'
    case 'navigation':
      return 'navigation'
    case 'grid':
      return 'grid'
    default:
      return section
  }
}

function getBaseSections(projectType: BlueprintProjectType): string[] {
  return [...(PROJECT_TYPE_DEFAULT_SECTIONS[projectType] ?? PROJECT_TYPE_DEFAULT_SECTIONS.landing)]
}

function mergeSections(
  projectType: BlueprintProjectType,
  requiredSections: SectionSlug[],
  normalizedPrompt: string,
): string[] {
  const base = getBaseSections(projectType)
  const requested = requiredSections.map(mapSectionSlugToBlueprintSection)
  const merged = Array.from(new Set([...base, ...requested]))

  if (projectType === 'business' && (normalizedPrompt.includes('clinic') || normalizedPrompt.includes('vet') || normalizedPrompt.includes('veterin'))) {
    merged.splice(Math.max(merged.indexOf('testimonials'), 0), 0, 'faq')
  }

  return Array.from(new Set(merged))
}

function buildRequestedSections(
  requiredSections: SectionSlug[],
  businessType: string,
  audience: string,
  tone: string,
  styleKeywords: string[],
  pageGoal: string,
): ProjectBlueprintSection[] {
  const requestedSections = Array.from(new Set(requiredSections.map(mapSectionSlugToBlueprintSection)))
  const orderIndex = new Map<string, number>(SECTION_PRIORITY_ORDER.map((sectionType, index) => [sectionType, index]))

  return requestedSections
    .sort((left, right) => (orderIndex.get(left) ?? 999) - (orderIndex.get(right) ?? 999))
    .map((sectionType, index) => ({
      sectionType,
      priority: (index + 1) * 10,
      required: true,
      contentBrief: buildSectionContentBrief(
        sectionType,
        businessType,
        audience,
        tone,
        styleKeywords,
        pageGoal,
      ),
    }))
}

function isSectionExplicitlyRequested(sectionType: string, requiredSections: SectionSlug[]): boolean {
  return requiredSections.map(mapSectionSlugToBlueprintSection).includes(sectionType)
}

function buildSectionContentBrief(
  sectionType: string,
  businessType: string,
  audience: string,
  tone: string,
  styleKeywords: string[],
  pageGoal: string,
): Record<string, unknown> {
  return {
    businessType,
    audience,
    tone,
    styleKeywords,
    pageGoal,
    emphasis: sectionType,
  }
}

function buildSections(
  projectType: BlueprintProjectType,
  requiredSections: SectionSlug[],
  businessType: string,
  audience: string,
  tone: string,
  styleKeywords: string[],
  pageGoal: string,
  normalizedPrompt: string,
): ProjectBlueprintSection[] {
  const mergedSections = mergeSections(projectType, requiredSections, normalizedPrompt)
  const orderIndex = new Map<string, number>(SECTION_PRIORITY_ORDER.map((sectionType, index) => [sectionType, index]))

  return mergedSections
    .sort((left, right) => (orderIndex.get(left) ?? 999) - (orderIndex.get(right) ?? 999))
    .map((sectionType, index) => ({
      sectionType,
      priority: (index + 1) * 10,
      required: ['header', 'hero', 'cta', 'footer'].includes(sectionType) || isSectionExplicitlyRequested(sectionType, requiredSections),
      contentBrief: buildSectionContentBrief(
        sectionType,
        businessType,
        audience,
        tone,
        styleKeywords,
        pageGoal,
      ),
    }))
}

function buildRestrictions(projectType: BlueprintProjectType, normalizedPrompt: string) {
  const noPricing = normalizedPrompt.includes('no pricing') || normalizedPrompt.includes('without pricing')
    ? true
    : projectType !== 'saas'
  const noTestimonials = normalizedPrompt.includes('no testimonials') || normalizedPrompt.includes('without testimonials')
  const onePageOnly = !normalizedPrompt.includes('multi-page') && !normalizedPrompt.includes('multi page')

  return {
    noPricing,
    noTestimonials,
    onePageOnly,
  }
}

export function createEmergencyFallbackBlueprint(projectType: BlueprintProjectType = 'landing'): ProjectBlueprint {
  const businessType = projectType === 'saas'
    ? 'SaaS product'
    : projectType === 'ecommerce'
      ? 'Online store'
      : projectType === 'portfolio'
        ? 'Creative portfolio'
        : projectType === 'restaurant'
          ? 'Restaurant'
          : 'Business website'
  const audience = projectType === 'saas'
    ? 'teams evaluating the product'
    : 'visitors looking for a clear overview'
  const tone = projectType === 'portfolio' ? 'editorial' : 'clear'
  const styleKeywords = projectType === 'portfolio' ? ['editorial'] : ['clean']
  const pageGoal = projectType === 'saas'
    ? 'Convert visitors into product leads'
    : 'Build trust and move visitors toward a clear next step'

  return {
    projectType,
    businessType,
    audience,
    tone,
    styleKeywords,
    pageGoal,
    sections: buildSections(
      projectType,
      [],
      businessType,
      audience,
      tone,
      styleKeywords,
      pageGoal,
      projectType,
    ),
    restrictions: {
      noPricing: projectType !== 'saas',
      noTestimonials: false,
      onePageOnly: true,
    },
  }
}

export function __resetCreateBlueprintCacheForTests(): void {
  promptDerivationCache.clear()
  blueprintCache.clear()
}

export function createBlueprint(input: CreateBlueprintInput): ProjectBlueprint {
  const { normalizedPrompt, promptAnalysis, detectedProjectType } = getPromptDerivation(input.prompt)
  if (normalizedPrompt === '') {
    return createEmergencyFallbackBlueprint(mapBuilderProjectTypeToBlueprintProjectType(input.projectType))
  }

  const cacheKey = stableSerialize({
    normalizedPrompt,
    projectType: input.projectType ?? null,
  })
  const cachedBlueprint = blueprintCache.get(cacheKey)
  if (cachedBlueprint) {
    return clonePlainData(cachedBlueprint)
  }

  const projectType = mapBuilderProjectTypeToBlueprintProjectType(
    input.projectType ?? detectedProjectType.projectType ?? promptAnalysis.projectType
  )
  const styleKeywords = extractStyleKeywords(normalizedPrompt)
  const forPhrase = extractForPhrase(normalizedPrompt)
  const businessType = extractBusinessType(normalizedPrompt, projectType, promptAnalysis.industry, forPhrase)
  const audience = extractAudience(projectType, forPhrase, businessType)
  const tone = resolveTone(projectType, styleKeywords, promptAnalysis.tone)
  const effectiveStyleKeywords = Array.from(new Set(styleKeywords.length > 0 ? styleKeywords : [tone]))
  const pageGoal = resolvePageGoal(projectType, businessType, audience, normalizedPrompt)
  const sections = buildRequestedSections(
    promptAnalysis.explicitSections,
    businessType,
    audience,
    tone,
    effectiveStyleKeywords,
    pageGoal,
  )

  const blueprint = enhanceBlueprintWithLayout({
    blueprint: {
      projectType,
      businessType,
      audience,
      tone,
      styleKeywords: effectiveStyleKeywords,
      pageGoal,
      sections,
      restrictions: buildRestrictions(projectType, normalizedPrompt),
      sourcePrompt: input.prompt,
    },
    prompt: input.prompt,
  })

  rememberCacheEntry(blueprintCache, cacheKey, clonePlainData(blueprint))

  return blueprint
}
