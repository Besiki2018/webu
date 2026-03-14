import type { BuilderComponentInstance } from '../../core/types'
import type { AiComponentCatalogEntry, AiComponentCatalogIndex } from '../componentCatalog'
import type { ProjectBlueprint } from '../blueprintTypes'
import type { AiSitePlanSection } from '../sitePlanner'

export type DesignQualityCategory =
  | 'spacing'
  | 'typography'
  | 'contrast'
  | 'hierarchy'
  | 'layoutBalance'
  | 'ctaClarity'

export type DesignQualityAction =
  | 'increase_padding_y'
  | 'set_gap'
  | 'set_max_width'
  | 'set_text_color'
  | 'set_background_color'
  | 'strengthen_cta_label'
  | 'promote_cta_section'
  | 'swap_variant'

export interface DesignQualitySuggestion {
  category: DesignQualityCategory
  target: string
  action: DesignQualityAction
  value?: unknown
  path?: string
  detail?: string
  sectionIndex?: number
}

export interface DesignQualityAnalyzerResult {
  score: number
  issues: string[]
  suggestions: DesignQualitySuggestion[]
}

export interface DesignQualityCategoryScores {
  spacing: number
  typography: number
  contrast: number
  hierarchy: number
  layoutBalance: number
  ctaClarity: number
}

export interface DesignQualityReport {
  threshold: number
  overallScore: number
  initialOverallScore: number
  categoryScores: DesignQualityCategoryScores
  initialCategoryScores: DesignQualityCategoryScores
  issues: string[]
  improvements: DesignQualitySuggestion[]
  improvementsApplied: string[]
  autoImproved: boolean
}

export interface DesignQualitySectionContext {
  nodeId: string
  node: BuilderComponentInstance
  section: AiSitePlanSection
  sectionIndex: number
  sectionType: string
  componentKey: string
  variant: string | null
  props: Record<string, unknown>
  resolvedProps: Record<string, unknown>
  defaultProps: Record<string, unknown>
  schemaFieldPaths: Set<string>
  variantOptions: string[]
  catalogEntry: AiComponentCatalogEntry | null
}

export interface BuildDesignQualityReportInput {
  blueprint: ProjectBlueprint
  siteSections: AiSitePlanSection[]
  tree: BuilderComponentInstance[]
  registryIndex: AiComponentCatalogIndex
  threshold?: number
}

export interface DesignQualityAnalysisContext {
  sections: DesignQualitySectionContext[]
  tree: BuilderComponentInstance[]
  blueprint: BuildDesignQualityReportInput['blueprint']
  threshold: number
}

export interface ImproveDesignFromReportInput {
  blueprint: ProjectBlueprint
  siteSections: AiSitePlanSection[]
  tree: BuilderComponentInstance[]
  registryIndex: AiComponentCatalogIndex
  report: DesignQualityReport
}

export interface ImproveDesignFromReportResult {
  siteSections: AiSitePlanSection[]
  changesApplied: string[]
}
