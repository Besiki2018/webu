import {
  extractContentFromDesign,
  rawToExtracted,
  type DesignExtractionProvider,
  type ExtractedDesignContent,
} from '../designContentExtractor';
import type { ContentGeneratorProvider } from '../contentGenerator';

describe('designContentExtractor', () => {
  describe('rawToExtracted', () => {
    it('maps raw extraction to ExtractedDesignContent', () => {
      const raw = {
        titles: ['Main Headline'],
        subtitles: ['Supporting line here'],
        buttons: ['Get started', 'Learn more'],
        textBlocks: ['Paragraph one.'],
        imageUrls: ['https://example.com/hero.png'],
      };
      const out = rawToExtracted(raw);
      expect(out.title).toBe('Main Headline');
      expect(out.subtitle).toBe('Supporting line here');
      expect(out.ctaText).toBe('Get started');
      expect(out.ctaSecondary).toBe('Learn more');
      expect(out.images).toEqual(['https://example.com/hero.png']);
      expect(out.textBlocks).toEqual(['Paragraph one.']);
    });

    it('uses first title and first button', () => {
      const out = rawToExtracted({
        titles: ['First', 'Second'],
        buttons: ['CTA'],
      });
      expect(out.title).toBe('First');
      expect(out.ctaText).toBe('CTA');
    });

    it('returns empty-ish object when raw is empty', () => {
      const out = rawToExtracted({});
      expect(out.title).toBeUndefined();
      expect(out.subtitle).toBeUndefined();
      expect(out.ctaText).toBeUndefined();
    });
  });

  describe('extractContentFromDesign', () => {
    it('returns placeholder when no providers', async () => {
      const result = await extractContentFromDesign('data:image/png;base64,abc', {});
      expect(result.title).toBe('Your headline');
      expect(result.subtitle).toBeDefined();
      expect(result.ctaText).toBe('Get started');
    });

    it('uses extraction provider when provided and returns clear content', async () => {
      const extractionProvider: DesignExtractionProvider = async () => ({
        titles: ['Extracted Title'],
        subtitles: ['Extracted subtitle'],
        buttons: ['Sign up'],
      });
      const result = await extractContentFromDesign('data:image/png;base64,x', {
        extractionProvider,
      });
      expect(result.title).toBe('Extracted Title');
      expect(result.subtitle).toBe('Extracted subtitle');
      expect(result.ctaText).toBe('Sign up');
    });

    it('falls back to AI when extraction is unclear', async () => {
      const extractionProvider: DesignExtractionProvider = async () => ({
        titles: [],
        unclear: true,
      });
      const contentProvider: ContentGeneratorProvider = async () =>
        JSON.stringify({
          title: 'AI Generated Title',
          subtitle: 'AI generated subtitle.',
          cta: 'Start free trial',
        });
      const result = await extractContentFromDesign('data:image/png;base64,y', {
        extractionProvider,
        contentGeneratorProvider: contentProvider,
      });
      expect(result.title).toBe('AI Generated Title');
      expect(result.subtitle).toBe('AI generated subtitle.');
      expect(result.ctaText).toBe('Start free trial');
    });

    it('falls back to AI when extraction is empty', async () => {
      const extractionProvider: DesignExtractionProvider = async () => ({
        titles: [],
        subtitles: [],
        buttons: [],
      });
      const contentProvider: ContentGeneratorProvider = async () =>
        JSON.stringify({
          title: 'Fallback Title',
          subtitle: 'Fallback subtitle.',
          cta: 'Get started',
        });
      const result = await extractContentFromDesign('data:image/png;base64,z', {
        extractionProvider,
        contentGeneratorProvider: contentProvider,
      });
      expect(result.title).toBe('Fallback Title');
      expect(result.subtitle).toBe('Fallback subtitle.');
      expect(result.ctaText).toBe('Get started');
    });

    it('accepts File and converts to data URL for extraction', async () => {
      const file = new File(['fake'], 'design.png', { type: 'image/png' });
      const extractionProvider: DesignExtractionProvider = async (imageSource) => {
        expect(imageSource.startsWith('data:')).toBe(true);
        return { titles: ['From File'], buttons: ['Submit'] };
      };
      const result = await extractContentFromDesign(file, { extractionProvider });
      expect(result.title).toBe('From File');
      expect(result.ctaText).toBe('Submit');
    });

    it('returns placeholders when content generator throws', async () => {
      const extractionProvider: DesignExtractionProvider = async () => ({ unclear: true });
      const contentProvider: ContentGeneratorProvider = async () => {
        throw new Error('API error');
      };
      const result = await extractContentFromDesign('data:image/png;base64,a', {
        extractionProvider,
        contentGeneratorProvider: contentProvider,
      });
      expect(result.title).toBe('Your headline');
      expect(result.ctaText).toBe('Get started');
    });
  });
});
