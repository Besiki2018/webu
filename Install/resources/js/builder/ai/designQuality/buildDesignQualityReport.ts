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
  DesignQualityCategoryScores,
  DesignQualityReport,
  DesignQualitySectionContext,
} from './types'

const DEFAULT_THRESHOLD = 80

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

  return merged
}

function computeOverallScore(scores: DesignQualityCategoryScores): number {
  return Math.round((
    scores.spacing
    + scores.typography
    + scores.contrast
    + scores.hierarchy
    + scores.layoutBalance
    + scores.ctaClarity
  ) / 6)
}

export function buildDesignQualityReport(input: BuildDesignQualityReportInput): DesignQualityReport {
  const threshold = input.threshold ?? DEFAULT_THRESHOLD
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
