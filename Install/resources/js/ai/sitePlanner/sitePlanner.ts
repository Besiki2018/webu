/**
 * AI Site Planner for Webu.
 * Generates a structured website plan (pages + sections) from a user prompt.
 * Uses the active AI provider and codebase scanner on the backend.
 * Does not execute file changes; the plan is consumed by the tools/execution pipeline.
 */

import type { GenerateSitePlanOptions, GenerateSitePlanResult, SitePlan } from './types';

/** Fallback plan when API fails (matches backend fallback). */
const FALLBACK_PLAN: SitePlan = {
  siteName: 'Website',
  pages: [
    { name: 'home', title: 'Home', sections: ['HeroSection', 'FeaturesSection', 'CTASection'] },
    { name: 'contact', title: 'Contact', sections: ['HeroSection', 'FormWrapperSection', 'CTASection'] },
  ],
};

function getCsrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

/**
 * Normalize backend plan to typed SitePlan (pages array with name, title, sections).
 */
function normalizePlan(raw: Record<string, unknown>): SitePlan {
  const siteName = typeof raw.siteName === 'string' && raw.siteName.trim() ? raw.siteName.trim() : 'Website';
  const rawPages = Array.isArray(raw.pages) ? raw.pages : [];
  const pages = rawPages
    .filter((p): p is Record<string, unknown> => p != null && typeof p === 'object')
    .map((p) => {
      const name = typeof p.name === 'string' ? p.name.replace(/[^a-z0-9_-]/g, '').toLowerCase() || 'home' : 'home';
      const title = typeof p.title === 'string' && p.title.trim() ? p.title.trim() : name.charAt(0).toUpperCase() + name.slice(1);
      const sections = Array.isArray(p.sections)
        ? p.sections.filter((s): s is string => typeof s === 'string').map((s) => s.trim()).filter(Boolean)
        : [];
      return { name, title, sections: sections.length ? sections : ['HeroSection', 'FeaturesSection', 'CTASection'] };
    })
    .filter((p) => p.name.length > 0);

  if (pages.length === 0) {
    return FALLBACK_PLAN;
  }

  return { siteName, pages };
}

/**
 * Generate a site plan from a user prompt. Backend runs codebase scanner and AI.
 * On API failure returns a fallback plan so callers always get a valid structure.
 */
export async function generateSitePlan(
  projectId: string,
  prompt: string,
  options: GenerateSitePlanOptions = {}
): Promise<GenerateSitePlanResult> {
  const apiBase = (options.apiBase ?? '').replace(/\/$/, '');
  const url = `${apiBase}/panel/projects/${projectId}/ai/site-plan`;

  try {
    const body: { prompt: string; design_pattern_hints?: string[] } = { prompt: prompt.trim() };
    if (options.designPatternHints && options.designPatternHints.length > 0) {
      body.design_pattern_hints = options.designPatternHints;
    }
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    });

    const json = await res.json().catch(() => ({}));

    if (res.ok && json.success === true && json.plan && typeof json.plan === 'object') {
      const plan = normalizePlan(json.plan);
      return {
        success: true,
        plan,
        fromFallback: Boolean(json.from_fallback),
      };
    }

    const error = typeof json.error === 'string' ? json.error : 'Could not generate site plan';
    return {
      success: false,
      error,
      plan: FALLBACK_PLAN,
      fromFallback: true,
    };
  } catch {
    return {
      success: false,
      error: 'Could not generate site plan',
      plan: FALLBACK_PLAN,
      fromFallback: true,
    };
  }
}

export { FALLBACK_PLAN };
