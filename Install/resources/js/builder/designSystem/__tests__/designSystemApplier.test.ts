import {
  normalizeColor,
  normalizeSpacing,
  applyTokensToSectionProps,
  applyTokensToLayout,
  getButtonTokenRefs,
} from '../designSystemApplier';

describe('designSystemApplier', () => {
  describe('normalizeColor', () => {
    it('returns var(--color-*) for hex matching token', () => {
      const result = normalizeColor('#ffffff');
      expect(result).toContain('var(--color-');
      expect(result).toMatch(/\)$/);
    });

    it('leaves var(--color-*) as-is', () => {
      const v = 'var(--color-primary)';
      expect(normalizeColor(v)).toBe(v);
    });

    it('returns undefined for empty', () => {
      expect(normalizeColor(undefined)).toBeUndefined();
      expect(normalizeColor('')).toBe('');
    });
  });

  describe('normalizeSpacing', () => {
    it('returns var(--spacing-*) for px value', () => {
      const result = normalizeSpacing('16px');
      expect(result).toContain('var(--spacing-');
    });

    it('leaves var(--spacing-*) as-is', () => {
      const v = 'var(--spacing-md)';
      expect(normalizeSpacing(v)).toBe(v);
    });
  });

  describe('applyTokensToSectionProps', () => {
    it('normalizes backgroundColor to token ref', () => {
      const props = { backgroundColor: '#ffffff', title: 'Hero' };
      const out = applyTokensToSectionProps('hero', props);
      expect(out.backgroundColor).toContain('var(--color-');
    });

    it('applies semantic default for hero when backgroundColor missing', () => {
      const props = { title: 'Hero' };
      const out = applyTokensToSectionProps('hero', props);
      expect(out.backgroundColor).toBe('var(--color-background)');
    });

    it('applies semantic default for cta when backgroundColor missing', () => {
      const props = {};
      const out = applyTokensToSectionProps('cta', props);
      expect(out.backgroundColor).toBe('var(--color-primary)');
    });
  });

  describe('applyTokensToLayout', () => {
    it('maps over sections and applies tokens', () => {
      const sections = [
        { component: 'hero', props: { title: 'Hi', backgroundColor: '#fff' } },
        { component: 'cta', props: {} },
      ];
      const result = applyTokensToLayout(sections);
      expect(result).toHaveLength(2);
      expect(result[0].props.backgroundColor).toContain('var(--color-');
      expect(result[1].props.backgroundColor).toBe('var(--color-primary)');
    });
  });

  describe('getButtonTokenRefs', () => {
    it('returns primary button refs', () => {
      const refs = getButtonTokenRefs('primary');
      expect(refs.background).toContain('var(--color-');
      expect(refs.radius).toContain('var(--radius-');
    });

    it('returns outline refs', () => {
      const refs = getButtonTokenRefs('outline');
      expect(refs.background).toBe('transparent');
    });
  });
});
