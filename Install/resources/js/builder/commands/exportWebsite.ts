/**
 * Builder command: Export Website (Phase 9).
 *
 * User clicks "Export Website" → choose format (React, HTML, Next.js, Static)
 * → system generates payload with components, assets, styles, routes, content.
 */

import type { ExportFormat } from '../exportWebsite';
import {
  buildWebsiteExportPayload,
  serializeWebsiteExportPayload,
  type WebsiteExportPayload,
} from '../exportWebsite';
import { useBuilderStore } from '../store/builderStore';

export const EXPORT_WEBSITE_COMMAND = 'export_website';

export interface ExportWebsiteParams {
  format?: ExportFormat;
}

export interface ExportWebsiteResult {
  ok: boolean;
  format: ExportFormat;
  payload?: WebsiteExportPayload;
  json?: string;
  error?: string;
}

/**
 * Builds the full export payload from current store state and returns it (and optional JSON string).
 * UI can then trigger download or send payload to backend for code generation.
 */
export function runExportWebsite(params: ExportWebsiteParams = {}): ExportWebsiteResult {
  const format = params.format ?? 'react';
  const state = useBuilderStore.getState();
  const { componentTree, projectType } = state;

  try {
    const payload = buildWebsiteExportPayload({
      componentTree,
      projectType,
      format,
    });
    const json = serializeWebsiteExportPayload(payload);
    return { ok: true, format, payload, json };
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    return { ok: false, format, error: message };
  }
}
