/**
 * FINAL RESULT — Create component pipeline tests.
 */
import { runCreateComponentPipeline, addCreatedComponentToCanvas } from '../createComponentPipeline';

describe('createComponentPipeline', () => {
  it('"Create pricing section" returns action create with spec, folder, registryId', () => {
    const result = runCreateComponentPipeline({ prompt: 'Create pricing section' });
    expect(result.success).toBe(true);
    expect(result.action).toBe('create');
    expect(result.spec?.componentName).toBe('PricingSection');
    expect(result.spec?.slug).toBe('pricing_table');
    expect(result.folder?.files.length).toBeGreaterThan(0);
    expect(result.registryId).toBeTruthy();
    expect(result.folder?.files.some((f) => f.path.endsWith('.tsx'))).toBe(true);
    expect(result.folder?.files.some((f) => f.path.endsWith('.schema.ts'))).toBe(true);
    expect(result.folder?.files.some((f) => f.path.endsWith('.defaults.ts'))).toBe(true);
  });

  it('validation is run and passes for generated pricing folder', () => {
    const result = runCreateComponentPipeline({ prompt: 'Create pricing section' });
    expect(result.validation).toBeDefined();
    expect(result.validation?.valid).toBe(true);
  });

  it('empty prompt returns success false', () => {
    const result = runCreateComponentPipeline({ prompt: '' });
    expect(result.success).toBe(false);
    expect(result.reason).toContain('Empty');
  });

  it('non-component prompt returns success false', () => {
    const result = runCreateComponentPipeline({ prompt: 'What is the weather?' });
    expect(result.success).toBe(false);
    expect(result.reason).toContain('Not a component request');
  });

  it('addCreatedComponentToCanvas calls addSectionByKey with registryId', () => {
    const calls: Array<{ key: string; source: string }> = [];
    const addSectionByKey = (sectionKey: string, source: 'library' | 'toolbar') => {
      calls.push({ key: sectionKey, source });
    };
    addCreatedComponentToCanvas(addSectionByKey, 'webu_general_pricing_table_01');
    expect(calls).toHaveLength(1);
    expect(calls[0].key).toBe('webu_general_pricing_table_01');
    expect(calls[0].source).toBe('library');
  });
});
