/**
 * Builder: hydrate page from CMS data.
 * Given websiteId + pageId (website_page id), the backend serves page content from page_sections.
 * This module documents the contract: builder renders from CMS content (content_json + style_json)
 * and theme from website_theme / website.theme. No raw JSON shown in UI.
 *
 * The actual payload can be loaded via:
 * - GET /panel/sites/:siteId/pages/:pageId/cms-hydrate — returns { pageId, slug, title, sections, theme }
 *   from Universal CMS (page_sections + website.theme). Use for "Load from CMS" in builder.
 * - Or use latest revision (GET /panel/sites/:siteId/pages/:pageId) which is synced from CMS
 *   when editing in Admin (pushWebsitePageToRevision).
 */

export interface HydratedSection {
  type: string;
  props: Record<string, unknown>;
}

export interface HydratedPagePayload {
  pageId: number;
  slug: string;
  title: string;
  sections: HydratedSection[];
  theme?: Record<string, unknown>;
}

/**
 * Maps section_type to a display label for the UI (no dev names).
 */
export const SECTION_TYPE_LABELS: Record<string, string> = {
  hero: 'Hero',
  features: 'Features',
  cta: 'Call to action',
  heading: 'Heading',
  content: 'Content',
  contact: 'Contact',
  gallery: 'Gallery',
};

/**
 * Given content_json.sections from API, normalize for builder (type + props only).
 */
export function normalizeSectionsFromCMS(
  sections: Array<{ type?: string; props?: Record<string, unknown> }>
): HydratedSection[] {
  return (sections || []).map((s) => ({
    type: s.type || 'content',
    props: s.props || {},
  }));
}
