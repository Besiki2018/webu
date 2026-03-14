import type { BuilderComponentInstance } from '../core/types'
import { normalizeProjectSiteType, type BuilderProject, type ProjectType } from '../projectTypes'
import { treeToSectionsDraft } from '../aiSiteGeneration'
import { sectionPlanToComponentTree } from './siteBuilder'
import { createEmergencyFallbackBlueprint } from './createBlueprint'
import { generateSectionContent } from './generateSectionContent'
import {
  buildGenerationDiagnostics,
  createGenerationLogEntry,
  GenerationTraceError,
  isGenerationTraceError,
} from './generationTracing'
import {
  formatGeneratedSiteValidationIssues,
  type GeneratedSiteValidationMode,
  validateGeneratedSite,
} from './validateGeneratedSite'
import {
  selectSectionsFromBlueprint,
  getEmergencyFallbackSections,
} from './selectSectionsFromBlueprint'
import { selectComponentsFromBlueprint } from './selectComponentsFromBlueprint'
import type {
  BuildGenerationDiagnostics,
  BlueprintGenerationLogEntry,
  BlueprintProjectType,
  ProjectBlueprint,
} from './blueprintTypes'
import type { AiSitePlan } from './sitePlanner'
import type { BuilderSection } from '../visual/treeUtils'

export interface BuildSiteFromBlueprintInput {
  prompt: string
  blueprint: ProjectBlueprint
  brandName?: string | null
  builderProjectTypeOverride?: ProjectType | null
  generationMode?: GeneratedSiteValidationMode
}

export interface BuildSiteFromBlueprintResult {
  blueprint: ProjectBlueprint
  tree: BuilderComponentInstance[]
  sectionsDraft: BuilderSection[]
  projectType: ProjectType
  project: BuilderProject
  available_components: string[]
  sitePlan: AiSitePlan
  generationLog: BlueprintGenerationLogEntry[]
  usedEmergencyFallback: boolean
  diagnostics: BuildGenerationDiagnostics
}

function mapBlueprintProjectTypeToBuilderProjectType(projectType: BlueprintProjectType): ProjectType {
  switch (projectType) {
    case 'saas':
      return 'saas'
    case 'ecommerce':
      return 'ecommerce'
    case 'portfolio':
      return 'portfolio'
    case 'restaurant':
      return 'restaurant'
    case 'business':
      return 'business'
    case 'landing':
    default:
      return 'landing'
  }
}

function mapBlueprintProjectTypeToAiProjectType(projectType: BlueprintProjectType): AiSitePlan['projectType'] {
  switch (projectType) {
    case 'saas':
      return 'saas'
    case 'ecommerce':
      return 'ecommerce'
    case 'portfolio':
      return 'portfolio'
    case 'restaurant':
      return 'restaurant'
    case 'business':
      return 'business'
    case 'landing':
    default:
      return 'landing'
  }
}

function logBlueprintStep(
  logs: BlueprintGenerationLogEntry[],
  step: BlueprintGenerationLogEntry['step'],
  message: string,
  payload: unknown,
  status: BlueprintGenerationLogEntry['status'] = 'info',
): void {
  const entry = createGenerationLogEntry(step, message, payload, status)
  logs.push(entry)
  if (typeof console === 'undefined') {
    return
  }

  if (status === 'failure' && typeof console.error === 'function') {
    console.error(`[builder.ai] ${message}`, payload)
    return
  }

  if (typeof console.info === 'function') {
    console.info(`[builder.ai] ${message}`, payload)
  }
}

