/**
 * Part 4 — Component Improvement Suggestions.
 *
 * Suggests concrete improvements: upgrade hero variant (hero1 → hero3), add icons to features,
 * add background pattern, improve CTA visibility. AI or builder can apply suggested patches.
 */

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface ComponentSectionInput {
  localId: string;
  type: string;
  props?: Record<string, unknown>;
}

export interface ComponentImproverInput {
  sections: ComponentSectionInput[];
  sectionKinds?: string[];
}

export interface ImprovementSuggestion {
  /** Short title (e.g. "Upgrade hero variant"). */
  title: string;
  /** Longer description for the AI or user. */
  description: string;
  sectionId?: string;
  sectionKind?: string;
  /** Props patch to apply (e.g. { variant: 'hero-3' }). */
  suggestedPatch: Record<string, unknown>;
  /** Optional priority 1–3 (1 = high). */
  priority?: number;
}

export interface ComponentImproverReport {
  suggestions: ImprovementSuggestion[];
  summary?: string;
}

// ---------------------------------------------------------------------------
// Section type → kind
// ---------------------------------------------------------------------------

function getSectionKind(type: string): string {
  const t = (type || '').trim().toLowerCase();
  if (t.includes('header') || t.includes('nav')) return 'header';
  if (t.includes('footer')) return 'footer';
  if (t.includes('hero') || t.includes('banner')) return 'hero';
  if (t.includes('feature')) return 'features';
  if (t.includes('cta') || t.includes('call_to_action') || t.includes('calltoaction')) return 'cta';
  if (t.includes('pricing')) return 'pricing';
  if (t.includes('testimonial') || t.includes('review')) return 'testimonials';
  if (t.includes('card')) return 'cards';
  if (t.includes('grid')) return 'grid';
  if (t.includes('newsletter')) return 'newsletter';
  return t || 'unknown';
}

function getVariant(props: Record<string, unknown> | undefined): string {
  if (!props) return '';
  const v = props.variant ?? props.variantId;
  return typeof v === 'string' ? v.trim().toLowerCase() : '';
}

// ---------------------------------------------------------------------------
// Variant upgrade maps (current → suggested better)
// ---------------------------------------------------------------------------

/** Hero: suggest upgrading from 1/2 to 3 for a more modern look. */
const HERO_VARIANT_UPGRADE: Record<string, string> = {
  'hero-1': 'hero-3',
  'hero-2': 'hero-3',
  'hero1': 'hero-3',
  'hero2': 'hero-3',
};

/** Features: suggest variant with stronger visual (e.g. icons). */
const FEATURES_VARIANT_UPGRADE: Record<string, string> = {
  'features-1': 'features-2',
  'features-2': 'features-3',
  'features1': 'features-2',
  'features2': 'features-3',
};

/** CTA: suggest more visible variant. */
const CTA_VARIANT_UPGRADE: Record<string, string> = {
  'cta-1': 'cta-2',
  'cta-2': 'cta-3',
  'cta1': 'cta-2',
  'cta2': 'cta-3',
};

// ---------------------------------------------------------------------------
// Suggestion builders
// ---------------------------------------------------------------------------

function suggestHeroImprovements(section: ComponentSectionInput, kind: string): ImprovementSuggestion[] {
  const out: ImprovementSuggestion[] = [];
  const variant = getVariant(section.props);
  const upgrade = variant ? HERO_VARIANT_UPGRADE[variant] : 'hero-3';
  if (upgrade) {
    out.push({
      title: 'Upgrade hero variant',
      description: `Upgrade from ${variant || 'default'} to ${upgrade} for a more modern hero layout`,
      sectionId: section.localId,
      sectionKind: 'hero',
      suggestedPatch: { variant: upgrade },
      priority: 1,
    });
  }
  if (!section.props?.backgroundImage && !section.props?.backgroundColor) {
    out.push({
      title: 'Add background pattern',
      description: 'Add a background pattern or gradient to the hero for visual depth',
      sectionId: section.localId,
      sectionKind: 'hero',
      suggestedPatch: { backgroundColor: '#f8fafc' },
      priority: 2,
    });
  }
  return out;
}

