import { detectLayoutFromImage, type LayoutVisionProvider } from '@/builder/ai/layoutDetector';
import { detectStyleFromDesign, type StyleVisionProvider } from '@/builder/ai/designStyleAnalyzer';
import {
    extractContentFromDesign,
    type DesignExtractionProvider,
    type ExtractContentFromDesignOptions,
} from '@/builder/ai/designContentExtractor';
import {
    processDesignImages,
    type ImageBlockDetector,
    type StockImageProvider,
} from '@/builder/ai/designImageProcessor';
import type { ContentGeneratorProvider } from '@/builder/ai/contentGenerator';
import type { ProjectType } from '@/builder/projectTypes';

import type {
    ImageImportBlockContent,
    ImageImportDesignExtraction,
    ImageImportFunctionSignal,
    ImageImportMode,
    ImageImportSourceKind,
    ImageImportStyleDirection,
    ImageImportVisualBlock,
    ImageImportVisualBlockKind,
} from './types';

export interface ImageImportDesignExtractionRequest {
    image: File | string;
    sourceKind?: ImageImportSourceKind;
    sourceLabel?: string | null;
    projectType?: ProjectType;
    mode?: ImageImportMode;
    preferredStyle?: string | null;
}

export interface ImageImportDesignExtractionResponse {
    schemaVersion?: 1;
    sourceKind?: ImageImportSourceKind;
    sourceLabel?: string | null;
    projectType?: ProjectType;
    mode?: ImageImportMode;
    blocks?: Array<Partial<ImageImportVisualBlock>>;
    functionSignals?: Array<Partial<ImageImportFunctionSignal>>;
    styleDirection?: Partial<ImageImportStyleDirection>;
    warnings?: string[];
    extractedAt?: string | null;
    metadata?: Record<string, unknown>;
}

export type ImageImportDesignExtractionProvider = (
    request: ImageImportDesignExtractionRequest,
) => Promise<ImageImportDesignExtractionResponse>;

export interface CreateImageImportDesignExtractionOptions extends ImageImportDesignExtractionRequest {
    backendProvider?: ImageImportDesignExtractionProvider | null;
    layoutVisionProvider?: LayoutVisionProvider | null;
    styleVisionProvider?: StyleVisionProvider | null;
    extractionProvider?: DesignExtractionProvider | null;
    contentGeneratorProvider?: ContentGeneratorProvider | null;
    imageDetector?: ImageBlockDetector | null;
    stockImageProvider?: StockImageProvider | null;
}

function fileToDataUrl(file: File): Promise<string> {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
            if (typeof reader.result === 'string') {
                resolve(reader.result);
                return;
            }

            reject(new Error('FileReader did not return a string'));
        };
        reader.onerror = () => reject(reader.error);
        reader.readAsDataURL(file);
    });
}

