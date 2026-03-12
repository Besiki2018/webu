import { describe, it, expect } from 'vitest';
import { applyChangeSetToSections, type SectionItem } from '../changes/applyChangeSet';

describe('applyChangeSetToSections', () => {
  const baseSections: SectionItem[] = [
    { id: 'hero-1', type: 'hero', props: { headline: 'Welcome', subheadline: 'Tagline' } },
    { id: 'section-2', type: 'features', props: {} },
  ];

  it('applies updateSection', () => {
    const result = applyChangeSetToSections(baseSections, {
      operations: [
        { op: 'updateSection', sectionId: 'hero-1', patch: { headline: 'Updated' } },
      ],
      summary: [],
    });
    expect(result).toHaveLength(2);
    expect(result[0].props?.headline).toBe('Updated');
    expect(result[0].props?.subheadline).toBe('Tagline');
  });

  it('applies insertSection afterSectionId', () => {
    const result = applyChangeSetToSections(baseSections, {
      operations: [
        { op: 'insertSection', sectionType: 'pricing', afterSectionId: 'hero-1' },
      ],
      summary: [],
    });
    expect(result).toHaveLength(3);
    expect(result[1].type).toBe('pricing');
    expect(result[1].id).toBeUndefined();
  });

  it('applies deleteSection', () => {
    const result = applyChangeSetToSections(baseSections, {
      operations: [{ op: 'deleteSection', sectionId: 'section-2' }],
      summary: [],
    });
    expect(result).toHaveLength(1);
    expect(result[0].id).toBe('hero-1');
  });

  it('applies reorderSection', () => {
    const result = applyChangeSetToSections(baseSections, {
      operations: [{ op: 'reorderSection', sectionId: 'section-2', toIndex: 0 }],
      summary: [],
    });
    expect(result).toHaveLength(2);
    expect(result[0].id).toBe('section-2');
    expect(result[1].id).toBe('hero-1');
  });

  it('applies updateText', () => {
    const result = applyChangeSetToSections(baseSections, {
      operations: [
        { op: 'updateText', sectionId: 'hero-1', value: 'Short headline', path: 'headline' },
      ],
      summary: [],
    });
    expect(result[0].props?.headline).toBe('Short headline');
  });

  it('applies updateText with nested path (generated component props)', () => {
    const pricingSections: SectionItem[] = [
      { id: 'pricing-1', type: 'pricing', props: { title: 'Plans', plans: [{ name: 'Basic', price: '9' }, { name: 'Pro', price: '29' }] } },
    ];
    const result = applyChangeSetToSections(pricingSections, {
      operations: [
        { op: 'updateText', sectionId: 'pricing-1', value: 'Our Pricing', path: 'title' },
        { op: 'updateText', sectionId: 'pricing-1', value: '19', path: 'plans.0.price' },
      ],
      summary: [],
    });
    expect(result[0].props?.title).toBe('Our Pricing');
    expect((result[0].props?.plans as Array<{ price?: string }>)?.[0]?.price).toBe('19');
  });

  it('applies replaceImage', () => {
    const withImage: SectionItem[] = [
      { id: 'img-1', type: 'image', props: { image: { url: '/old.jpg', alt: 'Old' } } },
    ];
    const result = applyChangeSetToSections(withImage, {
      operations: [
        { op: 'replaceImage', sectionId: 'img-1', url: '/new.jpg', alt: 'New alt' },
      ],
      summary: [],
    });
    expect((result[0].props?.image as Record<string, unknown>)?.url).toBe('/new.jpg');
    expect((result[0].props?.image as Record<string, unknown>)?.alt).toBe('New alt');
  });

  it('applies updateButton', () => {
    const result = applyChangeSetToSections(baseSections, {
      operations: [
        { op: 'updateButton', sectionId: 'hero-1', label: 'Get Started', href: '/signup' },
      ],
      summary: [],
    });
    expect(result[0].props?.buttonLabel).toBe('Get Started');
    expect(result[0].props?.buttonHref).toBe('/signup');
  });

  it('does not mutate input', () => {
    const copy = baseSections.map((s) => ({ ...s, props: { ...s.props } }));
    applyChangeSetToSections(baseSections, {
      operations: [{ op: 'updateSection', sectionId: 'hero-1', patch: { headline: 'X' } }],
      summary: [],
    });
    expect(baseSections[0].props?.headline).toBe('Welcome');
  });
});
