/**
 * Part 4 — Component Improvement Suggestions tests.
 */
import {
  suggestComponentImprovements,
  getVariantUpgrade,
  type ComponentSectionInput,
  type ComponentImproverInput,
} from '../componentImprover';

function section(localId: string, type: string, props?: Record<string, unknown>): ComponentSectionInput {
  return { localId, type, props };
}

describe('componentImprover', () => {
  it('suggests hero1 → hero3 upgrade', () => {
    const input: ComponentImproverInput = {
      sections: [
        section('h1', 'webu_general_hero_01', { variant: 'hero-1' }),
      ],
    };
    const report = suggestComponentImprovements(input);
    const upgrade = report.suggestions.find((s) => s.title === 'Upgrade hero variant');
    expect(upgrade).toBeDefined();
    expect(upgrade?.suggestedPatch).toEqual({ variant: 'hero-3' });
    expect(upgrade?.sectionKind).toBe('hero');
  });

  it('suggests hero2 → hero3', () => {
    const input: ComponentImproverInput = {
      sections: [section('h1', 'hero', { variant: 'hero-2' })],
    };
    const report = suggestComponentImprovements(input);
    const upgrade = report.suggestions.find((s) => s.title === 'Upgrade hero variant');
    expect(upgrade?.suggestedPatch.variant).toBe('hero-3');
  });

  it('suggests add icons to features (variant upgrade)', () => {
    const input: ComponentImproverInput = {
      sections: [section('f1', 'webu_general_features_01', { variant: 'features-1' })],
    };
    const report = suggestComponentImprovements(input);
    const icons = report.suggestions.find((s) => s.title === 'Add icons to features');
    expect(icons).toBeDefined();
    expect(icons?.suggestedPatch.variant).toBe('features-2');
  });

  it('suggests add background pattern for hero when no background', () => {
    const input: ComponentImproverInput = {
      sections: [section('h1', 'hero', { variant: 'hero-1' })],
    };
    const report = suggestComponentImprovements(input);
    const bg = report.suggestions.find((s) => s.title === 'Add background pattern');
    expect(bg).toBeDefined();
    expect(bg?.suggestedPatch.backgroundColor).toBeDefined();
  });

  it('suggests improve CTA visibility (variant upgrade)', () => {
    const input: ComponentImproverInput = {
      sections: [section('c1', 'webu_general_cta_01', { variant: 'cta-1' })],
    };
    const report = suggestComponentImprovements(input);
    const cta = report.suggestions.find((s) => s.title === 'Improve CTA visibility');
    expect(cta).toBeDefined();
    expect(cta?.suggestedPatch.variant).toBe('cta-2');
  });

  it('getVariantUpgrade returns hero-3 for hero-1', () => {
    expect(getVariantUpgrade('hero', 'hero-1')).toBe('hero-3');
    expect(getVariantUpgrade('hero', 'hero-2')).toBe('hero-3');
    expect(getVariantUpgrade('hero', 'hero-3')).toBeNull();
  });

  it('getVariantUpgrade returns features-2 for features-1', () => {
    expect(getVariantUpgrade('features', 'features-1')).toBe('features-2');
  });

  it('does not suggest hero upgrade when already hero-3', () => {
    const input: ComponentImproverInput = {
      sections: [section('h1', 'hero', { variant: 'hero-3' })],
    };
    const report = suggestComponentImprovements(input);
    const upgrade = report.suggestions.find((s) => s.title === 'Upgrade hero variant');
    expect(upgrade).toBeUndefined();
  });

  it('returns summary with count', () => {
    const report = suggestComponentImprovements({
      sections: [
        section('h1', 'hero', { variant: 'hero-1' }),
        section('c1', 'cta', { variant: 'cta-1' }),
      ],
    });
    expect(report.summary).toBeDefined();
    expect(report.suggestions.length).toBeGreaterThan(0);
  });
});
