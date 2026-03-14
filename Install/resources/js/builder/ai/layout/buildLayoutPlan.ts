import type {
  DetectedLayoutDomain,
  ProjectBlueprint,
  ProjectBlueprintSection,
} from '../blueprintTypes'
import { selectLayoutTemplate } from './layoutTemplates'
import { normalizeLayoutPlan, type DraftLayoutPlanSection } from './normalizeLayoutPlan'
import { scoreLayoutSections } from './scoreLayoutSections'

export interface BuildLayoutPlanInput {
  blueprint: ProjectBlueprint
  detectedDomain: DetectedLayoutDomain
}

export interface BuildLayoutPlanResult {
  sections: ProjectBlueprintSection[]
  selectedLayoutTemplate: string
  finalSections: string[]
}

const REQUIRED_SHELL_SECTIONS = ['header', 'hero', 'footer']

function buildContentBrief(blueprint: ProjectBlueprint, sectionType: string): Record<string, unknown> {
  return {
    businessType: blueprint.businessType,
    audience: blueprint.audience,
    tone: blueprint.tone,
    styleKeywords: [...blueprint.styleKeywords],
    pageGoal: blueprint.pageGoal,
    emphasis: sectionType,
  }
}

function shouldExcludeSection(blueprint: ProjectBlueprint, sectionType: string): boolean {
  if (blueprint.restrictions?.noPricing && sectionType === 'pricing') {
    return true
  }

  if (blueprint.restrictions?.noTestimonials && ['testimonials', 'reviews'].includes(sectionType)) {
    return true
  }

  return false
}

function normalizeLayoutSectionType(
  blueprint: ProjectBlueprint,
  detectedDomain: DetectedLayoutDomain,
  sectionType: string,
): string {
  if (sectionType === 'booking') {
    if (detectedDomain.domain === 'restaurant' || blueprint.projectType === 'restaurant') {
      return 'reservation'
    }
    if (detectedDomain.domain === 'vet_clinic') {
      return 'appointment_booking'
    }
  }

  return sectionType
}

function buildMergedSections(
  blueprint: ProjectBlueprint,
  detectedDomain: DetectedLayoutDomain,
  templateSections: string[],
): DraftLayoutPlanSection[] {
  const existingSections = new Map(
    blueprint.sections.map((section, index) => [
      normalizeLayoutSectionType(blueprint, detectedDomain, section.sectionType),
      {
        type: normalizeLayoutSectionType(blueprint, detectedDomain, section.sectionType),
        required: section.required,
        contentBrief: section.contentBrief,
        originalIndex: index + templateSections.length + REQUIRED_SHELL_SECTIONS.length,
      } satisfies DraftLayoutPlanSection,
    ])
  )

  return Array.from(new Set([
    ...REQUIRED_SHELL_SECTIONS,
    ...templateSections.map((sectionType) => normalizeLayoutSectionType(blueprint, detectedDomain, sectionType)),
    ...blueprint.sections.map((section) => normalizeLayoutSectionType(blueprint, detectedDomain, section.sectionType)),
  ]))
    .filter((sectionType) => !shouldExcludeSection(blueprint, sectionType))
    .map((sectionType, index) => {
      const existing = existingSections.get(sectionType)
      return {
        type: sectionType,
        required: existing?.required === true || REQUIRED_SHELL_SECTIONS.includes(sectionType),
        contentBrief: existing?.contentBrief ?? buildContentBrief(blueprint, sectionType),
        originalIndex: existing ? Math.min(existing.originalIndex ?? index, index) : index,
      } satisfies DraftLayoutPlanSection
    })
}

export function buildLayoutPlan(input: BuildLayoutPlanInput): BuildLayoutPlanResult {
  const selectedTemplate = selectLayoutTemplate(input.detectedDomain.domain, input.blueprint.projectType)
  const scoredSections = scoreLayoutSections({
    blueprint: input.blueprint,
    detectedDomain: input.detectedDomain,
    sections: buildMergedSections(input.blueprint, input.detectedDomain, selectedTemplate.sections),
  })
  const normalizedSections = normalizeLayoutPlan(scoredSections)
  const sections = normalizedSections.map((section, index) => ({
    sectionType: section.type,
    priority: (index + 1) * 10,
    required: section.required,
    ...(section.contentBrief ? { contentBrief: section.contentBrief } : {}),
  }))

  return {
    sections,
    selectedLayoutTemplate: selectedTemplate.key,
    finalSections: sections.map((section) => section.sectionType),
  }
}
