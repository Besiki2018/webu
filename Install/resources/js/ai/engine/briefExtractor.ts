/**
 * Website brief types — aligned with backend WebsiteBriefExtractor.
 * Used when calling generate-website or a future brief API.
 */

export type WebsiteType = 'business' | 'ecommerce' | 'portfolio' | 'booking';
export type Style = 'modern' | 'minimal' | 'luxury' | 'playful' | 'corporate';
export type Language = 'ka' | 'en' | 'both';

export interface WebsiteBrief {
  websiteType: WebsiteType;
  businessType: string | null;
  brandName: string;
  tone: string;
  style: Style;
  language: Language;
  mustHavePages: string[];
  primaryGoal: string | null;
  cta: string | null;
}

/**
 * Optional: call a future brief API to get structured brief from prompt.
 * Backend currently does extraction server-side inside GenerateWebsiteProjectService.
 */
export async function fetchBrief(_userPrompt: string): Promise<WebsiteBrief> {
  throw new Error(
    'Brief extraction runs on the server. Use POST /projects/generate-website with prompt; backend returns redirect.'
  );
}
