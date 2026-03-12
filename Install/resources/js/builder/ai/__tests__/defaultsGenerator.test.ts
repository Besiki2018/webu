import { generateDefaults } from '../defaultsGenerator';
import { generateComponentSpec } from '../componentSpecGenerator';

describe('defaultsGenerator', () => {
  it('generates Pricing.defaults.ts with title and plans array', () => {
    const spec = generateComponentSpec({
      prompt: 'Create pricing table',
      designStyle: 'modern',
    });
    const out = generateDefaults(spec);
    expect(out.filePath).toBe('components/sections/Pricing/Pricing.defaults.ts');
    expect(out.exportName).toBe('PricingDefaults');
    expect(out.source).toContain('export const PricingDefaults');
    expect(out.source).toContain('title: "Pricing Plans"');
    expect(out.source).toContain('plans: [');
    expect(out.source).toContain('name: "Basic"');
    expect(out.source).toContain('price: "$19"');
    expect(out.source).toContain('cta: "Start"');
    expect(out.source).toContain('name: "Pro"');
    expect(out.source).toContain('price: "$49"');
    expect(out.source).toContain('name: "Enterprise"');
    expect(out.source).toContain('price: "$99"');
    expect(out.source).toContain('cta: "Contact"');
  });

  it('generates FaqAccordion defaults with items (question/answer)', () => {
    const spec = generateComponentSpec({
      prompt: 'Create FAQ accordion',
      designStyle: 'minimal',
    });
    const out = generateDefaults(spec);
    expect(out.filePath).toBe('components/sections/Faq/Faq.defaults.ts');
    expect(out.source).toContain('FaqDefaults');
    expect(out.source).toContain('title: "Frequently asked questions"');
    expect(out.source).toContain('items: [');
    expect(out.source).toContain('question:');
    expect(out.source).toContain('answer:');
  });

  it('generates TeamSection defaults with members', () => {
    const spec = generateComponentSpec({
      prompt: 'Create team section',
      designStyle: 'modern',
    });
    const out = generateDefaults(spec);
    expect(out.source).toContain('members: [');
    expect(out.source).toContain('name: "Jane Doe"');
    expect(out.source).toContain('role: "Lead"');
  });
});
