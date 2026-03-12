import { isRecord } from '@/builder/state/sectionProps';

export type BuilderBridgeViewport = 'desktop' | 'tablet' | 'mobile';
export type BuilderBridgeInteractionState = 'normal' | 'hover' | 'focus' | 'active';
export type BuilderBridgeSidebarMode = 'elements' | 'settings';
export type BuilderBridgeSource = 'webu-chat-builder' | 'webu-cms-builder';
export type BuilderBridgeMutationType = 'apply-change-set' | 'add-section' | 'remove-section' | 'move-section';

export interface BuilderBridgePageIdentity {
    pageId: number | null;
    pageSlug: string | null;
    pageTitle: string | null;
}

export interface BuilderBridgeLibraryItem {
    key: string;
    label: string;
    category: string;
}

export interface BuilderBridgeStructureSection {
    localId: string;
    sectionKey: string;
    type: string;
    label: string;
    previewText: string;
    propsText: string;
    props: Record<string, unknown>;
}

export interface BuilderBridgeStateMeta {
    stateVersion?: number | null;
    structureHash?: string | null;
    revisionId?: number | null;
    revisionVersion?: number | null;
}

export interface BuilderBridgeChatBaseMessage extends BuilderBridgePageIdentity {
    source: 'webu-chat-builder';
}

export interface BuilderBridgeCmsBaseMessage extends BuilderBridgePageIdentity, BuilderBridgeStateMeta {
    source: 'webu-cms-builder';
}

export type BuilderBridgeChatCommand =
    | (BuilderBridgeChatBaseMessage & { type: 'builder:ping' })
    | (BuilderBridgeChatBaseMessage & { type: 'builder:clear-selected-section' })
    | (BuilderBridgeChatBaseMessage & { type: 'builder:refresh-preview' })
    | (BuilderBridgeChatBaseMessage & { type: 'builder:save-draft' })
    | (BuilderBridgeChatBaseMessage & { type: 'builder:set-viewport'; viewport: BuilderBridgeViewport })
    | (BuilderBridgeChatBaseMessage & { type: 'builder:set-interaction-state'; interactionState: BuilderBridgeInteractionState })
    | (BuilderBridgeChatBaseMessage & { type: 'builder:set-sidebar-mode'; mode: BuilderBridgeSidebarMode })
    | (BuilderBridgeChatBaseMessage & { type: 'builder:set-structure-open'; open: boolean })
    | (BuilderBridgeChatBaseMessage & { type: 'builder:set-selected-section'; sectionLocalId: string | null; parameterPath?: string | null })
    | (BuilderBridgeChatBaseMessage & { type: 'builder:set-selected-section-key'; sectionKey: string; parameterPath?: string | null })
    | (BuilderBridgeChatBaseMessage & {
        type: 'builder:set-selected-target';
        sectionLocalId?: string | null;
        sectionKey?: string | null;
        componentType?: string | null;
        componentName?: string | null;
        parameterPath?: string | null;
        componentPath?: string | null;
        elementId?: string | null;
        selector?: string | null;
        textPreview?: string | null;
        props?: Record<string, unknown> | null;
        fieldLabel?: string | null;
        fieldGroup?: string | null;
        builderId?: string | null;
        parentId?: string | null;
        editableFields?: string[];
        sectionId?: string | null;
        instanceId?: string | null;
        variants?: Record<string, unknown> | null;
        allowedUpdates?: Record<string, unknown> | null;
        responsiveContext?: Record<string, unknown> | null;
    })
    | (BuilderBridgeChatBaseMessage & { type: 'builder:set-initial-sections'; sections: Array<Record<string, unknown>> })
    | (BuilderBridgeChatBaseMessage & { type: 'builder:apply-change-set'; requestId: string; changeSet: Record<string, unknown> })
    | (BuilderBridgeChatBaseMessage & {
        type: 'builder:add-section-by-key';
        requestId: string;
        sectionKey: string;
        afterSectionLocalId?: string | null;
        targetSectionKey?: string | null;
        placement?: 'before' | 'after' | 'inside' | null;
    })
    | (BuilderBridgeChatBaseMessage & {
        type: 'builder:remove-section';
        requestId: string;
        sectionLocalId?: string | null;
        sectionIndex?: number | null;
        sectionKey?: string | null;
    })
    | (BuilderBridgeChatBaseMessage & {
        type: 'builder:move-section';
        requestId: string;
        sectionLocalId: string;
        targetSectionLocalId: string;
        position: 'before' | 'after';
    });

