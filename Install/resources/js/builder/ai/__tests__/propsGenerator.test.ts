import {
  contentToHeroProps,
  contentToFeaturesProps,
  contentToCtaProps,
  contentToGridProps,
} from '../propsGenerator';
import type { HeroContentResult, FeaturesContentResult, CtaContentResult, ProductHighlightsResult } from '../contentGenerator';

describe('propsGenerator', () => {
  describe('contentToHeroProps', () => {
    it('maps hero content to builder props with title, subtitle, buttonText', () => {
      const content: HeroContentResult = {
        title: 'Beautiful Furniture for Modern Living',
        subtitle: 'Discover handcrafted pieces designed for comfort and style.',
        cta: 'Shop Now',
      };
      const props = contentToHeroProps(content);
      expect(props.title).toBe(content.title);
      expect(props.subtitle).toBe(content.subtitle);
      expect(props.buttonText).toBe('Shop Now');
      expect(props.buttonLink).toBe('#');
    });

    it('includes optional image when provided', () => {
      const content: HeroContentResult = { title: 'Hi', subtitle: '', cta: 'Go' };
      const props = contentToHeroProps(content, { image: 'https://example.com/hero.jpg' });
      expect(props.image).toBe('https://example.com/hero.jpg');
    });

    it('maps eyebrow and ctaSecondary when present', () => {
      const content: HeroContentResult = {
        title: 'T',
        subtitle: 'S',
        cta: 'Primary',
        eyebrow: 'New',
        ctaSecondary: 'Learn more',
      };
      const props = contentToHeroProps(content);
      expect(props.eyebrow).toBe('New');
      expect(props.secondaryButtonText).toBe('Learn more');
    });
  });

  describe('contentToFeaturesProps', () => {
    it('maps features content to builder props with title and items', () => {
      const content: FeaturesContentResult = {
        title: 'Why Choose Us',
        items: [
          { title: 'Quality', description: 'Built to last.' },
          { title: 'Design', description: 'Thoughtfully crafted.' },
        ],
      };
      const props = contentToFeaturesProps(content);
      expect(props.title).toBe('Why Choose Us');
      expect(props.items).toHaveLength(2);
      expect(props.items[0]).toMatchObject({ title: 'Quality', description: 'Built to last.' });
      expect(props.items[0].icon).toBeDefined();
    });

    it('assigns default icons when icons option not provided', () => {
      const content: FeaturesContentResult = {
        title: 'Features',
        items: [{ title: 'One', description: 'D1' }],
      };
      const props = contentToFeaturesProps(content);
      expect(props.items[0].icon).toBe('Package');
    });
  });

  describe('contentToCtaProps', () => {
    it('maps CTA content to builder props', () => {
      const content: CtaContentResult = {
        title: 'Ready to start?',
        subtitle: 'Join today.',
        buttonLabel: 'Get started',
      };
      const props = contentToCtaProps(content);
      expect(props.title).toBe('Ready to start?');
      expect(props.subtitle).toBe('Join today.');
      expect(props.buttonLabel).toBe('Get started');
      expect(props.buttonUrl).toBe('#');
    });
  });

  describe('contentToGridProps', () => {
    it('maps product highlights to grid builder props', () => {
      const content: ProductHighlightsResult = {
        title: 'Best Sellers',
        items: [
          { name: 'Sofa', description: 'Comfortable sofa.' },
          { name: 'Table', description: 'Oak table.' },
        ],
      };
      const props = contentToGridProps(content);
      expect(props.title).toBe('Best Sellers');
      expect(props.items).toHaveLength(2);
      expect(props.items[0]).toMatchObject({ title: 'Sofa', name: 'Sofa', description: 'Comfortable sofa.' });
    });
  });
});
