import type { DesignQualityAnalysisContext, DesignQualityAnalyzerResult, DesignQualitySuggestion } from './types'
import { getValueAtPath } from '../../state/sectionProps'

function hasPrimaryAction(section: DesignQualityAnalysisContext['sections'][number]): boolean {
  const candidates = [
    getValueAtPath(section.resolvedProps, 'buttonText'),
    getValueAtPath(section.resolvedProps, 'buttonLabel'),
    getValueAtPath(section.resolvedProps, 'ctaLabel'),
    getValueAtPath(section.resolvedProps, 'submit_label'),
  ]

  return candidates.some((candidate) => typeof candidate === 'string' && candidate.trim() !== '')
}

export function analyzeHierarchy(context: DesignQualityAnalysisContext): DesignQualityAnalyzerResult {
  const issues: string[] = []
  const suggestions: DesignQualitySuggestion[] = []
  let score = 100

  const heroIndex = context.sections.findIndex((section) => section.sectionType === 'hero')
  const ctaIndex = context.sections.findIndex((section) => section.sectionType === 'cta' || section.sectionType === 'contact' || section.sectionType === 'appointment_booking' || section.sectionType === 'reservation')
  const heroSection = heroIndex >= 0 ? context.sections[heroIndex] : null

  if (heroSection) {
    if (!hasPrimaryAction(heroSection)) {
      issues.push('hero lacks a dominant call to action')
      suggestions.push({
        category: 'hierarchy',
        target: heroSection.nodeId,
        sectionIndex: heroSection.sectionIndex,
        action: 'strengthen_cta_label',
        detail: 'Give the hero an obvious primary action.',
      })
      score -= 12
    }
  } else {
    issues.push('hero lacks dominance')
    score -= 20
  }

  if (ctaIndex === -1) {
    issues.push('page has no strong CTA near the decision point')
    score -= 10
  } else if (ctaIndex > Math.max(4, Math.floor(context.sections.length * 0.7))) {
    const ctaSection = context.sections[ctaIndex]
    issues.push('CTA buried below too much content')
    suggestions.push({
      category: 'hierarchy',
      target: ctaSection.nodeId,
      sectionIndex: ctaSection.sectionIndex,
      action: 'promote_cta_section',
      detail: 'Move the CTA closer to the decision point.',
    })
    score -= 10
  }

  const testimonialIndex = context.sections.findIndex((section) => section.sectionType === 'testimonials' || section.sectionType === 'reviews')
  if (heroIndex >= 0 && testimonialIndex > 0 && testimonialIndex < heroIndex + 2) {
    issues.push('testimonial content competes with hero too early')
    score -= 6
  }

  return {
    score: Math.max(45, score),
    issues,
    suggestions,
  }
}
