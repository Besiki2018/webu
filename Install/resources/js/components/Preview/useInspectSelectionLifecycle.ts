/**
 * Hook for inspect-mode selection lifecycle: hover, click, overlay state, and mention building.
 * Extracts selection logic from InspectPreview for clearer module boundaries.
 */
import { useCallback, useState, useRef, useEffect, type RefObject } from 'react';
import { buildElementId } from '@/builder/componentParameterMetadata';
import {
    resolveComponentInspectableTarget,
    resolveElementAtPoint as resolveElementAtPointInIframe,
    resolvePlacementTarget as resolvePlacementTargetInIframe,
    resolveSectionOnlyFallbackTarget as resolveSectionOnlyFallbackTargetInIframe,
    resolveSectionTarget as resolveSectionTargetInIframe,
    type InspectPreviewDropPlacement,
} from './inspectPreviewTargets';
import type { ElementMention } from '@/types/inspector';

export type DropPlacement = InspectPreviewDropPlacement;

const DEBUG_INSPECT = typeof window !== 'undefined'
    && window.location.search.includes('tab=inspect')
    && window.location.search.includes('debug=inspect');

function inspectLifecycleLog(...args: unknown[]): void {
    if (DEBUG_INSPECT) {
        console.warn('[WebuInspectLifecycle]', ...args);
    }
}

function isInspectableElement(value: unknown): value is HTMLElement {
    return Boolean(value)
        && typeof value === 'object'
        && 'nodeType' in (value as Node)
        && (value as Node).nodeType === 1
        && typeof (value as Element).closest === 'function';
}

export interface PreviewOverlayBox {
    left: number;
    top: number;
    width: number;
    height: number;
    placement: DropPlacement | null;
    label: string | null;
}

export interface LiveStructureItem {
    localId: string;
    sectionKey: string;
    label: string;
    previewText: string;
    props: Record<string, unknown>;
}

export function buildElementMentionFromTarget(
    target: Element,
    placement: DropPlacement | null = null,
): ElementMention | null {
    const sectionHost = target.closest<HTMLElement>('[data-webu-section]');
    if (!sectionHost) return null;

    const resolvedTarget = isInspectableElement(target) ? target : sectionHost;
    const scopeEl = resolvedTarget.closest<HTMLElement>('[data-webu-field-scope]');
    const fieldEl = resolvedTarget.closest<HTMLElement>('[data-webu-field], [data-webu-field-url]');
    const exactParameterName = fieldEl?.getAttribute('data-webu-field')
        ?? fieldEl?.getAttribute('data-webu-field-url')
        ?? null;
    const rawScopePath = scopeEl?.getAttribute('data-webu-field-scope') ?? null;
    const componentPath = (() => {
        if (!rawScopePath) return exactParameterName ?? null;
        if (!exactParameterName || !exactParameterName.startsWith(`${rawScopePath}.`)) return rawScopePath;
        const scopeSegments = rawScopePath.split('.').filter(Boolean);
        const exactSegments = exactParameterName.split('.').filter(Boolean);
        const nextSegment = exactSegments[scopeSegments.length] ?? null;
        return nextSegment && /^\d+$/.test(nextSegment)
            ? [...scopeSegments, nextSegment].join('.')
            : rawScopePath;
    })();
    const parameterName = (() => {
        if (exactParameterName && rawScopePath && exactParameterName !== rawScopePath && !exactParameterName.startsWith(`${rawScopePath}.`)) {
            return `${rawScopePath}.${exactParameterName}`;
        }
        return exactParameterName ?? componentPath;
    })();

    const sectionKey = sectionHost.getAttribute('data-webu-section');
    const sectionLocalId = sectionHost.getAttribute('data-webu-section-local-id');
    const textPreview = (
        resolvedTarget.textContent
        || resolvedTarget.getAttribute('aria-label')
        || resolvedTarget.getAttribute('title')
        || resolvedTarget.getAttribute('alt')
        || sectionHost.textContent
        || ''
    )
        .replace(/\s+/g, ' ')
        .trim()
        .slice(0, 120);
    let selector = sectionLocalId
        ? `[data-webu-section-local-id="${sectionLocalId.replace(/"/g, '\\"')}"]`
        : sectionKey
            ? `[data-webu-section="${sectionKey.replace(/"/g, '\\"')}"]`
            : resolvedTarget.tagName.toLowerCase();
    if (parameterName && (sectionLocalId || sectionKey)) {
        const fieldAttribute = exactParameterName
            ? (fieldEl?.getAttribute('data-webu-field') ? 'data-webu-field' : 'data-webu-field-url')
            : 'data-webu-field-scope';
        selector = sectionLocalId
            ? `[data-webu-section-local-id="${sectionLocalId.replace(/"/g, '\\"')}"] [${fieldAttribute}="${parameterName.replace(/"/g, '\\"')}"]`
            : `[data-webu-section="${(sectionKey ?? '').replace(/"/g, '\\"')}"] [${fieldAttribute}="${parameterName.replace(/"/g, '\\"')}"]`;
    }
    const elementId = sectionKey && parameterName ? buildElementId(sectionKey, parameterName) : null;

    return {
        id: elementId || selector || (target as HTMLElement).id || sectionLocalId || sectionKey || resolvedTarget.tagName.toLowerCase(),
        tagName: resolvedTarget.tagName.toLowerCase(),
        selector,
        textPreview,
        sectionKey: sectionKey ?? undefined,
        sectionLocalId: sectionLocalId ?? undefined,
        placement,
        parameterName: parameterName ?? undefined,
        componentPath: componentPath ?? undefined,
        elementId: elementId ?? undefined,
    };
}

