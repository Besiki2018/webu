/**
 * Component Refactor Actions — canonical set of supported refactor action kinds.
 * Used by the AI refactor engine and UI to classify and describe transformations.
 *
 * Supported actions:
 * - remove element
 * - replace element
 * - add element
 * - modify element props
 * - restructure layout
 */

/** Supported refactor action kinds. */
export const REFACTOR_ACTION_KINDS = [
  'remove_element',
  'replace_element',
  'add_element',
  'modify_element_props',
  'restructure_layout',
] as const;

export type RefactorActionKind = (typeof REFACTOR_ACTION_KINDS)[number];

/** Human-readable label per action kind. */
export const REFACTOR_ACTION_LABELS: Record<RefactorActionKind, string> = {
  remove_element: 'Remove element',
  replace_element: 'Replace element',
  add_element: 'Add element',
  modify_element_props: 'Modify element props',
  restructure_layout: 'Restructure layout',
};

/** Short description per action kind (for tooltips / AI). */
export const REFACTOR_ACTION_DESCRIPTIONS: Record<RefactorActionKind, string> = {
  remove_element: 'Remove a component or sub-element from the page.',
  replace_element: 'Replace a component or layout with another (e.g. different variant or component).',
  add_element: 'Add a new component or widget to the page.',
  modify_element_props: 'Change props of an existing component (text, images, visibility, etc.).',
  restructure_layout: 'Change the structure or order of sections (e.g. move, reorder, nest).',
};

/** Example refactor descriptions (for AI prompts and UI). */
export const REFACTOR_ACTION_EXAMPLES: Record<RefactorActionKind, string[]> = {
  remove_element: [
    'remove search field',
    'remove cart icon',
    'remove newsletter block',
  ],
  replace_element: [
    'replace hero layout',
    'replace generic search with product search',
    'replace header variant',
  ],
  add_element: [
    'add product filters',
    'add booking widget',
    'add cart icon',
    'add product search',
  ],
  modify_element_props: [
    'change hero title',
    'update CTA button text',
    'switch search mode to product',
    'show/hide cart icon',
  ],
  restructure_layout: [
    'move hero below header',
    'reorder sections',
    'move filters above product grid',
  ],
};

/** Type guard for refactor action kind. */
export function isRefactorActionKind(value: unknown): value is RefactorActionKind {
  return typeof value === 'string' && (REFACTOR_ACTION_KINDS as readonly string[]).includes(value);
}
