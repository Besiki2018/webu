import { useCallback, useMemo, type Dispatch, type MutableRefObject, type SetStateAction } from 'react';
import { toast } from 'sonner';
import type { DragEndEvent, DragStartEvent } from '@dnd-kit/core';

import type { CmsNestedSelection } from '@/builder/cms/useCmsSelectionStateSync';
import {
    CMS_LAYOUT_PRIMITIVE_SECTION_KEYS,
    CMS_NESTED_ADD_SECTION_KEYS,
} from '@/builder/cms/nestedSectionTree';
import {
    type BuilderEditableTarget,
    type BuilderSidebarMode,
} from '@/builder/editingState';
import {
    applyBuilderUpdatePipeline,
    type BuilderUpdateOperation,
} from '@/builder/state/updatePipeline';
import type { SectionDraft } from '@/builder/state/useBuilderCanvasState';
import { isRecord } from '@/builder/state/sectionProps';
import { getInsertIndex } from '@/builder/visual/treeUtils';
import { parseVisualDropId, type DropTarget, VISUAL_DROP_PREFIX } from '@/builder/visual/types';
import {
    getComponentRenderEntry,
    getDefaultProps as getBuilderDefaultProps,
    isComponentAllowedForProjectSiteType,
    isValidComponent,
    resolveComponentRegistryKey,
} from '@/builder/componentRegistry';
import type { ProjectSiteType } from '@/builder/projectTypes';

type StateUpdater<T> = (value: T | ((current: T) => T)) => void;

interface UseCmsStructureMutationHandlersOptions {
    sectionsDraftRef: MutableRefObject<SectionDraft[]>;
    scheduleStructuralDraftPersistRef: MutableRefObject<() => void>;
    syncPreviewVisibleSections: () => void;
    nextSectionLocalId: () => string;
    projectSiteType: ProjectSiteType | string;
    selectedSectionLocalId: string | null;
    selectedBuilderTarget: BuilderEditableTarget | null;
    selectedNestedSectionParentLocalId: string | null;
    isEmbeddedSidebarMode: boolean;
    canvasDropId: string;
    visualDropId: string;
    sectionSchemaByKey: Map<string, unknown>;
    templateSectionPreviewByKey: Map<string, { defaultProps?: Record<string, unknown> | null }>;
    sectionDisplayLabelByKey: Map<string, string>;
    hydrateSectionDefaultsFromCms: (sectionType: string, props: Record<string, unknown>) => Record<string, unknown>;
    normalizeSectionTypeKey: (key: string) => string;
    isFixedLayoutSectionKey: (key: string) => boolean;
    buildPropsFromSchema: (schema: unknown) => Record<string, unknown>;
    cloneRecord: (input: Record<string, unknown>) => Record<string, unknown>;
    formatPropsText: (value: unknown) => string;
    ensurePreviewSectionVisibility: (sectionKey: string, label?: string | null) => void;
    extractLibrarySectionKey: (dragId: string) => string | null;
    setSectionsDraft: Dispatch<SetStateAction<SectionDraft[]>>;
    applyMutationState: (next: {
        sectionsDraft: SectionDraft[];
        selectedSectionLocalId: string | null;
        selectedBuilderTarget: BuilderEditableTarget | null;
        mutationId?: string | null;
    }) => void;
    setSelectedFixedSectionKey: StateUpdater<string | null>;
    setSelectedNestedSection: StateUpdater<CmsNestedSelection | null>;
    setBuilderSidebarMode: (mode: BuilderSidebarMode) => void;
    setActiveDragId: (value: string | null) => void;
    setBuilderCurrentDropTarget: StateUpdater<DropTarget | null>;
    onOperationsApplied?: (operations: BuilderUpdateOperation[], nextSectionsDraft: SectionDraft[]) => void;
    t: (key: string) => string;
}

function pathStartsWith(path: number[], prefix: number[]): boolean {
    return prefix.every((segment, index) => path[index] === segment);
}

