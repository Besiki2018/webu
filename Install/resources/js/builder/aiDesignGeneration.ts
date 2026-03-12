/**
 * Phase 10 — AI Design Generation.
 *
 * User can provide: screenshot, Figma link, template, design inspiration.
 * AI converts design into: component layout, component props, builder structure.
 *
 * Output is the same as Phase 8 (site generation): SiteStructureSection[] with
 * componentKey, variant, props per section. Use buildTreeFromStructure + runGenerateSite
 * to apply to the builder.
 *
 * Example: Screenshot → Hero + Features + CTA
 */

import type { SiteStructureSection } from './aiSiteGeneration';

/** Design input type — what the user provides for AI to convert. */
export type DesignInputType = 'screenshot' | 'figma_link' | 'template' | 'design_inspiration';

export const DESIGN_INPUT_TYPES: DesignInputType[] = [
  'screenshot',
  'figma_link',
  'template',
  'design_inspiration',
];

export const DESIGN_INPUT_LABELS: Record<DesignInputType, string> = {
  screenshot: 'Screenshot',
  figma_link: 'Figma link',
  template: 'Template',
  design_inspiration: 'Design inspiration',
};

/** Payload sent to backend when user submits a design for conversion. */
export interface DesignConversionInput {
  /** What kind of design input this is. */
  designInputType: DesignInputType;
  /** Screenshot: base64 or URL. Figma: file/version URL. Template: template id. Inspiration: URL or image. */
  designInput: string;
  /** Optional project type hint for component selection. */
  projectType?: string;
}

/**
 * AI output from design conversion — same shape as site generation structure.
 * Backend returns this; frontend applies via runGenerateSite({ projectType, structure }).
 */
export type DesignConversionOutput = SiteStructureSection[];

/** Result of design conversion (backend response or tool_result). */
export interface DesignConversionResult {
  ok: boolean;
  /** Generated component layout + props = builder structure (apply with runGenerateSite). */
  structure?: DesignConversionOutput;
  projectType?: string;
  error?: string;
}
