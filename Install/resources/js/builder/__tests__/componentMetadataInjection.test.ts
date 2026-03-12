/**
 * Component Metadata Injection — ensure every instance has componentKey, variant, capabilities, projectTypes.
 */

import { describe, it, expect } from 'vitest';
import {
  getMetadataForComponentKey,
  injectComponentMetadata,
  injectTreeMetadata,
  getInstanceMetadata,
  type ComponentInstanceMetadata,
} from '../componentMetadataInjection';
import type { BuilderComponentInstance } from '../core/types';

function node(
  id: string,
  componentKey: string,
  props: Record<string, unknown> = {},
  variant?: string,
  metadata?: Record<string, unknown>
): BuilderComponentInstance {
  return {
    id,
    componentKey,
    props,
    ...(variant !== undefined && { variant }),
    ...(metadata !== undefined && { metadata }),
  };
}

describe('componentMetadataInjection', () => {
  describe('getMetadataForComponentKey', () => {
    it('returns componentKey, variant, capabilities, projectTypes for header', () => {
      const meta = getMetadataForComponentKey('webu_header_01', 'header-1');
      expect(meta.componentKey).toBe('webu_header_01');
      expect(meta.variant).toBe('header-1');
      expect(Array.isArray(meta.capabilities)).toBe(true);
      expect(meta.capabilities).toContain('navigation');
      expect(meta.capabilities).toContain('search');
      expect(Array.isArray(meta.projectTypes)).toBe(true);
    });

    it('returns empty arrays for unknown component', () => {
      const meta = getMetadataForComponentKey('unknown_component_99');
      expect(meta.componentKey).toBe('unknown_component_99');
      expect(meta.capabilities).toEqual([]);
      expect(meta.projectTypes).toEqual([]);
    });
  });

  describe('injectComponentMetadata', () => {
    it('adds metadata to node from registry schema', () => {
      const n = node('header-1', 'webu_header_01', {}, 'header-1');
      const injected = injectComponentMetadata(n);
      expect(injected.metadata).toBeDefined();
      expect((injected.metadata as ComponentInstanceMetadata).componentKey).toBe('webu_header_01');
      expect((injected.metadata as ComponentInstanceMetadata).variant).toBe('header-1');
      expect((injected.metadata as ComponentInstanceMetadata).capabilities).toContain('navigation');
      expect((injected.metadata as ComponentInstanceMetadata).projectTypes).toBeDefined();
    });

    it('preserves existing metadata and overwrites standard keys', () => {
      const n = node('header-1', 'webu_header_01', {}, undefined, { custom: 'value' });
      const injected = injectComponentMetadata(n);
      expect((injected.metadata as Record<string, unknown>).custom).toBe('value');
      expect((injected.metadata as ComponentInstanceMetadata).componentKey).toBe('webu_header_01');
    });

    it('does not mutate original node', () => {
      const n = node('hero-1', 'webu_general_hero_01');
      injectComponentMetadata(n);
      expect(n.metadata).toBeUndefined();
    });
  });

  describe('injectTreeMetadata', () => {
    it('injects metadata into every node in tree', () => {
      const tree: BuilderComponentInstance[] = [
        node('header-1', 'webu_header_01'),
        node('hero-1', 'webu_general_hero_01'),
      ];
      const result = injectTreeMetadata(tree);
      expect(result).toHaveLength(2);
      expect(result[0]!.metadata).toBeDefined();
      expect(result[1]!.metadata).toBeDefined();
      expect((result[0]!.metadata as ComponentInstanceMetadata).capabilities).toBeDefined();
      expect((result[1]!.metadata as ComponentInstanceMetadata).capabilities).toBeDefined();
    });

    it('injects into nested children', () => {
      const tree: BuilderComponentInstance[] = [
        {
          id: 'parent',
          componentKey: 'webu_header_01',
          props: {},
          children: [node('child', 'webu_general_hero_01')],
        },
      ];
      const result = injectTreeMetadata(tree);
      expect(result[0]!.metadata).toBeDefined();
      expect(result[0]!.children).toHaveLength(1);
      expect(result[0]!.children![0]!.metadata).toBeDefined();
    });
  });

  describe('getInstanceMetadata', () => {
    it('returns metadata from node when present', () => {
      const n = node('header-1', 'webu_header_01', {}, undefined, {
        componentKey: 'webu_header_01',
        variant: 'header-1',
        capabilities: ['navigation', 'search'],
        projectTypes: ['business'],
      });
      const meta = getInstanceMetadata(n);
      expect(meta.capabilities).toEqual(['navigation', 'search']);
      expect(meta.projectTypes).toEqual(['business']);
    });

    it('falls back to registry when node has no metadata', () => {
      const n = node('header-1', 'webu_header_01');
      const meta = getInstanceMetadata(n);
      expect(meta.componentKey).toBe('webu_header_01');
      expect(Array.isArray(meta.capabilities)).toBe(true);
      expect(Array.isArray(meta.projectTypes)).toBe(true);
    });
  });
});
