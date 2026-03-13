import { useEffect } from 'react';

import {
    buildEditableTargetFromMessagePayload,
    buildEditableTargetFromSection,
    editableTargetToMessagePayload,
    type BuilderEditableTarget,
} from '@/builder/editingState';
import type { SectionDraft } from '@/builder/state/useBuilderCanvasState';

type StateUpdater<T> = (value: T | ((current: T) => T)) => void;

export interface CmsNestedSelection {
    parentLocalId: string;
    path: number[];
}

interface UseCmsSelectionStateSyncOptions {
    sectionsDraft: SectionDraft[];
    selectedSectionLocalId: string | null;
    selectedSectionDraft: SectionDraft | null;
    selectedFixedSectionKey: string | null;
    selectedNestedSection: CmsNestedSelection | null;
    setSelectedSectionLocalId: StateUpdater<string | null>;
    setSelectedFixedSectionKey: StateUpdater<string | null>;
    setSelectedNestedSection: StateUpdater<CmsNestedSelection | null>;
    setSelectedBuilderTarget: StateUpdater<BuilderEditableTarget | null>;
    normalizeSectionTypeKey: (key: string | null | undefined) => string;
    resolveFixedSectionComponentName: (key: string | null) => string | null;
}

function syncSelectedSectionTarget(
    current: BuilderEditableTarget | null,
    nextBase: BuilderEditableTarget,
): BuilderEditableTarget {
    if (!current || current.sectionLocalId !== nextBase.sectionLocalId) {
        return nextBase;
    }

    const currentPayload = editableTargetToMessagePayload(current);
    if (!currentPayload) {
        return {
            ...nextBase,
            pageId: current.pageId ?? nextBase.pageId ?? null,
            pageSlug: current.pageSlug ?? nextBase.pageSlug ?? null,
            pageTitle: current.pageTitle ?? nextBase.pageTitle ?? null,
            textPreview: current.textPreview ?? nextBase.textPreview ?? null,
            responsiveContext: current.responsiveContext ?? nextBase.responsiveContext ?? null,
        };
    }

    const rebuiltCurrentTarget = buildEditableTargetFromMessagePayload({
        ...currentPayload,
        pageId: current.pageId ?? nextBase.pageId ?? null,
        pageSlug: current.pageSlug ?? nextBase.pageSlug ?? null,
        pageTitle: current.pageTitle ?? nextBase.pageTitle ?? null,
        sectionLocalId: nextBase.sectionLocalId,
        sectionKey: nextBase.sectionKey,
        componentType: nextBase.componentType,
        componentName: current.componentName ?? nextBase.componentName ?? null,
        selector: current.selector ?? nextBase.selector ?? null,
        textPreview: current.textPreview ?? nextBase.textPreview ?? null,
        props: nextBase.props ?? current.props ?? null,
        editableFields: current.editableFields ?? nextBase.editableFields ?? [],
        currentBreakpoint: current.responsiveContext?.currentBreakpoint ?? null,
        currentInteractionState: current.responsiveContext?.currentInteractionState ?? null,
    });

    return rebuiltCurrentTarget ?? {
        ...nextBase,
        pageId: current.pageId ?? nextBase.pageId ?? null,
        pageSlug: current.pageSlug ?? nextBase.pageSlug ?? null,
        pageTitle: current.pageTitle ?? nextBase.pageTitle ?? null,
        textPreview: current.textPreview ?? nextBase.textPreview ?? null,
        responsiveContext: current.responsiveContext ?? nextBase.responsiveContext ?? null,
    };
}

export function useCmsSelectionStateSync({
    sectionsDraft,
    selectedSectionLocalId,
    selectedSectionDraft,
    selectedFixedSectionKey,
    selectedNestedSection,
    setSelectedSectionLocalId,
    setSelectedFixedSectionKey,
    setSelectedNestedSection,
    setSelectedBuilderTarget,
    normalizeSectionTypeKey,
    resolveFixedSectionComponentName,
}: UseCmsSelectionStateSyncOptions): void {
    useEffect(() => {
        if (sectionsDraft.length === 0) {
            if (selectedSectionLocalId !== null) {
                setSelectedSectionLocalId(null);
            }

            return;
        }

        if (selectedSectionLocalId !== null && !sectionsDraft.some((section) => section.localId === selectedSectionLocalId)) {
            setSelectedSectionLocalId(null);
        }
    }, [sectionsDraft, selectedSectionLocalId, setSelectedSectionLocalId]);

    useEffect(() => {
        if (selectedSectionLocalId !== null && selectedFixedSectionKey !== null) {
            setSelectedFixedSectionKey(null);
        }
    }, [selectedFixedSectionKey, selectedSectionLocalId, setSelectedFixedSectionKey]);

    useEffect(() => {
        if (selectedNestedSection != null && selectedSectionLocalId !== selectedNestedSection.parentLocalId) {
            setSelectedNestedSection(null);
        }
    }, [selectedSectionLocalId, selectedNestedSection, setSelectedNestedSection]);

    useEffect(() => {
        if (!selectedSectionDraft) {
            setSelectedBuilderTarget((current) => {
                if (!current) {
                    return null;
                }

                const currentSectionKey = normalizeSectionTypeKey(current.sectionKey ?? current.componentType ?? '');
                const normalizedFixedSectionKey = normalizeSectionTypeKey(selectedFixedSectionKey ?? '');
                if (normalizedFixedSectionKey !== '' && currentSectionKey === normalizedFixedSectionKey) {
                    return {
                        ...current,
                        sectionLocalId: null,
                        sectionKey: selectedFixedSectionKey,
                        componentType: selectedFixedSectionKey,
                        componentName: resolveFixedSectionComponentName(selectedFixedSectionKey) ?? current.componentName,
                        sectionId: null,
                    };
                }

                return null;
            });
            return;
        }

        setSelectedBuilderTarget((current) => {
            const nextBase = buildEditableTargetFromSection(selectedSectionDraft);
            if (!nextBase) {
                return current;
            }

            return syncSelectedSectionTarget(current, nextBase);
        });
    }, [
        normalizeSectionTypeKey,
        resolveFixedSectionComponentName,
        selectedFixedSectionKey,
        selectedSectionDraft,
        setSelectedBuilderTarget,
    ]);
}
