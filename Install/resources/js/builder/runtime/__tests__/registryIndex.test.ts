import { describe, expect, it } from 'vitest'

import { buildRegistryIndex } from '../registryIndex'

describe('registryIndex', () => {
  it('indexes entries by component key and section type', () => {
    const index = buildRegistryIndex([
      { componentKey: 'hero-a', sectionType: 'hero', label: 'Hero A' },
      { componentKey: 'hero-b', sectionType: 'hero', label: 'Hero B' },
      { componentKey: 'footer-a', sectionType: 'footer', label: 'Footer A' },
    ])

    expect(index.byKey['hero-a']?.componentKey).toBe('hero-a')
    expect(index.bySectionType.hero?.map((entry) => entry.componentKey)).toEqual(['hero-a', 'hero-b'])
    expect(index.bySectionType.footer?.map((entry) => entry.componentKey)).toEqual(['footer-a'])
  })
})
