import {
  promptToSite,
  getDefaultStructureForPrompt,
} from '../aiPromptToSite';

describe('aiPromptToSite', () => {
  describe('promptToSite', () => {
    it('adapts prompts through the blueprint pipeline', () => {
      const vet = promptToSite({
        userPrompt: 'Create a modern vet clinic website for premium pet care',
      });
      const saas = promptToSite({
        userPrompt: 'Create a minimalist SaaS landing page for finance teams',
      });

      expect(vet.blueprint.projectType).toBe('business');
      expect(saas.blueprint.projectType).toBe('saas');
      expect(vet.generationLog.some((entry) => entry.step === 'blueprint')).toBe(true);
      expect(vet.structure.map((section) => section.componentKey)).not.toEqual(
        saas.structure.map((section) => section.componentKey),
      );
    });
  });

  describe('getDefaultStructureForPrompt', () => {
    it('returns ecommerce structure for ecommerce', () => {
      const s = getDefaultStructureForPrompt('ecommerce');
      expect(s.length).toBeGreaterThan(0);
      expect(s[0]?.componentKey).toBe('webu_header_01');
    });

    it('returns saas structure for saas', () => {
      const s = getDefaultStructureForPrompt('saas');
      expect(s.some((section) => section.componentKey === 'webu_general_hero_01')).toBe(true);
    });

    it('returns landing structure for landing', () => {
      const s = getDefaultStructureForPrompt('landing');
      expect(s.some((section) => section.componentKey === 'webu_general_cta_01')).toBe(true);
    });

    it('returns landing structure for unknown project type', () => {
      const s = getDefaultStructureForPrompt('business');
      expect(s.length).toBeGreaterThan(0);
    });
  });
});
