/**
 * Part 12 — Prevent Duplicate Components tests.
 */
import {
  checkDuplicateComponent,
  checkDuplicateFromSpec,
  getCategoryFromSlug,
  keyToComponentName,
  getCapabilitiesFromSchema,
  buildExistingSummariesFromRegistry,
  getExistingSummariesFromBuilderRegistry,
  type ExistingComponentSummary,
} from '../duplicateComponentChecker';
import { generateComponentSpec, generateComponentSpecWithDuplicateCheck } from '../componentSpecGenerator';

describe('duplicateComponentChecker', () => {
  describe('getCategoryFromSlug', () => {
    it('returns first segment for pricing_table', () => {
      expect(getCategoryFromSlug('pricing_table')).toBe('pricing');
    });
    it('returns first segment for team_section', () => {
      expect(getCategoryFromSlug('team_section')).toBe('team');
    });
  });

  describe('keyToComponentName', () => {
    it('maps pricing to PricingSection', () => {
      expect(keyToComponentName('pricing')).toBe('PricingSection');
    });
    it('maps hero to HeroSection', () => {
      expect(keyToComponentName('hero')).toBe('HeroSection');
    });
  });

  describe('getCapabilitiesFromSchema', () => {
    it('extracts from editableFields array of objects', () => {
      const schema = { editableFields: [{ key: 'title' }, { key: 'plans' }] };
      expect(getCapabilitiesFromSchema(schema)).toEqual(['title', 'plans']);
    });
    it('extracts from fields array', () => {
      const schema = { fields: [{ path: 'headline' }, { path: 'cta' }] };
      expect(getCapabilitiesFromSchema(schema)).toEqual(['headline', 'cta']);
    });
  });

  describe('checkDuplicateComponent', () => {
    const existingPricing: ExistingComponentSummary = {
      registryId: 'webu_general_pricing_table_01',
      key: 'pricing',
      componentName: 'PricingSection',
      category: 'pricing',
      capabilities: ['title', 'plans', 'price', 'features', 'ctaButton'],
    };

    it('returns addVariant when existing has same component name', () => {
      const result = checkDuplicateComponent(
        { componentName: 'PricingSection', slug: 'pricing_table', props: ['title', 'plans'] },
        [existingPricing]
      );
      expect(result.isDuplicate).toBe(true);
      expect(result.action).toBe('addVariant');
      expect(result.existingRegistryId).toBe('webu_general_pricing_table_01');
      expect(result.existingKey).toBe('pricing');
    });

    it('returns addVariant when existing has same category', () => {
      const result = checkDuplicateComponent(
        { componentName: 'PricingSection', slug: 'pricing_table', props: [] },
        [{ ...existingPricing, componentName: undefined }]
      );
      expect(result.isDuplicate).toBe(true);
      expect(result.action).toBe('addVariant');
    });

    it('returns addVariant when capabilities overlap significantly', () => {
      const result = checkDuplicateComponent(
        { componentName: 'PlansSection', slug: 'plans', props: ['title', 'plans', 'price', 'ctaButton'] },
        [{ ...existingPricing, componentName: 'PricingSection' }]
      );
      expect(result.isDuplicate).toBe(true);
      expect(result.action).toBe('addVariant');
    });

    it('returns create when no existing matches', () => {
      const result = checkDuplicateComponent(
        { componentName: 'PricingSection', slug: 'pricing_table', props: ['title', 'plans'] },
        []
      );
      expect(result.isDuplicate).toBe(false);
      expect(result.action).toBe('create');
    });

    it('returns create when existing has different category', () => {
      const result = checkDuplicateComponent(
        { componentName: 'PricingSection', slug: 'pricing_table', props: ['title', 'plans'] },
        [{ registryId: 'webu_general_hero_01', key: 'hero', componentName: 'HeroSection', category: 'hero', capabilities: ['title', 'subtitle'] }]
      );
      expect(result.isDuplicate).toBe(false);
      expect(result.action).toBe('create');
    });
  });

  describe('checkDuplicateFromSpec', () => {
    it('returns addVariant when spec matches existing PricingSection', () => {
      const spec = generateComponentSpec({ prompt: 'Create pricing table' });
      const existing: ExistingComponentSummary[] = [
        { registryId: 'webu_general_pricing_table_01', key: 'pricing', componentName: 'PricingSection', category: 'pricing', capabilities: ['title', 'plans', 'price'] },
      ];
      const result = checkDuplicateFromSpec(spec, existing);
      expect(result.isDuplicate).toBe(true);
      expect(result.action).toBe('addVariant');
    });

    it('returns create when no existing summaries', () => {
      const spec = generateComponentSpec({ prompt: 'Create pricing table' });
      const result = checkDuplicateFromSpec(spec, []);
      expect(result.isDuplicate).toBe(false);
      expect(result.action).toBe('create');
    });
  });

  describe('buildExistingSummariesFromRegistry', () => {
    it('builds summaries from getRegistryEntries', () => {
      const summaries = buildExistingSummariesFromRegistry(() => [
        { registryId: 'webu_general_pricing_table_01', key: 'pricing', entry: { schema: { editableFields: [{ key: 'title' }, { key: 'plans' }] } } },
      ]);
      expect(summaries).toHaveLength(1);
      expect(summaries[0].registryId).toBe('webu_general_pricing_table_01');
      expect(summaries[0].key).toBe('pricing');
      expect(summaries[0].componentName).toBe('PricingSection');
      expect(summaries[0].category).toBe('pricing');
      expect(summaries[0].capabilities).toEqual(['title', 'plans']);
    });
  });

  describe('getExistingSummariesFromBuilderRegistry', () => {
    it('builds summaries from registry snapshot', () => {
      const summaries = getExistingSummariesFromBuilderRegistry({
        registryIdToKey: { webu_general_hero_01: 'hero' },
        getEntry: (id) => (id === 'webu_general_hero_01' ? { schema: { fields: [{ path: 'title' }] } } : null),
      });
      expect(summaries).toHaveLength(1);
      expect(summaries[0].key).toBe('hero');
      expect(summaries[0].componentName).toBe('HeroSection');
      expect(summaries[0].capabilities).toEqual(['title']);
    });
  });

  describe('generateComponentSpecWithDuplicateCheck (integration)', () => {
    it('returns spec and addVariant when PricingSection exists in summaries', () => {
      const existing: ExistingComponentSummary[] = [
        { registryId: 'webu_general_pricing_table_01', key: 'pricing', componentName: 'PricingSection', category: 'pricing', capabilities: ['title', 'plans'] },
      ];
      const { spec, duplicateResult } = generateComponentSpecWithDuplicateCheck(
        { prompt: 'Create pricing table', designStyle: 'modern' },
        existing
      );
      expect(spec.componentName).toBe('PricingSection');
      expect(duplicateResult.isDuplicate).toBe(true);
      expect(duplicateResult.action).toBe('addVariant');
      expect(duplicateResult.existingKey).toBe('pricing');
    });

    it('returns spec and create when no duplicate', () => {
      const { spec, duplicateResult } = generateComponentSpecWithDuplicateCheck(
        { prompt: 'Create pricing table' },
        []
      );
      expect(spec.componentName).toBe('PricingSection');
      expect(duplicateResult.isDuplicate).toBe(false);
      expect(duplicateResult.action).toBe('create');
    });
  });
});
