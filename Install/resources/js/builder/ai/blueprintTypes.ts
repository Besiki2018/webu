import type { AiComponentLayoutType } from './componentCatalog'
import type { DesignQualityReport } from './designQuality/types'

export const BLUEPRINT_PROJECT_TYPES = [
  'landing',
  'saas',
  'ecommerce',
  'business',
  'portfolio',
  'restaurant',
] as const

export type BlueprintProjectType = (typeof BLUEPRINT_PROJECT_TYPES)[number]
export type LayoutDomain = 'vet_clinic' | 'restaurant' | 'saas' | 'agency' | 'portfolio' | 'ecommerce' | 'unknown'

export interface ProjectBlueprintRestrictions {
  noPricing?: boolean
  noTestimonials?: boolean
  onePageOnly?: boolean
}

export interface ProjectBlueprintSection {
  sectionType: 'header' | 'hero' | 'features' | 'pricing' | 'testimonials' | 'faq' | 'cta' | 'footer' | string
  priority: number
  required: boolean
  contentBrief?: Record<string, unknown>
}

export type ProjectBlueprint = {
  projectType: BlueprintProjectType
  businessType: string
  audience: string
  tone: string
  styleKeywords: string[]
  pageGoal: string
  sections: ProjectBlueprintSection[]
  restrictions?: ProjectBlueprintRestrictions
  sourcePrompt?: string
  layoutDiagnostics?: BlueprintLayoutDiagnostics
}

export interface DetectedLayoutDomain {
  domain: LayoutDomain
  confidence: number
  keywords: string[]
}

export interface BlueprintLayoutDiagnostics {
  detectedDomain: DetectedLayoutDomain
  selectedLayoutTemplate: string
  finalSections: string[]
}

export interface NormalizedBlueprintSection extends ProjectBlueprintSection {
  sectionType: string
  priority: number
  required: boolean
  contentBrief?: Record<string, unknown>
}

export interface BlueprintComponentSelection {
  sectionType: string
  priority: number
  required: boolean
  layoutType: AiComponentLayoutType
  componentKey: string
  label: string
  variant?: string
  contentBrief?: Record<string, unknown>
}

export type BlueprintGenerationStep =
  | 'session'
  | 'prompt'
  | 'blueprint'
  | 'layout'
  | 'sections'
  | 'component_scores'
  | 'components'
  | 'content'
  | 'design_quality'
  | 'design_improvement'
  | 'validation'
  | 'tree'
  | 'preview'
  | 'fallback'

export type BlueprintGenerationStatus = 'info' | 'success' | 'failure'
export type BuildGenerationMode = 'blueprint' | 'direct-structure' | 'emergency-fallback'

export interface BlueprintGenerationLogEntry {
  step: BlueprintGenerationStep
  status: BlueprintGenerationStatus
  message: string
  payload: unknown
}

export interface BuildGenerationDiagnostics {
  prompt: string | null
  generationMode: BuildGenerationMode
  selectedProjectType: string | null
  selectedBusinessType: string | null
  detectedDomain: DetectedLayoutDomain | null
  selectedLayoutTemplate: string | null
  selectedSectionTypes: string[]
  finalSections: string[]
  validationPassed: boolean
  emergencyFallbackUsed: boolean
  selectedSections: string[]
  selectedComponentKeys: string[]
  fallbackUsed: boolean
  designQualityReport: DesignQualityReport | null
  failedStep: BlueprintGenerationStep | null
  rootCause: string | null
  events: BlueprintGenerationLogEntry[]
}
