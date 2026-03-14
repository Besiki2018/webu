import { beforeEach, describe, expect, it, vi } from 'vitest'
import { getAllowedComponentCatalogIndex } from '../componentCatalog'
import * as componentScoring from '../componentScoring'
import {
  __resetComponentRetrievalCacheForTests,
  retrieveBestComponentForSection,
  retrieveComponentsForSection,
} from '../componentRetrieval'
import type { NormalizedBlueprintSection, ProjectBlueprint } from '../blueprintTypes'

function createBlueprint(overrides: Partial<ProjectBlueprint>): ProjectBlueprint {
  return {
    projectType: 'business',
    businessType: 'Business website',
    audience: 'customers looking for services',
    tone: 'clean',
    styleKeywords: ['clean'],
    pageGoal: 'Generate qualified inquiries',
    sections: [],
    restrictions: {
      noPricing: true,
      noTestimonials: false,
      onePageOnly: true,
    },
    ...overrides,
  }
}

function createSection(sectionType: string): NormalizedBlueprintSection {
  return {
    sectionType,
    priority: 10,
    required: true,
    contentBrief: {
      emphasis: sectionType,
    },
  }
}

describe('componentRetrieval', () => {
  beforeEach(() => {
    __resetComponentRetrievalCacheForTests()
    vi.restoreAllMocks()
  })

  it('returns deterministic ranked results for the same blueprint context', () => {
    const blueprint = createBlueprint({
      businessType: 'Finance consulting firm',
      audience: 'finance leaders',
      tone: 'clean',
      styleKeywords: ['clean', 'trust'],
      pageGoal: 'Turn finance leaders into consultation leads',
    })
    const section = createSection('cta')
    const registryIndex = getAllowedComponentCatalogIndex('business')

    const first = retrieveComponentsForSection({
      blueprint,
      section,
      registryIndex,
      sectionIndex: 5,
      totalSections: 7,
    }).map((candidate) => candidate.entry.componentKey)

    const second = retrieveComponentsForSection({
      blueprint,
      section,
      registryIndex,
      sectionIndex: 5,
      totalSections: 7,
    }).map((candidate) => candidate.entry.componentKey)

    expect(first).toEqual(second)
  })

  it('retrieves different CTA components for different industries on the same project type', () => {
    const registryIndex = getAllowedComponentCatalogIndex('business')
    const section = createSection('cta')
    const vetBlueprint = createBlueprint({
      businessType: 'Vet clinic',
      audience: 'pet owners seeking trusted care',
      tone: 'premium',
      styleKeywords: ['premium', 'clean', 'medical', 'trust'],
      pageGoal: 'Build trust and drive appointment inquiries',
    })
    const consultingBlueprint = createBlueprint({
      businessType: 'Finance consulting firm',
      audience: 'finance leaders',
      tone: 'premium',
      styleKeywords: ['premium', 'clean', 'trust'],
      pageGoal: 'Convert finance leaders into strategy calls',
    })

    const vetResult = retrieveBestComponentForSection({
      blueprint: vetBlueprint,
      section,
      registryIndex,
      sectionIndex: 5,
      totalSections: 7,
    })
    const consultingResult = retrieveBestComponentForSection({
      blueprint: consultingBlueprint,
      section,
      registryIndex,
      sectionIndex: 5,
      totalSections: 7,
    })

    expect(vetResult?.entry.componentKey).toBe('webu_general_form_wrapper_01')
    expect(consultingResult?.entry.componentKey).toBe('webu_general_cta_01')
  })

  it('memoizes identical retrieval requests to avoid rescoring the same registry slice', () => {
    const scoreSpy = vi.spyOn(componentScoring, 'scoreComponentForSection')
    const blueprint = createBlueprint({
      businessType: 'Finance consulting firm',
      audience: 'finance leaders',
      tone: 'clean',
      styleKeywords: ['clean', 'trust'],
      pageGoal: 'Turn finance leaders into consultation leads',
    })
    const section = createSection('hero')
    const registryIndex = getAllowedComponentCatalogIndex('business')

    const first = retrieveComponentsForSection({
      blueprint,
      section,
      registryIndex,
      sectionIndex: 1,
      totalSections: 7,
    })
    const firstPassCallCount = scoreSpy.mock.calls.length
    const second = retrieveComponentsForSection({
      blueprint,
      section,
      registryIndex,
      sectionIndex: 1,
      totalSections: 7,
    })

    expect(first.map((candidate) => candidate.entry.componentKey)).toEqual(
      second.map((candidate) => candidate.entry.componentKey),
    )
    expect(firstPassCallCount).toBeGreaterThan(0)
    expect(scoreSpy).toHaveBeenCalledTimes(firstPassCallCount)
  })
})
