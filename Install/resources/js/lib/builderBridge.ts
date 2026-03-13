import type { BuilderSelectionMessagePayload } from '@/builder/editingState';
import { isRecord } from '@/builder/state/sectionProps';
import type {
    BuilderBridgeInteractionState,
    BuilderBridgeLibraryItem,
    BuilderBridgeMutationType,
    BuilderBridgePageIdentity,
    BuilderBridgeSidebarMode,
    BuilderBridgeStateMeta,
    BuilderBridgeStructureSection,
    BuilderBridgeViewport,
} from '@/builder/cms/embeddedBuilderBridgeContract';

export const BUILDER_BRIDGE_VERSION = 1 as const;

export type BuilderBridgeRuntimeSource = 'chat' | 'preview' | 'sidebar';
export type BuilderBridgeDiagnosticPhase = 'send' | 'receive' | 'ignore' | 'drop';

export type BuilderBridgeMessageType =
    | 'BUILDER_READY'
    | 'BUILDER_SYNC_STATE'
    | 'BUILDER_SELECT_TARGET'
    | 'BUILDER_HOVER_TARGET'
    | 'BUILDER_PATCH_PROPS'
    | 'BUILDER_DELETE_NODE'
    | 'BUILDER_INSERT_NODE'
    | 'BUILDER_MOVE_NODE'
    | 'BUILDER_CLEAR_SELECTION'
    | 'BUILDER_REQUEST_STATE'
    | 'BUILDER_ACK'
    | 'BUILDER_SAVE_DRAFT'
    | 'BUILDER_REFRESH_PREVIEW';

export interface BuilderBridgeEnvelopeBase<
    TType extends BuilderBridgeMessageType,
    TPayload,
> extends BuilderBridgePageIdentity {
    type: TType;
    source: BuilderBridgeRuntimeSource;
    signature: string;
    projectId: string;
    requestId: string;
    timestamp: number;
    version: typeof BUILDER_BRIDGE_VERSION;
    payload: TPayload;
}

export interface BuilderBridgeReadyPayload extends BuilderBridgeStateMeta {
    channel: 'preview' | 'sidebar';
}

export interface BuilderBridgeSyncStatePayload extends BuilderBridgeStateMeta {
    viewport?: BuilderBridgeViewport | null;
    interactionState?: BuilderBridgeInteractionState | null;
    structureOpen?: boolean | null;
    sidebarMode?: BuilderBridgeSidebarMode | null;
    selectedTarget?: BuilderSelectionMessagePayload | null;
    hoveredTarget?: BuilderSelectionMessagePayload | null;
    structureSections?: BuilderBridgeStructureSection[] | null;
    libraryItems?: BuilderBridgeLibraryItem[] | null;
    draftSaveState?: {
        isSaving: boolean;
        success?: boolean | null;
        message?: string | null;
    } | null;
    previewRefresh?: boolean;
}

export interface BuilderBridgePatchPropsPayload {
    changeSet: Record<string, unknown>;
}

export interface BuilderBridgeDeleteNodePayload {
    sectionLocalId?: string | null;
    sectionIndex?: number | null;
    sectionKey?: string | null;
}

export interface BuilderBridgeInsertNodePayload {
    sectionKey?: string | null;
    sectionLocalId?: string | null;
    afterSectionLocalId?: string | null;
    targetSectionKey?: string | null;
    placement?: 'before' | 'after' | 'inside' | null;
    sections?: Array<Record<string, unknown>>;
}

export interface BuilderBridgeRequestStatePayload {
    reason?: string | null;
}

export interface BuilderBridgeMoveNodePayload {
    sectionLocalId: string;
    targetSectionLocalId: string;
    position: 'before' | 'after';
}

export interface BuilderBridgeAckPayload extends BuilderBridgeStateMeta {
    ackType: BuilderBridgeMessageType;
    success: boolean;
    changed?: boolean | null;
    error?: string | null;
    mutation?: BuilderBridgeMutationType | null;
}

export interface BuilderBridgeSaveDraftPayload {
    reason?: string | null;
}

export interface BuilderBridgeRefreshPreviewPayload {
    reason?: string | null;
}

