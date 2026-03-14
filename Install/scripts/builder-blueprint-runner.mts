#!/usr/bin/env node

import fs from 'node:fs'
import process from 'node:process'
import { buildSiteFromBlueprint } from '../resources/js/builder/ai/buildSiteFromBlueprint'
import { createBlueprint } from '../resources/js/builder/ai/createBlueprint'
import { isGenerationTraceError } from '../resources/js/builder/ai/generationTracing'
import { isProjectType, type ProjectType } from '../resources/js/builder/projectTypes'

interface RunnerInput {
  prompt?: string
  projectType?: string | null
  brandName?: string | null
}

interface TraceEvent {
  step?: unknown
  status?: unknown
  message?: unknown
}

interface DiagnosticsLike {
  selectedProjectType?: unknown
  selectedBusinessType?: unknown
  selectedSections?: unknown
  selectedComponentKeys?: unknown
  fallbackUsed?: unknown
  failedStep?: unknown
  rootCause?: unknown
}

function readInput(): RunnerInput {
  const raw = fs.readFileSync(0, 'utf8')
  const normalized = typeof raw === 'string' ? raw.trim() : ''

  if (normalized === '') {
    return {}
  }

  try {
    const decoded = JSON.parse(normalized)
    return typeof decoded === 'object' && decoded !== null ? decoded as RunnerInput : {}
  } catch {
    return {}
  }
}

function normalizeProjectType(value: string | null | undefined): ProjectType | null {
  if (typeof value !== 'string') {
    return null
  }

  const normalized = value.trim().toLowerCase()

  return isProjectType(normalized) ? normalized : null
}

function withSilentConsole<T>(callback: () => T): T {
  const originalInfo = console.info
  const originalWarn = console.warn
  const originalError = console.error

  console.info = () => undefined
  console.warn = () => undefined
  console.error = () => undefined

  try {
    return callback()
  } finally {
    console.info = originalInfo
    console.warn = originalWarn
    console.error = originalError
  }
}

function compactGenerationLog(log: unknown): Array<{ step: string; status: string; message: string }> {
  if (!Array.isArray(log)) {
    return []
  }

  return log.flatMap((entry) => {
    if (typeof entry !== 'object' || entry === null) {
      return []
    }

    const traceEntry = entry as TraceEvent
    const step = typeof traceEntry.step === 'string' ? traceEntry.step : ''
    const status = typeof traceEntry.status === 'string' ? traceEntry.status : 'info'
    const message = typeof traceEntry.message === 'string' ? traceEntry.message : ''

    if (step === '' && message === '') {
      return []
    }

    return [{ step, status, message }]
  })
}

function compactDiagnostics(value: unknown): Record<string, unknown> | null {
  if (typeof value !== 'object' || value === null) {
    return null
  }

  const diagnostics = value as DiagnosticsLike

  return {
    selectedProjectType: typeof diagnostics.selectedProjectType === 'string' ? diagnostics.selectedProjectType : null,
    selectedBusinessType: typeof diagnostics.selectedBusinessType === 'string' ? diagnostics.selectedBusinessType : null,
    selectedSections: Array.isArray(diagnostics.selectedSections)
      ? diagnostics.selectedSections.filter((section): section is string => typeof section === 'string')
      : [],
    selectedComponentKeys: Array.isArray(diagnostics.selectedComponentKeys)
      ? diagnostics.selectedComponentKeys.filter((section): section is string => typeof section === 'string')
      : [],
    fallbackUsed: diagnostics.fallbackUsed === true,
    failedStep: typeof diagnostics.failedStep === 'string' ? diagnostics.failedStep : null,
    rootCause: typeof diagnostics.rootCause === 'string' ? diagnostics.rootCause : null,
  }
}

const input = readInput()
const prompt = typeof input.prompt === 'string' ? input.prompt.trim() : ''

if (prompt === '') {
  process.stdout.write(JSON.stringify({
    ok: false,
    error: 'prompt_required',
  }))
  process.exit(0)
}

try {
  const builderProjectType = normalizeProjectType(input.projectType)
  const blueprint = withSilentConsole(() => createBlueprint({
    prompt,
    projectType: input.projectType ?? null,
  }))
  const result = withSilentConsole(() => buildSiteFromBlueprint({
    prompt,
    blueprint,
    brandName: typeof input.brandName === 'string' && input.brandName.trim() !== ''
      ? input.brandName.trim()
      : null,
    builderProjectTypeOverride: builderProjectType,
    generationMode: 'blueprint',
  }))

  process.stdout.write(JSON.stringify({
    ok: true,
    blueprint: result.blueprint,
    projectType: result.projectType,
    sitePlan: result.sitePlan,
    generationLog: compactGenerationLog(result.generationLog),
    diagnostics: compactDiagnostics(result.diagnostics),
  }))
  process.exit(0)
} catch (error) {
  const message = error instanceof Error ? error.message : 'builder_blueprint_generation_failed'

  process.stdout.write(JSON.stringify({
    ok: false,
    error: message,
    diagnostics: isGenerationTraceError(error) ? error.diagnostics : null,
  }))
  process.exit(0)
}
