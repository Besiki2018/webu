import {
  getSmartPreferredIndices,
  getSmartAvoidIndices,
  filterAvoidedIndices,
  SMART_PREFERRED_INDICES,
  SMART_AVOID_INDICES,
} from '../smartVariants';
import { selectVariant } from '../componentSelector';

describe('smartVariants', () => {
  describe('SMART_PREFERRED_INDICES', () => {
    it('prefers modern hero indices (2,3,4)', () => {
      expect(SMART_PREFERRED_INDICES.webu_general_hero_01).toEqual([2, 3, 4]);
    });
    it('prefers balanced feature indices (1,2)', () => {
      expect(SMART_PREFERRED_INDICES.webu_general_features_01).toEqual([1, 2]);
    });
  });

  describe('SMART_AVOID_INDICES', () => {
    it('avoids old/simple hero (0,1)', () => {
      expect(SMART_AVOID_INDICES.webu_general_hero_01).toEqual([0, 1]);
    });
  });

  describe('getSmartPreferredIndices / getSmartAvoidIndices', () => {
    it('returns preferred indices for hero', () => {
      expect(getSmartPreferredIndices('webu_general_hero_01')).toEqual([2, 3, 4]);
    });
    it('returns avoid indices for hero', () => {
      expect(getSmartAvoidIndices('webu_general_hero_01')).toEqual([0, 1]);
    });
  });

  describe('filterAvoidedIndices', () => {
    it('removes avoided indices', () => {
      expect(filterAvoidedIndices([0, 1, 2, 3], 'webu_general_hero_01')).toEqual([2, 3]);
    });
  });

  describe('component selector uses smart variants when no tone', () => {
    it('selects non-avoided variant for hero when tone is null', () => {
      const v = selectVariant('webu_general_hero_01', {
        projectType: 'landing',
        tone: null,
        industry: null,
      });
      expect(['hero-3', 'hero-4', 'hero-5']).toContain(v);
    });
  });
});
