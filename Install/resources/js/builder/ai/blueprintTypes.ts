import type { AiComponentLayoutType } from './componentCatalog'

export const BLUEPRINT_PROJECT_TYPES = [
  'landing',
  'saas',
  'ecommerce',
  'business',
  'portfolio',
  'restaurant',
] as const

export type BlueprintProjectType = (typeof BLUEPRINT_PROJECT_TYPES)[number]

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
  | 'sections'
  | 'component_scores'
  | 'components'
  | 'content'
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
  selectedSectionTypes: string[]
  validationPassed: boolean
  emergencyFallbackUsed: boolean
  selectedSections: string[]
  selectedComponentKeys: string[]
  fallbackUsed: boolean
  failedStep: BlueprintGenerationStep | null
  rootCause: string | null
  events: BlueprintGenerationLogEntry[]
}
