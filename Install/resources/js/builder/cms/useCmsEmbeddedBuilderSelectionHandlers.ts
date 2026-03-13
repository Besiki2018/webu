import { useCallback } from 'react';
import { buildSectionScopedEditableTarget, type BuilderEditableTarget, type BuilderSidebarTab } from '@/builder/editingState';
import { isRecord } from '@/builder/state/sectionProps';
import { applyBuilderUpdatePipeline, type BuilderUpdateOperation } from '@/builder/state/updatePipeline';
import type { BuilderBridgeSidebarMode, BuilderBridgeViewport, BuilderBridgeInteractionState } from '@/builder/cms/embeddedBuilderBridgeContract';
import type { SectionDraft } from '@/builder/state/useBuilderCanvasState';
import type {
    BuilderSelectedSectionKeyPayload,
    BuilderSelectedSectionPayload,
    EmbeddedBuilderSelectedTargetPayload,
} from '@/builder/cms/useEmbeddedBuilderBridge';

type StateUpdater<T> = (value: T | ((current: T) => T)) => void;

interface UseCmsEmbeddedBuilderSelectionHandlersOptions {
    isEmbeddedMode: boolean;
    pageEditorMode: 'builder' | 'text';
    setPageEditorMode: (mode: 'builder' | 'text') => void;
    setSectionsDraft: StateUpdater<SectionDraft[]>;
    sectionsDraftRef: { current: SectionDraft[] };
    scheduleStructuralDraftPersistRef: { current: () => void };
    setSelectedSectionLocalId: StateUpdater<string | null>;
    setSelectedFixedSectionKey: (value: string | null) => void;
    setSelectedNestedSection: (value: null) => void;
    setSelectedBuilderTarget: StateUpdater<BuilderEditableTarget | null>;
    setBuilderSidebarMode: (mode: BuilderBridgeSidebarMode) => void;
    setSelectedSidebarTab: (tab: BuilderSidebarTab) => void;
    selectSectionByPreviewKey: (sectionKey: string) => void;
    builderPreviewMode: BuilderBridgeViewport;
    builderPreviewInteractionState: BuilderBridgeInteractionState;
    normalizeSectionTypeKey: (key: string) => string;
    formatPropsText: (props: Record<string, unknown>) => string;
    builderFieldGroupToSidebarTab: (fieldGroup?: BuilderEditableTarget['fieldGroup']) => BuilderSidebarTab;
    createBuilderSectionDraft: (input: { sectionType: string; props?: Record<string, unknown>; localId?: string | null }) => SectionDraft | null;
    applyMutationState: (state: {
        sectionsDraft: SectionDraft[];
        selectedSectionLocalId: string | null;
        selectedBuilderTarget: BuilderEditableTarget | null;
        mutationId?: string | null;
    }) => void;
}

