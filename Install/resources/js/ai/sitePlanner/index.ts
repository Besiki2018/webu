/**
 * AI Site Planner module for Webu.
 * Generates full website structure (siteName, pages, sections per page) from a user prompt.
 */

export { generateSitePlan, FALLBACK_PLAN } from './sitePlanner';
export type {
  SitePlan,
  PagePlan,
  GenerateSitePlanOptions,
  GenerateSitePlanResult,
  GenerateSitePlanSuccess,
  GenerateSitePlanError,
} from './types';
