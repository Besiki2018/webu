/**
 * Command categories for the AI editing command library.
 * Used for grouping and suggesting commands.
 */
export const COMMAND_CATEGORIES = {
  CONTENT_EDITING: 'Content Editing',
  LAYOUT_EDITING: 'Layout Editing',
  THEME_EDITING: 'Theme Editing',
  SECTION_MANAGEMENT: 'Section Management',
  ECOMMERCE_EDITING: 'E-commerce Editing',
  SEO_EDITING: 'SEO Editing',
  LANGUAGE_TOOLS: 'Language Tools',
} as const;

export type CommandCategoryKey = keyof typeof COMMAND_CATEGORIES;

export const COMMAND_CATEGORY_LIST: { id: CommandCategoryKey; label: string }[] = [
  { id: 'CONTENT_EDITING', label: COMMAND_CATEGORIES.CONTENT_EDITING },
  { id: 'LAYOUT_EDITING', label: COMMAND_CATEGORIES.LAYOUT_EDITING },
  { id: 'THEME_EDITING', label: COMMAND_CATEGORIES.THEME_EDITING },
  { id: 'SECTION_MANAGEMENT', label: COMMAND_CATEGORIES.SECTION_MANAGEMENT },
  { id: 'ECOMMERCE_EDITING', label: COMMAND_CATEGORIES.ECOMMERCE_EDITING },
  { id: 'SEO_EDITING', label: COMMAND_CATEGORIES.SEO_EDITING },
  { id: 'LANGUAGE_TOOLS', label: COMMAND_CATEGORIES.LANGUAGE_TOOLS },
];
