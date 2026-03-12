import { useCallback, useMemo, type Dispatch, type MutableRefObject, type SetStateAction } from 'react';
import { toast } from 'sonner';
import type { DragEndEvent, DragStartEvent } from '@dnd-kit/core';

import type { CmsNestedSelection } from '@/builder/cms/useCmsSelectionStateSync';
import {
    CMS_LAYOUT_PRIMITIVE_SECTION_KEYS,
    CMS_NESTED_ADD_SECTION_KEYS,
    getSectionsArrayAtPath,
    replaceNestedSectionsAtPath,
} from '@/builder/cms/nestedSectionTree';
import {
    buildEditableTargetFromSection,
    type BuilderEditableTarget,
    type BuilderSidebarMode,
} from '@/builder/editingState';
import { applyBuilderUpdatePipeline } from '@/builder/state/updatePipeline';
import type { SectionDraft } from '@/builder/state/useBuilderCanvasState';
import { isRecord } from '@/builder/state/sectionProps';
import { duplicateSection, getInsertIndex, moveSection } from '@/builder/visual/treeUtils';
import { parseVisualDropId, type DropTarget, VISUAL_DROP_PREFIX } from '@/builder/visual/types';
import { getEntry, hasEntry } from '@/builder/registry/componentRegistry';

type StateUpdater<T> = (value: T | ((current: T) => T)) => void;

interface UseCmsStructureMutationHandlersOptions {
    sectionsDraftRef: MutableRefObject<SectionDraft[]>;
    scheduleStructuralDraftPersistRef: MutableRefObject<() => void>;
    syncPreviewVisibleSections: () => void;
    nextSectionLocalId: () => string;
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
    t: (key: string) => string;
}

function parseRecordJson(input: string): Record<string, unknown> | null {
    try {
        const parsed = JSON.parse(input || '{}');
        return isRecord(parsed) ? parsed : {};
    } catch {
        return null;
    }
}

