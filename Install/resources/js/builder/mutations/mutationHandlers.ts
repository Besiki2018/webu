import type { BuilderDocument } from '@/builder/types/builderDocument';
import type { BuilderNode } from '@/builder/types/builderNode';
import {
    collectSubtreeIds,
    findFallbackSelection,
    generateBuilderNodeId,
    insertAt,
    removeFromArray,
} from '@/builder/utils/document';
import type { BuilderMutation, DuplicateNodeMutation } from './dispatchBuilderMutation';

export interface MutationApplyResult {
    document: BuilderDocument;
    selectedNodeId: string | null;
    hoveredNodeId: string | null;
}

interface MutationHandlerContext {
    document: BuilderDocument;
    activePageId: string;
    selectedNodeId: string | null;
    hoveredNodeId: string | null;
}

function patchNode(
    document: BuilderDocument,
    nodeId: string,
    key: 'props' | 'styles',
    patch: Record<string, unknown>,
): BuilderDocument {
    const node = document.nodes[nodeId];
    if (! node) {
        return document;
    }

    const currentValue = key === 'props' ? node.props : (node.styles ?? {});
    const hasChanges = Object.entries(patch).some(([entryKey, entryValue]) => currentValue[entryKey] !== entryValue);
    if (! hasChanges) {
        return document;
    }

    const nextValue = { ...currentValue, ...patch };
    const nextNode: BuilderNode = key === 'props'
        ? { ...node, props: nextValue }
        : { ...node, styles: nextValue };

    return {
        ...document,
        nodes: {
            ...document.nodes,
            [nodeId]: nextNode,
        },
    };
}

function duplicateSubtree(document: BuilderDocument, mutation: DuplicateNodeMutation): { document: BuilderDocument; duplicatedRootId: string | null } {
    const sourceNode = document.nodes[mutation.payload.nodeId];
    if (! sourceNode) {
        return {
            document,
            duplicatedRootId: null,
        };
    }

    const nextNodes: Record<string, BuilderNode> = {
        ...document.nodes,
    };
    const subtreeIds = collectSubtreeIds(document, mutation.payload.nodeId);
    const idMap = new Map<string, string>(subtreeIds.map((sourceId) => [sourceId, generateBuilderNodeId('node')]));

    for (const sourceId of subtreeIds) {
        const source = document.nodes[sourceId];
        if (! source) {
            continue;
        }

        const cloneId = idMap.get(sourceId)!;
        const cloneNode: BuilderNode = {
            ...source,
            id: cloneId,
            parentId:
                sourceId === mutation.payload.nodeId
                    ? (mutation.payload.targetParentId ?? source.parentId)
                    : (source.parentId ? idMap.get(source.parentId) ?? source.parentId : null),
            children: source.children.map((childId) => idMap.get(childId) ?? childId),
            props: { ...source.props },
            styles: { ...(source.styles ?? {}) },
            bindings: { ...(source.bindings ?? {}) },
            meta: source.meta ? { ...source.meta } : undefined,
        };
        nextNodes[cloneId] = cloneNode;
    }

    const newRootId = idMap.get(mutation.payload.nodeId)!;
    const parentId = mutation.payload.targetParentId ?? sourceNode.parentId;
    if (! parentId || ! document.nodes[parentId]) {
        return {
            document,
            duplicatedRootId: null,
        };
    }

    const parentNode = document.nodes[parentId];
    const sourceIndex = parentNode.children.indexOf(mutation.payload.nodeId);
    const resolvedIndex = typeof mutation.payload.index === 'number'
        ? mutation.payload.index
        : (sourceIndex >= 0 ? sourceIndex + 1 : parentNode.children.length);

    nextNodes[parentId] = {
        ...parentNode,
        children: insertAt(parentNode.children, resolvedIndex, newRootId),
    };

    return {
        document: {
            ...document,
            nodes: nextNodes,
        },
        duplicatedRootId: newRootId,
    };
}

