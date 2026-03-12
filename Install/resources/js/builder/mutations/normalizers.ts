import type { BuilderNode } from '@/builder/types/builderNode';
import { generateBuilderNodeId } from '@/builder/utils/document';
import type { BuilderMutation, InsertNodeMutation } from './dispatchBuilderMutation';

export function normalizeBuilderMutation(mutation: BuilderMutation): BuilderMutation {
    if (mutation.type !== 'INSERT_NODE') {
        return mutation;
    }

    const incomingNode: Partial<BuilderNode> = mutation.payload.node ?? {};
    const normalizedNode: BuilderNode = {
        id: typeof incomingNode.id === 'string' && incomingNode.id.trim() !== '' ? incomingNode.id : generateBuilderNodeId('node'),
        type: incomingNode.type ?? 'component',
        componentKey: incomingNode.componentKey,
        parentId: mutation.payload.parentId || incomingNode.parentId || null,
        children: Array.isArray(incomingNode.children) ? incomingNode.children : [],
        props: incomingNode.props ?? {},
        styles: incomingNode.styles ?? {},
        bindings: incomingNode.bindings ?? {},
        meta: incomingNode.meta ?? {},
    };

    const normalizedMutation: InsertNodeMutation = {
        ...mutation,
        payload: {
            ...mutation.payload,
            node: normalizedNode,
        },
    };

    return normalizedMutation;
}
