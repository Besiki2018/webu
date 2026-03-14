import type { PendingBuilderStructureMutation } from '@/builder/cms/chatBuilderStructureMutations';
import type { WorkspaceBuilderStructureItem as BuilderStructureItem } from '@/builder/cms/workspaceBuilderSync';

export type { PendingBuilderStructureMutation } from '@/builder/cms/chatBuilderStructureMutations';

export interface BuilderBridgeEventSignatureInput {
    pageId?: number | null;
    pageSlug?: string | null;
    stateVersion?: number | null;
    revisionVersion?: number | null;
    structureHash?: string | null;
}

export interface BuilderBridgeStateCursor {
    pageId: number | null;
    pageSlug: string | null;
    stateVersion: number | null;
    revisionVersion: number | null;
}

export interface BuilderMutationAckInput {
    requestId: string;
    success: boolean;
    changed: boolean;
    error?: string | null;
}

export interface BuilderMutationAckResolution {
    status: 'ignore' | 'keep-pending' | 'clear-success' | 'clear-error';
    errorMessage: string | null;
}

function arePlainValuesEqual(left: unknown, right: unknown): boolean {
    if (Object.is(left, right)) {
        return true;
    }

    if (Array.isArray(left) || Array.isArray(right)) {
        if (!Array.isArray(left) || !Array.isArray(right) || left.length !== right.length) {
            return false;
        }

        return left.every((entry, index) => arePlainValuesEqual(entry, right[index]));
    }

    if (left && right && typeof left === 'object' && typeof right === 'object') {
        const leftEntries = Object.entries(left as Record<string, unknown>);
        const rightEntries = Object.entries(right as Record<string, unknown>);
        if (leftEntries.length !== rightEntries.length) {
            return false;
        }

        return leftEntries.every(([key, value]) => (
            Object.prototype.hasOwnProperty.call(right, key)
            && arePlainValuesEqual(value, (right as Record<string, unknown>)[key])
        ));
    }

    return false;
}

export function areBuilderStructureCollectionsEqual(
    left: BuilderStructureItem[],
    right: BuilderStructureItem[],
): boolean {
    if (left === right) {
        return true;
    }

    if (left.length !== right.length) {
        return false;
    }

    return left.every((item, index) => {
        const candidate = right[index];
        if (!candidate) {
            return false;
        }

        return item.localId === candidate.localId
            && item.sectionKey === candidate.sectionKey
            && item.label === candidate.label
            && item.previewText === candidate.previewText
            && arePlainValuesEqual(item.props, candidate.props);
    });
}

export function shouldIgnoreRevertingPendingStructureSnapshot(
    pending: PendingBuilderStructureMutation | null,
    nextItems: BuilderStructureItem[],
): boolean {
    if (!pending || pending.previewItems === null) {
        return false;
    }

    if (areBuilderStructureCollectionsEqual(nextItems, pending.previewItems)) {
        return false;
    }

    return areBuilderStructureCollectionsEqual(nextItems, pending.baseItems);
}

export function buildBuilderBridgeEventSignature(input: BuilderBridgeEventSignatureInput): string {
    return [
        input.pageId ?? 'null',
        input.pageSlug ?? 'null',
        input.stateVersion ?? 'null',
        input.revisionVersion ?? 'null',
        input.structureHash ?? 'no-hash',
    ].join(':');
}

export function isStaleBuilderBridgeState(
    current: BuilderBridgeStateCursor | null,
    incoming: BuilderBridgeEventSignatureInput,
): boolean {
    if (!current) {
        return false;
    }

    const currentPageId = current.pageId;
    const currentPageSlug = current.pageSlug?.trim().toLowerCase() ?? null;
    const incomingPageId = typeof incoming.pageId === 'number' ? incoming.pageId : null;
    const incomingPageSlug = typeof incoming.pageSlug === 'string' && incoming.pageSlug.trim() !== ''
        ? incoming.pageSlug.trim().toLowerCase()
        : null;

    if (currentPageId !== null && incomingPageId !== null && currentPageId !== incomingPageId) {
        return false;
    }

    if (currentPageId === null && incomingPageId === null && currentPageSlug !== null && incomingPageSlug !== null && currentPageSlug !== incomingPageSlug) {
        return false;
    }

    if (typeof incoming.stateVersion === 'number' && typeof current.stateVersion === 'number') {
        return incoming.stateVersion < current.stateVersion;
    }

    if (typeof incoming.revisionVersion === 'number' && typeof current.revisionVersion === 'number') {
        return incoming.revisionVersion < current.revisionVersion;
    }

    return false;
}

export function resolvePendingBuilderStructureMutation(
    pending: PendingBuilderStructureMutation | null,
    ack: BuilderMutationAckInput,
): BuilderMutationAckResolution {
    if (!pending || pending.requestId !== ack.requestId) {
        return {
            status: 'ignore',
            errorMessage: null,
        };
    }

    if (!ack.success) {
        return {
            status: 'clear-error',
            errorMessage: ack.error?.trim() || 'Builder update failed',
        };
    }

    if (!ack.changed) {
        return {
            status: 'clear-error',
            errorMessage: 'No builder change was applied',
        };
    }

    return {
        status: 'keep-pending',
        errorMessage: null,
    };
}
