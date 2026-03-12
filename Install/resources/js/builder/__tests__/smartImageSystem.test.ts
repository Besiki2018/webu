import {
  IMAGE_FIELD_SOURCES,
  IMAGE_FIELD_SOURCE_LABELS,
  getImagePropPathsForComponent,
  getPrimaryImagePropForComponent,
} from '../smartImageSystem';

describe('smartImageSystem', () => {
  describe('IMAGE_FIELD_SOURCES', () => {
    it('includes upload, unsplash, ai_generation, media_library', () => {
      expect(IMAGE_FIELD_SOURCES).toContain('upload');
      expect(IMAGE_FIELD_SOURCES).toContain('unsplash');
      expect(IMAGE_FIELD_SOURCES).toContain('ai_generation');
      expect(IMAGE_FIELD_SOURCES).toContain('media_library');
      expect(IMAGE_FIELD_SOURCES).toHaveLength(4);
    });
  });

  describe('IMAGE_FIELD_SOURCE_LABELS', () => {
    it('has label for each source', () => {
      expect(IMAGE_FIELD_SOURCE_LABELS.upload).toBe('Upload');
      expect(IMAGE_FIELD_SOURCE_LABELS.unsplash).toBe('Unsplash');
      expect(IMAGE_FIELD_SOURCE_LABELS.ai_generation).toBe('AI generation');
      expect(IMAGE_FIELD_SOURCE_LABELS.media_library).toBe('Media library');
    });
  });

  describe('getImagePropPathsForComponent', () => {
    it('returns image and backgroundImage for Hero', () => {
      const paths = getImagePropPathsForComponent('webu_general_hero_01');
      expect(paths).toContain('image');
      expect(paths).toContain('backgroundImage');
      expect(paths.length).toBeGreaterThanOrEqual(2);
    });

    it('returns empty array for unknown component', () => {
      expect(getImagePropPathsForComponent('unknown_key')).toEqual([]);
    });
  });

  describe('getPrimaryImagePropForComponent', () => {
    it('returns image for Hero', () => {
      expect(getPrimaryImagePropForComponent('webu_general_hero_01')).toBe('image');
    });

    it('returns null for unknown component', () => {
      expect(getPrimaryImagePropForComponent('unknown_key')).toBeNull();
    });
  });
});
