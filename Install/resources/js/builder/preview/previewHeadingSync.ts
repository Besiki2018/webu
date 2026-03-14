import type { BuilderComponentSchema } from '@/builder/componentRegistry';
import { resolveSchemaPreferredStringProp } from '@/builder/schema/schemaBindingResolver';

function isRecord(value: unknown): value is Record<string, unknown> {
    return Boolean(value && typeof value === 'object' && !Array.isArray(value));
}

function readNonEmptyString(value: unknown): string | null {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

export function normalizeLivePreviewPrimitive(value: unknown): string | null {
    if (typeof value === 'string' || typeof value === 'number') {
        const text = String(value).trim();
        return text === '' ? null : text;
    }

    return null;
}

export function normalizeLivePreviewObjectPayload(
    value: unknown,
): { label: string | null; url: string | null; alt: string | null } | null {
    if (!isRecord(value)) {
        return null;
    }

    return {
        label: normalizeLivePreviewPrimitive(value.label)
            ?? normalizeLivePreviewPrimitive(value.text)
            ?? normalizeLivePreviewPrimitive(value.title)
            ?? normalizeLivePreviewPrimitive(value.value),
        url: normalizeLivePreviewPrimitive(value.url)
            ?? normalizeLivePreviewPrimitive(value.href)
            ?? normalizeLivePreviewPrimitive(value.src)
            ?? normalizeLivePreviewPrimitive(value.image_url),
        alt: normalizeLivePreviewPrimitive(value.alt)
            ?? normalizeLivePreviewPrimitive(value.label)
            ?? normalizeLivePreviewPrimitive(value.title),
    };
}

export function flattenLivePreviewEntries(
    value: unknown,
    prefix: string[] = [],
): Array<{ key: string; value: unknown }> {
    if (Array.isArray(value)) {
        return value.flatMap((entry, index) => flattenLivePreviewEntries(entry, [...prefix, String(index)]));
    }

    if (isRecord(value)) {
        const currentKey = prefix.join('.');
        const entries = prefix.length > 0
            ? [{ key: currentKey, value }]
            : [];

        return [
            ...entries,
            ...Object.entries(value).flatMap(([childKey, childValue]) => (
                flattenLivePreviewEntries(childValue, [...prefix, childKey])
            )),
        ];
    }

    if (prefix.length === 0) {
        return [];
    }

    return [{
        key: prefix.join('.'),
        value,
    }];
}

export function applyLivePreviewValueToNode(
    node: Element,
    key: string,
    value: unknown,
    attribute: 'data-webu-field' | 'data-webu-field-url' = 'data-webu-field',
): void {
    const tagName = String((node as HTMLElement).tagName || '').toUpperCase();
    if (tagName === '') {
        return;
    }

    const objectPayload = normalizeLivePreviewObjectPayload(value);
    const primitive = normalizeLivePreviewPrimitive(value);
    const keyLower = key.toLowerCase();
    const urlLikeKey = attribute === 'data-webu-field-url' || /(url|href|link|src|image|logo)/i.test(keyLower);
    const setTextContent = (text: string) => {
        if (!(node instanceof HTMLElement)) {
            if ((node.textContent ?? '') === text) {
                return;
            }
            node.textContent = text;
            return;
        }

        const preferredTextNode = node.children.length === 1 && node.firstElementChild instanceof HTMLElement
            ? node.firstElementChild
            : node;
        if ((preferredTextNode.textContent ?? '') === text) {
            return;
        }
        preferredTextNode.textContent = text;
    };

    if (objectPayload) {
        if (tagName === 'IMG') {
            if (objectPayload.url) {
                node.setAttribute('src', objectPayload.url);
            }
            if (objectPayload.alt) {
                node.setAttribute('alt', objectPayload.alt);
            }
            return;
        }

        if (tagName === 'A') {
            if (objectPayload.url) {
                node.setAttribute('href', objectPayload.url);
            }
            if (attribute !== 'data-webu-field-url' && objectPayload.label) {
                setTextContent(objectPayload.label);
            }
            return;
        }

        if (objectPayload.label && attribute !== 'data-webu-field-url') {
            setTextContent(objectPayload.label);
        }

        if (
            objectPayload.url
            && node instanceof HTMLElement
            && /^(DIV|SECTION|ARTICLE)$/.test(tagName)
        ) {
            node.style.backgroundImage = `url("${objectPayload.url.replace(/"/g, '\\"')}")`;
        }

        return;
    }

    if (primitive === null) {
        return;
    }

    if (tagName === 'INPUT' || tagName === 'TEXTAREA') {
        if (/(placeholder)/i.test(keyLower)) {
            node.setAttribute('placeholder', primitive);
        } else if (node instanceof HTMLInputElement || node instanceof HTMLTextAreaElement) {
            node.value = primitive;
        }
        return;
    }

    if (tagName === 'IMG') {
        if (urlLikeKey) {
            node.setAttribute('src', primitive);
        } else {
            node.setAttribute('alt', primitive);
        }
        return;
    }

    if (tagName === 'A') {
        if (urlLikeKey) {
            node.setAttribute('href', primitive);
        } else {
            setTextContent(primitive);
        }
        return;
    }

    if (
        urlLikeKey
        && node instanceof HTMLElement
        && /^(DIV|SECTION|ARTICLE)$/.test(tagName)
    ) {
        node.style.backgroundImage = `url("${primitive.replace(/"/g, '\\"')}")`;
        return;
    }

    if (attribute !== 'data-webu-field-url') {
        setTextContent(primitive);
    }
}

export function isLivePreviewRenderableNode(node: Element): boolean {
    if (!(node instanceof HTMLElement)) {
        return true;
    }

    const ownerWindow = node.ownerDocument?.defaultView ?? null;
    const fallbackWindow = typeof window !== 'undefined' ? window : null;
    const style = ownerWindow?.getComputedStyle(node) ?? fallbackWindow?.getComputedStyle(node);
    const rect = node.getBoundingClientRect();
    const userAgent = ownerWindow?.navigator?.userAgent ?? '';
    const isJsdom = ownerWindow === null || /jsdom/i.test(userAgent);

    return !node.hidden
        && node.getAttribute('aria-hidden') !== 'true'
        && style?.display !== 'none'
        && style?.visibility !== 'hidden'
        && (isJsdom || rect.width > 0 || rect.height > 0);
}

export function applyLivePreviewFieldByKey(container: Element, key: string, value: unknown): void {
    const safeKey = key.trim();
    if (safeKey === '') {
        return;
    }

    const encodedKey = safeKey.replace(/"/g, '\\"');
    const exactFieldNodes = Array.from(container.querySelectorAll(`[data-webu-field="${encodedKey}"]`));
    const exactUrlNodes = Array.from(container.querySelectorAll(`[data-webu-field-url="${encodedKey}"]`));

    if (exactFieldNodes.length > 0 || exactUrlNodes.length > 0) {
        exactFieldNodes.forEach((node) => applyLivePreviewValueToNode(node, safeKey, value, 'data-webu-field'));
        exactUrlNodes.forEach((node) => applyLivePreviewValueToNode(node, safeKey, value, 'data-webu-field-url'));

        const hasVisibleExactNode = [...exactFieldNodes, ...exactUrlNodes].some((node) => isLivePreviewRenderableNode(node));
        if (hasVisibleExactNode) {
            return;
        }
    }

    const keyLower = safeKey.toLowerCase();
    let fallbackSelectors: string[] = [];

    if (['headline', 'title', 'heading'].includes(keyLower)) {
        fallbackSelectors = ['h1', 'h2', 'h3'];
    } else if (['subtitle', 'body', 'description', 'text'].includes(keyLower)) {
        fallbackSelectors = ['p'];
    } else if (/(cta|button|link)/.test(keyLower)) {
        fallbackSelectors = ['a.btn', 'a.button', 'button', 'a'];
    } else if (/(image|logo|thumbnail|photo)/.test(keyLower)) {
        fallbackSelectors = ['img'];
    }

    if (fallbackSelectors.length === 0) {
        return;
    }

    const fallbackNode = Array.from(container.querySelectorAll(fallbackSelectors.join(', ')))
        .find((node) => isLivePreviewRenderableNode(node));
    if (fallbackNode) {
        applyLivePreviewValueToNode(fallbackNode, safeKey, value, 'data-webu-field');
    }
}

export function reconcileLivePreviewHeading(
    container: Element,
    props: Record<string, unknown>,
    previewText: string,
    schema: BuilderComponentSchema | null,
): string | null {
    const preferredHeading = resolveSchemaPreferredStringProp(schema, props, ['title', 'headline', 'heading'])
        ?? normalizeLivePreviewPrimitive(previewText);
    if (!preferredHeading) {
        return null;
    }

    const exactHeadingNodes = ['title', 'headline', 'heading'].flatMap((key) => (
        Array.from(container.querySelectorAll(`[data-webu-field="${key}"]`))
    ));

    exactHeadingNodes.forEach((node) => {
        applyLivePreviewValueToNode(node, 'title', preferredHeading, 'data-webu-field');
    });

    if (exactHeadingNodes.some((node) => isLivePreviewRenderableNode(node))) {
        return preferredHeading;
    }

    const visibleHeading = Array.from(
        container.querySelectorAll('h1:not([data-webu-field]), h2:not([data-webu-field]), h3:not([data-webu-field])')
    ).find((node) => isLivePreviewRenderableNode(node))
        ?? Array.from(container.querySelectorAll('h1, h2, h3'))
            .find((node) => isLivePreviewRenderableNode(node));
    if (visibleHeading) {
        if (!visibleHeading.getAttribute('data-webu-field')) {
            visibleHeading.setAttribute('data-webu-field', 'title');
            visibleHeading.setAttribute('data-webu-field-source', 'inferred');
        }
        applyLivePreviewValueToNode(visibleHeading, 'title', preferredHeading, 'data-webu-field');
    }

    return preferredHeading;
}

export function syncVisiblePreviewHeading(container: Element, text: string): void {
    const visibleHeading = container.querySelector<HTMLElement>(
        'h1:not([data-webu-field]), h2:not([data-webu-field]), h3:not([data-webu-field])'
    );
    if (visibleHeading && visibleHeading.textContent?.trim() !== text) {
        visibleHeading.textContent = text;
    }
}
