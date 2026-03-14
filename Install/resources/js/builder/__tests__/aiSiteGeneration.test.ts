import { beforeEach, describe, expect, it, vi } from 'vitest';
import * as componentRegistry from '../componentRegistry';
import {
  __resetBuildTreeFromStructureCacheForTests,
  buildTreeFromStructure,
} from '../aiSiteGeneration';

describe('aiSiteGeneration', () => {
  beforeEach(() => {
    __resetBuildTreeFromStructureCacheForTests();
    vi.restoreAllMocks();
  });

  it('builds a stable component tree from direct structure input', () => {
    const tree = buildTreeFromStructure({
      projectType: 'landing',
      structure: [
        { componentKey: 'webu_header_01' },
        { componentKey: 'webu_general_hero_01', variant: 'hero-2', props: { title: 'Custom hero title' } },
        { componentKey: 'webu_footer_01' },
      ],
    });

    expect(tree.map((node) => node.id)).toEqual(['header-1', 'general-hero-2', 'footer-3']);
    expect(tree[1]).toMatchObject({
      componentKey: 'webu_general_hero_01',
      variant: 'hero-2',
      props: expect.objectContaining({
        title: 'Custom hero title',
        variant: 'hero-2',
      }),
    });
  });

  it('reuses the cached tree build for identical structures', () => {
    const entrySpy = vi.spyOn(componentRegistry, 'getEntry');

    const input = {
      projectType: 'landing' as const,
      structure: [
        { componentKey: 'webu_header_01' },
        { componentKey: 'webu_general_hero_01' },
        { componentKey: 'webu_footer_01' },
      ],
    };

    const first = buildTreeFromStructure(input);
    const second = buildTreeFromStructure(input);

    expect(first).toEqual(second);
    expect(first).not.toBe(second);
    expect(entrySpy).toHaveBeenCalledTimes(3);
  });
});
