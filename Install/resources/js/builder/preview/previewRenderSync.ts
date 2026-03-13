import { getComponentSchema } from '@/builder/componentRegistry';
import { resolveSchemaPreferredStringProp } from '@/builder/schema/schemaBindingResolver';

import {
    applyLivePreviewFieldByKey,
    flattenLivePreviewEntries,
    normalizeLivePreviewObjectPayload,
    normalizeLivePreviewPrimitive,
    reconcileLivePreviewHeading,
} from './previewHeadingSync';

function isRecord(value: unknown): value is Record<string, unknown> {
    return Boolean(value && typeof value === 'object' && !Array.isArray(value));
}

export interface LivePreviewStructureItem {
    localId: string;
    sectionKey: string;
    label: string;
    previewText: string;
    props: Record<string, unknown>;
}

export function normalizeLivePreviewSectionProps(item: LivePreviewStructureItem): Record<string, unknown> {
    const next = { ...item.props };
    const schema = getComponentSchema(item.sectionKey);
    const preferredHeading = resolveSchemaPreferredStringProp(schema, next, ['title', 'headline', 'heading']);
    if (preferredHeading) {
        next.title = preferredHeading;
        next.headline = preferredHeading;
    }

    const primaryButtonText = normalizeLivePreviewPrimitive(next.buttonText)
        ?? normalizeLivePreviewPrimitive(next.ctaText)
        ?? normalizeLivePreviewPrimitive(next.ctaLabel)
        ?? normalizeLivePreviewObjectPayload(next.buttonLink)?.label
        ?? normalizeLivePreviewObjectPayload(next.primary_cta)?.label;
    const primaryButtonUrl = normalizeLivePreviewPrimitive(next.buttonLink)
        ?? normalizeLivePreviewPrimitive(next.ctaLink)
        ?? normalizeLivePreviewPrimitive(next.ctaUrl)
        ?? normalizeLivePreviewObjectPayload(next.buttonLink)?.url
        ?? normalizeLivePreviewObjectPayload(next.primary_cta)?.url;
    if (primaryButtonText || primaryButtonUrl) {
        const existingPrimary = isRecord(next.primary_cta) ? next.primary_cta : {};
        next.primary_cta = {
            ...existingPrimary,
            text: primaryButtonText ?? normalizeLivePreviewPrimitive(existingPrimary.text),
            label: primaryButtonText ?? normalizeLivePreviewPrimitive(existingPrimary.label),
            title: primaryButtonText ?? normalizeLivePreviewPrimitive(existingPrimary.title),
            url: primaryButtonUrl ?? normalizeLivePreviewPrimitive(existingPrimary.url),
            href: primaryButtonUrl ?? normalizeLivePreviewPrimitive(existingPrimary.href),
        };
    }

    const secondaryButtonText = normalizeLivePreviewPrimitive(next.secondaryButtonText)
        ?? normalizeLivePreviewObjectPayload(next.secondaryButtonLink)?.label
        ?? normalizeLivePreviewObjectPayload(next.secondary_cta)?.label;
    const secondaryButtonUrl = normalizeLivePreviewPrimitive(next.secondaryButtonLink)
        ?? normalizeLivePreviewObjectPayload(next.secondaryButtonLink)?.url
        ?? normalizeLivePreviewObjectPayload(next.secondary_cta)?.url;
    if (secondaryButtonText || secondaryButtonUrl) {
        const existingSecondary = isRecord(next.secondary_cta) ? next.secondary_cta : {};
        next.secondary_cta = {
            ...existingSecondary,
            text: secondaryButtonText ?? normalizeLivePreviewPrimitive(existingSecondary.text),
            label: secondaryButtonText ?? normalizeLivePreviewPrimitive(existingSecondary.label),
            title: secondaryButtonText ?? normalizeLivePreviewPrimitive(existingSecondary.title),
            url: secondaryButtonUrl ?? normalizeLivePreviewPrimitive(existingSecondary.url),
            href: secondaryButtonUrl ?? normalizeLivePreviewPrimitive(existingSecondary.href),
        };
    }

    const preferredImage = normalizeLivePreviewPrimitive(next.image)
        ?? normalizeLivePreviewPrimitive(next.backgroundImage)
        ?? normalizeLivePreviewPrimitive(next.background_image);
    if (preferredImage) {
        next.image = preferredImage;
        next.backgroundImage = preferredImage;
        next.background_image = preferredImage;
    }

    return next;
}

export function syncLivePreviewSection(
    container: Element,
    item: LivePreviewStructureItem,
): string | null {
    const normalizedProps = normalizeLivePreviewSectionProps(item);
    const flattenedEntries = flattenLivePreviewEntries(normalizedProps);
    flattenedEntries.forEach((entry) => {
        applyLivePreviewFieldByKey(container, entry.key, entry.value);
    });

    const preferredHeading = reconcileLivePreviewHeading(
        container,
        normalizedProps,
        item.previewText,
        getComponentSchema(item.sectionKey),
    ) ?? normalizeLivePreviewPrimitive(item.previewText);

    if (flattenedEntries.length === 0 && item.previewText.trim() !== '') {
        const titleNode = container.querySelector('h1, h2, h3, p');
        if (titleNode) {
            titleNode.textContent = item.previewText;
        }
    }

    return preferredHeading;
}
