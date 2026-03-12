import {
  INTERACTION_TARGET_MS,
  getVisibleRange,
  DEFAULT_SECTION_HEIGHT_ESTIMATE,
  DRAG_ACTIVATION_DISTANCE_PX,
  DRAG_ACTIVATION_DELAY_MS,
  getSectionRowKey,
  getLazySectionFactory,
} from '../performanceOptimization';

describe('performanceOptimization', () => {
  describe('INTERACTION_TARGET_MS', () => {
    it('is 50ms', () => {
      expect(INTERACTION_TARGET_MS).toBe(50);
    });
  });

  describe('getVisibleRange', () => {
    it('returns full range when container is large', () => {
      const r = getVisibleRange(2000, 0, 5);
      expect(r.startIndex).toBe(0);
      expect(r.endIndex).toBe(4);
      expect(r.offsetTop).toBe(0);
      expect(r.totalHeight).toBe(5 * DEFAULT_SECTION_HEIGHT_ESTIMATE);
    });

    it('returns empty range for zero items', () => {
      const r = getVisibleRange(500, 0, 0);
      expect(r.startIndex).toBe(0);
      expect(r.endIndex).toBe(-1);
      expect(r.offsetTop).toBe(0);
      expect(r.totalHeight).toBe(0);
    });

    it('uses custom getItemHeight when provided', () => {
      const r = getVisibleRange(300, 0, 4, (i) => (i === 0 ? 100 : 150));
      expect(r.totalHeight).toBe(100 + 150 * 3);
      expect(r.startIndex).toBe(0);
      expect(r.endIndex).toBeGreaterThanOrEqual(0);
    });

    it('adjusts startIndex/offsetTop with scroll', () => {
      const getHeight = () => 200;
      const r = getVisibleRange(400, 500, 10, getHeight);
      expect(r.offsetTop).toBe(400); // 2 items * 200
      expect(r.startIndex).toBe(2);
    });
  });

  describe('drag constants', () => {
    it('DRAG_ACTIVATION_DISTANCE_PX is 5', () => {
      expect(DRAG_ACTIVATION_DISTANCE_PX).toBe(5);
    });
    it('DRAG_ACTIVATION_DELAY_MS is 0', () => {
      expect(DRAG_ACTIVATION_DELAY_MS).toBe(0);
    });
  });

  describe('getSectionRowKey', () => {
    it('returns localId as key', () => {
      expect(getSectionRowKey('sec-1')).toBe('sec-1');
    });
  });

  describe('getLazySectionFactory', () => {
    it('returns null for unregistered key', () => {
      expect(getLazySectionFactory('unknown')).toBeNull();
    });
  });
});
