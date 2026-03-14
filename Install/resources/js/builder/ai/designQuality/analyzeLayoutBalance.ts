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
        score -= 8
      }
    } else {
      denseRunLength = 0
    }
  })

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

  return {
    score: Math.max(45, score),
    issues,
    suggestions,
  }
}
