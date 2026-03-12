<?php

namespace App\Services\AiTools;

/**
 * Webu Design Intelligence System (backend).
 * Returns design rules as a prompt fragment so the AI follows container, spacing,
 * typography, and grid rules when generating or modifying layouts.
 * Used by Site Planner and project-edit flows.
 */
class DesignRulesService
{
    /**
     * Design rules prompt fragment. Must be included in Site Planner and code-generation prompts.
     */
    private const DESIGN_RULES_PROMPT = <<<'PROMPT'

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
PROMPT;

    /**
     * Returns the design rules as a string for inclusion in AI prompts.
     * Inject this into the Site Planner and any prompt that generates or modifies layout/code.
     */
    public function getPromptFragment(): string
    {
        return self::DESIGN_RULES_PROMPT;
    }
}