export function buildSiteFromBlueprint(input: BuildSiteFromBlueprintInput): BuildSiteFromBlueprintResult {
  const generationLog: BlueprintGenerationLogEntry[] = []
  const resolvedGenerationMode = input.generationMode ?? 'blueprint'
  let effectiveBlueprint = input.blueprint
  let builderProjectType: ProjectType | null = input.builderProjectTypeOverride ?? null
  let selectedSectionTypes: string[] = []
  let selectedComponentKeys: string[] = []
  let usedEmergencyFallback = resolvedGenerationMode === 'emergency-fallback'
  let validationPassed = false

  if (!input.blueprint) {
    throw new GenerationTraceError('project_blueprint_required', buildGenerationDiagnostics({
      prompt: input.prompt,
      generationMode: resolvedGenerationMode,
      validationPassed: false,
      emergencyFallbackUsed: false,
      failedStep: 'blueprint',
      rootCause: 'project_blueprint_required',
    }))
  }

  const buildDiagnostics = (overrides: Partial<Omit<BuildGenerationDiagnostics, 'events'>> = {}): BuildGenerationDiagnostics => (
    buildGenerationDiagnostics({
      prompt: input.prompt,
      generationMode: overrides.generationMode ?? (usedEmergencyFallback ? 'emergency-fallback' : resolvedGenerationMode),
      selectedProjectType: overrides.selectedProjectType
        ?? builderProjectType
        ?? mapBlueprintProjectTypeToBuilderProjectType(effectiveBlueprint.projectType),
      selectedBusinessType: overrides.selectedBusinessType ?? effectiveBlueprint.businessType ?? null,
      selectedSectionTypes: overrides.selectedSectionTypes ?? selectedSectionTypes,
      selectedSections: overrides.selectedSections ?? selectedSectionTypes,
      selectedComponentKeys: overrides.selectedComponentKeys ?? selectedComponentKeys,
      validationPassed: overrides.validationPassed ?? validationPassed,
      emergencyFallbackUsed: overrides.emergencyFallbackUsed ?? usedEmergencyFallback,
      fallbackUsed: overrides.fallbackUsed ?? usedEmergencyFallback,
      failedStep: overrides.failedStep ?? null,
      rootCause: overrides.rootCause ?? null,
      events: generationLog,
    })
  )

  try {
    logBlueprintStep(generationLog, 'prompt', 'prompt received', {
      prompt: input.prompt,
      builderProjectTypeOverride: input.builderProjectTypeOverride ?? null,
      generationMode: resolvedGenerationMode,
    })
    logBlueprintStep(generationLog, 'blueprint', 'blueprint created', effectiveBlueprint, 'success')

    let selectedSectionsResult = selectSectionsFromBlueprint(effectiveBlueprint)
    selectedSectionTypes = selectedSectionsResult.sections.map((section) => section.sectionType)
    logBlueprintStep(generationLog, 'sections', 'sections selected', selectedSectionsResult.sections, 'success')

    let componentResult = selectComponentsFromBlueprint({
      prompt: input.prompt,
      blueprint: effectiveBlueprint,
      sections: selectedSectionsResult.sections,
      builderProjectTypeOverride: input.builderProjectTypeOverride,
    })

    usedEmergencyFallback = usedEmergencyFallback || selectedSectionsResult.usedEmergencyFallback

    logBlueprintStep(generationLog, 'component_scores', 'components scored', componentResult.scorecards, 'success')

    if (componentResult.components.length === 0) {
      effectiveBlueprint = createEmergencyFallbackBlueprint(effectiveBlueprint.projectType)
      selectedSectionsResult = {
        sections: getEmergencyFallbackSections(effectiveBlueprint.projectType),
        usedEmergencyFallback: true,
      }
      selectedSectionTypes = selectedSectionsResult.sections.map((section) => section.sectionType)
      componentResult = selectComponentsFromBlueprint({
        prompt: input.prompt,
        blueprint: effectiveBlueprint,
        sections: selectedSectionsResult.sections,
        builderProjectTypeOverride: input.builderProjectTypeOverride,
      })
      usedEmergencyFallback = true
      logBlueprintStep(generationLog, 'fallback', 'emergency fallback blueprint', effectiveBlueprint, 'success')
      logBlueprintStep(generationLog, 'component_scores', 'components rescored after fallback', componentResult.scorecards, 'success')
    }

    selectedComponentKeys = componentResult.components.map((component) => component.componentKey)
    logBlueprintStep(generationLog, 'components', 'components selected', componentResult.components, 'success')

    builderProjectType = input.builderProjectTypeOverride
      ?? mapBlueprintProjectTypeToBuilderProjectType(effectiveBlueprint.projectType)
    const siteType = normalizeProjectSiteType(builderProjectType)
    const aiProjectType = mapBlueprintProjectTypeToAiProjectType(effectiveBlueprint.projectType)

    const resolvedSectionContent = componentResult.components.map((section, index) => (
      generateSectionContent({
        prompt: input.prompt,
        blueprint: effectiveBlueprint,
        section,
        brandName: input.brandName,
        sectionIndex: index,
        totalSections: componentResult.components.length,
      })
    ))

    logBlueprintStep(
      generationLog,
      'content',
      'content generated',
      resolvedSectionContent.map((entry, index) => ({
        componentKey: componentResult.components[index]?.componentKey,
        sectionType: componentResult.components[index]?.sectionType,
        brief: entry.brief,
        content: entry.content,
        props: entry.props,
      })),
      'success'
    )

    const siteSections = componentResult.components.map((section, index) => {
      return {
        componentKey: section.componentKey,
        label: section.label,
        layoutType: section.layoutType,
        ...(section.variant ? { variant: section.variant } : {}),
        props: resolvedSectionContent[index]?.props ?? {},
      }
    })

    const sitePlan: AiSitePlan = {
      projectType: aiProjectType,
      builderProjectType,
      pages: [{
        name: 'home',
        sections: siteSections,
      }],
      available_components: componentResult.availableComponents,
      project: {
        type: siteType,
      },
    }

    const tree = sectionPlanToComponentTree({
      sections: siteSections.map((section) => ({
        componentKey: section.componentKey,
        ...(section.variant ? { variant: section.variant } : {}),
        ...(section.props ? { props: section.props } : {}),
      })),
    }, {
      propsByIndex: {},
    })

    logBlueprintStep(
      generationLog,
      'tree',
      'tree built',
      tree.map((node) => ({
        id: node.id,
        componentKey: node.componentKey,
      })),
      'success'
    )

    const validation = validateGeneratedSite({
      projectType: builderProjectType,
      tree,
      supportedComponentKeys: componentResult.availableComponents,
      plannedSections: siteSections.map((section, index) => ({
        componentKey: section.componentKey,
        props: section.props,
        sectionId: `planned-section-${index + 1}`,
      })),
      generationMode: resolvedGenerationMode,
      usedEmergencyFallback,
    })
    if (!validation.ok) {
      const errorMessage = formatGeneratedSiteValidationIssues(validation.issues)
      logBlueprintStep(generationLog, 'validation', 'validation failed', {
        issues: validation.issues,
      }, 'failure')
      throw new GenerationTraceError(errorMessage, buildDiagnostics({
        failedStep: 'validation',
        rootCause: errorMessage,
        validationPassed: false,
      }))
    }
    validationPassed = true
    logBlueprintStep(generationLog, 'validation', 'validation passed', {
      issues: [],
      selectedSections: selectedSectionTypes,
      selectedComponentKeys,
      generationMode: resolvedGenerationMode,
      usedEmergencyFallback,
    }, 'success')

    return {
      blueprint: effectiveBlueprint,
      tree,
      sectionsDraft: treeToSectionsDraft(tree),
      projectType: builderProjectType,
      project: {
        projectType: builderProjectType,
        type: siteType,
      },
      available_components: componentResult.availableComponents,
      sitePlan,
      generationLog,
      usedEmergencyFallback,
      diagnostics: buildDiagnostics({
        validationPassed: true,
      }),
    }
  } catch (error) {
    if (isGenerationTraceError(error)) {
      throw error
    }

    const message = error instanceof Error ? error.message : String(error)
    const failedStep = generationLog[generationLog.length - 1]?.step ?? 'prompt'
    logBlueprintStep(generationLog, failedStep, `generation failed during ${failedStep}`, {
      error: message,
    }, 'failure')

    throw new GenerationTraceError(message, buildDiagnostics({
      failedStep,
      rootCause: message,
      validationPassed: false,
    }))
  }
}
