import {
  detectLayoutFromImage,
  detectLayoutHeuristic,
  getHeuristicLayout,
  normalizeVisionBlocks,
  type DetectedLayoutBlock,
  type LayoutPosition,
} from '../layoutDetector';

describe('layoutDetector', () => {
  describe('getHeuristicLayout', () => {
    it('returns default header, hero, features, testimonials, cta, footer when no project type', () => {
      const blocks = getHeuristicLayout();
      expect(blocks).toHaveLength(6);
      expect(blocks[0]).toEqual({ type: 'header', position: 'top' });
      expect(blocks[1]).toEqual({ type: 'hero', position: 'top-section' });
      expect(blocks.map((b) => b.type)).toContain('features');
      expect(blocks.map((b) => b.type)).toContain('testimonials');
      expect(blocks.map((b) => b.type)).toContain('cta');
      expect(blocks[blocks.length - 1]).toEqual({ type: 'footer', position: 'end' });
    });

    it('saas adds pricing and keeps testimonials', () => {
      const blocks = getHeuristicLayout('saas');
      const types = blocks.map((b) => b.type);
      expect(types).toContain('pricing');
      expect(types).toContain('features');
      expect(types).toContain('testimonials');
      expect(blocks[0].type).toBe('header');
      expect(blocks[blocks.length - 1].type).toBe('footer');
    });

    it('ecommerce adds productGrid', () => {
      const blocks = getHeuristicLayout('ecommerce');
      expect(blocks.map((b) => b.type)).toContain('productGrid');
    });

    it('restaurant has menu and gallery', () => {
      const blocks = getHeuristicLayout('restaurant');
      expect(blocks.map((b) => b.type)).toContain('menu');
      expect(blocks.map((b) => b.type)).toContain('gallery');
    });
  });

  describe('detectLayoutHeuristic', () => {
    it('returns result with blocks array', () => {
      const result = detectLayoutHeuristic('landing');
      expect(result.blocks).toBeDefined();
      expect(Array.isArray(result.blocks)).toBe(true);
      expect(result.blocks.length).toBeGreaterThan(0);
      result.blocks.forEach((b) => {
        expect(b).toHaveProperty('type');
        expect(b).toHaveProperty('position');
        expect(['top', 'top-section', 'middle', 'bottom', 'end']).toContain(b.position);
      });
    });
  });

  describe('normalizeVisionBlocks', () => {
    it('maps vision-style array to DetectedLayoutBlock[]', () => {
      const raw = [
        { type: 'HEADER', position: 'top' },
        { type: 'hero', position: 'top-section' },
        { type: 'feature grid', position: 'middle' },
        { type: 'cta', position: 'bottom' },
        { type: 'footer', position: 'end' },
      ];
      const blocks = normalizeVisionBlocks(raw);
      expect(blocks).toHaveLength(5);
      expect(blocks[0]).toEqual({ type: 'header', position: 'top' });
      expect(blocks[1]).toEqual({ type: 'hero', position: 'top-section' });
      expect(blocks[2].type).toBe('features');
      expect(blocks[3].position).toBe('bottom');
      expect(blocks[4]).toEqual({ type: 'footer', position: 'end' });
    });

    it('assigns first to top, last to end when position missing', () => {
      const raw = [{ type: 'header' }, { type: 'hero' }, { type: 'footer' }];
      const blocks = normalizeVisionBlocks(raw);
      expect(blocks[0].position).toBe('top');
      expect(blocks[1].position).toBe('middle');
      expect(blocks[2].position).toBe('end');
    });

    it('skips invalid type and non-objects', () => {
      const raw = [
        { type: 'header', position: 'top' },
        { type: 'invalid_section', position: 'middle' },
        null,
        { type: 'footer', position: 'end' },
      ];
      const blocks = normalizeVisionBlocks(raw);
      expect(blocks).toHaveLength(2);
      expect(blocks[0].type).toBe('header');
      expect(blocks[1].type).toBe('footer');
    });

    it('returns empty for non-array or empty', () => {
      expect(normalizeVisionBlocks([])).toEqual([]);
      expect(normalizeVisionBlocks(null)).toEqual([]);
      expect(normalizeVisionBlocks({})).toEqual([]);
    });
  });

  describe('detectLayoutFromImage', () => {
    it('returns heuristic layout when image is data URL and no vision provider', async () => {
      const result = await detectLayoutFromImage('data:image/png;base64,abc', {});
      expect(result.blocks).toBeDefined();
      expect(result.blocks.length).toBeGreaterThan(0);
      expect(result.blocks[0].type).toBe('header');
      expect(result.blocks[result.blocks.length - 1].type).toBe('footer');
    });

    it('uses projectType for heuristic when no vision provider', async () => {
      const result = await detectLayoutFromImage('data:image/jpeg;base64,xyz', {
        projectType: 'saas',
      });
      expect(result.blocks.map((b) => b.type)).toContain('pricing');
    });

    it('uses vision provider when provided and returns valid blocks', async () => {
      const visionProvider = async (): Promise<DetectedLayoutBlock[]> => [
        { type: 'header', position: 'top' },
        { type: 'hero', position: 'top-section' },
        { type: 'features', position: 'middle' },
        { type: 'cta', position: 'bottom' },
        { type: 'footer', position: 'end' },
      ];
      const result = await detectLayoutFromImage('data:image/png;base64,img', {
        visionProvider,
      });
      expect(result.blocks).toHaveLength(5);
      expect(result.blocks[2].type).toBe('features');
    });

    it('falls back to heuristic when vision provider throws', async () => {
      const visionProvider = async (): Promise<DetectedLayoutBlock[]> => {
        throw new Error('API error');
      };
      const result = await detectLayoutFromImage('data:image/png;base64,x', {
        visionProvider,
        projectType: 'landing',
      });
      expect(result.blocks).toBeDefined();
      expect(result.blocks.length).toBeGreaterThan(0);
      expect(result.blocks[result.blocks.length - 1].type).toBe('footer');
    });

    it('accepts File and converts to data URL for vision', async () => {
      const file = new File(['fake'], 'design.png', { type: 'image/png' });
      const result = await detectLayoutFromImage(file, { projectType: 'ecommerce' });
      expect(result.blocks).toBeDefined();
      expect(result.blocks.map((b) => b.type)).toContain('productGrid');
    });
  });
});
