import {
  buildImagePrompt,
  generateImage,
  injectImageIntoProps,
  HERO_IMAGE_PROMPT_EXAMPLE,
  type ImageGeneratorProvider,
} from '../imageGenerator';

describe('imageGenerator', () => {
  describe('buildImagePrompt', () => {
    it('builds hero prompt with industry and tone', () => {
      const prompt = buildImagePrompt({
        sectionType: 'hero',
        industry: 'furniture',
        tone: 'modern',
      });
      expect(prompt).toContain('modern');
      expect(prompt).toContain('furniture');
    });

    it('uses general template for unknown section type', () => {
      const prompt = buildImagePrompt({
        sectionType: 'general',
        industry: 'tech',
        tone: 'minimal',
      });
      expect(prompt.length).toBeGreaterThan(0);
    });

    it('includes customPhrase when provided', () => {
      const prompt = buildImagePrompt({
        sectionType: 'hero',
        industry: 'furniture',
        tone: 'modern',
        customPhrase: 'living room interior',
      });
      expect(prompt).toContain('living room interior');
    });
  });

  describe('HERO_IMAGE_PROMPT_EXAMPLE', () => {
    it('matches example hero prompt', () => {
      expect(HERO_IMAGE_PROMPT_EXAMPLE).toBe('modern living room furniture interior');
    });
  });

  describe('generateImage', () => {
    it('returns URL from provider', async () => {
      const provider: ImageGeneratorProvider = async () => 'https://example.com/generated.png';
      const url = await generateImage('modern furniture interior', provider);
      expect(url).toBe('https://example.com/generated.png');
    });
  });

  describe('injectImageIntoProps', () => {
    it('merges image URL into props under given key', () => {
      const props = { title: 'Hero', subtitle: 'Welcome' };
      const out = injectImageIntoProps(props, 'https://example.com/hero.jpg', 'image');
      expect(out).toMatchObject({
        title: 'Hero',
        subtitle: 'Welcome',
        image: 'https://example.com/hero.jpg',
      });
    });

    it('uses backgroundImage when propKey is backgroundImage', () => {
      const out = injectImageIntoProps({}, 'https://example.com/bg.jpg', 'backgroundImage');
      expect(out.backgroundImage).toBe('https://example.com/bg.jpg');
    });
  });
});