export type BuilderReadyMessage = BuilderBridgeEnvelopeBase<'BUILDER_READY', BuilderBridgeReadyPayload>;
export type BuilderSyncStateMessage = BuilderBridgeEnvelopeBase<'BUILDER_SYNC_STATE', BuilderBridgeSyncStatePayload>;
export type BuilderSelectTargetMessage = BuilderBridgeEnvelopeBase<'BUILDER_SELECT_TARGET', {
    target: BuilderSelectionMessagePayload | null;
}>;
export type BuilderHoverTargetMessage = BuilderBridgeEnvelopeBase<'BUILDER_HOVER_TARGET', {
    target: BuilderSelectionMessagePayload | null;
}>;
export type BuilderPatchPropsMessage = BuilderBridgeEnvelopeBase<'BUILDER_PATCH_PROPS', BuilderBridgePatchPropsPayload>;
export type BuilderDeleteNodeMessage = BuilderBridgeEnvelopeBase<'BUILDER_DELETE_NODE', BuilderBridgeDeleteNodePayload>;
export type BuilderInsertNodeMessage = BuilderBridgeEnvelopeBase<'BUILDER_INSERT_NODE', BuilderBridgeInsertNodePayload>;
export type BuilderMoveNodeMessage = BuilderBridgeEnvelopeBase<'BUILDER_MOVE_NODE', BuilderBridgeMoveNodePayload>;
export type BuilderClearSelectionMessage = BuilderBridgeEnvelopeBase<'BUILDER_CLEAR_SELECTION', {
    reason?: string | null;
}>;
export type BuilderRequestStateMessage = BuilderBridgeEnvelopeBase<'BUILDER_REQUEST_STATE', BuilderBridgeRequestStatePayload>;
export type BuilderAckMessage = BuilderBridgeEnvelopeBase<'BUILDER_ACK', BuilderBridgeAckPayload>;
export type BuilderSaveDraftMessage = BuilderBridgeEnvelopeBase<'BUILDER_SAVE_DRAFT', BuilderBridgeSaveDraftPayload>;
export type BuilderRefreshPreviewMessage = BuilderBridgeEnvelopeBase<'BUILDER_REFRESH_PREVIEW', BuilderBridgeRefreshPreviewPayload>;

export type BuilderBridgeMessage =
    | BuilderReadyMessage
    | BuilderSyncStateMessage
    | BuilderSelectTargetMessage
    | BuilderHoverTargetMessage
    | BuilderPatchPropsMessage
    | BuilderDeleteNodeMessage
    | BuilderInsertNodeMessage
    | BuilderMoveNodeMessage
    | BuilderClearSelectionMessage
    | BuilderRequestStateMessage
    | BuilderAckMessage
    | BuilderSaveDraftMessage
    | BuilderRefreshPreviewMessage;

export interface BuilderBridgeParseResult {
    message: BuilderBridgeMessage | null;
    error: string | null;
}

interface BuilderBridgeVisualStateSignatureInput extends BuilderBridgePageIdentity {
    viewport?: BuilderBridgeViewport | null;
    interactionState?: BuilderBridgeInteractionState | null;
    structureOpen?: boolean | null;
    sidebarMode?: BuilderBridgeSidebarMode | null;
}

interface BuilderBridgeSelectionSignatureInput extends BuilderBridgePageIdentity {
    target: BuilderSelectionMessagePayload | null | undefined;
}

interface BuilderBridgeDiagnosticInput {
    phase: BuilderBridgeDiagnosticPhase;
    actor: BuilderBridgeRuntimeSource;
    target?: string | null;
    message?: BuilderBridgeMessage | null;
    rawType?: string | null;
    requestId?: string | null;
    reason?: string | null;
}

interface BuilderBridgeEnvelopeSignatureInput extends BuilderBridgePageIdentity {
    type: BuilderBridgeMessageType;
    source: BuilderBridgeRuntimeSource;
    projectId: string;
    requestId: string;
    timestamp: number;
    version: typeof BUILDER_BRIDGE_VERSION;
    payload: unknown;
}

interface BuilderBridgeEnvelopeMetadata extends BuilderBridgePageIdentity {
    source: BuilderBridgeRuntimeSource;
    signature: string;
    projectId: string;
    requestId: string;
    timestamp: number;
    version: typeof BUILDER_BRIDGE_VERSION;
}

const BUILDER_BRIDGE_TRACKED_SIGNATURE_LIMIT = 200;

function readBuilderBridgeDebugFlag(): boolean {
    try {
        if (typeof process !== 'undefined' && process.env?.NODE_ENV === 'development') {
            return true;
        }
    } catch {
        // Ignore environments without process.
    }

    try {
        return typeof import.meta !== 'undefined'
            && Boolean(import.meta.env?.DEV)
            && import.meta.env?.MODE !== 'test';
    } catch {
        return false;
    }
}

export function isBuilderBridgeDebugEnabled(): boolean {
    return readBuilderBridgeDebugFlag();
}

function resolveSelectedTargetId(
    target: BuilderSelectionMessagePayload | null | undefined,
): string | null {
    if (!target) {
        return null;
    }

    return target.builderId?.trim()
        || target.elementId?.trim()
        || target.sectionLocalId?.trim()
        || target.sectionId?.trim()
        || target.sectionKey?.trim()
        || null;
}