function suggestFeaturesImprovements(section: ComponentSectionInput, kind: string): ImprovementSuggestion[] {
  const out: ImprovementSuggestion[] = [];
  const variant = getVariant(section.props);
  const upgrade = variant ? FEATURES_VARIANT_UPGRADE[variant] : 'features-2';
  if (upgrade) {
    out.push({
      title: 'Add icons to features',
      description: `Use variant ${upgrade} for icon support and clearer feature cards`,
      sectionId: section.localId,
      sectionKind: 'features',
      suggestedPatch: { variant: upgrade },
      priority: 1,
    });
  }
  return out;
}

function suggestCtaImprovements(section: ComponentSectionInput, kind: string): ImprovementSuggestion[] {
  const out: ImprovementSuggestion[] = [];
  const variant = getVariant(section.props);
  const upgrade = variant ? CTA_VARIANT_UPGRADE[variant] : 'cta-2';
  if (upgrade) {
    out.push({
      title: 'Improve CTA visibility',
      description: `Upgrade to variant ${upgrade} for better CTA prominence and conversion`,
      sectionId: section.localId,
      sectionKind: 'cta',
      suggestedPatch: { variant: upgrade },
      priority: 1,
    });
  }
  if (!section.props?.backgroundColor && !section.props?.backgroundImage) {
    out.push({
      title: 'Add CTA background',
      description: 'Add a background color or pattern so the CTA stands out from the page',
      sectionId: section.localId,
      sectionKind: 'cta',
      suggestedPatch: { backgroundColor: '#0f172a' },
      priority: 2,
    });
  }
  return out;
}

function suggestGenericBackground(section: ComponentSectionInput, kind: string): ImprovementSuggestion[] {
  if (kind !== 'hero' && kind !== 'cta') return [];
  if (section.props?.backgroundImage || section.props?.backgroundColor) return [];
  return [{
    title: 'Add background pattern',
    description: `Add a subtle background to the ${kind} section for visual hierarchy`,
    sectionId: section.localId,
    sectionKind: kind,
    suggestedPatch: { backgroundColor: kind === 'cta' ? '#0f172a' : '#f1f5f9' },
    priority: 2,
  }];
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Analyzes sections and returns improvement suggestions (upgrade variant, add icons,
 * add background pattern, improve CTA visibility). Each suggestion includes a
 * suggestedPatch that can be applied to section props.
 *
 * Example: hero1 → hero3 (suggestedPatch: { variant: 'hero-3' }).
 *
 * @param input — sections and optional sectionKinds
 * @returns ComponentImproverReport with suggestions
 */
export function suggestComponentImprovements(input: ComponentImproverInput): ComponentImproverReport {
  const sections = input.sections ?? [];
  const kinds = input.sectionKinds ?? sections.map((s) => getSectionKind(s.type));
  const suggestions: ImprovementSuggestion[] = [];

  for (let i = 0; i < sections.length; i++) {
    const section = sections[i];
    const kind = kinds[i] ?? getSectionKind(section.type);

    if (kind === 'hero') {
      suggestions.push(...suggestHeroImprovements(section, kind));
    } else if (kind === 'features') {
      suggestions.push(...suggestFeaturesImprovements(section, kind));
    } else if (kind === 'cta') {
      suggestions.push(...suggestCtaImprovements(section, kind));
    } else {
      suggestions.push(...suggestGenericBackground(section, kind));
    }
  }

  const summary =
    suggestions.length === 0
      ? 'No improvement suggestions'
      : `${suggestions.length} improvement suggestion${suggestions.length === 1 ? '' : 's'} (e.g. upgrade variant, add background)`;

  return { suggestions, summary };
}

/**
 * Returns the suggested variant upgrade for a section kind and current variant.
 * Example: getVariantUpgrade('hero', 'hero-1') → 'hero-3'.
 */
export function getVariantUpgrade(sectionKind: string, currentVariant: string): string | null {
  const v = (currentVariant || '').trim().toLowerCase();
  const k = (sectionKind || '').trim().toLowerCase();
  if (k === 'hero') return HERO_VARIANT_UPGRADE[v] ?? null;
  if (k === 'features') return FEATURES_VARIANT_UPGRADE[v] ?? null;
  if (k === 'cta') return CTA_VARIANT_UPGRADE[v] ?? null;
  return null;
}
