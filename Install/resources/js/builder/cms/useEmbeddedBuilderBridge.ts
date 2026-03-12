import { useEffect, useRef } from 'react';
import {
    hasBuilderBridgePageIdentity,
    payloadTargetsBuilderBridgePage,
    type BuilderBridgeInteractionState,
    type BuilderBridgePageIdentity,
    type BuilderBridgeSidebarMode,
    type BuilderBridgeViewport,
} from '@/builder/cms/embeddedBuilderBridgeContract';
import {
    buildBuilderSelectionMessageSignature,
    type BuilderSelectionMessagePayload,
    type BuilderEditableTarget,
} from '@/builder/editingState';
import type { SectionDraft } from '@/builder/state/useBuilderCanvasState';
import { buildCanonicalBridgeSelectedTargetPayload } from '@/builder/cms/canonicalSelectionPayload';
import {
    buildBuilderAckMessage,
    buildBuilderReadyMessage,
    buildBuilderSelectTargetMessage,
    buildBuilderSyncStateMessage,
    builderBridgeEnvelopeTargetsProject,
    inspectBuilderBridgeEnvelope,
    logBuilderBridgeDiagnostic,
    postBuilderBridgeEnvelope,
    type BuilderBridgeMessage,
} from '@/lib/builderBridge';

interface BuilderLibrarySnapshotItem {
    key: string;
    label?: string | null;
    category?: string | null;
}

export type EmbeddedBuilderIncomingPayload = BuilderBridgeMessage;
export interface EmbeddedBuilderApplyChangeSetPayload {
    requestId: string;
    changeSet: Record<string, unknown>;
}
export interface EmbeddedBuilderSelectedTargetPayload extends BuilderSelectionMessagePayload {}
export interface EmbeddedBuilderAddSectionPayload {
    requestId: string;
    sectionKey: string;
    sectionLocalId?: string | null;
    afterSectionLocalId?: string | null;
    targetSectionKey?: string | null;
    placement?: 'before' | 'after' | 'inside' | null;
}
export interface EmbeddedBuilderRemoveSectionPayload {
    requestId: string;
    sectionLocalId?: string | null;
    sectionIndex?: number | null;
    sectionKey?: string | null;
}
export interface EmbeddedBuilderMoveSectionPayload {
    requestId: string;
    sectionLocalId: string;
    targetSectionLocalId: string;
    position: 'before' | 'after';
}
export type EmitEmbeddedBuilderMessage = (message: Record<string, unknown>) => void;

export interface BuilderSelectedSectionPayload {
    sectionLocalId: string | null;
    parameterPath: string | null;
}

export interface BuilderSelectedSectionKeyPayload {
    sectionKey: string;
    parameterPath: string | null;
}

export interface UseEmbeddedBuilderBridgeOptions {
    projectId: string;
    isEmbeddedMode: boolean;
    isEmbeddedVisualBuilder: boolean;
    isEmbeddedSidebarMode: boolean;
    builderViewport: BuilderBridgeViewport;
    builderInteractionState: BuilderBridgeInteractionState;
    selectedTargetViewport: BuilderBridgeViewport;
    selectedTargetInteractionState: BuilderBridgeInteractionState;
    isStructurePanelCollapsed: boolean;
    selectedPage: BuilderBridgePageIdentity;
    stateVersion: number;
    structureHash: string;
    revisionId: number | null;
    revisionVersion: number | null;
    selectedSectionLocalId: string | null;
    selectedSectionDraft: SectionDraft | null;
    selectedFixedSectionKey: string | null;
    selectedBuilderTarget: BuilderEditableTarget | null;
    sectionsDraft: SectionDraft[];
    builderSectionLibrary: BuilderLibrarySnapshotItem[];
    sectionDisplayLabelByKey: Map<string, string>;
    t: (key: string) => string;
    normalizeSectionTypeKey: (key: string) => string;
    buildSectionPreviewText: (props: Record<string, unknown>, fallback: string) => string;
    getBuilderSectionExplicitProps: (section: SectionDraft) => Record<string, unknown> | null;
    onSetViewport: (viewport: BuilderBridgeViewport) => void;
    onSetInteractionState: (state: BuilderBridgeInteractionState) => void;
    onRefreshPreview: () => void;
    onSetSidebarMode: (mode: BuilderBridgeSidebarMode) => void;
    onClearSelectedSection: () => void;
    onSetInitialSections: (sections: Array<Record<string, unknown>>) => void;
    onApplyChangeSet: (payload: EmbeddedBuilderApplyChangeSetPayload, emit: EmitEmbeddedBuilderMessage) => void;
    onSetSelectedTarget: (payload: EmbeddedBuilderSelectedTargetPayload) => void;
    onSetStructureOpen: (open: boolean) => void;
    onSaveDraft: (emit: EmitEmbeddedBuilderMessage) => void;
    onAddSectionByKey: (payload: EmbeddedBuilderAddSectionPayload, emit: EmitEmbeddedBuilderMessage) => void;
    onRemoveSection: (payload: EmbeddedBuilderRemoveSectionPayload, emit: EmitEmbeddedBuilderMessage) => void;
    onMoveSection: (payload: EmbeddedBuilderMoveSectionPayload, emit: EmitEmbeddedBuilderMessage) => void;
}

