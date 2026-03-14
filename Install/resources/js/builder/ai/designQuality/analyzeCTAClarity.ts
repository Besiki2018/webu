import type { DesignQualityAnalysisContext, DesignQualityAnalyzerResult, DesignQualitySuggestion } from './types'
import { getValueAtPath } from '../../state/sectionProps'

const GENERIC_CTA_PATTERN = /\b(learn more|get started|submit|click here|contact us|read more)\b/i

function resolveCtaLabel(section: DesignQualityAnalysisContext['sections'][number]): string | null {
  const candidates = [
    getValueAtPath(section.resolvedProps, 'buttonText'),
    getValueAtPath(section.resolvedProps, 'buttonLabel'),
    getValueAtPath(section.resolvedProps, 'ctaLabel'),
    getValueAtPath(section.resolvedProps, 'submit_label'),
  ]

  for (const candidate of candidates) {
    if (typeof candidate === 'string' && candidate.trim() !== '') {
      return candidate.trim()
    }
  }

  return null
}

export function analyzeCTAClarity(context: DesignQualityAnalysisContext): DesignQualityAnalyzerResult {
  const issues: string[] = []
  const suggestions: DesignQualitySuggestion[] = []
  let score = 100

  const ctaSections = context.sections.filter((section) => (
    section.sectionType === 'hero'
    || section.sectionType === 'cta'
    || section.sectionType === 'contact'
    || section.sectionType === 'appointment_booking'
    || section.sectionType === 'reservation'
  ))

  if (ctaSections.length === 0) {
    issues.push('page is missing a clear next action')
    return {
      score: 50,
      issues,
      suggestions,
    }
  }

  ctaSections.forEach((sectionContext) => {
    const label = resolveCtaLabel(sectionContext)
    if (!label) {
      issues.push(`${sectionContext.sectionType} is missing an actionable CTA`)
      suggestions.push({
        category: 'ctaClarity',
        target: sectionContext.nodeId,
        sectionIndex: sectionContext.sectionIndex,
        action: 'strengthen_cta_label',
        detail: 'Add a clearer CTA label tied to the user intent.',
      })
      score -= 10
      return
    }

    if (GENERIC_CTA_PATTERN.test(label)) {
      issues.push(`${sectionContext.sectionType} CTA text is too generic`)
      suggestions.push({
        category: 'ctaClarity',
        target: sectionContext.nodeId,
        sectionIndex: sectionContext.sectionIndex,
        action: 'strengthen_cta_label',
        detail: 'Replace generic CTA copy with a more specific action.',
      })
      score -= 8
    }
  })

  const primaryCta = ctaSections[0]
  if (primaryCta && primaryCta.sectionIndex > 3) {
    issues.push('primary CTA appears too late in the page flow')
    suggestions.push({
      category: 'ctaClarity',
      target: primaryCta.nodeId,
      sectionIndex: primaryCta.sectionIndex,
      action: 'promote_cta_section',
      detail: 'Move the first CTA closer to the decision moment.',
    })
    score -= 8
  }

  return {
    score: Math.max(45, score),
    issues,
    suggestions,
  }
}
