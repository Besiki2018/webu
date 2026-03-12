/**
 * Part 6 — Design Consistency Analyzer tests.
 */
import {
  analyzeDesignConsistency,
  type DesignSectionInput,
  type DesignConsistencyInput,
} from '../designConsistencyAnalyzer';

function section(localId: string, type: string, props?: Record<string, unknown>): DesignSectionInput {
  return { localId, type, props };
}

describe('designConsistencyAnalyzer', () => {
  it('reports CTA color inconsistent with brand and suggests use primary color', () => {
    const input: DesignConsistencyInput = {
      sections: [
        section('1', 'hero', { backgroundColor: '#0f172a' }),
        section('2', 'webu_general_cta_01', { backgroundColor: '#64748b' }),
      ],
      theme: { primaryColor: '#2563eb' },
      sectionKinds: ['hero', 'cta'],
    };
    const report = analyzeDesignConsistency(input);
    const ctaColor = report.issues.find(
      (i) => i.issue === 'CTA color inconsistent with brand' && i.category === 'color'
    );
    expect(ctaColor).toBeDefined();
    expect(ctaColor?.suggestedFix).toBe('use primary color');
    expect(ctaColor?.sectionKind).toBe('cta');
  });

  it('suggests use primary color when CTA has no background', () => {
    const input: DesignConsistencyInput = {
      sections: [section('c1', 'cta', {})],
      theme: { primaryColor: '#2563eb' },
      sectionKinds: ['cta'],
    };
    const report = analyzeDesignConsistency(input);
    const fix = report.issues.find((i) => i.suggestedFix.toLowerCase().includes('primary'));
    expect(fix).toBeDefined();
  });

  it('reports inconsistent spacing when many different padding values', () => {
    const input: DesignConsistencyInput = {
      sections: [
        section('1', 'a', { padding: 'none' }),
        section('2', 'b', { padding: 'sm' }),
        section('3', 'c', { padding: 'md' }),
        section('4', 'd', { padding: 'lg' }),
        section('5', 'e', { padding: 'xl' }),
      ],
    };
    const report = analyzeDesignConsistency(input);
    const spacing = report.issues.find((i) => i.category === 'spacing');
    expect(spacing).toBeDefined();
    expect(spacing?.suggestedFix).toMatch(/consistent|scale|padding/);
  });

  it('reports mixed button styles when multiple button variants', () => {
    const input: DesignConsistencyInput = {
      sections: [
        section('1', 'hero', { buttonVariant: 'primary' }),
        section('2', 'cta', { buttonVariant: 'outline' }),
        section('3', 'features', { buttonVariant: 'ghost' }),
      ],
    };
    const report = analyzeDesignConsistency(input);
    const buttons = report.issues.find((i) => i.category === 'buttons');
    expect(buttons).toBeDefined();
    expect(buttons?.issue).toMatch(/mixed button|button style/);
  });

  it('reports font mismatch when multiple font families', () => {
    const input: DesignConsistencyInput = {
      sections: [
        section('1', 'hero', { fontFamily: 'Inter' }),
        section('2', 'features', { fontFamily: 'Georgia' }),
        section('3', 'cta', { fontFamily: 'System' }),
      ],
      sectionKinds: ['hero', 'features', 'cta'],
    };
    const report = analyzeDesignConsistency(input);
    const font = report.issues.find((i) => i.category === 'typography' && i.issue.includes('font'));
    expect(font).toBeDefined();
    expect(font?.suggestedFix).toMatch(/font|theme/);
  });

  it('suggests theme font when theme.fontFamily provided', () => {
    const input: DesignConsistencyInput = {
      sections: [
        section('1', 'a', { fontFamily: 'Arial' }),
        section('2', 'b', { fontFamily: 'Serif' }),
        section('3', 'c', { fontFamily: 'Mono' }),
      ],
      theme: { fontFamily: 'Inter' },
    };
    const report = analyzeDesignConsistency(input);
    const typo = report.issues.find((i) => i.category === 'typography' && i.issue.includes('font'));
    expect(typo).toBeDefined();
    expect(typo?.suggestedFix).toContain('Inter');
  });

  it('returns summary when no issues', () => {
    const report = analyzeDesignConsistency({ sections: [] });
    expect(report.summary).toContain('good');
  });
});
