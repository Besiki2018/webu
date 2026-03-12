/**
 * Hook for inspect-mode selection lifecycle: hover, click, overlay state, and mention building.
 * Extracts selection logic from InspectPreview for clearer module boundaries.
 */
import { useCallback, useState, useRef, useEffect, type RefObject } from 'react';
import {
    resolvePlacementTarget as resolvePlacementTargetInIframe,
    resolvePreviewTargetAtPoint as resolvePreviewTargetAtPointInIframe,
    resolvePreviewTargetFromElement,
    resolveSectionTarget as resolveSectionTargetInIframe,
    type ResolvedPreviewTarget,
    resolveSectionOnlyFallbackTarget as resolveSectionOnlyFallbackTargetInIframe,
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
    const resolution = resolvePreviewTargetFromElement(target);
    return buildElementMentionFromResolvedTarget(
        resolution.status === 'resolved' ? resolution.target : null,
        placement,
    );
}

function buildElementMentionFromResolvedTarget(
    target: ResolvedPreviewTarget | null,
    placement: DropPlacement | null = null,
): ElementMention | null {
    if (!target) {
        return null;
    }

    const textPreview = (
        target.node.textContent
        || target.node.getAttribute('aria-label')
        || target.node.getAttribute('title')
        || target.node.getAttribute('alt')
        || target.section.textContent
        || ''
    )
        .replace(/\s+/g, ' ')
        .trim()
        .slice(0, 120);

    return {
        id: target.targetId,
        targetId: target.targetId,
        tagName: target.node.tagName.toLowerCase(),
        selector: target.selector,
        textPreview,
        sectionKey: target.sectionKey ?? undefined,
        sectionLocalId: target.sectionLocalId ?? undefined,
        placement,
        parameterName: target.kind === 'section' ? undefined : (target.parameterPath ?? undefined),
        componentPath: target.kind === 'section' ? undefined : (target.componentPath ?? undefined),
        elementId: target.elementId ?? undefined,
    };
}

function hasBackingPathValue(value: unknown, path: string | null): boolean {
    if (!path) {
        return true;
    }

    const segments = path.split('.').filter(Boolean);
    if (segments.length === 0) {
        return true;
    }

    let current: unknown = value;
    for (const segment of segments) {
        if (Array.isArray(current)) {
            const index = Number(segment);
            if (!Number.isInteger(index) || index < 0 || index >= current.length) {
                return false;
            }

            current = current[index];
            continue;
        }

        if (!current || typeof current !== 'object' || !(segment in (current as Record<string, unknown>))) {
            return false;
        }

        current = (current as Record<string, unknown>)[segment];
    }

    return true;
}

