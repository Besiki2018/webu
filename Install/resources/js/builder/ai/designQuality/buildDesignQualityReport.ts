import { getValueAtPath } from '../../state/sectionProps'
import type { BuilderComponentInstance } from '../../core/types'
import { analyzeCTAClarity } from './analyzeCTAClarity'
import { analyzeContrast } from './analyzeContrast'
import { analyzeHierarchy } from './analyzeHierarchy'
import { analyzeLayoutBalance } from './analyzeLayoutBalance'
import { analyzeSpacing } from './analyzeSpacing'
import { analyzeTypography } from './analyzeTypography'
import type {
  BuildDesignQualityReportInput,
  DesignQualityAnalysisContext,
  DesignQualityAnalyzerResult,
  DesignQualityCategory,
  DesignQualityCategoryScores,
  DesignQualityReport,
  DesignQualitySectionContext,
} from './types'

export const DESIGN_QUALITY_MINIMUM_SCORE = 80

function resolveSectionType(componentKey: string, layoutType: string): string {
  return layoutType === 'product-grid' ? 'product_grid' : layoutType || componentKey
}

export function buildSectionContexts(input: BuildDesignQualityReportInput): DesignQualitySectionContext[] {
  return input.siteSections.map((section, index) => {
    const node = input.tree[index] ?? {
      id: `${section.componentKey}-${index + 1}`,
      componentKey: section.componentKey,
      ...(section.variant ? { variant: section.variant } : {}),
      props: section.props ?? {},
    }
    const catalogEntry = input.registryIndex.byKey[section.componentKey] ?? null
    const defaultProps = catalogEntry?.defaultProps ?? {}
    const resolvedProps = {
      ...defaultProps,
      ...(section.props ?? {}),
    }

    return {
      nodeId: node?.id ?? `${section.componentKey}-${index + 1}`,
      node,
      section,
      sectionIndex: index,
      sectionType: resolveSectionType(section.componentKey, section.layoutType),
      componentKey: section.componentKey,
      variant: section.variant ?? (typeof getValueAtPath(resolvedProps, 'variant') === 'string'
        ? String(getValueAtPath(resolvedProps, 'variant'))
        : null),
      props: section.props ?? {},
      resolvedProps,
      defaultProps,
      schemaFieldPaths: new Set(catalogEntry?.propsSchema.map((field) => field.path) ?? []),
      variantOptions: catalogEntry?.variants.map((variant) => variant.id) ?? [],
      catalogEntry,
    }
  })
}

function mergeIssues(...results: DesignQualityAnalyzerResult[]): string[] {
  return [...new Set(results.flatMap((result) => result.issues))]
}

function mergeSuggestions(...results: DesignQualityAnalyzerResult[]): DesignQualityReport['improvements'] {
  const seen = new Set<string>()
  const merged: DesignQualityReport['improvements'] = []
  const categoryPriority: Record<DesignQualityCategory, number> = {
    contrast: 0,
    hierarchy: 1,
    ctaClarity: 2,
    spacing: 3,
    typography: 4,
    layoutBalance: 5,
  }

  results.forEach((result) => {
    result.suggestions.forEach((suggestion) => {
      const key = `${suggestion.category}:${suggestion.action}:${suggestion.target}:${String(suggestion.path ?? '')}:${String(suggestion.value ?? '')}`
      if (seen.has(key)) {
        return
      }
      seen.add(key)
      merged.push(suggestion)
    })
  })

  return merged.sort((left, right) => {
    const priorityDelta = categoryPriority[left.category] - categoryPriority[right.category]
    if (priorityDelta !== 0) {
      return priorityDelta
    }

    const sectionDelta = (left.sectionIndex ?? Number.MAX_SAFE_INTEGER) - (right.sectionIndex ?? Number.MAX_SAFE_INTEGER)
    if (sectionDelta !== 0) {
      return sectionDelta
    }

    return left.target.localeCompare(right.target)
  })
}

function computeOverallScore(scores: DesignQualityCategoryScores): number {
  return Math.round(
    (scores.spacing * 0.17)
    + (scores.typography * 0.17)
    + (scores.contrast * 0.18)
    + (scores.hierarchy * 0.18)
    + (scores.layoutBalance * 0.14)
    + (scores.ctaClarity * 0.16),
  )
}

export function buildDesignQualityReport(input: BuildDesignQualityReportInput): DesignQualityReport {
  const threshold = input.threshold ?? DESIGN_QUALITY_MINIMUM_SCORE
  const context: DesignQualityAnalysisContext = {
    sections: buildSectionContexts(input),
    tree: input.tree,
    blueprint: input.blueprint,
    threshold,
  }

  const spacing = analyzeSpacing(context)
  const typography = analyzeTypography(context)
  const contrast = analyzeContrast(context)
  const hierarchy = analyzeHierarchy(context)
  const layoutBalance = analyzeLayoutBalance(context)
  const ctaClarity = analyzeCTAClarity(context)

  const categoryScores: DesignQualityCategoryScores = {
    spacing: spacing.score,
    typography: typography.score,
    contrast: contrast.score,
    hierarchy: hierarchy.score,
    layoutBalance: layoutBalance.score,
    ctaClarity: ctaClarity.score,
  }

  const overallScore = computeOverallScore(categoryScores)

  return {
    threshold,
    overallScore,
    initialOverallScore: overallScore,
    categoryScores,
    initialCategoryScores: { ...categoryScores },
    issues: mergeIssues(spacing, typography, contrast, hierarchy, layoutBalance, ctaClarity),
    improvements: mergeSuggestions(spacing, typography, contrast, hierarchy, layoutBalance, ctaClarity),
    improvementsApplied: [],
    autoImproved: false,
  }
}
