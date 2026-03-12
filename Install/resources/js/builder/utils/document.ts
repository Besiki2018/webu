import type { BuilderDocument } from '@/builder/types/builderDocument';
import type { BuilderNode } from '@/builder/types/builderNode';

function createRandomIdFragment(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}

export function generateBuilderNodeId(prefix = 'node'): string {
    return `${prefix}-${createRandomIdFragment()}`;
}

export function cloneBuilderDocument(document: BuilderDocument): BuilderDocument {
    return JSON.parse(JSON.stringify(document)) as BuilderDocument;
}

export function getActivePage(document: BuilderDocument, activePageId: string | null | undefined) {
    const resolvedPageId = activePageId && document.pages[activePageId] ? activePageId : document.rootPageId;

    return document.pages[resolvedPageId] ?? null;
}

export function getPageRootNode(document: BuilderDocument, activePageId: string | null | undefined): BuilderNode | null {
    const page = getActivePage(document, activePageId);

    return page ? (document.nodes[page.rootNodeId] ?? null) : null;
}

export function resolveBuilderNodeLabel(node: BuilderNode | null | undefined): string {
    if (! node) {
        return 'Untitled';
    }

    const metaLabel = typeof node.meta?.label === 'string' ? node.meta.label.trim() : '';
    if (metaLabel !== '') {
        return metaLabel;
    }

    const props = node.props ?? {};
    const propLabelCandidates = [props.title, props.headline, props.text, props.buttonText, props.label];
    for (const candidate of propLabelCandidates) {
        if (typeof candidate === 'string' && candidate.trim() !== '') {
            return candidate.trim();
        }
    }

    if (typeof node.componentKey === 'string' && node.componentKey.trim() !== '') {
        return node.componentKey.trim();
    }

    return node.type;
}

export function insertAt<T>(items: T[], index: number, item: T): T[] {
    const next = [...items];
    const targetIndex = Math.max(0, Math.min(index, next.length));
    next.splice(targetIndex, 0, item);

    return next;
}

export function removeFromArray<T>(items: T[], item: T): T[] {
    return items.filter((candidate) => candidate !== item);
}

export function collectSubtreeIds(document: BuilderDocument, rootNodeId: string): string[] {
    const stack = [rootNodeId];
    const visited = new Set<string>();

    while (stack.length > 0) {
        const currentId = stack.pop();
        if (! currentId || visited.has(currentId)) {
            continue;
        }

        const node = document.nodes[currentId];
        if (! node) {
            continue;
        }

        visited.add(currentId);
        for (const childId of node.children) {
            stack.push(childId);
        }
    }

    return [...visited];
}

export function findFallbackSelection(document: BuilderDocument, activePageId: string | null | undefined): string | null {
    const rootNode = getPageRootNode(document, activePageId);
    if (! rootNode) {
        return null;
    }

    return rootNode.children[0] ?? rootNode.id;
}

export function buildDocumentSummary(document: BuilderDocument, activePageId: string | null | undefined): string {
    const page = getActivePage(document, activePageId);
    if (! page) {
        return 'No active page';
    }

    const rootNode = document.nodes[page.rootNodeId];
    const labels = (rootNode?.children ?? [])
        .map((nodeId) => resolveBuilderNodeLabel(document.nodes[nodeId]))
        .slice(0, 12);

    return `${page.title}: ${labels.join(', ')}`;
}
