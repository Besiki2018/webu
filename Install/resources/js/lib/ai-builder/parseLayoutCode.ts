/**
 * Parses React-like JSX layout code back into a list of sections (type + props).
 * Used by the Code → Builder Sync Engine when the user edits the generated code.
 */

import type { ParseLayoutCodeResult } from './types';
import { getSectionKeyFromTagName } from './sectionTagMap';

/** Match self-closing JSX tags: <TagName attr="value" /> (attribute values may contain /) */
const TAG_ATTR_REGEX = /<([A-Z][A-Za-z0-9]*)\s*([^>]*?)\s*\/>/g;
const PAGE_DATA_REGEX = /const\s+pageData\s*=\s*([\s\S]*?)\s+as const;/;

function parseAttributes(attrString: string): Record<string, unknown> {
  const props: Record<string, unknown> = {};
  const regex = /([A-Za-z_][A-Za-z0-9_]*)=["']([^"']*)["']/g;
  let m;
  while ((m = regex.exec(attrString)) !== null) {
    props[m[1]] = m[2].replace(/&quot;/g, '"');
  }
  return props;
}

function parsePageDataSections(code: string): Array<{ type: string; props: Record<string, unknown> }> {
  const match = code.match(PAGE_DATA_REGEX);
  if (!match) {
    return [];
  }

  try {
    const parsed = JSON.parse(match[1].trim()) as {
      sections?: Array<{ type?: unknown; props?: unknown }>;
    };
    if (!Array.isArray(parsed.sections)) {
      return [];
    }

    return parsed.sections.flatMap((section) => {
      const type = typeof section?.type === 'string' ? section.type.trim() : '';
      const props = section?.props && typeof section.props === 'object' && !Array.isArray(section.props)
        ? section.props as Record<string, unknown>
        : {};

      if (type === '') {
        return [];
      }

      return [{ type, props }];
    });
  } catch {
    return [];
  }
}

/**
 * Parse a string of JSX layout code (e.g. from the Code tab) into sections
 * that can be applied to the builder (sectionsDraft).
 */
export function parseLayoutCode(code: string): ParseLayoutCodeResult {
  const errors: string[] = [];
  const sections: Array<{ type: string; props: Record<string, unknown> }> = parsePageDataSections(code);
  const normalized = code.trim();

  if (sections.length === 0) {
    const selfClosingRegex = new RegExp(TAG_ATTR_REGEX.source, 'g');
    let match;
    while ((match = selfClosingRegex.exec(normalized)) !== null) {
      const tagName = match[1];
      const attrString = (match[2] ?? '').trim();
      const sectionKey = getSectionKeyFromTagName(tagName) ?? tagName.toLowerCase().replace(/\s+/g, '_');
      const props = parseAttributes(attrString);
      sections.push({ type: sectionKey, props });
    }
  }

  if (sections.length === 0) {
    errors.push('No valid builder sections found. Expected JSX tags like <Hero title="..." /> or an exported pageData object.');
  }

  return {
    ok: errors.length === 0,
    sections,
    errors,
  };
}
