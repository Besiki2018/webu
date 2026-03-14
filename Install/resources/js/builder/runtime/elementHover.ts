export const AI_NODE_ID_ATTRIBUTE = 'data-ai-node-id';
export const AI_NODE_FLASH_ATTRIBUTE = 'data-ai-node-flash';
export const AI_NODE_TOOLTIP_LABEL = 'Click to edit with AI';
export const AI_NODE_HOVER_OUTLINE = '2px solid #4F46E5';
export const AI_NODE_FLASH_DURATION_MS = 400;

function normalizeSegment(value: string | null | undefined): string | null {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

export function buildAiNodeId(
    sectionLocalId: string | null | undefined,
    parameterPath?: string | null,
    sectionKey?: string | null,
): string | null {
    const scope = normalizeSegment(sectionLocalId) ?? normalizeSegment(sectionKey);
    const path = normalizeSegment(parameterPath);

    if (!scope) {
        return null;
    }

    return path ? `${scope}.${path}` : scope;
}

export function buildAiNodeTag(aiNodeId: string): string {
    return `@node(${aiNodeId})`;
}

export function extractAiNodeIds(value: string): string[] {
    const matches = value.matchAll(/@node\(([^)\n]+)\)/g);
    const ids = new Set<string>();

    for (const match of matches) {
        const nextId = normalizeSegment(match[1]);
        if (nextId) {
            ids.add(nextId);
        }
    }

    return Array.from(ids);
}

export function stripAiNodeTags(value: string): string {
    return value
        .replace(/@node\(([^)\n]+)\)\s*/g, '')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

export function appendAiNodeTag(value: string, aiNodeId: string): string {
    const nextTag = buildAiNodeTag(aiNodeId);
    if (extractAiNodeIds(value).includes(aiNodeId)) {
        return value;
    }

    const trimmed = value.trimEnd();
    if (trimmed === '') {
        return `${nextTag}\n`;
    }

    return `${trimmed}\n${nextTag}\n`;
}

export function readAiNodeId(target: Element | null): string | null {
    if (!target) {
        return null;
    }

    const node = target.closest<HTMLElement>(`[${AI_NODE_ID_ATTRIBUTE}]`);
    return normalizeSegment(node?.getAttribute(AI_NODE_ID_ATTRIBUTE));
}

export function flashAiNodes(doc: Document, aiNodeIds: string[]): void {
    if (aiNodeIds.length === 0) {
        return;
    }

    const uniqueIds = Array.from(new Set(aiNodeIds.map((value) => normalizeSegment(value)).filter(Boolean)));
    uniqueIds.forEach((aiNodeId) => {
        const selector = `[${AI_NODE_ID_ATTRIBUTE}="${String(aiNodeId).replace(/"/g, '\\"')}"]`;
        doc.querySelectorAll<HTMLElement>(selector).forEach((node) => {
            node.setAttribute(AI_NODE_FLASH_ATTRIBUTE, 'true');
            window.setTimeout(() => {
                node.removeAttribute(AI_NODE_FLASH_ATTRIBUTE);
            }, AI_NODE_FLASH_DURATION_MS);
        });
    });
}
