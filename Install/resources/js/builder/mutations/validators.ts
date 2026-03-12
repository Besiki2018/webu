import type { BuilderDocument } from '@/builder/types/builderDocument';
import { collectSubtreeIds } from '@/builder/utils/document';
import type { BuilderMutation } from './dispatchBuilderMutation';

function isPageRootNode(document: BuilderDocument, nodeId: string): boolean {
    return Object.values(document.pages).some((page) => page.rootNodeId === nodeId);
}

export function validateBuilderMutation(document: BuilderDocument, mutation: BuilderMutation): string[] {
    const { type, payload } = mutation;

    switch (type) {
        case 'PATCH_NODE_PROPS':
        case 'PATCH_NODE_STYLES':
        case 'DUPLICATE_NODE':
        case 'WRAP_NODE':
        case 'UNWRAP_NODE':
            return document.nodes[payload.nodeId]
                ? []
                : ['Missing target node'];
        case 'DELETE_NODE':
            if (! document.nodes[payload.nodeId]) {
                return ['Missing target node'];
            }
            if (isPageRootNode(document, payload.nodeId)) {
                return ['Cannot delete a page root node'];
            }

            return [];
        case 'INSERT_NODE':
            if (! document.nodes[payload.parentId]) {
                return ['Missing parent node'];
            }
            if (! payload.node.id.trim()) {
                return ['Missing inserted node id'];
            }
            if (document.nodes[payload.node.id]) {
                return ['Inserted node id already exists'];
            }

            return [];
        case 'MOVE_NODE': {
            if (! document.nodes[payload.nodeId]) {
                return ['Missing moving node'];
            }
            if (isPageRootNode(document, payload.nodeId)) {
                return ['Cannot move a page root node'];
            }
            if (! document.nodes[payload.targetParentId]) {
                return ['Missing target parent'];
            }
            if (collectSubtreeIds(document, payload.nodeId).includes(payload.targetParentId)) {
                return ['Cannot move a node into its own subtree'];
            }

            return [];
        }
        case 'SELECT_NODE':
        case 'HOVER_NODE':
            return payload.nodeId === null || document.nodes[payload.nodeId]
                ? []
                : ['Missing target node'];
        default:
            return [];
    }
}
