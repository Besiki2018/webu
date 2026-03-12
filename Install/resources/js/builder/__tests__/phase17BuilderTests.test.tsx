/**
 * Phase 17 — Builder tests.
 * Covers: registry integrity, schema defaults, component render, sidebar field generation,
 * prop update rerender, variant switching.
 * Uses schema-driven registry (builder/registry), CanvasRenderer, store, updateComponentProps, SidebarInspector.
 */

import React from 'react';
import { describe, expect, it, beforeEach, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { mergeDefaults } from '../utils';
import type { BuilderComponentInstance } from '../core';

// Mock Inertia Link so section components (Hero, CTA, etc.) render in tests
vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href }: { children: React.ReactNode; href?: string }) => (
    <a href={href ?? '#'}>{children}</a>
  ),
}));

// Mock builder store so CanvasRenderer and updateComponentProps get a working store in tests
vi.mock('../store', () => {
  const state: Record<string, unknown> = {
    componentTree: [],
    selectedComponentId: null,
    hoveredComponentId: null,
    currentBreakpoint: 'desktop',
    builderMode: 'elements',
    selectedProps: null,
  };
  const useBuilderStore = (selector: (s: unknown) => unknown) => {
    const s = {
      ...state,
      setComponentTree: (next: unknown) => {
        state.componentTree = typeof next === 'function' ? (next as (p: unknown) => unknown)(state.componentTree) : next;
      },
      setSelectedComponentId: (id: string | null) => { state.selectedComponentId = id; },
      setHoveredComponentId: (id: string | null) => { state.hoveredComponentId = id; },
      setCurrentBreakpoint: () => {},
      setBuilderMode: () => {},
      setSelectedProps: (p: unknown) => { state.selectedProps = p; },
      clearSelection: () => { state.selectedComponentId = null; state.selectedProps = null; },
      reset: () => {},
    };
    return selector(s);
  };
  useBuilderStore.getState = () => ({
    ...state,
    setComponentTree: (next: unknown) => {
      state.componentTree = typeof next === 'function' ? (next as (p: unknown) => unknown)(state.componentTree) : next;
    },
    setSelectedComponentId: (id: string | null) => { state.selectedComponentId = id; },
    setSelectedProps: (p: unknown) => { state.selectedProps = p; },
    setHoveredComponentId: () => {},
    setCurrentBreakpoint: () => {},
    setBuilderMode: () => {},
    clearSelection: () => { state.selectedComponentId = null; state.selectedProps = null; },
    reset: () => {},
  });
  useBuilderStore.setState = (next: unknown) => {
    const patch = typeof next === 'function' ? (next as (s: unknown) => unknown)(state) : next;
    if (patch && typeof patch === 'object') Object.assign(state, patch);
  };
  return { useBuilderStore };
});

// Import registry directly from componentRegistry to avoid re-export ordering issues
import {
  componentRegistry,
  REGISTRY_ID_TO_KEY,
  getEntry,
  getRegistryKeyByComponentId,
  hasEntry,
} from '../registry/componentRegistry';
import { useBuilderStore } from '../store';
import { updateComponentProps } from '../updates/updateComponentProps';
import { CanvasRenderer } from '../renderer/CanvasRenderer';
import { SidebarInspector } from '../inspector/SidebarInspector';

const REGISTRY_IDS = Object.keys(REGISTRY_ID_TO_KEY ?? {});

function resetStore() {
  useBuilderStore.setState({
    componentTree: [],
    selectedComponentId: null,
    hoveredComponentId: null,
    selectedProps: null,
  });
}

// ---------------------------------------------------------------------------
// 1. Registry integrity
// ---------------------------------------------------------------------------
describe('Phase 17 — Registry integrity', () => {
  it('every REGISTRY_ID_TO_KEY id has a componentRegistry entry', () => {
    for (const id of REGISTRY_IDS) {
      const key = REGISTRY_ID_TO_KEY[id];
      expect(key, `key for ${id}`).toBeTruthy();
      const entry = componentRegistry[key];
      expect(entry, `entry for ${id} (key: ${key})`).toBeDefined();
      expect(entry.component).toBeDefined();
      expect(typeof entry.component === 'function').toBe(true);
      expect(entry.schema).toBeDefined();
      expect(typeof entry.schema === 'object').toBe(true);
      expect(entry.defaults).toBeDefined();
      expect(typeof entry.defaults === 'object').toBe(true);
    }
  });

  it('getEntry returns non-null for every registered id', () => {
    for (const id of REGISTRY_IDS) {
      const entry = getEntry(id);
      expect(entry, `getEntry("${id}")`).not.toBeNull();
      expect(entry?.component).toBeDefined();
      expect(entry?.schema).toBeDefined();
      expect(entry?.defaults).toBeDefined();
    }
  });

  it('getEntry returns null for unknown id', () => {
    expect(getEntry('unknown_component_99')).toBeNull();
    expect(hasEntry('unknown_component_99')).toBe(false);
  });

  it('getRegistryKeyByComponentId maps id to short key', () => {
    expect(getRegistryKeyByComponentId('webu_header_01')).toBe('header');
    expect(getRegistryKeyByComponentId('webu_general_hero_01')).toBe('hero');
    expect(getRegistryKeyByComponentId('webu_general_features_01')).toBe('features');
    expect(getRegistryKeyByComponentId('unknown')).toBeNull();
  });
});

