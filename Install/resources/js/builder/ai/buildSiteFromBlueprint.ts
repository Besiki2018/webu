import type { BuilderComponentInstance } from '../core/types'
import { normalizeProjectSiteType, type BuilderProject, type ProjectType } from '../projectTypes'
import { treeToSectionsDraft } from '../aiSiteGeneration'
import { createEmergencyFallbackBlueprint } from './createBlueprint'
import { generateSectionContent } from './generateSectionContent'
import {
  buildGenerationDiagnostics,
  createEmptyStageTimings,
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
import { getAllowedComponentCatalogIndex } from './componentCatalog'
import type {
  BuildGenerationDiagnostics,
  BuildGenerationStageTimingsMs,
  BlueprintGenerationLogEntry,
  BlueprintProjectType,
  ProjectBlueprint,
} from './blueprintTypes'
import type { AiSitePlan } from './sitePlanner'
import type { BuilderSection } from '../visual/treeUtils'
import { inferAiProjectTypeFromBuilderProjectType, type AiProjectType } from './projectTypeDetector'
import { enhanceBlueprintWithLayout } from './blueprint/enhanceBlueprintWithLayout'
import { buildDesignQualityReport } from './designQuality/buildDesignQualityReport'
import { improveDesignFromReport } from './designQuality/improveDesignFromReport'
import type { DesignQualityReport } from './designQuality/types'
import { cloneData } from '../runtime/clone'

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

function resolveCatalogProjectType(blueprint: ProjectBlueprint, override?: ProjectType | null): AiProjectType {
  if (override) {
    return inferAiProjectTypeFromBuilderProjectType(override)
  }

  return mapBlueprintProjectTypeToAiProjectType(blueprint.projectType)
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

function getTimingNow(): number {
  if (typeof globalThis.performance?.now === 'function') {
    return globalThis.performance.now()
  }

  return Date.now()
}

function roundTiming(durationMs: number): number {
  return Math.round(durationMs * 100) / 100
}

function componentKeyToNodeId(componentKey: string, index: number): string {
  const slug = componentKey
    .replace(/^webu_/, '')
    .replace(/_01$/, '')
    .replace(/_/g, '-')
    .toLowerCase()

  return `${slug || 'section'}-${index + 1}`
}

function buildMergedSectionProps(
  defaultProps: Record<string, unknown>,
  overrideProps: Record<string, unknown>,
  variant?: string,
): Record<string, unknown> {
  const mergedProps = Object.keys(defaultProps).length > 0
    ? cloneData(defaultProps)
    : {}

  Object.assign(mergedProps, overrideProps)

  if (variant !== undefined) {
    mergedProps.variant = variant
  }

  return mergedProps
}

function assembleSiteSectionsAndTree(input: {
  sections: AiSitePlan['pages'][number]['sections']
  registryIndex: ReturnType<typeof getAllowedComponentCatalogIndex>
}): {
  siteSections: AiSitePlan['pages'][number]['sections']
  tree: BuilderComponentInstance[]
} {
  const siteSections = input.sections.map((section) => ({
    ...section,
    props: section.props ?? {},
  }))
  const tree = new Array<BuilderComponentInstance>(siteSections.length)
  const defaultPropsCache = new Map<string, Record<string, unknown>>()

  for (let index = 0; index < siteSections.length; index += 1) {
    const section = siteSections[index]!
    const cachedDefaultProps = defaultPropsCache.get(section.componentKey)
      ?? input.registryIndex.byKey[section.componentKey]?.defaultProps
      ?? {}

    if (!defaultPropsCache.has(section.componentKey)) {
      defaultPropsCache.set(section.componentKey, cachedDefaultProps)
    }

    tree[index] = {
      id: componentKeyToNodeId(section.componentKey, index),
      componentKey: section.componentKey,
      ...(section.variant !== undefined && { variant: section.variant }),
      props: buildMergedSectionProps(cachedDefaultProps, section.props ?? {}, section.variant),
    }
  }

  return {
    siteSections,
    tree,
  }
}

function validateAssembledSite(input: {
  projectType: ProjectType
  tree: BuilderComponentInstance[]
  siteSections: AiSitePlan['pages'][number]['sections']
  availableComponents: string[]
  registryIndex: ReturnType<typeof getAllowedComponentCatalogIndex>
  generationMode: GeneratedSiteValidationMode
  usedEmergencyFallback: boolean
}) {
  return validateGeneratedSite({
    projectType: input.projectType,
    tree: input.tree,
    supportedComponentKeys: input.availableComponents,
    registryIndex: input.registryIndex,
    plannedSections: input.siteSections.map((section, index) => ({
      componentKey: section.componentKey,
      props: section.props,
      sectionId: `planned-section-${index + 1}`,
    })),
    generationMode: input.generationMode,
    usedEmergencyFallback: input.usedEmergencyFallback,
  })
}

export async function buildSiteFromBlueprint(input: BuildSiteFromBlueprintInput): Promise<BuildSiteFromBlueprintResult> {
  const generationLog: BlueprintGenerationLogEntry[] = []
  const resolvedGenerationMode = input.generationMode ?? 'blueprint'
  let effectiveBlueprint = input.blueprint
  let builderProjectType: ProjectType | null = input.builderProjectTypeOverride ?? null
  let selectedSectionTypes: string[] = []
  let selectedComponentKeys: string[] = []
  let usedEmergencyFallback = resolvedGenerationMode === 'emergency-fallback'
  let validationPassed = false
  let designQualityReport: DesignQualityReport | null = null
  const stageTimingsMs: BuildGenerationStageTimingsMs = createEmptyStageTimings()

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
      detectedDomain: overrides.detectedDomain ?? effectiveBlueprint.layoutDiagnostics?.detectedDomain ?? null,
      selectedLayoutTemplate: overrides.selectedLayoutTemplate ?? effectiveBlueprint.layoutDiagnostics?.selectedLayoutTemplate ?? null,
      selectedSectionTypes: overrides.selectedSectionTypes ?? selectedSectionTypes,
      finalSections: overrides.finalSections ?? effectiveBlueprint.layoutDiagnostics?.finalSections ?? selectedSectionTypes,
      selectedSections: overrides.selectedSections ?? selectedSectionTypes,
      selectedComponentKeys: overrides.selectedComponentKeys ?? selectedComponentKeys,
      validationPassed: overrides.validationPassed ?? validationPassed,
      emergencyFallbackUsed: overrides.emergencyFallbackUsed ?? usedEmergencyFallback,
      fallbackUsed: overrides.fallbackUsed ?? usedEmergencyFallback,
      designQualityReport: overrides.designQualityReport ?? designQualityReport,
      stageTimingsMs: overrides.stageTimingsMs ?? stageTimingsMs,
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
    const layoutPlanningStart = getTimingNow()
    if (resolvedGenerationMode !== 'emergency-fallback') {
      effectiveBlueprint = enhanceBlueprintWithLayout({
        blueprint: effectiveBlueprint,
        prompt: input.prompt,
      })
    }
    stageTimingsMs.layoutPlanning = roundTiming(getTimingNow() - layoutPlanningStart)
    logBlueprintStep(generationLog, 'blueprint', 'blueprint created', effectiveBlueprint, 'success')
    logBlueprintStep(generationLog, 'layout', 'layout plan created', {
      durationMs: stageTimingsMs.layoutPlanning,
      ...(effectiveBlueprint.layoutDiagnostics ?? {
        detectedDomain: null,
        selectedLayoutTemplate: null,
        finalSections: effectiveBlueprint.sections.map((section) => section.sectionType),
      }),
    }, 'success')

    let registryIndex = getAllowedComponentCatalogIndex(
      resolveCatalogProjectType(effectiveBlueprint, input.builderProjectTypeOverride ?? null)
    )

    let selectedSectionsResult = selectSectionsFromBlueprint(effectiveBlueprint)
    selectedSectionTypes = selectedSectionsResult.sections.map((section) => section.sectionType)
    logBlueprintStep(generationLog, 'sections', 'sections selected', {
      count: selectedSectionsResult.sections.length,
      sectionTypes: selectedSectionTypes,
    }, 'success')

    const componentSelectionStart = getTimingNow()
    let componentResult = selectComponentsFromBlueprint({
      prompt: input.prompt,
      blueprint: effectiveBlueprint,
      sections: selectedSectionsResult.sections,
      builderProjectTypeOverride: input.builderProjectTypeOverride,
      registryIndex,
    })

    usedEmergencyFallback = usedEmergencyFallback || selectedSectionsResult.usedEmergencyFallback

    logBlueprintStep(generationLog, 'component_scores', 'components scored', componentResult.scorecards, 'success')

    if (componentResult.components.length === 0) {
      effectiveBlueprint = createEmergencyFallbackBlueprint(effectiveBlueprint.projectType)
      registryIndex = getAllowedComponentCatalogIndex(
        resolveCatalogProjectType(effectiveBlueprint, input.builderProjectTypeOverride ?? null)
      )
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
        registryIndex,
      })
      usedEmergencyFallback = true
      logBlueprintStep(generationLog, 'fallback', 'emergency fallback blueprint', effectiveBlueprint, 'success')
      logBlueprintStep(generationLog, 'component_scores', 'components rescored after fallback', componentResult.scorecards, 'success')
    }
    stageTimingsMs.componentSelection = roundTiming(getTimingNow() - componentSelectionStart)

    selectedComponentKeys = componentResult.components.map((component) => component.componentKey)
    logBlueprintStep(generationLog, 'components', 'components selected', {
      durationMs: stageTimingsMs.componentSelection,
      components: componentResult.components.map((component) => ({
        sectionType: component.sectionType,
        componentKey: component.componentKey,
        layoutType: component.layoutType,
        variant: component.variant ?? null,
      })),
    }, 'success')

    builderProjectType = input.builderProjectTypeOverride
      ?? mapBlueprintProjectTypeToBuilderProjectType(effectiveBlueprint.projectType)
    const siteType = normalizeProjectSiteType(builderProjectType)
    const aiProjectType = mapBlueprintProjectTypeToAiProjectType(effectiveBlueprint.projectType)
    const selectedCatalogEntries = componentResult.components.map((component) => (
      registryIndex.byKey[component.componentKey] ?? null
    ))

    const contentGenerationStart = getTimingNow()
    const resolvedSectionContent = await Promise.all(componentResult.components.map(async (section, index) => (
      generateSectionContent({
        prompt: input.prompt,
        blueprint: effectiveBlueprint,
        section,
        catalogEntry: selectedCatalogEntries[index],
        brandName: input.brandName,
        sectionIndex: index,
        totalSections: componentResult.components.length,
      })
    )))
    stageTimingsMs.contentGeneration = roundTiming(getTimingNow() - contentGenerationStart)

    logBlueprintStep(
      generationLog,
      'content',
      'content generated',
      {
        durationMs: stageTimingsMs.contentGeneration,
        sections: resolvedSectionContent.map((entry, index) => ({
          componentKey: componentResult.components[index]?.componentKey,
          sectionType: componentResult.components[index]?.sectionType,
          brief: entry.brief,
          propKeys: Object.keys(entry.props),
        })),
      },
      'success'
    )

    const treeAssemblyStart = getTimingNow()
    let assembled = assembleSiteSectionsAndTree({
      sections: componentResult.components.map((section, index) => ({
        componentKey: section.componentKey,
        label: section.label,
        layoutType: section.layoutType,
        ...(section.variant ? { variant: section.variant } : {}),
        props: resolvedSectionContent[index]?.props ?? {},
      })),
      registryIndex,
    })
    let siteSections = assembled.siteSections
    let tree = assembled.tree
    stageTimingsMs.treeAssembly = roundTiming(getTimingNow() - treeAssemblyStart)

    const designOptimizationStart = getTimingNow()
    const initialDesignQualityReport = buildDesignQualityReport({
      blueprint: effectiveBlueprint,
      siteSections,
      tree,
      registryIndex,
    })
    let currentDesignQualityReport = initialDesignQualityReport
    const appliedDesignImprovements: string[] = []
    let designImprovementPasses = 0

    designQualityReport = initialDesignQualityReport
    logBlueprintStep(generationLog, 'design_quality', 'design quality report built', {
      overallScore: initialDesignQualityReport.overallScore,
      threshold: initialDesignQualityReport.threshold,
      categoryScores: initialDesignQualityReport.categoryScores,
      issues: initialDesignQualityReport.issues,
      improvements: initialDesignQualityReport.improvements,
    }, initialDesignQualityReport.overallScore >= initialDesignQualityReport.threshold ? 'success' : 'info')

    while (
      currentDesignQualityReport.overallScore < currentDesignQualityReport.threshold
      && currentDesignQualityReport.improvements.length > 0
      && designImprovementPasses < 2
    ) {
      const improvementResult = improveDesignFromReport({
        blueprint: effectiveBlueprint,
        siteSections,
        tree,
        registryIndex,
        report: currentDesignQualityReport,
      })

      if (improvementResult.changesApplied.length === 0) {
        logBlueprintStep(generationLog, 'design_improvement', 'design quality improvements unavailable', {
          originalScore: initialDesignQualityReport.overallScore,
          finalScore: currentDesignQualityReport.overallScore,
          threshold: currentDesignQualityReport.threshold,
          issues: currentDesignQualityReport.issues,
        }, 'info')
        break
      }

      designImprovementPasses += 1
      appliedDesignImprovements.push(...improvementResult.changesApplied)
      assembled = assembleSiteSectionsAndTree({
        sections: improvementResult.siteSections.map((section) => ({
          ...section,
          props: section.props ?? {},
        })),
        registryIndex,
      })
      siteSections = assembled.siteSections
      tree = assembled.tree

      const intermediateValidation = validateAssembledSite({
        projectType: builderProjectType,
        tree,
        siteSections,
        availableComponents: componentResult.availableComponents,
        registryIndex,
        generationMode: resolvedGenerationMode,
        usedEmergencyFallback,
      })
      if (!intermediateValidation.ok) {
        const errorMessage = formatGeneratedSiteValidationIssues(intermediateValidation.issues)
        throw new GenerationTraceError(errorMessage, buildDiagnostics({
          failedStep: 'design_improvement',
          rootCause: errorMessage,
          validationPassed: false,
          designQualityReport: currentDesignQualityReport,
        }))
      }

      const nextReport = buildDesignQualityReport({
        blueprint: effectiveBlueprint,
        siteSections,
        tree,
        registryIndex,
        threshold: currentDesignQualityReport.threshold,
      })

      currentDesignQualityReport = {
        ...nextReport,
        initialOverallScore: initialDesignQualityReport.overallScore,
        initialCategoryScores: initialDesignQualityReport.categoryScores,
        improvementsApplied: Array.from(new Set(appliedDesignImprovements)),
        autoImproved: true,
      }
      designQualityReport = currentDesignQualityReport

      logBlueprintStep(generationLog, 'design_improvement', 'design quality improvements applied', {
        pass: designImprovementPasses,
        originalScore: initialDesignQualityReport.overallScore,
        currentScore: currentDesignQualityReport.overallScore,
        threshold: currentDesignQualityReport.threshold,
        changesApplied: improvementResult.changesApplied,
        categoryScores: currentDesignQualityReport.categoryScores,
      }, 'success')
    }

    if (designImprovementPasses === 0 && initialDesignQualityReport.overallScore >= initialDesignQualityReport.threshold) {
      designQualityReport = initialDesignQualityReport
    } else if (designImprovementPasses === 0) {
      designQualityReport = {
        ...initialDesignQualityReport,
        improvementsApplied: [],
        autoImproved: false,
      }
    } else if (currentDesignQualityReport.overallScore < currentDesignQualityReport.threshold) {
      logBlueprintStep(generationLog, 'design_improvement', 'design quality remains below threshold after auto-improvement', {
        originalScore: initialDesignQualityReport.overallScore,
        finalScore: currentDesignQualityReport.overallScore,
        threshold: currentDesignQualityReport.threshold,
        improvementsApplied: currentDesignQualityReport.improvementsApplied,
      }, 'info')
    }
    stageTimingsMs.designOptimization = roundTiming(getTimingNow() - designOptimizationStart)

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

    logBlueprintStep(
      generationLog,
      'tree',
      'tree built',
      {
        durationMs: stageTimingsMs.treeAssembly,
        nodeCount: tree.length,
        nodes: tree.map((node) => ({
          id: node.id,
          componentKey: node.componentKey,
        })),
      },
      'success'
    )

    const validationStart = getTimingNow()
    const validation = validateAssembledSite({
      projectType: builderProjectType,
      tree,
      siteSections,
      availableComponents: componentResult.availableComponents,
      registryIndex,
      generationMode: resolvedGenerationMode,
      usedEmergencyFallback,
    })
    stageTimingsMs.validation = roundTiming(getTimingNow() - validationStart)
    if (!validation.ok) {
      const errorMessage = formatGeneratedSiteValidationIssues(validation.issues)
      logBlueprintStep(generationLog, 'validation', 'validation failed', {
        durationMs: stageTimingsMs.validation,
        issues: validation.issues,
      }, 'failure')
      throw new GenerationTraceError(errorMessage, buildDiagnostics({
        failedStep: 'validation',
        rootCause: errorMessage,
        validationPassed: false,
        designQualityReport,
      }))
    }
    validationPassed = true
    logBlueprintStep(generationLog, 'validation', 'validation passed', {
      durationMs: stageTimingsMs.validation,
      issues: [],
      selectedSections: selectedSectionTypes,
      selectedComponentKeys,
      generationMode: resolvedGenerationMode,
      usedEmergencyFallback,
      designQualityOverallScore: designQualityReport?.overallScore ?? null,
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
        designQualityReport,
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
      designQualityReport,
    }))
  }
}