function repairNestedSelectionAfterRemove(
    current: CmsNestedSelection | null,
    parentLocalId: string,
    removedPath: number[],
): CmsNestedSelection | null {
    if (!current || current.parentLocalId !== parentLocalId || removedPath.length === 0) {
        return current;
    }

    if (pathStartsWith(current.path, removedPath)) {
        return null;
    }

    const parentPath = removedPath.slice(0, -1);
    const removedIndex = removedPath[removedPath.length - 1] ?? -1;
    if (current.path.length <= parentPath.length || !pathStartsWith(current.path, parentPath)) {
        return current;
    }

    const currentIndex = current.path[parentPath.length] ?? -1;
    if (currentIndex <= removedIndex) {
        return current;
    }

    const nextPath = [...current.path];
    nextPath[parentPath.length] = currentIndex - 1;
    return {
        ...current,
        path: nextPath,
    };
}

function repairNestedSelectionAfterReorder(
    current: CmsNestedSelection | null,
    parentLocalId: string,
    movedPath: number[],
    toIndex: number,
): CmsNestedSelection | null {
    if (!current || current.parentLocalId !== parentLocalId || movedPath.length === 0) {
        return current;
    }

    const parentPath = movedPath.slice(0, -1);
    const fromIndex = movedPath[movedPath.length - 1] ?? -1;
    if (fromIndex < 0 || current.path.length <= parentPath.length || !pathStartsWith(current.path, parentPath)) {
        return current;
    }

    const nextPath = [...current.path];
    const currentIndex = nextPath[parentPath.length] ?? -1;
    if (pathStartsWith(nextPath, movedPath)) {
        nextPath[parentPath.length] = toIndex;
        return {
            ...current,
            path: nextPath,
        };
    }

    if (fromIndex < toIndex && currentIndex > fromIndex && currentIndex <= toIndex) {
        nextPath[parentPath.length] = currentIndex - 1;
        return {
            ...current,
            path: nextPath,
        };
    }

    if (toIndex < fromIndex && currentIndex >= toIndex && currentIndex < fromIndex) {
        nextPath[parentPath.length] = currentIndex + 1;
        return {
            ...current,
            path: nextPath,
        };
    }

    return current;
}

