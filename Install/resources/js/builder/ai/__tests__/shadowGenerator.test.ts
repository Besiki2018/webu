import {
  generateShadowScale,
  shadowScaleToCssVars,
  DEFAULT_SHADOW_SCALE,
  type ShadowScale,
} from '../shadowGenerator';

describe('shadowGenerator', () => {
  it('returns shadow scale with sm, md, lg, xl', () => {
    const scale = generateShadowScale({});
    expect(scale.none).toBe('none');
    expect(scale.sm).toBeDefined();
    expect(scale.md).toBeDefined();
    expect(scale.lg).toBeDefined();
    expect(scale.xl).toBeDefined();
  });

  it('shadow-md matches example: 0 10px 25px rgba(0,0,0,0.1) for soft', () => {
    const scale = generateShadowScale({ style: 'soft' });
    expect(scale.md).toContain('10px');
    expect(scale.md).toContain('25px');
    expect(scale.md).toContain('rgba(0,0,0,0.1)');
  });

  it('shadowScaleToCssVars produces --shadow-* vars', () => {
    const scale = generateShadowScale({ style: 'flat' });
    const vars = shadowScaleToCssVars(scale);
    expect(vars['--shadow-none']).toBe('none');
    expect(vars['--shadow-sm']).toBeDefined();
    expect(vars['--shadow-md']).toBeDefined();
    expect(vars['--shadow-lg']).toBeDefined();
    expect(vars['--shadow-xl']).toBeDefined();
  });

  it('DEFAULT_SHADOW_SCALE is soft style', () => {
    expect(DEFAULT_SHADOW_SCALE.md).toContain('10px');
  });

  it('style flat produces lighter shadows', () => {
    const flat = generateShadowScale({ style: 'flat' });
    const strong = generateShadowScale({ style: 'strong' });
    expect(flat.md).not.toBe(strong.md);
  });
});
