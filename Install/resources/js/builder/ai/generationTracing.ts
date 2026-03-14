import type {
  BlueprintGenerationLogEntry,
  BlueprintGenerationStatus,
  BlueprintGenerationStep,
  BuildGenerationDiagnostics,
  BuildGenerationStageTimingsMs,
} from './blueprintTypes'
import type { DesignQualityReport } from './designQuality/types'

export function createEmptyStageTimings(): BuildGenerationStageTimingsMs {
  return {
    layoutPlanning: 0,
    componentSelection: 0,
    contentGeneration: 0,
    treeAssembly: 0,
    designOptimization: 0,
    validation: 0,
    previewRendering: null,
  }
}

export function createGenerationLogEntry(
  step: BlueprintGenerationStep,
  message: string,
  payload: unknown,
  status: BlueprintGenerationStatus = 'info',
): BlueprintGenerationLogEntry {
  return {
    step,
    status,
    message,
    payload,
  }
}

export function buildGenerationDiagnostics(input: {
  prompt?: string | null
  generationMode?: BuildGenerationDiagnostics['generationMode']
  selectedProjectType?: string | null
  selectedBusinessType?: string | null
  detectedDomain?: BuildGenerationDiagnostics['detectedDomain']
  selectedLayoutTemplate?: string | null
  selectedSectionTypes?: string[]
  finalSections?: string[]
  selectedSections?: string[]
  selectedComponentKeys?: string[]
  validationPassed?: boolean
  emergencyFallbackUsed?: boolean
  fallbackUsed?: boolean
  designQualityReport?: DesignQualityReport | null
  stageTimingsMs?: Partial<BuildGenerationStageTimingsMs>
  failedStep?: BlueprintGenerationStep | null
  rootCause?: string | null
  events?: BlueprintGenerationLogEntry[]
}): BuildGenerationDiagnostics {
  const selectedSectionTypes = input.selectedSectionTypes ?? input.selectedSections ?? []
  const emergencyFallbackUsed = input.emergencyFallbackUsed ?? input.fallbackUsed ?? false
  const finalSections = input.finalSections ?? selectedSectionTypes

  return {
    prompt: input.prompt ?? null,
    generationMode: input.generationMode ?? (emergencyFallbackUsed ? 'emergency-fallback' : 'blueprint'),
    selectedProjectType: input.selectedProjectType ?? null,
    selectedBusinessType: input.selectedBusinessType ?? null,
    detectedDomain: input.detectedDomain ?? null,
    selectedLayoutTemplate: input.selectedLayoutTemplate ?? null,
    selectedSectionTypes,
    finalSections,
    validationPassed: input.validationPassed === true,
    emergencyFallbackUsed,
    selectedSections: selectedSectionTypes,
    selectedComponentKeys: input.selectedComponentKeys ?? [],
    fallbackUsed: emergencyFallbackUsed,
    designQualityReport: input.designQualityReport ?? null,
    stageTimingsMs: {
      ...createEmptyStageTimings(),
      ...(input.stageTimingsMs ?? {}),
    },
    failedStep: input.failedStep ?? null,
    rootCause: input.rootCause ?? null,
    events: input.events ? [...input.events] : [],
  }
}

export function appendGenerationDiagnosticsEvent(
  diagnostics: BuildGenerationDiagnostics | null,
  entry: BlueprintGenerationLogEntry,
  overrides: Partial<Pick<BuildGenerationDiagnostics, 'failedStep' | 'rootCause'>> = {},
): BuildGenerationDiagnostics | null {
  if (!diagnostics) {
    return diagnostics
  }

  return {
    ...diagnostics,
    failedStep: overrides.failedStep ?? diagnostics.failedStep,
    rootCause: overrides.rootCause ?? diagnostics.rootCause,
    events: [...diagnostics.events, entry],
  }
}

export class GenerationTraceError extends Error {
  diagnostics: BuildGenerationDiagnostics

  constructor(message: string, diagnostics: BuildGenerationDiagnostics) {
    super(message)
    this.name = 'GenerationTraceError'
    this.diagnostics = diagnostics
  }
}

export function isGenerationTraceError(error: unknown): error is GenerationTraceError {
  return error instanceof GenerationTraceError
}