function applyInsertNode(document: BuilderDocument, parentId: string, node: BuilderNode, index?: number): BuilderDocument {
    const parentNode = document.nodes[parentId];
    if (! parentNode) {
        return document;
    }

    const nextNode: BuilderNode = {
        ...node,
        parentId,
        props: { ...node.props },
        styles: node.styles ? { ...node.styles } : undefined,
        bindings: node.bindings ? { ...node.bindings } : undefined,
        meta: node.meta ? { ...node.meta } : undefined,
    };

    return {
        ...document,
        nodes: {
            ...document.nodes,
            [parentId]: {
                ...parentNode,
                children: insertAt(parentNode.children, typeof index === 'number' ? index : parentNode.children.length, node.id),
            },
            [node.id]: nextNode,
        },
    };
}

function applyDeleteNode(
    document: BuilderDocument,
    activePageId: string,
    selectedNodeId: string | null,
    hoveredNodeId: string | null,
    nodeId: string,
): MutationApplyResult {
    const node = document.nodes[nodeId];
    if (! node) {
        return {
            document,
            selectedNodeId,
            hoveredNodeId,
        };
    }

    const subtreeIds = collectSubtreeIds(document, nodeId);
    const nextNodes: Record<string, BuilderNode> = {
        ...document.nodes,
    };

    if (node.parentId && document.nodes[node.parentId]) {
        const parentNode = document.nodes[node.parentId];
        nextNodes[node.parentId] = {
            ...parentNode,
            children: removeFromArray(parentNode.children, nodeId),
        };
    }

    for (const subtreeId of subtreeIds) {
        delete nextNodes[subtreeId];
    }

    const nextDocument: BuilderDocument = {
        ...document,
        nodes: nextNodes,
    };
    const shouldResetSelection = selectedNodeId ? subtreeIds.includes(selectedNodeId) : false;
    const shouldResetHover = hoveredNodeId ? subtreeIds.includes(hoveredNodeId) : false;

    return {
        document: nextDocument,
        selectedNodeId: shouldResetSelection ? findFallbackSelection(nextDocument, activePageId) : selectedNodeId,
        hoveredNodeId: shouldResetHover ? null : hoveredNodeId,
    };
}

function applyMoveNode(document: BuilderDocument, nodeId: string, targetParentId: string, index?: number): BuilderDocument {
    const node = document.nodes[nodeId];
    const targetParent = document.nodes[targetParentId];
    if (! node || ! targetParent) {
        return document;
    }

    const nextNodes: Record<string, BuilderNode> = {
        ...document.nodes,
    };
    const sourceParentId = node.parentId;
    const targetChildrenBase = sourceParentId === targetParentId
        ? removeFromArray(targetParent.children, nodeId)
        : targetParent.children;
    const targetIndex = typeof index === 'number' ? index : targetChildrenBase.length;
    const nextTargetChildren = insertAt(targetChildrenBase, targetIndex, nodeId);

    if (sourceParentId && sourceParentId !== targetParentId && document.nodes[sourceParentId]) {
        const sourceParent = document.nodes[sourceParentId];
        nextNodes[sourceParentId] = {
            ...sourceParent,
            children: removeFromArray(sourceParent.children, nodeId),
        };
    }

    nextNodes[targetParentId] = {
        ...targetParent,
        children: nextTargetChildren,
    };
    nextNodes[nodeId] = {
        ...node,
        parentId: targetParentId,
    };

    return {
        ...document,
        nodes: nextNodes,
    };
}

function applyWrapNode(document: BuilderDocument, nodeId: string, wrapperNodeId?: string): { document: BuilderDocument; selectedNodeId: string | null } {
    const node = document.nodes[nodeId];
    if (! node || ! node.parentId) {
        return {
            document,
            selectedNodeId: null,
        };
    }

    const parentNode = document.nodes[node.parentId];
    if (! parentNode) {
        return {
            document,
            selectedNodeId: null,
        };
    }

    const wrapperId = wrapperNodeId ?? generateBuilderNodeId('section');
    const wrapperNode: BuilderNode = {
        id: wrapperId,
        type: 'section',
        componentKey: 'section',
        parentId: parentNode.id,
        children: [node.id],
        props: { title: 'Group' },
        styles: {},
        bindings: {},
        meta: { label: 'Group' },
    };

    return {
        document: {
            ...document,
            nodes: {
                ...document.nodes,
                [parentNode.id]: {
                    ...parentNode,
                    children: parentNode.children.map((childId) => (childId === node.id ? wrapperId : childId)),
                },
                [node.id]: {
                    ...node,
                    parentId: wrapperId,
                },
                [wrapperId]: wrapperNode,
            },
        },
        selectedNodeId: wrapperId,
    };
}