function normalizeText(value: unknown): string | null {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

function normalizeStringArray(value: unknown): string[] {
    return Array.isArray(value)
        ? value
            .map((entry) => normalizeText(entry))
            .filter((entry): entry is string => entry !== null)
        : [];
}

function normalizeBlockContent(input: Partial<ImageImportBlockContent> | null | undefined): ImageImportBlockContent {
    return {
        eyebrow: normalizeText(input?.eyebrow),
        title: normalizeText(input?.title),
        subtitle: normalizeText(input?.subtitle),
        body: normalizeText(input?.body),
        ctaLabel: normalizeText(input?.ctaLabel),
        secondaryCtaLabel: normalizeText(input?.secondaryCtaLabel),
        items: normalizeStringArray(input?.items),
        labels: normalizeStringArray(input?.labels),
        imageUrls: normalizeStringArray(input?.imageUrls),
    };
}

function normalizeStyleDirection(input: Partial<ImageImportStyleDirection> | null | undefined): ImageImportStyleDirection {
    const primaryStyle = normalizeText(input?.primaryStyle);
    return {
        primaryStyle: primaryStyle === 'minimal'
            || primaryStyle === 'bold'
            || primaryStyle === 'corporate'
            || primaryStyle === 'editorial'
            ? primaryStyle
            : 'modern',
        isDark: input?.isDark === true,
        spacing: input?.spacing === 'airy' || input?.spacing === 'compact' ? input.spacing : 'balanced',
        borderTreatment: input?.borderTreatment === 'soft'
            || input?.borderTreatment === 'sharp'
            ? input.borderTreatment
            : 'rounded',
        visualWeight: input?.visualWeight === 'light' || input?.visualWeight === 'strong'
            ? input.visualWeight
            : 'balanced',
    };
}

function mapPreferredStyleToAccentTone(value: string | null | undefined): ImageImportStyleDirection['primaryStyle'] {
    switch ((value ?? '').trim().toLowerCase()) {
        case 'minimal':
            return 'minimal';
        case 'bold':
        case 'startup':
            return 'bold';
        case 'corporate':
            return 'corporate';
        case 'editorial':
            return 'editorial';
        default:
            return 'modern';
    }
}

function normalizeVisualBlockKind(value: unknown): ImageImportVisualBlockKind {
    switch (normalizeText(value)) {
        case 'navbar':
        case 'hero':
        case 'feature-list':
        case 'card-grid':
        case 'cta':
        case 'footer':
        case 'gallery':
        case 'form':
        case 'grid':
        case 'product-grid':
        case 'testimonial-strip':
        case 'content':
        case 'logo-strip':
        case 'pricing':
        case 'faq':
            return value as ImageImportVisualBlockKind;
        default:
            return 'unknown';
    }
}

function normalizeFunctionSignal(input: Partial<ImageImportFunctionSignal> | null | undefined): ImageImportFunctionSignal | null {
    const kind = normalizeText(input?.kind);
    if (
        kind !== 'booking'
        && kind !== 'ecommerce'
        && kind !== 'newsletter'
        && kind !== 'contact_form'
        && kind !== 'blog'
    ) {
        return null;
    }

    return {
        kind,
        confidence: typeof input?.confidence === 'number' && Number.isFinite(input.confidence) ? input.confidence : 0.6,
        evidence: normalizeStringArray(input?.evidence),
        sourceBlockIds: normalizeStringArray(input?.sourceBlockIds),
    };
}

function normalizeVisualBlock(block: Partial<ImageImportVisualBlock>, index: number): ImageImportVisualBlock {
    return {
        id: normalizeText(block.id) ?? `block-${index + 1}`,
        kind: normalizeVisualBlockKind(block.kind),
        order: typeof block.order === 'number' && Number.isFinite(block.order) ? block.order : index,
        level: typeof block.level === 'number' && Number.isFinite(block.level) ? block.level : 0,
        parentId: normalizeText(block.parentId),
        confidence: typeof block.confidence === 'number' && Number.isFinite(block.confidence) ? block.confidence : 0.6,
        bounds: block.bounds ?? null,
        content: normalizeBlockContent(block.content),
        layout: {
            columns: typeof block.layout?.columns === 'number' && Number.isFinite(block.layout.columns) ? block.layout.columns : null,
            preserveHierarchy: block.layout?.preserveHierarchy !== false,
            preserveSpacing: block.layout?.preserveSpacing !== false,
            hasMedia: block.layout?.hasMedia === true,
            hasButtons: block.layout?.hasButtons === true,
            density: block.layout?.density === 'airy' || block.layout?.density === 'compact' ? block.layout.density : 'balanced',
            alignment: block.layout?.alignment === 'left'
                || block.layout?.alignment === 'center'
                || block.layout?.alignment === 'split'
                || block.layout?.alignment === 'grid'
                ? block.layout.alignment
                : 'stack',
        },
        evidence: normalizeStringArray(block.evidence),
    };
}

export function normalizeImageImportDesignExtractionResponse(
    response: ImageImportDesignExtractionResponse,
): ImageImportDesignExtraction {
    return {
        schemaVersion: 1,
        sourceKind: response.sourceKind ?? 'reference',
        sourceLabel: normalizeText(response.sourceLabel),
        projectType: response.projectType ?? 'landing',
        mode: response.mode ?? 'reference',
        blocks: Array.isArray(response.blocks)
            ? response.blocks.map((block, index) => normalizeVisualBlock(block, index))
            : [],
        functionSignals: Array.isArray(response.functionSignals)
            ? response.functionSignals
                .map((signal) => normalizeFunctionSignal(signal))
                .filter((signal): signal is ImageImportFunctionSignal => signal !== null)
            : [],
        styleDirection: normalizeStyleDirection(response.styleDirection),
        warnings: normalizeStringArray(response.warnings),
        extractedAt: normalizeText(response.extractedAt),
        metadata: response.metadata ?? {},
    };
}

function mapLegacyLayoutTypeToVisualKind(type: string): ImageImportVisualBlockKind {
    switch (type) {
        case 'header':
        case 'navigation':
            return 'navbar';
        case 'hero':
            return 'hero';
        case 'features':
            return 'feature-list';
        case 'productGrid':
            return 'product-grid';
        case 'pricing':
            return 'pricing';
        case 'testimonials':
            return 'testimonial-strip';
        case 'cta':
            return 'cta';
        case 'footer':
            return 'footer';
        case 'gallery':
            return 'gallery';
        case 'contact':
        case 'booking':
            return 'form';
        case 'faq':
            return 'faq';
        case 'cards':
            return 'card-grid';
        case 'grid':
        case 'blog':
        case 'menu':
            return 'grid';
        default:
            return 'content';
    }
}

async function resolveImageSource(image: File | string): Promise<string> {
    return typeof image === 'string' ? image : fileToDataUrl(image);
}

export async function createImageImportDesignExtraction(
    input: CreateImageImportDesignExtractionOptions,
): Promise<ImageImportDesignExtraction> {
    const request: ImageImportDesignExtractionRequest = {
        image: input.image,
        sourceKind: input.sourceKind ?? (typeof input.image === 'string' ? 'url' : 'upload'),
        sourceLabel: input.sourceLabel ?? null,
        projectType: input.projectType ?? 'landing',
        mode: input.mode ?? 'reference',
        preferredStyle: input.preferredStyle ?? null,
    };

    if (input.backendProvider) {
        const response = await input.backendProvider(request);
        return normalizeImageImportDesignExtractionResponse({
            ...response,
            sourceKind: response.sourceKind ?? request.sourceKind,
            sourceLabel: response.sourceLabel ?? request.sourceLabel,
            projectType: response.projectType ?? request.projectType,
            mode: response.mode ?? request.mode,
        });
    }

    const imageSource = await resolveImageSource(input.image);
    const [layoutResult, styleResult, contentResult, processedImages] = await Promise.all([
        detectLayoutFromImage(imageSource, {
            projectType: request.projectType,
            preferredStyle: request.preferredStyle ?? undefined,
            visionProvider: input.layoutVisionProvider ?? undefined,
        }),
        detectStyleFromDesign({
            designImageSource: imageSource,
            projectType: request.projectType,
            styleVisionProvider: input.styleVisionProvider ?? undefined,
        }),
        extractContentFromDesign(imageSource, {
            extractionProvider: input.extractionProvider ?? undefined,
            contentGeneratorProvider: input.contentGeneratorProvider ?? undefined,
            projectType: request.projectType,
        } satisfies ExtractContentFromDesignOptions),
        processDesignImages(imageSource, {
            detector: input.imageDetector ?? undefined,
            stockImageProvider: input.stockImageProvider ?? undefined,
            projectType: request.projectType,
            useOriginals: true,
        }),
    ]);

    const heroImages = processedImages
        .filter((image) => image.role === 'hero' || image.role === 'general')
        .map((image) => image.url);

    const blocks = layoutResult.blocks.map((block, index): ImageImportVisualBlock => {
        const kind = mapLegacyLayoutTypeToVisualKind(block.type);
        const isHero = kind === 'hero';
        const title = isHero ? contentResult.title ?? 'Design-derived hero' : null;
        const subtitle = isHero ? contentResult.subtitle ?? null : null;
        const ctaLabel = isHero || kind === 'cta' || kind === 'form'
            ? contentResult.ctaText ?? 'Get started'
            : null;

        return {
            id: `legacy-block-${index + 1}`,
            kind,
            order: index,
            level: 0,
            parentId: null,
            confidence: 0.62,
            bounds: null,
            content: {
                eyebrow: isHero ? contentResult.eyebrow ?? null : null,
                title,
                subtitle,
                body: !isHero && kind === 'content'
                    ? contentResult.textBlocks?.[0] ?? contentResult.subtitle ?? null
                    : null,
                ctaLabel,
                secondaryCtaLabel: isHero ? contentResult.ctaSecondary ?? null : null,
                items: kind === 'feature-list' || kind === 'grid'
                    ? (contentResult.textBlocks ?? []).slice(0, 4)
                    : [],
                labels: [],
                imageUrls: isHero ? heroImages.slice(0, 1) : processedImages.filter((image) => image.role === 'general').map((image) => image.url),
            },
            layout: {
                columns: kind === 'grid' || kind === 'card-grid' || kind === 'gallery' ? 3 : null,
                preserveHierarchy: request.mode === 'recreate',
                preserveSpacing: request.mode === 'recreate',
                hasMedia: heroImages.length > 0,
                hasButtons: kind === 'cta' || kind === 'hero' || kind === 'form',
                density: styleResult.style === 'minimal' ? 'airy' : 'balanced',
                alignment: isHero ? 'split' : kind === 'grid' || kind === 'gallery' ? 'grid' : 'stack',
            },
            evidence: [block.type, block.position],
        };
    });

    return {
        schemaVersion: 1,
        sourceKind: request.sourceKind ?? 'reference',
        sourceLabel: request.sourceLabel ?? null,
        projectType: request.projectType ?? 'landing',
        mode: request.mode ?? 'reference',
        blocks,
        functionSignals: [],
        styleDirection: normalizeStyleDirection({
            primaryStyle: request.preferredStyle?.trim() !== ''
                ? mapPreferredStyleToAccentTone(request.preferredStyle)
                : mapPreferredStyleToAccentTone(styleResult.style),
            isDark: styleResult.isDarkTheme ?? styleResult.style === 'dark',
            spacing: styleResult.style === 'minimal' ? 'airy' : styleResult.style === 'corporate' ? 'balanced' : 'compact',
            borderTreatment: styleResult.style === 'corporate' ? 'sharp' : 'rounded',
            visualWeight: styleResult.style === 'minimal' ? 'light' : 'balanced',
        }),
        warnings: ['legacy_heuristic_extraction'],
        extractedAt: new Date().toISOString(),
        metadata: {
            extractedImages: processedImages.length,
        },
    };
}
