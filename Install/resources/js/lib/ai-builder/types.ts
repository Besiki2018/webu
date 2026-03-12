/**
 * Types for the Webu AI Website Builder code generation and sync.
 * Aligns with SectionDraft (Cms.tsx) and builder structure items (Chat.tsx).
 */

export interface SectionDraftLike {
  localId: string;
  type: string;
  props?: Record<string, unknown>;
  propsText: string;
}

export interface GeneratedSection {
  type: string;
  props: Record<string, unknown>;
}

export interface CodeGeneratorOptions {
  /** Section key → JSX tag name (e.g. 'webu_general_hero_01' → 'Hero') */
  sectionTagMap?: Record<string, string>;
  /** Prop keys to include as JSX attributes (default: common content keys) */
  propKeysToEmit?: string[];
  /** Omit props with these values (e.g. '', null, default) */
  omitEmptyProps?: boolean;
  /** Format: 'jsx' | 'tsx' (for future use) */
  format?: 'jsx' | 'tsx';
}

export interface GeneratedPageCodeOptions {
  pageName?: string;
  revisionSource?: string | null;
  includeImports?: boolean;
  includePageData?: boolean;
  /** e.g. './components/' so Page imports from './components/Hero' for agent-editable full source. */
  componentImportPrefix?: string | null;
}

export interface ParseLayoutCodeResult {
  ok: boolean;
  sections: GeneratedSection[];
  errors: string[];
}
