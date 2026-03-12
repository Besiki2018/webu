import { useCallback } from 'react';

import type { CmsNestedSelection } from '@/builder/cms/useCmsSelectionStateSync';
import { buildEditableTargetFromSection, type BuilderEditableTarget, type BuilderSidebarMode } from '@/builder/editingState';
import type { SectionDraft } from '@/builder/state/useBuilderCanvasState';

type StateUpdater<T> = (value: T | ((current: T) => T)) => void;

interface UseCmsSidebarSelectionActionsOptions {
    sectionsDraft: SectionDraft[];
    setSelectedSectionLocalId: StateUpdater<string | null>;
    setSelectedFixedSectionKey: StateUpdater<string | null>;
    setSelectedNestedSection: StateUpdater<CmsNestedSelection | null>;
    setSelectedBuilderTarget: StateUpdater<BuilderEditableTarget | null>;
    setBuilderSidebarMode: (mode: BuilderSidebarMode) => void;
    ensurePreviewSectionVisibility: (sectionKey: string, label?: string | null) => void;
}

export function useCmsSidebarSelectionActions({
    sectionsDraft,
    setSelectedSectionLocalId,
    setSelectedFixedSectionKey,
    setSelectedNestedSection,
    setSelectedBuilderTarget,
    setBuilderSidebarMode,
    ensurePreviewSectionVisibility,
}: UseCmsSidebarSelectionActionsOptions) {
    const findSectionByLocalId = useCallback((localId: string | null) => {
        if (!localId) {
            return null;
        }

        return sectionsDraft.find((section) => section.localId === localId) ?? null;
    }, [sectionsDraft]);

    const handleOpenElementsSidebar = useCallback(() => {
        setBuilderSidebarMode('elements');
    }, [setBuilderSidebarMode]);

    const handleOpenSettingsSidebar = useCallback(() => {
        setBuilderSidebarMode('settings');
    }, [setBuilderSidebarMode]);

    const handleOpenDesignSystemSidebar = useCallback(() => {
        setBuilderSidebarMode('design-system');
    }, [setBuilderSidebarMode]);

    const handleFocusSection = useCallback((localId: string) => {
        setSelectedFixedSectionKey(null);
        setSelectedNestedSection(null);
        setSelectedSectionLocalId(localId);
        setSelectedBuilderTarget(buildEditableTargetFromSection(findSectionByLocalId(localId)));
        setBuilderSidebarMode('settings');
    }, [
        findSectionByLocalId,
        setBuilderSidebarMode,
        setSelectedBuilderTarget,
        setSelectedFixedSectionKey,
        setSelectedNestedSection,
        setSelectedSectionLocalId,
    ]);

    const handleOpenWarningSection = useCallback((localId: string, sectionType: string, sectionLabel: string) => {
        setSelectedFixedSectionKey(null);
        setSelectedNestedSection(null);
        setSelectedSectionLocalId(localId);
        setSelectedBuilderTarget(buildEditableTargetFromSection(findSectionByLocalId(localId)));
        setBuilderSidebarMode('settings');

        if (sectionType.trim() !== '') {
            ensurePreviewSectionVisibility(sectionType, sectionLabel);
        }
    }, [
        ensurePreviewSectionVisibility,
        findSectionByLocalId,
        setBuilderSidebarMode,
        setSelectedBuilderTarget,
        setSelectedFixedSectionKey,
        setSelectedNestedSection,
        setSelectedSectionLocalId,
    ]);

    const handleSelectNestedSection = useCallback((parentLocalId: string, path: number[]) => {
        const parentSection = findSectionByLocalId(parentLocalId);
        setSelectedSectionLocalId(parentLocalId);
        setSelectedNestedSection({ parentLocalId, path });
        setSelectedBuilderTarget(buildEditableTargetFromSection(parentSection));
        setBuilderSidebarMode('settings');
    }, [
        findSectionByLocalId,
        setBuilderSidebarMode,
        setSelectedBuilderTarget,
        setSelectedNestedSection,
        setSelectedSectionLocalId,
    ]);

    const handleOpenFixedSection = useCallback((fixedSectionKey: string, label?: string | null) => {
        setSelectedSectionLocalId(null);
        setSelectedFixedSectionKey(fixedSectionKey);
        setSelectedNestedSection(null);
        setSelectedBuilderTarget(null);
        setBuilderSidebarMode('settings');
        ensurePreviewSectionVisibility(fixedSectionKey, label ?? null);
    }, [
        ensurePreviewSectionVisibility,
        setBuilderSidebarMode,
        setSelectedBuilderTarget,
        setSelectedFixedSectionKey,
        setSelectedNestedSection,
        setSelectedSectionLocalId,
    ]);

    const handleOpenSiteSettings = useCallback(() => {
        setSelectedSectionLocalId(null);
        setSelectedFixedSectionKey(null);
        setSelectedNestedSection(null);
        setSelectedBuilderTarget(null);
        setBuilderSidebarMode('settings');
    }, [
        setBuilderSidebarMode,
        setSelectedBuilderTarget,
        setSelectedFixedSectionKey,
        setSelectedNestedSection,
        setSelectedSectionLocalId,
    ]);

    const handleCloseFixedSection = useCallback(() => {
        setSelectedFixedSectionKey(null);
        setSelectedBuilderTarget(null);
    }, [setSelectedBuilderTarget, setSelectedFixedSectionKey]);

    const handleBackToParentNestedSection = useCallback(() => {
        setSelectedNestedSection(null);
        setSelectedBuilderTarget((current) => {
            if (!current?.sectionLocalId) {
                return null;
            }

            return buildEditableTargetFromSection(findSectionByLocalId(current.sectionLocalId));
        });
    }, [findSectionByLocalId, setSelectedBuilderTarget, setSelectedNestedSection]);

    const handleResetSidebarToElements = useCallback(() => {
        setSelectedSectionLocalId(null);
        setSelectedNestedSection(null);
        setSelectedFixedSectionKey(null);
        setSelectedBuilderTarget(null);
        setBuilderSidebarMode('elements');
    }, [
        setBuilderSidebarMode,
        setSelectedBuilderTarget,
        setSelectedFixedSectionKey,
        setSelectedNestedSection,
        setSelectedSectionLocalId,
    ]);

    return {
        handleOpenElementsSidebar,
        handleOpenSettingsSidebar,
        handleOpenDesignSystemSidebar,
        handleFocusSection,
        handleOpenWarningSection,
        handleSelectNestedSection,
        handleOpenFixedSection,
        handleOpenSiteSettings,
        handleCloseFixedSection,
        handleBackToParentNestedSection,
        handleResetSidebarToElements,
    };
}
