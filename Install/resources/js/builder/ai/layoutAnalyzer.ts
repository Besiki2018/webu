/**
 * Part 3 — Layout Balance Detection.
 *
 * Analyzes section spacing, visual hierarchy, grid structure, and alignment.
 * Example issues: hero too tall, features too crowded, cta too small.
 * AI proposes fixes.
 */

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface LayoutSectionInput {
  localId: string;
  type: string;
  props?: Record<string, unknown>;
}

export interface LayoutAnalyzerInput {
  sections: LayoutSectionInput[];
  /** Optional: precomputed section kinds (e.g. from siteAnalyzer) for faster lookup. */
  sectionKinds?: string[];
}

export interface LayoutIssue {
  /** Human-readable issue (e.g. "hero too tall"). */
  issue: string;
  /** Section localId or type that this applies to. */
  sectionId?: string;
  /** Section kind (hero, features, cta, etc.) for grouping. */
  sectionKind?: string;
  /** Category: spacing, hierarchy, grid, alignment. */
  category: 'spacing' | 'hierarchy' | 'grid' | 'alignment';
  /** Suggested fix for the AI to propose. */
  proposedFix: string;
}

export interface LayoutAnalysisReport {
  issues: LayoutIssue[];
  /** Optional summary for UI. */
  summary?: string;
}

// ---------------------------------------------------------------------------
// Section type → kind (same concept as siteAnalyzer)
// ---------------------------------------------------------------------------