export function useCmsStructureMutationHandlers({
    sectionsDraftRef,
    scheduleStructuralDraftPersistRef,
    syncPreviewVisibleSections,
    nextSectionLocalId,
    projectSiteType,
    selectedSectionLocalId,
    selectedBuilderTarget,
    selectedNestedSectionParentLocalId,
    isEmbeddedSidebarMode,
    canvasDropId,
    visualDropId,
    sectionSchemaByKey,
    templateSectionPreviewByKey,
    sectionDisplayLabelByKey,
    hydrateSectionDefaultsFromCms,
    normalizeSectionTypeKey,
    isFixedLayoutSectionKey,
    buildPropsFromSchema,
    cloneRecord,
    formatPropsText,
    ensurePreviewSectionVisibility,
    extractLibrarySectionKey,
    applyMutationState,
    setSelectedFixedSectionKey,
    setSelectedNestedSection,
    setBuilderSidebarMode,
    setActiveDragId,
    setBuilderCurrentDropTarget,
    onOperationsApplied,
    t,
}: UseCmsStructureMutationHandlersOptions) {
    const layoutPrimitiveSectionKeys = useMemo<string[]>(
        () => [...CMS_LAYOUT_PRIMITIVE_SECTION_KEYS],
        [],
    );
    const addInsideSectionOptions = useMemo(() => CMS_NESTED_ADD_SECTION_KEYS.map((key) => ({
        key,
        label: sectionDisplayLabelByKey.get(normalizeSectionTypeKey(key)) ?? sectionDisplayLabelByKey.get(key) ?? key,
    })), [normalizeSectionTypeKey, sectionDisplayLabelByKey]);

    const applyStructureMutationState = useCallback((
        sections: SectionDraft[],
        selection: {
            sectionLocalId: string | null;
            target: BuilderEditableTarget | null;
        },
    ) => {
        sectionsDraftRef.current = sections;
        applyMutationState({
            sectionsDraft: sections,
            selectedSectionLocalId: selection.sectionLocalId,
            selectedBuilderTarget: selection.target,
        });
        syncPreviewVisibleSections();
    }, [applyMutationState, sectionsDraftRef, syncPreviewVisibleSections]);

    const buildSectionDefaultProps = useCallback((sectionKey: string) => {
        const normalizedSectionKey = normalizeSectionTypeKey(sectionKey);
        if (normalizedSectionKey === '' || isFixedLayoutSectionKey(normalizedSectionKey)) {
            return null;
        }

        const templateSchema = sectionSchemaByKey.get(sectionKey);
        const templateDefaults = templateSectionPreviewByKey.get(normalizedSectionKey)?.defaultProps ?? null;
        let defaultProps = templateDefaults
            ? cloneRecord(templateDefaults)
            : buildPropsFromSchema(templateSchema);

        if (Object.keys(defaultProps).length === 0) {
            const registryDefaults = getBuilderDefaultProps(sectionKey);
            if (Object.keys(registryDefaults).length > 0) {
                defaultProps = cloneRecord(registryDefaults);
            }
        }

        if (Object.keys(defaultProps).length === 0) {
            const registryEntry = getComponentRenderEntry(sectionKey);
            if (registryEntry?.defaults && typeof registryEntry.defaults === 'object' && !Array.isArray(registryEntry.defaults)) {
                defaultProps = cloneRecord(registryEntry.defaults as Record<string, unknown>);
            }
        }

        return {
            normalizedSectionKey,
            hydratedDefaultProps: hydrateSectionDefaultsFromCms(normalizedSectionKey, defaultProps),
        };
    }, [
        buildPropsFromSchema,
        cloneRecord,
        hydrateSectionDefaultsFromCms,
        isFixedLayoutSectionKey,
        normalizeSectionTypeKey,
        sectionSchemaByKey,
        templateSectionPreviewByKey,
    ]);

    const createSectionDraft = useCallback(({ sectionType, props, localId, bindingMeta }: {
        sectionType: string;
        props?: Record<string, unknown>;
        localId?: string | null;
        bindingMeta?: Record<string, unknown> | null;
    }): SectionDraft => ({
        localId: typeof localId === 'string' && localId.trim() !== '' ? localId.trim() : nextSectionLocalId(),
        type: sectionType,
        propsText: formatPropsText(props ?? {}),
        propsError: null,
        bindingMeta: bindingMeta ?? null,
    }), [formatPropsText, nextSectionLocalId]);

    const runStructureOperations = useCallback((operations: BuilderUpdateOperation[]) => (
        (() => {
            const result = applyBuilderUpdatePipeline({
            sectionsDraft: sectionsDraftRef.current,
            selectedSectionLocalId,
            selectedBuilderTarget,
        }, operations, {
            createSection: createSectionDraft,
        });

            if (result.ok && result.changed) {
                onOperationsApplied?.(operations, result.state.sectionsDraft);
            }

            return result;
        })()
    ), [createSectionDraft, onOperationsApplied, sectionsDraftRef, selectedBuilderTarget, selectedSectionLocalId]);

    const addSectionByKey = useCallback((
        sectionKey: string,
        source: 'library' | 'toolbar' = 'toolbar',
        options?: { insertIndex?: number; localId?: string | null },
    ) => {
        const resolvedSectionKey = resolveComponentRegistryKey(sectionKey) ?? normalizeSectionTypeKey(sectionKey);
        if (resolvedSectionKey === '') {
            return;
        }

        const isFixedLayoutSection = isFixedLayoutSectionKey(resolvedSectionKey);

        if (!isFixedLayoutSection && !isValidComponent(resolvedSectionKey)) {
            toast.error(t('Component is not registered for this builder'));
            return;
        }

        if (!isFixedLayoutSection && !isComponentAllowedForProjectSiteType(resolvedSectionKey, projectSiteType)) {
            toast.error(t('Component is not allowed for this project type'));
            return;
        }

        const sectionDefaults = buildSectionDefaultProps(resolvedSectionKey);
        if (!sectionDefaults) {
            return;
        }

        const { hydratedDefaultProps, normalizedSectionKey } = sectionDefaults;
        const localId = typeof options?.localId === 'string' && options.localId.trim() !== ''
            ? options.localId.trim()
            : nextSectionLocalId();
        const insertIndex = typeof options?.insertIndex === 'number' ? options.insertIndex : null;
        const result = runStructureOperations([{
            kind: 'insert-section',
            source: source === 'toolbar' ? 'toolbar' : 'drag-drop',
            sectionType: normalizedSectionKey,
            props: hydratedDefaultProps,
            localId,
            insertIndex,
            selectInserted: true,
        }]);

        if (!result.ok || !result.changed) {
            return;
        }

        setSelectedFixedSectionKey(null);
        setSelectedNestedSection(null);
        applyStructureMutationState(result.state.sectionsDraft, {
            sectionLocalId: result.state.selectedSectionLocalId,
            target: result.state.selectedBuilderTarget,
        });
        setBuilderSidebarMode('settings');

        const sectionLabel = sectionDisplayLabelByKey.get(normalizedSectionKey)
            ?? sectionDisplayLabelByKey.get(resolvedSectionKey)
            ?? sectionDisplayLabelByKey.get(sectionKey)
            ?? normalizedSectionKey;
        if (!isEmbeddedSidebarMode) {
            setTimeout(() => ensurePreviewSectionVisibility(normalizedSectionKey, sectionLabel), 0);
        }

        if (source === 'library') {
            toast.success(t('Component added to page canvas'));
        }

        scheduleStructuralDraftPersistRef.current();
    }, [
        applyStructureMutationState,
        buildSectionDefaultProps,
        ensurePreviewSectionVisibility,
        isEmbeddedSidebarMode,
        nextSectionLocalId,
        projectSiteType,
        normalizeSectionTypeKey,
        runStructureOperations,
        scheduleStructuralDraftPersistRef,
        sectionDisplayLabelByKey,
        setBuilderSidebarMode,
        setSelectedFixedSectionKey,
        setSelectedNestedSection,
        selectedSectionLocalId,
        t,
    ]);

    const handleRemoveSection = useCallback((localId: string) => {
        const normalizedLocalId = localId.trim();
        const shouldResetSidebar = selectedSectionLocalId === normalizedLocalId
            || selectedNestedSectionParentLocalId === normalizedLocalId;
        const result = runStructureOperations([{
            kind: 'delete-section',
            source: 'toolbar',
            sectionLocalId: normalizedLocalId,
        }]);

        if (!result.ok || !result.changed) {
            return;
        }

        setSelectedNestedSection((current) => (
            current?.parentLocalId === normalizedLocalId ? null : current
        ));
        applyStructureMutationState(result.state.sectionsDraft, {
            sectionLocalId: result.state.selectedSectionLocalId,
            target: result.state.selectedBuilderTarget,
        });
        if (shouldResetSidebar) {
            setBuilderSidebarMode(result.state.selectedSectionLocalId ? 'settings' : 'elements');
        }

        scheduleStructuralDraftPersistRef.current();
    }, [
        applyStructureMutationState,
        runStructureOperations,
        scheduleStructuralDraftPersistRef,
        selectedNestedSectionParentLocalId,
        selectedSectionLocalId,
        setBuilderSidebarMode,
        setSelectedNestedSection,
    ]);

    const handleDuplicateSection = useCallback((localId: string) => {
        const duplicateId = nextSectionLocalId();
        const result = runStructureOperations([{
            kind: 'duplicate-section',
            source: 'toolbar',
            sectionLocalId: localId.trim(),
            newLocalId: duplicateId,
            selectDuplicate: true,
        }]);
        if (!result.ok || !result.changed) {
            return;
        }

        setSelectedFixedSectionKey(null);
        setSelectedNestedSection(null);
        applyStructureMutationState(result.state.sectionsDraft, {
            sectionLocalId: result.state.selectedSectionLocalId,
            target: result.state.selectedBuilderTarget,
        });
        setBuilderSidebarMode('settings');
        scheduleStructuralDraftPersistRef.current();
    }, [
        applyStructureMutationState,
        nextSectionLocalId,
        runStructureOperations,
        scheduleStructuralDraftPersistRef,
        setBuilderSidebarMode,
        setSelectedFixedSectionKey,
        setSelectedNestedSection,
    ]);

    const handleAddSectionInside = useCallback((localId: string, sectionKey: string) => {
        const sectionDefaults = buildSectionDefaultProps(sectionKey);
        if (!sectionDefaults) {
            return;
        }

        const result = runStructureOperations([{
            kind: 'insert-nested-section',
            source: 'toolbar',
            sectionLocalId: localId.trim(),
            sectionType: sectionDefaults.normalizedSectionKey,
            props: sectionDefaults.hydratedDefaultProps,
            nestedSectionPath: [],
        }]);
        if (!result.ok || !result.changed) {
            return;
        }

        applyStructureMutationState(result.state.sectionsDraft, {
            sectionLocalId: result.state.selectedSectionLocalId,
            target: result.state.selectedBuilderTarget,
        });

        toast.success(t('Section added inside'));
        scheduleStructuralDraftPersistRef.current();
    }, [
        applyStructureMutationState,
        buildSectionDefaultProps,
        runStructureOperations,
        scheduleStructuralDraftPersistRef,
        t,
    ]);

    const handleRemoveNestedSection = useCallback((parentLocalId: string, path: number[]) => {
        if (path.length === 0) {
            return;
        }

        const result = runStructureOperations([{
            kind: 'delete-nested-section',
            source: 'toolbar',
            sectionLocalId: parentLocalId.trim(),
            nestedSectionPath: path,
        }]);
        if (!result.ok || !result.changed) {
            return;
        }

        setSelectedNestedSection((current) => repairNestedSelectionAfterRemove(current, parentLocalId.trim(), path));
        applyStructureMutationState(result.state.sectionsDraft, {
            sectionLocalId: result.state.selectedSectionLocalId,
            target: result.state.selectedBuilderTarget,
        });

        toast.success(t('Nested section removed'));
        scheduleStructuralDraftPersistRef.current();
    }, [applyStructureMutationState, runStructureOperations, scheduleStructuralDraftPersistRef, setSelectedNestedSection, t]);

    const handleMoveNestedSection = useCallback((parentLocalId: string, path: number[], direction: 'up' | 'down') => {
        if (path.length === 0) {
            return;
        }

        const fromIndex = path[path.length - 1] ?? -1;
        const targetIndex = direction === 'up' ? fromIndex - 1 : fromIndex + 1;
        if (targetIndex < 0) {
            return;
        }

        const result = runStructureOperations([{
            kind: 'reorder-nested-section',
            source: 'toolbar',
            sectionLocalId: parentLocalId.trim(),
            nestedSectionPath: path,
            toIndex: targetIndex,
        }]);
        if (!result.ok || !result.changed) {
            return;
        }

        setSelectedNestedSection((current) => repairNestedSelectionAfterReorder(current, parentLocalId.trim(), path, targetIndex));
        applyStructureMutationState(result.state.sectionsDraft, {
            sectionLocalId: result.state.selectedSectionLocalId,
            target: result.state.selectedBuilderTarget,
        });

        scheduleStructuralDraftPersistRef.current();
    }, [applyStructureMutationState, runStructureOperations, scheduleStructuralDraftPersistRef, setSelectedNestedSection]);

    const handlePasteSection = useCallback(async () => {
        try {
            const text = await navigator.clipboard.readText();
            const raw = JSON.parse(text) as unknown;
            if (!raw || typeof raw !== 'object' || Array.isArray(raw)) {
                toast.error(t('Clipboard must contain a section object with type and props'));
                return;
            }

            const payload = raw as Record<string, unknown>;
            const type = typeof payload.type === 'string' ? payload.type.trim() : '';
            const resolvedType = resolveComponentRegistryKey(type) ?? normalizeSectionTypeKey(type);
            if (resolvedType === '') {
                toast.error(t('Section JSON must have a "type" string'));
                return;
            }

            if (isFixedLayoutSectionKey(resolvedType)) {
                toast.error(t('Cannot paste fixed header/footer section here'));
                return;
            }

            if (!isValidComponent(resolvedType)) {
                toast.error(t('Component is not registered for this builder'));
                return;
            }

            if (!isComponentAllowedForProjectSiteType(resolvedType, projectSiteType)) {
                toast.error(t('Component is not allowed for this project type'));
                return;
            }

            const props = isRecord(payload.props) ? payload.props : {};
            const bindingMeta = isRecord(payload.binding) ? payload.binding : null;
            const localId = nextSectionLocalId();
            const insertIndex = selectedSectionLocalId != null
                ? sectionsDraftRef.current.findIndex((section) => section.localId === selectedSectionLocalId) + 1
                : sectionsDraftRef.current.length;
            const targetIndex = insertIndex < 0 || insertIndex > sectionsDraftRef.current.length
                ? sectionsDraftRef.current.length
                : insertIndex;
            const result = runStructureOperations([{
                kind: 'insert-section',
                source: 'toolbar',
                sectionType: resolvedType,
                props,
                localId,
                bindingMeta,
                insertIndex: targetIndex,
                selectInserted: true,
            }]);
            if (!result.ok || !result.changed) {
                toast.error(result.errors[0]?.message ?? t('Failed to paste section'));
                return;
            }

            setSelectedFixedSectionKey(null);
            setSelectedNestedSection(null);
            applyStructureMutationState(result.state.sectionsDraft, {
                sectionLocalId: result.state.selectedSectionLocalId,
                target: result.state.selectedBuilderTarget,
            });
            setBuilderSidebarMode('settings');
            toast.success(t('Section pasted'));
            scheduleStructuralDraftPersistRef.current();
        } catch (error) {
            if (error instanceof SyntaxError) {
                toast.error(t('Invalid JSON in clipboard'));
            } else {
                toast.error(t('Failed to paste section'));
            }
        }
    }, [
        applyStructureMutationState,
        isFixedLayoutSectionKey,
        nextSectionLocalId,
        normalizeSectionTypeKey,
        projectSiteType,
        runStructureOperations,
        scheduleStructuralDraftPersistRef,
        sectionsDraftRef,
        selectedSectionLocalId,
        setBuilderSidebarMode,
        setSelectedFixedSectionKey,
        setSelectedNestedSection,
        t,
    ]);

    const handleMoveSection = useCallback((localId: string, direction: 'up' | 'down') => {
        const currentIndex = sectionsDraftRef.current.findIndex((item) => item.localId === localId);
        if (currentIndex < 0) {
            return;
        }

        const targetIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;
        if (targetIndex < 0 || targetIndex >= sectionsDraftRef.current.length) {
            return;
        }

        const result = runStructureOperations([{
            kind: 'reorder-section',
            source: 'toolbar',
            sectionLocalId: localId.trim(),
            toIndex: targetIndex,
        }]);
        if (!result.ok || !result.changed) {
            return;
        }

        applyStructureMutationState(result.state.sectionsDraft, {
            sectionLocalId: result.state.selectedSectionLocalId,
            target: result.state.selectedBuilderTarget,
        });

        scheduleStructuralDraftPersistRef.current();
    }, [applyStructureMutationState, runStructureOperations, scheduleStructuralDraftPersistRef, sectionsDraftRef]);

    const handleBuilderDragStart = useCallback((event: DragStartEvent) => {
        setActiveDragId(String(event.active.id));
        setBuilderCurrentDropTarget(null);
    }, [setActiveDragId, setBuilderCurrentDropTarget]);

    const handleBuilderDragEnd = useCallback((event: DragEndEvent) => {
        setActiveDragId(null);
        setBuilderCurrentDropTarget(null);

        const activeId = String(event.active.id);
        const overId = event.over ? String(event.over.id) : null;
        const currentSections = sectionsDraftRef.current;

        if (!overId) {
            return;
        }

        const librarySectionKey = extractLibrarySectionKey(activeId);
        if (librarySectionKey) {
            if (overId.startsWith(VISUAL_DROP_PREFIX)) {
                const parsed = parseVisualDropId(overId);
                if (parsed) {
                    const { position, sectionLocalId } = parsed;
                    if (position === 'inside' && sectionLocalId) {
                        handleAddSectionInside(sectionLocalId, librarySectionKey);
                    } else {
                        const insertIndex = getInsertIndex(currentSections, sectionLocalId, position);
                        if (insertIndex >= 0) {
                            addSectionByKey(librarySectionKey, 'library', { insertIndex });
                        } else {
                            addSectionByKey(librarySectionKey, 'library', { insertIndex: currentSections.length });
                        }
                    }
                }
                return;
            }

            let insertIndex = currentSections.length;
            if (overId === canvasDropId || overId === visualDropId) {
                if (selectedSectionLocalId) {
                    const selectedIndex = currentSections.findIndex((section) => section.localId === selectedSectionLocalId);
                    if (selectedIndex >= 0) {
                        insertIndex = selectedIndex + 1;
                    }
                }
            } else {
                const overIndex = currentSections.findIndex((section) => section.localId === overId);
                if (overIndex >= 0) {
                    insertIndex = overIndex;
                }
            }

            addSectionByKey(librarySectionKey, 'library', { insertIndex });
            return;
        }

        if (activeId === overId) {
            return;
        }

        const oldIndex = currentSections.findIndex((section) => section.localId === activeId);
        if (oldIndex === -1) {
            return;
        }

        const targetIndex = overId === canvasDropId || overId === visualDropId
            ? Math.max(0, currentSections.length - 1)
            : currentSections.findIndex((section) => section.localId === overId);
        if (targetIndex === -1) {
            return;
        }

        const result = runStructureOperations([{
            kind: 'reorder-section',
            source: 'drag-drop',
            sectionLocalId: activeId,
            toIndex: targetIndex,
        }]);
        if (!result.ok || !result.changed) {
            return;
        }

        applyStructureMutationState(result.state.sectionsDraft, {
            sectionLocalId: result.state.selectedSectionLocalId,
            target: result.state.selectedBuilderTarget,
        });

        scheduleStructuralDraftPersistRef.current();
    }, [
        addSectionByKey,
        applyStructureMutationState,
        canvasDropId,
        extractLibrarySectionKey,
        handleAddSectionInside,
        runStructureOperations,
        scheduleStructuralDraftPersistRef,
        sectionsDraftRef,
        selectedSectionLocalId,
        setActiveDragId,
        setBuilderCurrentDropTarget,
        visualDropId,
    ]);

    return {
        layoutPrimitiveSectionKeys,
        addInsideSectionOptions,
        addSectionByKey,
        handleRemoveSection,
        handleDuplicateSection,
        handleAddSectionInside,
        handleRemoveNestedSection,
        handleMoveNestedSection,
        handlePasteSection,
        handleMoveSection,
        handleBuilderDragStart,
        handleBuilderDragEnd,
    };
}
