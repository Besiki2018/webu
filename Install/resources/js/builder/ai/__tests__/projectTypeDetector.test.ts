import { describe, expect, it } from 'vitest';

import {
  detectProjectType,
  inferAiProjectTypeFromBuilderProjectType,
  mapAiProjectTypeToBuilderProjectType,
  mapAiProjectTypeToSiteType,
} from '../projectTypeDetector';

describe('projectTypeDetector', () => {
  it('detects ecommerce prompts', () => {
    const result = detectProjectType('Create a cosmetics online store');

    expect(result.projectType).toBe('ecommerce');
    expect(result.builderProjectType).toBe('ecommerce');
    expect(result.siteType).toBe('ecommerce');
    expect(result.matchedKeywords).toContain('store');
  });

  it('detects clinic prompts and maps them to booking-safe governance', () => {
    const result = detectProjectType('Create a veterinary clinic website');

    expect(result.projectType).toBe('clinic');
    expect(result.builderProjectType).toBe('business');
    expect(result.siteType).toBe('booking');
  });

  it('maps AI project types back into builder/runtime types', () => {
    expect(mapAiProjectTypeToBuilderProjectType('booking')).toBe('hotel');
    expect(mapAiProjectTypeToSiteType('restaurant')).toBe('booking');
    expect(inferAiProjectTypeFromBuilderProjectType('saas')).toBe('saas');
  });
});
