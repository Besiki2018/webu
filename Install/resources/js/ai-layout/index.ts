/**
 * AI Layout Generator — public API.
 * Layout JSON only; no raw HTML. Components from design system; content from CMS bindings.
 */

export { LayoutRenderer } from './LayoutRenderer';
export type { LayoutRendererProps } from './LayoutRenderer';
export { resolveBindings, resolveBindingPath } from './resolveBindings';
export { getComponent, registry } from './componentRegistry';
export type { RegistryEntry } from './componentRegistry';
export type { AILayoutSchema, LayoutSection, StructuredInput, ThemeTokens, CMSData } from './types';
