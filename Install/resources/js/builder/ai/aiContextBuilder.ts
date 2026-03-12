import type { BuilderDocument } from '@/builder/types/builderDocument';
import { buildDocumentSummary, resolveBuilderNodeLabel } from '@/builder/utils/document';

export function buildAiSelectedNodeContext(document: BuilderDocument, selectedNodeId: string | null): string | null {
    if (! selectedNodeId) {
        return null;
    }

    const node = document.nodes[selectedNodeId];
    if (! node) {
        return null;
    }

    return `${resolveBuilderNodeLabel(node)} (${node.componentKey ?? node.type})`;
}

export function buildAiPromptContext(document: BuilderDocument, activePageId: string, selectedNodeId: string | null) {
    return {
        pageSummary: buildDocumentSummary(document, activePageId),
        selectedNodeContext: buildAiSelectedNodeContext(document, selectedNodeId),
    };
}
