import type { ProjectBlueprint } from '../blueprintTypes'
import { clonePlainData } from '../stableSerialize'
import { detectDomain } from '../layout/detectDomain'
import { buildLayoutPlan } from '../layout/buildLayoutPlan'

export interface EnhanceBlueprintWithLayoutInput {
  blueprint: ProjectBlueprint
  prompt?: string | null
  userMetadata?: Partial<{
    businessType: string
    audience: string
    pageGoal: string
    styleKeywords: string[]
  }>
}

function resolvePrompt(input: EnhanceBlueprintWithLayoutInput): string {
  if (typeof input.prompt === 'string' && input.prompt.trim() !== '') {
    return input.prompt.trim()
  }

  if (typeof input.blueprint.sourcePrompt === 'string' && input.blueprint.sourcePrompt.trim() !== '') {
    return input.blueprint.sourcePrompt.trim()
  }

  return [
    input.blueprint.projectType,
    input.blueprint.businessType,
    input.blueprint.audience,
    input.blueprint.pageGoal,
    ...input.blueprint.styleKeywords,
  ].filter(Boolean).join(' ')
}

export function enhanceBlueprintWithLayout(input: EnhanceBlueprintWithLayoutInput): ProjectBlueprint {
  const blueprint = clonePlainData(input.blueprint)
  const prompt = resolvePrompt(input)
  const detectedDomain = detectDomain({
    prompt,
    projectType: blueprint.projectType,
    userMetadata: {
      businessType: blueprint.businessType,
      audience: blueprint.audience,
      pageGoal: blueprint.pageGoal,
      styleKeywords: blueprint.styleKeywords,
      ...(input.userMetadata ?? {}),
    },
  })
  const layoutPlan = buildLayoutPlan({
    blueprint: {
      ...blueprint,
      sourcePrompt: prompt,
    },
    detectedDomain,
  })

  return {
    ...blueprint,
    sourcePrompt: prompt,
    sections: layoutPlan.sections,
    layoutDiagnostics: {
      detectedDomain,
      selectedLayoutTemplate: layoutPlan.selectedLayoutTemplate,
      finalSections: layoutPlan.finalSections,
    },
  }
}
