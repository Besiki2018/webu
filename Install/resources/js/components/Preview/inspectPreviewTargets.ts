const LAYOUT_PRIMITIVE_SECTION_KEYS = new Set(['container', 'grid', 'section']);

export type InspectPreviewDropPlacement = 'before' | 'after' | 'inside';

function isElementNode(value: unknown): value is Element {
    return Boolean(value)
        && typeof value === 'object'
        && 'nodeType' in (value as Node)
        && (value as Node).nodeType === 1;
}

function isInspectableElement(value: unknown): value is HTMLElement {
    return isElementNode(value) && typeof (value as Element).closest === 'function';
}

function resolvePointInIframe(
    iframe: HTMLIFrameElement,
    scale: number,
    clientX: number,
    clientY: number,
): { doc: Document; localX: number; localY: number; docX: number; docY: number; iframeRect: DOMRect } | null {
    const doc = iframe.contentDocument;
    if (!doc || typeof doc.elementFromPoint !== 'function') {
        return null;
    }

    const iframeRect = iframe.getBoundingClientRect();
    const localX = clientX - iframeRect.left;
    const localY = clientY - iframeRect.top;
    const docX = scale > 0 ? localX / scale : localX;
    const docY = scale > 0 ? localY / scale : localY;

    return {
        doc,
        localX,
        localY,
        docX,
        docY,
        iframeRect,
    };
}

/**
 * Prefer the most specific (nearest/smallest) editable target.
 * - data-webu-field / data-webu-field-url = leaf field (most specific)
 * - data-webu-field-scope = repeater/menu item scope (use deepest when no leaf)
 */
export function resolveComponentInspectableTarget(target: Element | null): HTMLElement | null {
    if (!isInspectableElement(target)) {
        return null;
    }

    // 1. Prefer exact field target (leaf) — always more specific than scope
    const exactFieldTarget = target.closest<HTMLElement>('[data-webu-field], [data-webu-field-url]');
    if (exactFieldTarget) {
        return exactFieldTarget;
    }

    // 2. Fall back to scope — use the deepest (most specific) scope
    const scopeCandidates: HTMLElement[] = [];
    let scopeCursor: HTMLElement | null = target;
    while (scopeCursor) {
        if (scopeCursor.hasAttribute('data-webu-field-scope')) {
            scopeCandidates.push(scopeCursor);
        }
        scopeCursor = scopeCursor.parentElement;
    }

    if (scopeCandidates.length > 0) {
        return [...scopeCandidates].sort((left, right) => {
            const leftPath = (left.getAttribute('data-webu-field-scope') ?? '').trim();
            const rightPath = (right.getAttribute('data-webu-field-scope') ?? '').trim();
            const leftDepth = leftPath === '' ? 0 : leftPath.split('.').filter(Boolean).length;
            const rightDepth = rightPath === '' ? 0 : rightPath.split('.').filter(Boolean).length;
            return rightDepth - leftDepth;
        })[0] ?? null;
    }

    return null;
}

export function resolveTopIframeElementAtPoint(
    iframe: HTMLIFrameElement | null,
    scale: number,
    clientX: number,
    clientY: number,
): Element | null {
    if (!iframe) {
        return null;
    }

    const point = resolvePointInIframe(iframe, scale, clientX, clientY);
    if (!point) {
        return null;
    }

    if (point.localX < 0 || point.localY < 0 || point.localX > point.iframeRect.width || point.localY > point.iframeRect.height) {
        return null;
    }

    if (typeof point.doc.elementsFromPoint === 'function') {
        const stack = point.doc.elementsFromPoint(point.docX, point.docY);
        const topElement = stack.find((entry): entry is Element => isElementNode(entry));
        if (topElement) {
            return topElement;
        }
    }

    const element = point.doc.elementFromPoint(point.docX, point.docY);
    return isElementNode(element) ? element : null;
}

