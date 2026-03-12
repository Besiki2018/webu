import { generateComponentSpec } from '../componentSpecGenerator';

describe('componentSpecGenerator', () => {
  it('generates PricingSection spec for "Create pricing table"', () => {
    const spec = generateComponentSpec({
      prompt: 'Create pricing table',
      projectType: 'saas',
      designStyle: 'modern',
    });
    expect(spec.componentName).toBe('PricingSection');
    expect(spec.layoutType).toBe('grid');
    expect(spec.props).toContain('title');
    expect(spec.props).toContain('plans');
    expect(spec.props).toContain('planName');
    expect(spec.props).toContain('price');
    expect(spec.props).toContain('features');
    expect(spec.props).toContain('ctaButton');
    expect(spec.variantTypes).toEqual(['cards', 'horizontal', 'minimal']);
    expect(spec.slug).toBe('pricing_table');
    expect(spec.suggestedRegistryId).toBe('webu_general_pricing_table_01');
  });

  it('generates TestimonialsSlider spec for "Create testimonials slider"', () => {
    const spec = generateComponentSpec({
      prompt: 'Create testimonials slider',
      designStyle: 'modern',
    });
    expect(spec.componentName).toBe('TestimonialsSlider');
    expect(spec.layoutType).toBe('slider');
    expect(spec.props).toContain('title');
    expect(spec.props).toContain('items');
    expect(spec.variantTypes).toContain('carousel');
    expect(spec.variantTypes).toContain('minimal');
    expect(spec.slug).toBe('testimonials_slider');
  });

  it('generates TeamSection spec for "Create team section"', () => {
    const spec = generateComponentSpec({
      prompt: 'Create team section',
      designStyle: 'minimal',
    });
    expect(spec.componentName).toBe('TeamSection');
    expect(spec.props).toContain('members');
    expect(spec.props).toContain('name');
    expect(spec.props).toContain('role');
    expect(spec.slug).toBe('team_section');
  });

  it('generates FaqAccordion spec for "Create FAQ accordion"', () => {
    const spec = generateComponentSpec({
      prompt: 'Create FAQ accordion',
      projectType: 'landing',
    });
    expect(spec.componentName).toBe('FaqAccordion');
    expect(spec.layoutType).toBe('accordion');
    expect(spec.props).toContain('question');
    expect(spec.props).toContain('answer');
    expect(spec.variantTypes).toContain('single');
    expect(spec.variantTypes).toContain('grouped');
  });

  it('generates FeatureComparisonTable spec for "Create feature comparison table"', () => {
    const spec = generateComponentSpec({
      prompt: 'Create feature comparison table',
      designStyle: 'corporate',
    });
    expect(spec.componentName).toBe('FeatureComparisonTable');
    expect(spec.layoutType).toBe('table');
    expect(spec.props).toContain('plans');
    expect(spec.props).toContain('features');
    expect(spec.slug).toBe('feature_comparison_table');
  });

  it('minimal style can reduce variant count', () => {
    const spec = generateComponentSpec({
      prompt: 'Create pricing table',
      designStyle: 'minimal',
    });
    expect(spec.variantTypes.length).toBeLessThanOrEqual(3);
  });

  it('fallback for unknown prompt returns CustomSection-like spec', () => {
    const spec = generateComponentSpec({
      prompt: 'Create something custom',
      designStyle: 'modern',
    });
    expect(spec.componentName).toBeDefined();
    expect(spec.layoutType).toBeDefined();
    expect(spec.props.length).toBeGreaterThan(0);
    expect(spec.variantTypes.length).toBeGreaterThan(0);
  });
});
