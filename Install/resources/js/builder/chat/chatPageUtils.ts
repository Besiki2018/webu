import { buildPageComponentCode } from '@/lib/ai-builder';
import { buildGeneratedPagePath } from '@/builder/cms/chatEmbeddedBuilderUtils';
import {
    stringifySectionProps,
    isRecord as isBuilderRecord,
} from '@/builder/state/sectionProps';
import type {
    WorkspaceBuilderCodePage as BuilderCodePage,
    WorkspaceBuilderCodeSection as BuilderCodeSection,
} from '@/builder/cms/workspaceBuilderSync';

export type ChatViewMode = 'preview' | 'inspect' | 'code' | 'design' | 'settings';

export interface GeneratedPagePayloadLike {
    page_id: number | null;
    slug: string | null;
    title: string | null;
    revision_source: string | null;
    sections?: unknown;
}

export interface DerivedPreviewCodeFile {
    path: string;
    content: string;
    language: string;
    displayName?: string;
}

const VIEW_MODES: ChatViewMode[] = ['preview', 'inspect', 'code', 'design', 'settings'];
const DEFAULT_STRUCTURE_PANEL_POSITION = { x: 24, y: 72 };

export function isConversationalMessage(message: string): boolean {
    const lower = message.trim().toLowerCase();
    const normalized = lower.replace(/\s+/g, ' ');
    if (!normalized) return false;
    const conversational = [
        'გამარჯობა', 'გისურვებთ', 'ჰეი', 'ჰაი', 'hello', 'hi ', 'hi!', 'hi.', 'hey ', 'hey!',
        'აქ ხარ', 'არის ვინმე', 'are you there', 'anyone there', 'შეგიძლია', 'can you hear',
        'good morning', 'good evening', 'good night', 'დილა მშვიდობისა', 'საღამო მშვიდობისა',
    ];
    if (conversational.some((phrase) => normalized.startsWith(phrase) || normalized === phrase.replace(/ $/, ''))) return true;
    if (/^(hi|hey|hello|გამარჯობა|ჰეი|ჰაი)[\s.!?]*$/i.test(normalized)) return true;
    if (/^აქ\s+ხარ\s*\??\s*$/i.test(normalized)) return true;
    return false;
}

export function buildDetailedAssistantMessage(
    intro: string,
    changesLabel: string,
    details: string[] = [],
    note?: string | null,
): string {
    const normalizedDetails = details
        .map((detail) => detail.trim())
        .filter((detail) => detail.length > 0)
        .slice(0, 8);

    const lines = [intro.trim()];

    if (note && note.trim()) {
        lines.push('', note.trim());
    }

    if (normalizedDetails.length > 0) {
        lines.push('', changesLabel.trim(), ...normalizedDetails.map((detail) => `- ${detail}`));
    }

    return lines.join('\n');
}

export function reorderStructureCollection<T extends { localId: string }>(
    items: T[],
    activeLocalId: string,
    targetLocalId: string,
    position: 'before' | 'after'
): T[] {
    const sourceIndex = items.findIndex((item) => item.localId === activeLocalId);
    const targetIndex = items.findIndex((item) => item.localId === targetLocalId);

    if (sourceIndex === -1 || targetIndex === -1 || sourceIndex === targetIndex) {
        return items;
    }

    const next = [...items];
    const [moved] = next.splice(sourceIndex, 1);
    const insertionAnchorIndex = next.findIndex((item) => item.localId === targetLocalId);
    if (!moved || insertionAnchorIndex === -1) {
        return items;
    }

    const insertionIndex = position === 'after' ? insertionAnchorIndex + 1 : insertionAnchorIndex;
    next.splice(insertionIndex, 0, moved);

    return next;
}

export function getStructurePanelStorageKey(projectId: string): string {
    return `webu:chat:structure-panel:${projectId}`;
}

export function readPersistedStructurePanelState(projectId: string, fallbackOpen: boolean): {
    open: boolean;
    position: { x: number; y: number };
} {
    if (typeof window === 'undefined') {
        return {
            open: fallbackOpen,
            position: DEFAULT_STRUCTURE_PANEL_POSITION,
        };
    }

    try {
        const raw = window.localStorage.getItem(getStructurePanelStorageKey(projectId));
        if (!raw) {
            return {
                open: fallbackOpen,
                position: DEFAULT_STRUCTURE_PANEL_POSITION,
            };
        }

        const parsed = JSON.parse(raw) as {
            open?: unknown;
            position?: { x?: unknown; y?: unknown } | null;
        } | null;
        const x = Number(parsed?.position?.x);
        const y = Number(parsed?.position?.y);

        return {
            // The floating structure panel should stay closed by default and open only
            // from the header toggle, even if an older session stored it as open.
            open: fallbackOpen,
            position: Number.isFinite(x) && Number.isFinite(y)
                ? { x, y }
                : DEFAULT_STRUCTURE_PANEL_POSITION,
        };
    } catch {
        return {
            open: fallbackOpen,
            position: DEFAULT_STRUCTURE_PANEL_POSITION,
        };
    }
}

