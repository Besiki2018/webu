import { buildElementId } from '@/builder/componentParameterMetadata';
import { buildTargetId } from '@/builder/editingState';
import {
    buildDOMMapCached,
    getSectionAtPoint,
    type DOMMap,
    type MappedElement,
} from '@/builder/domMapper';

const LAYOUT_PRIMITIVE_SECTION_KEYS = new Set(['container', 'grid', 'section']);

export type InspectPreviewDropPlacement = 'before' | 'after' | 'inside';
export type PreviewTargetAttribute = 'data-webu-field' | 'data-webu-field-url' | 'data-webu-field-scope' | null;
export type PreviewTargetKind = 'field' | 'scope' | 'section';
export type PreviewTargetResolutionStatus = 'resolved' | 'missing-backing-node' | 'none';

export interface ResolvedPreviewTarget {
    node: HTMLElement;
    section: HTMLElement;
    kind: PreviewTargetKind;
    attribute: PreviewTargetAttribute;
    sectionLocalId: string | null;
    sectionKey: string | null;
    parameterPath: string | null;
    componentPath: string | null;
    selector: string;
    targetId: string;
    elementId: string | null;
}

export interface PreviewTargetResolution {
    status: PreviewTargetResolutionStatus;
    target: ResolvedPreviewTarget | null;
    reason: 'mapped-element' | 'nearest-editable-ancestor' | 'section-fallback' | 'root-canvas-fallback' | 'missing-backing-node' | 'none';
}

function isElementNode(value: unknown): value is Element {
    return Boolean(value)
        && typeof value === 'object'
        && 'nodeType' in (value as Node)
        && (value as Node).nodeType === 1;
}

function isInspectableElement(value: unknown): value is HTMLElement {
    return isElementNode(value) && typeof (value as Element).closest === 'function';
}