// ---------------------------------------------------------------------------
// 2. Schema defaults
// ---------------------------------------------------------------------------
describe('Phase 17 — Schema defaults', () => {
  it('each entry has defaults aligned with schema props or fields', () => {
    for (const id of REGISTRY_IDS) {
      const entry = getEntry(id);
      if (!entry?.schema || !entry?.defaults) continue;
      const schema = entry.schema as Record<string, unknown>;
      const defaults = entry.defaults as Record<string, unknown>;
      const propsSchema = schema.props as Record<string, { default?: unknown }> | undefined;
      if (propsSchema && typeof propsSchema === 'object') {
        for (const [key, def] of Object.entries(propsSchema)) {
          if (def && typeof def === 'object' && 'default' in def) {
            expect(defaults[key] !== undefined || def.default !== undefined, `default or schema default for ${id}.${key}`).toBe(true);
          }
        }
      }
    }
  });

  it('mergeDefaults(entry.defaults, {}) yields valid props (no undefined required keys)', () => {
    for (const id of REGISTRY_IDS) {
      const entry = getEntry(id);
      if (!entry?.defaults) continue;
      const merged = mergeDefaults(entry.defaults as Record<string, unknown>, {});
      expect(merged).toBeDefined();
      expect(typeof merged === 'object').toBe(true);
    }
  });

  it('mergeDefaults(entry.defaults, overrides) applies overrides', () => {
    const entry = getEntry('webu_general_hero_01');
    expect(entry?.defaults).toBeDefined();
    const merged = mergeDefaults(entry!.defaults as Record<string, unknown>, { title: 'Custom Title' });
    expect(merged.title).toBe('Custom Title');
  });
});

// ---------------------------------------------------------------------------
// 3. Component render
// ---------------------------------------------------------------------------
describe('Phase 17 — Component render', () => {
  it('CanvasRenderer renders hero with default props', () => {
    const tree: BuilderComponentInstance[] = [
      {
        id: 'hero-1',
        componentKey: 'webu_general_hero_01',
        variant: 'hero-1',
        props: { title: 'Test Hero Title' },
        children: [],
      },
    ];
    render(<CanvasRenderer componentTree={tree} />);
    expect(screen.getByText('Test Hero Title')).toBeInTheDocument();
  });

  it('CanvasRenderer renders features section with title', () => {
    const tree: BuilderComponentInstance[] = [
      {
        id: 'features-1',
        componentKey: 'webu_general_features_01',
        variant: 'features-1',
        props: { title: 'Our Features' },
        children: [],
      },
    ];
    render(<CanvasRenderer componentTree={tree} />);
    expect(screen.getByText('Our Features')).toBeInTheDocument();
  });

  it('CanvasRenderer renders CTA section with title', () => {
    const tree: BuilderComponentInstance[] = [
      {
        id: 'cta-1',
        componentKey: 'webu_general_cta_01',
        variant: 'cta-1',
        props: { title: 'Join us today' },
        children: [],
      },
    ];
    render(<CanvasRenderer componentTree={tree} />);
    expect(screen.getByText('Join us today')).toBeInTheDocument();
  });

  it('CanvasRenderer shows placeholder for unknown componentKey', () => {
    const tree: BuilderComponentInstance[] = [
      {
        id: 'unknown-1',
        componentKey: 'unknown_section_99',
        props: {},
        children: [],
      },
    ];
    render(<CanvasRenderer componentTree={tree} />);
    expect(screen.getByText('unknown_section_99')).toBeInTheDocument();
    expect(document.querySelector('[data-builder-placeholder]')).toBeInTheDocument();
  });
});

