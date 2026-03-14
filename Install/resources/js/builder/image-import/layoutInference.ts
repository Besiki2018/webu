import type {
    ImageImportDesignExtraction,
    ImageImportFunctionSignal,
    ImageImportInteractiveModuleKind,
    ImageImportLayoutInference,
    ImageImportLayoutNode,
    ImageImportLayoutNodeKind,
    ImageImportVisualBlock,
} from './types';

function normalizeText(value: unknown): string {
    return typeof value === 'string' ? value.trim() : '';
}

function containsAny(value: string, patterns: string[]): boolean {
    const normalized = value.toLowerCase();
    return patterns.some((pattern) => normalized.includes(pattern));
}

function collectEvidenceText(block: ImageImportVisualBlock): string[] {
    return [
        block.kind,
        block.content.title,
        block.content.subtitle,
        block.content.body,
        ...block.content.items,
        ...block.content.labels,
        ...block.evidence,
    ]
        .map((entry) => normalizeText(entry))
        .filter(Boolean);
}

function inferModuleFromBlock(block: ImageImportVisualBlock, projectType: ImageImportDesignExtraction['projectType']): ImageImportInteractiveModuleKind | null {
    const evidence = collectEvidenceText(block).join(' ').toLowerCase();

    if (
        projectType === 'ecommerce'
        || block.kind === 'product-grid'
        || containsAny(evidence, ['shop', 'cart', 'product', 'collection', 'price'])
    ) {
        return 'ecommerce';
    }

    if (
        projectType === 'restaurant'
        || projectType === 'hotel'
        || containsAny(evidence, ['book', 'reserve', 'availability', 'table', 'check-in', 'appointment'])
    ) {
        return 'booking';
    }

    if (containsAny(evidence, ['newsletter', 'subscribe', 'updates', 'weekly'])) {
        return 'newsletter';
    }

    if (block.kind === 'form' || containsAny(evidence, ['contact', 'message', 'email', 'reach out'])) {
        return 'contact_form';
    }

    if (containsAny(evidence, ['blog', 'article', 'post', 'insight'])) {
        return 'blog';
    }

    return null;
}

function buildFunctionSignal(kind: ImageImportInteractiveModuleKind, block: ImageImportVisualBlock): ImageImportFunctionSignal {
    return {
        kind,
        confidence: block.kind === 'form' || block.kind === 'product-grid' ? 0.86 : 0.72,
        evidence: collectEvidenceText(block).slice(0, 6),
        sourceBlockIds: [block.id],
    };
}

export function inferInteractiveModules(extraction: ImageImportDesignExtraction): ImageImportFunctionSignal[] {
    const merged = new Map<ImageImportInteractiveModuleKind, ImageImportFunctionSignal>();

    extraction.functionSignals.forEach((signal) => {
        merged.set(signal.kind, signal);
    });

    extraction.blocks.forEach((block) => {
        const inferred = inferModuleFromBlock(block, extraction.projectType);
        if (!inferred) {
            return;
        }

        const existing = merged.get(inferred);
        const nextSignal = buildFunctionSignal(inferred, block);
        if (!existing || existing.confidence < nextSignal.confidence) {
            merged.set(inferred, nextSignal);
        }
    });

    return Array.from(merged.values()).sort((left, right) => right.confidence - left.confidence);
}

function mapBlockToNodeKind(
    block: ImageImportVisualBlock,
    interactiveModule: ImageImportInteractiveModuleKind | null,
): ImageImportLayoutNodeKind {
    switch (block.kind) {
        case 'navbar':
            return 'header';
        case 'hero':
            return 'hero';
        case 'feature-list':
        case 'logo-strip':
        case 'pricing':
            return 'features';
        case 'gallery':
            return 'gallery';
        case 'form':
            return 'form';
        case 'grid':
        case 'card-grid':
            return interactiveModule === 'ecommerce' ? 'product-grid' : 'grid';
        case 'product-grid':
            return 'product-grid';
        case 'testimonial-strip':
            return 'testimonials';
        case 'cta':
            return 'cta';
        case 'footer':
            return 'footer';
        case 'faq':
            return 'faq';
        case 'content':
            return 'content';
        case 'unknown':
        default:
            return 'generated';
    }
}

