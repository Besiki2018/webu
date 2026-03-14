import { describe, expect, it } from 'vitest';
import type { AiComponentCatalogEntry } from '../componentCatalog';
import { scoreComponentForSection } from '../componentScoring';
import type { NormalizedBlueprintSection, ProjectBlueprint } from '../blueprintTypes';

function createBlueprint(overrides: Partial<ProjectBlueprint> = {}): ProjectBlueprint {
  return {
    projectType: 'business',
    businessType: 'Vet clinic',
    audience: 'pet owners seeking trusted care',
    tone: 'premium',
    styleKeywords: ['premium', 'clean', 'medical', 'trust'],
    pageGoal: 'Drive appointment requests',
    sections: [],
    restrictions: {
      noPricing: true,
      noTestimonials: false,
      onePageOnly: true,
    },
    ...overrides,
  };
}

function createSection(sectionType: string): NormalizedBlueprintSection {
  return {
    sectionType,
    priority: 20,
    required: true,
    contentBrief: {
      emphasis: sectionType,
    },
  };
}

function createEntry(overrides: Partial<AiComponentCatalogEntry>): AiComponentCatalogEntry {
  return {
    componentKey: 'webu_general_hero_01',
    label: 'Hero Section',
    category: 'general',
    projectTypesAllowed: ['business', 'landing'],
    layoutType: 'hero',
    sectionType: 'hero',
    categoryTags: ['medical', 'clinic', 'services'],
    styleTags: ['premium', 'clean', 'trust'],
    priorityScore: 80,
    propsSchema: [
      { path: 'title', type: 'text', label: 'Title', group: 'content' },
      { path: 'subtitle', type: 'richtext', label: 'Subtitle', group: 'content' },
      { path: 'buttonText', type: 'link', label: 'CTA', group: 'content' },
    ],
    defaultProps: {
      title: 'Trusted care',
    },
    variants: [
      { id: 'hero-3', label: 'Premium Hero', kind: 'layout' },
      { id: 'hero-2', label: 'Clean Hero', kind: 'layout' },
    ],
    responsiveEnabled: true,
    supportsResponsiveOverrides: true,
    supportsVisibility: true,
    capabilities: ['appointment', 'trust', 'care'],
    ...overrides,
  };
}

describe('componentScoring', () => {
  it('rewards components that match project, business, and style signals', () => {
    const blueprint = createBlueprint();
    const section = createSection('hero');

    const highFit = scoreComponentForSection({
      entry: createEntry({ componentKey: 'vet_hero' }),
      blueprint,
      section,
      sectionIndex: 1,
      totalSections: 6,
      compatibleSectionTypes: ['hero', 'banner'],
      usedComponentKeys: new Set<string>(),
    });
    const weakFit = scoreComponentForSection({
      entry: createEntry({
        componentKey: 'generic_grid',
        projectTypesAllowed: ['portfolio'],
        sectionType: 'grid',
        categoryTags: ['portfolio', 'gallery'],
        styleTags: ['editorial'],
        capabilities: ['showcase'],
        responsiveEnabled: false,
        supportsResponsiveOverrides: false,
        supportsVisibility: false,
      }),
      blueprint,
      section,
      sectionIndex: 1,
      totalSections: 6,
      compatibleSectionTypes: ['hero', 'banner'],
      usedComponentKeys: new Set<string>(),
    });

    expect(highFit.total).toBeGreaterThan(weakFit.total);
    expect(highFit.businessTypeMatch).toBeGreaterThan(weakFit.businessTypeMatch);
    expect(highFit.styleKeywordMatch).toBeGreaterThan(weakFit.styleKeywordMatch);
    expect(highFit.mobileFriendliness).toBeGreaterThan(weakFit.mobileFriendliness);
  });

  it('applies a repetition penalty when the same component key was already used', () => {
    const blueprint = createBlueprint({
      businessType: 'Finance consulting firm',
      audience: 'finance leaders',
      styleKeywords: ['premium', 'trust'],
      pageGoal: 'Book strategy calls',
    });
    const entry = createEntry({
      componentKey: 'webu_general_cta_01',
      layoutType: 'cta',
      sectionType: 'cta',
      categoryTags: ['services', 'professional'],
      capabilities: ['lead', 'consultation'],
    });
    const section = createSection('cta');

    const fresh = scoreComponentForSection({
      entry,
      blueprint,
      section,
      sectionIndex: 5,
      totalSections: 7,
      compatibleSectionTypes: ['cta', 'banner', 'form'],
      usedComponentKeys: new Set<string>(),
    });
    const repeated = scoreComponentForSection({
      entry,
      blueprint,
      section,
      sectionIndex: 5,
      totalSections: 7,
      compatibleSectionTypes: ['cta', 'banner', 'form'],
      usedComponentKeys: new Set<string>(['webu_general_cta_01']),
    });

    expect(repeated.repetitionPenalty).toBeGreaterThan(0);
    expect(repeated.total).toBeLessThan(fresh.total);
  });
});
