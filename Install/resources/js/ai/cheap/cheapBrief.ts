/**
 * Ultra Cheap Mode: brief extraction contract.
 * Backend does rule-based (or tiny-model) extraction and returns this shape.
 * No main model; output is minimal JSON only.
 */

export type WebsiteType = 'business' | 'ecommerce' | 'portfolio' | 'booking';
export type Style = 'modern' | 'minimal' | 'luxury' | 'playful' | 'corporate';
export type Language = 'ka' | 'en' | 'both';

export interface CheapBrief {
  websiteType: WebsiteType;
  category: string;
  style: Style;
  language: Language;
  brandName?: string;
}

export interface CheapBriefResponse {
  brief: CheapBrief;
  confidence?: number;
  oneQuestion?: string;
}

/**
 * Call backend to extract brief (Ultra Cheap: rule-based, no main model).
 * If confidence < 0.75 backend may return oneQuestion; frontend shows max 1 question then proceeds with defaults.
 * Route: POST /api/cheap-brief (auth required).
 */
export async function fetchCheapBrief(prompt: string): Promise<CheapBriefResponse> {
  const csrf = getCsrfToken();
  const response = await fetch('/api/cheap-brief', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
    },
    body: JSON.stringify({ prompt }),
    credentials: 'same-origin',
  });
  if (!response.ok) {
    throw new Error('Brief extraction failed');
  }
  return response.json();
}

function getCsrfToken(): string {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return (meta?.getAttribute('content') ?? '') as string;
}
