import type {
  BlueprintGenerationLogEntry,
  BlueprintGenerationStatus,
  BlueprintGenerationStep,
  BuildGenerationDiagnostics,
} from './blueprintTypes'

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
  selectedProjectType?: string | null
  selectedBusinessType?: string | null
  selectedSections?: string[]
  selectedComponentKeys?: string[]
  fallbackUsed?: boolean
  failedStep?: BlueprintGenerationStep | null
  rootCause?: string | null
  events?: BlueprintGenerationLogEntry[]
}): BuildGenerationDiagnostics {
  return {
    prompt: input.prompt ?? null,
    selectedProjectType: input.selectedProjectType ?? null,
    selectedBusinessType: input.selectedBusinessType ?? null,
    selectedSections: input.selectedSections ?? [],
    selectedComponentKeys: input.selectedComponentKeys ?? [],
    fallbackUsed: input.fallbackUsed === true,
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
