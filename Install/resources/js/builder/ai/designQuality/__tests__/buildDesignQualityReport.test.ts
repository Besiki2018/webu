import { describe, expect, it } from 'vitest'
import { sectionPlanToComponentTree } from '../../siteBuilder'
import { getAllowedComponentCatalogIndex } from '../../componentCatalog'
import { buildDesignQualityReport } from '../buildDesignQualityReport'
import { improveDesignFromReport } from '../improveDesignFromReport'
import type { ProjectBlueprint } from '../../blueprintTypes'
import type { AiSitePlanSection } from '../../sitePlanner'

function createBlueprint(): ProjectBlueprint {
  return {
    projectType: 'business',
    businessType: 'Veterinary Clinic',
    audience: 'Pet owners in Tbilisi',
    tone: 'premium',
    styleKeywords: ['premium', 'clean', 'medical', 'trust'],
    pageGoal: 'Encourage appointment bookings',
    sections: [
      { sectionType: 'header', priority: 1, required: true },
      { sectionType: 'hero', priority: 2, required: true },
      { sectionType: 'features', priority: 3, required: true },
      { sectionType: 'cta', priority: 4, required: true },
      { sectionType: 'footer', priority: 5, required: true },
    ],
  }
}

function createWeakSections(): AiSitePlanSection[] {
  return [
    {
      componentKey: 'webu_header_01',
      label: 'Header',
      layoutType: 'header',
      props: {},
    },
    {
      componentKey: 'webu_general_hero_01',
      label: 'Hero',
      layoutType: 'hero',
      props: {
        title: 'Trusted veterinary care',
        subtitle: 'Trusted veterinary care',
        backgroundColor: '#ffffff',
        textColor: '#cbd5e1',
      },
    },
    {
      componentKey: 'webu_general_features_01',
      label: 'Features',
      layoutType: 'features',
      props: {
        title: 'Why families choose us',
        description: 'Preventive care, diagnostics, surgery, and wellness plans for pets across every life stage.',
      },
    },
    {
      componentKey: 'webu_general_cta_01',
      label: 'CTA',
      layoutType: 'cta',
      props: {
        title: 'Ready to book?',
        subtitle: 'Call or click to schedule.',
        buttonText: 'Submit',
        backgroundColor: '#ffffff',
        textColor: '#cbd5e1',
      },
    },
    {
      componentKey: 'webu_footer_01',
      label: 'Footer',
      layoutType: 'footer',
      props: {},
    },
  ]
}

describe('design quality engine', () => {
  it('detects weak spacing, hierarchy, contrast, and CTA clarity', () => {
    const blueprint = createBlueprint()
    const siteSections = createWeakSections()
    const tree = sectionPlanToComponentTree({
      sections: siteSections.map((section) => ({
        componentKey: section.componentKey,
        ...(section.variant ? { variant: section.variant } : {}),
        props: section.props ?? {},
      })),
    })
    const registryIndex = getAllowedComponentCatalogIndex('business')

    const report = buildDesignQualityReport({
      blueprint,
      siteSections,
      tree,
      registryIndex,
    })

    expect(report.overallScore).toBeLessThan(90)
    expect(report.threshold).toBe(80)
    expect(report.categoryScores.spacing).toBeLessThan(100)
    expect(report.categoryScores.contrast).toBeLessThan(100)
    expect(report.issues).toContain('hero padding too small')
    expect(report.issues.some((issue) => issue.includes('CTA text is too generic'))).toBe(true)
    expect(report.issues.some((issue) => issue.includes('contrast'))).toBe(true)
    expect(report.improvements.length).toBeGreaterThan(0)
  })

  it('applies safe improvements and keeps the page schema-valid for rebuild', () => {
    const blueprint = createBlueprint()
    const siteSections = createWeakSections()
    const tree = sectionPlanToComponentTree({
      sections: siteSections.map((section) => ({
        componentKey: section.componentKey,
        ...(section.variant ? { variant: section.variant } : {}),
        props: section.props ?? {},
      })),
    })
    const registryIndex = getAllowedComponentCatalogIndex('business')

    const report = buildDesignQualityReport({
      blueprint,
      siteSections,
      tree,
      registryIndex,
    })

    const improved = improveDesignFromReport({
      blueprint,
      siteSections,
      tree,
      registryIndex,
      report,
    })

    const hero = improved.siteSections.find((section) => section.layoutType === 'hero')
    const cta = improved.siteSections.find((section) => section.layoutType === 'cta')
    const improvedTree = sectionPlanToComponentTree({
      sections: improved.siteSections.map((section) => ({
        componentKey: section.componentKey,
        ...(section.variant ? { variant: section.variant } : {}),
        props: section.props ?? {},
      })),
    })
    const improvedReport = buildDesignQualityReport({
      blueprint,
      siteSections: improved.siteSections,
      tree: improvedTree,
      registryIndex,
      threshold: report.threshold,
    })

    expect(improved.changesApplied.length).toBeGreaterThan(0)
    expect(hero?.props).toMatchObject({
      advanced: {
        padding_top: expect.any(Number),
        padding_bottom: expect.any(Number),
      },
    })
    expect(cta?.props?.buttonText).toBe('Book a visit')
    expect(cta?.props?.backgroundColor ?? cta?.props?.background_color).toBe('#1d4ed8')
    expect(improvedReport.overallScore).toBeGreaterThan(report.overallScore)
  })
})