export interface UseInspectSelectionLifecycleOptions {
    iframeRef: RefObject<HTMLIFrameElement | null>;
    frameRef: RefObject<HTMLDivElement | null>;
    scale: number;
    mode: 'preview' | 'inspect' | 'design';
    isBuilding: boolean;
    iframeReady: boolean;
    highlightSectionKey: string | null;
    highlightSectionLocalId: string | null;
    selectedElementMention: ElementMention | null;
    pendingLibraryItem: { key: string; label: string } | null;
    onElementSelect?: (element: ElementMention) => void;
    onLibraryItemPlace?: (sectionKey: string, target: ElementMention | null) => void;
}

export interface UseInspectSelectionLifecycleReturn {
    hoveredOverlay: PreviewOverlayBox | null;
    selectedOverlay: PreviewOverlayBox | null;
    setSelectedOverlay: React.Dispatch<React.SetStateAction<PreviewOverlayBox | null>>;
    buildElementMention: (target: Element, placement?: DropPlacement | null) => ElementMention | null;
    clearHoveredSection: () => void;
    updateHoveredSection: (section: Element | null, placement?: DropPlacement | null) => void;
    measureSectionOverlay: (section: Element, placement?: DropPlacement | null) => PreviewOverlayBox | null;
    overlaysMatch: (a: PreviewOverlayBox | null, b: PreviewOverlayBox | null) => boolean;
    setHoveredOverlayFromSection: (section: Element | null, placement?: DropPlacement | null) => void;
    resolveSelectedSectionNode: () => HTMLElement | null;
    resolveSelectedPreviewTarget: () => HTMLElement | null;
    hoveredSectionRef: React.MutableRefObject<Element | null>;
    handlePlacementPointerMove: (event: React.MouseEvent<HTMLDivElement>) => void;
    handlePlacementLeave: () => void;
    handlePlacementClick: (event: React.MouseEvent<HTMLDivElement>) => void;
    handlePlacementDragOver: (event: React.DragEvent<HTMLDivElement>) => void;
    handlePlacementDrop: (event: React.DragEvent<HTMLDivElement>) => void;
    handleInspectPointerMove: (event: React.MouseEvent<HTMLDivElement>) => void;
    handleInspectPointerLeave: () => void;
    handleInspectClick: (event: React.MouseEvent<HTMLDivElement>) => void;
}

