/**
 * Ultimate AI website generation — CMS-first, single flow.
 * Calls backend orchestrator; returns project/website so UI can open editor.
 */

export type Language = 'ka' | 'en' | 'both';
export type Style = 'modern' | 'minimal' | 'luxury' | 'playful' | 'corporate';
export type WebsiteType = 'business' | 'ecommerce' | 'portfolio' | 'booking';

export interface GenerateWebsiteProjectInput {
  userPrompt: string;
  language?: Language;
  style?: Style;
  websiteType?: WebsiteType;
  brandName?: string;
  currency?: string;
}

export interface WebsiteRecord {
  id: string;
  name: string;
  domain: string | null;
  theme: Record<string, unknown> | null;
  site_id: string | null;
}

export interface PageRecord {
  id: number;
  slug: string;
  title: string;
  order: number;
}

export interface SectionRecord {
  id: number;
  section_type: string;
  order: number;
  settings_json: Record<string, unknown>;
}

export interface ThemeRecord {
  palette?: Record<string, string>;
  typography?: Record<string, string>;
  radius?: Record<string, string>;
  spacing?: Record<string, string>;
}

export interface SeoRecord {
  seo_title: string;
  meta_description: string;
  og_title?: string | null;
  og_image?: string | null;
}

export interface GenerateWebsiteProjectResult {
  website: WebsiteRecord;
  project: { id: string };
  site: { id: string };
  pages: PageRecord[];
  theme: ThemeRecord;
  seo: SeoRecord[];
}

/**
 * Generate a full website (CMS-first). Uses POST /projects/generate-website which
 * redirects to project.cms. For SPA/form submit use router.post(route('projects.generate-website'), form).
 * This helper returns the shape the backend would return if it were JSON (for type/documentation).
 */
export function generateWebsiteProject(
  _input: GenerateWebsiteProjectInput
): Promise<GenerateWebsiteProjectResult> {
  // Backend is form POST + redirect. Use from a form or Inertia:
  // router.post(route('projects.generate-website'), { prompt, language, style, websiteType });
  return Promise.reject(
    new Error(
      'Use router.post(route("projects.generate-website"), { prompt, language, style, websiteType }) — backend returns redirect to project.cms.'
    )
  );
}
