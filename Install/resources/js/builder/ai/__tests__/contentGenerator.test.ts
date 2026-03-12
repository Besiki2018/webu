import {
  generateContent,
  generateHeroContent,
  buildPrompt,
  type ContentGeneratorProvider,
  type GenerateContentInput,
} from '../contentGenerator';

describe('contentGenerator', () => {
  const mockProvider: ContentGeneratorProvider = async (prompt, options) => {
    return JSON.stringify({
      title: 'Beautiful Furniture for Modern Living',
      subtitle: 'Discover handcrafted pieces designed for comfort and style.',
      cta: 'Shop Now',
    });
  };

  const heroInput: GenerateContentInput = {
    sectionType: 'hero',
    projectType: 'ecommerce',
    industry: 'furniture',
    tone: 'modern',
    language: 'en',
  };

  describe('buildPrompt', () => {
    it('is a function (exported for testing)', () => {
      expect(typeof buildPrompt).toBe('function');
    });
  });

  describe('generateContent', () => {
    it('returns parsed hero content when provider returns valid JSON', async () => {
      const result = await generateContent(heroInput, mockProvider);
      expect(result).toMatchObject({
        title: 'Beautiful Furniture for Modern Living',
        subtitle: 'Discover handcrafted pieces designed for comfort and style.',
        cta: 'Shop Now',
      });
    });

    it('extracts JSON from markdown-wrapped response', async () => {
      const provider: ContentGeneratorProvider = async () =>
        'Here is the content:\n```json\n{"title": "Test", "subtitle": "Sub", "cta": "Go"}\n```';
      const result = await generateContent(heroInput, provider);
      expect(result).toMatchObject({ title: 'Test', subtitle: 'Sub', cta: 'Go' });
    });
  });

  describe('generateHeroContent', () => {
    it('returns HeroContentResult', async () => {
      const result = await generateHeroContent(
        { projectType: 'ecommerce', industry: 'furniture', language: 'en' },
        mockProvider
      );
      expect(result.title).toBeDefined();
      expect(result.subtitle).toBeDefined();
      expect(result.cta).toBeDefined();
    });
  });
});