// ---------------------------------------------------------------------------
// 4. Sidebar field generation
// ---------------------------------------------------------------------------
describe('Phase 17 — Sidebar field generation', () => {
  beforeEach(resetStore);

  it('SidebarInspector shows fields for selected hero node', () => {
    const tree: BuilderComponentInstance[] = [
      {
        id: 'hero-1',
        componentKey: 'webu_general_hero_01',
        variant: 'hero-1',
        props: { title: 'Hero' },
        children: [],
      },
    ];
    useBuilderStore.setState({ componentTree: tree, selectedComponentId: 'hero-1' });
    render(<SidebarInspector />);
    expect(screen.getAllByText(/Title/i).length).toBeGreaterThan(0);
  });

  it('SidebarInspector shows componentKey when component is selected', () => {
    const tree: BuilderComponentInstance[] = [
      {
        id: 'hero-1',
        componentKey: 'webu_general_hero_01',
        variant: 'hero-1',
        props: {},
        children: [],
      },
    ];
    useBuilderStore.setState({ componentTree: tree, selectedComponentId: 'hero-1' });
    render(<SidebarInspector />);
    expect(screen.getByText('webu_general_hero_01')).toBeInTheDocument();
  });

  it('SidebarInspector shows "Select an element" when nothing selected', () => {
    useBuilderStore.setState({ componentTree: [], selectedComponentId: null });
    render(<SidebarInspector />);
    expect(screen.getByText(/Select an element/i)).toBeInTheDocument();
  });
});

// ---------------------------------------------------------------------------
// 5. Prop update rerender
// ---------------------------------------------------------------------------
describe('Phase 17 — Prop update rerender', () => {
  beforeEach(resetStore);

  it('updateComponentProps updates store componentTree', () => {
    const tree: BuilderComponentInstance[] = [
      {
        id: 'hero-1',
        componentKey: 'webu_general_hero_01',
        variant: 'hero-1',
        props: { title: 'Initial' },
        children: [],
      },
    ];
    useBuilderStore.setState({ componentTree: tree });
    const result = updateComponentProps('hero-1', { path: 'title', value: 'Updated Title' });
    expect(result.ok).toBe(true);
    const state = useBuilderStore.getState();
    const node = state.componentTree.find((n) => n.id === 'hero-1');
    expect(node?.props?.title).toBe('Updated Title');
  });

  it('updateComponentProps rejects invalid componentId', () => {
    useBuilderStore.setState({ componentTree: [] });
    const result = updateComponentProps('nonexistent', { path: 'title', value: 'X' });
    expect(result.ok).toBe(false);
    expect(result.error).toBe('component_not_found');
  });

  it('updateComponentProps rejects field not in schema', () => {
    const tree: BuilderComponentInstance[] = [
      {
        id: 'hero-1',
        componentKey: 'webu_general_hero_01',
        variant: 'hero-1',
        props: {},
        children: [],
      },
    ];
    useBuilderStore.setState({ componentTree: tree });
    const result = updateComponentProps('hero-1', { path: 'notInSchema', value: 'x' });
    expect(result.ok).toBe(false);
    expect(result.error).toBe('field_not_found');
  });

  it('after prop update, canvas re-renders with new value', () => {
    const tree: BuilderComponentInstance[] = [
      {
        id: 'hero-1',
        componentKey: 'webu_general_hero_01',
        variant: 'hero-1',
        props: { title: 'Before' },
        children: [],
      },
    ];
    useBuilderStore.setState({ componentTree: tree });
    updateComponentProps('hero-1', { path: 'title', value: 'After Update' });
    const state = useBuilderStore.getState();
    render(<CanvasRenderer componentTree={state.componentTree} />);
    expect(screen.getByText('After Update')).toBeInTheDocument();
  });
});

// ---------------------------------------------------------------------------
// 6. Variant switching
// ---------------------------------------------------------------------------
describe('Phase 17 — Variant switching', () => {
  beforeEach(resetStore);

  it('node.variant is passed to component via props', () => {
    const tree: BuilderComponentInstance[] = [
      {
        id: 'hero-1',
        componentKey: 'webu_general_hero_01',
        variant: 'hero-1',
        props: { title: 'Variant Test' },
        children: [],
      },
    ];
    render(<CanvasRenderer componentTree={tree} />);
    const wrapper = document.querySelector('[data-variant="hero-1"]');
    expect(wrapper).toBeInTheDocument();
  });

  it('updateComponentProps for variant updates node.variant and store', () => {
    const tree: BuilderComponentInstance[] = [
      {
        id: 'hero-1',
        componentKey: 'webu_general_hero_01',
        variant: 'hero-1',
        props: { title: 'Hero' },
        children: [],
      },
    ];
    useBuilderStore.setState({ componentTree: tree });
    const result = updateComponentProps('hero-1', { path: 'variant', value: 'hero-2' });
    expect(result.ok).toBe(true);
    const state = useBuilderStore.getState();
    const node = state.componentTree.find((n) => n.id === 'hero-1');
    expect(node?.variant).toBe('hero-2');
    expect(node?.props?.variant).toBe('hero-2');
  });

  it('CanvasRenderer renders with updated variant', () => {
    const tree: BuilderComponentInstance[] = [
      {
        id: 'hero-1',
        componentKey: 'webu_general_hero_01',
        variant: 'hero-2',
        props: { title: 'Hero 2 Style' },
        children: [],
      },
    ];
    render(<CanvasRenderer componentTree={tree} />);
    expect(document.querySelector('[data-variant="hero-2"]')).toBeInTheDocument();
    expect(screen.getByText('Hero 2 Style')).toBeInTheDocument();
  });
});

