import type { BuilderNode } from '@/builder/types/builderNode';
import type { BuilderDocument } from '@/builder/types/builderDocument';
import { getBuilderComponentDefinition } from '@/builder/components/registry';
import { generateBuilderNodeId } from '@/builder/utils/document';

export function createNodeFromComponentKey(componentKey: string, parentId: string): BuilderNode {
    const definition = getBuilderComponentDefinition(componentKey);
    if (! definition) {
        throw new Error(`Unknown builder component: ${componentKey}`);
    }

    return {
        id: generateBuilderNodeId(componentKey),
        type: componentKey === 'text' ? 'text' : componentKey === 'image' ? 'image' : componentKey === 'button' ? 'button' : 'component',
        componentKey,
        parentId,
        children: [],
        props: { ...definition.defaultProps },
        styles: {},
        bindings: {},
        meta: {
            label: definition.label,
        },
    };
}

export function resolveInsertParentId(document: BuilderDocument, activePageId: string, selectedNodeId: string | null): string {
    if (selectedNodeId) {
        const selectedNode = document.nodes[selectedNodeId];
        if (selectedNode && (selectedNode.type === 'page' || selectedNode.componentKey === 'section')) {
            return selectedNode.id;
        }
        if (selectedNode?.parentId) {
            return selectedNode.parentId;
        }
    }

    const page = document.pages[activePageId] ?? document.pages[document.rootPageId];

    return page.rootNodeId;
}
