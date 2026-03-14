import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as promptAnalyzer from '../promptAnalyzer'
import * as projectTypeDetector from '../projectTypeDetector'
import {
  __resetCreateBlueprintCacheForTests,
  createBlueprint,
} from '../createBlueprint'

describe('createBlueprint', () => {
  beforeEach(() => {
    __resetCreateBlueprintCacheForTests()
    vi.restoreAllMocks()
  })

  it('extracts a business blueprint for vet clinic prompts', () => {
    const blueprint = createBlueprint({
      prompt: 'Create a modern vet clinic website for premium pet care',
    })

    expect(blueprint.projectType).toBe('business')
    expect(blueprint.businessType.toLowerCase()).toContain('vet clinic')
    expect(blueprint.audience.length).toBeGreaterThan(0)
    expect(blueprint.styleKeywords).toEqual(expect.arrayContaining(['modern', 'premium']))
    expect(blueprint.pageGoal.length).toBeGreaterThan(0)
    expect(blueprint.sections.some((section) => section.sectionType === 'pricing')).toBe(false)
  })

  it('extracts a saas blueprint for finance team landing prompts', () => {
    const blueprint = createBlueprint({
      prompt: 'Create a minimalist SaaS landing page for finance teams',
    })

    expect(blueprint.projectType).toBe('saas')
    expect(blueprint.businessType.toLowerCase()).toContain('finance')
    expect(blueprint.audience).toContain('finance teams')
    expect(blueprint.tone).toBe('minimal')
    expect(blueprint.styleKeywords).toEqual(expect.arrayContaining(['minimalist']))
    expect(blueprint.sections.some((section) => section.sectionType === 'pricing')).toBe(true)
  })

  it('uses the emergency fallback blueprint for empty prompts', () => {
    const blueprint = createBlueprint({
      prompt: '   ',
      projectType: 'restaurant',
    })

    expect(blueprint.projectType).toBe('restaurant')
    expect(blueprint.sections.map((section) => section.sectionType)).toEqual(expect.arrayContaining([
      'header',
      'hero',
      'cta',
      'footer',
    ]))
  })

  it('caches normalized prompt analysis for repeat prompts', () => {
    const analyzeSpy = vi.spyOn(promptAnalyzer, 'analyzePrompt')
    const detectSpy = vi.spyOn(projectTypeDetector, 'detectProjectType')

    const first = createBlueprint({
      prompt: 'Create a premium website for a finance consulting firm',
    })
    const second = createBlueprint({
      prompt: 'Create a premium website for a finance consulting firm',
    })

    expect(first).toEqual(second)
    expect(analyzeSpy).toHaveBeenCalledTimes(1)
    expect(detectSpy).toHaveBeenCalledTimes(1)
  })
})
