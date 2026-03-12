/**
 * Phase 9 — Full Code Export.
 *
 * User clicks "Export Website" → system can generate:
 * - React site
 * - HTML site
 * - Next.js site
 * - Static export
 *
 * Export includes: components, assets, styles, routes, content.
 */

import type { BuilderComponentInstance } from './core/types';
import type { ProjectType } from './projectTypes';
import { toSerializableNode } from './core/pageModel';

/** Export format: target stack for the generated site. */
export type ExportFormat = 'react' | 'html' | 'nextjs' | 'static';

export const EXPORT_FORMATS: ExportFormat[] = ['react', 'html', 'nextjs', 'static'];

export const EXPORT_FORMAT_LABELS: Record<ExportFormat, string> = {
  react: 'React site',
  html: 'HTML site',
  nextjs: 'Next.js site',
  static: 'Static export',
};

/** Payload sent to backend or used for download — everything needed to generate the site. */
export interface WebsiteExportPayload {
  /** Export format requested. */
  format: ExportFormat;
  /** Project type (e.g. saas, landing). */
  projectType: ProjectType;
  /** Page content: serializable component tree. */
  content: BuilderComponentInstance[];
  /** Unique list of component keys used in the tree (for bundling only used components). */
  components: string[];
  /** Asset URLs referenced in content (images, etc.) — for download or copy. */
  assets: string[];
  /** Global style hints (e.g. theme, breakpoints) — for styles generation. */
  styles: ExportStyleHint[];
  /** Routes: for multi-page, array of { path, content }; for single page, one route. */
  routes: ExportRoute[];
  /** Timestamp. */
  exportedAt: string;
}

export interface ExportStyleHint {
  key: string;
  value?: unknown;
}

export interface ExportRoute {
  path: string;
  /** Serializable component tree for this route. */
  content: BuilderComponentInstance[];
}

function collectComponentKeys(tree: BuilderComponentInstance[]): Set<string> {
  const keys = new Set<string>();
  function walk(nodes: BuilderComponentInstance[]) {
    for (const node of nodes) {
      keys.add(node.componentKey);
      if (Array.isArray(node.children) && node.children.length > 0) walk(node.children);
    }
  }
  walk(tree);
  return keys;
}

function collectAssetUrls(tree: BuilderComponentInstance[]): string[] {
  const urls: string[] = [];
  function walk(nodes: BuilderComponentInstance[]) {
    for (const node of nodes) {
      const props = node.props ?? {};
      for (const [k, v] of Object.entries(props)) {
        if (typeof v === 'string' && (k.toLowerCase().includes('image') || k.toLowerCase().includes('url') || k === 'src' || k === 'logo_url' || k === 'backgroundImage')) {
          if (v.startsWith('http') || v.startsWith('/') || v.startsWith('data:')) urls.push(v);
        }
      }
      if (Array.isArray(node.children) && node.children.length > 0) walk(node.children);
    }
  }
  walk(tree);
  return [...new Set(urls)];
}

/**
 * Builds the full export payload from the current component tree and project type.
 * Use for "Export Website" — send to backend for codegen or trigger download.
 */
export function buildWebsiteExportPayload(
  input: {
    componentTree: BuilderComponentInstance[];
    projectType: ProjectType;
    format: ExportFormat;
  }
): WebsiteExportPayload {
  const { componentTree, projectType, format } = input;
  const serialized = componentTree.map(toSerializableNode);
  const components = Array.from(collectComponentKeys(componentTree));
  const assets = collectAssetUrls(componentTree);
  const styles: ExportStyleHint[] = [
    { key: 'projectType', value: projectType },
    { key: 'breakpoints', value: ['desktop', 'tablet', 'mobile'] },
  ];
  const routes: ExportRoute[] = [
    { path: '/', content: serialized },
  ];

  return {
    format,
    projectType,
    content: serialized,
    components,
    assets,
    styles,
    routes,
    exportedAt: new Date().toISOString(),
  };
}

/**
 * Serialize the export payload to a JSON string (e.g. for download or API).
 */
export function serializeWebsiteExportPayload(payload: WebsiteExportPayload): string {
  return JSON.stringify(payload, null, 2);
}

/**
 * One-shot: get current tree from store, build payload, return JSON string.
 * Requires store to be available (e.g. from a component or command).
 */
export function getWebsiteExportJson(
  getState: () => { componentTree: BuilderComponentInstance[]; projectType: ProjectType },
  format: ExportFormat = 'react'
): string {
  const state = getState();
  const payload = buildWebsiteExportPayload({
    componentTree: state.componentTree,
    projectType: state.projectType,
    format,
  });
  return serializeWebsiteExportPayload(payload);
}
