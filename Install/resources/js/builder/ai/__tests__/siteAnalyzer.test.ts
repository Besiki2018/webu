/**
 * Part 1 — Site Analyzer tests.
 */
import { analyzeSite, type SectionInput, type BuilderStateInput } from '../siteAnalyzer';

function section(localId: string, type: string, props?: Record<string, unknown>): SectionInput {
  return { localId, type, props };
}

describe('siteAnalyzer', () => {
  it('returns issues for page with header, hero, features, footer (missing CTA)', () => {
    const state: BuilderStateInput = {
      sections: [
        section('1', 'webu_header_01', { logoText: 'Logo' }),
        section('2', 'webu_general_hero_01', { title: 'Hero', subtitle: 'Sub' }),
        section('3', 'webu_general_features_01', { title: 'Features' }),
        section('4', 'webu_footer_01', { copyright: '©' }),
      ],
    };
    const report = analyzeSite(state);
    expect(report.issues).toContain('missing CTA section');
    expect(report.issues).not.toContain('missing header');
    expect(report.issues).not.toContain('missing hero section');
    expect(report.issues).not.toContain('missing footer');
    expect(report.sectionKinds).toEqual(['header', 'hero', 'features', 'footer']);
    expect(report.stats?.hasHeader).toBe(true);
    expect(report.stats?.hasCta).toBe(false);
  });

  it('returns missing sections when page has only hero', () => {
    const state: BuilderStateInput = {
      sections: [section('1', 'hero', { title: 'Hero' })],
    };
    const report = analyzeSite(state);
    expect(report.issues).toContain('missing header');
    expect(report.issues).toContain('missing footer');
    expect(report.issues).toContain('missing CTA section');
    expect(report.issues.some((i) => i.includes('hierarchy'))).toBe(true);
  });

  it('reports hero layout too simple when hero has few props', () => {
    const state: BuilderStateInput = {
      sections: [
        section('1', 'header'),
        section('2', 'hero', { title: 'Only title' }),
        section('3', 'footer', { copyright: '©', links: [] }),
      ],
    };
    const report = analyzeSite(state);
    expect(report.issues).toContain('hero layout too simple');
  });

  it('reports footer incomplete when footer has minimal props', () => {
    const state: BuilderStateInput = {
      sections: [
        section('1', 'header'),
        section('2', 'hero', { title: 'H', subtitle: 'S' }),
        section('3', 'footer', { copyright: '©' }),
      ],
    };
    const report = analyzeSite(state);
    expect(report.issues).toContain('footer incomplete');
  });

  it('reports weak hierarchy when footer is not last', () => {
    const state: BuilderStateInput = {
      sections: [
        section('1', 'footer'),
        section('2', 'hero'),
        section('3', 'header'),
      ],
    };
    const report = analyzeSite(state);
    expect(report.issues.some((i) => i.includes('top') || i.includes('hierarchy'))).toBe(true);
    expect(report.issues.some((i) => i.includes('end') || i.includes('footer'))).toBe(true);
  });

  it('reports design inconsistencies for duplicate headers/footers', () => {
    const state: BuilderStateInput = {
      sections: [
        section('1', 'header'),
        section('2', 'header'),
        section('3', 'footer'),
        section('4', 'footer'),
      ],
    };
    const report = analyzeSite(state);
    expect(report.issues).toContain('design inconsistency: multiple headers');
    expect(report.issues.some((i) => i.includes('footer'))).toBe(true);
  });

  it('example output shape: issues array with expected strings', () => {
    const state: BuilderStateInput = {
      sections: [
        section('1', 'header'),
        section('2', 'hero', { title: 'H' }),
        section('3', 'features'),
        section('4', 'footer', {}),
      ],
    };
    const report = analyzeSite(state);
    expect(report).toMatchObject({
      issues: expect.any(Array),
    });
    expect(report.issues.every((i) => typeof i === 'string')).toBe(true);
    expect(report.issues).toContain('missing CTA section');
    expect(report.issues).toContain('hero layout too simple');
    expect(report.issues).toContain('footer incomplete');
  });

  it('empty sections returns page has no sections and missing sections', () => {
    const report = analyzeSite({ sections: [] });
    expect(report.issues).toContain('page has no sections');
    expect(report.stats?.totalSections).toBe(0);
  });
});
