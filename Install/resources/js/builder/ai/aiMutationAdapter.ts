import type { BuilderAiSuggestion } from './aiTypes';
import type { BuilderMutation } from '@/builder/mutations/dispatchBuilderMutation';

type SafeAiMutation = Extract<BuilderMutation, {
    type: 'PATCH_NODE_PROPS' | 'INSERT_NODE' | 'DELETE_NODE' | 'MOVE_NODE';
}>;

const ALLOWED_MUTATIONS = new Set<SafeAiMutation['type']>([
    'PATCH_NODE_PROPS',
    'INSERT_NODE',
    'DELETE_NODE',
    'MOVE_NODE',
]);

function isRecord(value: unknown): value is Record<string, unknown> {
    return !!value && typeof value === 'object';
}

function isSafeAiMutation(mutation: unknown): mutation is SafeAiMutation {
    if (! isRecord(mutation) || typeof mutation.type !== 'string' || ! ALLOWED_MUTATIONS.has(mutation.type as SafeAiMutation['type'])) {
        return false;
    }

    const payload = isRecord(mutation.payload) ? mutation.payload : null;
    if (! payload) {
        return false;
    }

    switch (mutation.type) {
        case 'PATCH_NODE_PROPS':
            return typeof payload.nodeId === 'string' && isRecord(payload.patch);
        case 'INSERT_NODE':
            return typeof payload.parentId === 'string' && isRecord(payload.node);
        case 'DELETE_NODE':
            return typeof payload.nodeId === 'string';
        case 'MOVE_NODE':
            return typeof payload.nodeId === 'string' && typeof payload.targetParentId === 'string';
        default:
            return false;
    }
}

export function adaptAiSuggestions(input: unknown): BuilderAiSuggestion[] {
    if (! Array.isArray(input)) {
        return [];
    }

    return input
        .filter(isRecord)
        .map((item, index) => {
            const mutations = Array.isArray(item.mutations)
                ? item.mutations.filter(isSafeAiMutation)
                : [];

            return {
                id: typeof item.id === 'string' ? item.id : `suggestion-${index + 1}`,
                title: typeof item.title === 'string' ? item.title : `Suggestion ${index + 1}`,
                summary: typeof item.summary === 'string' ? item.summary : 'Structured mutation suggestion',
                mutations,
            };
        })
        .filter((suggestion) => suggestion.mutations.length > 0);
}
