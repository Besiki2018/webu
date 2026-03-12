/**
 * AI Component Generator module for Webu.
 * Generates new section components when they do not exist; uses Agent Tools and Design System.
 */

export { ensureSectionExists, normalizeSectionName } from './componentGenerator';
export type {
  EnsureSectionExistsOptions,
  EnsureSectionExistsResult,
  EnsureSectionExistsReused,
  EnsureSectionExistsCreated,
  EnsureSectionExistsError,
} from './types';