export function useInspectSelectionLifecycle(
    options: UseInspectSelectionLifecycleOptions,
): UseInspectSelectionLifecycleReturn {
    const {
        iframeRef,
        frameRef,
        scale,
        mode,
        isBuilding,
        iframeReady,
        highlightSectionKey,
        highlightSectionLocalId,
        selectedElementMention,
        pendingLibraryItem,
        onElementSelect,
        onLibraryItemPlace,
    } = options;

    const [hoveredOverlay, setHoveredOverlay] = useState<PreviewOverlayBox | null>(null);
    const [selectedOverlay, setSelectedOverlay] = useState<PreviewOverlayBox | null>(null);
    const hoveredSectionRef = useRef<Element | null>(null);

    const getActiveIframe = useCallback((): HTMLIFrameElement | null => {
        const liveIframe = frameRef.current?.querySelector<HTMLIFrameElement>('iframe');
        return liveIframe ?? iframeRef.current ?? null;
    }, [frameRef, iframeRef]);

    const buildElementMention = useCallback((target: Element, placement: DropPlacement | null = null) => {
        return buildElementMentionFromTarget(target, placement);
    }, []);

    const buildSectionElementMention = useCallback((target: Element, placement: DropPlacement | null = null) => {
        const sectionHost = target.closest<HTMLElement>('[data-webu-section]');
        if (!sectionHost) {
            return null;
        }

        return buildElementMentionFromTarget(sectionHost, placement);
    }, []);

    const overlaysMatch = useCallback((left: PreviewOverlayBox | null, right: PreviewOverlayBox | null) => {
        if (left === right) return true;
        if (!left || !right) return false;
        return (
            left.left === right.left && left.top === right.top
            && left.width === right.width && left.height === right.height
            && left.placement === right.placement && left.label === right.label
        );
    }, []);

    const measureSectionOverlay = useCallback((section: Element, placement: DropPlacement | null = null): PreviewOverlayBox | null => {
        const frame = frameRef.current;
        const iframe = getActiveIframe();
        if (!frame || !iframe) return null;

        const frameRect = frame.getBoundingClientRect();
        const sectionRect = section.getBoundingClientRect();
        if (sectionRect.width <= 0 || sectionRect.height <= 0) return null;

        const inParentViewport = sectionRect.left >= frameRect.left - 2 && sectionRect.top >= frameRect.top - 2
            && sectionRect.left <= frameRect.right + 2 && sectionRect.top <= frameRect.bottom + 2;
        const left = inParentViewport ? sectionRect.left - frameRect.left : sectionRect.left;
        const top = inParentViewport ? sectionRect.top - frameRect.top : sectionRect.top;

        return {
            left: Math.round(left),
            top: Math.round(top),
            width: Math.round(sectionRect.width),
            height: Math.round(sectionRect.height),
            placement,
            label: section.getAttribute('data-webu-field-scope') ?? section.getAttribute('data-webu-field') ?? section.getAttribute('data-webu-field-url') ?? section.getAttribute('data-webu-section'),
        };
    }, [frameRef, getActiveIframe]);

    const setHoveredOverlayFromSection = useCallback((section: Element | null, placement: DropPlacement | null = null) => {
        const nextOverlay = section ? measureSectionOverlay(section, placement) : null;
        setHoveredOverlay((current) => (overlaysMatch(current, nextOverlay) ? current : nextOverlay));
    }, [measureSectionOverlay, overlaysMatch]);

    const resolveSelectedSectionNode = useCallback((): HTMLElement | null => {
        const iframeDoc = getActiveIframe()?.contentDocument;
        if (!iframeDoc) return null;
        if (highlightSectionLocalId) {
            return iframeDoc.querySelector<HTMLElement>(`[data-webu-section-local-id="${highlightSectionLocalId.replace(/"/g, '\\"')}"]`);
        }
        if (highlightSectionKey) {
            return iframeDoc.querySelector<HTMLElement>(`[data-webu-section="${highlightSectionKey.replace(/"/g, '\\"')}"]`);
        }
        return null;
    }, [getActiveIframe, highlightSectionKey, highlightSectionLocalId]);

    const resolveSelectedPreviewTarget = useCallback((): HTMLElement | null => {
        const iframeDoc = getActiveIframe()?.contentDocument;
        if (!iframeDoc) return null;

        const selectedSectionLocalId = typeof selectedElementMention?.sectionLocalId === 'string' && selectedElementMention.sectionLocalId.trim() !== ''
            ? selectedElementMention.sectionLocalId.trim()
            : null;
        const selectedSectionKey = typeof selectedElementMention?.sectionKey === 'string' && selectedElementMention.sectionKey.trim() !== ''
            ? selectedElementMention.sectionKey.trim()
            : null;
        const selectedParameterPath = typeof selectedElementMention?.parameterName === 'string' && selectedElementMention.parameterName.trim() !== ''
            ? selectedElementMention.parameterName.trim()
            : null;
        const selectedComponentPath = typeof selectedElementMention?.componentPath === 'string' && selectedElementMention.componentPath.trim() !== ''
            ? selectedElementMention.componentPath.trim()
            : null;

        const sectionHost = selectedSectionLocalId
            ? iframeDoc.querySelector<HTMLElement>(`[data-webu-section-local-id="${selectedSectionLocalId.replace(/"/g, '\\"')}"]`)
            : selectedSectionKey
                ? iframeDoc.querySelector<HTMLElement>(`[data-webu-section="${selectedSectionKey.replace(/"/g, '\\"')}"]`)
                : resolveSelectedSectionNode();

        if (!sectionHost) return resolveSelectedSectionNode();

        if (selectedParameterPath) {
            const fieldNode = sectionHost.querySelector<HTMLElement>(
                `[data-webu-field-scope="${selectedParameterPath.replace(/"/g, '\\"')}"], [data-webu-field="${selectedParameterPath.replace(/"/g, '\\"')}"], [data-webu-field-url="${selectedParameterPath.replace(/"/g, '\\"')}"]`
            );
            if (fieldNode) return fieldNode;
        }
        if (selectedComponentPath) {
            const scopeNode = sectionHost.querySelector<HTMLElement>(
                `[data-webu-field-scope="${selectedComponentPath.replace(/"/g, '\\"')}"], [data-webu-field="${selectedComponentPath.replace(/"/g, '\\"')}"], [data-webu-field-url="${selectedComponentPath.replace(/"/g, '\\"')}"]`
            );
            if (scopeNode) return scopeNode;
        }
        return sectionHost;
    }, [getActiveIframe, resolveSelectedSectionNode, selectedElementMention]);

    const clearHoveredSection = useCallback(() => {
        hoveredSectionRef.current?.removeAttribute('data-webu-chat-hovered');
        hoveredSectionRef.current?.removeAttribute('data-webu-chat-drop-position');
        hoveredSectionRef.current = null;
        setHoveredOverlay(null);
    }, []);

    const updateHoveredSection = useCallback((nextSection: Element | null, placement: DropPlacement | null = null) => {
        if (hoveredSectionRef.current === nextSection) {
            if (nextSection) {
                if (placement) nextSection.setAttribute('data-webu-chat-drop-position', placement);
                else nextSection.removeAttribute('data-webu-chat-drop-position');
            }
            setHoveredOverlayFromSection(nextSection, placement);
            return;
        }
        hoveredSectionRef.current?.removeAttribute('data-webu-chat-hovered');
        hoveredSectionRef.current?.removeAttribute('data-webu-chat-drop-position');
        hoveredSectionRef.current = nextSection;
        if (nextSection) {
            nextSection.setAttribute('data-webu-chat-hovered', 'true');
            if (placement) nextSection.setAttribute('data-webu-chat-drop-position', placement);
            else nextSection.removeAttribute('data-webu-chat-drop-position');
        }
        setHoveredOverlayFromSection(nextSection, placement);
    }, [setHoveredOverlayFromSection]);

    const resolveElementAtPoint = useCallback((clientX: number, clientY: number) => {
        return resolveElementAtPointInIframe(getActiveIframe(), scale, clientX, clientY);
    }, [getActiveIframe, scale]);

    const resolveFreshElementAtPoint = useCallback((clientX: number, clientY: number): HTMLElement | null => {
        const iframe = getActiveIframe();
        const doc = iframe?.contentDocument;
        const win = iframe?.contentWindow;
        if (!iframe || !doc) {
            return null;
        }

        const iframeRect = iframe.getBoundingClientRect();
        const localX = clientX - iframeRect.left;
        const localY = clientY - iframeRect.top;
        if (localX < 0 || localY < 0 || localX > iframeRect.width || localY > iframeRect.height) {
            return null;
        }

        const docWidth = Math.max(
            doc.documentElement?.clientWidth ?? 0,
            doc.body?.clientWidth ?? 0,
            win?.innerWidth ?? 0,
        );
        const docHeight = Math.max(
            doc.documentElement?.clientHeight ?? 0,
            doc.body?.clientHeight ?? 0,
            win?.innerHeight ?? 0,
        );
        const scaleX = docWidth > 0 ? iframeRect.width / docWidth : scale;
        const scaleY = docHeight > 0 ? iframeRect.height / docHeight : scale;
        const docX = scaleX > 0 ? localX / scaleX : localX;
        const docY = scaleY > 0 ? localY / scaleY : localY;

        if (typeof doc.elementsFromPoint === 'function') {
            const stack = doc.elementsFromPoint(docX, docY);
            for (const candidate of stack) {
                const resolvedTarget = resolveComponentInspectableTarget(isInspectableElement(candidate) ? candidate : null);
                if (resolvedTarget) {
                    return resolvedTarget;
                }
            }
        }

        const direct = doc.elementFromPoint(docX, docY);
        return resolveComponentInspectableTarget(isInspectableElement(direct) ? direct : null);
    }, [getActiveIframe, scale]);

    const resolvePlacementTarget = useCallback((target: EventTarget | null, clientX: number, clientY: number) => {
        return resolvePlacementTargetInIframe(getActiveIframe(), scale, target, clientX, clientY);
    }, [getActiveIframe, scale]);

    const resolveSectionOnlyFallbackTarget = useCallback((opts: { target: EventTarget | null; clientX?: number; clientY?: number }) => {
        return resolveSectionOnlyFallbackTargetInIframe(getActiveIframe(), scale, opts);
    }, [getActiveIframe, scale]);

    const resolveParentClientPoint = useCallback((clientX: number, clientY: number) => {
        const iframeRect = getActiveIframe()?.getBoundingClientRect();
        if (!iframeRect) return null;
        return {
            clientX: iframeRect.left + (clientX * scale),
            clientY: iframeRect.top + (clientY * scale),
        };
    }, [getActiveIframe, scale]);

    const handlePlacementPointerMove = useCallback((event: React.MouseEvent<HTMLDivElement>) => {
        if (!pendingLibraryItem) return;
        const resolvedTarget = resolvePlacementTarget(event.target, event.clientX, event.clientY);
        updateHoveredSection(resolvedTarget?.section ?? null, resolvedTarget?.placement ?? null);
    }, [pendingLibraryItem, resolvePlacementTarget, updateHoveredSection]);

    const handlePlacementLeave = useCallback(() => {
        if (pendingLibraryItem) clearHoveredSection();
    }, [clearHoveredSection, pendingLibraryItem]);

    const handlePlacementClick = useCallback((event: React.MouseEvent<HTMLDivElement>) => {
        if (!pendingLibraryItem || !onLibraryItemPlace) return;
        event.preventDefault();
        const resolvedTarget = resolvePlacementTarget(event.target, event.clientX, event.clientY);
        onLibraryItemPlace(
            pendingLibraryItem.key,
            resolvedTarget ? buildElementMention(resolvedTarget.section, resolvedTarget.placement) : null,
        );
        clearHoveredSection();
    }, [buildElementMention, clearHoveredSection, onLibraryItemPlace, pendingLibraryItem, resolvePlacementTarget]);

    const handlePlacementDragOver = useCallback((event: React.DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy';
        if (!pendingLibraryItem) return;
        const resolvedTarget = resolvePlacementTarget(event.target, event.clientX, event.clientY);
        updateHoveredSection(resolvedTarget?.section ?? null, resolvedTarget?.placement ?? null);
    }, [pendingLibraryItem, resolvePlacementTarget, updateHoveredSection]);

    const handlePlacementDrop = useCallback((event: React.DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        if (!onLibraryItemPlace) return;
        const sectionKey = pendingLibraryItem?.key ?? (event.dataTransfer?.getData?.('text/plain') ?? '').trim();
        if (!sectionKey) return;
        const resolvedTarget = resolvePlacementTarget(event.target, event.clientX, event.clientY);
        onLibraryItemPlace(sectionKey, resolvedTarget ? buildElementMention(resolvedTarget.section, resolvedTarget.placement) : null);
        clearHoveredSection();
    }, [buildElementMention, clearHoveredSection, onLibraryItemPlace, pendingLibraryItem?.key, resolvePlacementTarget]);

    const handleInspectPointerMove = useCallback((event: React.MouseEvent<HTMLDivElement>) => {
        if (mode !== 'inspect' || isBuilding) return;
        const hoverTarget = resolveElementAtPoint(event.clientX, event.clientY)
            ?? resolveFreshElementAtPoint(event.clientX, event.clientY)
            ?? resolveSectionOnlyFallbackTarget({ target: event.target, clientX: event.clientX, clientY: event.clientY });
        const hoveredSection = hoverTarget?.closest<HTMLElement>('[data-webu-section]') ?? hoverTarget;
        updateHoveredSection(hoveredSection);
    }, [isBuilding, mode, resolveElementAtPoint, resolveFreshElementAtPoint, resolveSectionOnlyFallbackTarget, updateHoveredSection]);

    const handleInspectPointerLeave = useCallback(() => {
        if (mode !== 'inspect') return;
        clearHoveredSection();
    }, [clearHoveredSection, mode]);

    const handleInspectClick = useCallback((event: React.MouseEvent<HTMLDivElement>) => {
        if (mode !== 'inspect' || isBuilding) return;
        event.preventDefault();
        const elementAtPoint = resolveElementAtPoint(event.clientX, event.clientY)
            ?? resolveFreshElementAtPoint(event.clientX, event.clientY);
        const section = elementAtPoint ? null : resolveSectionOnlyFallbackTarget({
            target: event.target,
            clientX: event.clientX,
            clientY: event.clientY,
        });
        const selectedSection = elementAtPoint?.closest<HTMLElement>('[data-webu-section]') ?? section;
        const mention = selectedSection ? buildSectionElementMention(selectedSection) : null;
        inspectLifecycleLog('handleInspectClick', {
            clientX: event.clientX,
            clientY: event.clientY,
            elementAtPoint: elementAtPoint instanceof Element
                ? {
                    tag: elementAtPoint.tagName,
                    field: elementAtPoint.getAttribute('data-webu-field'),
                    scope: elementAtPoint.getAttribute('data-webu-field-scope'),
                    section: elementAtPoint.getAttribute('data-webu-section'),
                    localId: elementAtPoint.closest('[data-webu-section]')?.getAttribute('data-webu-section-local-id') ?? null,
                }
                : null,
            section: section instanceof Element
                ? {
                    tag: section.tagName,
                    section: section.getAttribute('data-webu-section'),
                    localId: section.getAttribute('data-webu-section-local-id'),
                }
                : null,
            mention,
        });
        if (!mention) return;
        setSelectedOverlay((current) => {
            const nextOverlay = selectedSection ? measureSectionOverlay(selectedSection) : null;
            return overlaysMatch(current, nextOverlay) ? current : nextOverlay;
        });
        onElementSelect?.(mention);
    }, [buildSectionElementMention, isBuilding, measureSectionOverlay, mode, onElementSelect, overlaysMatch, resolveElementAtPoint, resolveFreshElementAtPoint, resolveSectionOnlyFallbackTarget]);

    useEffect(() => {
        if (!pendingLibraryItem) clearHoveredSection();
    }, [clearHoveredSection, pendingLibraryItem]);

    useEffect(() => {
        if (mode !== 'inspect' || !iframeReady || pendingLibraryItem) return;
        const iframe = getActiveIframe();
        const iframeDoc = iframe?.contentDocument;
        const iframeWin = iframe?.contentWindow;
        if (!iframeDoc || !iframeWin) return;

        const onIframeMouseMove = (e: MouseEvent) => {
            const target = e.target as Node | null;
            if (!target || !iframeDoc.contains(target)) return;
            const targetElement = isInspectableElement(target) ? target : (target as Node).parentElement;
            if (!targetElement) return;
            const point = resolveParentClientPoint(e.clientX, e.clientY);
            const hoverTarget = (point ? resolveElementAtPoint(point.clientX, point.clientY) : null)
                ?? (point ? resolveFreshElementAtPoint(point.clientX, point.clientY) : null)
                ?? resolveComponentInspectableTarget(targetElement)
                ?? resolveSectionOnlyFallbackTarget({ target: targetElement, clientX: point?.clientX, clientY: point?.clientY });
            const hoveredSection = hoverTarget?.closest<HTMLElement>('[data-webu-section]') ?? hoverTarget;
            updateHoveredSection(hoveredSection);
        };
        const onIframeMouseLeave = () => clearHoveredSection();
        const onIframeClick = (e: MouseEvent) => {
            const target = e.target as Node | null;
            if (!target || !iframeDoc.contains(target)) return;
            const targetElement = isInspectableElement(target) ? target : (target as Node).parentElement;
            const section = targetElement?.closest<HTMLElement>('[data-webu-section]') ?? null;
            if (!section) return;
            e.preventDefault();
            e.stopPropagation();
            const point = resolveParentClientPoint(e.clientX, e.clientY);
            const resolvedTarget = (point ? resolveElementAtPoint(point.clientX, point.clientY) : null)
                ?? (point ? resolveFreshElementAtPoint(point.clientX, point.clientY) : null)
                ?? resolveComponentInspectableTarget(targetElement)
                ?? resolveSectionOnlyFallbackTarget({ target: targetElement, clientX: point?.clientX, clientY: point?.clientY });
            if (!resolvedTarget) return;
            const selectedSection = resolvedTarget.closest<HTMLElement>('[data-webu-section]') ?? resolvedTarget;
            const mention = buildSectionElementMention(selectedSection);
            inspectLifecycleLog('onIframeClick', {
                targetTag: targetElement?.tagName ?? null,
                resolvedTarget: {
                    tag: resolvedTarget.tagName,
                    field: resolvedTarget.getAttribute('data-webu-field'),
                    scope: resolvedTarget.getAttribute('data-webu-field-scope'),
                    section: resolvedTarget.getAttribute('data-webu-section'),
                    localId: resolvedTarget.closest('[data-webu-section]')?.getAttribute('data-webu-section-local-id') ?? null,
                },
                mention,
            });
            if (mention) {
                const overlay = measureSectionOverlay(selectedSection);
                setSelectedOverlay((current) => (overlaysMatch(current, overlay) ? current : overlay));
                onElementSelect?.(mention);
            }
        };
        iframeDoc.addEventListener('mousemove', onIframeMouseMove, { passive: true });
        iframeDoc.addEventListener('mouseleave', onIframeMouseLeave);
        iframeDoc.addEventListener('click', onIframeClick, { capture: true });
        return () => {
            iframeDoc.removeEventListener('mousemove', onIframeMouseMove);
            iframeDoc.removeEventListener('mouseleave', onIframeMouseLeave);
            iframeDoc.removeEventListener('click', onIframeClick, { capture: true });
        };
    }, [
        mode, iframeReady, pendingLibraryItem,
        updateHoveredSection, clearHoveredSection, buildSectionElementMention, measureSectionOverlay,
        onElementSelect, overlaysMatch, resolveElementAtPoint, resolveParentClientPoint,
        resolveFreshElementAtPoint, resolveSectionOnlyFallbackTarget, getActiveIframe,
    ]);

    return {
        hoveredOverlay,
        selectedOverlay,
        setSelectedOverlay,
        hoveredSectionRef,
        buildElementMention,
        clearHoveredSection,
        updateHoveredSection,
        measureSectionOverlay,
        overlaysMatch,
        setHoveredOverlayFromSection,
    resolveSelectedSectionNode,
    resolveSelectedPreviewTarget,
    handlePlacementPointerMove,
        handlePlacementLeave,
        handlePlacementClick,
        handlePlacementDragOver,
        handlePlacementDrop,
        handleInspectPointerMove,
        handleInspectPointerLeave,
        handleInspectClick,
    };
}
