import { generateComponentCode } from '../componentCodeGenerator';
import { generateComponentSpec } from '../componentSpecGenerator';

describe('componentCodeGenerator', () => {
  it('generates Pricing component for pricing spec', () => {
    const spec = generateComponentSpec({
      prompt: 'Create pricing table',
      designStyle: 'modern',
    });
    const out = generateComponentCode(spec);
    expect(out.componentName).toBe('PricingSection');
    expect(out.filePath).toBe('components/sections/Pricing/Pricing.tsx');
    expect(out.source).toContain('export interface Plan');
    expect(out.source).toContain('name?:');
    expect(out.source).toContain('price?:');
    expect(out.source).toContain('cta?:');
    expect(out.source).toContain('export interface PricingProps');
    expect(out.source).toContain('plans?: Plan[]');
    expect(out.source).toContain('export default function Pricing');
    expect(out.source).toContain('props.title');
    expect(out.source).toContain('pricing-grid');
    expect(out.source).toContain('props.plans ?? []');
    expect(out.source).toContain('plan.name');
    expect(out.source).toContain('plan.cta');
    expect(out.source).toContain('props.variant');
    expect(out.source).toContain('backgroundColor');
  });

  it('with useVariantsImport emits import from ./Pricing.variants', () => {
    const spec = generateComponentSpec({
      prompt: 'Create pricing table',
      designStyle: 'modern',
    });
    const out = generateComponentCode(spec, { useVariantsImport: true });
    expect(out.source).toContain("from './Pricing.variants'");
    expect(out.source).toContain('PRICING_DEFAULT_VARIANT');
  });

  it('generates FaqAccordion with details/summary', () => {
    const spec = generateComponentSpec({
      prompt: 'Create FAQ accordion',
      designStyle: 'minimal',
    });
    const out = generateComponentCode(spec);
    expect(out.filePath).toBe('components/sections/Faq/Faq.tsx');
    expect(out.source).toContain('faq-accordion');
    expect(out.source).toContain('<details');
    expect(out.source).toContain('<summary>');
    expect(out.source).toContain('item.question');
    expect(out.source).toContain('item.answer');
  });

  it('generates TeamSection with members grid', () => {
    const spec = generateComponentSpec({
      prompt: 'Create team section',
      designStyle: 'modern',
    });
    const out = generateComponentCode(spec);
    expect(out.source).toContain('export interface Member');
    expect(out.source).toContain('members?: Member[]');
    expect(out.source).toContain('member.name');
  });
});