function applyUnwrapNode(document: BuilderDocument, wrapperId: string): { document: BuilderDocument; selectedNodeId: string | null } {
    const wrapper = document.nodes[wrapperId];
    if (! wrapper || ! wrapper.parentId) {
        return {
            document,
            selectedNodeId: null,
        };
    }

    const parentNode = document.nodes[wrapper.parentId];
    if (! parentNode) {
        return {
            document,
            selectedNodeId: null,
        };
    }

    const wrapperIndex = parentNode.children.indexOf(wrapperId);
    let nextParentChildren = parentNode.children.filter((childId) => childId !== wrapperId);
    const nextNodes: Record<string, BuilderNode> = {
        ...document.nodes,
    };

    wrapper.children.forEach((childId, offset) => {
        const childNode = document.nodes[childId];
        if (! childNode) {
            return;
        }

        nextNodes[childId] = {
            ...childNode,
            parentId: parentNode.id,
        };
        nextParentChildren = insertAt(nextParentChildren, wrapperIndex + offset, childId);
    });

    nextNodes[parentNode.id] = {
        ...parentNode,
        children: nextParentChildren,
    };
    delete nextNodes[wrapperId];

    return {
        document: {
            ...document,
            nodes: nextNodes,
        },
        selectedNodeId: nextParentChildren[wrapperIndex] ?? parentNode.id,
    };
}

export function applyBuilderMutation(context: MutationHandlerContext, mutation: BuilderMutation): MutationApplyResult {
    switch (mutation.type) {
        case 'PATCH_NODE_PROPS':
            return {
                document: patchNode(context.document, mutation.payload.nodeId, 'props', mutation.payload.patch),
                selectedNodeId: context.selectedNodeId,
                hoveredNodeId: context.hoveredNodeId,
            };
        case 'PATCH_NODE_STYLES':
            return {
                document: patchNode(context.document, mutation.payload.nodeId, 'styles', mutation.payload.patch),
                selectedNodeId: context.selectedNodeId,
                hoveredNodeId: context.hoveredNodeId,
            };
        case 'INSERT_NODE':
            return {
                document: applyInsertNode(context.document, mutation.payload.parentId, mutation.payload.node, mutation.payload.index),
                selectedNodeId: mutation.payload.node.id,
                hoveredNodeId: context.hoveredNodeId,
            };
        case 'DELETE_NODE':
            return applyDeleteNode(
                context.document,
                context.activePageId,
                context.selectedNodeId,
                context.hoveredNodeId,
                mutation.payload.nodeId,
            );
        case 'MOVE_NODE':
            return {
                document: applyMoveNode(context.document, mutation.payload.nodeId, mutation.payload.targetParentId, mutation.payload.index),
                selectedNodeId: context.selectedNodeId,
                hoveredNodeId: context.hoveredNodeId,
            };
        case 'DUPLICATE_NODE': {
            const duplicated = duplicateSubtree(context.document, mutation);

            return {
                document: duplicated.document,
                selectedNodeId: duplicated.duplicatedRootId ?? context.selectedNodeId,
                hoveredNodeId: context.hoveredNodeId,
            };
        }
        case 'WRAP_NODE': {
            const wrapped = applyWrapNode(context.document, mutation.payload.nodeId, mutation.payload.wrapperNodeId);

            return {
                document: wrapped.document,
                selectedNodeId: wrapped.selectedNodeId ?? context.selectedNodeId,
                hoveredNodeId: context.hoveredNodeId,
            };
        }
        case 'UNWRAP_NODE': {
            const unwrapped = applyUnwrapNode(context.document, mutation.payload.nodeId);

            return {
                document: unwrapped.document,
                selectedNodeId: unwrapped.selectedNodeId ?? context.selectedNodeId,
                hoveredNodeId: context.hoveredNodeId,
            };
        }
        default:
            return {
                document: context.document,
                selectedNodeId: context.selectedNodeId,
                hoveredNodeId: context.hoveredNodeId,
            };
    }
}
