import { useEffect, useRef } from 'react';

import type { DesignSystemOverrides } from '@/builder/designSystem/DesignSystemPanel';
import { observeDOMMapInvalidation } from '@/builder/domMapper';

interface UseCmsPreviewIframeBindingOptions {
    isVisualBuilderOpen: boolean;
    builderPreviewIframeRef: { current: HTMLIFrameElement | null };
    builderPreviewDocumentRef: { current: Document | null };
    selectedSectionLocalId: string | null;
    selectedFixedSectionKey: string | null;
    headerVariant: string;
    footerVariant: string;
    designSystemOverrides: DesignSystemOverrides;
    ensurePreviewSelectionStyle: (doc: Document) => void;
    ensureBuilderStableCanvasStyle: (doc: Document) => void;
    ensureDesignSystemTokensStyle: (doc: Document, overrides: DesignSystemOverrides) => void;
    syncPreviewVisibleSections: () => void;
    syncBuilderPreviewDraftBindings: () => void;
    clearPreviewSelectionHighlight: () => void;
    highlightPreviewSection: (element: HTMLElement | null) => void;
    selectSectionByPreviewKey: (sectionKey: string, element?: HTMLElement | null) => void;
    isFixedLayoutSectionKey: (key: string) => boolean;
    detectFixedSectionKeyFromElement: (sectionElement: HTMLElement, headerVariant: string, footerVariant: string) => string | null;
}

function findPreviewSectionElement(doc: Document, event: MouseEvent): HTMLElement | null {
    const target = event.target;
    let sectionElement: HTMLElement | null = null;

    if (target instanceof HTMLElement) {
        sectionElement = target.closest<HTMLElement>('[data-webu-section]');
    }

    if (!sectionElement && typeof event.clientX === 'number' && typeof event.clientY === 'number' && typeof doc.elementsFromPoint === 'function') {
        const stack = doc.elementsFromPoint(event.clientX, event.clientY);
        for (const node of stack) {
            if (!(node instanceof HTMLElement)) {
                continue;
            }

            const candidate = node.closest<HTMLElement>('[data-webu-section]');
            if (candidate) {
                sectionElement = candidate;
                break;
            }
        }
    }

    if (!sectionElement && typeof event.clientX === 'number' && typeof event.clientY === 'number') {
        const allSections = Array.from(doc.querySelectorAll<HTMLElement>('[data-webu-section]'));
        for (const candidate of allSections) {
            const rect = candidate.getBoundingClientRect();
            if (
                event.clientX >= rect.left &&
                event.clientX <= rect.right &&
                event.clientY >= rect.top &&
                event.clientY <= rect.bottom
            ) {
                sectionElement = candidate;
                break;
            }
        }
    }

    return sectionElement;
}

