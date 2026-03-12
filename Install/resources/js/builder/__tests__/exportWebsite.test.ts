/**
 * Phase 9 — Full code export: buildWebsiteExportPayload.
 */

import { describe, it, expect } from 'vitest';
import type { BuilderComponentInstance } from '../core/types';
import { buildWebsiteExportPayload } from '../exportWebsite';

describe('exportWebsite', () => {
  const tree: BuilderComponentInstance[] = [
    { id: 'header-1', componentKey: 'webu_header_01', props: {} },
    { id: 'hero-1', componentKey: 'webu_general_hero_01', props: { title: 'Welcome', image: 'https://example.com/hero.jpg' } },
  ];

  describe('buildWebsiteExportPayload', () => {
    it('includes content, components, assets, styles, routes', () => {
      const payload = buildWebsiteExportPayload({
        componentTree: tree,
        projectType: 'saas',
        format: 'react',
      });
      expect(payload.format).toBe('react');
      expect(payload.projectType).toBe('saas');
      expect(payload.content).toHaveLength(2);
      expect(payload.content[0]!.componentKey).toBe('webu_header_01');
      expect(payload.components).toContain('webu_header_01');
      expect(payload.components).toContain('webu_general_hero_01');
      expect(payload.assets).toContain('https://example.com/hero.jpg');
      expect(payload.styles).toBeDefined();
      expect(payload.routes).toHaveLength(1);
      expect(payload.routes[0]!.path).toBe('/');
      expect(payload.routes[0]!.content).toHaveLength(2);
      expect(payload.exportedAt).toBeDefined();
    });

    it('supports all export formats', () => {
      const formats = ['react', 'html', 'nextjs', 'static'] as const;
      for (const format of formats) {
        const payload = buildWebsiteExportPayload({
          componentTree: tree,
          projectType: 'landing',
          format,
        });
        expect(payload.format).toBe(format);
      }
    });
  });
});
