import { describe, expect, it } from 'vitest';

import {
  getAllowedComponentCatalog,
  getCatalogEntry,
  getComponentCatalog,
} from '../componentCatalog';

describe('componentCatalog', () => {
  it('builds AI metadata from the canonical registry', () => {
    const hero = getCatalogEntry('webu_general_hero_01');

    expect(hero).toMatchObject({
      componentKey: 'webu_general_hero_01',
      layoutType: 'hero',
      sectionType: 'hero',
    });
    expect(hero?.propsSchema.length).toBeGreaterThan(0);
    expect(hero?.defaultProps).toEqual(expect.any(Object));
    expect(hero?.variants).toBeInstanceOf(Array);
    expect(hero?.categoryTags).toEqual(expect.arrayContaining(['hero', 'brand']));
    expect(hero?.styleTags).toEqual(expect.arrayContaining(['modern', 'premium']));
    expect(hero?.priorityScore).toBeGreaterThan(0);
  });

  it('filters the catalog by allowed project/site type', () => {
    const ecommerceCatalog = getAllowedComponentCatalog('ecommerce');
    const clinicCatalog = getAllowedComponentCatalog('clinic');

    expect(ecommerceCatalog.some((entry) => entry.componentKey === 'webu_ecom_product_grid_01')).toBe(true);
    expect(ecommerceCatalog.some((entry) => entry.componentKey === 'webu_general_banner_01')).toBe(false);
    expect(clinicCatalog.some((entry) => entry.layoutType === 'form')).toBe(true);
  });

  it('exposes a non-empty catalog for AI planning', () => {
    expect(getComponentCatalog().length).toBeGreaterThan(10);
  });
});
