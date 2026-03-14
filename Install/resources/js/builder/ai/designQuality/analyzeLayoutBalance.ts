import type { DesignQualityAnalysisContext, DesignQualityAnalyzerResult, DesignQualitySuggestion } from './types'

const DENSE_SECTION_TYPES = new Set([
  'features',
  'pricing',
  'testimonials',
  'reviews',
  'grid',
  'cards',
  'product_grid',
  'featured_products',
  'categories',
  'portfolio_gallery',
])

export function analyzeLayoutBalance(context: DesignQualityAnalysisContext): DesignQualityAnalyzerResult {
  const issues: string[] = []
  const suggestions: DesignQualitySuggestion[] = []
  let score = 100

  let denseRunLength = 0
  let previousType: string | null = null

  context.sections.forEach((sectionContext) => {
    const normalizedType = sectionContext.sectionType

    if (normalizedType === previousType && normalizedType !== 'hero' && normalizedType !== 'header' && normalizedType !== 'footer') {
      issues.push(`duplicate stacked ${normalizedType} sections flatten the page rhythm`)
      score -= 8
    }
    previousType = normalizedType

    if (DENSE_SECTION_TYPES.has(normalizedType)) {
      denseRunLength += 1
      if (denseRunLength >= 3) {
        issues.push('too many similar dense sections are stacked together')
        if (sectionContext.variantOptions.length > 1) {
          suggestions.push({
            category: 'layoutBalance',
            target: sectionContext.nodeId,
            sectionIndex: sectionContext.sectionIndex,
            action: 'swap_variant',
            detail: 'Swap to a cleaner layout to break up repetitive dense sections.',
          })
        }
        score -= 8
      }
    } else {
      denseRunLength = 0
    }
  })

  const coreSections = context.sections.filter((section) => section.sectionType !== 'header' && section.sectionType !== 'footer')
  if (
    coreSections.length <= 3
    && coreSections.map((section) => section.sectionType).join('|') === 'hero|features|cta'
  ) {
    issues.push('page rhythm feels flat and too template-like')
    const middleSection = coreSections[1] ?? null
    if (middleSection?.variantOptions.length && middleSection.variantOptions.length > 1) {
      suggestions.push({
        category: 'layoutBalance',
        target: middleSection.nodeId,
        sectionIndex: middleSection.sectionIndex,
        action: 'swap_variant',
        detail: 'Use a more distinctive middle section variant to add rhythm.',
      })
    }
    score -= 10
  }

  const footerIndex = context.sections.findIndex((section) => section.sectionType === 'footer')
  if (footerIndex > 0) {
    const denseSectionsAfterMidpoint = context.sections
      .slice(Math.floor(context.sections.length / 2), footerIndex)
      .filter((section) => DENSE_SECTION_TYPES.has(section.sectionType))
      .length

    if (denseSectionsAfterMidpoint >= 3) {
      issues.push('visual weight is concentrated too heavily in the lower half of the page')
      const ctaSection = context.sections.find((section) => section.sectionType === 'cta')
      if (ctaSection) {
        suggestions.push({
          category: 'layoutBalance',
          target: ctaSection.nodeId,
          sectionIndex: ctaSection.sectionIndex,
          action: 'promote_cta_section',
          detail: 'Move the CTA earlier to rebalance the page rhythm.',
        })
      }
      score -= 8
    }
  }

  const lightSections = context.sections.filter((section) => !DENSE_SECTION_TYPES.has(section.sectionType)).length
  if (lightSections <= 2 && context.sections.length >= 5) {
    issues.push('page rhythm is too dense from top to bottom')
    score -= 6
  }

  return {
    score: Math.max(30, score),
    issues,
    suggestions,
  }
}
