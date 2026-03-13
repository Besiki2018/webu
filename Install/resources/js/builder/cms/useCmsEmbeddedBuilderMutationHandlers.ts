import { useCallback, type Dispatch, type MutableRefObject, type SetStateAction } from 'react';
import { applyBuilderChangeSetPipeline, applyBuilderUpdatePipeline } from '@/builder/state/updatePipeline';
import type { SectionDraft } from '@/builder/state/useBuilderCanvasState';
import type { BuilderEditableTarget } from '@/builder/editingState';
import type {
    EmbeddedBuilderAddSectionPayload,
    EmbeddedBuilderApplyChangeSetPayload,
    EmbeddedBuilderMoveSectionPayload,
    EmbeddedBuilderRemoveSectionPayload,
    EmitEmbeddedBuilderMessage,
} from '@/builder/cms/useEmbeddedBuilderBridge';

interface BuilderMutationStateLike {
    sectionsDraft: SectionDraft[];
    selectedSectionLocalId: string | null;
    selectedBuilderTarget: BuilderEditableTarget | null;
}

interface UseCmsEmbeddedBuilderMutationHandlersOptions {
    isEmbeddedMode: boolean;
    pageEditorMode: string;
    selectedPageId: number | null;
    selectedSectionLocalId: string | null;
    selectedBuilderTarget: BuilderEditableTarget | null;
    sectionsDraftRef: MutableRefObject<SectionDraft[]>;
    saveDraftRevisionInternalRef: MutableRefObject<(options?: { silent?: boolean; refreshAfterSave?: boolean }) => Promise<number | null>>;
    scheduleStructuralDraftPersistRef: MutableRefObject<() => void>;
    setPageEditorMode: (mode: 'builder') => void;
    setSectionsDraft: Dispatch<SetStateAction<SectionDraft[]>>;
    setSelectedSectionLocalId: (value: string | null) => void;
    setSelectedNestedSection: (value: { parentLocalId: string; path: number[] } | null) => void;
    setSelectedFixedSectionKey: (value: string | null) => void;
    setBuilderSidebarMode: (mode: 'elements' | 'settings' | 'design-system') => void;
    normalizeSectionTypeKey: (key: string) => string;
    isHeaderSectionKey: (key: unknown) => boolean;
    isFooterSectionKey: (key: unknown) => boolean;
    createBuilderSectionDraft: (input: { sectionType: string; props?: Record<string, unknown>; localId?: string | null }) => SectionDraft | null;
    applyMutationState: (state: BuilderMutationStateLike) => void;
    addSectionByKey: (
        sectionKey: string,
        source?: 'library' | 'toolbar',
        options?: { insertIndex?: number; localId?: string | null },
    ) => void;
    handleAddSectionInside: (parentLocalId: string, sectionKey: string) => void;
    handleRemoveSection: (localId: string) => void;
    t: (key: string) => string;
}

function emitEmbeddedBuilderMutationResult(
    emit: EmitEmbeddedBuilderMessage,
    payload: {
        requestId: string | null;
        mutation: 'apply-change-set' | 'add-section' | 'remove-section' | 'move-section';
        success: boolean;
        changed: boolean;
        error?: string | null;
    },
): void {
    if (!payload.requestId) {
        return;
    }

    emit({
        type: 'builder:mutation-result',
        requestId: payload.requestId,
        mutation: payload.mutation,
        success: payload.success,
        changed: payload.changed,
        error: payload.error ?? null,
    });
}

