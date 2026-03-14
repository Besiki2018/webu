import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createBlueprint } from '../../ai/createBlueprint';
import { useBuilderStore } from '../../store/builderStore';
import {
  GENERATE_SITE_COMMAND,
  runGenerateSite,
} from '../generateSite';

describe('generateSite command', () => {
  beforeEach(() => {
    useBuilderStore.setState({
      projectType: 'landing',
      componentTree: [],
    });
    vi.restoreAllMocks();
  });

  it('exposes command name for AI', () => {
    expect(GENERATE_SITE_COMMAND).toBe('generate_site');
  });

  it('builds from direct structure when structure is provided', () => {
    const infoSpy = vi.spyOn(console, 'info').mockImplementation(() => undefined);

    const result = runGenerateSite({
      projectType: 'landing',
      structure: [
        { componentKey: 'webu_header_01' },
        { componentKey: 'webu_general_hero_01' },
        { componentKey: 'webu_footer_01' },
      ],
    });

    expect(result.ok).toBe(true);
    expect(result.generationMode).toBe('direct-structure');
    expect(result.trace.resolvedMode).toBe('direct-structure');
    expect(useBuilderStore.getState().componentTree).toHaveLength(3);
    expect(infoSpy).toHaveBeenCalledWith(
      '[builder.generateSite] applied',
      expect.objectContaining({
        resolvedMode: 'direct-structure',
        nodeCount: 3,
      }),
    );
  });

  it('builds from blueprint when structure is missing', () => {
    const blueprint = createBlueprint({
      prompt: 'Create a minimalist SaaS landing page for finance teams',
    });

    const result = runGenerateSite({
      blueprint,
    });

    expect(result.ok).toBe(true);
    expect(result.projectType).toBe('saas');
    expect(result.generationMode).toBe('blueprint');
    expect(result.trace.resolvedMode).toBe('blueprint');
    expect(useBuilderStore.getState().componentTree.length).toBeGreaterThan(0);
    expect(result.diagnostics?.selectedProjectType).toBe('saas');
    expect(result.diagnostics?.selectedBusinessType).toBe('Finance Software');
    expect(result.diagnostics?.selectedSections.length).toBeGreaterThan(0);
    expect(result.diagnostics?.events.some((entry) => entry.step === 'component_scores')).toBe(true);
  });

  it('returns an explicit error when neither blueprint nor structure exists', () => {
    const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => undefined);

    const result = runGenerateSite({
      projectType: 'saas',
    });

    expect(result.ok).toBe(false);
    expect(result.nodeCount).toBe(0);
    expect(result.trace.resolvedMode).toBe('error');
    expect(result.error).toBe('Generation failed: no site blueprint or direct structure was provided. Emergency fallback must be requested explicitly.');
    expect(useBuilderStore.getState().componentTree).toEqual([]);
    expect(errorSpy).toHaveBeenCalledWith(
      '[builder.generateSite] failed',
      expect.objectContaining({
        resolvedMode: 'error',
        error: 'Generation failed: no site blueprint or direct structure was provided. Emergency fallback must be requested explicitly.',
      }),
    );
  });

  it('uses emergency fallback only when explicitly requested', () => {
    const result = runGenerateSite({
      projectType: 'ecommerce',
      generationMode: 'emergency-fallback',
    });

    expect(result.ok).toBe(true);
    expect(result.projectType).toBe('ecommerce');
    expect(result.generationMode).toBe('emergency-fallback');
    expect(result.trace.resolvedMode).toBe('emergency-fallback');
    expect(useBuilderStore.getState().componentTree.length).toBeGreaterThan(0);
    expect(result.diagnostics?.fallbackUsed).toBe(true);
  });

  it('fails fast when direct structure includes invalid component keys', () => {
    const result = runGenerateSite({
      projectType: 'landing',
      structure: [
        { componentKey: 'webu_header_01' },
        { componentKey: 'not_a_real_component' },
        { componentKey: 'webu_footer_01' },
      ],
    });

    expect(result.ok).toBe(false);
    expect(result.trace.resolvedMode).toBe('error');
    expect(result.error).toContain('Generated site validation failed:');
    expect(result.error).toContain('Unknown component key "not_a_real_component"');
    expect(result.diagnostics?.failedStep).toBe('validation');
    expect(useBuilderStore.getState().componentTree).toEqual([]);
  });

  it('rolls back the current builder state when apply crashes mid-mutation', () => {
    const previousTree = [
      {
        id: 'existing-hero',
        componentKey: 'webu_general_hero_01',
        props: { title: 'Existing site state' },
      },
    ];
    useBuilderStore.setState({
      projectType: 'business',
      componentTree: previousTree,
      selectedComponentId: 'existing-hero',
    });

    const storeState = useBuilderStore.getState();
    const originalSetComponentTree = storeState.setComponentTree;
    storeState.setComponentTree = () => {
      throw new Error('apply exploded');
    };

    try {
      const result = runGenerateSite({
        projectType: 'landing',
        structure: [
          { componentKey: 'webu_header_01' },
          { componentKey: 'webu_general_hero_01' },
          { componentKey: 'webu_footer_01' },
        ],
      });

      expect(result.ok).toBe(false);
      expect(result.error).toContain('Failed to apply generated site: apply exploded');
      expect(result.diagnostics?.failedStep).toBe('tree');
      expect(useBuilderStore.getState().projectType).toBe('business');
      expect(useBuilderStore.getState().componentTree).toEqual(previousTree);
      expect(useBuilderStore.getState().selectedComponentId).toBe('existing-hero');
    } finally {
      useBuilderStore.getState().setComponentTree = originalSetComponentTree;
    }
  });

  it('skips builder mutation when the generated tree is identical to the current state', () => {
    const initial = runGenerateSite({
      projectType: 'landing',
      structure: [
        { componentKey: 'webu_header_01' },
        { componentKey: 'webu_general_hero_01' },
        { componentKey: 'webu_footer_01' },
      ],
    });

    expect(initial.ok).toBe(true);

    const existingTreeReference = useBuilderStore.getState().componentTree;
    const result = runGenerateSite({
      projectType: 'landing',
      structure: [
        { componentKey: 'webu_header_01' },
        { componentKey: 'webu_general_hero_01' },
        { componentKey: 'webu_footer_01' },
      ],
    });

    expect(result.ok).toBe(true);
    expect(result.diagnostics?.events.some((entry) => (
      entry.step === 'tree' && entry.message === 'tree apply skipped because structure is unchanged'
    ))).toBe(true);
    expect(useBuilderStore.getState().componentTree).toBe(existingTreeReference);
  });
});
