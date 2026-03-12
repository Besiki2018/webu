/**
 * Part 12 — AI Brand Generator (optional).
 *
 * User writes e.g. "Create design system for luxury furniture brand".
 * AI (rule-based) generates: elegant color palette, serif heading font,
 * neutral backgrounds, large spacing.
 *
 * Uses keyword/prompt mapping to produce designSystemGenerator input;
 * for full AI you would call an LLM and parse the result.
 */

import {
  type DesignSystemGeneratorInput,
  type GeneratedDesignSystem,
  generateDesignSystem,
} from './designSystemGenerator';

// ---------------------------------------------------------------------------
// Prompt parsing (keyword → design params)
// ---------------------------------------------------------------------------

const INDUSTRY_KEYWORDS: Record<string, string> = {
  furniture: 'furniture',
  luxury: 'luxury',
  fashion: 'fashion',
  tech: 'technology',
  saas: 'saas',
  ecommerce: 'ecommerce',
  restaurant: 'restaurant',
  health: 'healthcare',
  finance: 'finance',
  legal: 'legal',
  education: 'education',
};

const STYLE_KEYWORDS: Record<string, string> = {
  luxury: 'elegant',
  elegant: 'elegant',
  minimal: 'minimal',
  modern: 'modern',
  bold: 'bold',
  professional: 'professional',
  warm: 'warm',
  friendly: 'warm',
  spacious: 'spacious',
  compact: 'compact',
  serif: 'editorial',
  editorial: 'editorial',
  neutral: 'minimal',
  large: 'spacious',
  airy: 'spacious',
};

const PROJECT_TYPE_KEYWORDS: Record<string, string> = {
  saas: 'saas',
  landing: 'landing',
  ecommerce: 'ecommerce',
  blog: 'blog',
  portfolio: 'portfolio',
  corporate: 'corporate',
};

function extractBrandName(prompt: string): string {
  const m = prompt.match(/(?:for|brand|company)\s+([a-zA-Z0-9\s]+?)(?:\s+(?:brand|design|website)|$)/i);
  if (m) return m[1].trim();
  const m2 = prompt.match(/([A-Z][a-zA-Z0-9]+)/);
  return m2 ? m2[1] : 'Brand';
}

/**
 * Maps a natural language prompt to design system generator input.
 * Example: "Create design system for luxury furniture brand" →
 *   { projectType: 'ecommerce', industry: 'furniture', designStyle: 'elegant', brandName: 'Brand' }
 */
export function promptToDesignSystemInput(prompt: string): DesignSystemGeneratorInput {
  const lower = (prompt || '').toLowerCase();
  const words = lower.split(/\s+/);

  let industry = 'general';
  for (const [key, value] of Object.entries(INDUSTRY_KEYWORDS)) {
    if (lower.includes(key)) {
      industry = value;
      break;
    }
  }

  let designStyle = 'modern';
  for (const [key, value] of Object.entries(STYLE_KEYWORDS)) {
    if (lower.includes(key)) {
      designStyle = value;
      break;
    }
  }

  let projectType = 'landing';
  for (const [key, value] of Object.entries(PROJECT_TYPE_KEYWORDS)) {
    if (lower.includes(key)) {
      projectType = value;
      break;
    }
  }

  const brandName = extractBrandName(prompt);

  return {
    projectType,
    industry,
    designStyle,
    brandName,
  };
}

/**
 * Generates a full design system from a natural language prompt.
 * Example: "Create design system for luxury furniture brand" →
 * elegant palette, serif heading, neutral backgrounds, large spacing.
 */
export function generateDesignSystemFromPrompt(prompt: string): GeneratedDesignSystem {
  const input = promptToDesignSystemInput(prompt);
  return generateDesignSystem(input);
}
