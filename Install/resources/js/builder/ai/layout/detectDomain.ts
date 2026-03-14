import type { BlueprintProjectType, DetectedLayoutDomain, LayoutDomain } from '../blueprintTypes'

export interface DetectDomainInput {
  prompt: string
  projectType?: BlueprintProjectType | string | null
  userMetadata?: Partial<{
    businessType: string
    audience: string
    pageGoal: string
    styleKeywords: string[]
  }>
}

const DOMAIN_MATCH_ORDER: LayoutDomain[] = [
  'vet_clinic',
  'restaurant',
  'saas',
  'agency',
  'portfolio',
  'ecommerce',
  'unknown',
]

const DOMAIN_KEYWORDS: Record<Exclude<LayoutDomain, 'unknown'>, string[]> = {
  vet_clinic: [
    'vet',
    'veterinary',
    'animal hospital',
    'pet clinic',
    'dog care',
    'cat care',
  ],
  restaurant: [
    'restaurant',
    'menu',
    'chef',
    'reservation',
    'dining',
  ],
  saas: [
    'software',
    'platform',
    'automation',
    'saas',
    'dashboard',
  ],
  agency: [
    'marketing',
    'digital agency',
    'consulting',
  ],
  portfolio: [
    'portfolio',
    'designer',
    'photographer',
  ],
  ecommerce: [
    'shop',
    'products',
    'store',
    'buy online',
  ],
}

function normalizeText(value: string): string {
  return value.toLowerCase().trim().replace(/\s+/g, ' ')
}

function normalizeProjectType(projectType: DetectDomainInput['projectType']): BlueprintProjectType | null {
  if (typeof projectType !== 'string') {
    return null
  }

  const normalized = normalizeText(projectType)
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
    case 'landing':
      return normalized
    default:
      return null
  }
}

function resolveProjectTypeDomain(projectType: DetectDomainInput['projectType']): LayoutDomain | null {
  switch (normalizeProjectType(projectType)) {
    case 'saas':
      return 'saas'
    case 'ecommerce':
      return 'ecommerce'
    case 'portfolio':
      return 'portfolio'
    case 'restaurant':
      return 'restaurant'
    default:
      return null
  }
}

function buildSignalSource(input: DetectDomainInput): string {
  const metadata = input.userMetadata ?? {}
  return normalizeText([
    input.prompt,
    metadata.businessType,
    metadata.audience,
    metadata.pageGoal,
    ...(metadata.styleKeywords ?? []),
  ].filter(Boolean).join(' '))
}

function scoreKeywordMatch(source: string, keyword: string): number {
  if (!source.includes(keyword)) {
    return 0
  }

  return keyword.includes(' ') ? 3 : 2
}

export function detectDomain(input: DetectDomainInput): DetectedLayoutDomain {
  const source = buildSignalSource(input)
  const projectTypeDomain = resolveProjectTypeDomain(input.projectType)
  const scores = DOMAIN_MATCH_ORDER
    .filter((domain): domain is Exclude<LayoutDomain, 'unknown'> => domain !== 'unknown')
    .map((domain) => {
      const keywords = DOMAIN_KEYWORDS[domain].filter((keyword) => source.includes(keyword))
      const keywordScore = keywords.reduce((total, keyword) => total + scoreKeywordMatch(source, keyword), 0)
      const projectTypeBoost = projectTypeDomain === domain ? 2 : 0

      return {
        domain,
        keywords,
        score: keywordScore + projectTypeBoost,
      }
    })

  const winner = [...scores].sort((left, right) => {
    if (right.score !== left.score) {
      return right.score - left.score
    }

    return DOMAIN_MATCH_ORDER.indexOf(left.domain) - DOMAIN_MATCH_ORDER.indexOf(right.domain)
  })[0]

  if (!winner || winner.score <= 0) {
    if (projectTypeDomain) {
      return {
        domain: projectTypeDomain,
        confidence: 0.55,
        keywords: [],
      }
    }

    return {
      domain: 'unknown',
      confidence: 0,
      keywords: [],
    }
  }

  return {
    domain: winner.domain,
    confidence: Math.min(0.99, 0.35 + (winner.score / 12)),
    keywords: winner.keywords,
  }
}