function getSectionKind(type: string): string {
  const t = (type || '').trim().toLowerCase();
  if (t.includes('header') || t.includes('nav')) return 'header';
  if (t.includes('footer')) return 'footer';
  if (t.includes('hero') || t.includes('banner')) return 'hero';
  if (t.includes('feature')) return 'features';
  if (t.includes('cta') || t.includes('call_to_action') || t.includes('calltoaction')) return 'cta';
  if (t.includes('pricing') || t.includes('plan')) return 'pricing';
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

function getPropNumber(props: Record<string, unknown> | undefined, key: string): number | undefined {
  if (!props) return undefined;
  const v = props[key];
  if (typeof v === 'number' && !Number.isNaN(v)) return v;
  if (typeof v === 'string') {
    const n = parseInt(v, 10);
    if (!Number.isNaN(n)) return n;
  }
  return undefined;
}

function getItemCount(props: Record<string, unknown> | undefined, key: string): number {
  if (!props) return 0;
  const v = props[key];
  if (Array.isArray(v)) return v.length;
  return 0;
}

/** Approximate content "height" from text length and presence of image. */
function estimateContentWeight(props: Record<string, unknown> | undefined): number {
  if (!props) return 0;
  let w = 0;
  const title = getPropStr(props, 'title', 'headline', 'heading');
  const subtitle = getPropStr(props, 'subtitle', 'description', 'body');
  if (title) w += Math.min(3, Math.ceil(title.length / 40));
  if (subtitle) w += Math.min(4, Math.ceil(subtitle.length / 60));
  if (props.image || props.imageUrl || props.backgroundImage) w += 4;
  const items = getItemCount(props, 'items');
  const plans = getItemCount(props, 'plans');
  const members = getItemCount(props, 'members');
  w += Math.min(10, (items + plans + members) * 2);
  return w;
}

// ---------------------------------------------------------------------------
// Spacing
// ---------------------------------------------------------------------------

function analyzeSectionSpacing(sections: LayoutSectionInput[], kinds: string[]): LayoutIssue[] {
  const issues: LayoutIssue[] = [];
  if (sections.length <= 1) return issues;

  const withPadding = sections.filter((s) => {
    const p = s.props;
    const pad = getPropStr(p, 'padding', 'spacing');
    return pad && pad !== 'none' && pad !== '0';
  });
  const withoutPadding = sections.length - withPadding.length;
  if (withoutPadding >= sections.length * 0.6 && sections.length >= 3) {
    issues.push({
      issue: 'section spacing is tight or inconsistent',
      category: 'spacing',
      proposedFix: 'Add consistent padding or spacing to sections for better breathing room',
    });
  }

  if (sections.length > 10) {
    issues.push({
      issue: 'too many sections; spacing may feel cramped',
      category: 'spacing',
      proposedFix: 'Consider grouping related content or reducing to 6–8 main sections',
    });
  }

  return issues;
}

// ---------------------------------------------------------------------------
// Visual hierarchy
// ---------------------------------------------------------------------------

function analyzeVisualHierarchy(sections: LayoutSectionInput[], kinds: string[]): LayoutIssue[] {
  const issues: LayoutIssue[] = [];

  for (let i = 0; i < sections.length; i++) {
    const s = sections[i];
    const kind = kinds[i] ?? getSectionKind(s.type);
    const props = s.props ?? {};
    const weight = estimateContentWeight(props);

    if (kind === 'hero') {
      if (weight >= 8) {
        issues.push({
          issue: 'hero too tall',
          sectionId: s.localId,
          sectionKind: 'hero',
          category: 'hierarchy',
          proposedFix: 'Reduce hero content or use a more compact hero variant; shorten headline and subtitle',
        });
      }
      if (weight <= 1 && !getPropStr(props, 'title', 'headline')) {
        issues.push({
          issue: 'hero too minimal',
          sectionId: s.localId,
          sectionKind: 'hero',
          category: 'hierarchy',
          proposedFix: 'Add a headline and optional subtitle so the hero has clear visual weight',
        });
      }
    }

    if (kind === 'cta') {
      const title = getPropStr(props, 'title', 'headline');
      const subtitle = getPropStr(props, 'subtitle', 'description');
      const btn = getPropStr(props, 'buttonLabel', 'buttonText', 'cta');
      if (weight <= 1 || (!title && !btn)) {
        issues.push({
          issue: 'cta too small',
          sectionId: s.localId,
          sectionKind: 'cta',
          category: 'hierarchy',
          proposedFix: 'Add a clear headline and call-to-action button; increase CTA section padding for prominence',
        });
      }
    }
  }

  const heroIndex = kinds.indexOf('hero');
  if (heroIndex > 2 && sections.length > 3) {
    issues.push({
      issue: 'hero is too far down the page',
      category: 'hierarchy',
      proposedFix: 'Move hero near the top (after header) for stronger visual hierarchy',
    });
  }

  return issues;
}

// ---------------------------------------------------------------------------
// Grid structure
// ---------------------------------------------------------------------------

function analyzeGridStructure(sections: LayoutSectionInput[], kinds: string[]): LayoutIssue[] {
  const issues: LayoutIssue[] = [];

  for (let i = 0; i < sections.length; i++) {
    const s = sections[i];
    const kind = kinds[i] ?? getSectionKind(s.type);
    const props = s.props ?? {};

    if (kind === 'features' || kind === 'cards' || kind === 'grid') {
      const items = getItemCount(props, 'items');
      const plans = getItemCount(props, 'plans');
      const members = getItemCount(props, 'members');
      const count = items + plans + members || 0;

      if (count > 8) {
        issues.push({
          issue: 'features too crowded',
          sectionId: s.localId,
          sectionKind: kind === 'features' ? 'features' : kind,
          category: 'grid',
          proposedFix: 'Show fewer items per row or reduce total items; use a grid with more columns or a carousel',
        });
      }

      const cols = getPropNumber(props, 'columns');
      if (count >= 6 && cols !== undefined && cols > 4) {
        issues.push({
          issue: 'grid has too many columns for content',
          sectionId: s.localId,
          sectionKind: kind,
          category: 'grid',
          proposedFix: 'Use 2–3 columns on desktop for better readability',
        });
      }
    }
  }

  return issues;
}

// ---------------------------------------------------------------------------
// Alignment
// ---------------------------------------------------------------------------

function analyzeAlignment(sections: LayoutSectionInput[], kinds: string[]): LayoutIssue[] {
  const issues: LayoutIssue[] = [];

  const alignmentValues = sections
    .map((s) => getPropStr(s.props, 'alignment', 'textAlign', 'align'))
    .filter(Boolean);
  const mixed = new Set(alignmentValues);
  if (mixed.size > 2 && alignmentValues.length >= 3) {
    issues.push({
      issue: 'inconsistent alignment across sections',
      category: 'alignment',
      proposedFix: 'Use consistent text alignment (e.g. center for hero/CTA, left for body) across the page',
    });
  }

  return issues;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Analyzes layout balance: section spacing, visual hierarchy, grid structure, alignment.
 * Returns issues with proposed fixes so the AI can suggest improvements.
 *
 * @param input — sections and optional precomputed sectionKinds
 * @returns LayoutAnalysisReport with issues (e.g. "hero too tall", "features too crowded", "cta too small") and proposedFix for each
 */
export function analyzeLayout(input: LayoutAnalyzerInput): LayoutAnalysisReport {
  const sections = input.sections ?? [];
  const kinds = input.sectionKinds ?? sections.map((s) => getSectionKind(s.type));

  const issues: LayoutIssue[] = [];
  issues.push(...analyzeSectionSpacing(sections, kinds));
  issues.push(...analyzeVisualHierarchy(sections, kinds));
  issues.push(...analyzeGridStructure(sections, kinds));
  issues.push(...analyzeAlignment(sections, kinds));

  const summary =
    issues.length === 0
      ? 'Layout balance looks good'
      : `${issues.length} layout issue${issues.length === 1 ? '' : 's'} found; AI can propose fixes`;

  return {
    issues,
    summary,
  };
}
