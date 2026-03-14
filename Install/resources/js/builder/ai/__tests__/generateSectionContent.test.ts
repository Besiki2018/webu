import { createBlueprint } from '../createBlueprint'
import { generateSectionContent } from '../generateSectionContent'
import { hasDisallowedProductionCopy } from '../contentContracts'
import { selectComponentsFromBlueprint } from '../selectComponentsFromBlueprint'
import { selectSectionsFromBlueprint } from '../selectSectionsFromBlueprint'

function buildSelections(prompt: string) {
  const blueprint = createBlueprint({ prompt })
  const sections = selectSectionsFromBlueprint(blueprint).sections
  const components = selectComponentsFromBlueprint({
    prompt,
    blueprint,
    sections,
  }).components

  return {
    blueprint,
    components,
  }
}

describe('generateSectionContent', () => {
  it('creates industry-specific hero, features, and action content from the blueprint', () => {
    const vetPrompt = 'Create a modern vet clinic website for premium pet care'
    const financePrompt = 'Create a minimalist SaaS landing page for finance teams'
    const vet = buildSelections(vetPrompt)
    const finance = buildSelections(financePrompt)

    const vetHero = generateSectionContent({
      prompt: vetPrompt,
      blueprint: vet.blueprint,
      section: vet.components.find((section) => section.layoutType === 'hero')!,
      sectionIndex: 0,
      totalSections: vet.components.length,
    })
    const financeHero = generateSectionContent({
      prompt: financePrompt,
      blueprint: finance.blueprint,
      section: finance.components.find((section) => section.layoutType === 'hero')!,
      sectionIndex: 0,
      totalSections: finance.components.length,
    })
    const vetFeatures = generateSectionContent({
      prompt: vetPrompt,
      blueprint: vet.blueprint,
      section: vet.components.find((section) => section.layoutType === 'features')!,
      sectionIndex: 1,
      totalSections: vet.components.length,
    })
    const financeFeatures = generateSectionContent({
      prompt: financePrompt,
      blueprint: finance.blueprint,
      section: finance.components.find((section) => section.layoutType === 'features')!,
      sectionIndex: 1,
      totalSections: finance.components.length,
    })
    const vetActionSelection = vet.components.find((section) => section.layoutType === 'form' || section.layoutType === 'cta')!
    const financeActionSelection = finance.components.find((section) => section.layoutType === 'form' || section.layoutType === 'cta')!
    const vetAction = generateSectionContent({
      prompt: vetPrompt,
      blueprint: vet.blueprint,
      section: vetActionSelection,
      sectionIndex: vet.components.indexOf(vetActionSelection),
      totalSections: vet.components.length,
    })
    const financeAction = generateSectionContent({
      prompt: financePrompt,
      blueprint: finance.blueprint,
      section: financeActionSelection,
      sectionIndex: finance.components.indexOf(financeActionSelection),
      totalSections: finance.components.length,
    })

    expect(vetHero.props.title).not.toBe(financeHero.props.title)
    expect(JSON.stringify(vetHero.props)).toMatch(/pet|veterinary|visit|clinic/i)
    expect(JSON.stringify(financeHero.props)).toMatch(/finance|demo|workflow|reporting/i)
    expect(JSON.stringify(vetFeatures.props)).toMatch(/pet|visit|care|treatment/i)
    expect(JSON.stringify(financeFeatures.props)).toMatch(/finance|approval|reporting|close/i)
    expect(JSON.stringify(vetAction.props)).toMatch(/appointment|visit|pet|care/i)
    expect(JSON.stringify(financeAction.props)).toMatch(/demo|finance|workflow|consultation/i)
  })

  it('resolves props without banned filler copy', () => {
    const prompt = 'Create a modern ecommerce website for a furniture store'
    const { blueprint, components } = buildSelections(prompt)
    const resolved = components.map((section, index) => (
      generateSectionContent({
        prompt,
        blueprint,
        section,
        sectionIndex: index,
        totalSections: components.length,
      })
    ))

    expect(hasDisallowedProductionCopy(resolved.map((entry) => entry.props))).toBe(false)
  })
})
