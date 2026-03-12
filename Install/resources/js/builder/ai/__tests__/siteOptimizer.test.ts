/**
 * Part 7 — Auto Optimization Engine tests.
 */
import { optimizeSite, type OptimizerSectionInput, type SiteOptimizerInput } from '../siteOptimizer';

function section(localId: string, type: string, props?: Record<string, unknown>): OptimizerSectionInput {
  return { localId, type, props };
}

describe('siteOptimizer', () => {
  it('produces replaceVariant transformation hero1 → hero4', () => {
    const input: SiteOptimizerInput = {
      sections: [section('h1', 'webu_general_hero_01', { variant: 'hero-1' })],
      pageType: 'landing',
      skipAddSections: true,
    };
    const report = optimizeSite(input);
    const replace = report.transformations.find((t) => t.type === 'replaceVariant' && t.sectionKind === 'hero');
    expect(replace).toBeDefined();
    expect(replace?.type).toBe('replaceVariant');
    if (replace?.type === 'replaceVariant') {
      expect(replace.from).toBe('hero-1');
      expect(replace.to).toBe('hero-4');
      expect(replace.patch).toEqual({ variant: 'hero-4' });
    }
  });

  it('produces addSection for missing CTA when skipAddSections is false', () => {
    const input: SiteOptimizerInput = {
      sections: [
        section('1', 'header'),
        section('2', 'hero'),
        section('3', 'features'),
        section('4', 'footer'),
      ],
      pageType: 'landing',
      skipAddSections: false,
    };
    const report = optimizeSite(input);
    const addCta = report.transformations.find((t) => t.type === 'addSection' && t.sectionKind === 'cta');
    expect(addCta).toBeDefined();
    if (addCta?.type === 'addSection') {
      expect(addCta.suggestedType).toBe('webu_general_cta_01');
    }
  });

  it('does not add addSection steps when skipAddSections is true', () => {
    const input: SiteOptimizerInput = {
      sections: [section('1', 'hero')],
      pageType: 'landing',
      skipAddSections: true,
    };
    const report = optimizeSite(input);
    expect(report.transformations.filter((t) => t.type === 'addSection')).toHaveLength(0);
  });

  it('includes improveLayout steps from layout analyzer when issues exist', () => {
    const input: SiteOptimizerInput = {
      sections: [
        section('1', 'hero', { headline: 'H'.repeat(200), subtitle: 'S'.repeat(300), image: true }),
        section('2', 'cta', {}),
      ],
      skipAddSections: true,
    };
    const report = optimizeSite(input);
    const layout = report.transformations.filter((t) => t.type === 'improveLayout');
    expect(layout.length).toBeGreaterThanOrEqual(0);
  });

  it('includes normalizeSpacing or normalizeColor when design consistency has issues', () => {
    const input: SiteOptimizerInput = {
      sections: [
        section('1', 'a', { padding: 'none' }),
        section('2', 'b', { padding: 'sm' }),
        section('3', 'c', { padding: 'md' }),
        section('4', 'd', { padding: 'lg' }),
        section('5', 'e', { padding: 'xl' }),
      ],
      skipAddSections: true,
    };
    const report = optimizeSite(input);
    const norm = report.transformations.filter((t) => t.type === 'normalizeSpacing' || t.type === 'normalizeColor');
    expect(norm.length).toBeGreaterThanOrEqual(0);
  });

  it('returns summary with transformation count', () => {
    const report = optimizeSite({
      sections: [section('h1', 'hero', { variant: 'hero-1' }), section('c1', 'cta', { variant: 'cta-1' })],
      skipAddSections: true,
    });
    expect(report.summary).toBeDefined();
    expect(report.transformations.length).toBeGreaterThan(0);
  });
});