export type BuilderBridgeCmsEvent =
    | (BuilderBridgeCmsBaseMessage & {
        type: 'builder:state';
        viewport: BuilderBridgeViewport;
        structureOpen: boolean;
        interactionState: BuilderBridgeInteractionState;
    })
    | (BuilderBridgeCmsBaseMessage & { type: 'builder:ready' })
    | (BuilderBridgeCmsBaseMessage & {
        type: 'builder:selected-section';
        sectionLocalId: string | null;
        sectionKey?: string | null;
    })
    | (BuilderBridgeCmsBaseMessage & {
        type: 'builder:selected-target';
        sectionLocalId?: string | null;
        sectionKey?: string | null;
        componentType?: string | null;
        componentName?: string | null;
        parameterPath?: string | null;
        componentPath?: string | null;
        elementId?: string | null;
        selector?: string | null;
        textPreview?: string | null;
        props?: Record<string, unknown> | null;
        fieldLabel?: string | null;
        fieldGroup?: string | null;
        builderId?: string | null;
        parentId?: string | null;
        editableFields?: string[];
        sectionId?: string | null;
        instanceId?: string | null;
        variants?: Record<string, unknown> | null;
        allowedUpdates?: Record<string, unknown> | null;
        responsiveContext?: Record<string, unknown> | null;
        viewport?: BuilderBridgeViewport | null;
        interactionState?: BuilderBridgeInteractionState | null;
    })
    | (BuilderBridgeCmsBaseMessage & {
        type: 'builder:draft-save-state';
        isSaving: boolean;
        success?: boolean | null;
        message?: string | null;
        revisionId?: number | null;
    })
    | (BuilderBridgeCmsBaseMessage & {
        type: 'builder:mutation-result';
        requestId: string;
        mutation: BuilderBridgeMutationType;
        success: boolean;
        changed: boolean;
        error?: string | null;
    })
    | (BuilderBridgeCmsBaseMessage & { type: 'builder:preview-refresh' })
    | (BuilderBridgeCmsBaseMessage & { type: 'builder:library-snapshot'; items: BuilderBridgeLibraryItem[] })
    | (BuilderBridgeCmsBaseMessage & { type: 'builder:structure-snapshot'; sections: BuilderBridgeStructureSection[] });

type BuilderBridgePageIdentityInput = {
    pageId?: unknown;
    pageSlug?: unknown;
    pageTitle?: unknown;
} | null | undefined;

function normalizeBuilderBridgePageId(value: unknown): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string' && /^\d+$/.test(value.trim())) {
        const parsed = Number.parseInt(value.trim(), 10);
        return Number.isFinite(parsed) ? parsed : null;
    }

    return null;
}

