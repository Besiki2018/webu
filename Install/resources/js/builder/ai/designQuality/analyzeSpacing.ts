import type { DesignQualityAnalysisContext, DesignQualityAnalyzerResult, DesignQualitySuggestion } from './types'
import { getValueAtPath } from '../../state/sectionProps'

function asNumber(value: unknown): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value
  }

  if (typeof value === 'string') {
    const numeric = Number.parseFloat(value)
    return Number.isFinite(numeric) ? numeric : null
  }

  return null
}

function getVerticalPadding(context: DesignQualityAnalysisContext['sections'][number]): number {
  const candidates = [
    getValueAtPath(context.props, 'padding_y'),
    getValueAtPath(context.props, 'advanced.padding_top'),
    getValueAtPath(context.props, 'advanced.padding_bottom'),
    getValueAtPath(context.defaultProps, 'padding_y'),
    getValueAtPath(context.defaultProps, 'advanced.padding_top'),
    getValueAtPath(context.defaultProps, 'advanced.padding_bottom'),
  ]

  for (const candidate of candidates) {
    const numeric = asNumber(candidate)
    if (numeric !== null) {
      return numeric
    }
  }

  return 0
}

function getBodyLength(section: DesignQualityAnalysisContext['sections'][number]): number {
  const candidates = [
    getValueAtPath(section.resolvedProps, 'subtitle'),
    getValueAtPath(section.resolvedProps, 'description'),
    getValueAtPath(section.resolvedProps, 'body'),
    getValueAtPath(section.resolvedProps, 'text'),
  ]

  for (const candidate of candidates) {
    if (typeof candidate === 'string' && candidate.trim() !== '') {
      return candidate.trim().length
    }
  }

  return 0
}

function getRepeaterItemCount(section: DesignQualityAnalysisContext['sections'][number]): number {
  const items = getValueAtPath(section.resolvedProps, 'items')
  return Array.isArray(items) ? items.length : 0
}

function supportsField(context: DesignQualityAnalysisContext['sections'][number], path: string): boolean {
  return context.schemaFieldPaths.has(path)
}

function createSpacingSuggestion(
  context: DesignQualityAnalysisContext['sections'][number],
  value: number,
  detail: string,
): DesignQualitySuggestion {
  return {
    category: 'spacing',
    target: context.nodeId,
    sectionIndex: context.sectionIndex,
    action: 'increase_padding_y',
    value,
    path: supportsField(context, 'padding_y') ? 'padding_y' : 'advanced.padding_top',
    detail,
  }
}

export function analyzeSpacing(context: DesignQualityAnalysisContext): DesignQualityAnalyzerResult {
  const issues: string[] = []
  const suggestions: DesignQualitySuggestion[] = []
  let score = 100
  let overcrowdedRuns = 0

  context.sections.forEach((sectionContext, index) => {
    const padding = getVerticalPadding(sectionContext)
    const bodyLength = getBodyLength(sectionContext)
    const itemCount = getRepeaterItemCount(sectionContext)
    const minimumPadding = sectionContext.sectionType === 'hero'
      ? 96
      : sectionContext.sectionType === 'cta'
        ? 72
        : sectionContext.sectionType === 'footer'
          ? 48
          : 56
    const maximumPadding = sectionContext.sectionType === 'hero'
      ? 140
      : sectionContext.sectionType === 'cta'
        ? 120
        : sectionContext.sectionType === 'footer'
          ? 80
          : 112

    if (padding < minimumPadding) {
      issues.push(`${sectionContext.sectionType} padding too small`)
      suggestions.push(createSpacingSuggestion(sectionContext, minimumPadding, `Increase ${sectionContext.sectionType} breathing room.`))
      score -= sectionContext.sectionType === 'hero' ? 12 : 8
      overcrowdedRuns += 1
    } else {
      overcrowdedRuns = 0
    }

    if (padding > maximumPadding) {
      issues.push(`${sectionContext.sectionType} spacing feels too loose`)
      suggestions.push({
        category: 'spacing',
        target: sectionContext.nodeId,
        sectionIndex: sectionContext.sectionIndex,
        action: 'decrease_padding_y',
        value: maximumPadding,
        path: supportsField(sectionContext, 'padding_y') ? 'padding_y' : 'advanced.padding_top',
        detail: `Tighten ${sectionContext.sectionType} spacing to avoid empty vertical gaps.`,
      })
      score -= 6
    }

    if (bodyLength > 140 && padding < Math.max(minimumPadding, 64)) {
      issues.push(`${sectionContext.sectionType} text blocks sit too close to the edges`)
      suggestions.push(createSpacingSuggestion(
        sectionContext,
        Math.max(minimumPadding, 72),
        'Add more vertical breathing room around long-form text.',
      ))
      score -= 6
    }

    const previous = context.sections[index - 1]
    if (previous) {
      const previousPadding = getVerticalPadding(previous)
      if (padding > 0 && previousPadding > 0 && Math.abs(padding - previousPadding) >= 48) {
        issues.push(`${previous.sectionType} and ${sectionContext.sectionType} spacing feels inconsistent`)
        suggestions.push(createSpacingSuggestion(sectionContext, Math.max(56, Math.min(96, previousPadding)), 'Smooth section rhythm between adjacent sections.'))
        score -= 5
      }

      if (padding <= 40 && previousPadding <= 40) {
        issues.push(`${previous.sectionType} and ${sectionContext.sectionType} feel overcrowded`)
        suggestions.push(createSpacingSuggestion(sectionContext, 64, 'Add breathing room between dense stacked sections.'))
        score -= 7
      }
    }

    if (overcrowdedRuns >= 2) {
      issues.push('multiple consecutive sections feel compressed')
      score -= 5
    }

    if ((sectionContext.sectionType === 'features' || sectionContext.sectionType === 'testimonials') && supportsField(sectionContext, 'gap')) {
      const gap = asNumber(getValueAtPath(sectionContext.props, 'gap') ?? getValueAtPath(sectionContext.defaultProps, 'gap'))
      if (gap !== null && gap < 24) {
        issues.push(`${sectionContext.sectionType} cards gap inconsistent`)
        suggestions.push({
          category: 'spacing',
          target: sectionContext.nodeId,
          sectionIndex: sectionContext.sectionIndex,
          action: 'set_gap',
          value: 24,
          path: 'gap',
          detail: 'Normalize card spacing for cleaner scanning.',
        })
        score -= 4
      }
    }

    if (
      itemCount >= 4
      && !supportsField(sectionContext, 'gap')
      && sectionContext.variantOptions.length > 1
      && (sectionContext.sectionType === 'features' || sectionContext.sectionType === 'testimonials' || sectionContext.sectionType === 'product_grid')
    ) {
      issues.push(`${sectionContext.sectionType} layout feels crowded for the amount of content`)
      suggestions.push({
        category: 'spacing',
        target: sectionContext.nodeId,
        sectionIndex: sectionContext.sectionIndex,
        action: 'swap_variant',
        detail: 'Switch to a roomier variant for dense card content.',
      })
      score -= 5
    }
  })

  return {
    score: Math.max(30, score),
    issues,
    suggestions,
  }
}
