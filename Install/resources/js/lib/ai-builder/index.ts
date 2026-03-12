/**
 * Webu AI Website Builder – code generation and code ↔ builder sync.
 * See docs/WEBU_AI_WEBSITE_BUILDER_ARCHITECTURE.md for full architecture.
 */

export {
  buildPageComponentCode,
  buildFullComponentSource,
  buildDesignTokensFileContent,
  sectionsDraftToCode,
} from './codeGenerator';
export { parseLayoutCode } from './parseLayoutCode';
export { getSectionTagName, getSectionKeyFromTagName, SECTION_TAG_MAP } from './sectionTagMap';
export type { SectionDraftLike, GeneratedSection, CodeGeneratorOptions, GeneratedPageCodeOptions, ParseLayoutCodeResult } from './types';
