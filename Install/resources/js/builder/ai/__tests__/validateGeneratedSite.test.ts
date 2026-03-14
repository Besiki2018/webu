import { describe, expect, it } from 'vitest'
import { validateGeneratedSite } from '../validateGeneratedSite'
import type { BuilderComponentInstance } from '../../core/types'

function buildValidLandingTree(): BuilderComponentInstance[] {
  return [
    {
      id: 'header-1',
      componentKey: 'webu_header_01',
      props: {
        logoText: 'Northline',
      },
    },
    {
      id: 'hero-1',
      componentKey: 'webu_general_hero_01',
      props: {
        title: 'Premium care for modern teams',
        subtitle: 'Launch faster with a clear workflow and trusted delivery.',
        buttonText: 'Book a demo',
      },
    },
    {
      id: 'footer-1',
      componentKey: 'webu_footer_01',
      props: {
        copyright: '© 2026 Northline',
      },
    },
  ]
}

describe('validateGeneratedSite', () => {
  it('passes a valid landing tree', () => {
    const result = validateGeneratedSite({
      projectType: 'landing',
      tree: buildValidLandingTree(),
      generationMode: 'blueprint',
    })

    expect(result.ok).toBe(true)
    expect(result.issues).toEqual([])
  })

  it('fails when a repaired tree hides an invalid planned component key', () => {
    const result = validateGeneratedSite({
      projectType: 'landing',
      tree: buildValidLandingTree(),
      plannedSections: [
        { sectionId: 'planned-1', componentKey: 'webu_header_01' },
        { sectionId: 'planned-2', componentKey: 'totally_fake_hero' },
        { sectionId: 'planned-3', componentKey: 'webu_footer_01' },
      ],
      generationMode: 'blueprint',
    })

    expect(result.ok).toBe(false)
    expect(result.issues).toEqual(expect.arrayContaining([
      expect.objectContaining({
        code: 'unknown_component_key',
        componentKey: 'totally_fake_hero',
      }),
    ]))
  })

  it('validates nested components against the allowed registry for the project type', () => {
    const tree = buildValidLandingTree()
    tree[1] = {
      ...tree[1],
      children: [{
        id: 'nested-product-grid-1',
        componentKey: 'webu_ecom_product_grid_01',
        props: {
          title: 'Featured products',
          items: [{ title: 'Starter kit' }],
        },
      }],
    }

    const result = validateGeneratedSite({
      projectType: 'landing',
      tree,
      generationMode: 'blueprint',
    })

    expect(result.ok).toBe(false)
    expect(result.issues).toEqual(expect.arrayContaining([
      expect.objectContaining({
        code: 'unsupported_component_key',
        componentKey: 'webu_ecom_product_grid_01',
      }),
    ]))
  })

  it('rejects multiple fallback sections outside explicit emergency fallback mode', () => {
    const result = validateGeneratedSite({
      projectType: 'landing',
      tree: buildValidLandingTree().map((node) => ({
        ...node,
        props: {
          ...node.props,
          __emergencyFallback: true,
        },
      })),
      generationMode: 'blueprint',
      usedEmergencyFallback: true,
    })

    expect(result.ok).toBe(false)
    expect(result.issues).toEqual(expect.arrayContaining([
      expect.objectContaining({
        code: 'emergency_fallback_overflow',
      }),
    ]))
  })
})
