/**
 * 50+ command patterns for AI interpretation.
 * Each pattern is an example user phrase that maps to ChangeSet operations.
 */
import type { CommandCategoryKey } from './categories';

export interface CommandPattern {
  example: string;
  category: CommandCategoryKey;
  description?: string;
}

/** Content Editing */
export const CONTENT_EDITING_PATTERNS: CommandPattern[] = [
  { example: 'Rewrite this section', category: 'CONTENT_EDITING', description: 'Regenerate section copy' },
  { example: 'Rewrite this section shorter', category: 'CONTENT_EDITING' },
  { example: 'Make this text shorter', category: 'CONTENT_EDITING' },
  { example: 'Make this text more professional', category: 'CONTENT_EDITING' },
  { example: 'Improve this headline', category: 'CONTENT_EDITING' },
  { example: 'Make the tone more friendly', category: 'CONTENT_EDITING' },
  { example: 'Add call-to-action', category: 'CONTENT_EDITING' },
  { example: 'Make this paragraph clearer', category: 'CONTENT_EDITING' },
  { example: 'Summarize this section', category: 'CONTENT_EDITING' },
  { example: 'Expand this section', category: 'CONTENT_EDITING' },
  { example: 'Make it more concise', category: 'CONTENT_EDITING' },
  { example: 'Add a subheadline', category: 'CONTENT_EDITING' },
];

/** Layout Editing */
export const LAYOUT_EDITING_PATTERNS: CommandPattern[] = [
  { example: 'Move this section up', category: 'LAYOUT_EDITING' },
  { example: 'Move this section down', category: 'LAYOUT_EDITING' },
  { example: 'Move testimonials below hero', category: 'LAYOUT_EDITING' },
  { example: 'Duplicate this section', category: 'LAYOUT_EDITING' },
  { example: 'Delete this section', category: 'LAYOUT_EDITING' },
  { example: 'Remove this section', category: 'LAYOUT_EDITING' },
  { example: 'Add spacing here', category: 'LAYOUT_EDITING' },
  { example: 'Center this section', category: 'LAYOUT_EDITING' },
  { example: 'Make this section full width', category: 'LAYOUT_EDITING' },
  { example: 'Reduce spacing between sections', category: 'LAYOUT_EDITING' },
];

/** Theme Editing */
export const THEME_EDITING_PATTERNS: CommandPattern[] = [
  { example: 'Change primary color to dark blue', category: 'THEME_EDITING' },
  { example: 'Switch to dark theme', category: 'THEME_EDITING' },
  { example: 'Make buttons rounded', category: 'THEME_EDITING' },
  { example: 'Use modern font', category: 'THEME_EDITING' },
  { example: 'Make design more minimal', category: 'THEME_EDITING' },
  { example: 'Increase spacing between sections', category: 'THEME_EDITING' },
  { example: 'Change accent color to green', category: 'THEME_EDITING' },
  { example: 'Use a serif font for headings', category: 'THEME_EDITING' },
  { example: 'Make buttons larger', category: 'THEME_EDITING' },
];

/** Section Management */
export const SECTION_MANAGEMENT_PATTERNS: CommandPattern[] = [
  { example: 'Add hero section', category: 'SECTION_MANAGEMENT' },
  { example: 'Add pricing section', category: 'SECTION_MANAGEMENT' },
  { example: 'Add pricing table', category: 'SECTION_MANAGEMENT' },
  { example: 'Add pricing section below hero', category: 'SECTION_MANAGEMENT' },
  { example: 'Add FAQ section', category: 'SECTION_MANAGEMENT' },
  { example: 'Add testimonials', category: 'SECTION_MANAGEMENT' },
  { example: 'Add team section', category: 'SECTION_MANAGEMENT' },
  { example: 'Add contact section', category: 'SECTION_MANAGEMENT' },
  { example: 'Add gallery', category: 'SECTION_MANAGEMENT' },
  { example: 'Add features section', category: 'SECTION_MANAGEMENT' },
  { example: 'Add CTA section', category: 'SECTION_MANAGEMENT' },
  { example: 'Add newsletter signup', category: 'SECTION_MANAGEMENT' },
];

/** E-commerce */
export const ECOMMERCE_PATTERNS: CommandPattern[] = [
  { example: 'Add 3 products', category: 'ECOMMERCE_EDITING' },
  { example: 'Add 5 demo products', category: 'ECOMMERCE_EDITING' },
  { example: 'Add product categories', category: 'ECOMMERCE_EDITING' },
  { example: 'Create product description', category: 'ECOMMERCE_EDITING' },
  { example: 'Change product price', category: 'ECOMMERCE_EDITING' },
  { example: 'Remove product', category: 'ECOMMERCE_EDITING' },
  { example: 'Add discount banner', category: 'ECOMMERCE_EDITING' },
  { example: 'Create product collection', category: 'ECOMMERCE_EDITING' },
  { example: 'Add product grid', category: 'ECOMMERCE_EDITING' },
];

/** SEO */
export const SEO_PATTERNS: CommandPattern[] = [
  { example: 'Improve SEO for this page', category: 'SEO_EDITING' },
  { example: 'Generate meta title', category: 'SEO_EDITING' },
  { example: 'Generate meta description', category: 'SEO_EDITING' },
  { example: 'Add keywords', category: 'SEO_EDITING' },
  { example: 'Optimize headings', category: 'SEO_EDITING' },
  { example: 'Write SEO-friendly headline', category: 'SEO_EDITING' },
];

/** Language */
export const LANGUAGE_PATTERNS: CommandPattern[] = [
  { example: 'Translate page to Georgian', category: 'LANGUAGE_TOOLS' },
  { example: 'Translate page to English', category: 'LANGUAGE_TOOLS' },
  { example: 'Make text bilingual', category: 'LANGUAGE_TOOLS' },
  { example: 'Rewrite text in Georgian', category: 'LANGUAGE_TOOLS' },
  { example: 'Translate this section to Spanish', category: 'LANGUAGE_TOOLS' },
];

export const ALL_COMMAND_PATTERNS: CommandPattern[] = [
  ...CONTENT_EDITING_PATTERNS,
  ...LAYOUT_EDITING_PATTERNS,
  ...THEME_EDITING_PATTERNS,
  ...SECTION_MANAGEMENT_PATTERNS,
  ...ECOMMERCE_PATTERNS,
  ...SEO_PATTERNS,
  ...LANGUAGE_PATTERNS,
];

/** Quick suggestions for UI (short labels). */
export const QUICK_COMMAND_SUGGESTIONS: { label: string; example: string }[] = [
  { label: 'Rewrite text', example: 'Rewrite this section shorter' },
  { label: 'Add section', example: 'Add pricing section below hero' },
  { label: 'Change color', example: 'Change primary color to dark blue' },
  { label: 'Translate page', example: 'Translate page to Georgian' },
  { label: 'Improve SEO', example: 'Improve SEO for this page' },
  { label: 'Remove section', example: 'Remove testimonials' },
  { label: 'Move section', example: 'Move this section up' },
  { label: 'Dark theme', example: 'Switch to dark theme' },
];