function resolveSelectedTargetIdFromMessage(message: BuilderBridgeMessage | null | undefined): string | null {
    if (!message) {
        return null;
    }

    switch (message.type) {
        case 'BUILDER_SELECT_TARGET':
        case 'BUILDER_HOVER_TARGET':
            return resolveSelectedTargetId(message.payload.target);
        case 'BUILDER_SYNC_STATE':
            return resolveSelectedTargetId(message.payload.selectedTarget)
                || resolveSelectedTargetId(message.payload.hoveredTarget);
        case 'BUILDER_DELETE_NODE':
            return message.payload.sectionLocalId?.trim()
                || message.payload.sectionKey?.trim()
                || null;
        case 'BUILDER_INSERT_NODE':
            return message.payload.sectionLocalId?.trim()
                || message.payload.afterSectionLocalId?.trim()
                || message.payload.sectionKey?.trim()
                || message.payload.targetSectionKey?.trim()
                || null;
        case 'BUILDER_MOVE_NODE':
            return message.payload.sectionLocalId?.trim()
                || message.payload.targetSectionLocalId?.trim()
                || null;
        default:
            return null;
    }
}

function resolveMutationType(message: BuilderBridgeMessage | null | undefined): string | null {
    if (!message) {
        return null;
    }

    switch (message.type) {
        case 'BUILDER_PATCH_PROPS':
        case 'BUILDER_DELETE_NODE':
        case 'BUILDER_INSERT_NODE':
        case 'BUILDER_MOVE_NODE':
        case 'BUILDER_SAVE_DRAFT':
        case 'BUILDER_REFRESH_PREVIEW':
            return message.type;
        case 'BUILDER_ACK':
            return message.payload.mutation ?? message.payload.ackType;
        default:
            return null;
    }
}

function resolveMutationId(message: BuilderBridgeMessage | null | undefined): string | null {
    const mutationType = resolveMutationType(message);
    if (!message || !mutationType) {
        return null;
    }

    return message.requestId;
}

export function logBuilderBridgeDiagnostic({
    phase,
    actor,
    target = null,
    message = null,
    rawType = null,
    requestId = null,
    reason = null,
}: BuilderBridgeDiagnosticInput): void {
    if (!isBuilderBridgeDebugEnabled()) {
        return;
    }

    console.debug('[BuilderBridge]', {
        phase,
        actor,
        type: message?.type ?? rawType ?? null,
        source: message?.source ?? actor,
        signature: message?.signature ?? null,
        target,
        projectId: message?.projectId ?? null,
        requestId: message?.requestId ?? requestId ?? null,
        selectedTargetId: resolveSelectedTargetIdFromMessage(message),
        mutationId: resolveMutationId(message),
        mutationType: resolveMutationType(message),
        reason,
    });
}

interface BuilderBridgeMessageBaseInput {
    source: BuilderBridgeRuntimeSource;
    projectId: string | number;
    page: BuilderBridgePageIdentity | null | undefined;
    requestId?: string | null;
    timestamp?: number | null;
}

function normalizeProjectId(value: string | number): string {
    return `${value}`.trim();
}

function readNumber(value: unknown): number | null {
    return typeof value === 'number' && Number.isFinite(value) ? value : null;
}

