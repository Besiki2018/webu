import {
    getComponentCodegenMetadata,
    getDefaultProps,
    getShortDisplayName,
    hasEntry,
    resolveComponentRegistryKey,
} from '@/builder/componentRegistry';
import { getVariantForStyle } from '@/builder/ai/designStyleAnalyzer';

import type {
    ImageImportComponentMatch,
    ImageImportDesignExtraction,
    ImageImportGeneratedComponentSpec,
    ImageImportInteractiveModuleKind,
    ImageImportLayoutInference,
    ImageImportLayoutNode,
    ImageImportLayoutNodeKind,
} from './types';

const CANDIDATES_BY_KIND: Record<ImageImportLayoutNodeKind, string[]> = {
    header: ['webu_header_01', 'webu_general_navigation_01'],
    hero: ['webu_general_hero_01'],
    features: ['webu_general_features_01', 'webu_general_cards_01'],
    gallery: ['webu_general_grid_01', 'webu_general_cards_01'],
    form: ['webu_general_form_wrapper_01', 'webu_general_cta_01'],
    grid: ['webu_general_grid_01', 'webu_general_cards_01'],
    'product-grid': ['webu_ecom_product_grid_01', 'webu_general_grid_01'],
    testimonials: ['webu_general_testimonials_01', 'webu_general_cards_01'],
    cta: ['webu_general_cta_01'],
    footer: ['webu_footer_01'],
    faq: ['faq_accordion_plus', 'webu_general_cards_01'],
    content: ['webu_general_heading_01', 'webu_general_text_01', 'webu_general_section_01'],
    generated: [],
};

function toPascalCase(value: string): string {
    return value
        .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
        .split(/[^a-z0-9]+/i)
        .filter(Boolean)
        .map((part) => part[0]!.toUpperCase() + part.slice(1))
        .join('');
}

function buildSectionFilePath(pageSlug: string, componentName: string, index: number): string {
    return `src/sections/imported/${toPascalCase(pageSlug)}${componentName}${index + 1}.tsx`;
}

function buildGeneratedComponentSpec(pageSlug: string, node: ImageImportLayoutNode, index: number): ImageImportGeneratedComponentSpec {
    const componentName = `${toPascalCase(pageSlug)}${toPascalCase(node.sectionLabel || node.kind)}Section`;
    return {
        componentName,
        filePath: buildSectionFilePath(pageSlug, componentName, index),
        template: node.kind === 'hero'
            ? 'hero'
            : node.kind === 'form'
                ? 'form'
                : node.kind === 'gallery' || node.kind === 'grid' || node.kind === 'product-grid'
                    ? 'grid'
                    : 'content',
    };
}

function scoreCandidate(
    node: ImageImportLayoutNode,
    candidate: string,
    interactiveModule: ImageImportInteractiveModuleKind | null,
    exactMode: boolean,
): number {
    let score = 0.55;

    if (node.kind === 'header' && candidate === 'webu_header_01') {
        score += 0.18;
    }

    if (node.kind === 'hero' && candidate === 'webu_general_hero_01') {
        score += 0.18;
    }

    if (node.kind === 'footer' && candidate === 'webu_footer_01') {
        score += 0.18;
    }

    if (node.kind === 'product-grid' && candidate === 'webu_ecom_product_grid_01') {
        score += 0.2;
    }

    if (node.kind === 'features' && candidate === 'webu_general_features_01') {
        score += 0.14;
    }

    if (node.kind === 'grid' && candidate === 'webu_general_grid_01') {
        score += 0.14;
    }

    if (node.kind === 'gallery' && candidate === 'webu_general_grid_01') {
        score += 0.12;
    }

    if (node.kind === 'cta' && candidate === 'webu_general_cta_01') {
        score += 0.18;
    }

    if (node.kind === 'form' && candidate === 'webu_general_form_wrapper_01') {
        score += 0.18;
    }

    if (node.kind === 'testimonials' && candidate === 'webu_general_testimonials_01') {
        score += 0.12;
    }

    if (node.kind === 'faq' && candidate === 'faq_accordion_plus') {
        score += 0.12;
    }

    if (interactiveModule === 'ecommerce' && candidate === 'webu_ecom_product_grid_01') {
        score += 0.18;
    }

    if ((interactiveModule === 'booking' || interactiveModule === 'contact_form' || interactiveModule === 'newsletter') && candidate === 'webu_general_form_wrapper_01') {
        score += 0.15;
    }

    if (!exactMode) {
        score += 0.06;
    }

    if (node.kind === 'generated') {
        score -= 0.3;
    }

    return Math.min(score, 0.98);
}