function buildPropsSeed(block: ImageImportVisualBlock, kind: ImageImportLayoutNodeKind, interactiveModule: ImageImportInteractiveModuleKind | null): Record<string, unknown> {
    const title = block.content.title ?? block.content.labels[0] ?? null;
    const subtitle = block.content.subtitle ?? block.content.body ?? null;
    const image = block.content.imageUrls[0] ?? null;

    switch (kind) {
        case 'header':
            return {
                logoText: title ?? 'Brand',
                menu_items: (block.content.items.length > 0 ? block.content.items : ['Home', 'About', 'Contact']).map((label, index) => ({
                    label,
                    url: index === 0 ? '/' : `/${label.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`,
                })),
                ctaText: block.content.ctaLabel,
            };
        case 'hero':
            return {
                eyebrow: block.content.eyebrow,
                title: title ?? 'Design-driven hero',
                subtitle,
                buttonText: block.content.ctaLabel ?? 'Get started',
                secondaryButtonText: block.content.secondaryCtaLabel,
                image,
            };
        case 'features':
        case 'grid':
        case 'gallery':
        case 'product-grid':
        case 'testimonials':
            return {
                title: title ?? (kind === 'testimonials' ? 'What customers say' : 'Highlights'),
                subtitle,
                items: (block.content.items.length > 0 ? block.content.items : block.content.labels).slice(0, 6).map((item, index) => ({
                    title: item,
                    description: interactiveModule === 'ecommerce'
                        ? `Image-derived item ${index + 1}`
                        : 'Imported from design reference',
                    image_url: block.content.imageUrls[index] ?? null,
                })),
            };
        case 'form':
            return {
                title: title ?? (interactiveModule === 'booking' ? 'Book a visit' : 'Get in touch'),
                subtitle,
                submitLabel: block.content.ctaLabel ?? (interactiveModule === 'newsletter' ? 'Subscribe' : 'Send'),
                formType: interactiveModule ?? 'contact_form',
                fields: interactiveModule === 'booking'
                    ? ['name', 'email', 'date', 'time']
                    : interactiveModule === 'newsletter'
                        ? ['email']
                        : ['name', 'email', 'message'],
            };
        case 'cta':
            return {
                title: title ?? 'Ready to launch?',
                subtitle,
                buttonLabel: block.content.ctaLabel ?? 'Start now',
            };
        case 'footer':
            return {
                logoText: title ?? 'Brand',
                description: subtitle ?? block.content.body,
                links: (block.content.items.length > 0 ? block.content.items : ['Privacy', 'Terms', 'Contact']).map((label) => ({
                    label,
                    url: `/${label.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`,
                })),
            };
        case 'faq':
            return {
                title: title ?? 'Frequently asked questions',
                items: (block.content.items.length > 0 ? block.content.items : ['Question one', 'Question two']).map((item) => ({
                    question: item,
                    answer: 'Answer imported from the design direction.',
                })),
            };
        case 'content':
        case 'generated':
        default:
            return {
                title: title ?? 'Imported section',
                body: subtitle ?? block.content.body ?? 'Content adapted from the uploaded reference.',
                image_url: image,
            };
    }
}

