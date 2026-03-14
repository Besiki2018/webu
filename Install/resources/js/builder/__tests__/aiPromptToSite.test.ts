import { promptToSite } from '../aiPromptToSite';

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
      expect(vet.generationLog.some((entry) => entry.step === 'fallback')).toBe(false);
      expect(vet.structure.length).toBeGreaterThan(0);
      expect(vet.structure.map((section) => section.componentKey)).not.toEqual(
        saas.structure.map((section) => section.componentKey),
      );
    });
  });
});
