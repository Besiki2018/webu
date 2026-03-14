import { selectVariant, type LayoutComplexity } from './componentSelector';
import { getCatalogEntry, type AiComponentCatalogEntry } from './componentCatalog';
import type { AiProjectType } from './projectTypeDetector';

export interface VariantSelectionInput {
    componentKey: string;
    prompt: string;
    projectType: AiProjectType;
    tone?: string | null;
    industry?: string | null;
    styleKeywords?: string[];
    sectionType?: string | null;
    existingLayoutTypes?: string[];
    layoutComplexity?: LayoutComplexity;
}

function normalizePrompt(prompt: string): string {
    return prompt.toLowerCase().trim();
}

function matchesVariantKeyword(entry: AiComponentCatalogEntry, prompt: string): string | null {
    const normalizedPrompt = normalizePrompt(prompt);
    if (normalizedPrompt === '' || entry.variants.length === 0) {
        return null;
    }

    for (const variant of entry.variants) {
        const normalizedLabel = variant.label.toLowerCase();
        if (normalizedPrompt.includes(normalizedLabel)) {
            return variant.id;
        }

        if (normalizedPrompt.includes('minimal') && normalizedLabel.includes('minimal')) {
            return variant.id;
        }

        if (normalizedPrompt.includes('split') && normalizedLabel.includes('split')) {
            return variant.id;
        }

        if (normalizedPrompt.includes('center') && normalizedLabel.includes('center')) {
            return variant.id;
        }

        if (normalizedPrompt.includes('video') && normalizedLabel.includes('video')) {
            return variant.id;
        }
    }

    return null;
}

export function selectComponentVariant(input: VariantSelectionInput): string {
    const entry = getCatalogEntry(input.componentKey);
    if (!entry) {
        return '';
    }

    const keywordMatch = matchesVariantKeyword(entry, [
        input.prompt,
        input.industry ?? '',
        ...(input.styleKeywords ?? []),
        input.sectionType ?? '',
    ].filter(Boolean).join(' '));
    if (keywordMatch) {
        return keywordMatch;
    }

    const normalizedTone = input.tone ?? (
        normalizePrompt(input.prompt).includes('minimal')
            ? 'minimal'
            : normalizePrompt(input.prompt).includes('bold')
                ? 'bold'
                : normalizePrompt(input.prompt).includes('modern')
                    ? 'modern'
                    : null
    );

    const selected = selectVariant(input.componentKey, {
        projectType: input.projectType === 'booking' || input.projectType === 'clinic'
            ? 'hotel'
            : input.projectType === 'restaurant'
                ? 'restaurant'
                : input.projectType === 'business'
                    ? 'business'
                    : input.projectType === 'landing'
                        ? 'landing'
                        : input.projectType === 'portfolio'
                            ? 'portfolio'
                            : input.projectType === 'saas'
                                ? 'saas'
                                : input.projectType === 'blog'
                                    ? 'blog'
                                    : input.projectType === 'education'
                                        ? 'education'
                                        : 'ecommerce',
        tone: normalizedTone,
        industry: input.industry ?? null,
        layoutComplexity: input.layoutComplexity ?? 'medium',
    });

    if (selected) {
        return selected;
    }

    return entry.variants[0]?.id ?? '';
}