function readText(value: unknown): string | null {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

function readBoolean(value: unknown): boolean | null {
    return typeof value === 'boolean' ? value : null;
}

function readViewport(value: unknown): BuilderBridgeViewport | null {
    return value === 'desktop' || value === 'tablet' || value === 'mobile' ? value : null;
}

function readInteractionState(value: unknown): BuilderBridgeInteractionState | null {
    return value === 'normal' || value === 'hover' || value === 'focus' || value === 'active' ? value : null;
}

function readSidebarMode(value: unknown): BuilderBridgeSidebarMode | null {
    return value === 'elements' || value === 'settings' ? value : null;
}

function readStringArray(value: unknown): string[] | null {
    if (!Array.isArray(value)) {
        return null;
    }

    return value
        .map((entry) => (typeof entry === 'string' ? entry.trim() : ''))
        .filter(Boolean);
}

function readRecord(value: unknown): Record<string, unknown> | null {
    return isRecord(value) ? value : null;
}

function canonicalizeBuilderBridgeSignatureValue(value: unknown): unknown {
    if (Array.isArray(value)) {
        return value.map((entry) => canonicalizeBuilderBridgeSignatureValue(entry));
    }

    if (!isRecord(value)) {
        return value ?? null;
    }

    return Object.keys(value)
        .sort()
        .reduce<Record<string, unknown>>((normalized, key) => {
            normalized[key] = canonicalizeBuilderBridgeSignatureValue(value[key]);
            return normalized;
        }, {});
}

export function buildBuilderBridgeEnvelopeSignature(message: BuilderBridgeEnvelopeSignatureInput): string {
    return JSON.stringify(canonicalizeBuilderBridgeSignatureValue({
        type: message.type,
        source: message.source,
        projectId: message.projectId,
        requestId: message.requestId,
        timestamp: message.timestamp,
        version: message.version,
        pageId: message.pageId ?? null,
        pageSlug: message.pageSlug ?? null,
        pageTitle: message.pageTitle ?? null,
        payload: normalizeBuilderBridgePayloadForSignature(message.type, message.payload),
    }));
}

export function rememberBuilderBridgeEnvelopeSignature(
    signatures: Set<string>,
    signature: string | null | undefined,
): boolean {
    const normalizedSignature = readText(signature);
    if (!normalizedSignature) {
        return false;
    }

    if (signatures.has(normalizedSignature)) {
        return false;
    }

    signatures.add(normalizedSignature);
    if (signatures.size > BUILDER_BRIDGE_TRACKED_SIGNATURE_LIMIT) {
        const oldestSignature = signatures.values().next().value;
        if (typeof oldestSignature === 'string') {
            signatures.delete(oldestSignature);
        }
    }

    return true;
}

function readStateMeta(record: Record<string, unknown>): BuilderBridgeStateMeta {
    return {
        stateVersion: readNumber(record.stateVersion),
        structureHash: readText(record.structureHash),
        revisionId: readNumber(record.revisionId),
        revisionVersion: readNumber(record.revisionVersion),
    };
}

function parseLibraryItem(value: unknown): BuilderBridgeLibraryItem | null {
    const record = readRecord(value);
    if (!record) {
        return null;
    }

    const key = readText(record.key);
    const label = readText(record.label);
    const category = readText(record.category);
    if (!key || !label || !category) {
        return null;
    }

    return { key, label, category };
}

function parseStructureSection(value: unknown): BuilderBridgeStructureSection | null {
    const record = readRecord(value);
    if (!record) {
        return null;
    }

    const localId = readText(record.localId);
    const sectionKey = readText(record.sectionKey);
    const type = readText(record.type);
    const label = readText(record.label);
    if (!localId || !sectionKey || !type || !label) {
        return null;
    }

    return {
        localId,
        sectionKey,
        type,
        label,
        previewText: typeof record.previewText === 'string' ? record.previewText : '',
        propsText: typeof record.propsText === 'string' ? record.propsText : '',
        props: readRecord(record.props) ?? {},
    };
}

function parseSelectionPayload(value: unknown): BuilderSelectionMessagePayload | null {
    const record = readRecord(value);
    if (!record) {
        return null;
    }

    return {
        pageId: readNumber(record.pageId),
        pageSlug: readText(record.pageSlug),
        pageTitle: readText(record.pageTitle),
        sectionLocalId: readText(record.sectionLocalId),
        sectionKey: readText(record.sectionKey),
        componentType: readText(record.componentType),
        componentName: readText(record.componentName),
        parameterPath: readText(record.parameterPath),
        componentPath: readText(record.componentPath),
        elementId: readText(record.elementId),
        selector: readText(record.selector),
        textPreview: readText(record.textPreview),
        props: readRecord(record.props),
        fieldLabel: readText(record.fieldLabel),
        fieldGroup: readText(record.fieldGroup) as BuilderSelectionMessagePayload['fieldGroup'] ?? null,
        builderId: readText(record.builderId),
        parentId: readText(record.parentId),
        editableFields: readStringArray(record.editableFields) ?? [],
        sectionId: readText(record.sectionId),
        instanceId: readText(record.instanceId),
        variants: readRecord(record.variants) as BuilderSelectionMessagePayload['variants'] ?? null,
        allowedUpdates: readRecord(record.allowedUpdates) as BuilderSelectionMessagePayload['allowedUpdates'] ?? null,
        currentBreakpoint: readViewport(record.currentBreakpoint),
        currentInteractionState: readInteractionState(record.currentInteractionState),
        responsiveContext: readRecord(record.responsiveContext) as BuilderSelectionMessagePayload['responsiveContext'] ?? null,
    };
}

function normalizeBuilderBridgePayloadForSignature(
    type: BuilderBridgeMessageType,
    payload: unknown,
): unknown {
    const record = readRecord(payload) ?? {};

    switch (type) {
        case 'BUILDER_READY':
            return {
                channel: record.channel === 'preview' || record.channel === 'sidebar'
                    ? record.channel
                    : null,
                ...readStateMeta(record),
            };
        case 'BUILDER_SYNC_STATE':
            return {
                ...readStateMeta(record),
                viewport: readViewport(record.viewport),
                interactionState: readInteractionState(record.interactionState),
                structureOpen: readBoolean(record.structureOpen),
                sidebarMode: readSidebarMode(record.sidebarMode),
                selectedTarget: parseSelectionPayload(record.selectedTarget),
                hoveredTarget: parseSelectionPayload(record.hoveredTarget),
                structureSections: Array.isArray(record.structureSections)
                    ? record.structureSections
                        .map((entry) => parseStructureSection(entry))
                        .filter((entry): entry is BuilderBridgeStructureSection => entry !== null)
                    : null,
                libraryItems: Array.isArray(record.libraryItems)
                    ? record.libraryItems
                        .map((entry) => parseLibraryItem(entry))
                        .filter((entry): entry is BuilderBridgeLibraryItem => entry !== null)
                    : null,
                draftSaveState: (() => {
                    const draftSaveState = readRecord(record.draftSaveState);
                    if (!draftSaveState) {
                        return null;
                    }

                    const isSaving = readBoolean(draftSaveState.isSaving);
                    if (isSaving === null) {
                        return null;
                    }

                    return {
                        isSaving,
                        success: readBoolean(draftSaveState.success),
                        message: typeof draftSaveState.message === 'string' ? draftSaveState.message : null,
                    };
                })(),
                previewRefresh: readBoolean(record.previewRefresh) ?? false,
            };
        case 'BUILDER_SELECT_TARGET':
        case 'BUILDER_HOVER_TARGET':
            return {
                target: parseSelectionPayload(record.target),
            };
        case 'BUILDER_PATCH_PROPS':
            return {
                changeSet: readRecord(record.changeSet) ?? {},
            };
        case 'BUILDER_DELETE_NODE':
            return {
                sectionLocalId: readText(record.sectionLocalId),
                sectionIndex: readNumber(record.sectionIndex),
                sectionKey: readText(record.sectionKey),
            };
        case 'BUILDER_INSERT_NODE':
            return {
                sectionKey: readText(record.sectionKey),
                sectionLocalId: readText(record.sectionLocalId),
                afterSectionLocalId: readText(record.afterSectionLocalId),
                targetSectionKey: readText(record.targetSectionKey),
                placement: record.placement === 'before' || record.placement === 'after' || record.placement === 'inside'
                    ? record.placement
                    : null,
                sections: Array.isArray(record.sections)
                    ? record.sections.filter((entry): entry is Record<string, unknown> => isRecord(entry))
                    : undefined,
            };
        case 'BUILDER_MOVE_NODE':
            return {
                sectionLocalId: readText(record.sectionLocalId),
                targetSectionLocalId: readText(record.targetSectionLocalId),
                position: record.position === 'before' || record.position === 'after'
                    ? record.position
                    : null,
            };
        case 'BUILDER_CLEAR_SELECTION':
        case 'BUILDER_REQUEST_STATE':
        case 'BUILDER_SAVE_DRAFT':
        case 'BUILDER_REFRESH_PREVIEW':
            return {
                reason: readText(record.reason),
            };
        case 'BUILDER_ACK':
            return {
                ackType: readText(record.ackType),
                success: readBoolean(record.success),
                changed: readBoolean(record.changed),
                error: typeof record.error === 'string' ? record.error : null,
                mutation: record.mutation === 'apply-change-set'
                    || record.mutation === 'add-section'
                    || record.mutation === 'remove-section'
                    || record.mutation === 'move-section'
                    ? record.mutation
                    : null,
                ...readStateMeta(record),
            };
        default:
            return {};
    }
}

export function createBuilderBridgeRequestId(prefix = 'builder'): string {
    return `${prefix}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
}

function createBuilderBridgeEnvelope<TType extends BuilderBridgeMessageType, TPayload>(
    type: TType,
    payload: TPayload,
    input: BuilderBridgeMessageBaseInput,
): BuilderBridgeEnvelopeBase<TType, TPayload> {
    const envelope = {
        type,
        payload,
        source: input.source,
        projectId: normalizeProjectId(input.projectId),
        requestId: input.requestId && input.requestId.trim() !== ''
            ? input.requestId
            : createBuilderBridgeRequestId(type.toLowerCase()),
        timestamp: typeof input.timestamp === 'number' && Number.isFinite(input.timestamp)
            ? input.timestamp
            : Date.now(),
        version: BUILDER_BRIDGE_VERSION,
        pageId: input.page?.pageId ?? null,
        pageSlug: input.page?.pageSlug ?? null,
        pageTitle: input.page?.pageTitle ?? null,
    };

    return {
        ...envelope,
        signature: buildBuilderBridgeEnvelopeSignature(envelope),
    };
}

export function buildBuilderReadyMessage(
    payload: BuilderBridgeReadyPayload,
    input: BuilderBridgeMessageBaseInput,
): BuilderReadyMessage {
    return createBuilderBridgeEnvelope('BUILDER_READY', payload, input);
}

export function buildBuilderSyncStateMessage(
    payload: BuilderBridgeSyncStatePayload,
    input: BuilderBridgeMessageBaseInput,
): BuilderSyncStateMessage {
    return createBuilderBridgeEnvelope('BUILDER_SYNC_STATE', payload, input);
}

export function buildBuilderSelectTargetMessage(
    target: BuilderSelectionMessagePayload | null,
    input: BuilderBridgeMessageBaseInput,
): BuilderSelectTargetMessage {
    return createBuilderBridgeEnvelope('BUILDER_SELECT_TARGET', { target }, input);
}

export function buildBuilderHoverTargetMessage(
    target: BuilderSelectionMessagePayload | null,
    input: BuilderBridgeMessageBaseInput,
): BuilderHoverTargetMessage {
    return createBuilderBridgeEnvelope('BUILDER_HOVER_TARGET', { target }, input);
}

export function buildBuilderPatchPropsMessage(
    payload: BuilderBridgePatchPropsPayload,
    input: BuilderBridgeMessageBaseInput,
): BuilderPatchPropsMessage {
    return createBuilderBridgeEnvelope('BUILDER_PATCH_PROPS', payload, input);
}

export function buildBuilderDeleteNodeMessage(
    payload: BuilderBridgeDeleteNodePayload,
    input: BuilderBridgeMessageBaseInput,
): BuilderDeleteNodeMessage {
    return createBuilderBridgeEnvelope('BUILDER_DELETE_NODE', payload, input);
}

export function buildBuilderInsertNodeMessage(
    payload: BuilderBridgeInsertNodePayload,
    input: BuilderBridgeMessageBaseInput,
): BuilderInsertNodeMessage {
    return createBuilderBridgeEnvelope('BUILDER_INSERT_NODE', payload, input);
}

export function buildBuilderMoveNodeMessage(
    payload: BuilderBridgeMoveNodePayload,
    input: BuilderBridgeMessageBaseInput,
): BuilderMoveNodeMessage {
    return createBuilderBridgeEnvelope('BUILDER_MOVE_NODE', payload, input);
}

export function buildBuilderClearSelectionMessage(
    reason: string | null | undefined,
    input: BuilderBridgeMessageBaseInput,
): BuilderClearSelectionMessage {
    return createBuilderBridgeEnvelope('BUILDER_CLEAR_SELECTION', {
        reason: reason ?? null,
    }, input);
}

export function buildBuilderRequestStateMessage(
    payload: BuilderBridgeRequestStatePayload,
    input: BuilderBridgeMessageBaseInput,
): BuilderRequestStateMessage {
    return createBuilderBridgeEnvelope('BUILDER_REQUEST_STATE', payload, input);
}

export function buildBuilderAckMessage(
    payload: BuilderBridgeAckPayload,
    input: BuilderBridgeMessageBaseInput,
): BuilderAckMessage {
    return createBuilderBridgeEnvelope('BUILDER_ACK', payload, input);
}

export function buildBuilderSaveDraftMessage(
    payload: BuilderBridgeSaveDraftPayload,
    input: BuilderBridgeMessageBaseInput,
): BuilderSaveDraftMessage {
    return createBuilderBridgeEnvelope('BUILDER_SAVE_DRAFT', payload, input);
}

export function buildBuilderRefreshPreviewMessage(
    payload: BuilderBridgeRefreshPreviewPayload,
    input: BuilderBridgeMessageBaseInput,
): BuilderRefreshPreviewMessage {
    return createBuilderBridgeEnvelope('BUILDER_REFRESH_PREVIEW', payload, input);
}

export function postBuilderBridgeEnvelope(
    targetWindow: Window,
    targetOrigin: string,
    message: BuilderBridgeMessage,
): void {
    targetWindow.postMessage(message, targetOrigin);
}

export function inspectBuilderBridgeEnvelope(value: unknown): BuilderBridgeParseResult {
    const fail = (error: string): BuilderBridgeParseResult => ({
        message: null,
        error,
    });

    const record = readRecord(value);
    if (!record) {
        return fail('non-record-envelope');
    }

    const type = readText(record.type) as BuilderBridgeMessageType | null;
    const source = readText(record.source) as BuilderBridgeRuntimeSource | null;
    const signature = readText(record.signature);
    const projectId = readText(record.projectId);
    const requestId = readText(record.requestId);
    const timestamp = readNumber(record.timestamp);
    const version = readNumber(record.version);
    const payload = readRecord(record.payload) ?? {};

    if (
        !type
        || !source
        || (source !== 'chat' && source !== 'preview' && source !== 'sidebar')
        || !signature
        || !projectId
        || !requestId
        || timestamp === null
        || version !== BUILDER_BRIDGE_VERSION
    ) {
        if (!type) {
            return fail('missing-message-type');
        }
        if (!source || (source !== 'chat' && source !== 'preview' && source !== 'sidebar')) {
            return fail('invalid-message-source');
        }
        if (!signature) {
            return fail('missing-message-signature');
        }
        if (!projectId) {
            return fail('missing-project-id');
        }
        if (!requestId) {
            return fail('missing-request-id');
        }
        if (timestamp === null) {
            return fail('invalid-timestamp');
        }

        return fail('unsupported-message-version');
    }

    const base: BuilderBridgeEnvelopeMetadata = {
        source,
        signature,
        projectId,
        requestId,
        timestamp,
        version: BUILDER_BRIDGE_VERSION,
        pageId: readNumber(record.pageId),
        pageSlug: readText(record.pageSlug),
        pageTitle: readText(record.pageTitle),
    };

    const finalize = (message: BuilderBridgeMessage): BuilderBridgeParseResult => {
        const { signature: _signature, ...unsignedMessage } = message;
        if (signature !== buildBuilderBridgeEnvelopeSignature(unsignedMessage)) {
            return fail('invalid-message-signature');
        }

        return {
            message,
            error: null,
        };
    };

    switch (type) {
        case 'BUILDER_READY': {
            const channel = payload.channel === 'preview' || payload.channel === 'sidebar'
                ? payload.channel
                : null;
            if (!channel) {
                return fail('invalid-ready-channel');
            }
            const message: BuilderReadyMessage = {
                ...base,
                type,
                payload: {
                    channel,
                    ...readStateMeta(payload),
                },
            };
            return finalize(message);
        }
        case 'BUILDER_SYNC_STATE': {
            const message: BuilderSyncStateMessage = {
                ...base,
                type,
                payload: {
                    ...readStateMeta(payload),
                    viewport: readViewport(payload.viewport),
                    interactionState: readInteractionState(payload.interactionState),
                    structureOpen: readBoolean(payload.structureOpen),
                    sidebarMode: readSidebarMode(payload.sidebarMode),
                    selectedTarget: parseSelectionPayload(payload.selectedTarget),
                    hoveredTarget: parseSelectionPayload(payload.hoveredTarget),
                    structureSections: Array.isArray(payload.structureSections)
                        ? payload.structureSections
                            .map((entry) => parseStructureSection(entry))
                            .filter((entry): entry is BuilderBridgeStructureSection => entry !== null)
                        : null,
                    libraryItems: Array.isArray(payload.libraryItems)
                        ? payload.libraryItems
                            .map((entry) => parseLibraryItem(entry))
                            .filter((entry): entry is BuilderBridgeLibraryItem => entry !== null)
                        : null,
                    draftSaveState: (() => {
                        const draftSaveState = readRecord(payload.draftSaveState);
                        if (!draftSaveState) {
                            return null;
                        }

                        const isSaving = readBoolean(draftSaveState.isSaving);
                        if (isSaving === null) {
                            return null;
                        }

                        return {
                            isSaving,
                            success: readBoolean(draftSaveState.success),
                            message: typeof draftSaveState.message === 'string' ? draftSaveState.message : null,
                        };
                    })(),
                    previewRefresh: readBoolean(payload.previewRefresh) ?? false,
                },
            };
            return finalize(message);
        }
        case 'BUILDER_SELECT_TARGET': {
            const message: BuilderSelectTargetMessage = {
                ...base,
                type,
                payload: {
                    target: parseSelectionPayload(payload.target),
                },
            };
            return finalize(message);
        }
        case 'BUILDER_HOVER_TARGET': {
            const message: BuilderHoverTargetMessage = {
                ...base,
                type,
                payload: {
                    target: parseSelectionPayload(payload.target),
                },
            };
            return finalize(message);
        }
        case 'BUILDER_PATCH_PROPS': {
            const changeSet = readRecord(payload.changeSet);
            if (!changeSet) {
                return fail('invalid-change-set');
            }
            const message: BuilderPatchPropsMessage = {
                ...base,
                type,
                payload: {
                    changeSet,
                },
            };
            return finalize(message);
        }
        case 'BUILDER_DELETE_NODE': {
            const message: BuilderDeleteNodeMessage = {
                ...base,
                type,
                payload: {
                    sectionLocalId: readText(payload.sectionLocalId),
                    sectionIndex: readNumber(payload.sectionIndex),
                    sectionKey: readText(payload.sectionKey),
                },
            };
            return finalize(message);
        }
        case 'BUILDER_INSERT_NODE': {
            const placement = payload.placement === 'before' || payload.placement === 'after' || payload.placement === 'inside'
                ? payload.placement
                : null;
            const message: BuilderInsertNodeMessage = {
                ...base,
                type,
                payload: {
                    sectionKey: readText(payload.sectionKey),
                    sectionLocalId: readText(payload.sectionLocalId),
                    afterSectionLocalId: readText(payload.afterSectionLocalId),
                    targetSectionKey: readText(payload.targetSectionKey),
                    placement,
                    sections: Array.isArray(payload.sections)
                        ? payload.sections.filter((entry): entry is Record<string, unknown> => isRecord(entry))
                        : undefined,
                },
            };
            return finalize(message);
        }
        case 'BUILDER_MOVE_NODE': {
            const sectionLocalId = readText(payload.sectionLocalId);
            const targetSectionLocalId = readText(payload.targetSectionLocalId);
            const position = payload.position === 'before' || payload.position === 'after'
                ? payload.position
                : null;
            if (!sectionLocalId || !targetSectionLocalId || !position) {
                return fail('invalid-move-payload');
            }
            const message: BuilderMoveNodeMessage = {
                ...base,
                type,
                payload: {
                    sectionLocalId,
                    targetSectionLocalId,
                    position,
                },
            };
            return finalize(message);
        }
        case 'BUILDER_CLEAR_SELECTION': {
            const message: BuilderClearSelectionMessage = {
                ...base,
                type,
                payload: {
                    reason: readText(payload.reason),
                },
            };
            return finalize(message);
        }
        case 'BUILDER_REQUEST_STATE': {
            const message: BuilderRequestStateMessage = {
                ...base,
                type,
                payload: {
                    reason: readText(payload.reason),
                },
            };
            return finalize(message);
        }
        case 'BUILDER_ACK': {
            const ackType = readText(payload.ackType) as BuilderBridgeMessageType | null;
            const success = readBoolean(payload.success);
            if (!ackType || success === null) {
                return fail('invalid-ack-payload');
            }

            const mutation = payload.mutation === 'apply-change-set'
                || payload.mutation === 'add-section'
                || payload.mutation === 'remove-section'
                || payload.mutation === 'move-section'
                ? payload.mutation
                : null;

            const message: BuilderAckMessage = {
                ...base,
                type,
                payload: {
                    ackType,
                    success,
                    changed: readBoolean(payload.changed),
                    error: typeof payload.error === 'string' ? payload.error : null,
                    mutation,
                    ...readStateMeta(payload),
                },
            };
            return finalize(message);
        }
        case 'BUILDER_SAVE_DRAFT': {
            const message: BuilderSaveDraftMessage = {
                ...base,
                type,
                payload: {
                    reason: readText(payload.reason),
                },
            };
            return finalize(message);
        }
        case 'BUILDER_REFRESH_PREVIEW': {
            const message: BuilderRefreshPreviewMessage = {
                ...base,
                type,
                payload: {
                    reason: readText(payload.reason),
                },
            };
            return finalize(message);
        }
        default:
            return fail('unsupported-message-type');
    }
}

export function parseBuilderBridgeEnvelope(value: unknown): BuilderBridgeMessage | null {
    return inspectBuilderBridgeEnvelope(value).message;
}

export function builderBridgeMessageEchoesActor(
    message: BuilderBridgeMessage | null | undefined,
    actor: BuilderBridgeRuntimeSource,
): boolean {
    return Boolean(message && message.source === actor);
}

export function buildBuilderBridgeVisualStateSignature({
    pageId = null,
    pageSlug = null,
    pageTitle = null,
    viewport = null,
    interactionState = null,
    structureOpen = null,
    sidebarMode = null,
}: BuilderBridgeVisualStateSignatureInput): string {
    return JSON.stringify({
        type: 'BUILDER_SYNC_STATE',
        pageId,
        pageSlug,
        pageTitle,
        payload: {
            viewport,
            interactionState,
            structureOpen: typeof structureOpen === 'boolean' ? structureOpen : null,
            sidebarMode,
        },
    });
}

export function buildBuilderBridgeSelectionSignature({
    pageId = null,
    pageSlug = null,
    pageTitle = null,
    target,
}: BuilderBridgeSelectionSignatureInput): string {
    return JSON.stringify({
        type: target ? 'BUILDER_SELECT_TARGET' : 'BUILDER_CLEAR_SELECTION',
        pageId,
        pageSlug,
        pageTitle,
        payload: target
            ? {
                pageId: target.pageId ?? null,
                pageSlug: target.pageSlug ?? null,
                pageTitle: target.pageTitle ?? null,
                sectionLocalId: target.sectionLocalId ?? null,
                sectionKey: target.sectionKey ?? null,
                componentType: target.componentType ?? null,
                componentName: target.componentName ?? null,
                parameterPath: target.parameterPath ?? null,
                componentPath: target.componentPath ?? null,
                elementId: target.elementId ?? null,
                selector: target.selector ?? null,
                textPreview: target.textPreview ?? null,
                fieldLabel: target.fieldLabel ?? null,
                fieldGroup: target.fieldGroup ?? null,
                builderId: target.builderId ?? null,
                parentId: target.parentId ?? null,
                editableFields: Array.isArray(target.editableFields)
                    ? [...target.editableFields].filter((entry) => typeof entry === 'string').sort()
                    : [],
                sectionId: target.sectionId ?? null,
                instanceId: target.instanceId ?? null,
                currentBreakpoint: target.currentBreakpoint ?? null,
                currentInteractionState: target.currentInteractionState ?? null,
            }
            : null,
    });
}

export function builderBridgeEnvelopeTargetsProject(
    message: BuilderBridgeMessage,
    projectId: string | number,
): boolean {
    return message.projectId === normalizeProjectId(projectId);
}
