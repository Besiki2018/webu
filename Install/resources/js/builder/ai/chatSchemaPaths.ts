/**
 * Part 9 — Chat Compatibility: map chat commands to schema paths for generated components.
 *
 * Chat uses schema (editableFields) to update props. This module helps resolve natural language
 * to prop paths so "Change pricing title", "Add fourth pricing plan", "Update price", "Add feature"
 * map to the correct path for updateSection / updateText.
 */

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface ChatPathResolution {
  /** Prop path (e.g. 'title', 'plans', 'plans.2.price'). */
  path: string;
  /** Hint for value type (text, list, etc.). */
  type: string;
  /** Optional: for list items, the index to add or update (e.g. "fourth" → 3). */
  listIndex?: number;
  /** Optional: for "add plan" / "add feature", true. */
  appendToList?: boolean;
}

// ---------------------------------------------------------------------------
// Phrase → path mapping (component-agnostic by field name)
// ---------------------------------------------------------------------------

const TITLE_PHRASES = ['title', 'heading', 'headline', 'section title', 'pricing title', 'change title'];
const PLANS_PHRASES = ['plan', 'plans', 'pricing plan', 'add plan', 'fourth plan', 'new plan'];
const PRICE_PHRASES = ['price', 'pricing', 'update price', 'change price'];
const FEATURES_PHRASES = ['feature', 'features', 'add feature', 'add features'];
const CTA_PHRASES = ['cta', 'button', 'call to action', 'cta button'];

/** Ordinal word → 0-based index. */
const ORDINAL_INDEX: Record<string, number> = {
  first: 0, second: 1, third: 2, fourth: 3, fifth: 4, sixth: 5,
  1: 0, 2: 1, 3: 2, 4: 3, 5: 4,
};

function normalizePhrase(s: string): string {
  return s.toLowerCase().trim().replace(/\s+/g, ' ');
}

/**
 * Resolves a chat phrase (e.g. "change pricing title", "add fourth pricing plan") to a schema path and hints.
 * Uses editableFields when provided to validate path; otherwise uses built-in mappings.
 */
export function resolveChatPhraseToPath(
  phrase: string,
  options?: { editableFields?: Array<{ key: string; type: string }>; sectionType?: string }
): ChatPathResolution | null {
  const norm = normalizePhrase(phrase);
  const editableSet = options?.editableFields
    ? new Set(options.editableFields.map((f) => f.key.toLowerCase()))
    : null;

  const check = (path: string, type: string): boolean => {
    if (!editableSet) return true;
    const key = path.split('.')[0] ?? path;
    return editableSet.has(key.toLowerCase());
  };

  for (const p of TITLE_PHRASES) {
    if (norm.includes(p) && check('title', 'text')) {
      return { path: 'title', type: 'text' };
    }
  }
  for (const p of CTA_PHRASES) {
    if (norm.includes(p) && (check('ctaButton', 'text') || check('cta', 'text'))) {
      return { path: 'ctaButton', type: 'text' };
    }
  }
  for (const p of PLANS_PHRASES) {
    if (!norm.includes(p)) continue;
    if (!check('plans', 'list')) continue;
    const append = /add\s+(?:a\s+)?(?:fourth|fifth|third|new|another)?\s*(?:pricing\s+)?plan/i.test(norm) || /add\s+.*\bplan\b/i.test(norm);
    const ordinalMatch = norm.match(/(?:fourth|fifth|third|second|first|1st|2nd|3rd|4th|5th|\b(\d+)\s*th)/i);
    let listIndex: number | undefined;
    if (ordinalMatch) {
      const word = ordinalMatch[1] ?? ordinalMatch[0];
      listIndex = typeof word === 'string' ? ORDINAL_INDEX[word.toLowerCase()] ?? parseInt(word, 10) - 1 : undefined;
    }
    return { path: 'plans', type: 'list', listIndex, appendToList: append };
  }
  for (const p of PRICE_PHRASES) {
    if (norm.includes(p)) {
      if (p === 'pricing' && !/\bprice\b/i.test(norm)) continue;
      if (check('plans', 'list')) return { path: 'plans', type: 'list' };
      if (check('price', 'text')) return { path: 'price', type: 'text' };
    }
  }
  for (const p of FEATURES_PHRASES) {
    if (norm.includes(p) && (check('features', 'list') || check('items', 'list'))) {
      const append = /add\s+feature(s)?/i.test(norm);
      return { path: 'features', type: 'list', appendToList: append };
    }
  }

  return null;
}

/**
 * Returns allowed paths for chat from schema.editableFields (generated component schema).
 * Chat should use these as allowedUpdates so updates are schema-driven.
 */
export function allowedUpdatesFromEditableFields(
  editableFields: Array<{ key: string; type: string }>
): Array<{ path: string; type: string }> {
  return editableFields.map((f) => ({ path: f.key, type: f.type ?? 'text' }));
}
