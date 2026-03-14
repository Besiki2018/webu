import type { DesignQualityAnalysisContext, DesignQualityAnalyzerResult, DesignQualitySuggestion } from './types'
import { getValueAtPath } from '../../state/sectionProps'

const GENERIC_CTA_PATTERN = /\b(learn more|get started|submit|click here|contact us|read more)\b/i
const WEAK_CTA_COLOR = /^(#fff(?:fff)?|#f8fafc|#f9fafb)$/i

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

function resolveColor(section: DesignQualityAnalysisContext['sections'][number], ...paths: string[]): string | null {
  for (const path of paths) {
    const value = getValueAtPath(section.resolvedProps, path)
    if (typeof value === 'string' && value.trim() !== '') {
      return value.trim()
    }
  }

  return null
}

function supportsField(section: DesignQualityAnalysisContext['sections'][number], ...paths: string[]): string | null {
  return paths.find((path) => section.schemaFieldPaths.has(path)) ?? null
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
    const backgroundPath = supportsField(sectionContext, 'backgroundColor', 'background_color')
    const textPath = supportsField(sectionContext, 'textColor', 'text_color')
    const backgroundColor = resolveColor(sectionContext, 'backgroundColor', 'background_color')
    const textColor = resolveColor(sectionContext, 'textColor', 'text_color')
    if (!label) {
      issues.push(`${sectionContext.sectionType} is missing an actionable CTA`)
      suggestions.push({
        category: 'ctaClarity',
        target: sectionContext.nodeId,
        sectionIndex: sectionContext.sectionIndex,
        action: 'strengthen_cta_label',
        detail: 'Add a clearer CTA label tied to the user intent.',
      })
      if (backgroundPath) {
        suggestions.push({
          category: 'ctaClarity',
          target: sectionContext.nodeId,
          sectionIndex: sectionContext.sectionIndex,
          action: 'set_background_color',
          value: '#1d4ed8',
          path: backgroundPath,
          detail: 'Give the CTA a stronger accent surface.',
        })
      }
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

    if (
      (sectionContext.sectionType === 'cta' || sectionContext.sectionType === 'hero')
      && backgroundPath
      && textPath
      && (!backgroundColor || WEAK_CTA_COLOR.test(backgroundColor))
      && (!textColor || WEAK_CTA_COLOR.test(textColor))
    ) {
      issues.push(`${sectionContext.sectionType} CTA lacks visual prominence`)
      suggestions.push({
        category: 'ctaClarity',
        target: sectionContext.nodeId,
        sectionIndex: sectionContext.sectionIndex,
        action: 'set_background_color',
        value: '#1d4ed8',
        path: backgroundPath,
        detail: 'Strengthen CTA prominence with a clearer accent surface.',
      })
      suggestions.push({
        category: 'ctaClarity',
        target: sectionContext.nodeId,
        sectionIndex: sectionContext.sectionIndex,
        action: 'set_text_color',
        value: '#ffffff',
        path: textPath,
        detail: 'Increase CTA legibility against the accent background.',
      })
      if (sectionContext.variantOptions.length > 1) {
        suggestions.push({
          category: 'ctaClarity',
          target: sectionContext.nodeId,
          sectionIndex: sectionContext.sectionIndex,
          action: 'swap_variant',
          detail: 'Switch to a more assertive CTA variant.',
        })
      }
      score -= 9
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