export function resolveSectionTarget(
    iframe: HTMLIFrameElement | null,
    scale: number,
    target: EventTarget | null,
    clientX?: number,
    clientY?: number,
): HTMLElement | null {
    if (!iframe?.contentDocument) {
        return null;
    }

    if (typeof clientX === 'number' && typeof clientY === 'number') {
        const point = resolvePointInIframe(iframe, scale, clientX, clientY);
        if (!point) {
            return null;
        }

        const tryFindSection = (x: number, y: number): HTMLElement | null => {
            if (typeof point.doc.elementsFromPoint === 'function') {
                const stack = point.doc.elementsFromPoint(x, y);
                for (const node of stack) {
                    if (!isElementNode(node)) continue;
                    const section = node.closest<HTMLElement>('[data-webu-section]');
                    if (section) return section;
                }
            }

            const direct = point.doc.elementFromPoint(x, y);
            return isElementNode(direct) ? direct.closest<HTMLElement>('[data-webu-section]') : null;
        };

        if (point.localX >= 0 && point.localY >= 0 && point.localX <= point.iframeRect.width && point.localY <= point.iframeRect.height) {
            const section = tryFindSection(point.docX, point.docY);
            if (section) return section;

            const allSections = Array.from(point.doc.querySelectorAll<HTMLElement>('[data-webu-section]'));
            const containing = allSections.filter((entry) => {
                const rect = entry.getBoundingClientRect();
                return rect.width > 0 && rect.height > 0 && point.docX >= rect.left && point.docX <= rect.right && point.docY >= rect.top && point.docY <= rect.bottom;
            });

            if (containing.length > 0) {
                containing.sort((left, right) => {
                    const leftRect = left.getBoundingClientRect();
                    const rightRect = right.getBoundingClientRect();
                    return leftRect.width * leftRect.height - rightRect.width * rightRect.height;
                });
                return containing[0] ?? null;
            }
        }
    }

    return isElementNode(target) ? target.closest<HTMLElement>('[data-webu-section]') : null;
}

export function resolveSectionOnlyFallbackTarget(
    iframe: HTMLIFrameElement | null,
    scale: number,
    options: {
        target: EventTarget | null;
        clientX?: number;
        clientY?: number;
    },
): HTMLElement | null {
    const section = resolveSectionTarget(iframe, scale, options.target, options.clientX, options.clientY);
    if (!section) {
        return null;
    }

    const exactTarget = typeof options.clientX === 'number' && typeof options.clientY === 'number'
        ? resolveElementAtPoint(iframe, scale, options.clientX, options.clientY)
        : resolveComponentInspectableTarget(isElementNode(options.target) ? options.target : null);
    if (exactTarget) {
        return null;
    }
    return section;
}

/**
 * Resolve the most specific editable element at the given point.
 * Prefers leaf fields (data-webu-field) over scopes (data-webu-field-scope).
 * Returns the smallest/most specific target, never a wrapper when a child is hittable.
 */
export function resolveElementAtPoint(
    iframe: HTMLIFrameElement | null,
    scale: number,
    clientX: number,
    clientY: number,
): Element | null {
    if (!iframe) {
        return null;
    }

    const point = resolvePointInIframe(iframe, scale, clientX, clientY);
    if (!point) {
        return null;
    }

    if (point.localX < 0 || point.localY < 0 || point.localX > point.iframeRect.width || point.localY > point.iframeRect.height) {
        return null;
    }

    if (typeof point.doc.elementsFromPoint === 'function') {
        const stack = point.doc.elementsFromPoint(point.docX, point.docY);
        const candidates: HTMLElement[] = [];
        for (const candidate of stack) {
            const resolvedTarget = resolveComponentInspectableTarget(isElementNode(candidate) ? candidate : null);
            if (resolvedTarget) {
                candidates.push(resolvedTarget);
            }
        }
        if (candidates.length > 0) {
            return pickMostSpecificEditableTarget(candidates);
        }
    }

    const element = point.doc.elementFromPoint(point.docX, point.docY);
    const resolvedTarget = isElementNode(element) ? resolveComponentInspectableTarget(element) : null;
    return resolvedTarget;
}

/**
 * From multiple editable targets, pick the most specific (smallest / deepest path).
 * Prefer leaf fields over scopes; prefer longer paths over shorter.
 */
