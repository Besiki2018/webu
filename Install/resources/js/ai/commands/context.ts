/**
 * Page context for the command interpreter.
 * Enables context-aware section selection (e.g. "this section", "hero", "testimonials").
 */
export interface SectionInfo {
  id: string;
  type: string;
  label?: string;
  /** Optional preview of props for disambiguation (e.g. headline) */
  preview?: Record<string, unknown>;
}

/** Selected editable element for precise AI targeting (e.g. "Change this title"). */
export interface SelectedElementContext {
  /** Section instance id (for updateText sectionId) */
  sectionId: string;
  /** Parameter path (e.g. headline, title, buttonText) */
  parameterPath: string;
  /** Display id for chat: ComponentName.parameterName */
  elementId: string;
  /** Optional component label (e.g. Hero Section) */
  componentLabel?: string;
  /** Part 9 — Schema-driven: allowed prop paths for this section (e.g. title, plans, plans.0.price). Use these for updateText/updateSection. */
  allowedPaths?: Array<{ path: string; type: string }>;
}

export interface PageContext {
  /** Current page slug or id */
  pageSlug?: string;
  pageId?: string | null;
  /** Sections in order; id should match sectionId used in operations */
  sections: SectionInfo[];
  /** Available section types for insertSection (e.g. hero, pricing, faq) */
  componentTypes?: string[];
  /** Current theme summary for updateTheme (e.g. primary, preset) */
  theme?: Record<string, unknown>;
  /** Currently selected section id if user said "this section" */
  selectedSectionId?: string | null;
  /** Currently selected element (component.parameter) for "change this title" style edits */
  selectedElement?: SelectedElementContext | null;
  /** Content locale (e.g. "en", "ka") */
  locale?: string;
}

export function summarizePageContextForPrompt(ctx: PageContext): string {
  const parts: string[] = ['Current page context:'];
  if (ctx.pageSlug) parts.push(`Page: ${ctx.pageSlug}`);
  if (ctx.sections.length > 0) {
    parts.push(
      'Sections (use these sectionId values): ' +
        ctx.sections
          .map((s, i) => `${s.id} (type: ${s.type}${s.label ? `, label: ${s.label}` : ''})`)
          .join('; ')
    );
  }
  if (ctx.componentTypes && ctx.componentTypes.length > 0) {
    parts.push('Available section types for insert: ' + ctx.componentTypes.join(', '));
  }
  if (ctx.theme && Object.keys(ctx.theme).length > 0) {
    parts.push('Theme (for updateTheme): ' + JSON.stringify(ctx.theme));
  }
  if (ctx.selectedSectionId) {
    parts.push(`Selected section: ${ctx.selectedSectionId}`);
  }
  if (ctx.selectedElement) {
    parts.push(
      `Selected element: ${ctx.selectedElement.elementId} (sectionId: ${ctx.selectedElement.sectionId}, path: ${ctx.selectedElement.parameterPath}). Use updateText with this sectionId and path to change this element.`
    );
    if (ctx.selectedElement.allowedPaths && ctx.selectedElement.allowedPaths.length > 0) {
      parts.push(
        'Allowed prop paths for this section (use for updateText path or updateSection patch): ' +
          ctx.selectedElement.allowedPaths.map((p) => `${p.path} (${p.type})`).join(', ')
      );
    }
  }
  if (ctx.locale) {
    parts.push(`Locale: ${ctx.locale}`);
  }
  return parts.join('\n');
}
