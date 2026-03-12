/**
 * Webu Design Intelligence System.
 * Global layout, spacing, and typography rules that the AI must follow when generating
 * or modifying websites. Integrates with the AI Site Planner and code generation.
 */

import type { DesignRulesSpec } from './types';

// ---------------------------------------------------------------------------
// Container width rules — all sections use this container structure
// ---------------------------------------------------------------------------

export const CONTAINER_WIDTHS = {
  desktop: '1290px',
  tablet: '1024px',
  mobile: '100%',
} as const;

/** Responsive container class name used in generated markup. */
export const CONTAINER_CLASS = 'container';

/** Correct section + container structure. Header and footer must use the same. */
export const CONTAINER_STRUCTURE = `
<section class="section">
  <div class="${CONTAINER_CLASS}">
    <!-- content -->
  </div>
</section>`.trim();

// ---------------------------------------------------------------------------
// Spacing rules — consistent across all pages
// ---------------------------------------------------------------------------

export const SPACING = {
  /** Standard section padding (default for most sections). */
  section: { top: '80px', bottom: '80px' },
  /** Medium sections. */
  medium: { top: '60px', bottom: '60px' },
  /** Small sections. */
  small: { top: '40px', bottom: '40px' },
} as const;

// ---------------------------------------------------------------------------
// Typography scale — headings and body
// ---------------------------------------------------------------------------

export const TYPOGRAPHY = {
  h1: { desktop: '48px', tablet: '36px', mobile: '28px' },
  h2: { desktop: '36px', tablet: '28px', mobile: '24px' },
  h3: '24px',
  paragraph: '16px',
  lineHeight: '1.5',
} as const;

// ---------------------------------------------------------------------------
// Grid system
// ---------------------------------------------------------------------------

export const GRID = {
  desktopColumns: 12,
  /** Feature sections: 3 columns or 4 columns. */
  featureColumns: [3, 4] as const,
  /** Product grids: 4 columns. */
  productColumns: 4,
  /** Mobile: single column. */
  mobileColumns: 1,
} as const;

// ---------------------------------------------------------------------------
// Breakpoints — responsive behavior
// ---------------------------------------------------------------------------

export const BREAKPOINTS = {
  tablet: '1024px',
  mobile: '768px',
} as const;

/** Below tablet: 2-column layouts collapse to 1-column. */
export const RESPONSIVE_BEHAVIOR = 'Below tablet (1024px) and mobile (768px), multi-column layouts collapse to single column.';

// ---------------------------------------------------------------------------
// Section composition — recommended structures for Site Planner
// ---------------------------------------------------------------------------

export const SECTION_STRUCTURES = {
  /** Landing page structure. */
  landing: ['HeroSection', 'FeaturesSection', 'SocialProofSection', 'CTASection'],
  /** Business website structure. */
  business: ['HeroSection', 'ServicesSection', 'GallerySection', 'TestimonialsSection', 'ContactSection'],
} as const;

// ---------------------------------------------------------------------------
// Full spec (for programmatic use)
// ---------------------------------------------------------------------------

export const DESIGN_RULES_SPEC: DesignRulesSpec = {
  containers: CONTAINER_WIDTHS,
  spacing: SPACING,
  typography: TYPOGRAPHY,
  grid: {
    desktopColumns: GRID.desktopColumns,
    featureColumns: GRID.featureColumns,
    productColumns: GRID.productColumns,
    mobileColumns: GRID.mobileColumns,
  },
  breakpoints: BREAKPOINTS,
  sectionStructures: { ...SECTION_STRUCTURES },
};

// ---------------------------------------------------------------------------
// AI prompt fragment — inject into Site Planner and code-generation prompts
// ---------------------------------------------------------------------------

const DESIGN_RULES_PROMPT = `
## Webu Design System (MANDATORY)

All generated layouts and components MUST follow these rules. Do not create random widths or inline styles that override them.

### Container width rules
- Desktop: max-width 1290px
- Tablet: max-width 1024px
- Mobile: max-width 100%
- Every section must wrap content in a container: <section><div class="container">...</div></section>
- Header and footer must use the same container structure: <header><div class="container">...</div></header>, <footer><div class="container">...</div></footer>
- NEVER use fixed widths on sections (e.g. width: 1600px). Always use the container.

### Spacing rules
- Section padding (default): top 80px, bottom 80px
- Medium sections: top 60px, bottom 60px
- Small sections: top 40px, bottom 40px
- Keep spacing consistent across pages.

### Typography
- H1: 48px desktop, 36px tablet, 28px mobile
- H2: 36px desktop, 28px tablet, 24px mobile
- H3: 24px
- Paragraph: 16px
- Line height: 1.5

### Grid
- Use 12-column grid for desktop layouts.
- Feature sections: 3 or 4 columns.
- Product grids: 4 columns.
- Mobile: single column (collapse multi-column below tablet).

### Responsive breakpoints
- Tablet: 1024px
- Mobile: 768px
- Below tablet, 2-column layouts collapse to 1-column.

### Section composition (plan pages accordingly)
- Landing: HeroSection → FeaturesSection → SocialProofSection → CTASection
- Business: Hero → Services → Gallery → Testimonials → Contact

### Example generated section (follow this pattern)
<section class="section">
  <div class="container">
    <h2>Section title</h2>
    <p>Section description</p>
  </div>
</section>

### Correct vs incorrect
- Correct: <section class="section"><div class="container">...</div></section> or <section class="features"><div class="container">...</div></section>
- Incorrect: <section style="width:1600px"> or any section without inner container. Do not create random layout widths.
`.trim();

/**
 * Returns the design rules as a string for inclusion in AI prompts.
 * Use this in the Site Planner and in any prompt that generates or modifies layout/code.
 */
export function getDesignRulesForPrompt(): string {
  return DESIGN_RULES_PROMPT;
}