export function useCmsEmbeddedBuilderSelectionHandlers({
    isEmbeddedMode,
    pageEditorMode,
    setPageEditorMode,
    setSectionsDraft,
    sectionsDraftRef,
    scheduleStructuralDraftPersistRef,
    setSelectedSectionLocalId,
    setSelectedFixedSectionKey,
    setSelectedNestedSection,
    setSelectedBuilderTarget,
    setBuilderSidebarMode,
    setSelectedSidebarTab,
    selectSectionByPreviewKey,
    builderPreviewMode,
    builderPreviewInteractionState,
    normalizeSectionTypeKey,
    formatPropsText,
    builderFieldGroupToSidebarTab,
    createBuilderSectionDraft,
    applyMutationState,
}: UseCmsEmbeddedBuilderSelectionHandlersOptions) {
    const findDraftByLocalId = useCallback((localId: string | null) => {
        if (!localId) {
            return null;
        }

        return sectionsDraftRef.current.find((section) => section.localId === localId) ?? null;
    }, [sectionsDraftRef]);

    const findDraftBySectionKey = useCallback((sectionKey: string | null) => {
        const normalizedSectionKey = normalizeSectionTypeKey(sectionKey ?? '');
        if (normalizedSectionKey === '') {
            return null;
        }

        const matches = sectionsDraftRef.current.filter((section) => normalizeSectionTypeKey(section.type) === normalizedSectionKey);
        return matches.length === 1 ? matches[0] : null;
    }, [normalizeSectionTypeKey, sectionsDraftRef]);

    const handleEmbeddedBuilderClearSelection = useCallback(() => {
        setSelectedSectionLocalId(null);
        setSelectedFixedSectionKey(null);
        setSelectedNestedSection(null);
        setSelectedBuilderTarget(null);
        setBuilderSidebarMode('elements');
        setSelectedSidebarTab('content');
    }, [setBuilderSidebarMode, setSelectedBuilderTarget, setSelectedFixedSectionKey, setSelectedNestedSection, setSelectedSectionLocalId, setSelectedSidebarTab]);

    const handleEmbeddedBuilderInitialSections = useCallback((rawSections: Array<Record<string, unknown>>) => {
        const drafts: SectionDraft[] = rawSections
            .filter((entry) => typeof entry.localId === 'string' && typeof entry.type === 'string')
            .map((entry) => {
                const localId = String(entry.localId).trim();
                const type = String(entry.type).trim();
                const props = isRecord(entry.props) ? entry.props : {};

                return {
                    localId: localId || `section-${Math.random().toString(36).slice(2, 9)}`,
                    type: type || 'section',
                    props,
                    propsText: typeof entry.propsText === 'string' && entry.propsText.trim() !== ''
                        ? entry.propsText.trim()
                        : formatPropsText(props),
                    propsError: null,
                    bindingMeta: null,
                };
            });

        if (drafts.length === 0) {
            return;
        }

        if (isEmbeddedMode && pageEditorMode !== 'builder') {
            setPageEditorMode('builder');
        }

        const operations: BuilderUpdateOperation[] = [
            ...sectionsDraftRef.current
                .slice()
                .reverse()
                .map((section) => ({
                    kind: 'delete-section' as const,
                    source: 'chat' as const,
                    sectionLocalId: section.localId,
                })),
            ...drafts.flatMap<BuilderUpdateOperation>((draft, index) => {
                const nextOperations: BuilderUpdateOperation[] = [{
                    kind: 'insert-section',
                    source: 'chat',
                    sectionType: draft.type,
                    insertIndex: index,
                    localId: draft.localId,
                    selectInserted: false,
                }];

                if (Object.keys(draft.props ?? {}).length > 0) {
                    nextOperations.push({
                        kind: 'merge-props',
                        source: 'chat',
                        sectionLocalId: draft.localId,
                        patch: draft.props ?? {},
                    });
                }

                return nextOperations;
            }),
        ];

        const result = applyBuilderUpdatePipeline({
            sectionsDraft: sectionsDraftRef.current,
            selectedSectionLocalId: null,
            selectedBuilderTarget: null,
        }, operations, {
            createSection: createBuilderSectionDraft,
        });

        if (!result.ok) {
            setSectionsDraft(drafts);
            sectionsDraftRef.current = drafts;
            setSelectedFixedSectionKey(null);
            setSelectedNestedSection(null);
            setSelectedSectionLocalId(null);
            setSelectedBuilderTarget(null);
            setBuilderSidebarMode('elements');
            setSelectedSidebarTab('content');
            scheduleStructuralDraftPersistRef.current();
            return;
        }

        sectionsDraftRef.current = result.state.sectionsDraft;
        applyMutationState(result.state);
        setSelectedFixedSectionKey(null);
        setSelectedNestedSection(null);
        setSelectedSectionLocalId(null);
        setSelectedBuilderTarget(null);
        setBuilderSidebarMode('elements');
        setSelectedSidebarTab('content');
        scheduleStructuralDraftPersistRef.current();
    }, [
        applyMutationState,
        createBuilderSectionDraft,
        formatPropsText,
        isEmbeddedMode,
        pageEditorMode,
        scheduleStructuralDraftPersistRef,
        sectionsDraftRef,
        setBuilderSidebarMode,
        setPageEditorMode,
        setSectionsDraft,
        setSelectedBuilderTarget,
        setSelectedFixedSectionKey,
        setSelectedNestedSection,
        setSelectedSectionLocalId,
        setSelectedSidebarTab,
    ]);

    const handleEmbeddedBuilderSelectedTarget = useCallback((payload: EmbeddedBuilderSelectedTargetPayload) => {
        const requestedLocalId = typeof payload.sectionLocalId === 'string' && payload.sectionLocalId.trim() !== ''
            ? payload.sectionLocalId.trim()
            : null;
        const requestedSectionKey = typeof payload.sectionKey === 'string' && payload.sectionKey.trim() !== ''
            ? payload.sectionKey.trim()
            : null;
        const matchedDraft = findDraftByLocalId(requestedLocalId) ?? findDraftBySectionKey(requestedSectionKey);
        const nextTarget = buildSectionScopedEditableTarget({
            pageId: payload.pageId ?? null,
            pageSlug: payload.pageSlug ?? null,
            pageTitle: payload.pageTitle ?? null,
            sectionLocalId: requestedLocalId ?? matchedDraft?.localId ?? null,
            sectionKey: requestedSectionKey ?? matchedDraft?.type ?? null,
            componentType: typeof payload.componentType === 'string' && payload.componentType.trim() !== ''
                ? payload.componentType.trim()
                : (requestedSectionKey ?? matchedDraft?.type ?? null),
            componentName: typeof payload.componentName === 'string' ? payload.componentName : null,
            textPreview: typeof payload.textPreview === 'string' ? payload.textPreview : null,
            props: isRecord(payload.props)
                ? payload.props
                : (isRecord(matchedDraft?.props) ? matchedDraft.props : null),
            currentBreakpoint: builderPreviewMode,
            currentInteractionState: builderPreviewInteractionState,
        });

        if (!nextTarget) {
            return;
        }

        if (nextTarget.sectionLocalId) {
            setSelectedFixedSectionKey(null);
            setSelectedNestedSection(null);
            setSelectedSectionLocalId(nextTarget.sectionLocalId);
        } else if (nextTarget.sectionKey) {
            selectSectionByPreviewKey(nextTarget.sectionKey);
        }

        setSelectedBuilderTarget(nextTarget);
        setBuilderSidebarMode('settings');
        setSelectedSidebarTab(builderFieldGroupToSidebarTab(
            typeof payload.fieldGroup === 'string'
                ? payload.fieldGroup as BuilderEditableTarget['fieldGroup']
                : null,
        ));
    }, [
        builderFieldGroupToSidebarTab,
        builderPreviewInteractionState,
        builderPreviewMode,
        findDraftByLocalId,
        findDraftBySectionKey,
        selectSectionByPreviewKey,
        setBuilderSidebarMode,
        setSelectedBuilderTarget,
        setSelectedFixedSectionKey,
        setSelectedNestedSection,
        setSelectedSectionLocalId,
        setSelectedSidebarTab,
    ]);

    const handleEmbeddedBuilderSelectedSection = useCallback((payload: BuilderSelectedSectionPayload) => {
        setSelectedFixedSectionKey(null);
        setSelectedNestedSection(null);
        setSelectedSectionLocalId(payload.sectionLocalId);
        setSelectedBuilderTarget((current) => {
            const matchedDraft = findDraftByLocalId(payload.sectionLocalId);

            return buildSectionScopedEditableTarget({
                pageId: current?.pageId ?? null,
                pageSlug: current?.pageSlug ?? null,
                pageTitle: current?.pageTitle ?? null,
                sectionLocalId: payload.sectionLocalId,
                sectionKey: matchedDraft?.type ?? current?.sectionKey ?? null,
                componentType: matchedDraft?.type ?? current?.componentType ?? current?.sectionKey ?? null,
                componentName: current?.componentName ?? null,
                textPreview: current?.textPreview ?? null,
                props: isRecord(matchedDraft?.props) ? matchedDraft.props : (current?.props ?? null),
                currentBreakpoint: builderPreviewMode,
                currentInteractionState: builderPreviewInteractionState,
            });
        });

        if (!payload.sectionLocalId) {
            return;
        }

        setBuilderSidebarMode('settings');
        setSelectedSidebarTab('content');
    }, [
        builderPreviewInteractionState,
        builderPreviewMode,
        findDraftByLocalId,
        setBuilderSidebarMode,
        setSelectedBuilderTarget,
        setSelectedFixedSectionKey,
        setSelectedNestedSection,
        setSelectedSectionLocalId,
        setSelectedSidebarTab,
    ]);

    const handleEmbeddedBuilderSelectedSectionKey = useCallback((payload: BuilderSelectedSectionKeyPayload) => {
        const nextSectionKey = normalizeSectionTypeKey(payload.sectionKey);
        if (nextSectionKey === '') {
            return;
        }

        selectSectionByPreviewKey(nextSectionKey);
        setSelectedBuilderTarget((current) => {
            const matchedDraft = findDraftBySectionKey(nextSectionKey);

            return buildSectionScopedEditableTarget({
                pageId: current?.pageId ?? null,
                pageSlug: current?.pageSlug ?? null,
                pageTitle: current?.pageTitle ?? null,
                sectionLocalId: matchedDraft?.localId ?? current?.sectionLocalId ?? null,
                sectionKey: nextSectionKey,
                componentType: nextSectionKey,
                componentName: current?.componentName ?? null,
                textPreview: current?.textPreview ?? null,
                props: isRecord(matchedDraft?.props) ? matchedDraft.props : (current?.props ?? null),
                currentBreakpoint: builderPreviewMode,
                currentInteractionState: builderPreviewInteractionState,
            });
        });

        if (payload.parameterPath) {
            setSelectedSidebarTab('content');
        }
    }, [
        builderPreviewInteractionState,
        builderPreviewMode,
        findDraftBySectionKey,
        normalizeSectionTypeKey,
        selectSectionByPreviewKey,
        setSelectedBuilderTarget,
        setSelectedSidebarTab,
    ]);

    return {
        handleEmbeddedBuilderClearSelection,
        handleEmbeddedBuilderInitialSections,
        handleEmbeddedBuilderSelectedTarget,
        handleEmbeddedBuilderSelectedSection,
        handleEmbeddedBuilderSelectedSectionKey,
    };
}