function buildRegistryProps(
    extraction: ImageImportDesignExtraction,
    node: ImageImportLayoutNode,
    registryKey: string,
): Record<string, unknown> {
    const defaults = getDefaultProps(registryKey);
    const next = {
        ...defaults,
        ...node.propsSeed,
    };
    const styleVariant = getVariantForStyle(
        registryKey,
        extraction.styleDirection.primaryStyle === 'bold'
            ? 'startup'
            : extraction.styleDirection.primaryStyle === 'editorial'
                ? 'modern'
                : extraction.styleDirection.primaryStyle,
    );
    if (styleVariant !== '') {
        next.variant = styleVariant;
    }

    return next;
}

function resolveCandidate(
    node: ImageImportLayoutNode,
    exactMode: boolean,
): { registryKey: string | null; score: number; rationale: string } {
    const candidates = CANDIDATES_BY_KIND[node.kind] ?? [];
    let best: { registryKey: string | null; score: number; rationale: string } = {
        registryKey: null,
        score: 0,
        rationale: 'no_candidate',
    };

    candidates.forEach((candidate) => {
        const registryKey = resolveComponentRegistryKey(candidate);
        if (!registryKey || !hasEntry(registryKey)) {
            return;
        }

        const score = scoreCandidate(node, registryKey, node.interactiveModule, exactMode);
        if (score > best.score) {
            best = {
                registryKey,
                score,
                rationale: node.interactiveModule
                    ? `${node.kind}_matched_with_${node.interactiveModule}_module`
                    : `${node.kind}_matched_to_registry`,
            };
        }
    });

    return best;
}

export function matchImageImportComponents(
    extraction: ImageImportDesignExtraction,
    layout: ImageImportLayoutInference,
    input: {
        pageSlug: string;
    },
): ImageImportComponentMatch[] {
    const exactMode = extraction.mode === 'recreate';
    const scoreThreshold = exactMode ? 0.72 : 0.62;

    return layout.nodes.map((node, index) => {
        const best = resolveCandidate(node, exactMode);
        if (best.registryKey && best.score >= scoreThreshold) {
            const codegen = getComponentCodegenMetadata(best.registryKey);
            const componentName = codegen?.importName
                ? `${toPascalCase(codegen.importName)}Imported`
                : `${toPascalCase(node.kind)}Imported`;

            return {
                nodeId: node.id,
                matchKind: 'registry',
                registryKey: best.registryKey,
                displayName: getShortDisplayName(best.registryKey, node.sectionLabel || node.kind),
                componentName,
                sourceFilePath: buildSectionFilePath(input.pageSlug, componentName, index),
                ownerType: node.kind === 'header' || node.kind === 'footer' ? 'layout' : 'component',
                score: best.score,
                rationale: best.rationale,
                props: buildRegistryProps(extraction, node, best.registryKey),
                generatedComponent: null,
                metadata: {
                    sourceKind: node.kind,
                    exactMode,
                    importedFrom: node.sourceBlockIds,
                },
            } satisfies ImageImportComponentMatch;
        }

        const generatedComponent = buildGeneratedComponentSpec(input.pageSlug, node, index);
        return {
            nodeId: node.id,
            matchKind: 'generated',
            registryKey: null,
            displayName: node.sectionLabel || 'Imported section',
            componentName: generatedComponent.componentName,
            sourceFilePath: generatedComponent.filePath,
            ownerType: 'component',
            score: best.score,
            rationale: best.registryKey ? 'registry_score_below_threshold' : 'no_registry_match',
            props: {
                ...node.propsSeed,
                importedMode: extraction.mode,
            },
            generatedComponent,
            metadata: {
                sourceKind: node.kind,
                exactMode,
                importedFrom: node.sourceBlockIds,
            },
        } satisfies ImageImportComponentMatch;
    });
}
