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

function getPrimaryText(section: DesignQualityAnalysisContext['sections'][number]): string {
  const candidates = [
    getValueAtPath(section.resolvedProps, 'title'),
    getValueAtPath(section.resolvedProps, 'headline'),
    getValueAtPath(section.resolvedProps, 'heading'),
  ]

  for (const candidate of candidates) {
    if (typeof candidate === 'string' && candidate.trim() !== '') {
      return candidate.trim()
    }
  }

  return ''
}

function getSecondaryText(section: DesignQualityAnalysisContext['sections'][number]): string {
  const candidates = [
    getValueAtPath(section.resolvedProps, 'subtitle'),
    getValueAtPath(section.resolvedProps, 'description'),
    getValueAtPath(section.resolvedProps, 'body'),
    getValueAtPath(section.resolvedProps, 'text'),
  ]

  for (const candidate of candidates) {
    if (typeof candidate === 'string' && candidate.trim() !== '') {
      return candidate.trim()
    }
  }

  return ''
}

function supportsField(section: DesignQualityAnalysisContext['sections'][number], path: string): boolean {
  return section.schemaFieldPaths.has(path)
}

function resolveMaxWidth(section: DesignQualityAnalysisContext['sections'][number]): number | null {
  return asNumber(getValueAtPath(section.resolvedProps, 'max_width') ?? getValueAtPath(section.defaultProps, 'max_width'))
}

export function analyzeTypography(context: DesignQualityAnalysisContext): DesignQualityAnalyzerResult {
  const issues: string[] = []
  const suggestions: DesignQualitySuggestion[] = []
  const observedSizes = new Set<number>()
  let score = 100

  context.sections.forEach((sectionContext) => {
    const title = getPrimaryText(sectionContext)
    const body = getSecondaryText(sectionContext)
    const titleSize = asNumber(getValueAtPath(sectionContext.resolvedProps, 'titleSize') ?? getValueAtPath(sectionContext.resolvedProps, 'headingSize'))
    const bodySize = asNumber(getValueAtPath(sectionContext.resolvedProps, 'bodySize') ?? getValueAtPath(sectionContext.resolvedProps, 'textSize'))

    if (titleSize !== null) {
      observedSizes.add(titleSize)
    }
    if (bodySize !== null) {
      observedSizes.add(bodySize)
    }

    if (sectionContext.sectionType === 'hero') {
      if (title.split(/\s+/).filter(Boolean).length < 4) {
        issues.push('hero title too small in narrative scope')
        if (sectionContext.variantOptions.length > 1) {
          suggestions.push({
            category: 'typography',
            target: sectionContext.nodeId,
            sectionIndex: sectionContext.sectionIndex,
            action: 'swap_variant',
            detail: 'Switch to a hero variant with stronger headline hierarchy.',
          })
        }
        score -= 6
      }

      if (body.length > 170 && supportsField(sectionContext, 'max_width')) {
        issues.push('hero body text too wide')
        suggestions.push({
          category: 'typography',
          target: sectionContext.nodeId,
          sectionIndex: sectionContext.sectionIndex,
          action: 'set_max_width',
          value: 760,
          path: 'max_width',
          detail: 'Reduce hero line length for cleaner hierarchy.',
        })
        score -= 8
      } else if (body.length > 170 && sectionContext.variantOptions.length > 1) {
        issues.push('hero body text feels visually dense')
        suggestions.push({
          category: 'typography',
          target: sectionContext.nodeId,
          sectionIndex: sectionContext.sectionIndex,
          action: 'swap_variant',
          detail: 'Use a cleaner hero variant with tighter text measure.',
        })
        score -= 6
      }
    }

    const maxWidth = resolveMaxWidth(sectionContext)
    if (body.length > 160 && supportsField(sectionContext, 'max_width') && (maxWidth === null || maxWidth > 820)) {
      issues.push(`${sectionContext.sectionType} body text too dense`)
      suggestions.push({
        category: 'typography',
        target: sectionContext.nodeId,
        sectionIndex: sectionContext.sectionIndex,
        action: 'set_max_width',
        value: sectionContext.sectionType === 'hero' ? 760 : 720,
        path: 'max_width',
        detail: 'Reduce paragraph width for readability.',
      })
      score -= 6
    } else if (body.length > 180 && sectionContext.variantOptions.length > 1) {
      issues.push(`${sectionContext.sectionType} copy block feels too heavy for its layout`)
      suggestions.push({
        category: 'typography',
        target: sectionContext.nodeId,
        sectionIndex: sectionContext.sectionIndex,
        action: 'swap_variant',
        detail: 'Use a lighter variant with cleaner text hierarchy.',
      })
      score -= 5
    }

    if (title !== '' && body !== '' && title.toLowerCase() === body.toLowerCase()) {
      issues.push(`${sectionContext.sectionType} is missing hierarchy between title and body`)
      if (supportsField(sectionContext, 'max_width')) {
        suggestions.push({
          category: 'typography',
          target: sectionContext.nodeId,
          sectionIndex: sectionContext.sectionIndex,
          action: 'set_max_width',
          value: 720,
          path: 'max_width',
          detail: 'Tighten text measure so repeated messaging feels less flat.',
        })
      }
      score -= 7
    }
  })

  if (observedSizes.size > 4) {
    issues.push('too many font sizes used across the page')
    score -= 8
  }

  return {
    score: Math.max(35, score),
    issues,
    suggestions,
  }
}
