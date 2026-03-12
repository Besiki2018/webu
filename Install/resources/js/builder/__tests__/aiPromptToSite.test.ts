import {
  PROMPT_TO_SITE_HINTS,
  getDefaultStructureForPrompt,
} from '../aiPromptToSite';
import { DEFAULT_ECOMMERCE_STRUCTURE, DEFAULT_SAAS_LANDING_STRUCTURE, DEFAULT_LANDING_STRUCTURE } from '../aiSiteGeneration';

describe('aiPromptToSite', () => {
  describe('PROMPT_TO_SITE_HINTS', () => {
    it('includes ecommerce, saas, landing', () => {
      expect(PROMPT_TO_SITE_HINTS.ecommerce.projectType).toBe('ecommerce');
      expect(PROMPT_TO_SITE_HINTS.saas.projectType).toBe('saas');
      expect(PROMPT_TO_SITE_HINTS.landing.projectType).toBe('landing');
    });
  });

  describe('getDefaultStructureForPrompt', () => {
    it('returns ecommerce structure for ecommerce', () => {
      const s = getDefaultStructureForPrompt('ecommerce');
      expect(s).toHaveLength(DEFAULT_ECOMMERCE_STRUCTURE.length);
      expect(s[0]?.componentKey).toBe('webu_header_01');
    });

    it('returns saas structure for saas', () => {
      const s = getDefaultStructureForPrompt('saas');
      expect(s).toHaveLength(DEFAULT_SAAS_LANDING_STRUCTURE.length);
    });

    it('returns landing structure for landing', () => {
      const s = getDefaultStructureForPrompt('landing');
      expect(s).toHaveLength(DEFAULT_LANDING_STRUCTURE.length);
    });

    it('returns landing structure for unknown project type', () => {
      const s = getDefaultStructureForPrompt('business');
      expect(s).toHaveLength(DEFAULT_LANDING_STRUCTURE.length);
    });
  });
});
