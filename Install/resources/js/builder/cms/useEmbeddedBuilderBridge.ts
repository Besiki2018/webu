import { useEffect, useRef } from 'react';
import {
    hasBuilderBridgePageIdentity,
    parseBuilderBridgeChatCommand,
    payloadTargetsBuilderBridgePage,
    postBuilderBridgeMessage,
    type BuilderBridgeChatCommand,
    type BuilderBridgeCmsEvent,
    type BuilderBridgeInteractionState,
    type BuilderBridgePageIdentity,
    type BuilderBridgeSidebarMode,
    type BuilderBridgeViewport,
} from '@/builder/cms/embeddedBuilderBridgeContract';
import {
    buildBuilderSelectionMessageSignature,
    type BuilderEditableTarget,
} from '@/builder/editingState';
import type { SectionDraft } from '@/builder/state/useBuilderCanvasState';
import { buildCanonicalBridgeSelectedTargetPayload } from '@/builder/cms/canonicalSelectionPayload';

interface BuilderLibrarySnapshotItem {
    key: string;
    label?: string | null;
    category?: string | null;
}

export type EmbeddedBuilderIncomingPayload = BuilderBridgeChatCommand;
export type EmbeddedBuilderApplyChangeSetPayload = Extract<BuilderBridgeChatCommand, { type: 'builder:apply-change-set' }>;
export type EmbeddedBuilderSelectedTargetPayload = Extract<BuilderBridgeChatCommand, { type: 'builder:set-selected-target' }>;
export type EmbeddedBuilderAddSectionPayload = Extract<BuilderBridgeChatCommand, { type: 'builder:add-section-by-key' }>;
export type EmbeddedBuilderRemoveSectionPayload = Extract<BuilderBridgeChatCommand, { type: 'builder:remove-section' }>;
export type EmbeddedBuilderMoveSectionPayload = Extract<BuilderBridgeChatCommand, { type: 'builder:move-section' }>;
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
    onSetSelectedSection: (payload: BuilderSelectedSectionPayload) => void;
    onSetSelectedSectionKey: (payload: BuilderSelectedSectionKeyPayload) => void;
    onSaveDraft: (emit: EmitEmbeddedBuilderMessage) => void;
    onAddSectionByKey: (payload: EmbeddedBuilderAddSectionPayload, emit: EmitEmbeddedBuilderMessage) => void;
    onRemoveSection: (payload: EmbeddedBuilderRemoveSectionPayload, emit: EmitEmbeddedBuilderMessage) => void;
    onMoveSection: (payload: EmbeddedBuilderMoveSectionPayload, emit: EmitEmbeddedBuilderMessage) => void;
}

