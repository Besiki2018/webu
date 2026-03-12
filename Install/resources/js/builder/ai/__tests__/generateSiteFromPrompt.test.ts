import { generateSiteFromPrompt } from '../generateSiteFromPrompt';

describe('generateSiteFromPrompt', () => {
  it('returns tree and sectionsDraft from prompt (no content provider)', async () => {
    const result = await generateSiteFromPrompt('Create a modern ecommerce website for a furniture store');
    expect(result.tree.length).toBeGreaterThan(0);
    expect(result.sectionsDraft.length).toBe(result.tree.length);
    expect(result.projectType).toBe('ecommerce');
    expect(result.tree[0]).toMatchObject({
      id: expect.any(String),
      componentKey: expect.any(String),
      props: expect.any(Object),
    });
  });

  it('uses component selector variants (no duplicate variants per component)', async () => {
    const result = await generateSiteFromPrompt('Ecommerce store with hero and features');
    const heroNodes = result.tree.filter((n) => n.componentKey === 'webu_general_hero_01');
    const featureNodes = result.tree.filter((n) => n.componentKey === 'webu_general_features_01');
    expect(heroNodes.length).toBeLessThanOrEqual(1);
    if (featureNodes.length >= 2) {
      const variants = featureNodes.map((n) => n.props?.variant ?? n.variant).filter(Boolean);
      expect(new Set(variants).size).toBe(featureNodes.length);
    }
  });

  it('"Create a modern SaaS landing page" generates Header, Hero, Features, Pricing, Testimonials, CTA, Footer', async () => {
    const result = await generateSiteFromPrompt('Create a modern SaaS landing page');
    expect(result.projectType).toBe('saas');
    const keys = result.tree.map((n) => n.componentKey);
    expect(keys).toContain('webu_header_01');
    expect(keys).toContain('webu_general_hero_01');
    expect(keys).toContain('webu_general_features_01');
    expect(keys).toContain('webu_general_cards_01');
    expect(keys).toContain('webu_general_cta_01');
    expect(keys).toContain('webu_footer_01');
    const featuresCount = keys.filter((k) => k === 'webu_general_features_01').length;
    expect(featuresCount).toBeGreaterThanOrEqual(2);
  });

  it('with content provider merges generated props into tree', async () => {
    const mockProvider = async () =>
      JSON.stringify({
        title: 'Test Hero Title',
        subtitle: 'Test subtitle.',
        cta: 'Shop Now',
      });
    const result = await generateSiteFromPrompt('Ecommerce furniture store', {
      contentProvider: mockProvider,
    });
    const hero = result.tree.find((n) => n.componentKey === 'webu_general_hero_01');
    expect(hero?.props?.title).toBe('Test Hero Title');
    expect(hero?.props?.buttonText).toBe('Shop Now');
  });
});