function normalizeBuilderBridgeText(value: unknown): string | null {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

export function normalizeBuilderBridgePageIdentity(input: BuilderBridgePageIdentityInput): BuilderBridgePageIdentity {
    const record = isRecord(input) ? input : {};

    return {
        pageId: normalizeBuilderBridgePageId(record.pageId),
        pageSlug: normalizeBuilderBridgeText(record.pageSlug)?.toLowerCase() ?? null,
        pageTitle: normalizeBuilderBridgeText(record.pageTitle),
    };
}

export function hasBuilderBridgePageIdentity(identity: BuilderBridgePageIdentity | null | undefined): boolean {
    return identity?.pageId !== null || identity?.pageSlug !== null;
}

export function builderBridgePagesMatch(
    left: BuilderBridgePageIdentity | null | undefined,
    right: BuilderBridgePageIdentity | null | undefined,
): boolean {
    if (!left || !right) {
        return false;
    }

    if (left.pageId !== null && right.pageId !== null) {
        return left.pageId === right.pageId;
    }

    if (left.pageSlug !== null && right.pageSlug !== null) {
        return left.pageSlug === right.pageSlug;
    }

    return false;
}

export function payloadTargetsBuilderBridgePage(
    payload: BuilderBridgePageIdentityInput,
    currentPage: BuilderBridgePageIdentity | null | undefined,
): boolean {
    const payloadPage = normalizeBuilderBridgePageIdentity(payload);

    if (!hasBuilderBridgePageIdentity(payloadPage)) {
        return true;
    }

    return builderBridgePagesMatch(payloadPage, currentPage);
}

export function attachBuilderBridgePageIdentity<T extends object>(
    payload: T,
    page: BuilderBridgePageIdentity | null | undefined,
): T & BuilderBridgePageIdentity {
    const normalizedPage = normalizeBuilderBridgePageIdentity(page);

    return {
        ...payload,
        ...normalizedPage,
    };
}

function readNumber(value: unknown): number | null {
    return typeof value === 'number' && Number.isFinite(value) ? value : null;
}

function readBoolean(value: unknown): boolean | null {
    return typeof value === 'boolean' ? value : null;
}

function readText(value: unknown): string | null {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

function readRecord(value: unknown): Record<string, unknown> | null {
    return isRecord(value) ? value : null;
}

function readStringArray(value: unknown): string[] | null {
    if (!Array.isArray(value)) {
        return null;
    }

    const normalized = value
        .map((entry) => (typeof entry === 'string' ? entry.trim() : ''))
        .filter(Boolean);

    return normalized.length > 0 ? normalized : [];
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

function readStructureSection(value: unknown): BuilderBridgeStructureSection | null {
    const record = readRecord(value);
    if (!record) {
        return null;
    }

    const localId = readText(record.localId);
    const sectionKey = readText(record.sectionKey);
    const type = readText(record.type);
    const label = readText(record.label);
    const previewText = typeof record.previewText === 'string' ? record.previewText : '';
    const propsText = typeof record.propsText === 'string' ? record.propsText : '';
    const props = readRecord(record.props) ?? {};

    if (!localId || !sectionKey || !type || !label) {
        return null;
    }

    return {
        localId,
        sectionKey,
        type,
        label,
        previewText,
        propsText,
        props,
    };
}

function readLibraryItem(value: unknown): BuilderBridgeLibraryItem | null {
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

function attachStateMeta<T extends object>(payload: T, record: Record<string, unknown>): T & BuilderBridgeStateMeta {
    return {
        ...payload,
        ...(readNumber(record.stateVersion) !== null ? { stateVersion: readNumber(record.stateVersion) } : {}),
        ...(typeof record.structureHash === 'string' ? { structureHash: record.structureHash } : {}),
        ...(readNumber(record.revisionId) !== null ? { revisionId: readNumber(record.revisionId) } : {}),
        ...(readNumber(record.revisionVersion) !== null ? { revisionVersion: readNumber(record.revisionVersion) } : {}),
    };
}

export function createBuilderBridgeRequestId(prefix = 'builder'): string {
    return `${prefix}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
}

export function postBuilderBridgeMessage<T extends object>(
    targetWindow: Window,
    targetOrigin: string,
    source: BuilderBridgeSource,
    payload: T,
    page: BuilderBridgePageIdentity | null | undefined,
): void {
    targetWindow.postMessage(attachBuilderBridgePageIdentity({
        source,
        ...payload,
    }, page), targetOrigin);
}

export function buildBuilderBridgeStructureHash(value: unknown): string {
    const serialize = (entry: unknown): string => {
        if (entry === null || typeof entry !== 'object') {
            return JSON.stringify(entry);
        }

        if (Array.isArray(entry)) {
            return `[${entry.map((item) => serialize(item)).join(',')}]`;
        }

        const record = entry as Record<string, unknown>;
        return `{${Object.keys(record).sort().map((key) => `${JSON.stringify(key)}:${serialize(record[key])}`).join(',')}}`;
    };

    return `${serialize(value).length}:${serialize(value).slice(0, 256)}`;
}

export function parseBuilderBridgeChatCommand(value: unknown): BuilderBridgeChatCommand | null {
    const record = readRecord(value);
    if (!record || record.source !== 'webu-chat-builder') {
        return null;
    }

    const page = normalizeBuilderBridgePageIdentity(record);
    const type = readText(record.type);
    if (!type) {
        return null;
    }

    switch (type) {
        case 'builder:ping':
        case 'builder:clear-selected-section':
        case 'builder:refresh-preview':
        case 'builder:save-draft':
            return attachBuilderBridgePageIdentity({ source: 'webu-chat-builder' as const, type }, page);
        case 'builder:set-viewport': {
            const viewport = readViewport(record.viewport);
            return viewport ? attachBuilderBridgePageIdentity({ source: 'webu-chat-builder' as const, type, viewport }, page) : null;
        }
        case 'builder:set-interaction-state': {
            const interactionState = readInteractionState(record.interactionState);
            return interactionState ? attachBuilderBridgePageIdentity({ source: 'webu-chat-builder' as const, type, interactionState }, page) : null;
        }
        case 'builder:set-sidebar-mode': {
            const mode = readSidebarMode(record.mode);
            return mode ? attachBuilderBridgePageIdentity({ source: 'webu-chat-builder' as const, type, mode }, page) : null;
        }
        case 'builder:set-structure-open': {
            const open = readBoolean(record.open);
            return open === null ? null : attachBuilderBridgePageIdentity({ source: 'webu-chat-builder' as const, type, open }, page);
        }
        case 'builder:set-selected-section': {
            return attachBuilderBridgePageIdentity({
                source: 'webu-chat-builder' as const,
                type,
                sectionLocalId: readText(record.sectionLocalId),
                parameterPath: readText(record.parameterPath),
            }, page);
        }
        case 'builder:set-selected-section-key': {
            const sectionKey = readText(record.sectionKey);
            return sectionKey ? attachBuilderBridgePageIdentity({
                source: 'webu-chat-builder' as const,
                type,
                sectionKey,
                parameterPath: readText(record.parameterPath),
            }, page) : null;
        }
        case 'builder:set-selected-target':
            return attachBuilderBridgePageIdentity({
                source: 'webu-chat-builder' as const,
                type,
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
                fieldGroup: readText(record.fieldGroup),
                builderId: readText(record.builderId),
                parentId: readText(record.parentId),
                editableFields: readStringArray(record.editableFields) ?? [],
                sectionId: readText(record.sectionId),
                instanceId: readText(record.instanceId),
                variants: readRecord(record.variants),
                allowedUpdates: readRecord(record.allowedUpdates),
                responsiveContext: readRecord(record.responsiveContext),
            }, page);
        case 'builder:set-initial-sections':
            return Array.isArray(record.sections)
                ? attachBuilderBridgePageIdentity({
                    source: 'webu-chat-builder' as const,
                    type,
                    sections: record.sections.filter((entry): entry is Record<string, unknown> => isRecord(entry)),
                }, page)
                : null;
        case 'builder:apply-change-set': {
            const requestId = readText(record.requestId);
            const changeSet = readRecord(record.changeSet);
            return requestId && changeSet
                ? attachBuilderBridgePageIdentity({ source: 'webu-chat-builder' as const, type, requestId, changeSet }, page)
                : null;
        }
        case 'builder:add-section-by-key': {
            const requestId = readText(record.requestId);
            const sectionKey = readText(record.sectionKey);
            const placement: 'before' | 'after' | 'inside' | null = record.placement === 'before' || record.placement === 'after' || record.placement === 'inside'
                ? record.placement
                : null;
            return requestId && sectionKey
                ? attachBuilderBridgePageIdentity({
                    source: 'webu-chat-builder' as const,
                    type,
                    requestId,
                    sectionKey,
                    afterSectionLocalId: readText(record.afterSectionLocalId),
                    targetSectionKey: readText(record.targetSectionKey),
                    placement,
                }, page)
                : null;
        }
        case 'builder:remove-section': {
            const requestId = readText(record.requestId);
            if (!requestId) {
                return null;
            }
            return attachBuilderBridgePageIdentity({
                source: 'webu-chat-builder' as const,
                type,
                requestId,
                sectionLocalId: readText(record.sectionLocalId),
                sectionIndex: readNumber(record.sectionIndex),
                sectionKey: readText(record.sectionKey),
            }, page);
        }
        case 'builder:move-section': {
            const requestId = readText(record.requestId);
            const sectionLocalId = readText(record.sectionLocalId);
            const targetSectionLocalId = readText(record.targetSectionLocalId);
            const position: 'before' | 'after' | null = record.position === 'before' || record.position === 'after' ? record.position : null;
            return requestId && sectionLocalId && targetSectionLocalId && position
                ? attachBuilderBridgePageIdentity({
                    source: 'webu-chat-builder' as const,
                    type,
                    requestId,
                    sectionLocalId,
                    targetSectionLocalId,
                    position,
                }, page)
                : null;
        }
        default:
            return null;
    }
}

export function parseBuilderBridgeCmsEvent(value: unknown): BuilderBridgeCmsEvent | null {
    const record = readRecord(value);
    if (!record || record.source !== 'webu-cms-builder') {
        return null;
    }

    const page = normalizeBuilderBridgePageIdentity(record);
    const type = readText(record.type);
    if (!type) {
        return null;
    }

    switch (type) {
        case 'builder:ready':
            return attachStateMeta(attachBuilderBridgePageIdentity({ source: 'webu-cms-builder' as const, type }, page), record);
        case 'builder:state': {
            const viewport = readViewport(record.viewport);
            const interactionState = readInteractionState(record.interactionState);
            const structureOpen = readBoolean(record.structureOpen);
            return viewport && interactionState && structureOpen !== null
                ? attachStateMeta(attachBuilderBridgePageIdentity({
                    source: 'webu-cms-builder' as const,
                    type,
                    viewport,
                    interactionState,
                    structureOpen,
                }, page), record)
                : null;
        }
        case 'builder:selected-section':
            return attachStateMeta(attachBuilderBridgePageIdentity({
                source: 'webu-cms-builder' as const,
                type,
                sectionLocalId: readText(record.sectionLocalId),
                sectionKey: readText(record.sectionKey),
            }, page), record);
        case 'builder:selected-target':
            return attachStateMeta(attachBuilderBridgePageIdentity({
                source: 'webu-cms-builder' as const,
                type,
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
                fieldGroup: readText(record.fieldGroup),
                builderId: readText(record.builderId),
                parentId: readText(record.parentId),
                editableFields: readStringArray(record.editableFields) ?? [],
                sectionId: readText(record.sectionId),
                instanceId: readText(record.instanceId),
                variants: readRecord(record.variants),
                allowedUpdates: readRecord(record.allowedUpdates),
                responsiveContext: readRecord(record.responsiveContext),
                viewport: readViewport(record.viewport),
                interactionState: readInteractionState(record.interactionState),
            }, page), record);
        case 'builder:draft-save-state': {
            const isSaving = readBoolean(record.isSaving);
            return isSaving === null
                ? null
                : attachStateMeta(attachBuilderBridgePageIdentity({
                    source: 'webu-cms-builder' as const,
                    type,
                    isSaving,
                    success: readBoolean(record.success),
                    message: typeof record.message === 'string' ? record.message : null,
                    revisionId: readNumber(record.revisionId),
                }, page), record);
        }
        case 'builder:preview-refresh':
            return attachStateMeta(attachBuilderBridgePageIdentity({ source: 'webu-cms-builder' as const, type }, page), record);
        case 'builder:mutation-result': {
            const requestId = readText(record.requestId);
            const mutation: BuilderBridgeMutationType | null = record.mutation === 'apply-change-set'
                || record.mutation === 'add-section'
                || record.mutation === 'remove-section'
                || record.mutation === 'move-section'
                ? record.mutation
                : null;
            const success = readBoolean(record.success);
            const changed = readBoolean(record.changed);
            return requestId && mutation && success !== null && changed !== null
                ? attachStateMeta(attachBuilderBridgePageIdentity({
                    source: 'webu-cms-builder' as const,
                    type,
                    requestId,
                    mutation,
                    success,
                    changed,
                    error: typeof record.error === 'string' ? record.error : null,
                }, page), record)
                : null;
        }
        case 'builder:library-snapshot':
            return Array.isArray(record.items)
                ? attachStateMeta(attachBuilderBridgePageIdentity({
                    source: 'webu-cms-builder' as const,
                    type,
                    items: record.items.map((entry) => readLibraryItem(entry)).filter((entry): entry is BuilderBridgeLibraryItem => entry !== null),
                }, page), record)
                : null;
        case 'builder:structure-snapshot':
            return Array.isArray(record.sections)
                ? attachStateMeta(attachBuilderBridgePageIdentity({
                    source: 'webu-cms-builder' as const,
                    type,
                    sections: record.sections.map((entry) => readStructureSection(entry)).filter((entry): entry is BuilderBridgeStructureSection => entry !== null),
                }, page), record)
                : null;
        default:
            return null;
    }
}
