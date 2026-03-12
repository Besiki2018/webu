/**
 * Builder compatibility contract.
 *
 * Every builder component must:
 * - Receive all data via props from builder state (no content from context or external store).
 * - Render purely from props (no hidden internal state for content).
 * - Be renderable as: <Component {...componentProps} />
 *
 * Phase 6 — Props from builder state: componentProps = saved component props + default props.
 * - CanvasRenderer: merged = mergeDefaults(entry.defaults, node.props); componentProps = mapBuilderProps(merged).
 * - BuilderCanvas (central): merged = mergeDefaults(centralEntry.defaults, savedProps); componentProps = mapBuilderProps(merged).
 * - Other paths may use resolveComponentProps(section.type, section.props) then mapBuilderProps or ensureFullComponentProps.
 * UI-only state (e.g. menu open/closed) may use local state; content must come from props.
 */

import type { ComponentType } from 'react';

/** Props object that is fully driven by builder state (defaults + user overrides). */
export type BuilderDrivenProps = Record<string, unknown>;

/**
 * A builder-compatible component receives componentProps and renders only from them.
 * No required context, no content from refs or hidden state.
 */
export type BuilderCompatibleComponent<P extends BuilderDrivenProps = BuilderDrivenProps> =
  ComponentType<P>;

/**
 * Merges registry defaults with resolved/mapped props so the component always
 * receives a full props object (no undefined for schema-defined fields).
 */
export function ensureFullComponentProps(
  defaults: Record<string, unknown>,
  resolved: Record<string, unknown>
): Record<string, unknown> {
  return { ...defaults, ...resolved };
}