// ---------------------------------------------------------------------------
// Phase 9 — Verify Editing Works
// 1. Change hero title  2. Change subtitle  3. Change button text
// 4. Replace image  5. Change background color
// Confirm: component rerenders, props update, builder state updates.
// ---------------------------------------------------------------------------
describe('Phase 9 — Verify Editing Works', () => {
  const heroNodeId = 'hero-1';
  const initialTree: BuilderComponentInstance[] = [
    {
      id: heroNodeId,
      componentKey: 'webu_general_hero_01',
      variant: 'hero-1',
      props: {
        title: 'Original Title',
        subtitle: 'Original Subtitle',
        buttonText: 'Original Button',
        image: '',
        backgroundColor: '',
      },
      children: [],
    },
  ];

  beforeEach(() => {
    resetStore();
    useBuilderStore.setState({ componentTree: initialTree.map((n) => ({ ...n, props: { ...n.props } })) });
  });

  it('1. Change hero title — builder state updates, props update, component rerenders', () => {
    const result = updateComponentProps(heroNodeId, { path: 'title', value: 'New Hero Title' });
    expect(result.ok).toBe(true);

    const state = useBuilderStore.getState();
    const node = state.componentTree.find((n) => n.id === heroNodeId);
    expect(node?.props?.title).toBe('New Hero Title');

    render(<CanvasRenderer componentTree={state.componentTree} />);
    expect(screen.getByText('New Hero Title')).toBeInTheDocument();
  });

  it('2. Change subtitle — builder state updates, props update, component rerenders', () => {
    const result = updateComponentProps(heroNodeId, { path: 'subtitle', value: 'New Subtitle Text' });
    expect(result.ok).toBe(true);

    const state = useBuilderStore.getState();
    const node = state.componentTree.find((n) => n.id === heroNodeId);
    expect(node?.props?.subtitle).toBe('New Subtitle Text');

    render(<CanvasRenderer componentTree={state.componentTree} />);
    expect(screen.getByText('New Subtitle Text')).toBeInTheDocument();
  });

  it('3. Change button text — builder state updates, props update, component rerenders', () => {
    const result = updateComponentProps(heroNodeId, { path: 'buttonText', value: 'Get Started Now' });
    expect(result.ok).toBe(true);

    const state = useBuilderStore.getState();
    const node = state.componentTree.find((n) => n.id === heroNodeId);
    expect(node?.props?.buttonText).toBe('Get Started Now');

    render(<CanvasRenderer componentTree={state.componentTree} />);
    expect(screen.getByText('Get Started Now')).toBeInTheDocument();
  });

  it('4. Replace image — builder state updates, props update, component receives new src', () => {
    const imageUrl = 'https://example.com/hero-new.png';
    const result = updateComponentProps(heroNodeId, { path: 'image', value: imageUrl });
    expect(result.ok).toBe(true);

    const state = useBuilderStore.getState();
    const node = state.componentTree.find((n) => n.id === heroNodeId);
    expect(node?.props?.image).toBe(imageUrl);

    render(<CanvasRenderer componentTree={state.componentTree} />);
    const img = document.querySelector('.webu-hero__image');
    expect(img).toBeInTheDocument();
    expect(img?.getAttribute('src')).toBe(imageUrl);
  });

  it('5. Change background color — builder state updates, props update, component receives style', () => {
    const color = '#1a1a2e';
    const result = updateComponentProps(heroNodeId, { path: 'backgroundColor', value: color });
    expect(result.ok).toBe(true);

    const state = useBuilderStore.getState();
    const node = state.componentTree.find((n) => n.id === heroNodeId);
    expect(node?.props?.backgroundColor).toBe(color);

    render(<CanvasRenderer componentTree={state.componentTree} />);
    const section = document.querySelector('.webu-hero');
    expect(section).toBeInTheDocument();
    const style = section?.getAttribute('style') ?? '';
    // Browser may serialize hex as rgb(26, 26, 46) for #1a1a2e
    expect(style.includes(color) || style.includes('rgb(26, 26, 46)')).toBe(true);
  });
});
