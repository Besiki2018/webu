import { useCallback, useEffect } from 'react';

import type { CmsNestedSelection } from '@/builder/cms/useCmsSelectionStateSync';
import type { BuilderEditableTarget, BuilderSidebarMode } from '@/builder/editingState';

type StateUpdater<T> = (value: T | ((current: T) => T)) => void;

interface UseCmsPageSelectionLifecycleOptions<TPageDetail, TBindingValidation> {
    selectedPageId: number | null;
    selectedSectionLocalId: string | null;
    isVisualBuilderOpen: boolean;
    pageDetailRequestSeqRef: { current: number };
    selectedPageIdRef: { current: number | null };
    loadPageDetail: (pageId: number) => Promise<unknown> | void;
    setSelectedPageDetail: StateUpdater<TPageDetail | null>;
    setPageEditorMode: (mode: 'builder' | 'text') => void;
    setPageRichTextHtml: (value: string) => void;
    setSelectedSectionLocalId: StateUpdater<string | null>;
    setSelectedBuilderTarget: StateUpdater<BuilderEditableTarget | null>;
    setSelectedNestedSection: StateUpdater<CmsNestedSelection | null>;
    setSelectedFixedSectionKey: StateUpdater<string | null>;
    setBindingValidationResult: StateUpdater<TBindingValidation | null>;
    setBuilderSidebarMode: (mode: BuilderSidebarMode) => void;
}

export function useCmsPageSelectionLifecycle<TPageDetail, TBindingValidation>({
    selectedPageId,
    selectedSectionLocalId,
    isVisualBuilderOpen,
    pageDetailRequestSeqRef,
    selectedPageIdRef,
    loadPageDetail,
    setSelectedPageDetail,
    setPageEditorMode,
    setPageRichTextHtml,
    setSelectedSectionLocalId,
    setSelectedBuilderTarget,
    setSelectedNestedSection,
    setSelectedFixedSectionKey,
    setBindingValidationResult,
    setBuilderSidebarMode,
}: UseCmsPageSelectionLifecycleOptions<TPageDetail, TBindingValidation>) {
    const resetBuilderSelection = useCallback((clearBindingValidation = false) => {
        setSelectedSectionLocalId(null);
        setSelectedBuilderTarget(null);
        setSelectedNestedSection(null);
        setSelectedFixedSectionKey(null);
        if (clearBindingValidation) {
            setBindingValidationResult(null);
        }
        setBuilderSidebarMode('elements');
    }, [
        setBindingValidationResult,
        setBuilderSidebarMode,
        setSelectedBuilderTarget,
        setSelectedFixedSectionKey,
        setSelectedNestedSection,
        setSelectedSectionLocalId,
    ]);

    const clearActiveSectionSelection = useCallback(() => {
        setSelectedSectionLocalId(null);
        setBuilderSidebarMode('elements');
    }, [setBuilderSidebarMode, setSelectedSectionLocalId]);

    useEffect(() => {
        selectedPageIdRef.current = selectedPageId;

        if (selectedPageId === null) {
            pageDetailRequestSeqRef.current += 1;
            setSelectedPageDetail(null);
            setPageEditorMode('builder');
            setPageRichTextHtml('');
            resetBuilderSelection(true);
            return;
        }

        resetBuilderSelection(true);
        void loadPageDetail(selectedPageId);
    }, [
        loadPageDetail,
        pageDetailRequestSeqRef,
        resetBuilderSelection,
        selectedPageId,
        selectedPageIdRef,
        setPageEditorMode,
        setPageRichTextHtml,
        setSelectedPageDetail,
    ]);

    useEffect(() => {
        if (!isVisualBuilderOpen) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key !== 'Escape') {
                return;
            }

            if (selectedSectionLocalId !== null) {
                clearActiveSectionSelection();
            }
        };

        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [clearActiveSectionSelection, isVisualBuilderOpen, selectedSectionLocalId]);

    return {
        clearActiveSectionSelection,
        resetBuilderSelection,
    };
}
