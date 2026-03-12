import { useCallback } from 'react';

import type { CmsNestedSelection } from '@/builder/cms/useCmsSelectionStateSync';
import {
    buildEditableTargetFromSection,
    type BuilderEditableTarget,
    type BuilderSidebarMode,
} from '@/builder/editingState';
import type { SectionDraft } from '@/builder/state/useBuilderCanvasState';

type StateUpdater<T> = (value: T | ((current: T) => T)) => void;

interface UseCmsPreviewSelectionControllerOptions {
    sectionsDraftRef: { current: SectionDraft[] };
    normalizeSectionTypeKey: (key: string) => string;
    isFixedLayoutSectionKey: (key: string) => boolean;
    syncFixedLayoutVariant: (key: string) => void;
    setSelectedSectionLocalId: StateUpdater<string | null>;
    setSelectedFixedSectionKey: StateUpdater<string | null>;
    setSelectedNestedSection: StateUpdater<CmsNestedSelection | null>;
    setSelectedBuilderTarget: StateUpdater<BuilderEditableTarget | null>;
    setBuilderSidebarMode: (mode: BuilderSidebarMode) => void;
    highlightPreviewSection: (element: HTMLElement | null) => void;
}

export function useCmsPreviewSelectionController({
    sectionsDraftRef,
    normalizeSectionTypeKey,
    isFixedLayoutSectionKey,
    syncFixedLayoutVariant,
    setSelectedSectionLocalId,
    setSelectedFixedSectionKey,
    setSelectedNestedSection,
    setSelectedBuilderTarget,
    setBuilderSidebarMode,
    highlightPreviewSection,
}: UseCmsPreviewSelectionControllerOptions) {
    const selectSectionByPreviewKey = useCallback((sectionKey: string, element: HTMLElement | null = null) => {
        const compact = (value: string) => normalizeSectionTypeKey(value).replace(/[-_\s]+/g, '');
        const normalizedKey = normalizeSectionTypeKey(sectionKey);
        if (normalizedKey === '') {
            return;
        }

        if (isFixedLayoutSectionKey(normalizedKey)) {
            setSelectedSectionLocalId(null);
            setSelectedFixedSectionKey(normalizedKey);
            setSelectedNestedSection(null);
            setSelectedBuilderTarget(null);
            syncFixedLayoutVariant(normalizedKey);
            setBuilderSidebarMode('settings');
            highlightPreviewSection(element);
            return;
        }

        const drafts = sectionsDraftRef.current;
        const previewLocalId = typeof element?.getAttribute('data-webu-section-local-id') === 'string'
            ? element.getAttribute('data-webu-section-local-id')!.trim()
            : '';
        if (previewLocalId !== '') {
            const exactDraft = drafts.find((section) => section.localId === previewLocalId) ?? null;
            if (exactDraft) {
                setSelectedFixedSectionKey(null);
                setSelectedNestedSection(null);
                setSelectedSectionLocalId(exactDraft.localId);
                setSelectedBuilderTarget(buildEditableTargetFromSection(exactDraft));
                setBuilderSidebarMode('settings');
                highlightPreviewSection(element);
                return;
            }
        }

        const exactMatches = drafts.filter((section) => normalizeSectionTypeKey(section.type) === normalizedKey);
        const compactKey = compact(normalizedKey);
        const compactMatches = exactMatches.length > 0
            ? exactMatches
            : drafts.filter((section) => compact(section.type) === compactKey);
        const matchedDraft = compactMatches.length === 1 ? compactMatches[0] : null;
        if (!matchedDraft) {
            return;
        }

        setSelectedFixedSectionKey(null);
        setSelectedNestedSection(null);
        setSelectedSectionLocalId(matchedDraft.localId);
        setSelectedBuilderTarget(buildEditableTargetFromSection(matchedDraft));
        setBuilderSidebarMode('settings');
        highlightPreviewSection(element);
    }, [
        highlightPreviewSection,
        isFixedLayoutSectionKey,
        normalizeSectionTypeKey,
        sectionsDraftRef,
        setBuilderSidebarMode,
        setSelectedBuilderTarget,
        setSelectedFixedSectionKey,
        setSelectedNestedSection,
        setSelectedSectionLocalId,
        syncFixedLayoutVariant,
    ]);

    return {
        selectSectionByPreviewKey,
    };
}