function ensureCoreSections(nodes: ImageImportLayoutNode[]): ImageImportLayoutNode[] {
    const kinds = new Set(nodes.map((node) => node.kind));
    const next = [...nodes];

    const appendIfMissing = (kind: ImageImportLayoutNodeKind, orderOffset: number, propsSeed: Record<string, unknown>) => {
        if (kinds.has(kind)) {
            return;
        }

        next.push({
            id: `inferred-${kind}`,
            kind,
            sourceBlockIds: [],
            order: next.length + orderOffset,
            sectionLabel: kind === 'header'
                ? 'Imported Header'
                : kind === 'footer'
                    ? 'Imported Footer'
                    : 'Imported CTA',
            registryHint: null,
            mode: 'reference',
            propsSeed,
            preserveHierarchy: false,
            preserveSpacing: false,
            repetition: 1,
            interactiveModule: null,
            metadata: {
                inferred: true,
            },
        });
    };

    appendIfMissing('header', 0, {
        logoText: 'Brand',
        menu_items: [{ label: 'Home', url: '/' }],
    });
    appendIfMissing('hero', 1, {
        title: 'Imported hero',
        subtitle: 'Styled after the uploaded reference.',
        buttonText: 'Get started',
    });
    appendIfMissing('cta', 2, {
        title: 'Continue with this direction',
        buttonLabel: 'Launch',
    });
    appendIfMissing('footer', 3, {
        logoText: 'Brand',
        links: [{ label: 'Contact', url: '/contact' }],
    });

    const rank: Partial<Record<ImageImportLayoutNodeKind, number>> = {
        header: -10,
        hero: -9,
        footer: 10_000,
    };

    return next.sort((left, right) => {
        const leftRank = rank[left.kind] ?? left.order;
        const rightRank = rank[right.kind] ?? right.order;
        return leftRank - rightRank;
    })
        .map((node, index) => ({
            ...node,
            order: index,
        }));
}

export function inferImageImportLayout(extraction: ImageImportDesignExtraction): ImageImportLayoutInference {
    const functionModules = inferInteractiveModules(extraction);
    const moduleByBlockId = new Map<string, ImageImportInteractiveModuleKind>();
    functionModules.forEach((signal) => {
        signal.sourceBlockIds.forEach((blockId) => {
            moduleByBlockId.set(blockId, signal.kind);
        });
    });

    let nodes = extraction.blocks
        .slice()
        .sort((left, right) => left.order - right.order)
        .map((block, index): ImageImportLayoutNode => {
            const interactiveModule = moduleByBlockId.get(block.id) ?? null;
            const kind = mapBlockToNodeKind(block, interactiveModule);
            return {
                id: `layout-node-${index + 1}`,
                kind,
                sourceBlockIds: [block.id],
                order: index,
                sectionLabel: block.content.title ?? `${kind} section`,
                registryHint: kind === 'generated' ? null : kind,
                mode: extraction.mode,
                propsSeed: buildPropsSeed(block, kind, interactiveModule),
                preserveHierarchy: extraction.mode === 'recreate' || block.layout.preserveHierarchy,
                preserveSpacing: extraction.mode === 'recreate' || block.layout.preserveSpacing,
                repetition: extraction.mode === 'recreate' && (block.layout.columns ?? 0) > 3 ? 2 : 1,
                interactiveModule,
                metadata: {
                    sourceKind: block.kind,
                    columns: block.layout.columns,
                    density: block.layout.density,
                    evidence: block.evidence,
                },
            };
        });

    if (extraction.mode === 'reference') {
        const seenCoreKinds = new Set<ImageImportLayoutNodeKind>();
        nodes = nodes.filter((node) => {
            if (node.kind !== 'header' && node.kind !== 'hero' && node.kind !== 'cta' && node.kind !== 'footer') {
                return true;
            }

            if (seenCoreKinds.has(node.kind)) {
                return false;
            }

            seenCoreKinds.add(node.kind);
            return true;
        });
    }

    nodes = ensureCoreSections(nodes);

    return {
        nodes,
        functionModules,
        warnings: extraction.mode === 'recreate'
            ? ['exact_recreation_uses_best_effort_structure']
            : ['reference_mode_preserves_direction_not_pixels'],
    };
}