function pickMostSpecificEditableTarget(candidates: HTMLElement[]): HTMLElement | null {
    if (candidates.length === 0) return null;
    if (candidates.length === 1) return candidates[0]!;

    const scored = candidates.map((el) => {
        const fieldPath = (el.getAttribute('data-webu-field') ?? el.getAttribute('data-webu-field-url') ?? '').trim();
        const scopePath = (el.getAttribute('data-webu-field-scope') ?? '').trim();
        const path = fieldPath || scopePath;
        const pathDepth = path === '' ? 0 : path.split('.').filter(Boolean).length;
        const isLeafField = fieldPath !== '';
        const rect = el.getBoundingClientRect();
        const area = rect.width * rect.height;
        return {
            el,
            pathDepth,
            isLeafField,
            area,
        };
    });

    scored.sort((a, b) => {
        if (a.isLeafField !== b.isLeafField) return a.isLeafField ? -1 : 1;
        if (a.pathDepth !== b.pathDepth) return b.pathDepth - a.pathDepth;
        return a.area - b.area;
    });

    return scored[0]!.el;
}

export function resolvePlacementTarget(
    iframe: HTMLIFrameElement | null,
    scale: number,
    target: EventTarget | null,
    clientX: number,
    clientY: number,
): { section: HTMLElement; placement: InspectPreviewDropPlacement } | null {
    if (!iframe?.contentDocument) {
        return null;
    }

    const point = resolvePointInIframe(iframe, scale, clientX, clientY);
    if (!point) {
        return null;
    }

    const computePlacement = (section: HTMLElement): InspectPreviewDropPlacement => {
        const sectionRect = section.getBoundingClientRect();
        const normalizedKey = (section.getAttribute('data-webu-section') || '').trim().toLowerCase();
        const canDropInside = LAYOUT_PRIMITIVE_SECTION_KEYS.has(normalizedKey);
        const topThreshold = sectionRect.top + Math.max(18, sectionRect.height * 0.24);
        const bottomThreshold = sectionRect.bottom - Math.max(18, sectionRect.height * 0.24);

        if (canDropInside && point.docY >= topThreshold && point.docY <= bottomThreshold) {
            return 'inside';
        }

        return point.docY < sectionRect.top + (sectionRect.height / 2) ? 'before' : 'after';
    };

    const directSection = resolveSectionTarget(iframe, scale, target, clientX, clientY);
    if (directSection) {
        return {
            section: directSection,
            placement: computePlacement(directSection),
        };
    }

    const allSections = Array.from(iframe.contentDocument.querySelectorAll<HTMLElement>('[data-webu-section]'))
        .filter((section) => {
            const rect = section.getBoundingClientRect();
            return rect.width > 0 && rect.height > 0;
        });

    if (allSections.length === 0) {
        return null;
    }

    const containing = allSections.filter((entry) => {
        const rect = entry.getBoundingClientRect();
        return point.docX >= rect.left && point.docX <= rect.right && point.docY >= rect.top && point.docY <= rect.bottom;
    });
    if (containing.length > 0) {
        containing.sort((left, right) => {
            const leftRect = left.getBoundingClientRect();
            const rightRect = right.getBoundingClientRect();
            return leftRect.width * leftRect.height - rightRect.width * rightRect.height;
        });
        return { section: containing[0]!, placement: computePlacement(containing[0]!) };
    }

    let bestSection: HTMLElement | null = null;
    let bestDistance = Number.POSITIVE_INFINITY;
    allSections.forEach((section) => {
        const rect = section.getBoundingClientRect();
        let distance = 0;
        if (point.docY < rect.top) distance = rect.top - point.docY;
        else if (point.docY > rect.bottom) distance = point.docY - rect.bottom;
        const horizontalPenalty = point.docX < rect.left ? rect.left - point.docX : point.docX > rect.right ? point.docX - rect.right : 0;
        distance += horizontalPenalty * 0.35;
        if (distance < bestDistance) {
            bestDistance = distance;
            bestSection = section;
        }
    });

    if (!bestSection) {
        return null;
    }

    return {
        section: bestSection,
        placement: computePlacement(bestSection),
    };
}
