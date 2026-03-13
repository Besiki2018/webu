import { resolveSchemaPreferredStringProp } from '@/builder/schema/schemaBindingResolver';

import type { LivePreviewStructureItem } from './previewRenderSync';
import { normalizeLivePreviewPrimitive } from './previewHeadingSync';

interface PlaceholderResolvedProp {
    path: string;
    value: string;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return Boolean(value && typeof value === 'object' && !Array.isArray(value));
}

function readNonEmptyString(value: unknown): string | null {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

export function resolvePlaceholderProp(
    props: Record<string, unknown>,
    candidates: string[],
    nestedCandidates: string[] = [],
): PlaceholderResolvedProp | null {
    for (const candidate of candidates) {
        const direct = readNonEmptyString(props[candidate]);
        if (direct) {
            return {
                path: candidate,
                value: direct,
            };
        }

        const nestedSource = props[candidate];
        if (!isRecord(nestedSource)) {
            continue;
        }

        for (const nestedCandidate of nestedCandidates) {
            const nestedValue = readNonEmptyString(nestedSource[nestedCandidate]);
            if (nestedValue) {
                return {
                    path: `${candidate}.${nestedCandidate}`,
                    value: nestedValue,
                };
            }
        }
    }

    return null;
}

export function setPlaceholderFieldAttribute(
    node: HTMLElement,
    attribute: 'data-webu-field' | 'data-webu-field-url',
    value: string | null,
): void {
    if (value) {
        node.setAttribute(attribute, value);
        return;
    }

    node.removeAttribute(attribute);
}

export function isPreviewPlaceholderSection(node: Element | null): boolean {
    return node instanceof HTMLElement && node.getAttribute('data-webu-chat-placeholder') === 'true';
}

export function syncPreviewPlaceholderSection(
    section: HTMLElement,
    item: LivePreviewStructureItem,
    translate: (key: string, fallback: string) => string,
): void {
    const headline = resolvePlaceholderProp(item.props, [
        'title',
        'headline',
        'heading',
        'name',
    ]);
    const subtitle = resolvePlaceholderProp(item.props, [
        'subtitle',
        'description',
        'body',
        'text',
    ]);
    const primaryActionLabel = resolvePlaceholderProp(item.props, [
        'buttonText',
        'buttonLabel',
        'button',
        'ctaLabel',
        'ctaText',
        'primaryCtaLabel',
    ], ['label', 'text', 'title']);
    const primaryActionUrl = resolvePlaceholderProp(item.props, [
        'buttonLink',
        'buttonUrl',
        'button_url',
        'ctaUrl',
        'ctaLink',
        'primaryCtaUrl',
        'primaryCtaLink',
    ], ['url', 'href', 'link']);
    const image = resolvePlaceholderProp(item.props, [
        'image',
        'imageUrl',
        'image_url',
        'image_01',
        'hero_image',
        'backgroundImage',
        'logo_url',
    ], ['url', 'src', 'image']);
    const imageAlt = resolvePlaceholderProp(item.props, [
        'imageAlt',
        'image_alt',
        'alt',
        'imageAltFallback',
    ]);
    const bodyText = headline?.value
        || item.previewText
        || resolveSchemaPreferredStringProp(null, item.props, ['title', 'headline', 'subtitle', 'description'])
        || item.label
        || item.sectionKey;

    section.setAttribute('data-webu-section', item.sectionKey);
    section.setAttribute('data-webu-section-local-id', item.localId);
    section.setAttribute('data-webu-chat-placeholder', 'true');
    section.style.minHeight = '132px';
    section.style.margin = '16px 0';
    section.style.border = '1px dashed rgba(37, 99, 235, 0.45)';
    section.style.borderRadius = '20px';
    section.style.background = 'linear-gradient(180deg, rgba(248,250,252,0.98) 0%, rgba(239,246,255,0.98) 100%)';
    section.style.boxShadow = '0 10px 30px rgba(15, 23, 42, 0.05)';
    section.style.padding = '24px';
    section.style.display = 'flex';
    section.style.flexDirection = 'column';
    section.style.justifyContent = 'center';
    section.style.gap = '10px';

    const ensureChild = (selector: string, dataAttr: string) => {
        const existing = section.querySelector<HTMLElement>(selector);
        if (existing) {
            return existing;
        }

        const child = section.ownerDocument.createElement('div');
        child.setAttribute(dataAttr, 'true');
        section.appendChild(child);
        return child;
    };

    const badge = ensureChild('[data-webu-chat-placeholder-badge="true"]', 'data-webu-chat-placeholder-badge');
    badge.textContent = item.label || item.sectionKey;
    badge.style.font = '600 12px/1.2 system-ui, sans-serif';
    badge.style.color = '#1d4ed8';
    badge.style.letterSpacing = '0.02em';
    badge.style.textTransform = 'uppercase';

    const body = ensureChild('[data-webu-chat-placeholder-body="true"]', 'data-webu-chat-placeholder-body');
    body.textContent = bodyText;
    setPlaceholderFieldAttribute(body, 'data-webu-field', headline?.path ?? null);
    body.style.font = '500 18px/1.55 system-ui, sans-serif';
    body.style.color = '#0f172a';

    const subtitleNode = subtitle
        ? ensureChild('[data-webu-chat-placeholder-subtitle="true"]', 'data-webu-chat-placeholder-subtitle')
        : section.querySelector<HTMLElement>('[data-webu-chat-placeholder-subtitle="true"]');
    if (subtitle && subtitleNode) {
        subtitleNode.textContent = subtitle.value;
        setPlaceholderFieldAttribute(subtitleNode, 'data-webu-field', subtitle.path);
        subtitleNode.style.font = '400 14px/1.55 system-ui, sans-serif';
        subtitleNode.style.color = '#475569';
    } else {
        subtitleNode?.remove();
    }

    const actionNode = (primaryActionLabel || primaryActionUrl)
        ? ensureChild('[data-webu-chat-placeholder-action="true"]', 'data-webu-chat-placeholder-action')
        : section.querySelector<HTMLElement>('[data-webu-chat-placeholder-action="true"]');
    if (actionNode && (primaryActionLabel || primaryActionUrl)) {
        const actionLabel = primaryActionLabel?.value ?? translate('Open link', 'ბმულის გახსნა');
        const actionUrl = primaryActionUrl?.value ?? '';
        actionNode.textContent = actionUrl ? `${actionLabel} -> ${actionUrl}` : actionLabel;
        setPlaceholderFieldAttribute(actionNode, 'data-webu-field', primaryActionLabel?.path ?? null);
        setPlaceholderFieldAttribute(actionNode, 'data-webu-field-url', primaryActionUrl?.path ?? null);
        actionNode.style.display = 'inline-flex';
        actionNode.style.alignItems = 'center';
        actionNode.style.width = 'fit-content';
        actionNode.style.minHeight = '36px';
        actionNode.style.padding = '0 14px';
        actionNode.style.borderRadius = '999px';
        actionNode.style.border = '1px solid rgba(29, 78, 216, 0.18)';
        actionNode.style.background = 'rgba(255, 255, 255, 0.88)';
        actionNode.style.color = '#1e293b';
        actionNode.style.font = '600 13px/1.35 system-ui, sans-serif';
        actionNode.style.whiteSpace = 'nowrap';
        actionNode.style.overflow = 'hidden';
        actionNode.style.textOverflow = 'ellipsis';
        actionNode.style.maxWidth = '100%';
    } else {
        actionNode?.remove();
    }

    const imageNode = image
        ? (section.querySelector<HTMLImageElement>('[data-webu-chat-placeholder-image="true"]') ?? section.ownerDocument.createElement('img'))
        : section.querySelector<HTMLImageElement>('[data-webu-chat-placeholder-image="true"]');
    if (image && imageNode) {
        imageNode.setAttribute('data-webu-chat-placeholder-image', 'true');
        imageNode.setAttribute('src', image.value);
        imageNode.setAttribute('alt', imageAlt?.value ?? headline?.value ?? item.label ?? item.sectionKey);
        setPlaceholderFieldAttribute(imageNode, 'data-webu-field', image.path);
        imageNode.style.width = '100%';
        imageNode.style.maxWidth = '280px';
        imageNode.style.height = '120px';
        imageNode.style.objectFit = 'cover';
        imageNode.style.borderRadius = '14px';
        imageNode.style.border = '1px solid rgba(148, 163, 184, 0.32)';
        imageNode.style.background = '#e2e8f0';
        imageNode.style.boxShadow = '0 6px 18px rgba(15, 23, 42, 0.08)';
        if (!imageNode.isConnected) {
            section.appendChild(imageNode);
        }
    } else {
        imageNode?.remove();
    }

    const caption = ensureChild('[data-webu-chat-placeholder-caption="true"]', 'data-webu-chat-placeholder-caption');
    caption.textContent = translate('Component is being added...', 'კომპონენტი ემატება...');
    caption.style.font = '400 13px/1.45 system-ui, sans-serif';
    caption.style.color = '#475569';
}

export function createPreviewPlaceholderSection(
    item: LivePreviewStructureItem,
    doc: Document,
    translate: (key: string, fallback: string) => string,
): HTMLElement {
    const section = doc.createElement('section');
    syncPreviewPlaceholderSection(section, item, translate);
    return section;
}

export function reconcilePreviewPlaceholderNodes(options: {
    iframeDoc: Document;
    liveStructureItems: LivePreviewStructureItem[];
    syncPlaceholderSection: (section: HTMLElement, item: LivePreviewStructureItem) => void;
    createPlaceholderSection: (item: LivePreviewStructureItem, doc: Document) => HTMLElement;
}): void {
    const {
        iframeDoc,
        liveStructureItems,
        syncPlaceholderSection,
        createPlaceholderSection,
    } = options;
    const expectedItemById = new Map(
        liveStructureItems
            .map((item) => [item.localId.trim(), item] as const)
            .filter(([localId]) => localId !== '')
    );
    const expectedIds = new Set(
        Array.from(expectedItemById.keys())
    );
    const placeholderNodesById = new Map<string, HTMLElement[]>();

    iframeDoc
        .querySelectorAll<HTMLElement>('[data-webu-chat-placeholder="true"]')
        .forEach((node) => {
            const localId = (node.getAttribute('data-webu-section-local-id') ?? '').trim();
            if (localId === '' || !expectedIds.has(localId)) {
                node.remove();
                return;
            }

            const realNode = iframeDoc.querySelector<HTMLElement>(
                `[data-webu-section-local-id="${localId.replace(/"/g, '\\"')}"]:not([data-webu-chat-placeholder="true"])`
            );
            if (realNode) {
                node.remove();
                return;
            }

            const existing = placeholderNodesById.get(localId) ?? [];
            existing.push(node);
            placeholderNodesById.set(localId, existing);
        });

    placeholderNodesById.forEach((nodes, localId) => {
        if (nodes.length === 0) {
            return;
        }

        const expectedItem = expectedItemById.get(localId) ?? null;
        nodes.forEach((node, index) => {
            if (index > 0) {
                node.remove();
                return;
            }

            if (expectedItem) {
                syncPlaceholderSection(node, expectedItem);
            }
        });
    });

    iframeDoc
        .querySelectorAll<HTMLElement>('[data-webu-section-local-id]')
        .forEach((node) => {
            const localId = (node.getAttribute('data-webu-section-local-id') ?? '').trim();
            const isPlaceholder = node.getAttribute('data-webu-chat-placeholder') === 'true';
            if (isPlaceholder) {
                return;
            }

            if (localId !== '' && expectedIds.size > 0 && !expectedIds.has(localId)) {
                node.style.setProperty('display', 'none', 'important');
                node.setAttribute('data-webu-chat-optimistic-hidden', 'true');
                return;
            }

            if (node.hasAttribute('data-webu-chat-optimistic-hidden')) {
                node.style.removeProperty('display');
                node.removeAttribute('data-webu-chat-optimistic-hidden');
            }
        });

    if (expectedIds.size === 0) {
        return;
    }

    const resolveAnchorContainer = (): HTMLElement | null => {
        const firstReal = iframeDoc.querySelector<HTMLElement>('[data-webu-section-local-id]:not([data-webu-chat-placeholder="true"])');
        if (firstReal?.parentElement) {
            return firstReal.parentElement;
        }

        return iframeDoc.querySelector<HTMLElement>('.main_content')
            ?? iframeDoc.querySelector<HTMLElement>('main')
            ?? iframeDoc.body;
    };

    const anchorContainer = resolveAnchorContainer();
    if (!anchorContainer) {
        return;
    }

    liveStructureItems.forEach((item, index) => {
        const localId = item.localId.trim();
        if (localId === '') {
            return;
        }

        const existingRealNode = iframeDoc.querySelector<HTMLElement>(
            `[data-webu-section-local-id="${localId.replace(/"/g, '\\"')}"]:not([data-webu-chat-placeholder="true"])`
        );
        const existingPlaceholder = iframeDoc.querySelector<HTMLElement>(
            `[data-webu-section-local-id="${localId.replace(/"/g, '\\"')}"][data-webu-chat-placeholder="true"]`
        );
        if (existingRealNode) {
            existingPlaceholder?.remove();
            return;
        }

        const placeholder = existingPlaceholder ?? createPlaceholderSection(item, iframeDoc);
        syncPlaceholderSection(placeholder, item);

        const nextSibling = liveStructureItems
            .slice(index + 1)
            .map((candidate) => (
                iframeDoc.querySelector<HTMLElement>(`[data-webu-section-local-id="${candidate.localId.replace(/"/g, '\\"')}"]`)
            ))
            .find((node): node is HTMLElement => Boolean(node));

        if (nextSibling?.parentElement) {
            nextSibling.parentElement.insertBefore(placeholder, nextSibling);
            return;
        }

        const previousSibling = [...liveStructureItems]
            .slice(0, index)
            .reverse()
            .map((candidate) => (
                iframeDoc.querySelector<HTMLElement>(`[data-webu-section-local-id="${candidate.localId.replace(/"/g, '\\"')}"]`)
            ))
            .find((node): node is HTMLElement => Boolean(node));

        if (previousSibling?.parentElement) {
            previousSibling.parentElement.insertBefore(placeholder, previousSibling.nextSibling);
            return;
        }

        anchorContainer.appendChild(placeholder);
    });
}
