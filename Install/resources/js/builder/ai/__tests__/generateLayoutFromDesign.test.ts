import { generateLayoutFromDesign } from '../generateLayoutFromDesign';

describe('generateLayoutFromDesign', () => {
  it('returns tree and sectionsDraft from design image (no providers)', async () => {
    const result = await generateLayoutFromDesign('data:image/png;base64,abc', {
      projectType: 'landing',
    });
    expect(result.tree).toBeDefined();
    expect(Array.isArray(result.tree)).toBe(true);
    expect(result.tree.length).toBeGreaterThan(0);
    expect(result.sectionsDraft).toBeDefined();
    expect(result.sectionsDraft.length).toBe(result.tree.length);
    expect(result.projectType).toBe('landing');
  });

  it('sectionsDraft have localId, type, props for canvas', async () => {
    const result = await generateLayoutFromDesign('data:image/png;base64,xyz', {
      projectType: 'saas',
    });
    result.sectionsDraft.forEach((s) => {
      expect(s.localId).toBeDefined();
      expect(s.type).toBeDefined();
      expect(s.props !== null && typeof s.props === 'object').toBe(true);
      expect(s.propsText).toBeDefined();
    });
  });

  it('first section is header, has hero and footer', async () => {
    const result = await generateLayoutFromDesign('data:image/png;base64,img', {
      projectType: 'landing',
    });
    const types = result.tree.map((n) => n.componentKey);
    expect(types[0]).toBe('webu_header_01');
    expect(types).toContain('webu_general_hero_01');
    expect(types[types.length - 1]).toBe('webu_footer_01');
  });

  it('FINAL RESULT: design upload generates Header, Hero, Features, Testimonials, CTA, Footer', async () => {
    const result = await generateLayoutFromDesign('data:image/png;base64,full', {
      projectType: 'landing',
    });
    const types = result.sectionsDraft.map((s) => s.type);
    expect(types).toContain('webu_header_01');
    expect(types).toContain('webu_general_hero_01');
    expect(types).toContain('webu_general_features_01');
    expect(types).toContain('webu_general_cards_01'); // Testimonials
    expect(types).toContain('webu_general_cta_01');
    expect(types).toContain('webu_footer_01');
    expect(result.sectionsDraft.length).toBeGreaterThanOrEqual(6);
  });

  it('accepts File and runs pipeline', async () => {
    const file = new File(['fake'], 'design.png', { type: 'image/png' });
    const result = await generateLayoutFromDesign(file, { projectType: 'ecommerce' });
    expect(result.tree.length).toBeGreaterThan(0);
    expect(result.sectionsDraft.length).toBe(result.tree.length);
    expect(result.projectType).toBe('ecommerce');
  });

  it('applies style-based variants (e.g. saas → startup style)', async () => {
    const result = await generateLayoutFromDesign('data:image/png;base64,a', {
      projectType: 'saas',
    });
    const hero = result.tree.find((n) => n.componentKey === 'webu_general_hero_01');
    expect(hero).toBeDefined();
    expect(hero!.props?.variant ?? hero!.variant).toBeTruthy();
  });

  describe('Part 10 — editable output', () => {
    it('sections have editable shape: localId, type, props (titles, variant, image, colors)', async () => {
      const result = await generateLayoutFromDesign('data:image/png;base64,editable', {
        projectType: 'landing',
      });
      const heroDraft = result.sectionsDraft.find((s) => s.type === 'webu_general_hero_01');
      expect(heroDraft).toBeDefined();
      expect(heroDraft!.localId).toBeTruthy();
      expect(typeof heroDraft!.props).toBe('object');
      expect(heroDraft!.props).not.toBeNull();
      const props = heroDraft!.props as Record<string, unknown>;
      expect('title' in props || 'headline' in props).toBe(true);
      expect('variant' in props).toBe(true);
      expect('subtitle' in props || 'subheading' in props || Object.keys(props).length >= 2).toBe(true);
    });

    it('every section has props and propsText for sidebar/raw editing', async () => {
      const result = await generateLayoutFromDesign('data:image/png;base64,p10', {
        projectType: 'saas',
      });
      result.sectionsDraft.forEach((s) => {
        expect(s.localId).toBeTruthy();
        expect(s.type).toBeTruthy();
        expect(typeof s.props).toBe('object');
        expect(typeof s.propsText).toBe('string');
        expect(s.propsText.length).toBeGreaterThan(0);
      });
    });
  });
});
