/**
 * Optimize for project type command — runOptimizeForProjectType.
 */

import { describe, it, expect, beforeEach } from 'vitest';
import { useBuilderStore } from '../../store/builderStore';
import { runOptimizeForProjectType, OPTIMIZE_FOR_PROJECT_TYPE_COMMAND } from '../optimizeForProjectType';
import type { BuilderComponentInstance } from '../../core/types';

function node(id: string, componentKey: string, props: Record<string, unknown> = {}): BuilderComponentInstance {
  return { id, componentKey, props };
}

describe('optimizeForProjectType command', () => {
  beforeEach(() => {
    useBuilderStore.setState({
      projectType: 'landing',
      componentTree: [],
    });
  });

  it('exposes command name for AI', () => {
    expect(OPTIMIZE_FOR_PROJECT_TYPE_COMMAND).toBe('optimize_for_project_type');
  });

  it('sets project type and applies refactors when tree has header with search', () => {
    const tree: BuilderComponentInstance[] = [
      node('header-1', 'webu_header_01', { showSearch: true, searchMode: 'generic' }),
    ];
    useBuilderStore.setState({ componentTree: tree });

    const result = runOptimizeForProjectType('ecommerce');

    expect(result.ok).toBe(true);
    expect(result.projectType).toBe('ecommerce');
    expect(result.updatesApplied).toBe(1);
    expect(result.summary.length).toBeGreaterThanOrEqual(1);

    const state = useBuilderStore.getState();
    expect(state.projectType).toBe('ecommerce');
    expect(state.componentTree).toHaveLength(1);
    expect(state.componentTree[0]!.props.showSearch).toBe(true);
    expect(state.componentTree[0]!.props.searchMode).toBe('product');
    expect(state.componentTree[0]!.props.showCartIcon).toBe(true);
  });

  it('accepts invalid project type and falls back to landing', () => {
    const result = runOptimizeForProjectType('invalid_type');
    expect(result.projectType).toBe('landing');
    expect(result.ok).toBe(true);
  });
});
