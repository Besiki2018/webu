import { getAllowedComponentCatalog, getCatalogEntry, type AiComponentCatalogEntry, type AiComponentLayoutType } from './componentCatalog';
import { composeSectionProps } from './sectionComposer';
import { selectComponentVariant } from './variantSelector';
import { detectProjectType, type AiProjectType } from './projectTypeDetector';
import type { AiBuilderMutation } from './builderRenderAdapter';
import type { BuilderUpdateStateSnapshot } from '../state/updatePipeline';

export interface ChatTargetResolutionResult {
    ok: boolean;
    projectType: AiProjectType;
    mutations: AiBuilderMutation[];
    reason?: string;
}

type ChatMutationIntent = 'insert-section' | 'remove-section' | 'update-props' | 'replace-section' | 'reorder-section';

function normalizePrompt(prompt: string): string {
    return prompt.toLowerCase().trim().replace(/\s+/g, ' ');
}

function detectIntent(prompt: string): ChatMutationIntent {
    const normalized = normalizePrompt(prompt);
    if (/\b(add|insert|include)\b/.test(normalized)) return 'insert-section';
    if (/\b(remove|delete)\b/.test(normalized)) return 'remove-section';
    if (/\b(move|reorder)\b/.test(normalized)) return 'reorder-section';
    if (/\b(make|replace)\b/.test(normalized)) return 'replace-section';
    return 'update-props';
}

function detectLayoutType(prompt: string): AiComponentLayoutType | null {
    const normalized = normalizePrompt(prompt);
    if (normalized.includes('product')) return 'product-grid';
    if (normalized.includes('testimonial')) return 'testimonials';
    if (normalized.includes('faq')) return 'faq';
    if (normalized.includes('hero')) return 'hero';
    if (normalized.includes('feature') || normalized.includes('pricing')) return 'features';
    if (normalized.includes('cta') || normalized.includes('call to action')) return 'cta';
    if (normalized.includes('footer')) return 'footer';
    if (normalized.includes('header') || normalized.includes('navigation')) return 'header';
    if (normalized.includes('form') || normalized.includes('contact')) return 'form';
    if (normalized.includes('banner')) return 'banner';
    if (normalized.includes('grid') || normalized.includes('gallery')) return 'grid';
    if (normalized.includes('card')) return 'cards';
    return null;
}

function resolveTargetSection(
    state: BuilderUpdateStateSnapshot,
    layoutType: AiComponentLayoutType | null,
): { localId: string; entry: AiComponentCatalogEntry } | null {
    if (state.selectedSectionLocalId) {
        const selectedSection = state.sectionsDraft.find((section) => section.localId === state.selectedSectionLocalId) ?? null;
        if (selectedSection) {
            const entry = getCatalogEntry(selectedSection.type);
            if (entry && (!layoutType || entry.layoutType === layoutType)) {
                return {
                    localId: selectedSection.localId,
                    entry,
                };
            }
        }
    }

    for (const section of state.sectionsDraft) {
        const entry = getCatalogEntry(section.type);
        if (entry && (!layoutType || entry.layoutType === layoutType)) {
            return {
                localId: section.localId,
                entry,
            };
        }
    }

    return null;
}

function resolveInsertComponent(
    prompt: string,
    projectType: AiProjectType,
): AiComponentCatalogEntry | null {
    const desiredLayoutType = detectLayoutType(prompt);
    const catalog = getAllowedComponentCatalog(projectType);
    if (!desiredLayoutType) {
        return catalog[0] ?? null;
    }

    return catalog.find((entry) => entry.layoutType === desiredLayoutType) ?? null;
}

function extractReplacementText(prompt: string): string | null {
    const quoted = prompt.match(/["“](.+?)["”]/);
    if (quoted?.[1]) {
        return quoted[1].trim();
    }

    const toMatch = prompt.match(/\bto\s+(.+)$/i);
    if (toMatch?.[1]) {
        return toMatch[1].trim();
    }

    return null;
}

function resolveTextPatch(entry: AiComponentCatalogEntry, prompt: string): Record<string, unknown> {
    const replacement = extractReplacementText(prompt);
    if (!replacement) {
        return {};
    }

    const fieldAliases = normalizePrompt(prompt).includes('link')
        ? ['buttonLink', 'buttonUrl', 'ctaLink', 'cta_url']
        : normalizePrompt(prompt).includes('cta')
            ? ['buttonText', 'buttonLabel', 'ctaText', 'cta_label']
            : ['title', 'headline', 'subtitle', 'description', 'buttonText', 'buttonLabel'];

    for (const alias of fieldAliases) {
        const field = entry.propsSchema.find((candidate) => candidate.path === alias || candidate.path.split('.').pop() === alias);
        if (field) {
            return { [field.path]: replacement };
        }
    }

    return {};
}

export function resolveChatTargetMutation(
    prompt: string,
    state: BuilderUpdateStateSnapshot,
    explicitProjectType?: AiProjectType | null,
): ChatTargetResolutionResult {
    const normalizedPrompt = normalizePrompt(prompt);
    const projectType = explicitProjectType ?? detectProjectType(prompt).projectType;
    const intent = detectIntent(prompt);
    const layoutType = detectLayoutType(prompt);

    if (intent === 'insert-section') {
        const component = resolveInsertComponent(prompt, projectType);
        if (!component) {
            return { ok: false, projectType, mutations: [], reason: 'No allowed component matched the requested section.' };
        }

        const variant = selectComponentVariant({
            componentKey: component.componentKey,
            prompt,
            projectType,
        });
        const target = resolveTargetSection(state, layoutType);
        const insertIndex = target
            ? state.sectionsDraft.findIndex((section) => section.localId === target.localId) + 1
            : state.sectionsDraft.length;

        return {
            ok: true,
            projectType,
            mutations: [{
                kind: 'insert-section',
                sectionType: component.componentKey,
                insertIndex,
                props: {
                    ...(variant ? { variant } : {}),
                    ...composeSectionProps(component.componentKey, { prompt, projectType }),
                },
            }],
        };
    }

    const target = resolveTargetSection(state, layoutType);
    if (!target) {
        return { ok: false, projectType, mutations: [], reason: 'No matching section is available in the current page.' };
    }

    if (intent === 'remove-section') {
        return {
            ok: true,
            projectType,
            mutations: [{
                kind: 'remove-section',
                targetSectionLocalId: target.localId,
            }],
        };
    }

    if (intent === 'reorder-section') {
        const toIndex = normalizedPrompt.includes('top') || normalizedPrompt.includes('first')
            ? 0
            : state.sectionsDraft.length - 1;
        return {
            ok: true,
            projectType,
            mutations: [{
                kind: 'reorder-section',
                targetSectionLocalId: target.localId,
                toIndex,
            }],
        };
    }

    if (intent === 'replace-section') {
        const variant = selectComponentVariant({
            componentKey: target.entry.componentKey,
            prompt,
            projectType,
        });
        return {
            ok: true,
            projectType,
            mutations: [{
                kind: 'replace-section',
                targetSectionLocalId: target.localId,
                sectionType: target.entry.componentKey,
                props: variant ? { variant } : {},
            }],
        };
    }

    const patch = resolveTextPatch(target.entry, prompt);
    if (Object.keys(patch).length === 0) {
        return { ok: false, projectType, mutations: [], reason: 'No editable field matched the requested change.' };
    }

    return {
        ok: true,
        projectType,
        mutations: [{
            kind: 'update-props',
            targetSectionLocalId: target.localId,
            patch,
        }],
    };
}
