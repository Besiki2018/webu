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

/** Default project type when creating a new project (e.g. landing). */
export const defaultProjectType: ProjectType = 'landing';

/** Type guard: returns true if value is a valid ProjectType. */
export function isProjectType(value: unknown): value is ProjectType {
  return typeof value === 'string' && (projectTypes as readonly string[]).includes(value);
}

/**
 * Project metadata shape. Every project in the builder must include projectType.
 * Example: { projectType: "ecommerce" }
 */
export interface BuilderProject {
  projectType: ProjectType;
}
