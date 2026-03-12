import {
  isImprovementCommand,
  runChatImprovement,
  applyOptimizationStepsToSections,
  formatImprovementSummary,
} from '../chatImprovementCommands';

describe('chatImprovementCommands', () => {
  describe('isImprovementCommand', () => {
    it('returns true for "Improve this page"', () => {
      expect(isImprovementCommand('Improve this page')).toBe(true);
      expect(isImprovementCommand('  improve this page  ')).toBe(true);
    });

    it('returns true for "Optimize layout"', () => {
      expect(isImprovementCommand('Optimize layout')).toBe(true);
      expect(isImprovementCommand('optimize the layout')).toBe(true);
    });

    it('returns true for "Make design modern"', () => {
      expect(isImprovementCommand('Make design modern')).toBe(true);
    });

    it('returns true for "Improve hero section"', () => {
      expect(isImprovementCommand('Improve hero section')).toBe(true);
      expect(isImprovementCommand('improve the hero section')).toBe(true);
    });

    it('returns false for unrelated messages', () => {
      expect(isImprovementCommand('Change the title')).toBe(false);
      expect(isImprovementCommand('Add a button')).toBe(false);
      expect(isImprovementCommand('')).toBe(false);
      expect(isImprovementCommand('hi')).toBe(false);
    });
  });

  describe('runChatImprovement', () => {
    it('returns report with transformations for sections', () => {
      const sections = [
        { localId: 's1', type: 'hero-1', props: { variant: 'hero-1' } },
        { localId: 's2', type: 'features-1', props: {} },
      ];
      const report = runChatImprovement(sections);
      expect(report.transformations).toBeDefined();
      expect(Array.isArray(report.transformations)).toBe(true);
      expect(report.summary).toBeDefined();
    });
  });

  describe('applyOptimizationStepsToSections', () => {
    it('applies replaceVariant patch to section', () => {
      const sections = [
        { localId: 's1', type: 'hero', props: { variant: 'hero-1' }, propsText: '{"variant":"hero-1"}' },
      ];
      const steps = [
        { type: 'replaceVariant' as const, sectionId: 's1', sectionKind: 'hero', from: 'hero-1', to: 'hero-4', patch: { variant: 'hero-4' } },
      ];
      const result = applyOptimizationStepsToSections(sections, steps);
      expect(result).toHaveLength(1);
      expect(result[0].props.variant).toBe('hero-4');
    });

    it('applies addSection', () => {
      const sections = [
        { localId: 's1', type: 'hero', props: {}, propsText: '{}' },
      ];
      const steps = [
        { type: 'addSection' as const, sectionKind: 'testimonials', suggestedType: 'webu_testimonials_01' },
      ];
      const result = applyOptimizationStepsToSections(sections, steps);
      expect(result).toHaveLength(2);
      expect(result[1].type).toBe('webu_testimonials_01');
      expect(result[1].localId).toMatch(/^chat-improve-/);
    });
  });

  describe('formatImprovementSummary', () => {
    it('returns message when appliedCount is 0', () => {
      expect(formatImprovementSummary([], 0)).toContain('No changes');
    });

    it('includes upgrade labels when steps applied', () => {
      const steps = [
        { type: 'replaceVariant' as const, sectionId: 's1', sectionKind: 'hero', from: 'hero-1', to: 'hero-4', patch: {} },
      ];
      expect(formatImprovementSummary(steps, 1)).toContain('Upgraded');
    });
  });
});
