import {
  generateRegistryInjection,
  getRegistryInjectionSnippet,
} from '../registryInjectionGenerator';
import { generateComponentSpec } from '../componentSpecGenerator';

describe('registryInjectionGenerator', () => {
  it('generates import, REGISTRY_ID_TO_KEY line, and componentRegistry entry for Pricing', () => {
    const spec = generateComponentSpec({
      prompt: 'Create pricing table',
      designStyle: 'modern',
    });
    const out = generateRegistryInjection(spec);
    expect(out.registryId).toBe('webu_general_pricing_table_01');
    expect(out.registryKey).toBe('pricing');
    expect(out.importStatement).toContain("from '@/components/sections/Pricing'");
    expect(out.importStatement).toContain('default as Pricing');
    expect(out.importStatement).toContain('PricingSchema');
    expect(out.importStatement).toContain('PricingDefaults');
    expect(out.registryIdToKeyLine).toContain("webu_general_pricing_table_01: 'pricing'");
    expect(out.componentRegistryEntry).toContain('pricing: {');
    expect(out.componentRegistryEntry).toContain('component: Pricing');
    expect(out.componentRegistryEntry).toContain('schema: PricingSchema');
    expect(out.componentRegistryEntry).toContain('defaults: PricingDefaults');
  });

  it('getRegistryInjectionSnippet returns instructions and all code parts', () => {
    const spec = generateComponentSpec({
      prompt: 'Create pricing table',
      designStyle: 'modern',
    });
    const snippet = getRegistryInjectionSnippet(spec);
    expect(snippet.instructions).toContain('componentRegistry.ts');
    expect(snippet.importLine).toBeTruthy();
    expect(snippet.idToKeyLine).toContain('pricing');
    expect(snippet.registryEntryBlock).toContain('Pricing');
  });
});
