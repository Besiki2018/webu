import { generateComponentFolder } from '../componentFolderGenerator';
import { generateComponentSpec } from '../componentSpecGenerator';

describe('componentFolderGenerator', () => {
  it('generates folder with Pricing.tsx, schema, defaults, variants, index', () => {
    const spec = generateComponentSpec({
      prompt: 'Create pricing table',
      designStyle: 'modern',
    });
    const folder = generateComponentFolder(spec);
    expect(folder.folderPath).toBe('components/sections/Pricing');
    expect(folder.componentShortName).toBe('Pricing');
    expect(folder.files).toHaveLength(5);

    const paths = folder.files.map((f) => f.path);
    expect(paths).toContain('components/sections/Pricing/Pricing.tsx');
    expect(paths).toContain('components/sections/Pricing/Pricing.schema.ts');
    expect(paths).toContain('components/sections/Pricing/Pricing.defaults.ts');
    expect(paths).toContain('components/sections/Pricing/Pricing.variants.ts');
    expect(paths).toContain('components/sections/Pricing/index.ts');
  });

  it('Pricing.tsx imports from ./Pricing.variants', () => {
    const spec = generateComponentSpec({
      prompt: 'Create pricing table',
      designStyle: 'modern',
    });
    const folder = generateComponentFolder(spec);
    const tsxFile = folder.files.find((f) => f.path.endsWith('.tsx'));
    expect(tsxFile?.content).toContain("import { PRICING_DEFAULT_VARIANT, type PricingVariantId } from './Pricing.variants'");
  });

  it('index.ts re-exports component, schema, defaults, variants', () => {
    const spec = generateComponentSpec({
      prompt: 'Create pricing table',
      designStyle: 'modern',
    });
    const folder = generateComponentFolder(spec);
    const indexFile = folder.files.find((f) => f.path.endsWith('index.ts'));
    expect(indexFile?.content).toContain("export { default as Pricing, type PricingProps } from './Pricing'");
    expect(indexFile?.content).toContain("export { PricingSchema } from './Pricing.schema'");
    expect(indexFile?.content).toContain("export { PricingDefaults } from './Pricing.defaults'");
    expect(indexFile?.content).toContain("export { PRICING_VARIANTS, PRICING_DEFAULT_VARIANT, type PricingVariantId } from './Pricing.variants'");
  });
});