function hasBackingStructureTarget(
    target: ResolvedPreviewTarget | null,
    liveStructureItems: LiveStructureItem[],
): boolean {
    if (!target) {
        return false;
    }

    const matchingItem = target.sectionLocalId
        ? liveStructureItems.find((item) => item.localId === target.sectionLocalId) ?? null
        : target.sectionKey
            ? liveStructureItems.find((item) => item.sectionKey === target.sectionKey) ?? null
            : null;

    if (!matchingItem) {
        return false;
    }

    return hasBackingPathValue(matchingItem.props, target.parameterPath);
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
    liveStructureItems: LiveStructureItem[];
    pendingLibraryItem: { key: string; label: string } | null;
    onElementSelect?: (element: ElementMention | null) => void;
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
        highlightSectionKey,
        highlightSectionLocalId,
        selectedElementMention,
        liveStructureItems,
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

    const resolvePlacementTarget = useCallback((target: EventTarget | null, clientX: number, clientY: number) => {
        return resolvePlacementTargetInIframe(getActiveIframe(), scale, target, clientX, clientY);
    }, [getActiveIframe, scale]);

    const resolveSectionOnlyFallbackTarget = useCallback((opts: { target: EventTarget | null; clientX?: number; clientY?: number }) => {
        return resolveSectionOnlyFallbackTargetInIframe(getActiveIframe(), scale, opts);
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
        const pointResolution = resolvePreviewTargetAtPointInIframe(getActiveIframe(), scale, {
            target: event.target,
            clientX: event.clientX,
            clientY: event.clientY,
        });

        if (pointResolution.status === 'missing-backing-node') {
            const fallbackSection = resolveSectionTargetInIframe(
                getActiveIframe(),
                scale,
                event.target,
                event.clientX,
                event.clientY,
            );
            updateHoveredSection(fallbackSection);
            return;
        }

        const hoveredSection = pointResolution.status === 'resolved'
            ? pointResolution.target?.section ?? null
            : resolveSectionOnlyFallbackTarget({ target: event.target, clientX: event.clientX, clientY: event.clientY });
        updateHoveredSection(hoveredSection);
    }, [getActiveIframe, isBuilding, mode, resolveSectionOnlyFallbackTarget, scale, updateHoveredSection]);

    const handleInspectPointerLeave = useCallback(() => {
        if (mode !== 'inspect') return;
        clearHoveredSection();
    }, [clearHoveredSection, mode]);

    const handleInspectClick = useCallback((event: React.MouseEvent<HTMLDivElement>) => {
        if (mode !== 'inspect' || isBuilding) return;
        event.preventDefault();
        const pointResolution = resolvePreviewTargetAtPointInIframe(getActiveIframe(), scale, {
            target: event.target,
            clientX: event.clientX,
            clientY: event.clientY,
        });

        const resolvedTarget = pointResolution.status === 'resolved'
            ? pointResolution.target
            : null;

        if (resolvedTarget && !hasBackingStructureTarget(resolvedTarget, liveStructureItems)) {
            inspectLifecycleLog('ignored-click-target-without-live-structure-node', {
                targetId: resolvedTarget.targetId,
                sectionLocalId: resolvedTarget.sectionLocalId,
                parameterPath: resolvedTarget.parameterPath,
            });
            return;
        }

        const selectedSection = resolvedTarget?.section
            ?? (pointResolution.status === 'missing-backing-node'
                ? resolveSectionTargetInIframe(
                    getActiveIframe(),
                    scale,
                    event.target,
                    event.clientX,
                    event.clientY,
                )
                : resolveSectionOnlyFallbackTarget({
                    target: event.target,
                    clientX: event.clientX,
                    clientY: event.clientY,
                }));
        const mention = buildElementMentionFromResolvedTarget(resolvedTarget)
            ?? (selectedSection ? buildSectionElementMention(selectedSection) : null);
        inspectLifecycleLog('handleInspectClick', {
            clientX: event.clientX,
            clientY: event.clientY,
            resolutionStatus: pointResolution.status,
            resolutionReason: pointResolution.reason,
            targetId: resolvedTarget?.targetId ?? null,
            sectionLocalId: resolvedTarget?.sectionLocalId ?? selectedSection?.getAttribute('data-webu-section-local-id') ?? null,
            parameterPath: resolvedTarget?.parameterPath ?? null,
            mention,
        });
        if (!mention) {
            setSelectedOverlay(null);
            clearHoveredSection();
            onElementSelect?.(null);
            return;
        }
        setSelectedOverlay((current) => {
            const nextOverlay = selectedSection ? measureSectionOverlay(selectedSection) : null;
            return overlaysMatch(current, nextOverlay) ? current : nextOverlay;
        });
        clearHoveredSection();
        onElementSelect?.(mention);
    }, [buildSectionElementMention, clearHoveredSection, getActiveIframe, isBuilding, liveStructureItems, measureSectionOverlay, mode, onElementSelect, overlaysMatch, resolveSectionOnlyFallbackTarget, scale]);

    useEffect(() => {
        if (pendingLibraryItem) {
            return;
        }

        const timeoutId = window.setTimeout(() => {
            clearHoveredSection();
        }, 0);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [clearHoveredSection, pendingLibraryItem]);

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
