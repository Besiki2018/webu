import { generateDesignSystem, designSystemToCssVars } from '../designSystemGenerator';

describe('designSystemGenerator', () => {
  it('returns a full design system for example input', () => {
    const result = generateDesignSystem({
      projectType: 'saas',
      industry: 'AI tools',
      designStyle: 'modern',
      brandName: 'Webu',
    });

    expect(result.colors).toBeDefined();
    expect(result.colors.primary).toBe('#2563eb');
    expect(result.colors.background).toBe('#ffffff');
    expect(result.typography).toBeDefined();
    expect(result.typography.fontSizes.base).toBe('1rem');
    expect(result.spacing).toBeDefined();
    expect(result.spacing.md).toBe('1rem');
    expect(result.radius).toBeDefined();
    expect(result.radius.button).toBeDefined();
    expect(result.shadows).toBeDefined();
    expect(result.shadows.card).toBeDefined();
    expect(result.buttonStyles).toBeDefined();
    expect(result.buttonStyles.primary.background).toBe('#2563eb');
    expect(result.buttonStyles.outline.color).toBe('#2563eb');
    expect(result.cssVarPrefix).toBe('--webu-token-');
  });

  it('varies palette by designStyle', () => {
    const minimal = generateDesignSystem({
      projectType: 'landing',
      industry: 'fashion',
      designStyle: 'minimal',
      brandName: 'Brand',
    });
    expect(minimal.colors.primary).toBe('#18181b');

    const bold = generateDesignSystem({
      projectType: 'landing',
      industry: 'entertainment',
      designStyle: 'bold',
      brandName: 'Brand',
    });
    expect(bold.colors.primary).toBe('#7c3aed');
  });

  it('designSystemToCssVars produces --webu-token-* variables', () => {
    const system = generateDesignSystem({
      projectType: 'saas',
      industry: 'AI tools',
      designStyle: 'modern',
      brandName: 'Webu',
    });
    const vars = designSystemToCssVars(system);
    expect(vars['--webu-token-color-primary']).toBe('#2563eb');
    expect(vars['--webu-token-space-md']).toBe('1rem');
    expect(vars['--webu-token-radius-button']).toBeDefined();
    expect(vars['--webu-token-shadow-card']).toBeDefined();
    expect(vars['--webu-token-font-size-base']).toBe('1rem');
  });
});
