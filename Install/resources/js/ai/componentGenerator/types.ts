/**
 * Types for the AI Component Generator.
 */

export interface EnsureSectionExistsOptions {
  /** Base URL for API (e.g. '') */
  apiBase?: string;
  /** User prompt for context when generating content */
  userPrompt?: string;
  /** Callback to trigger preview reload after creating/updating files */
  onReloadPreview?: () => void;
}

/** Result when section already existed (reused). */
export interface EnsureSectionExistsReused {
  created: false;
  path: string;
  reused: true;
}

/** Result when section was created. */
export interface EnsureSectionExistsCreated {
  created: true;
  path: string;
  reused: false;
}

/** Result when an error occurred. */
export interface EnsureSectionExistsError {
  created: false;
  error: string;
  path?: string;
  reused: false;
}

export type EnsureSectionExistsResult =
  | EnsureSectionExistsReused
  | EnsureSectionExistsCreated
  | EnsureSectionExistsError;
