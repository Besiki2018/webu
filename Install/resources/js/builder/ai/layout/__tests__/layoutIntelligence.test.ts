import { describe, expect, it } from 'vitest'
import type { ProjectBlueprint } from '../../blueprintTypes'
import { detectDomain } from '../detectDomain'
import { buildLayoutPlan } from '../buildLayoutPlan'

function createBlueprint(overrides: Partial<ProjectBlueprint> = {}): ProjectBlueprint {
  return {
    projectType: 'business',
    businessType: 'Vet clinic',
    audience: 'pet owners',
    tone: 'modern',
    styleKeywords: ['modern', 'clean'],
    pageGoal: 'Generate appointments',
    sections: [],
    restrictions: {
      noPricing: true,
      noTestimonials: false,
      onePageOnly: true,
    },
    sourcePrompt: 'Create a veterinary clinic website',
    ...overrides,
  }
}

describe('layout intelligence', () => {
  it('detects business domains from prompt keywords', () => {
    expect(detectDomain({
      prompt: 'Create a veterinary clinic website',
      projectType: 'business',
    })).toMatchObject({
      domain: 'vet_clinic',
    })

    expect(detectDomain({
      prompt: 'Create a restaurant website with menu and reservations',
      projectType: 'restaurant',
    })).toMatchObject({
      domain: 'restaurant',
    })
  })

  it('falls back to project-type hints when prompt keywords are sparse', () => {
    expect(detectDomain({
      prompt: 'Create a modern landing page',
      projectType: 'saas',
    })).toMatchObject({
      domain: 'saas',
    })
  })

  it('builds a deterministic vet clinic layout plan', () => {
    const detectedDomain = detectDomain({
      prompt: 'Create a veterinary clinic website',
      projectType: 'business',
    })

    const result = buildLayoutPlan({
      blueprint: createBlueprint(),
      detectedDomain,
    })

    expect(result.selectedLayoutTemplate).toBe('vet_clinic')
    expect(result.finalSections).toEqual([
      'header',
      'hero',
      'services',
      'doctors',
      'appointment_booking',
      'testimonials',
      'faq',
      'contact',
      'footer',
    ])
  })

  it('removes forbidden sections from the final layout plan', () => {
    const detectedDomain = detectDomain({
      prompt: 'Create a SaaS landing page for analytics software',
      projectType: 'saas',
    })

    const result = buildLayoutPlan({
      blueprint: createBlueprint({
        projectType: 'saas',
        businessType: 'Analytics software',
        audience: 'data teams',
        restrictions: {
          noPricing: true,
          noTestimonials: true,
          onePageOnly: true,
        },
        sourcePrompt: 'Create a SaaS landing page for analytics software',
      }),
      detectedDomain,
    })

    expect(result.finalSections).toEqual([
      'header',
      'hero',
      'problem',
      'solution',
      'features',
      'product_demo',
      'faq',
      'cta',
      'footer',
    ])
  })
})