export function normalizeGeneratedPageSections(sections: unknown): BuilderCodeSection[] {
    if (!Array.isArray(sections)) {
        return [];
    }

    return sections.flatMap((entry, index) => {
        if (!isBuilderRecord(entry)) {
            return [];
        }

        const type = typeof entry.type === 'string' ? entry.type.trim() : '';
        const props = isBuilderRecord(entry.props) ? entry.props : {};

        if (type === '') {
            return [];
        }

        const localId = typeof entry.localId === 'string' && entry.localId.trim() !== ''
            ? entry.localId.trim()
            : `generated-section-${index + 1}`;
        return [{
            localId,
            type,
            props,
            propsText: stringifySectionProps(props),
        }];
    });
}

export function buildGeneratedPageCode(page: BuilderCodePage, options: { componentImportPrefix?: string } = {}): string {
    return buildPageComponentCode(page.sections, {
        pageName: page.title?.trim() || page.slug?.trim() || 'Current page',
        revisionSource: page.revisionSource,
        componentImportPrefix: options.componentImportPrefix ?? undefined,
    });
}

export function buildDerivedPreviewFiles(
    pages: BuilderCodePage[],
    options: { prefix?: string } = {},
): DerivedPreviewCodeFile[] {
    const prefix = (options.prefix ?? 'derived-preview').replace(/\/+$/, '');
    if (pages.length === 0) {
        return [];
    }

    const files: DerivedPreviewCodeFile[] = [{
        path: `${prefix}/README.md`,
        language: 'markdown',
        displayName: 'README.md',
        content: [
            '# Derived Preview',
            '',
            'These files are read-only preview artifacts generated from CMS/PageRevision.',
            'They are useful for inspection, but they are not part of the editable workspace.',
            'AI project-edit and manual code edits operate on real workspace files instead.',
        ].join('\n'),
    }];

    pages.forEach((page, index) => {
        const slug = page.slug?.trim() || `page-${index + 1}`;
        files.push({
            path: `${prefix}/pages/${slug}/Page.preview.tsx`,
            displayName: `${slug}/Page.preview.tsx`,
            content: buildGeneratedPageCode(page, { componentImportPrefix: './preview-components/' }),
            language: 'typescript',
        });
    });

    files.push({
        path: `${prefix}/site-manifest.json`,
        displayName: 'site-manifest.json',
        language: 'json',
        content: JSON.stringify({
            pages: pages.map((page) => ({
                slug: page.slug,
                title: page.title,
                path: page.path,
                revisionSource: page.revisionSource,
                sections: page.sections.map((section) => ({
                    localId: section.localId,
                    type: section.type,
                })),
            })),
        }, null, 2),
    });

    return files;
}

export function getUniqueSectionTypes(sections: BuilderCodePage['sections']): string[] {
    const seen = new Set<string>();
    return (sections ?? [])
        .map((section) => (section.type ?? '').trim())
        .filter((type) => type && !seen.has(type) && (seen.add(type), true));
}

export function normalizeGeneratedPages(
    generatedPages: GeneratedPagePayloadLike[] | undefined,
    generatedPage: GeneratedPagePayloadLike | null | undefined
): BuilderCodePage[] {
    const sourcePages = Array.isArray(generatedPages) && generatedPages.length > 0
        ? generatedPages
        : (generatedPage ? [generatedPage] : []);
    const seenPaths = new Set<string>();

    return sourcePages.flatMap((page, index) => {
        const path = buildGeneratedPagePath(page, index);
        if (seenPaths.has(path)) {
            return [];
        }
        seenPaths.add(path);

        return [{
            path,
            pageId: page.page_id,
            slug: page.slug,
            title: page.title,
            revisionSource: page.revision_source,
            sections: normalizeGeneratedPageSections(page.sections),
        }];
    });
}

export function getInitialViewMode(): ChatViewMode {
    if (typeof window === 'undefined') return 'preview';
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('tab');
    if (tab && VIEW_MODES.includes(tab as ChatViewMode)) {
        return tab as ChatViewMode;
    }
    return 'preview';
}