function readTrimmedString(value: unknown): string | null {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

export function useEmbeddedBuilderBridge({
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
    onSetSelectedSection,
    onSetSelectedSectionKey,
    onSaveDraft,
    onAddSectionByKey,
    onRemoveSection,
    onMoveSection,
}: UseEmbeddedBuilderBridgeOptions): void {
    const lastSelectedTargetEventSignatureRef = useRef<string | null>(null);

    useEffect(() => {
        if (!isEmbeddedVisualBuilder || typeof window === 'undefined' || window.parent === window) {
            return;
        }

        postBuilderBridgeMessage(window.parent, window.location.origin, 'webu-cms-builder', {
            type: 'builder:state',
            viewport: builderViewport,
            structureOpen: !isStructurePanelCollapsed,
            interactionState: builderInteractionState,
            stateVersion,
            structureHash,
            revisionId,
            revisionVersion,
        }, selectedPage);
    }, [
        builderInteractionState,
        builderViewport,
        isEmbeddedVisualBuilder,
        isStructurePanelCollapsed,
        revisionId,
        revisionVersion,
        selectedPage,
        stateVersion,
        structureHash,
    ]);

    useEffect(() => {
        if (!isEmbeddedMode || typeof window === 'undefined') {
            return;
        }

        const targetOrigin = window.location.origin;
        const emit: EmitEmbeddedBuilderMessage = (message) => {
            if (window.parent === window) {
                return;
            }

            postBuilderBridgeMessage(window.parent, targetOrigin, 'webu-cms-builder', {
                ...message,
                stateVersion,
                structureHash,
                revisionId,
                revisionVersion,
            }, selectedPage);
        };

        const handleEmbeddedBuilderMessage = (event: MessageEvent) => {
            if (event.origin !== targetOrigin) {
                return;
            }

            const payload = parseBuilderBridgeChatCommand(event.data);
            if (!payload) {
                return;
            }

            if (payload.type === 'builder:set-viewport') {
                onSetViewport(payload.viewport);
                return;
            }

            if (payload.type === 'builder:set-interaction-state') {
                onSetInteractionState(payload.interactionState);
                return;
            }

            if (payload.type === 'builder:refresh-preview') {
                onRefreshPreview();
                return;
            }

            if (payload.type === 'builder:set-sidebar-mode') {
                onSetSidebarMode(payload.mode);
                return;
            }

            if (payload.type === 'builder:ping') {
                emit({
                    type: 'builder:ready',
                    stateVersion,
                    structureHash,
                    revisionId,
                    revisionVersion,
                });
                return;
            }

            if (!payloadTargetsBuilderBridgePage(payload, selectedPage)) {
                return;
            }

            if (payload.type === 'builder:clear-selected-section') {
                onClearSelectedSection();
                return;
            }

            if (payload.type === 'builder:set-initial-sections') {
                if (!hasBuilderBridgePageIdentity(selectedPage)) {
                    onSetInitialSections(payload.sections);
                }
                return;
            }

            if (payload.type === 'builder:apply-change-set') {
                onApplyChangeSet(payload, emit);
                return;
            }

            if (payload.type === 'builder:set-selected-target') {
                onSetSelectedTarget(payload);
                return;
            }

            if (payload.type === 'builder:set-structure-open') {
                onSetStructureOpen(payload.open);
                return;
            }

            if (payload.type === 'builder:set-selected-section') {
                onSetSelectedSection({
                    sectionLocalId: payload.sectionLocalId,
                    parameterPath: readTrimmedString(payload.parameterPath),
                });
                return;
            }

            if (payload.type === 'builder:set-selected-section-key') {
                const nextSectionKey = readTrimmedString(payload.sectionKey);
                if (nextSectionKey) {
                    onSetSelectedSectionKey({
                        sectionKey: nextSectionKey,
                        parameterPath: readTrimmedString(payload.parameterPath),
                    });
                }
                return;
            }

            if (payload.type === 'builder:save-draft') {
                onSaveDraft(emit);
                return;
            }

            if (payload.type === 'builder:add-section-by-key') {
                onAddSectionByKey(payload, emit);
                return;
            }

            if (payload.type === 'builder:remove-section') {
                onRemoveSection(payload, emit);
                return;
            }

            if (payload.type === 'builder:move-section') {
                onMoveSection(payload, emit);
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
        onSetSelectedSection,
        onSetSelectedSectionKey,
        onSetSelectedTarget,
        onSetSidebarMode,
        onSetStructureOpen,
        onSetViewport,
        revisionId,
        revisionVersion,
        selectedPage,
        stateVersion,
        structureHash,
    ]);

    useEffect(() => {
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
            type: 'builder:selected-target',
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

        if (lastSelectedTargetEventSignatureRef.current !== selectedTargetSignature) {
            lastSelectedTargetEventSignatureRef.current = selectedTargetSignature;
            postBuilderBridgeMessage(window.parent, window.location.origin, 'webu-cms-builder', {
                type: 'builder:selected-target',
                ...(selectedTargetPayload ?? {}),
                stateVersion,
                structureHash,
                revisionId,
                revisionVersion,
            }, selectedPage);
        }
    }, [
        isEmbeddedVisualBuilder,
        lastSelectedTargetEventSignatureRef,
        buildSectionPreviewText,
        getBuilderSectionExplicitProps,
        normalizeSectionTypeKey,
        revisionId,
        revisionVersion,
        selectedPage,
        selectedBuilderTarget,
        selectedFixedSectionKey,
        selectedSectionDraft,
        selectedSectionLocalId,
        sectionDisplayLabelByKey,
        t,
        selectedTargetInteractionState,
        selectedTargetViewport,
        stateVersion,
        structureHash,
    ]);

    useEffect(() => {
        if (!isEmbeddedSidebarMode || typeof window === 'undefined' || window.parent === window) {
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

        postBuilderBridgeMessage(window.parent, window.location.origin, 'webu-cms-builder', {
            type: 'builder:structure-snapshot',
            sections,
            stateVersion,
            structureHash,
            revisionId,
            revisionVersion,
        }, selectedPage);
    }, [
        buildSectionPreviewText,
        getBuilderSectionExplicitProps,
        isEmbeddedSidebarMode,
        normalizeSectionTypeKey,
        revisionId,
        revisionVersion,
        sectionDisplayLabelByKey,
        sectionsDraft,
        selectedPage,
        stateVersion,
        structureHash,
        t,
    ]);

    useEffect(() => {
        if (!isEmbeddedSidebarMode || typeof window === 'undefined' || window.parent === window) {
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

        postBuilderBridgeMessage(window.parent, window.location.origin, 'webu-cms-builder', {
            type: 'builder:library-snapshot',
            items,
            stateVersion,
            structureHash,
            revisionId,
            revisionVersion,
        }, selectedPage);
    }, [
        builderSectionLibrary,
        isEmbeddedSidebarMode,
        normalizeSectionTypeKey,
        revisionId,
        revisionVersion,
        sectionDisplayLabelByKey,
        selectedPage,
        stateVersion,
        structureHash,
    ]);
}