export function useCmsStructureMutationHandlers({
    sectionsDraftRef,
    scheduleStructuralDraftPersistRef,
    syncPreviewVisibleSections,
    nextSectionLocalId,
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
    setSectionsDraft,
    applyMutationState,
    setSelectedFixedSectionKey,
    setSelectedNestedSection,
    setBuilderSidebarMode,
    setActiveDragId,
    setBuilderCurrentDropTarget,
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

        if (Object.keys(defaultProps).length === 0 && hasEntry(sectionKey)) {
            const registryEntry = getEntry(sectionKey);
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

    const addSectionByKey = useCallback((
        sectionKey: string,
        source: 'library' | 'toolbar' = 'toolbar',
        options?: { insertIndex?: number; localId?: string | null },
    ) => {
        const sectionDefaults = buildSectionDefaultProps(sectionKey);
        if (!sectionDefaults) {
            return;
        }

        const { hydratedDefaultProps, normalizedSectionKey } = sectionDefaults;
        const localId = typeof options?.localId === 'string' && options.localId.trim() !== ''
            ? options.localId.trim()
            : nextSectionLocalId();
        const insertIndex = typeof options?.insertIndex === 'number' ? options.insertIndex : null;
        const result = applyBuilderUpdatePipeline({
            sectionsDraft: sectionsDraftRef.current,
            selectedSectionLocalId,
            selectedBuilderTarget: null,
        }, [{
            kind: 'insert-section',
            source: source === 'toolbar' ? 'toolbar' : 'drag-drop',
            sectionType: normalizedSectionKey,
            props: hydratedDefaultProps,
            localId,
            insertIndex,
        }], {
            createSection: ({ sectionType, props, localId: insertedLocalId }) => ({
                localId: typeof insertedLocalId === 'string' && insertedLocalId.trim() !== '' ? insertedLocalId.trim() : nextSectionLocalId(),
                type: sectionType,
                propsText: formatPropsText(props ?? {}),
                propsError: null,
                bindingMeta: null,
            }),
        });

        if (!result.ok || !result.changed) {
            return;
        }

        const insertedSection = result.state.sectionsDraft.find((section) => section.localId === localId) ?? {
            localId,
            type: normalizedSectionKey,
            propsText: formatPropsText(hydratedDefaultProps),
            propsError: null,
            bindingMeta: null,
        };

        setSelectedFixedSectionKey(null);
        setSelectedNestedSection(null);
        applyStructureMutationState(result.state.sectionsDraft, {
            sectionLocalId: localId,
            target: buildEditableTargetFromSection(insertedSection),
        });
        setBuilderSidebarMode('settings');

        const sectionLabel = sectionDisplayLabelByKey.get(normalizedSectionKey)
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
        formatPropsText,
        isEmbeddedSidebarMode,
        nextSectionLocalId,
        normalizeSectionTypeKey,
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
        const result = applyBuilderUpdatePipeline({
            sectionsDraft: sectionsDraftRef.current,
            selectedSectionLocalId,
            selectedBuilderTarget,
        }, [{
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
            setBuilderSidebarMode('elements');
        }

        scheduleStructuralDraftPersistRef.current();
    }, [
        applyStructureMutationState,
        scheduleStructuralDraftPersistRef,
        sectionsDraftRef,
        selectedBuilderTarget,
        selectedNestedSectionParentLocalId,
        selectedSectionLocalId,
        setBuilderSidebarMode,
        setSelectedNestedSection,
    ]);

    const handleDuplicateSection = useCallback((localId: string) => {
        const duplicateId = nextSectionLocalId();
        let nextSelectedId: string | null = duplicateId;

        setSectionsDraft((prev) => {
            const next = duplicateSection(prev, localId, duplicateId);
            if (next === prev) {
                nextSelectedId = null;
                return prev;
            }

            sectionsDraftRef.current = next;
            return next;
        });

        if (nextSelectedId) {
            const duplicatedSection = sectionsDraftRef.current.find((section) => section.localId === nextSelectedId) ?? null;
            setSelectedFixedSectionKey(null);
            setSelectedNestedSection(null);
            applyStructureMutationState(sectionsDraftRef.current, {
                sectionLocalId: nextSelectedId,
                target: buildEditableTargetFromSection(duplicatedSection),
            });
            setBuilderSidebarMode('settings');
        }

        scheduleStructuralDraftPersistRef.current();
    }, [
        applyStructureMutationState,
        nextSectionLocalId,
        scheduleStructuralDraftPersistRef,
        sectionsDraftRef,
        setBuilderSidebarMode,
        setSectionsDraft,
        setSelectedFixedSectionKey,
        setSelectedNestedSection,
    ]);

    const handleAddSectionInside = useCallback((localId: string, sectionKey: string) => {
        const sectionDefaults = buildSectionDefaultProps(sectionKey);
        if (!sectionDefaults) {
            return;
        }

        setSectionsDraft((prev) => {
            const draft = prev.find((item) => item.localId === localId);
            if (!draft) {
                return prev;
            }

            const parsed = parseRecordJson(draft.propsText);
            if (!parsed) {
                return prev;
            }

            const sections = Array.isArray(parsed.sections) ? [...parsed.sections] : [];
            sections.push({
                type: sectionKey,
                props: sectionDefaults.hydratedDefaultProps,
            });
            const nextProps = { ...parsed, sections };
            const next = prev.map((item) => (
                item.localId === localId
                    ? { ...item, propsText: formatPropsText(nextProps) }
                    : item
            ));
            sectionsDraftRef.current = next;
            return next;
        });

        toast.success(t('Section added inside'));
        scheduleStructuralDraftPersistRef.current();
    }, [
        buildSectionDefaultProps,
        formatPropsText,
        scheduleStructuralDraftPersistRef,
        sectionsDraftRef,
        setSectionsDraft,
        t,
    ]);

    const handleRemoveNestedSection = useCallback((parentLocalId: string, path: number[]) => {
        if (path.length === 0) {
            return;
        }

        setSectionsDraft((prev) => {
            const draft = prev.find((item) => item.localId === parentLocalId);
            if (!draft) {
                return prev;
            }

            const parsed = parseRecordJson(draft.propsText);
            if (!parsed) {
                return prev;
            }

            const parentPath = path.slice(0, -1);
            const indexToRemove = path[path.length - 1];
            const sections = parentPath.length === 0
                ? (Array.isArray(parsed.sections) ? [...parsed.sections] : [])
                : (getSectionsArrayAtPath(parsed, parentPath) ?? []);
            const nextSections = [...sections];
            if (indexToRemove < 0 || indexToRemove >= nextSections.length) {
                return prev;
            }

            nextSections.splice(indexToRemove, 1);
            const nextProps = replaceNestedSectionsAtPath(parsed, parentPath, nextSections);
            const next = prev.map((item) => (
                item.localId === parentLocalId
                    ? { ...item, propsText: formatPropsText(nextProps) }
                    : item
            ));
            sectionsDraftRef.current = next;
            return next;
        });

        toast.success(t('Nested section removed'));
        scheduleStructuralDraftPersistRef.current();
    }, [formatPropsText, scheduleStructuralDraftPersistRef, sectionsDraftRef, setSectionsDraft, t]);

    const handleMoveNestedSection = useCallback((parentLocalId: string, path: number[], direction: 'up' | 'down') => {
        if (path.length === 0) {
            return;
        }

        setSectionsDraft((prev) => {
            const draft = prev.find((item) => item.localId === parentLocalId);
            if (!draft) {
                return prev;
            }

            const parsed = parseRecordJson(draft.propsText);
            if (!parsed) {
                return prev;
            }

            const parentPath = path.slice(0, -1);
            const index = path[path.length - 1];
            const sections = parentPath.length === 0
                ? (Array.isArray(parsed.sections) ? [...parsed.sections] : [])
                : (getSectionsArrayAtPath(parsed, parentPath) ?? []);
            const nextSections = [...sections];
            if (index < 0 || index >= nextSections.length) {
                return prev;
            }

            const targetIndex = direction === 'up' ? index - 1 : index + 1;
            if (targetIndex < 0 || targetIndex >= nextSections.length) {
                return prev;
            }

            const [item] = nextSections.splice(index, 1);
            nextSections.splice(targetIndex, 0, item);
            const nextProps = replaceNestedSectionsAtPath(parsed, parentPath, nextSections);
            const next = prev.map((draftItem) => (
                draftItem.localId === parentLocalId
                    ? { ...draftItem, propsText: formatPropsText(nextProps) }
                    : draftItem
            ));
            sectionsDraftRef.current = next;
            return next;
        });

        scheduleStructuralDraftPersistRef.current();
    }, [formatPropsText, scheduleStructuralDraftPersistRef, sectionsDraftRef, setSectionsDraft]);

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
            if (type === '') {
                toast.error(t('Section JSON must have a "type" string'));
                return;
            }

            if (isFixedLayoutSectionKey(normalizeSectionTypeKey(type))) {
                toast.error(t('Cannot paste fixed header/footer section here'));
                return;
            }

            const props = isRecord(payload.props) ? payload.props : {};
            const bindingMeta = isRecord(payload.binding) ? payload.binding : null;
            const localId = nextSectionLocalId();
            const newSection: SectionDraft = {
                localId,
                type,
                propsText: formatPropsText(props),
                propsError: null,
                bindingMeta,
            };

            setSectionsDraft((prev) => {
                const insertIndex = selectedSectionLocalId != null
                    ? prev.findIndex((section) => section.localId === selectedSectionLocalId) + 1
                    : prev.length;
                const targetIndex = insertIndex < 0 || insertIndex > prev.length ? prev.length : insertIndex;
                const next = [...prev];
                next.splice(targetIndex, 0, newSection);
                sectionsDraftRef.current = next;
                return next;
            });

            setSelectedFixedSectionKey(null);
            setSelectedNestedSection(null);
            applyStructureMutationState(sectionsDraftRef.current, {
                sectionLocalId: localId,
                target: buildEditableTargetFromSection(newSection),
            });
            setBuilderSidebarMode('settings');
            toast.success(t('Section pasted'));
        } catch (error) {
            if (error instanceof SyntaxError) {
                toast.error(t('Invalid JSON in clipboard'));
            } else {
                toast.error(t('Failed to paste section'));
            }
        }

        scheduleStructuralDraftPersistRef.current();
    }, [
        applyStructureMutationState,
        formatPropsText,
        isFixedLayoutSectionKey,
        nextSectionLocalId,
        normalizeSectionTypeKey,
        scheduleStructuralDraftPersistRef,
        sectionsDraftRef,
        selectedSectionLocalId,
        setBuilderSidebarMode,
        setSectionsDraft,
        setSelectedFixedSectionKey,
        setSelectedNestedSection,
        t,
    ]);

    const handleMoveSection = useCallback((localId: string, direction: 'up' | 'down') => {
        setSectionsDraft((prev) => {
            const index = prev.findIndex((item) => item.localId === localId);
            if (index === -1) {
                return prev;
            }

            const targetIndex = direction === 'up' ? index - 1 : index + 1;
            if (targetIndex < 0 || targetIndex >= prev.length) {
                return prev;
            }

            const targetLocalId = prev[targetIndex].localId;
            const position = direction === 'up' ? 'before' : 'after';
            const next = moveSection(prev, localId, targetLocalId, position);
            sectionsDraftRef.current = next;
            return next;
        });

        scheduleStructuralDraftPersistRef.current();
    }, [scheduleStructuralDraftPersistRef, sectionsDraftRef, setSectionsDraft]);

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

        setSectionsDraft((prev) => {
            const oldIndex = prev.findIndex((section) => section.localId === activeId);
            if (oldIndex === -1) {
                return prev;
            }

            if (overId === canvasDropId || overId === visualDropId) {
                const lastLocalId = prev.length > 0 ? prev[prev.length - 1].localId : null;
                const next = lastLocalId ? moveSection(prev, activeId, lastLocalId, 'after') : prev;
                sectionsDraftRef.current = next;
                return next;
            }

            const newIndex = prev.findIndex((section) => section.localId === overId);
            if (newIndex === -1) {
                return prev;
            }

            const position = newIndex > oldIndex ? 'after' : 'before';
            const next = moveSection(prev, activeId, overId, position);
            sectionsDraftRef.current = next;
            return next;
        });

        scheduleStructuralDraftPersistRef.current();
    }, [
        addSectionByKey,
        canvasDropId,
        extractLibrarySectionKey,
        handleAddSectionInside,
        scheduleStructuralDraftPersistRef,
        sectionsDraftRef,
        selectedSectionLocalId,
        setActiveDragId,
        setBuilderCurrentDropTarget,
        setSectionsDraft,
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
