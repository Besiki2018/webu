/**
 * Types for the AI Site Planner. Plan format matches backend and execution pipeline.
 */

export interface PagePlan {
  name: string;
  title: string;
  sections: string[];
}

export interface SitePlan {
  siteName: string;
  pages: PagePlan[];
}

/** Options for generateSitePlan. */
export interface GenerateSitePlanOptions {
  apiBase?: string;
  /** Optional design memory hints (e.g. from getDesignPatternsForType) to improve plan quality */
  designPatternHints?: string[];
}

/** Successful plan response. */
export interface GenerateSitePlanSuccess {
  success: true;
  plan: SitePlan;
  fromFallback: boolean;
}

/** Failed plan response (network/validation). */
export interface GenerateSitePlanError {
  success: false;
  error: string;
  plan: SitePlan;
  fromFallback: true;
}

export type GenerateSitePlanResult = GenerateSitePlanSuccess | GenerateSitePlanError;
