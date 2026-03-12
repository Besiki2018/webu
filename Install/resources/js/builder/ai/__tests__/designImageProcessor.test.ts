import {
  processDesignImages,
  groupProcessedImagesByRole,
  type DetectedImageBlock,
  type ImageBlockDetector,
  type StockImageProvider,
} from '../designImageProcessor';

describe('designImageProcessor', () => {
  describe('processDesignImages', () => {
    it('returns empty array when no blocks and no detector', async () => {
      const result = await processDesignImages('data:image/png;base64,abc', {});
      expect(result).toEqual([]);
    });

    it('uses detected blocks and original URLs when useOriginals is true', async () => {
      const blocks: DetectedImageBlock[] = [
        { role: 'hero', url: 'https://example.com/hero.png' },
        { role: 'background', url: 'data:image/jpeg;base64,bg' },
      ];
      const result = await processDesignImages('data:image/png;base64,x', {
        detectedBlocks: blocks,
        useOriginals: true,
      });
      expect(result).toHaveLength(2);
      expect(result[0]).toMatchObject({
        url: 'https://example.com/hero.png',
        source: 'original',
        role: 'hero',
        index: 0,
      });
      expect(result[1]).toMatchObject({
        url: 'data:image/jpeg;base64,bg',
        source: 'original',
        role: 'background',
        index: 0,
      });
    });

    it('calls detector when detectedBlocks not provided', async () => {
      const detector: ImageBlockDetector = async () => [
        { role: 'hero', url: 'https://detected/hero.jpg' },
      ];
      const result = await processDesignImages('data:image/png;base64,y', {
        detector,
        useOriginals: true,
      });
      expect(result).toHaveLength(1);
      expect(result[0]!.url).toBe('https://detected/hero.jpg');
      expect(result[0]!.source).toBe('original');
    });

    it('uses AI-generated replacement when useAiGenerated and provider set', async () => {
      const blocks: DetectedImageBlock[] = [{ role: 'hero' }];
      const aiProvider = async () => 'https://ai-generated/hero.png';
      const result = await processDesignImages('data:image/png;base64,z', {
        detectedBlocks: blocks,
        useOriginals: false,
        useAiGenerated: true,
        aiImageProvider: aiProvider,
      });
      expect(result).toHaveLength(1);
      expect(result[0]!.url).toBe('https://ai-generated/hero.png');
      expect(result[0]!.source).toBe('aiGenerated');
    });

    it('uses stock replacement when useStock and provider set', async () => {
      const blocks: DetectedImageBlock[] = [{ role: 'hero' }];
      const stockProvider: StockImageProvider = async () => 'https://stock/hero.jpg';
      const result = await processDesignImages('data:image/png;base64,a', {
        detectedBlocks: blocks,
        useOriginals: false,
        useStock: true,
        stockImageProvider: stockProvider,
      });
      expect(result).toHaveLength(1);
      expect(result[0]!.url).toBe('https://stock/hero.jpg');
      expect(result[0]!.source).toBe('stock');
    });

    it('prefers original over AI when block has url and useOriginals not false', async () => {
      const blocks: DetectedImageBlock[] = [{ role: 'hero', url: 'https://original/hero.png' }];
      const aiProvider = async () => 'https://ai/hero.png';
      const result = await processDesignImages('data:image/png;base64,b', {
        detectedBlocks: blocks,
        useAiGenerated: true,
        aiImageProvider: aiProvider,
      });
      expect(result[0]!.url).toBe('https://original/hero.png');
      expect(result[0]!.source).toBe('original');
    });

    it('assigns index per role', async () => {
      const blocks: DetectedImageBlock[] = [
        { role: 'hero', url: 'https://a' },
        { role: 'hero', url: 'https://b' },
        { role: 'background', url: 'https://c' },
      ];
      const result = await processDesignImages('data:image/png;base64,c', {
        detectedBlocks: blocks,
      });
      expect(result[0]!.index).toBe(0);
      expect(result[1]!.index).toBe(1);
      expect(result[2]!.index).toBe(0);
    });

    it('skips blocks that resolve to empty URL', async () => {
      const blocks: DetectedImageBlock[] = [
        { role: 'hero' },
        { role: 'general' },
      ];
      const result = await processDesignImages('data:image/png;base64,d', {
        detectedBlocks: blocks,
        useOriginals: false,
      });
      expect(result).toHaveLength(0);
    });
  });

  describe('groupProcessedImagesByRole', () => {
    it('groups by role', () => {
      const processed = [
        { url: 'u1', source: 'original' as const, role: 'hero' as const, index: 0 },
        { url: 'u2', source: 'original' as const, role: 'background' as const, index: 0 },
        { url: 'u3', source: 'original' as const, role: 'hero' as const, index: 1 },
      ];
      const groups = groupProcessedImagesByRole(processed);
      expect(groups.hero).toHaveLength(2);
      expect(groups.hero[0]!.url).toBe('u1');
      expect(groups.hero[1]!.url).toBe('u3');
      expect(groups.background).toHaveLength(1);
      expect(groups.general).toHaveLength(0);
    });
  });
});
