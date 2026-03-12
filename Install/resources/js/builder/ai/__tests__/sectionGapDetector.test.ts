/**
 * Part 5 — Missing Section Detection tests.
 */
import { detectSectionGaps, type SectionInput, type SectionGapDetectorInput } from '../sectionGapDetector';

function section(localId: string, type: string, props?: Record<string, unknown>): SectionInput {
  return { localId, type, props };
}

describe('sectionGapDetector', () => {
  it('landing page missing testimonials and CTA → suggests add testimonials section, add CTA section', () => {
    const input: SectionGapDetectorInput = {
      sections: [
        section('1', 'webu_header_01'),
        section('2', 'webu_general_hero_01'),
        section('3', 'webu_general_features_01'),
        section('4', 'webu_footer_01'),
      ],
      pageType: 'landing',
    };
    const report = detectSectionGaps(input);
    expect(report.pageType).toBe('landing');
    expect(report.missing.some((m) => m.sectionKind === 'social_proof' || m.message.includes('social proof'))).toBe(true);
    expect(report.missing.some((m) => m.sectionKind === 'cta' || m.message.includes('CTA'))).toBe(true);
    expect(report.missing.every((m) => m.message.startsWith('add ') && m.message.endsWith(' section'))).toBe(true);
    expect(report.missing.every((m) => m.suggestedType)).toBe(true);
  });

  it('landing page missing CTA → suggests add CTA section', () => {
    const input: SectionGapDetectorInput = {
      sectionKinds: ['header', 'hero', 'features', 'social_proof', 'footer'],
      pageType: 'landing',
      sections: [],
    };
    const report = detectSectionGaps(input);
    const cta = report.missing.find((m) => m.sectionKind === 'cta');
    expect(cta).toBeDefined();
    expect(cta?.message).toBe('add CTA section');
    expect(cta?.suggestedType).toBe('webu_general_cta_01');
  });

  it('landing page missing testimonials → suggests add social proof / testimonials', () => {
    const input: SectionGapDetectorInput = {
      sectionKinds: ['header', 'hero', 'features', 'cta', 'footer'],
      pageType: 'landing',
      sections: [],
    };
    const report = detectSectionGaps(input);
    const testimonial = report.missing.find((m) => m.sectionKind === 'social_proof');
    expect(testimonial).toBeDefined();
    expect(testimonial?.message).toMatch(/add .* section/);
  });

  it('when no sections missing, missing array is empty', () => {
    const input: SectionGapDetectorInput = {
      sectionKinds: ['header', 'hero', 'features', 'social_proof', 'cta', 'footer'],
      pageType: 'landing',
      sections: [],
    };
    const report = detectSectionGaps(input);
    expect(report.missing).toHaveLength(0);
    expect(report.present.length).toBe(6);
  });

  it('summary mentions missing sections when gaps exist', () => {
    const report = detectSectionGaps({
      sections: [section('1', 'hero')],
      pageType: 'landing',
    });
    expect(report.summary).toBeDefined();
    expect(report.summary).toContain('missing');
  });

  it('defaults to landing page type', () => {
    const report = detectSectionGaps({ sections: [] });
    expect(report.pageType).toBe('landing');
  });
});
