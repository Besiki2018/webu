import { generateVariants } from '../variantsGenerator';
import { generateComponentSpec } from '../componentSpecGenerator';

describe('variantsGenerator', () => {
  it('generates Pricing.variants.ts with VARIANTS and DEFAULT_VARIANT', () => {
    const spec = generateComponentSpec({
      prompt: 'Create pricing table',
      designStyle: 'modern',
    });
    const out = generateVariants(spec);
    expect(out.filePath).toBe('components/sections/Pricing/Pricing.variants.ts');
    expect(out.defaultVariantConstantName).toBe('PRICING_DEFAULT_VARIANT');
    expect(out.source).toContain('export const PRICING_VARIANTS');
    expect(out.source).toContain("'cards'");
    expect(out.source).toContain("'horizontal'");
    expect(out.source).toContain("'minimal'");
    expect(out.source).toContain('export type PricingVariantId');
    expect(out.source).toContain("PRICING_DEFAULT_VARIANT: PricingVariantId = 'cards'");
    expect(out.source).toContain('PRICING_VARIANT_OPTIONS');
  });
});