function readTrimmedString(value: unknown): string | null {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

export function useEmbeddedBuilderBridge({
    projectId,
    isEmbeddedMode,
    isEmbeddedVisualBuilder,
    isEmbeddedSidebarMode,
    builderViewport,
    builderInteractionState,
    selectedTargetViewport,
    selectedTargetInteractionState,
    isStructurePanelCollapsed,
    selectedPage,
    stateVersion,
    structureHash,
    revisionId,
    revisionVersion,
    selectedSectionLocalId,
    selectedSectionDraft,
    selectedFixedSectionKey,
    selectedBuilderTarget,
    sectionsDraft,
    builderSectionLibrary,
    sectionDisplayLabelByKey,
    t,
    normalizeSectionTypeKey,
    buildSectionPreviewText,
    getBuilderSectionExplicitProps,
    onSetViewport,
    onSetInteractionState,
    onRefreshPreview,
    onSetSidebarMode,
    onClearSelectedSection,
    onSetInitialSections,
    onApplyChangeSet,
    onSetSelectedTarget,
    onSetStructureOpen,
    onSaveDraft,
    onAddSectionByKey,
    onRemoveSection,
    onMoveSection,
}: UseEmbeddedBuilderBridgeOptions): void {
    const lastSelectedTargetEventSignatureRef = useRef<string | null>(null);
    const targetOrigin = typeof window !== 'undefined' ? window.location.origin : '';
    const logSidebarBridge = (input: {
        phase: 'send' | 'receive' | 'ignore' | 'drop';
        target?: string | null;
        message?: BuilderBridgeMessage | null;
        rawType?: string | null;
        requestId?: string | null;
        reason?: string | null;
    }) => {
        logBuilderBridgeDiagnostic({
            actor: 'sidebar',
            ...input,
        });
    };

    const emitEnvelope = (message: BuilderBridgeMessage) => {
        if (typeof window === 'undefined' || window.parent === window) {
            return;
        }

        logSidebarBridge({
            phase: 'send',
            target: 'chat',
            message,
        });
        postBuilderBridgeEnvelope(window.parent, targetOrigin, message);
    };

    const emit: EmitEmbeddedBuilderMessage = (message) => {
        const type = readTrimmedString(message.type);
        if (!type) {
            logSidebarBridge({
                phase: 'drop',
                target: 'chat',
                reason: 'missing-legacy-message-type',
            });
            return;
        }

        const baseInput = {
            source: 'sidebar' as const,
            projectId,
            page: selectedPage,
            requestId: readTrimmedString(message.requestId),
        };
        const stateMeta = {
            stateVersion,
            structureHash,
            revisionId,
            revisionVersion,
        };

        if (type === 'builder:mutation-result') {
            const mutation = message.mutation === 'apply-change-set'
                || message.mutation === 'add-section'
                || message.mutation === 'remove-section'
                || message.mutation === 'move-section'
                ? message.mutation
                : null;
            const success = typeof message.success === 'boolean' ? message.success : null;
            if (!mutation || success === null) {
                logSidebarBridge({
                    phase: 'drop',
                    target: 'chat',
                    rawType: type,
                    requestId: baseInput.requestId,
                    reason: 'invalid-mutation-result-payload',
                });
                return;
            }

            const ackType = mutation === 'apply-change-set'
                ? 'BUILDER_PATCH_PROPS'
                : mutation === 'add-section'
                    ? 'BUILDER_INSERT_NODE'
                    : mutation === 'remove-section'
                        ? 'BUILDER_DELETE_NODE'
                        : 'BUILDER_MOVE_NODE';

            emitEnvelope(buildBuilderAckMessage({
                ...stateMeta,
                ackType,
                success,
                changed: typeof message.changed === 'boolean' ? message.changed : null,
                error: typeof message.error === 'string' ? message.error : null,
                mutation,
            }, baseInput));
            return;
        }

        if (type === 'builder:draft-save-state') {
            const isSaving = typeof message.isSaving === 'boolean' ? message.isSaving : null;
            if (isSaving === null) {
                logSidebarBridge({
                    phase: 'drop',
                    target: 'chat',
                    rawType: type,
                    requestId: baseInput.requestId,
                    reason: 'invalid-draft-save-state',
                });
                return;
            }

            emitEnvelope(buildBuilderSyncStateMessage({
                ...stateMeta,
                draftSaveState: {
                    isSaving,
                    success: typeof message.success === 'boolean' ? message.success : null,
                    message: typeof message.message === 'string' ? message.message : null,
                },
            }, baseInput));
            return;
        }

        if (type === 'builder:preview-refresh') {
            emitEnvelope(buildBuilderSyncStateMessage({
                ...stateMeta,
                previewRefresh: true,
            }, baseInput));
            return;
        }

        logSidebarBridge({
            phase: 'drop',
            target: 'chat',
            rawType: type,
            requestId: baseInput.requestId,
            reason: 'unsupported-legacy-emit-message',
        });
    };

    const emitCurrentReady = (requestId?: string | null) => {
        emitEnvelope(buildBuilderReadyMessage({
            channel: 'sidebar',
            stateVersion,
            structureHash,
            revisionId,
            revisionVersion,
        }, {
            source: 'sidebar',
            projectId,
            page: selectedPage,
            requestId: requestId ?? null,
        }));
    };

    const emitCurrentVisualState = (requestId?: string | null) => {
        emitEnvelope(buildBuilderSyncStateMessage({
            viewport: builderViewport,
            structureOpen: !isStructurePanelCollapsed,
            interactionState: builderInteractionState,
            stateVersion,
            structureHash,
            revisionId,
            revisionVersion,
        }, {
            source: 'sidebar',
            projectId,
            page: selectedPage,
            requestId: requestId ?? null,
        }));
    };

    const emitCurrentSelectedTarget = (requestId?: string | null, force = false) => {
        if (!isEmbeddedVisualBuilder || typeof window === 'undefined' || window.parent === window) {
            return;
        }

        const selectedSectionKey = selectedSectionDraft
            ? normalizeSectionTypeKey(selectedSectionDraft.type)
            : (selectedFixedSectionKey ? normalizeSectionTypeKey(selectedFixedSectionKey) : null);
        const selectedTargetPayload = buildCanonicalBridgeSelectedTargetPayload({
            pageIdentity: selectedPage,
            target: selectedBuilderTarget,
            fallback: selectedSectionDraft || selectedFixedSectionKey
                ? {
                    sectionLocalId: selectedSectionLocalId,
                    sectionKey: selectedSectionKey,
                    componentType: selectedSectionKey,
                    componentName: selectedSectionDraft
                        ? (sectionDisplayLabelByKey.get(selectedSectionKey ?? '') ?? sectionDisplayLabelByKey.get(selectedSectionDraft.type) ?? selectedSectionDraft.type)
                        : selectedFixedSectionKey,
                    textPreview: selectedSectionDraft
                        ? buildSectionPreviewText(getBuilderSectionExplicitProps(selectedSectionDraft) ?? {}, t('No preview text'))
                        : null,
                }
                : null,
            currentBreakpoint: selectedTargetViewport,
            currentInteractionState: selectedTargetInteractionState,
        });
        const selectedTargetSignature = JSON.stringify({
            type: 'BUILDER_SELECT_TARGET',
            pageId: selectedPage.pageId ?? null,
            pageSlug: selectedPage.pageSlug ?? null,
            pageTitle: selectedPage.pageTitle ?? null,
            payload: selectedTargetPayload
                ? buildBuilderSelectionMessageSignature({
                    ...selectedTargetPayload,
                    pageId: selectedPage.pageId,
                    pageSlug: selectedPage.pageSlug,
                    pageTitle: selectedPage.pageTitle,
                    currentBreakpoint: selectedTargetViewport,
                    currentInteractionState: selectedTargetInteractionState,
                })
                : 'null',
        });

        if (force || lastSelectedTargetEventSignatureRef.current !== selectedTargetSignature) {
            lastSelectedTargetEventSignatureRef.current = selectedTargetSignature;
            emitEnvelope(buildBuilderSelectTargetMessage(selectedTargetPayload, {
                source: 'sidebar',
                projectId,
                page: selectedPage,
                requestId: requestId ?? null,
            }));
        }
    };

    const emitCurrentStructureSnapshot = (requestId?: string | null) => {
        if (!isEmbeddedSidebarMode) {
            return;
        }

        const sections = sectionsDraft.map((section) => {
            const parsedProps = getBuilderSectionExplicitProps(section);
            const sectionType = normalizeSectionTypeKey(section.type);
            const label = sectionDisplayLabelByKey.get(sectionType) ?? sectionDisplayLabelByKey.get(section.type) ?? section.type;

            return {
                localId: section.localId,
                sectionKey: sectionType,
                type: sectionType,
                label,
                previewText: buildSectionPreviewText(parsedProps ?? {}, t('No preview text')),
                propsText: section.propsText,
                props: parsedProps ?? {},
            };
        });

        emitEnvelope(buildBuilderSyncStateMessage({
            stateVersion,
            structureHash,
            revisionId,
            revisionVersion,
            structureSections: sections,
        }, {
            source: 'sidebar',
            projectId,
            page: selectedPage,
            requestId: requestId ?? null,
        }));
    };

    const emitCurrentLibrarySnapshot = (requestId?: string | null) => {
        if (!isEmbeddedSidebarMode) {
            return;
        }

        const items = builderSectionLibrary.map((item) => {
            const normalizedKey = normalizeSectionTypeKey(item.key);

            return {
                key: normalizedKey,
                label: sectionDisplayLabelByKey.get(normalizedKey) ?? sectionDisplayLabelByKey.get(item.key) ?? item.label ?? item.key,
                category: item.category ?? '',
            };
        });

        emitEnvelope(buildBuilderSyncStateMessage({
            stateVersion,
            structureHash,
            revisionId,
            revisionVersion,
            libraryItems: items,
        }, {
            source: 'sidebar',
            projectId,
            page: selectedPage,
            requestId: requestId ?? null,
        }));
    };

    useEffect(() => {
        if (!isEmbeddedVisualBuilder || typeof window === 'undefined' || window.parent === window) {
            return;
        }

        emitCurrentVisualState();
    }, [
        builderInteractionState,
        builderViewport,
        emitCurrentVisualState,
        isEmbeddedVisualBuilder,
        isStructurePanelCollapsed,
    ]);

    useEffect(() => {
        if (!isEmbeddedMode || typeof window === 'undefined') {
            return;
        }

        const handleEmbeddedBuilderMessage = (event: MessageEvent) => {
            if (event.origin !== targetOrigin) {
                logSidebarBridge({
                    phase: 'ignore',
                    target: 'sidebar',
                    reason: 'origin-mismatch',
                });
                return;
            }

            const parsedEnvelope = inspectBuilderBridgeEnvelope(event.data);
            const payload = parsedEnvelope.message;
            if (!payload) {
                logSidebarBridge({
                    phase: 'ignore',
                    target: 'sidebar',
                    reason: parsedEnvelope.error ?? 'invalid-envelope',
                });
                return;
            }

            if (payload.source !== 'chat') {
                logSidebarBridge({
                    phase: 'ignore',
                    target: 'sidebar',
                    message: payload,
                    reason: 'unexpected-source',
                });
                return;
            }

            if (!builderBridgeEnvelopeTargetsProject(payload, projectId)) {
                logSidebarBridge({
                    phase: 'ignore',
                    target: 'sidebar',
                    message: payload,
                    reason: 'project-mismatch',
                });
                return;
            }

            if (payload.type === 'BUILDER_REQUEST_STATE') {
                logSidebarBridge({
                    phase: 'receive',
                    target: 'sidebar',
                    message: payload,
                });
                emitCurrentReady(payload.requestId);
                emitCurrentVisualState(payload.requestId);
                emitCurrentSelectedTarget(payload.requestId, true);
                emitCurrentStructureSnapshot(payload.requestId);
                emitCurrentLibrarySnapshot(payload.requestId);
                return;
            }

            if (!payloadTargetsBuilderBridgePage(payload, selectedPage)) {
                logSidebarBridge({
                    phase: 'ignore',
                    target: 'sidebar',
                    message: payload,
                    reason: 'page-mismatch',
                });
                return;
            }

            logSidebarBridge({
                phase: 'receive',
                target: 'sidebar',
                message: payload,
            });

            if (payload.type === 'BUILDER_SYNC_STATE') {
                if (payload.payload.viewport) {
                    onSetViewport(payload.payload.viewport);
                }
                if (payload.payload.interactionState) {
                    onSetInteractionState(payload.payload.interactionState);
                }
                if (payload.payload.sidebarMode) {
                    onSetSidebarMode(payload.payload.sidebarMode);
                }
                if (typeof payload.payload.structureOpen === 'boolean') {
                    onSetStructureOpen(payload.payload.structureOpen);
                }
                if (payload.payload.selectedTarget) {
                    onSetSelectedTarget(payload.payload.selectedTarget);
                }
                return;
            }

            if (payload.type === 'BUILDER_REFRESH_PREVIEW') {
                onRefreshPreview();
                return;
            }

            if (payload.type === 'BUILDER_CLEAR_SELECTION') {
                onClearSelectedSection();
                return;
            }

            if (payload.type === 'BUILDER_SELECT_TARGET') {
                if (payload.payload.target) {
                    onSetSelectedTarget(payload.payload.target);
                } else {
                    onClearSelectedSection();
                }
                return;
            }

            if (payload.type === 'BUILDER_PATCH_PROPS') {
                onApplyChangeSet({
                    requestId: payload.requestId,
                    changeSet: payload.payload.changeSet,
                }, emit);
                return;
            }

            if (payload.type === 'BUILDER_SAVE_DRAFT') {
                onSaveDraft(emit);
                return;
            }

            if (payload.type === 'BUILDER_INSERT_NODE') {
                if (Array.isArray(payload.payload.sections) && !hasBuilderBridgePageIdentity(selectedPage)) {
                    onSetInitialSections(payload.payload.sections);
                    return;
                }

                if (payload.payload.sectionKey) {
                    onAddSectionByKey({
                        requestId: payload.requestId,
                        sectionKey: payload.payload.sectionKey,
                        sectionLocalId: payload.payload.sectionLocalId ?? null,
                        afterSectionLocalId: payload.payload.afterSectionLocalId ?? null,
                        targetSectionKey: payload.payload.targetSectionKey ?? null,
                        placement: payload.payload.placement ?? null,
                    }, emit);
                }
                return;
            }

            if (payload.type === 'BUILDER_DELETE_NODE') {
                onRemoveSection({
                    requestId: payload.requestId,
                    sectionLocalId: payload.payload.sectionLocalId ?? null,
                    sectionIndex: payload.payload.sectionIndex ?? null,
                    sectionKey: payload.payload.sectionKey ?? null,
                }, emit);
                return;
            }

            if (payload.type === 'BUILDER_MOVE_NODE') {
                onMoveSection({
                    requestId: payload.requestId,
                    sectionLocalId: payload.payload.sectionLocalId,
                    targetSectionLocalId: payload.payload.targetSectionLocalId,
                    position: payload.payload.position,
                }, emit);
            }
        };

        window.addEventListener('message', handleEmbeddedBuilderMessage);

        return () => {
            window.removeEventListener('message', handleEmbeddedBuilderMessage);
        };
    }, [
        isEmbeddedMode,
        onAddSectionByKey,
        onApplyChangeSet,
        onClearSelectedSection,
        onMoveSection,
        onRefreshPreview,
        onRemoveSection,
        onSaveDraft,
        onSetInitialSections,
        onSetInteractionState,
        onSetSelectedTarget,
        onSetSidebarMode,
        onSetStructureOpen,
        onSetViewport,
        emitCurrentLibrarySnapshot,
        emitCurrentReady,
        emitCurrentSelectedTarget,
        emitCurrentStructureSnapshot,
        emitCurrentVisualState,
        projectId,
        selectedPage,
        targetOrigin,
    ]);

    useEffect(() => {
        emitCurrentSelectedTarget();
    }, [
        emitCurrentSelectedTarget,
    ]);

    useEffect(() => {
        emitCurrentStructureSnapshot();
    }, [
        emitCurrentStructureSnapshot,
    ]);

    useEffect(() => {
        emitCurrentLibrarySnapshot();
    }, [
        emitCurrentLibrarySnapshot,
    ]);
}
