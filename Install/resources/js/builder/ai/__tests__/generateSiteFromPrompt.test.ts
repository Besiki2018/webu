import { vi } from 'vitest';
import { generateSiteFromPrompt } from '../generateSiteFromPrompt';
import { hasDisallowedProductionCopy } from '../contentContracts';

describe('generateSiteFromPrompt', () => {
  it('returns tree and sectionsDraft from prompt (no content provider)', async () => {
    const result = await generateSiteFromPrompt('Create a modern ecommerce website for a furniture store');
    expect(result.tree.length).toBeGreaterThan(0);
    expect(result.sectionsDraft.length).toBe(result.tree.length);
    expect(result.projectType).toBe('ecommerce');
    expect(result.blueprint.projectType).toBe('ecommerce');
    expect(result.project).toEqual({
      projectType: 'ecommerce',
      type: 'ecommerce',
    });
    expect(result.available_components).toContain('webu_ecom_product_grid_01');
    expect(result.generationLog.some((entry) => entry.step === 'blueprint')).toBe(true);
    expect(result.generationLog.some((entry) => entry.step === 'prompt')).toBe(true);
    expect(result.generationLog.some((entry) => entry.step === 'component_scores')).toBe(true);
    expect(result.generationLog.some((entry) => entry.step === 'tree')).toBe(true);
    expect(result.generationLog.some((entry) => entry.step === 'validation' && entry.status === 'success')).toBe(true);
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
    expect(result.project.type).toBe('website');
    expect(result.blueprint.sections.map((section) => section.sectionType)).toEqual(expect.arrayContaining([
      'header',
      'hero',
      'features',
      'pricing',
      'testimonials',
      'cta',
      'footer',
    ]));
    const keys = result.tree.map((n) => n.componentKey);
    expect(keys).toContain('webu_header_01');
    expect(keys).toContain('webu_general_hero_01');
    expect(keys).toContain('webu_general_features_01');
    expect(keys).toContain('webu_general_testimonials_01');
    expect(keys).toContain('webu_general_cta_01');
    expect(keys).toContain('webu_footer_01');
    const pricingIndex = result.blueprint.sections.findIndex((section) => section.sectionType === 'pricing');
    expect(pricingIndex).toBeGreaterThanOrEqual(0);
    expect(result.sitePlan.pages[0]?.sections[pricingIndex]).toMatchObject({
      componentKey: expect.any(String),
      layoutType: expect.stringMatching(/pricing|cards|features|banner|grid/),
    });
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

  it('respects an explicit project type override and only plans allowed components for it', async () => {
    const result = await generateSiteFromPrompt('Simple brochure website', {
      projectType: 'ecommerce',
    });

    expect(result.projectType).toBe('ecommerce');
    expect(result.project.type).toBe('ecommerce');
    expect(result.available_components).toContain('webu_ecom_product_grid_01');
    expect(result.tree.some((node) => node.componentKey === 'webu_ecom_product_grid_01')).toBe(true);
  });

  it('builds different blueprints and structures for different prompt intents', async () => {
    const vet = await generateSiteFromPrompt('Create a modern vet clinic website for premium pet care');
    const saas = await generateSiteFromPrompt('Create a minimalist SaaS landing page for finance teams');

    expect(vet.blueprint.projectType).toBe('business');
    expect(saas.blueprint.projectType).toBe('saas');
    expect(vet.blueprint.sections.map((section) => section.sectionType)).not.toEqual(
      saas.blueprint.sections.map((section) => section.sectionType),
    );
    expect(vet.tree.map((node) => node.componentKey)).not.toEqual(
      saas.tree.map((node) => node.componentKey),
    );
  });

  it('fills the generated site with niche-specific copy before tree build', async () => {
    const vet = await generateSiteFromPrompt('Create a modern vet clinic website for premium pet care');
    const finance = await generateSiteFromPrompt('Create a minimalist SaaS landing page for finance teams');

    const vetHero = vet.sitePlan.pages[0]?.sections.find((section) => section.layoutType === 'hero')?.props ?? {};
    const financeHero = finance.sitePlan.pages[0]?.sections.find((section) => section.layoutType === 'hero')?.props ?? {};
    const vetFeatures = vet.sitePlan.pages[0]?.sections.find((section) => section.layoutType === 'features')?.props ?? {};
    const financeFeatures = finance.sitePlan.pages[0]?.sections.find((section) => section.layoutType === 'features')?.props ?? {};
    const vetAction = vet.sitePlan.pages[0]?.sections.find((section) => section.layoutType === 'form' || section.layoutType === 'cta')?.props ?? {};
    const financeAction = finance.sitePlan.pages[0]?.sections.find((section) => section.layoutType === 'form' || section.layoutType === 'cta')?.props ?? {};

    expect(vet.generationLog.some((entry) => entry.step === 'content')).toBe(true);
    expect(finance.generationLog.some((entry) => entry.step === 'content')).toBe(true);
    expect(vetHero.title).not.toEqual(financeHero.title);
    expect(JSON.stringify(vetHero)).toMatch(/pet|veterinary|visit|clinic/i);
    expect(JSON.stringify(financeHero)).toMatch(/finance|demo|workflow|reporting/i);
    expect(JSON.stringify(vetFeatures)).toMatch(/pet|care|visit|treatment/i);
    expect(JSON.stringify(financeFeatures)).toMatch(/finance|approval|reporting|close/i);
    expect(JSON.stringify(vetAction)).toMatch(/appointment|visit|pet|care/i);
    expect(JSON.stringify(financeAction)).toMatch(/demo|finance|workflow|consultation/i);
    expect(hasDisallowedProductionCopy(vet.tree.map((node) => node.props))).toBe(false);
    expect(hasDisallowedProductionCopy(finance.tree.map((node) => node.props))).toBe(false);
  });

  it('changes components or variants for different industries within the same project type', async () => {
    const clinic = await generateSiteFromPrompt('Create a premium website for a vet clinic');
    const consulting = await generateSiteFromPrompt('Create a premium website for a finance consulting firm');

    expect(clinic.projectType).toBe('business');
    expect(consulting.projectType).toBe('business');

    const clinicSignature = clinic.tree.map((node) => `${node.componentKey}:${String(node.props?.variant ?? node.variant ?? '')}`);
    const consultingSignature = consulting.tree.map((node) => `${node.componentKey}:${String(node.props?.variant ?? node.variant ?? '')}`);

    expect(clinicSignature).not.toEqual(consultingSignature);
  });

  it('keeps three different prompts structurally and textually distinct', async () => {
    const vet = await generateSiteFromPrompt('Create a premium website for a vet clinic');
    const finance = await generateSiteFromPrompt('Create a minimalist SaaS landing page for finance teams');
    const furniture = await generateSiteFromPrompt('Create a modern ecommerce website for a furniture store');

    const vetStructure = vet.tree.map((node) => `${node.componentKey}:${String(node.props?.variant ?? node.variant ?? '')}`);
    const financeStructure = finance.tree.map((node) => `${node.componentKey}:${String(node.props?.variant ?? node.variant ?? '')}`);
    const furnitureStructure = furniture.tree.map((node) => `${node.componentKey}:${String(node.props?.variant ?? node.variant ?? '')}`);

    const vetHero = JSON.stringify(vet.tree.find((node) => node.componentKey.includes('hero'))?.props ?? {});
    const financeHero = JSON.stringify(finance.tree.find((node) => node.componentKey.includes('hero'))?.props ?? {});
    const furnitureHero = JSON.stringify(furniture.tree.find((node) => node.componentKey.includes('hero'))?.props ?? {});

    expect(vetStructure).not.toEqual(financeStructure);
    expect(vetStructure).not.toEqual(furnitureStructure);
    expect(financeStructure).not.toEqual(furnitureStructure);
    expect(vetHero).not.toEqual(financeHero);
    expect(vetHero).not.toEqual(furnitureHero);
    expect(financeHero).not.toEqual(furnitureHero);
  });

  it('logs the blueprint object during generation', async () => {
    const infoSpy = vi.spyOn(console, 'info').mockImplementation(() => undefined);

    try {
      await generateSiteFromPrompt('Create a minimalist SaaS landing page for finance teams');
      expect(infoSpy).toHaveBeenCalledWith(
        '[builder.ai] blueprint created',
        expect.objectContaining({
          projectType: 'saas',
        }),
      );
    } finally {
      infoSpy.mockRestore();
    }
  });

  it('fails before returning malformed output when final content overrides leave required props empty', async () => {
    const brokenHeroProvider = async () => JSON.stringify({
      title: '',
      subtitle: '',
      cta: '',
    });

    await expect(generateSiteFromPrompt('Create a modern SaaS landing page', {
      contentProvider: brokenHeroProvider,
    })).rejects.toThrow('Generated site validation failed:');
  });
});
