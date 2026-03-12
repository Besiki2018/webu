import { generatedSystemToOverrides } from '../DesignSystemPanel';
import { generateDesignSystem } from '@/builder/ai/designSystemGenerator';

describe('DesignSystemPanel (generatedSystemToOverrides)', () => {
  it('maps generated system to overrides with primaryColor', () => {
    const system = generateDesignSystem({
      projectType: 'saas',
      industry: 'AI tools',
      designStyle: 'modern',
      brandName: 'Webu',
    });
    const overrides = generatedSystemToOverrides(system);
    expect(overrides.primaryColor).toBe(system.colors.primary);
    expect(overrides.spacingScale).toBeDefined();
    expect(overrides.radiusScale).toBeDefined();
    expect(['tight', 'moderate', 'spacious']).toContain(overrides.spacingScale);
    expect(['sharp', 'rounded', 'pill']).toContain(overrides.radiusScale);
  });

  it('infers serif when heading font contains serif', () => {
    const system = generateDesignSystem({
      projectType: 'landing',
      industry: 'furniture',
      designStyle: 'elegant',
      brandName: 'Luxury',
    });
    const overrides = generatedSystemToOverrides(system);
    expect(overrides.headingFont).toBeDefined();
    expect(overrides.bodyFont).toBeDefined();
  });
});
