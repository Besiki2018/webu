/**
 * Project Type System — defines which type of project the builder is editing.
 * Used for component context classification and AI refactor engine (e.g. which
 * components fit which project type, refactor suggestions).
 *
 * Each project created in the builder must include a projectType property.
 */

/** Supported project types in the Webu builder. */
export const projectTypes = [
  'business',
  'ecommerce',
  'saas',
  'portfolio',
  'restaurant',
  'hotel',
  'blog',
  'landing',
  'education',
] as const;

/** Union type of all supported project types. */
export type ProjectType = (typeof projectTypes)[number];

/** Normalized site type used by builder governance rules. */
export const projectSiteTypes = [
  'ecommerce',
  'booking',
  'landing',
  'website',
] as const;

export type ProjectSiteType = (typeof projectSiteTypes)[number];

/** Default project type when creating a new project (e.g. landing). */
export const defaultProjectType: ProjectType = 'landing';

/** Default normalized site type when builder metadata is missing. */
export const defaultProjectSiteType: ProjectSiteType = 'website';

const PROJECT_TYPE_TO_SITE_TYPE: Record<ProjectType, ProjectSiteType> = {
  business: 'website',
  ecommerce: 'ecommerce',
  saas: 'website',
  portfolio: 'website',
  restaurant: 'booking',
  hotel: 'booking',
  blog: 'website',
  landing: 'landing',
  education: 'website',
};

/** Type guard: returns true if value is a valid ProjectType. */
export function isProjectType(value: unknown): value is ProjectType {
  return typeof value === 'string' && (projectTypes as readonly string[]).includes(value);
}

/** Type guard: returns true if value is a valid normalized ProjectSiteType. */
export function isProjectSiteType(value: unknown): value is ProjectSiteType {
  return typeof value === 'string' && (projectSiteTypes as readonly string[]).includes(value);
}

/**
 * Resolves any legacy/broad builder project type into the normalized site type
 * used by AI and component governance.
 */
export function normalizeProjectSiteType(...candidates: unknown[]): ProjectSiteType {
  for (const candidate of candidates) {
    if (typeof candidate !== 'string') {
      continue;
    }

    const normalized = candidate.trim().toLowerCase();
    if (normalized === '') {
      continue;
    }

    if (isProjectSiteType(normalized)) {
      return normalized;
    }

    if (isProjectType(normalized)) {
      return PROJECT_TYPE_TO_SITE_TYPE[normalized];
    }

    if (normalized === 'marketing') {
      return 'landing';
    }

    if (normalized === 'general' || normalized === 'portfolio' || normalized === 'saas' || normalized === 'blog') {
      return 'website';
    }
  }

  return defaultProjectSiteType;
}

/**
 * Project metadata shape. Every project in the builder must include projectType
 * and a normalized project.type for governance-aware component filtering.
 * Example: { projectType: "ecommerce", type: "ecommerce" }
 */
export interface BuilderProject {
  projectType: ProjectType;
  type: ProjectSiteType;
}
