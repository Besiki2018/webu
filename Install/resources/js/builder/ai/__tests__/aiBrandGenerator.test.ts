import {
  promptToDesignSystemInput,
  generateDesignSystemFromPrompt,
} from '../aiBrandGenerator';

describe('aiBrandGenerator', () => {
  describe('promptToDesignSystemInput', () => {
    it('maps "luxury furniture brand" to elegant + furniture', () => {
      const input = promptToDesignSystemInput('Create design system for luxury furniture brand');
      expect(input.designStyle).toBe('elegant');
      expect(input.industry).toBe('furniture');
    });

    it('maps "minimal saas" to minimal + saas', () => {
      const input = promptToDesignSystemInput('minimal saas website');
      expect(input.projectType).toBe('saas');
      expect(input.designStyle).toBe('minimal');
    });

    it('extracts brand name when present', () => {
      const input = promptToDesignSystemInput('Create design system for Acme brand');
      expect(input.brandName).toBeDefined();
    });
  });

  describe('generateDesignSystemFromPrompt', () => {
    it('returns full design system for luxury furniture', () => {
      const system = generateDesignSystemFromPrompt('Create design system for luxury furniture brand');
      expect(system.colors).toBeDefined();
      expect(system.colors.primary).toBeDefined();
      expect(system.typography).toBeDefined();
      expect(system.spacing).toBeDefined();
      expect(system.radius).toBeDefined();
      expect(system.shadows).toBeDefined();
      expect(system.buttonStyles).toBeDefined();
    });

    it('empty prompt returns default modern system', () => {
      const system = generateDesignSystemFromPrompt('');
      expect(system.colors.primary).toBeDefined();
      expect(system.spacing.md).toBeDefined();
    });
  });
});