function readTrimmedAttribute(element: Element | null | undefined, name: string): string | null {
    const value = element?.getAttribute(name);
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

function deriveComponentPath(
    parameterPath: string | null,
    scopePath: string | null,
): string | null {
    if (!scopePath) {
        return parameterPath ?? null;
    }

    if (!parameterPath || !parameterPath.startsWith(`${scopePath}.`)) {
        return scopePath;
    }

    const scopeSegments = scopePath.split('.').filter(Boolean);
    const exactSegments = parameterPath.split('.').filter(Boolean);
    const nextSegment = exactSegments[scopeSegments.length] ?? null;

    return nextSegment && /^\d+$/.test(nextSegment)
        ? [...scopeSegments, nextSegment].join('.')
        : scopePath;
}

function buildPreviewTargetSelector(
    sectionLocalId: string | null,
    sectionKey: string | null,
    attribute: PreviewTargetAttribute,
    parameterPath: string | null,
): string {
    const sectionSelector = sectionLocalId
        ? `[data-webu-section-local-id="${sectionLocalId.replace(/"/g, '\\"')}"]`
        : sectionKey
            ? `[data-webu-section="${sectionKey.replace(/"/g, '\\"')}"]`
            : '[data-webu-section]';

    if (!attribute || !parameterPath) {
        return sectionSelector;
    }

    return `${sectionSelector} [${attribute}="${parameterPath.replace(/"/g, '\\"')}"]`;
}

function resolveTopLevelSections(doc: Document): HTMLElement[] {
    return Array.from(doc.querySelectorAll<HTMLElement>('[data-webu-section]'))
        .filter((section) => !section.parentElement?.closest('[data-webu-section]'));
}

function resolveRootCanvasSection(doc: Document): HTMLElement | null {
    const sections = resolveTopLevelSections(doc);
    return sections.length === 1 ? sections[0] ?? null : null;
}

function buildResolvedPreviewTarget(
    target: Element | null,
    mappedElement?: MappedElement | null,
): ResolvedPreviewTarget | null {
    const resolvedNode = isInspectableElement(target) ? target : null;
    const section = resolvedNode?.closest<HTMLElement>('[data-webu-section]') ?? null;
    if (!resolvedNode || !section) {
        return null;
    }

    const sectionLocalId = readTrimmedAttribute(section, 'data-webu-section-local-id');
    const sectionKey = readTrimmedAttribute(section, 'data-webu-section');
    if (!sectionLocalId && !sectionKey) {
        return null;
    }

    const exactFieldPath = mappedElement?.attribute === 'data-webu-field-scope'
        ? null
        : mappedElement?.parameterName
            ?? readTrimmedAttribute(resolvedNode.closest('[data-webu-field], [data-webu-field-url]'), 'data-webu-field')
            ?? readTrimmedAttribute(resolvedNode.closest('[data-webu-field], [data-webu-field-url]'), 'data-webu-field-url');
    const scopePath = mappedElement?.attribute === 'data-webu-field-scope'
        ? mappedElement.parameterName
        : readTrimmedAttribute(resolvedNode.closest('[data-webu-field-scope]'), 'data-webu-field-scope');
    const parameterPath = mappedElement?.parameterName ?? exactFieldPath ?? scopePath ?? null;
    const componentPath = deriveComponentPath(parameterPath, scopePath);
    const attribute: PreviewTargetAttribute = mappedElement?.attribute
        ?? (exactFieldPath
            ? (readTrimmedAttribute(resolvedNode.closest('[data-webu-field]'), 'data-webu-field') ? 'data-webu-field' : 'data-webu-field-url')
            : scopePath
                ? 'data-webu-field-scope'
                : null);
    const kind: PreviewTargetKind = !parameterPath
        ? 'section'
        : attribute === 'data-webu-field-scope'
            ? 'scope'
            : 'field';
    const selector = buildPreviewTargetSelector(sectionLocalId, sectionKey, attribute, parameterPath);
    const effectiveSectionKey = sectionKey ?? mappedElement?.sectionKey ?? null;

    return {
        node: resolvedNode,
        section,
        kind,
        attribute,
        sectionLocalId,
        sectionKey: effectiveSectionKey,
        parameterPath,
        componentPath,
        selector,
        targetId: buildTargetId(sectionLocalId, effectiveSectionKey, parameterPath),
        elementId: effectiveSectionKey && parameterPath
            ? (mappedElement?.elementId ?? buildElementId(effectiveSectionKey, parameterPath))
            : null,
    };
}

function resolveEditableCandidateFromPoint(doc: Document, x: number, y: number): HTMLElement | null {
    if (typeof doc.elementsFromPoint === 'function') {
        const stack = doc.elementsFromPoint(x, y);
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

    const element = doc.elementFromPoint(x, y);
    return isElementNode(element) ? resolveComponentInspectableTarget(element) : null;
}

function resolveMappedElementForTarget(
    target: ResolvedPreviewTarget,
    domMap: DOMMap,
): MappedElement | null {
    if (!target.sectionKey || !target.parameterPath) {
        return null;
    }

    return domMap.elementsById.get(buildElementId(target.sectionKey, target.parameterPath)) ?? null;
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

export function resolvePreviewTargetFromElement(target: Element | null): PreviewTargetResolution {
    if (!isInspectableElement(target)) {
        return {
            status: 'none',
            target: null,
            reason: 'none',
        };
    }

    const editableTarget = resolveComponentInspectableTarget(target);
    if (editableTarget) {
        return {
            status: 'resolved',
            target: buildResolvedPreviewTarget(editableTarget),
            reason: 'nearest-editable-ancestor',
        };
    }

    const sectionTarget = target.closest<HTMLElement>('[data-webu-section]');
    if (sectionTarget) {
        return {
            status: 'resolved',
            target: buildResolvedPreviewTarget(sectionTarget),
            reason: 'section-fallback',
        };
    }

    return {
        status: 'none',
        target: null,
        reason: 'none',
    };
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

export function resolvePreviewTargetAtPoint(
    iframe: HTMLIFrameElement | null,
    scale: number,
    options: {
        target: EventTarget | null;
        clientX: number;
        clientY: number;
    },
): PreviewTargetResolution {
    if (!iframe) {
        return {
            status: 'none',
            target: null,
            reason: 'none',
        };
    }

    const point = resolvePointInIframe(iframe, scale, options.clientX, options.clientY);
    if (!point) {
        return resolvePreviewTargetFromElement(isElementNode(options.target) ? options.target : null);
    }

    if (point.localX < 0 || point.localY < 0 || point.localX > point.iframeRect.width || point.localY > point.iframeRect.height) {
        return resolvePreviewTargetFromElement(isElementNode(options.target) ? options.target : null);
    }

    const domMap = buildDOMMapCached(point.doc);
    const editableCandidate = resolveEditableCandidateFromPoint(point.doc, point.docX, point.docY);
    if (editableCandidate) {
        const editableTarget = buildResolvedPreviewTarget(editableCandidate);
        if (!editableTarget) {
            return {
                status: 'missing-backing-node',
                target: null,
                reason: 'missing-backing-node',
            };
        }

        const mappedElement = resolveMappedElementForTarget(editableTarget, domMap);
        if (!mappedElement) {
            return {
                status: 'missing-backing-node',
                target: null,
                reason: 'missing-backing-node',
            };
        }

        return {
            status: 'resolved',
            target: buildResolvedPreviewTarget(editableCandidate, mappedElement),
            reason: 'mapped-element',
        };
    }

    const rawTopElement = resolveTopIframeElementAtPoint(iframe, scale, options.clientX, options.clientY);
    const rawEditableTarget = resolveComponentInspectableTarget(rawTopElement);
    if (rawEditableTarget) {
        return {
            status: 'missing-backing-node',
            target: null,
            reason: 'missing-backing-node',
        };
    }

    const sectionTarget = getSectionAtPoint(point.doc, point.docX, point.docY)
        ?? resolveSectionTarget(iframe, scale, options.target, options.clientX, options.clientY);
    if (sectionTarget) {
        return {
            status: 'resolved',
            target: buildResolvedPreviewTarget(sectionTarget),
            reason: 'section-fallback',
        };
    }

    const rootCanvasSection = resolveRootCanvasSection(point.doc);
    if (rootCanvasSection) {
        return {
            status: 'resolved',
            target: buildResolvedPreviewTarget(rootCanvasSection),
            reason: 'root-canvas-fallback',
        };
    }

    return {
        status: 'none',
        target: null,
        reason: 'none',
    };
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

    return resolveEditableCandidateFromPoint(point.doc, point.docX, point.docY);
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