export function useCmsEmbeddedBuilderMutationHandlers({
    isEmbeddedMode,
    pageEditorMode,
    selectedPageId,
    selectedSectionLocalId,
    selectedBuilderTarget,
    sectionsDraftRef,
    saveDraftRevisionInternalRef,
    scheduleStructuralDraftPersistRef,
    setPageEditorMode,
    setSelectedSectionLocalId,
    setSelectedNestedSection,
    setSelectedFixedSectionKey,
    setBuilderSidebarMode,
    normalizeSectionTypeKey,
    isHeaderSectionKey,
    isFooterSectionKey,
    createBuilderSectionDraft,
    applyMutationState,
    addSectionByKey,
    handleAddSectionInside,
    handleRemoveSection,
    t,
}: UseCmsEmbeddedBuilderMutationHandlersOptions) {
    const handleEmbeddedBuilderChangeSet = useCallback((
        payload: EmbeddedBuilderApplyChangeSetPayload,
        emit: EmitEmbeddedBuilderMessage,
    ) => {
        const requestId = payload.requestId;
        const result = applyBuilderChangeSetPipeline({
            sectionsDraft: sectionsDraftRef.current,
            selectedSectionLocalId,
            selectedBuilderTarget,
        }, {
            operations: Array.isArray((payload.changeSet as { operations?: unknown } | null)?.operations)
                ? ((payload.changeSet as { operations?: Array<Record<string, unknown>> }).operations ?? [])
                : [],
        }, {
            createSection: createBuilderSectionDraft,
        });

        if (result.ok && result.changed) {
            sectionsDraftRef.current = result.state.sectionsDraft;
            applyMutationState(result.state);
        }

        emitEmbeddedBuilderMutationResult(emit, {
            requestId,
            mutation: 'apply-change-set',
            success: result.ok,
            changed: result.changed,
            error: result.ok ? null : (result.errors[0]?.message ?? null),
        });
    }, [applyMutationState, createBuilderSectionDraft, sectionsDraftRef, selectedBuilderTarget, selectedSectionLocalId]);

    const handleEmbeddedBuilderSaveDraft = useCallback((emit: EmitEmbeddedBuilderMessage) => {
        void (async () => {
            emit({
                type: 'builder:draft-save-state',
                isSaving: true,
            });

            if (selectedPageId === null) {
                emit({
                    type: 'builder:draft-save-state',
                    isSaving: false,
                    success: false,
                    message: t('Select a page first'),
                });
                return;
            }

            const revisionId = await saveDraftRevisionInternalRef.current({
                silent: true,
                refreshAfterSave: true,
            });

            emit({
                type: 'builder:draft-save-state',
                isSaving: false,
                success: revisionId !== null,
                revisionId,
            });
        })();
    }, [saveDraftRevisionInternalRef, selectedPageId, t]);

    const handleEmbeddedBuilderAddSection = useCallback((payload: EmbeddedBuilderAddSectionPayload, emit: EmitEmbeddedBuilderMessage) => {
        if (isEmbeddedMode && pageEditorMode !== 'builder') {
            setPageEditorMode('builder');
        }

        const requestId = payload.requestId;
        const nextSectionKey = normalizeSectionTypeKey(payload.sectionKey);
        if (nextSectionKey === '') {
            emitEmbeddedBuilderMutationResult(emit, {
                requestId,
                mutation: 'add-section',
                success: false,
                changed: false,
                error: t('Select a valid section first'),
            });
            return;
        }

        let insertIndex: number | undefined;
        const afterSectionLocalId = payload.afterSectionLocalId?.trim() ?? '';
        const targetSectionKey = payload.targetSectionKey ? normalizeSectionTypeKey(payload.targetSectionKey) : '';
        const placement = payload.placement ?? '';

        if (afterSectionLocalId !== '') {
            if (placement === 'inside') {
                handleAddSectionInside(afterSectionLocalId, nextSectionKey);
                emitEmbeddedBuilderMutationResult(emit, {
                    requestId,
                    mutation: 'add-section',
                    success: true,
                    changed: true,
                });
                return;
            }

            const matchedIndex = sectionsDraftRef.current.findIndex((section) => section.localId === afterSectionLocalId);
            if (matchedIndex >= 0) {
                insertIndex = placement === 'before' ? matchedIndex : matchedIndex + 1;
            }
        } else if (targetSectionKey !== '') {
            if (isHeaderSectionKey(targetSectionKey)) {
                insertIndex = 0;
            } else if (isFooterSectionKey(targetSectionKey)) {
                insertIndex = sectionsDraftRef.current.length;
            }
        }

        addSectionByKey(nextSectionKey, 'library', {
            insertIndex,
            localId: payload.sectionLocalId ?? null,
        });
        emitEmbeddedBuilderMutationResult(emit, {
            requestId,
            mutation: 'add-section',
            success: true,
            changed: true,
        });
    }, [addSectionByKey, handleAddSectionInside, isEmbeddedMode, isFooterSectionKey, isHeaderSectionKey, normalizeSectionTypeKey, pageEditorMode, sectionsDraftRef, setPageEditorMode, t]);

    const handleEmbeddedBuilderRemoveSection = useCallback((payload: EmbeddedBuilderRemoveSectionPayload, emit: EmitEmbeddedBuilderMessage) => {
        if (isEmbeddedMode && pageEditorMode !== 'builder') {
            setPageEditorMode('builder');
        }

        const requestId = payload.requestId;
        const requestedLocalId = payload.sectionLocalId?.trim() ?? '';
        const requestedSectionIndex = payload.sectionIndex ?? null;
        const requestedSectionKey = payload.sectionKey ? normalizeSectionTypeKey(payload.sectionKey) : '';

        let resolvedLocalId = requestedLocalId;
        if (resolvedLocalId === '' || !sectionsDraftRef.current.some((section) => section.localId === resolvedLocalId)) {
            resolvedLocalId = '';

            if (requestedSectionIndex !== null) {
                const sectionAtIndex = sectionsDraftRef.current[requestedSectionIndex] ?? null;
                if (sectionAtIndex) {
                    const sectionAtIndexKey = normalizeSectionTypeKey(sectionAtIndex.type);
                    if (requestedSectionKey === '' || sectionAtIndexKey === requestedSectionKey) {
                        resolvedLocalId = sectionAtIndex.localId;
                    }
                }
            }

            if (resolvedLocalId === '' && requestedSectionKey !== '') {
                const sectionByKey = sectionsDraftRef.current.find((section) => (
                    normalizeSectionTypeKey(section.type) === requestedSectionKey
                ));
                if (sectionByKey) {
                    resolvedLocalId = sectionByKey.localId;
                }
            }
        }

        if (resolvedLocalId === '') {
            emitEmbeddedBuilderMutationResult(emit, {
                requestId,
                mutation: 'remove-section',
                success: false,
                changed: false,
                error: t('Section not found'),
            });
            return;
        }

        handleRemoveSection(resolvedLocalId);
        emitEmbeddedBuilderMutationResult(emit, {
            requestId,
            mutation: 'remove-section',
            success: true,
            changed: true,
        });
    }, [handleRemoveSection, isEmbeddedMode, normalizeSectionTypeKey, pageEditorMode, sectionsDraftRef, setPageEditorMode, t]);

    const handleEmbeddedBuilderMoveSection = useCallback((payload: EmbeddedBuilderMoveSectionPayload, emit: EmitEmbeddedBuilderMessage) => {
        if (isEmbeddedMode && pageEditorMode !== 'builder') {
            setPageEditorMode('builder');
        }

        const requestId = payload.requestId;
        const sectionLocalId = payload.sectionLocalId.trim();
        const targetSectionLocalId = payload.targetSectionLocalId.trim();
        const position = payload.position === 'before' ? 'before' : 'after';

        if (sectionLocalId === '' || targetSectionLocalId === '' || sectionLocalId === targetSectionLocalId) {
            emitEmbeddedBuilderMutationResult(emit, {
                requestId,
                mutation: 'move-section',
                success: false,
                changed: false,
                error: t('Section move target is invalid'),
            });
            return;
        }

        const currentSections = sectionsDraftRef.current;
        const currentIndex = currentSections.findIndex((section) => section.localId === sectionLocalId);
        const targetIndex = currentSections.findIndex((section) => section.localId === targetSectionLocalId);
        if (currentIndex < 0 || targetIndex < 0) {
            emitEmbeddedBuilderMutationResult(emit, {
                requestId,
                mutation: 'move-section',
                success: false,
                changed: false,
                error: t('Section move target is invalid'),
            });
            return;
        }

        const toIndex = position === 'before'
            ? (targetIndex > currentIndex ? targetIndex - 1 : targetIndex)
            : (targetIndex > currentIndex ? targetIndex : targetIndex + 1);
        const result = applyBuilderUpdatePipeline({
            sectionsDraft: currentSections,
            selectedSectionLocalId,
            selectedBuilderTarget,
        }, [{
            kind: 'reorder-section',
            source: 'chat',
            sectionLocalId,
            toIndex,
        }], {
            createSection: createBuilderSectionDraft,
        });
        if (!result.ok) {
            emitEmbeddedBuilderMutationResult(emit, {
                requestId,
                mutation: 'move-section',
                success: false,
                changed: false,
                error: result.errors[0]?.message ?? t('Section move target is invalid'),
            });
            return;
        }

        if (result.changed) {
            sectionsDraftRef.current = result.state.sectionsDraft;
            applyMutationState(result.state);
        }

        scheduleStructuralDraftPersistRef.current();
        emitEmbeddedBuilderMutationResult(emit, {
            requestId,
            mutation: 'move-section',
            success: true,
            changed: result.changed,
        });
    }, [
        applyMutationState,
        createBuilderSectionDraft,
        isEmbeddedMode,
        pageEditorMode,
        scheduleStructuralDraftPersistRef,
        sectionsDraftRef,
        selectedBuilderTarget,
        selectedSectionLocalId,
        setPageEditorMode,
        t,
    ]);

    return {
        handleEmbeddedBuilderChangeSet,
        handleEmbeddedBuilderSaveDraft,
        handleEmbeddedBuilderAddSection,
        handleEmbeddedBuilderRemoveSection,
        handleEmbeddedBuilderMoveSection,
    };
}
