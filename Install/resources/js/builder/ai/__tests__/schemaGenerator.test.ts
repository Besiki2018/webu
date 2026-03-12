import { generateSchema } from '../schemaGenerator';
import { generateComponentSpec } from '../componentSpecGenerator';

describe('schemaGenerator', () => {
  it('generates Pricing.schema.ts with component and editableFields', () => {
    const spec = generateComponentSpec({
      prompt: 'Create pricing table',
      designStyle: 'modern',
    });
    const out = generateSchema(spec);
    expect(out.filePath).toBe('components/sections/Pricing/Pricing.schema.ts');
    expect(out.schemaName).toBe('PricingSchema');
    expect(out.source).toContain('export const PricingSchema');
    expect(out.source).toContain('component: "pricing"');
    expect(out.source).toContain('editableFields:');
    expect(out.source).toContain('{ key: "title", type: "text" }');
    expect(out.source).toContain('{ key: "plans", type: "list" }');
    expect(out.source).toContain('{ key: "price", type: "text" }');
    expect(out.source).toContain('{ key: "features", type: "list" }');
    expect(out.source).toContain('{ key: "ctaButton", type: "text" }');
    expect(out.source).toContain('{ key: "variant", type: "select" }');
    expect(out.source).toContain('{ key: "backgroundColor", type: "color" }');
  });

  it('generates FaqAccordion schema with items as list', () => {
    const spec = generateComponentSpec({
      prompt: 'Create FAQ accordion',
      designStyle: 'minimal',
    });
    const out = generateSchema(spec);
    expect(out.filePath).toBe('components/sections/Faq/Faq.schema.ts');
    expect(out.source).toContain('component: "faq"');
    expect(out.source).toContain('{ key: "items", type: "list" }');
    expect(out.source).toContain('{ key: "question", type: "text" }');
    expect(out.source).toContain('{ key: "answer", type: "text" }');
  });

  it('generates TeamSection schema with members as list', () => {
    const spec = generateComponentSpec({
      prompt: 'Create team section',
      designStyle: 'modern',
    });
    const out = generateSchema(spec);
    expect(out.source).toContain('{ key: "members", type: "list" }');
    expect(out.source).toContain('{ key: "name", type: "text" }');
    expect(out.source).toContain('{ key: "textColor", type: "color" }');
  });
});
