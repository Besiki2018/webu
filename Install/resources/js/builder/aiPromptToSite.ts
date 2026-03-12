/**
 * Phase 15 — AI Prompt to Site Engine.
 *
 * Ultimate feature: user writes one prompt (e.g. "Create a modern ecommerce website for a furniture store")
 * → AI generates full site (projectType + structure + content) → apply via runGenerateSite().
 *
 * This module defines the contract and hints for the backend: parse prompt → infer project type + context
 * → fill structure with content → return generate_site tool payload.
 */

import type { ProjectType } from './projectTypes';
import type { SiteStructureSection } from './aiSiteGeneration';
import {
  DEFAULT_LANDING_STRUCTURE,
  DEFAULT_SAAS_LANDING_STRUCTURE,
  DEFAULT_ECOMMERCE_STRUCTURE,
} from './aiSiteGeneration';

// ---------------------------------------------------------------------------
// Contract: prompt in → generate_site payload out
// ---------------------------------------------------------------------------

/** Input: raw user prompt (e.g. "Create a modern ecommerce website for a furniture store"). */
export interface PromptToSiteInput {
  userPrompt: string;
}

/**
 * Output: same shape as generate_site tool result.
 * Backend AI should return this so the frontend can call runGenerateSite(payload).
 */
export interface PromptToSiteOutput {
  projectType: ProjectType;
  /** Ordered sections with optional props (e.g. hero title, subtitle, feature copy). AI fills from prompt context. */
  structure: SiteStructureSection[];
}

/**
 * Hints for the AI/backend: how to map natural language to project type and structure.
 * Example: "ecommerce" + "furniture store" → projectType: ecommerce, structure with props like
 * hero title "Modern furniture for your home", features about delivery/quality/style.
 */
export const PROMPT_TO_SITE_HINTS: Record<
  string,
  { projectType: ProjectType; description: string }
> = {
  ecommerce: {
    projectType: 'ecommerce',
    description: 'Online store: header with cart/nav, hero, features/benefits, CTA, footer. Fill props with industry (e.g. furniture, fashion).',
  },
  saas: {
    projectType: 'saas',
    description: 'SaaS landing: header, hero, features, pricing, CTA, footer. Fill props with product name and value props.',
  },
  landing: {
    projectType: 'landing',
    description: 'Generic landing: header, hero, features, CTA, footer. Fill props from prompt (audience, offer).',
  },
  business: {
    projectType: 'business',
    description: 'Business site: header, hero, features, CTA, footer. Fill with company/industry.',
  },
  portfolio: {
    projectType: 'portfolio',
    description: 'Portfolio: header, hero, work/projects, CTA, footer.',
  },
  restaurant: {
    projectType: 'restaurant',
    description: 'Restaurant: header, hero, menu highlights, CTA/reservations, footer.',
  },
};

/** Default structure by project type (used when AI omits structure or as base to fill). */
export function getDefaultStructureForPrompt(projectType: ProjectType): SiteStructureSection[] {
  switch (projectType) {
    case 'saas':
      return [...DEFAULT_SAAS_LANDING_STRUCTURE];
    case 'ecommerce':
      return [...DEFAULT_ECOMMERCE_STRUCTURE];
    default:
      return [...DEFAULT_LANDING_STRUCTURE];
  }
}
