/**
 * Part 6 — Design Consistency Analyzer.
 *
 * Detects: inconsistent spacing, mixed button styles, color misuse, font mismatch.
 * Example: "CTA color inconsistent with brand" → AI suggests "use primary color".
 */

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface DesignSectionInput {
  localId: string;
  type: string;
  props?: Record<string, unknown>;
}

export interface DesignConsistencyInput {
  sections: DesignSectionInput[];
  /** Optional brand/theme hints (e.g. primary color) for consistency checks. */
  theme?: {
    primaryColor?: string;
    fontFamily?: string;
  };
  sectionKinds?: string[];
}

export interface DesignConsistencyIssue {
  /** Human-readable issue (e.g. "CTA color inconsistent with brand"). */
  issue: string;
  /** Category: spacing, buttons, color, typography. */
  category: 'spacing' | 'buttons' | 'color' | 'typography';
  /** Suggested fix for the AI to propose (e.g. "use primary color"). */
  suggestedFix: string;
  sectionId?: string;
  sectionKind?: string;
}

export interface DesignConsistencyReport {
  issues: DesignConsistencyIssue[];
  summary?: string;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function getSectionKind(type: string): string {
  const t = (type || '').trim().toLowerCase();
  if (t.includes('header') || t.includes('nav')) return 'header';
  if (t.includes('footer')) return 'footer';
  if (t.includes('hero') || t.includes('banner')) return 'hero';
  if (t.includes('feature')) return 'features';
  if (t.includes('cta') || t.includes('call_to_action')) return 'cta';
  if (t.includes('pricing')) return 'pricing';
  if (t.includes('testimonial') || t.includes('review')) return 'testimonials';
  if (t.includes('card')) return 'cards';
  if (t.includes('grid')) return 'grid';
  if (t.includes('newsletter')) return 'newsletter';
  return t || 'unknown';
}

function getPropStr(props: Record<string, unknown> | undefined, ...keys: string[]): string {
  if (!props) return '';
  for (const k of keys) {
    const v = props[k];
    if (v != null && typeof v === 'string' && v.trim()) return v.trim();
  }
  return '';
}

// ---------------------------------------------------------------------------
// Inconsistent spacing
// ---------------------------------------------------------------------------

function analyzeSpacingConsistency(sections: DesignSectionInput[], kinds: string[]): DesignConsistencyIssue[] {
  const issues: DesignConsistencyIssue[] = [];
  const spacingValues = new Set<string>();
  const paddingValues = new Set<string>();

  for (let i = 0; i < sections.length; i++) {
    const p = sections[i]?.props;
    const pad = getPropStr(p, 'padding', 'spacing');
    if (pad) paddingValues.add(pad.toLowerCase());
    const space = getPropStr(p, 'spacing', 'gap');
    if (space) spacingValues.add(space.toLowerCase());
  }

  if (paddingValues.size > 3 && sections.length >= 3) {
    issues.push({
      issue: 'inconsistent spacing across sections',
      category: 'spacing',
      suggestedFix: 'Use a consistent padding scale (e.g. md or lg) across sections',
    });
  }

  if (spacingValues.size > 3 && sections.length >= 3) {
    issues.push({
      issue: 'mixed spacing values',
      category: 'spacing',
      suggestedFix: 'Standardize spacing (e.g. space-y-8 or padding-md) for visual rhythm',
    });
  }

  return issues;
}

// ---------------------------------------------------------------------------
// Mixed button styles
// ---------------------------------------------------------------------------

function analyzeButtonConsistency(sections: DesignSectionInput[], kinds: string[]): DesignConsistencyIssue[] {
  const issues: DesignConsistencyIssue[] = [];
  const buttonVariants = new Set<string>();

  for (let i = 0; i < sections.length; i++) {
    const p = sections[i]?.props;
    const v = getPropStr(p, 'buttonVariant', 'buttonStyle', 'variant');
    if (v) buttonVariants.add(v.toLowerCase());
  }

  if (buttonVariants.size > 2 && sections.length >= 2) {
    issues.push({
      issue: 'mixed button styles across sections',
      category: 'buttons',
      suggestedFix: 'Use one primary button style (e.g. primary or solid) for main CTAs',
    });
  }

  return issues;
}

// ---------------------------------------------------------------------------
// Color misuse
// ---------------------------------------------------------------------------

function analyzeColorConsistency(
  sections: DesignSectionInput[],
  kinds: string[],
  theme?: DesignConsistencyInput['theme']
): DesignConsistencyIssue[] {
  const issues: DesignConsistencyIssue[] = [];
  const backgroundColors = new Set<string>();
  const textColors = new Set<string>();

  for (let i = 0; i < sections.length; i++) {
    const s = sections[i];
    const p = s?.props;
    const kind = kinds[i] ?? getSectionKind(s?.type ?? '');
    const bg = getPropStr(p, 'backgroundColor', 'background');
    const text = getPropStr(p, 'textColor', 'color');
    if (bg) backgroundColors.add(bg.toLowerCase());
    if (text) textColors.add(text.toLowerCase());

    if (kind === 'cta') {
      const ctaBg = getPropStr(p, 'backgroundColor', 'background');
      const primary = theme?.primaryColor?.trim().toLowerCase();
      const hasBg = !!ctaBg || !!p?.backgroundImage;
      if (primary && ctaBg) {
        const ctaNorm = ctaBg.trim().toLowerCase();
        if (ctaNorm !== primary) {
          issues.push({
            issue: 'CTA color inconsistent with brand',
            category: 'color',
            suggestedFix: 'use primary color',
            sectionId: s?.localId,
            sectionKind: 'cta',
          });
        }
      }
      if (!hasBg) {
        issues.push({
          issue: 'CTA background not set; may lack contrast',
          category: 'color',
          suggestedFix: 'use primary color or a strong background for CTA visibility',
          sectionId: s?.localId,
          sectionKind: 'cta',
        });
      }
    }
  }

  if (backgroundColors.size > 5 && sections.length >= 4) {
    issues.push({
      issue: 'too many different background colors',
      category: 'color',
      suggestedFix: 'Limit to 2–3 background colors (e.g. white, primary, neutral) for consistency',
    });
  }

  if (textColors.size > 4 && sections.length >= 4) {
    issues.push({
      issue: 'inconsistent text colors',
      category: 'color',
      suggestedFix: 'Use a consistent text color (e.g. body and heading tokens) across sections',
    });
  }

  return issues;
}

// ---------------------------------------------------------------------------
// Font mismatch
// ---------------------------------------------------------------------------

function analyzeTypographyConsistency(
  sections: DesignSectionInput[],
  kinds: string[],
  theme?: DesignConsistencyInput['theme']
): DesignConsistencyIssue[] {
  const issues: DesignConsistencyIssue[] = [];
  const fontFamilies = new Set<string>();
  const fontSizes = new Set<string>();

  for (let i = 0; i < sections.length; i++) {
    const p = sections[i]?.props;
    const font = getPropStr(p, 'fontFamily', 'font', 'typography');
    const size = getPropStr(p, 'fontSize', 'textSize');
    if (font) fontFamilies.add(font.toLowerCase());
    if (size) fontSizes.add(size.toLowerCase());
  }

  if (fontFamilies.size > 2 && sections.length >= 2) {
    issues.push({
      issue: 'font mismatch across sections',
      category: 'typography',
      suggestedFix: theme?.fontFamily
        ? `Use theme font (${theme.fontFamily}) for consistency`
        : 'Use a single font family across the page',
    });
  }

  if (fontSizes.size > 4 && sections.length >= 3) {
    issues.push({
      issue: 'inconsistent font sizes',
      category: 'typography',
      suggestedFix: 'Use a consistent type scale (e.g. heading, body, small) across sections',
    });
  }

  return issues;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Analyzes design consistency: inconsistent spacing, mixed button styles,
 * color misuse, font mismatch. Returns issues with suggested fixes (e.g.
 * "CTA color inconsistent with brand" → "use primary color").
 *
 * @param input — sections, optional theme (primaryColor, fontFamily), optional sectionKinds
 * @returns DesignConsistencyReport with issues and suggestedFix per issue
 */
export function analyzeDesignConsistency(input: DesignConsistencyInput): DesignConsistencyReport {
  const sections = input.sections ?? [];
  const kinds = input.sectionKinds ?? sections.map((s) => getSectionKind(s.type));
  const theme = input.theme;

  const issues: DesignConsistencyIssue[] = [];
  issues.push(...analyzeSpacingConsistency(sections, kinds));
  issues.push(...analyzeButtonConsistency(sections, kinds));
  issues.push(...analyzeColorConsistency(sections, kinds, theme));
  issues.push(...analyzeTypographyConsistency(sections, kinds, theme));

  const summary =
    issues.length === 0
      ? 'Design consistency looks good'
      : `${issues.length} design consistency issue${issues.length === 1 ? '' : 's'} (AI suggests fixes)`;

  return { issues, summary };
}
