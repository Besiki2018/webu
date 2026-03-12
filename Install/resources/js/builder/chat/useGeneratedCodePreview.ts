/**
 * Hook for generated code preview (derived-preview) in Chat code tab.
 * Builds virtual files from builder code pages for read-only inspection.
 * Keeps this logic out of the main Chat page.
 */
import { useMemo, useState, useEffect, useRef } from 'react';
import { buildDesignTokensFileContent, buildFullComponentSource } from '@/lib/ai-builder';
import { buildGeneratedPageCode } from './chatPageUtils';
import { getComponentCodegenMetadata, getShortDisplayName } from '@/builder/componentRegistry';
import { buildWorkspaceStructureItemsFromCodePage, getWorkspaceBuilderPageIdentity, resolveWorkspaceBuilderActivePage } from '@/builder/cms/workspaceBuilderSync';
import { buildSectionPreviewText } from '@/builder/state/sectionProps';
import { normalizeGeneratedPages, getUniqueSectionTypes, type GeneratedPagePayloadLike } from './chatPageUtils';
import type { WorkspaceBuilderCodePage, WorkspaceBuilderStructureItem } from '@/builder/cms/workspaceBuilderSync';

const GENERATED_PAGE_PATH_PREFIX = 'derived-preview';

export interface GeneratedPagePayload {
    page_id?: number | null;
    slug?: string | null;
    title?: string | null;
    revision_source?: string | null;
    sections?: unknown;
}

export interface UseGeneratedCodePreviewOptions {
    generatedPages?: GeneratedPagePayloadLike[];
    generatedPage?: GeneratedPagePayloadLike | null;
}

export interface GeneratedVirtualFile {
    path: string;
    content: string;
    language: string;
    displayName?: string;
}

export interface UseGeneratedCodePreviewReturn {
    generatedVirtualFiles: GeneratedVirtualFile[];
    generatedVirtualFilePaths: Set<string>;
    builderCodePages: WorkspaceBuilderCodePage[];
    setBuilderCodePages: React.Dispatch<React.SetStateAction<WorkspaceBuilderCodePage[]>>;
    activeBuilderCodePage: WorkspaceBuilderCodePage | null;
    builderStructureItems: WorkspaceBuilderStructureItem[];
    setBuilderStructureItems: React.Dispatch<React.SetStateAction<WorkspaceBuilderStructureItem[]>>;
    structureSnapshotPageRef: React.MutableRefObject<{ pageId: number | null; pageSlug: string | null; pageTitle: string | null }>;
    GENERATED_PAGE_PATH_PREFIX: string;
}

export function useGeneratedCodePreview(
    options: UseGeneratedCodePreviewOptions,
    viewMode: string,
): UseGeneratedCodePreviewReturn {
    const { generatedPages, generatedPage } = options;

    const initialGeneratedCodePages = useMemo(
        () => normalizeGeneratedPages(generatedPages, generatedPage),
        [generatedPage, generatedPages],
    );

    const [builderCodePages, setBuilderCodePages] = useState<WorkspaceBuilderCodePage[]>(() => initialGeneratedCodePages);
    const [builderStructureItems, setBuilderStructureItems] = useState<WorkspaceBuilderStructureItem[]>([]);
    const structureSnapshotPageRef = useRef<{ pageId: number | null; pageSlug: string | null; pageTitle: string | null }>({
        pageId: null,
        pageSlug: null,
        pageTitle: null,
    });

    useEffect(() => {
        setBuilderCodePages(initialGeneratedCodePages);
    }, [initialGeneratedCodePages]);

    const activeBuilderCodePage = useMemo(
        () => resolveWorkspaceBuilderActivePage(builderCodePages, structureSnapshotPageRef.current),
        [builderCodePages],
    );

    const generatedVirtualFiles = useMemo(() => {
        const files: GeneratedVirtualFile[] = [];
        const designTokensPath = `${GENERATED_PAGE_PATH_PREFIX}/theme/designTokens.css`;
        const designTokensDisplayName = 'designTokens.css';

        builderCodePages.forEach((page) => {
            const pageDir = page.path.replace(/\/Page\.tsx$/i, '');
            const componentImportPrefix = './components/';
            files.push({
                path: page.path,
                displayName: page.path.replace(`${GENERATED_PAGE_PATH_PREFIX}/`, ''),
                content: buildGeneratedPageCode(page, { componentImportPrefix }),
                language: 'typescript',
            });
            const sectionTypes = getUniqueSectionTypes(page.sections);
            sectionTypes.forEach((registryId) => {
                const codegen = getComponentCodegenMetadata(registryId);
                if (!codegen?.importName) return;
                const componentPath = `${pageDir}/components/${codegen.importName}.tsx`;
                files.push({
                    path: componentPath,
                    displayName: `${codegen.importName}.tsx`,
                    content: buildFullComponentSource(registryId),
                    language: 'typescript',
                });
            });
        });

        if (files.length > 0 && !files.some((f) => f.path === designTokensPath)) {
            files.push({
                path: designTokensPath,
                displayName: designTokensDisplayName,
                content: buildDesignTokensFileContent(),
                language: 'css',
            });
        }
        return files;
    }, [builderCodePages]);

    const generatedVirtualFilePaths = useMemo(
        () => new Set(generatedVirtualFiles.map((f) => f.path)),
        [generatedVirtualFiles],
    );

    useEffect(() => {
        if (viewMode !== 'inspect') return;
        if (activeBuilderCodePage === null) return;
        const sections = activeBuilderCodePage.sections ?? [];
        if (sections.length === 0) return;

        setBuilderStructureItems((current) => {
            if (current.length > 0) return current;
            const items = buildWorkspaceStructureItemsFromCodePage(activeBuilderCodePage, {
                getDisplayLabel: (sectionKey) => getShortDisplayName(sectionKey, sectionKey),
                buildPreviewText: buildSectionPreviewText,
            });
            structureSnapshotPageRef.current = {
                pageId: activeBuilderCodePage.pageId ?? null,
                pageSlug: activeBuilderCodePage.slug ?? null,
                pageTitle: activeBuilderCodePage.title ?? null,
            };
            return items;
        });
    }, [activeBuilderCodePage, viewMode]);

    return {
        generatedVirtualFiles,
        generatedVirtualFilePaths,
        builderCodePages,
        setBuilderCodePages,
        activeBuilderCodePage,
        builderStructureItems,
        setBuilderStructureItems,
        structureSnapshotPageRef,
        GENERATED_PAGE_PATH_PREFIX,
    };
}
