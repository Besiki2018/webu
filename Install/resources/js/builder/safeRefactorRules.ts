/**
 * Safe Refactor Rules — constraints for the AI refactor engine.
 *
 * Policy:
 * - Never delete entire components automatically.
 * - Only modify: props, child elements, layout variants.
 * - If a component cannot be safely modified, suggest a replacement instead.
 */

/** Allowed modification types. No other mutations are permitted by the safe-refactor policy. */
export const SAFE_MODIFICATION_TYPES = ['props', 'child_elements', 'layout_variants'] as const;

export type SafeModificationType = (typeof SAFE_MODIFICATION_TYPES)[number];

/** Human-readable policy summary for AI and UI. */
export const SAFE_REFACTOR_POLICY = {
  /** Never delete entire components automatically. */
  neverDeleteComponents: true,
  /** Only these modification types are allowed. */
  allowedModifications: SAFE_MODIFICATION_TYPES as unknown as string[],
  /** When a component cannot be safely modified, suggest a replacement (do not auto-apply). */
  suggestReplacementWhenUnsafe: true,
} as const;

/** Descriptions for each allowed modification type. */
export const SAFE_MODIFICATION_DESCRIPTIONS: Record<SafeModificationType, string> = {
  props: 'Change component props (text, visibility, images, links, etc.).',
  child_elements: 'Add, reorder, or remove child elements within the component.',
  layout_variants: 'Switch layout or design variant (e.g. header-1 → header-2, hero-1 → hero-2).',
};

/**
 * Returns true if the modification is allowed by the safe refactor policy.
 */
export function isAllowedModification(type: string): type is SafeModificationType {
  return (SAFE_MODIFICATION_TYPES as readonly string[]).includes(type);
}

/**
 * Returns true if a refactor suggestion is safe: it only changes props, children, or variant.
 * Suggestions that would delete the entire component are not safe.
 */
export function isSafeRefactorSuggestion(suggestion: {
  propPatch?: Record<string, unknown>;
  deleteComponent?: boolean;
}): boolean {
  if (suggestion.deleteComponent === true) return false;
  if (suggestion.propPatch && Object.keys(suggestion.propPatch).length > 0) return true;
  return false;
}

/**
 * When a component cannot be safely modified, the engine should suggest a replacement
 * instead of applying an unsafe change. This type describes that suggestion.
 */
export interface SuggestReplacement {
  reason: string;
  /** Optional: suggested component key (e.g. webu_header_01) or variant to use instead. */
  suggestedComponentKey?: string;
  suggestedVariant?: string;
}
