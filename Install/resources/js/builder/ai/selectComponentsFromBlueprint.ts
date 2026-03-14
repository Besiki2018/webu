import type { ProjectType } from '../projectTypes'
import { getAllowedComponentCatalog } from './componentCatalog'
import {
  getCompatibleSectionTypes,
  retrieveComponentsForSection,
} from './componentRetrieval'
import { inferAiProjectTypeFromBuilderProjectType, type AiProjectType } from './projectTypeDetector'
import { selectComponentVariant } from './variantSelector'
import type { ComponentScoreBreakdown } from './componentScoring'
import type { BlueprintComponentSelection, NormalizedBlueprintSection, ProjectBlueprint } from './blueprintTypes'

export interface SelectComponentsFromBlueprintInput {
  prompt: string
  blueprint: ProjectBlueprint
  sections: NormalizedBlueprintSection[]
  builderProjectTypeOverride?: ProjectType | null
}

export interface SelectComponentsFromBlueprintResult {
  components: BlueprintComponentSelection[]
  availableComponents: string[]
  scorecards: Array<{
    sectionType: string
    compatibleSectionTypes: string[]
    selectedComponentKey: string | null
    candidates: Array<{
      componentKey: string
      label: string
      layoutType: string
      totalScore: number
      breakdown: ComponentScoreBreakdown
    }>
  }>
}

function resolveCatalogProjectType(blueprint: ProjectBlueprint, override?: ProjectType | null): AiProjectType {
  if (override) {
    return inferAiProjectTypeFromBuilderProjectType(override)
  }

  switch (blueprint.projectType) {
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

export function selectComponentsFromBlueprint(input: SelectComponentsFromBlueprintInput): SelectComponentsFromBlueprintResult {
  const catalogProjectType = resolveCatalogProjectType(input.blueprint, input.builderProjectTypeOverride)
  const catalog = getAllowedComponentCatalog(catalogProjectType)
  const components: BlueprintComponentSelection[] = []
  const scorecards: SelectComponentsFromBlueprintResult['scorecards'] = []
  const usedComponentKeys = new Set<string>()

  input.sections.forEach((section, index) => {
    const compatibleSectionTypes = getCompatibleSectionTypes(section.sectionType)
    const candidates = retrieveComponentsForSection({
      blueprint: input.blueprint,
      section,
      catalog,
      sectionIndex: index,
      totalSections: input.sections.length,
      usedComponentKeys,
    })
    const candidate = candidates[0] ?? null

    scorecards.push({
      sectionType: section.sectionType,
      compatibleSectionTypes,
      selectedComponentKey: candidate?.entry.componentKey ?? null,
      candidates: candidates.slice(0, 5).map((result) => ({
        componentKey: result.entry.componentKey,
        label: result.entry.label,
        layoutType: result.entry.layoutType,
        totalScore: result.score.total,
        breakdown: result.score,
      })),
    })

    if (!candidate) {
      return
    }

    const component = candidate.entry
    const variant = selectComponentVariant({
      componentKey: component.componentKey,
      prompt: [
        input.prompt,
        input.blueprint.businessType,
        input.blueprint.audience,
        input.blueprint.pageGoal,
        section.sectionType,
      ].filter(Boolean).join(' '),
      projectType: catalogProjectType,
      tone: input.blueprint.tone,
      industry: input.blueprint.businessType,
      styleKeywords: input.blueprint.styleKeywords,
      sectionType: section.sectionType,
      existingLayoutTypes: components.map((entry) => entry.layoutType),
    })

    usedComponentKeys.add(component.componentKey)

    components.push({
      sectionType: section.sectionType,
      priority: section.priority,
      required: section.required,
      layoutType: component.layoutType,
      componentKey: component.componentKey,
      label: component.label,
      ...(variant ? { variant } : {}),
      ...(section.contentBrief ? { contentBrief: section.contentBrief } : {}),
    })
  })

  return {
    components,
    availableComponents: catalog.map((entry) => entry.componentKey),
    scorecards,
  }
}
