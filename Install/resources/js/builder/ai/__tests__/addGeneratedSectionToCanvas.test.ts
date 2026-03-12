import {
  addGeneratedSectionToCanvas,
  getRegistryIdForGeneratedSpec,
  addGeneratedSpecSectionToCanvas,
} from '../addGeneratedSectionToCanvas';
import { generateComponentSpec } from '../componentSpecGenerator';

describe('addGeneratedSectionToCanvas', () => {
  it('getRegistryIdForGeneratedSpec returns registry ID for pricing spec', () => {
    const spec = generateComponentSpec({
      prompt: 'Create pricing table',
      designStyle: 'modern',
    });
    const registryId = getRegistryIdForGeneratedSpec(spec);
    expect(registryId).toBe('webu_general_pricing_table_01');
  });

  it('addGeneratedSectionToCanvas calls addSectionByKey with registry ID and library', () => {
    const added: Array<{ key: string; source: string; insertIndex?: number }> = [];
    const addSectionByKey = (sectionKey: string, source: 'library' | 'toolbar', options?: { insertIndex?: number }) => {
      added.push({ key: sectionKey, source, insertIndex: options?.insertIndex });
    };
    addGeneratedSectionToCanvas(addSectionByKey, 'webu_general_pricing_table_01');
    expect(added).toHaveLength(1);
    expect(added[0]!.key).toBe('webu_general_pricing_table_01');
    expect(added[0]!.source).toBe('library');
    expect(added[0]!.insertIndex).toBeUndefined();
  });

  it('addGeneratedSectionToCanvas with insertIndex passes it through', () => {
    const added: Array<{ key: string; insertIndex?: number }> = [];
    const addSectionByKey = (sectionKey: string, _source: 'library' | 'toolbar', options?: { insertIndex?: number }) => {
      added.push({ key: sectionKey, insertIndex: options?.insertIndex });
    };
    addGeneratedSectionToCanvas(addSectionByKey, 'webu_general_cta_01', { insertIndex: 2 });
    expect(added[0]!.insertIndex).toBe(2);
  });

  it('addGeneratedSpecSectionToCanvas uses spec to get registry ID and adds section', () => {
    const added: Array<{ key: string }> = [];
    const addSectionByKey = (sectionKey: string) => {
      added.push({ key: sectionKey });
    };
    const spec = generateComponentSpec({
      prompt: 'Create FAQ accordion',
      designStyle: 'minimal',
    });
    addGeneratedSpecSectionToCanvas(addSectionByKey, spec);
    expect(added).toHaveLength(1);
    expect(added[0]!.key).toBe('webu_general_faq_accordion_01');
  });
});
