/**
 * Part 3 — Layout Balance Detection tests.
 */
import { analyzeLayout, type LayoutSectionInput, type LayoutAnalyzerInput } from '../layoutAnalyzer';

function section(localId: string, type: string, props?: Record<string, unknown>): LayoutSectionInput {
  return { localId, type, props };
}

describe('layoutAnalyzer', () => {
  it('reports "hero too tall" when hero has heavy content', () => {
    const state: LayoutAnalyzerInput = {
      sections: [
        section('1', 'header'),
        section('2', 'webu_general_hero_01', {
          headline: 'A'.repeat(120),
          subtitle: 'B'.repeat(200),
          imageUrl: 'https://example.com/hero.jpg',
        }),
        section('3', 'footer'),
      ],
    };
    const report = analyzeLayout(state);
    const heroTall = report.issues.find((i) => i.issue === 'hero too tall');
    expect(heroTall).toBeDefined();
    expect(heroTall?.category).toBe('hierarchy');
    expect(heroTall?.proposedFix).toContain('compact');
  });

  it('reports "features too crowded" when many items', () => {
    const state: LayoutAnalyzerInput = {
      sections: [
        section('1', 'hero', { title: 'Hero' }),
        section('2', 'webu_general_features_01', {
          title: 'Features',
          items: Array(12).fill({ title: 'F', description: 'D' }),
        }),
        section('3', 'footer'),
      ],
    };
    const report = analyzeLayout(state);
    const crowded = report.issues.find((i) => i.issue === 'features too crowded');
    expect(crowded).toBeDefined();
    expect(crowded?.category).toBe('grid');
    expect(crowded?.proposedFix).toContain('fewer');
  });

  it('reports "cta too small" when CTA has minimal content', () => {
    const state: LayoutAnalyzerInput = {
      sections: [
        section('1', 'hero', { title: 'H' }),
        section('2', 'webu_general_cta_01', {}),
        section('3', 'footer'),
      ],
    };
    const report = analyzeLayout(state);
    const ctaSmall = report.issues.find((i) => i.issue === 'cta too small');
    expect(ctaSmall).toBeDefined();
    expect(ctaSmall?.sectionKind).toBe('cta');
    expect(ctaSmall?.proposedFix).toContain('headline');
  });

  it('reports section spacing when many sections lack padding', () => {
    const state: LayoutAnalyzerInput = {
      sections: Array(5)
        .fill(0)
        .map((_, i) => section(`s${i}`, 'features', { title: 'S' })),
    };
    const report = analyzeLayout(state);
    const spacing = report.issues.find((i) => i.category === 'spacing');
    expect(spacing).toBeDefined();
  });

  it('reports "too many sections" when more than 10', () => {
    const state: LayoutAnalyzerInput = {
      sections: Array(12).fill(0).map((_, i) => section(`s${i}`, 'features', { padding: 'none' })),
    };
    const report = analyzeLayout(state);
    const many = report.issues.find((i) => i.issue.includes('too many sections'));
    expect(many).toBeDefined();
  });

  it('accepts precomputed sectionKinds', () => {
    const state: LayoutAnalyzerInput = {
      sections: [
        section('1', 'custom_hero', { headline: 'X'.repeat(200), subtitle: 'Y'.repeat(300), image: true }),
        section('2', 'custom_features', { items: Array(10).fill({}) }),
      ],
      sectionKinds: ['hero', 'features'],
    };
    const report = analyzeLayout(state);
    expect(report.issues.some((i) => i.issue === 'hero too tall')).toBe(true);
    expect(report.issues.some((i) => i.issue === 'features too crowded')).toBe(true);
  });

  it('returns summary when issues exist', () => {
    const state: LayoutAnalyzerInput = {
      sections: [section('1', 'cta', {})],
    };
    const report = analyzeLayout(state);
    expect(report.summary).toBeDefined();
    expect(report.summary).toContain('issue');
  });

  it('returns summary; when no issues summary contains "good"', () => {
    const report = analyzeLayout({ sections: [] });
    expect(report.summary).toBeDefined();
    expect(report.issues).toHaveLength(0);
    expect(report.summary).toContain('good');
  });
});