export function useCmsPreviewIframeBinding({
    isVisualBuilderOpen,
    builderPreviewIframeRef,
    builderPreviewDocumentRef,
    selectedSectionLocalId,
    selectedFixedSectionKey,
    headerVariant,
    footerVariant,
    designSystemOverrides,
    ensurePreviewSelectionStyle,
    ensureBuilderStableCanvasStyle,
    ensureDesignSystemTokensStyle,
    syncPreviewVisibleSections,
    syncBuilderPreviewDraftBindings,
    clearPreviewSelectionHighlight,
    highlightPreviewSection,
    selectSectionByPreviewKey,
    isFixedLayoutSectionKey,
    detectFixedSectionKeyFromElement,
}: UseCmsPreviewIframeBindingOptions) {
    const designSystemOverridesRef = useRef(designSystemOverrides);

    useEffect(() => {
        designSystemOverridesRef.current = designSystemOverrides;
    }, [designSystemOverrides]);

    useEffect(() => {
        if (!isVisualBuilderOpen) {
            builderPreviewDocumentRef.current = null;
            clearPreviewSelectionHighlight();
            return;
        }

        const iframe = builderPreviewIframeRef.current;
        if (!iframe) {
            return;
        }

        let teardownDocumentEvents: (() => void) | null = null;
        const bindPreviewDocument = () => {
            teardownDocumentEvents?.();
            teardownDocumentEvents = null;

            const doc = iframe.contentDocument;
            if (!doc) {
                return;
            }

            builderPreviewDocumentRef.current = doc;
            ensurePreviewSelectionStyle(doc);
            ensureBuilderStableCanvasStyle(doc);
            ensureDesignSystemTokensStyle(doc, designSystemOverridesRef.current);
            syncPreviewVisibleSections();
            syncBuilderPreviewDraftBindings();

            const handleDocumentInteraction = (event: MouseEvent) => {
                if (event.cancelable) {
                    event.preventDefault();
                }
                event.stopPropagation();

                const sectionElement = findPreviewSectionElement(doc, event);
                if (!sectionElement) {
                    return;
                }

                const interactionTarget = event.target instanceof HTMLElement ? event.target : sectionElement;
                let sectionKey = sectionElement.getAttribute('data-webu-section') ?? '';
                const fixedKey = detectFixedSectionKeyFromElement(interactionTarget, headerVariant || 'webu_header_01', footerVariant || 'webu_footer_01');
                if (fixedKey) {
                    sectionKey = fixedKey;
                } else if (!isFixedLayoutSectionKey(sectionKey)) {
                    const fallbackFixedKey = detectFixedSectionKeyFromElement(
                        sectionElement,
                        headerVariant || 'webu_header_01',
                        footerVariant || 'webu_footer_01',
                    );
                    if (fallbackFixedKey) {
                        sectionKey = fallbackFixedKey;
                    }
                }

                selectSectionByPreviewKey(sectionKey, sectionElement);
            };

            doc.addEventListener('click', handleDocumentInteraction, true);
            const disconnectObserver = observeDOMMapInvalidation(doc);
            teardownDocumentEvents = () => {
                doc.removeEventListener('click', handleDocumentInteraction, true);
                disconnectObserver();
            };
        };

        bindPreviewDocument();
        iframe.addEventListener('load', bindPreviewDocument);

        return () => {
            iframe.removeEventListener('load', bindPreviewDocument);
            teardownDocumentEvents?.();
            teardownDocumentEvents = null;
            builderPreviewDocumentRef.current = null;
            clearPreviewSelectionHighlight();
        };
    }, [
        builderPreviewDocumentRef,
        builderPreviewIframeRef,
        clearPreviewSelectionHighlight,
        detectFixedSectionKeyFromElement,
        ensureBuilderStableCanvasStyle,
        ensureDesignSystemTokensStyle,
        ensurePreviewSelectionStyle,
        footerVariant,
        headerVariant,
        isFixedLayoutSectionKey,
        isVisualBuilderOpen,
        selectSectionByPreviewKey,
        syncBuilderPreviewDraftBindings,
        syncPreviewVisibleSections,
    ]);

    useEffect(() => {
        const doc = builderPreviewDocumentRef.current;
        if (!doc) {
            return;
        }

        ensureDesignSystemTokensStyle(doc, designSystemOverrides);
    }, [builderPreviewDocumentRef, designSystemOverrides, ensureDesignSystemTokensStyle]);

    useEffect(() => {
        if (!isVisualBuilderOpen) {
            return;
        }

        const doc = builderPreviewDocumentRef.current;
        if (!doc) {
            return;
        }

        if (selectedSectionLocalId) {
            const safeLocalId = selectedSectionLocalId.replace(/"/g, '\\"');
            const matched = doc.querySelector<HTMLElement>(`[data-webu-section-local-id="${safeLocalId}"]`);
            highlightPreviewSection(matched);
            return;
        }

        const sectionKey = selectedFixedSectionKey ?? '';
        if (sectionKey === '') {
            clearPreviewSelectionHighlight();
            return;
        }

        const safeSectionKey = sectionKey.replace(/"/g, '\\"');
        const matched = doc.querySelector<HTMLElement>(`[data-webu-section="${safeSectionKey}"]`);
        highlightPreviewSection(matched);
    }, [
        builderPreviewDocumentRef,
        clearPreviewSelectionHighlight,
        highlightPreviewSection,
        isVisualBuilderOpen,
        selectedFixedSectionKey,
        selectedSectionLocalId,
    ]);
}
