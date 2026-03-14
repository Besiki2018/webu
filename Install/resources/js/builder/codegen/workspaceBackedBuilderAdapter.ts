import { useCallback, useEffect, useRef } from 'react';
import axios from 'axios';

import {
    getComponentCodegenMetadata,
    getShortDisplayName,
    resolveComponentRegistryKey,
} from '@/builder/componentRegistry';
import { buildBuilderPageModelFromSectionDrafts } from '@/builder/model/pageModel';
import { normalizePath, parseSectionProps } from '@/builder/state/sectionProps';
import type { BuilderUpdateOperation } from '@/builder/state/updatePipeline';
import type { SectionDraft } from '@/builder/state/useBuilderCanvasState';
import { buildCmsBindingModelFromBuilderPageModel } from '@/builder/cmsIntegration/cmsBindingModel';
import { prepareVisualBuilderCmsEditExecution } from '@/builder/cmsIntegration/editExecutors';
import { applyCmsBindingModelToGeneratedPage } from '@/builder/cmsIntegration/workspaceCmsSync';
import { cloneRecordData } from '@/builder/runtime/clone';

import { buildProjectGraphPatchFromBuilderOperations } from './builderModelToProjectGraphPatch';
import {
    buildDefaultProjectGraphPageRoute,
    createGeneratedComponentInstance,
    createGeneratedFile,
    createGeneratedLayout,
    createGeneratedPage,
    createGeneratedProjectGraph,
    createGeneratedSection,
    resolveGeneratedSectionKindFromRegistryKey,
} from './projectGraph';
import type {
    GeneratedFile,
    GeneratedPage,
    GeneratedProjectGraph,
    ProjectGraphPatchInstruction,
    WorkspaceEditorSource,
    WorkspaceManifest,
    WorkspaceManifestFileOwnership,
} from './types';
import {
    applyManifestEditorMetadata,
    buildWorkspaceManifestFromProjectGraph,
    ensureManifestOwnershipEntries,
    normalizeWorkspaceManifest,
    WORKSPACE_MANIFEST_RELATIVE_PATH,
} from './workspaceManifest';

export interface WorkspaceProjectionFileMetadata {
    projection_role?: string | null;
    projection_source?: string | null;
    page_slug?: string | null;
    layout_files?: string[];
    section_files?: string[];
    component_name?: string | null;
    section_types?: string[];
    prop_paths?: string[];
    pages?: string[];
    page_paths?: string[];
}

export interface WorkspaceProjectionPageSectionMetadata {
    component_name?: string | null;
    component_path?: string | null;
    type?: string | null;
    local_id?: string | null;
    prop_keys?: string[];
    prop_paths?: string[];
    sample_props?: Record<string, unknown>;
    variants?: Record<string, unknown>;
}

export interface WorkspaceProjectionPageMetadata {
    page_id?: number | string | null;
    slug?: string | null;
    title?: string | null;
    path?: string | null;
    layout_files?: string[];
    section_files?: string[];
    sections?: WorkspaceProjectionPageSectionMetadata[];
}

export interface WorkspaceProjectionMetadata {
    pages: WorkspaceProjectionPageMetadata[];
    layouts: Array<Record<string, unknown>>;
    components: Array<Record<string, unknown>>;
    files: Record<string, WorkspaceProjectionFileMetadata>;
}

export interface WorkspaceBackedBuilderRevisionCursor {
    revisionId: number | null;
    revisionVersion: number | null;
}

export interface WorkspaceBackedBuilderState {
    loadedPageKey: string | null;
    manifest: WorkspaceManifest;
    projectionMetadata: WorkspaceProjectionMetadata;
    currentPageGraph: GeneratedPage | null;
    currentProjectGraph: GeneratedProjectGraph | null;
    pendingGraphPatches: ProjectGraphPatchInstruction[];
    dirtyPaths: string[];
    lastEditor: WorkspaceEditorSource | null;
    revisionCursor: WorkspaceBackedBuilderRevisionCursor;
    lastPersistedSignature: string | null;
}

export interface WorkspaceBackedBuilderMutationResult {
    editor: WorkspaceEditorSource;
    graphPatches: ProjectGraphPatchInstruction[];
    dirtyPaths: string[];
    pageGraph: GeneratedPage;
    manifest: WorkspaceManifest;
}

export interface WorkspaceBackedBuilderPersistResult {
    success: boolean;
    manifest: WorkspaceManifest | null;
    dirtyPaths: string[];
    errorMessage: string | null;
}

export interface WorkspaceBackedBuilderPageInput {
    id: number | null;
    slug: string | null;
    title: string | null;
}

export interface WorkspaceBackedBuilderLayoutOverrides {
    headerVariant: string | null;
    footerVariant: string | null;
}

interface BuildWorkspaceBackedGeneratedPageOptions {
    projectId: string;
    page: WorkspaceBackedBuilderPageInput;
    sectionsDraft: SectionDraft[];
    projectionMetadata: WorkspaceProjectionMetadata;
    layoutOverrides?: WorkspaceBackedBuilderLayoutOverrides;
    editor: WorkspaceEditorSource;
}

interface ApplyWorkspaceBackedBuilderMutationsOptions extends Omit<BuildWorkspaceBackedGeneratedPageOptions, 'editor'> {
    manifest: WorkspaceManifest;
    operations: BuilderUpdateOperation[];
}

interface UseWorkspaceBackedBuilderAdapterOptions {
    enabled?: boolean;
    projectId: string;
    projectName: string;
    currentPage: WorkspaceBackedBuilderPageInput;
    currentRevisionCursor: WorkspaceBackedBuilderRevisionCursor;
    getCurrentSectionsDraft: () => SectionDraft[];
    getCurrentLayoutOverrides?: () => WorkspaceBackedBuilderLayoutOverrides;
    onPreviewRefresh?: () => void;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function cloneRecord<T extends Record<string, unknown>>(value: T | null | undefined): T {
    return cloneRecordData(value);
}

function normalizeText(value: string | number | null | undefined, fallback = ''): string {
    const normalized = typeof value === 'string' || typeof value === 'number'
        ? String(value).trim()
        : '';
    return normalized !== '' ? normalized : fallback;
}

function normalizePageId(page: WorkspaceBackedBuilderPageInput): string {
    return normalizeText(page.id, page.slug ?? 'page');
}

function normalizePageSlug(page: WorkspaceBackedBuilderPageInput): string {
    return normalizeText(page.slug, 'home').toLowerCase();
}

function normalizePageTitle(page: WorkspaceBackedBuilderPageInput): string {
    return normalizeText(page.title, 'Untitled Page');
}

function createEmptyProjectionMetadata(): WorkspaceProjectionMetadata {
    return {
        pages: [],
        layouts: [],
        components: [],
        files: {},
    };
}

function normalizeProjectionMetadata(input: unknown): WorkspaceProjectionMetadata {
    const record = isRecord(input) ? input : {};

    return {
        pages: Array.isArray(record.pages)
            ? record.pages.filter((page): page is WorkspaceProjectionPageMetadata => isRecord(page))
            : [],
        layouts: Array.isArray(record.layouts)
            ? record.layouts.filter((entry): entry is Record<string, unknown> => isRecord(entry))
            : [],
        components: Array.isArray(record.components)
            ? record.components.filter((entry): entry is Record<string, unknown> => isRecord(entry))
            : [],
        files: isRecord(record.files)
            ? Object.fromEntries(Object.entries(record.files).filter(([, value]) => isRecord(value))) as Record<string, WorkspaceProjectionFileMetadata>
            : {},
    };
}

function mapOperationSourceToEditor(source: BuilderUpdateOperation['source']): WorkspaceEditorSource {
    return source === 'chat' ? 'ai' : 'visual_builder';
}

function inferEditorFromOperations(operations: BuilderUpdateOperation[], fallback: WorkspaceEditorSource = 'visual_builder'): WorkspaceEditorSource {
    for (const operation of operations) {
        if (operation.source === 'chat') {
            return 'ai';
        }
    }

    return fallback;
}

function findProjectionPage(
    projectionMetadata: WorkspaceProjectionMetadata,
    page: WorkspaceBackedBuilderPageInput,
): WorkspaceProjectionPageMetadata | null {
    const pageId = normalizePageId(page);
    const pageSlug = normalizePageSlug(page);

    return projectionMetadata.pages.find((entry) => (
        normalizeText(entry.page_id).toLowerCase() === pageId.toLowerCase()
        || normalizeText(entry.slug).toLowerCase() === pageSlug
    )) ?? null;
}

function buildDefaultPagePath(page: WorkspaceBackedBuilderPageInput): string {
    return `src/pages/${normalizePageSlug(page)}/Page.tsx`;
}

function defaultLayoutFiles(): string[] {
    return [
        'src/layouts/SiteLayout.tsx',
        'src/components/Header.tsx',
        'src/components/Footer.tsx',
    ];
}

function softParseProps(draft: SectionDraft): Record<string, unknown> {
    const parsed = parseSectionProps(draft.propsText);
    if (parsed !== null) {
        return parsed;
    }

    return isRecord(draft.props) ? cloneRecord(draft.props) : {};
}

function buildWorkspaceBackedPageSignature(input: {
    page: WorkspaceBackedBuilderPageInput;
    sectionsDraft: SectionDraft[];
    layoutOverrides?: WorkspaceBackedBuilderLayoutOverrides;
}): string {
    return JSON.stringify({
        pageId: normalizePageId(input.page),
        pageSlug: normalizePageSlug(input.page),
        layoutOverrides: input.layoutOverrides ?? null,
        sections: input.sectionsDraft.map((section) => ({
            localId: normalizeText(section.localId),
            type: normalizeText(section.type),
            props: softParseProps(section),
            bindingMeta: isRecord(section.bindingMeta) ? cloneRecord(section.bindingMeta) : null,
        })),
    });
}

function buildSectionComponentPath(registryKey: string | null): string | null {
    if (!registryKey) {
        return null;
    }

    const codegen = getComponentCodegenMetadata(registryKey);
    if (!codegen?.importName) {
        return null;
    }

    if (registryKey.includes('header')) {
        return 'src/components/Header.tsx';
    }

    if (registryKey.includes('footer')) {
        return 'src/components/Footer.tsx';
    }

    return `src/sections/${codegen.importName}.tsx`;
}

function resolveProjectionPageFiles(
    projectionMetadata: WorkspaceProjectionMetadata,
    page: WorkspaceBackedBuilderPageInput,
): {
    pagePath: string;
    layoutFiles: string[];
    sectionFiles: string[];
    sectionFileByLocalId: Map<string, string>;
} {
    const projectionPage = findProjectionPage(projectionMetadata, page);
    const pagePath = normalizeText(projectionPage?.path, buildDefaultPagePath(page));
    const layoutFiles = Array.isArray(projectionPage?.layout_files) && projectionPage.layout_files.length > 0
        ? projectionPage.layout_files.filter((path): path is string => typeof path === 'string' && path.trim() !== '')
        : defaultLayoutFiles();
    const sectionFiles = Array.isArray(projectionPage?.section_files)
        ? projectionPage.section_files.filter((path): path is string => typeof path === 'string' && path.trim() !== '')
        : [];
    const sectionFileByLocalId = new Map<string, string>();

    (projectionPage?.sections ?? []).forEach((section) => {
        const localId = normalizeText(section.local_id);
        const componentPath = normalizeText(section.component_path);
        if (localId && componentPath) {
            sectionFileByLocalId.set(localId, componentPath);
        }
    });

    return {
        pagePath,
        layoutFiles,
        sectionFiles,
        sectionFileByLocalId,
    };
}

function buildCurrentPageManifestEntries(
    manifest: WorkspaceManifest,
    pageGraph: GeneratedPage,
    projectionMetadata: WorkspaceProjectionMetadata,
): WorkspaceManifestFileOwnership[] {
    const projectionFiles = resolveProjectionPageFiles(projectionMetadata, {
        id: Number.isFinite(Number(pageGraph.id)) ? Number(pageGraph.id) : null,
        slug: pageGraph.slug,
        title: pageGraph.title,
    });
    const fileCatalog = projectionMetadata.files;
    const pageSectionLocalIds = pageGraph.sections.map((section) => section.localId);
    const pageComponentKeys = pageGraph.sections
        .map((section) => normalizeText(section.registryKey ?? section.components[0]?.registryKey ?? section.components[0]?.key))
        .filter((value): value is string => value !== '');
    const existingEntries = new Map(manifest.fileOwnership.map((entry) => [entry.path, entry]));

    const ensureEntry = (
        path: string,
        input: Partial<WorkspaceManifestFileOwnership>,
    ): WorkspaceManifestFileOwnership => {
        const normalizedPath = path.trim();
        const existing = existingEntries.get(normalizedPath);
        const projectionMeta = fileCatalog[normalizedPath] ?? {};
        const projectionRole = normalizeText(projectionMeta.projection_role) ?? input.kind ?? 'component';

        return {
            path: normalizedPath,
            kind: input.kind ?? (projectionRole === 'page'
                ? 'page'
                : projectionRole.includes('layout')
                    ? 'layout'
                    : 'component'),
            ownerType: input.ownerType ?? (projectionRole === 'page'
                ? 'page'
                : projectionRole.includes('layout')
                    ? 'layout'
                    : 'component'),
            ownerId: input.ownerId ?? pageGraph.id,
            generatedBy: existing?.generatedBy ?? 'ai',
            editState: existing?.editState ?? 'ai-generated',
            pageIds: input.pageIds ?? existing?.pageIds ?? [pageGraph.id],
            componentIds: input.componentIds ?? existing?.componentIds ?? pageGraph.sections.map((section) => `${pageGraph.id}:${section.localId}`),
            activeGenerationRunId: manifest.activeGenerationRunId,
            checksum: existing?.checksum ?? null,
            sectionLocalIds: input.sectionLocalIds ?? existing?.sectionLocalIds ?? pageSectionLocalIds,
            componentKeys: input.componentKeys ?? existing?.componentKeys ?? pageComponentKeys,
            originatingPageId: existing?.originatingPageId ?? pageGraph.id,
            originatingPageSlug: existing?.originatingPageSlug ?? pageGraph.slug,
            lastEditor: existing?.lastEditor ?? null,
            dirty: existing?.dirty ?? false,
            updatedAt: existing?.updatedAt ?? null,
            locked: existing?.locked ?? false,
            templateOwned: existing?.templateOwned ?? projectionRole === 'layout',
            lastOperationId: existing?.lastOperationId ?? null,
            lastOperationKind: existing?.lastOperationKind ?? null,
            cmsBacked: existing?.cmsBacked ?? false,
            contentOwner: existing?.contentOwner ?? null,
            cmsFieldPaths: existing?.cmsFieldPaths ?? [],
            visualFieldPaths: existing?.visualFieldPaths ?? [],
            codeFieldPaths: existing?.codeFieldPaths ?? [],
            syncDirection: existing?.syncDirection ?? null,
            conflictStatus: existing?.conflictStatus ?? null,
        };
    };

    const entries: WorkspaceManifestFileOwnership[] = [
        ensureEntry(projectionFiles.pagePath, {
            kind: 'page',
            ownerType: 'page',
            ownerId: pageGraph.id,
            pageIds: [pageGraph.id],
        }),
        ...projectionFiles.layoutFiles.map((path) => ensureEntry(path, {
            kind: 'layout',
            ownerType: 'layout',
            ownerId: 'site-layout',
            pageIds: [pageGraph.id],
        })),
    ];

    const seenSectionPaths = new Set<string>();
    pageGraph.sections.forEach((section) => {
        const path = projectionFiles.sectionFileByLocalId.get(section.localId)
            ?? buildSectionComponentPath(section.registryKey)
            ?? null;
        if (!path || seenSectionPaths.has(path)) {
            return;
        }

        seenSectionPaths.add(path);
        entries.push(ensureEntry(path, {
            kind: 'component',
            ownerType: 'component',
            ownerId: section.id,
            pageIds: [pageGraph.id],
            sectionLocalIds: [section.localId],
            componentKeys: [normalizeText(section.registryKey, section.components[0]?.key ?? '')],
        }));
    });

    return entries;
}

function buildGeneratedProjectGraphForCurrentPage(
    options: BuildWorkspaceBackedGeneratedPageOptions,
): GeneratedProjectGraph {
    const pageId = normalizePageId(options.page);
    const pageSlug = normalizePageSlug(options.page);
    const pageTitle = normalizePageTitle(options.page);
    const pageFiles = resolveProjectionPageFiles(options.projectionMetadata, options.page);
    const pageModel = buildBuilderPageModelFromSectionDrafts(options.sectionsDraft, {
        editorMode: 'builder',
        textEditorHtml: '',
        extraContent: {},
    });
    const bindingModel = buildCmsBindingModelFromBuilderPageModel({
        page: {
            id: pageId,
            slug: pageSlug,
            title: pageTitle,
        },
        model: pageModel,
        editor: options.editor === 'ai' ? 'ai' : 'visual_builder',
        createdBy: options.editor === 'ai' ? 'ai' : 'visual_builder',
    });

    const sections = pageModel.sections.map((section, index) => {
        const registryKey = resolveComponentRegistryKey(section.type) ?? section.type;
        const sectionKind = resolveGeneratedSectionKindFromRegistryKey(registryKey);
        const componentPath = pageFiles.sectionFileByLocalId.get(section.localId)
            ?? buildSectionComponentPath(registryKey)
            ?? null;
        const sectionProps = cloneRecord(section.props);
        const sectionId = `${pageId}:${section.localId}`;

        return createGeneratedSection({
            id: sectionId,
            localId: section.localId,
            kind: sectionKind,
            registryKey,
            label: getShortDisplayName(registryKey, section.type),
            order: index,
            props: sectionProps,
            sourceFilePath: componentPath,
            metadata: {
                bindingMeta: section.bindingMeta ?? null,
            },
            components: [
                createGeneratedComponentInstance({
                    id: `${sectionId}:component`,
                    key: registryKey,
                    registryKey,
                    displayName: getShortDisplayName(registryKey, section.type),
                    kind: sectionKind === 'header' || sectionKind === 'footer'
                        ? 'layout'
                        : sectionKind === 'hero' || sectionKind === 'features' || sectionKind === 'cta'
                            ? 'section'
                            : 'content',
                    props: cloneRecord(sectionProps),
                    children: [],
                    parentId: null,
                    pageId,
                    sectionId,
                    sourceFilePath: componentPath,
                    editable: true,
                    provenance: {
                        source: options.editor === 'ai' ? 'ai' : 'user',
                        runId: options.projectId,
                        promptFingerprint: null,
                        templateId: null,
                        notes: ['workspace-backed-builder'],
                    },
                }),
            ],
        });
    });

    const page = applyCmsBindingModelToGeneratedPage(createGeneratedPage({
        id: pageId,
        slug: pageSlug,
        title: pageTitle,
        routeId: `${pageId}:route`,
        layoutId: 'site-layout',
        entryFilePath: pageFiles.pagePath,
        sections,
        metadata: {
            layoutOverrides: {
                headerVariant: options.layoutOverrides?.headerVariant ?? null,
                footerVariant: options.layoutOverrides?.footerVariant ?? null,
            },
        },
    }), bindingModel);

    const files: GeneratedFile[] = buildCurrentPageManifestEntries(
        normalizeWorkspaceManifest({
            projectId: options.projectId,
            manifestPath: WORKSPACE_MANIFEST_RELATIVE_PATH,
        }),
        page,
        options.projectionMetadata,
    ).map((entry) => createGeneratedFile({
        id: entry.path,
        path: entry.path,
        contents: '',
        kind: entry.kind,
        language: entry.path.endsWith('.tsx')
            ? 'tsx'
            : entry.path.endsWith('.css')
                ? 'css'
                : entry.path.endsWith('.json')
                    ? 'json'
                    : 'text',
        source: entry.generatedBy,
        editState: entry.editState,
        ownerType: entry.ownerType,
        ownerId: entry.ownerId,
        pageIds: entry.pageIds,
        componentIds: entry.componentIds,
        metadata: {
            sectionLocalIds: entry.sectionLocalIds,
            componentKeys: entry.componentKeys,
        },
    }));

    return createGeneratedProjectGraph({
        projectId: options.projectId,
        name: pageTitle,
        rootDir: null,
        pages: [page],
        layouts: [
            createGeneratedLayout({
                id: 'site-layout',
                key: 'site-layout',
                name: 'Site Layout',
                kind: 'site',
                filePath: pageFiles.layoutFiles[0] ?? 'src/layouts/SiteLayout.tsx',
                props: {
                    headerVariant: options.layoutOverrides?.headerVariant ?? null,
                    footerVariant: options.layoutOverrides?.footerVariant ?? null,
                },
                slots: ['header', 'content', 'footer'],
                sectionIds: sections.map((section) => section.id),
            }),
        ],
        routes: [buildDefaultProjectGraphPageRoute(page)],
        components: page.sections.flatMap((section) => section.components),
        files,
        generation: {
            phase: 'ready',
            runId: null,
            message: null,
            errorMessage: null,
            startedAt: null,
            updatedAt: null,
            completedAt: null,
            failedAt: null,
            preview: {
                status: 'pending',
                ready: false,
                buildId: null,
                previewUrl: null,
                artifactHash: null,
                workspaceHash: null,
                builtAt: null,
                errorMessage: null,
            },
        },
    });
}

function mergeRecordsDeep(base: Record<string, unknown>, patch: Record<string, unknown>): Record<string, unknown> {
    const next = cloneRecord(base);

    Object.entries(patch).forEach(([key, value]) => {
        if (isRecord(value) && isRecord(next[key])) {
            next[key] = mergeRecordsDeep(next[key] as Record<string, unknown>, value);
            return;
        }

        next[key] = value;
    });

    return next;
}

function unsetValueAtPath(value: Record<string, unknown>, path: string[]): Record<string, unknown> {
    if (path.length === 0) {
        return value;
    }

    const [head, ...rest] = path;
    const next = cloneRecord(value);
    if (rest.length === 0) {
        delete next[head];
        return next;
    }

    if (!isRecord(next[head])) {
        return next;
    }

    next[head] = unsetValueAtPath(next[head] as Record<string, unknown>, rest);
    return next;
}

function reindexGeneratedPageSections(page: GeneratedPage): GeneratedPage {
    return {
        ...page,
        sections: page.sections.map((section, index) => ({
            ...section,
            order: index,
        })),
    };
}

export function applyProjectGraphPatchesToGeneratedPage(
    page: GeneratedPage,
    patches: ProjectGraphPatchInstruction[],
): GeneratedPage {
    let nextPage: GeneratedPage = {
        ...page,
        sections: [...page.sections],
    };

    patches.forEach((patch) => {
        switch (patch.kind) {
            case 'insert-section': {
                const registryKey = patch.section.registryKey ?? patch.section.localId;
                const section = createGeneratedSection({
                    id: `${page.id}:${patch.section.localId}`,
                    localId: patch.section.localId,
                    kind: patch.section.kind,
                    registryKey,
                    label: patch.section.label ?? getShortDisplayName(registryKey, registryKey),
                    order: typeof patch.insertIndex === 'number' ? patch.insertIndex : nextPage.sections.length,
                    props: cloneRecord(patch.section.props),
                    components: [
                        createGeneratedComponentInstance({
                            id: `${page.id}:${patch.section.localId}:component`,
                            key: registryKey,
                            registryKey,
                            displayName: getShortDisplayName(registryKey, registryKey),
                            kind: 'section',
                            props: cloneRecord(patch.section.props),
                            children: [],
                            parentId: null,
                            pageId: page.id,
                            sectionId: `${page.id}:${patch.section.localId}`,
                            sourceFilePath: buildSectionComponentPath(registryKey),
                            editable: true,
                            provenance: {
                                source: patch.source === 'builder' ? 'user' : 'ai',
                                runId: null,
                                promptFingerprint: null,
                                templateId: null,
                                notes: [],
                            },
                        }),
                    ],
                });

                const insertIndex = typeof patch.insertIndex === 'number' && Number.isFinite(patch.insertIndex)
                    ? Math.max(0, Math.min(patch.insertIndex, nextPage.sections.length))
                    : nextPage.sections.length;
                const sections = [...nextPage.sections];
                sections.splice(insertIndex, 0, section);
                nextPage = reindexGeneratedPageSections({
                    ...nextPage,
                    sections,
                });
                break;
            }
            case 'update-section-props': {
                nextPage = {
                    ...nextPage,
                    sections: nextPage.sections.map((section) => (
                        section.id === patch.sectionId || section.localId === patch.sectionId
                            ? {
                                ...section,
                                props: mergeRecordsDeep(section.props, patch.propsPatch),
                                components: section.components.map((component, index) => (
                                    index === 0
                                        ? { ...component, props: mergeRecordsDeep(component.props, patch.propsPatch) }
                                        : component
                                )),
                            }
                            : section
                    )),
                };
                break;
            }
            case 'unset-section-props': {
                nextPage = {
                    ...nextPage,
                    sections: nextPage.sections.map((section) => {
                        if (section.id !== patch.sectionId && section.localId !== patch.sectionId) {
                            return section;
                        }

                        const nextProps = patch.paths.reduce<Record<string, unknown>>((current, rawPath) => {
                            const normalizedPath = normalizePath(rawPath);
                            return unsetValueAtPath(current, normalizedPath);
                        }, cloneRecord(section.props));

                        return {
                            ...section,
                            props: nextProps,
                            components: section.components.map((component, index) => (
                                index === 0
                                    ? { ...component, props: cloneRecord(nextProps) }
                                    : component
                            )),
                        };
                    }),
                };
                break;
            }
            case 'delete-section': {
                nextPage = reindexGeneratedPageSections({
                    ...nextPage,
                    sections: nextPage.sections.filter((section) => section.id !== patch.sectionId && section.localId !== patch.sectionId),
                });
                break;
            }
            case 'move-section': {
                const currentIndex = nextPage.sections.findIndex((section) => section.id === patch.sectionId || section.localId === patch.sectionId);
                if (currentIndex === -1) {
                    break;
                }

                const sections = [...nextPage.sections];
                const [moved] = sections.splice(currentIndex, 1);
                const targetIndex = Math.max(0, Math.min(patch.toIndex, sections.length));
                sections.splice(targetIndex, 0, moved);
                nextPage = reindexGeneratedPageSections({
                    ...nextPage,
                    sections,
                });
                break;
            }
            case 'duplicate-section': {
                const current = nextPage.sections.find((section) => section.id === patch.sectionId || section.localId === patch.sectionId);
                if (!current) {
                    break;
                }

                const copy = createGeneratedSection({
                    ...current,
                    id: `${page.id}:${patch.newSectionId}`,
                    localId: patch.newSectionId,
                    components: current.components.map((component, index) => createGeneratedComponentInstance({
                        ...component,
                        id: `${page.id}:${patch.newSectionId}:component:${index + 1}`,
                        sectionId: `${page.id}:${patch.newSectionId}`,
                    })),
                });
                const sections = [...nextPage.sections];
                const insertIndex = sections.findIndex((section) => section.id === current.id);
                sections.splice(insertIndex + 1, 0, copy);
                nextPage = reindexGeneratedPageSections({
                    ...nextPage,
                    sections,
                });
                break;
            }
            case 'unsupported-builder-operation':
            default:
                break;
        }
    });

    return nextPage;
}

function collectPatchSectionMetadata(
    pageGraph: GeneratedPage,
    patches: ProjectGraphPatchInstruction[],
): {
    sectionLocalIds: string[];
    componentKeys: string[];
} {
    const sectionLocalIds = new Set<string>();
    const componentKeys = new Set<string>();

    const includeSection = (identifier: string | null | undefined) => {
        if (!identifier) {
            return;
        }

        const section = pageGraph.sections.find((candidate) => (
            candidate.id === identifier || candidate.localId === identifier
        ));
        if (!section) {
            return;
        }

        sectionLocalIds.add(section.localId);
        const componentKey = normalizeText(section.registryKey ?? section.components[0]?.registryKey ?? section.components[0]?.key);
        if (componentKey) {
            componentKeys.add(componentKey);
        }
    };

    patches.forEach((patch) => {
        switch (patch.kind) {
            case 'insert-section':
                sectionLocalIds.add(patch.section.localId);
                if (patch.section.registryKey) {
                    componentKeys.add(patch.section.registryKey);
                }
                break;
            case 'update-section-props':
            case 'unset-section-props':
            case 'delete-section':
            case 'move-section':
            case 'duplicate-section':
                includeSection(patch.sectionId);
                break;
            default:
                break;
        }
    });

    return {
        sectionLocalIds: Array.from(sectionLocalIds),
        componentKeys: Array.from(componentKeys),
    };
}

export function resolveAffectedWorkspacePaths(
    manifest: WorkspaceManifest,
    projectionMetadata: WorkspaceProjectionMetadata,
    pageGraph: GeneratedPage,
    patches: ProjectGraphPatchInstruction[],
): string[] {
    const resolvedFiles = resolveProjectionPageFiles(projectionMetadata, {
        id: Number.isFinite(Number(pageGraph.id)) ? Number(pageGraph.id) : null,
        slug: pageGraph.slug,
        title: pageGraph.title,
    });
    const affectedPaths = new Set<string>([resolvedFiles.pagePath]);

    const includeLayoutFiles = () => {
        resolvedFiles.layoutFiles.forEach((path) => affectedPaths.add(path));
    };

    patches.forEach((patch) => {
        switch (patch.kind) {
            case 'move-section':
                affectedPaths.add(resolvedFiles.pagePath);
                break;
            case 'insert-section':
                affectedPaths.add(resolvedFiles.pagePath);
                if (patch.section.kind === 'header' || patch.section.kind === 'footer') {
                    includeLayoutFiles();
                    break;
                }
                {
                    const sectionPath = resolvedFiles.sectionFileByLocalId.get(patch.section.localId)
                        ?? buildSectionComponentPath(patch.section.registryKey)
                        ?? null;
                    if (sectionPath) {
                        affectedPaths.add(sectionPath);
                    }
                }
                break;
            case 'delete-section':
            case 'update-section-props':
            case 'unset-section-props':
            case 'duplicate-section': {
                affectedPaths.add(resolvedFiles.pagePath);
                const section = pageGraph.sections.find((candidate) => candidate.id === patch.sectionId || candidate.localId === patch.sectionId);
                const sectionKind = section?.kind ?? 'content';

                if (sectionKind === 'header' || sectionKind === 'footer') {
                    includeLayoutFiles();
                    break;
                }

                const sectionLocalId = section?.localId ?? (patch.kind === 'duplicate-section' ? patch.newSectionId : null);
                const sectionPath = (sectionLocalId ? resolvedFiles.sectionFileByLocalId.get(sectionLocalId) : null)
                    ?? buildSectionComponentPath(section?.registryKey ?? null)
                    ?? null;
                if (sectionPath) {
                    affectedPaths.add(sectionPath);
                } else {
                    resolvedFiles.sectionFiles.forEach((path) => affectedPaths.add(path));
                }
                break;
            }
            case 'unsupported-builder-operation':
            default:
                break;
        }
    });

    if (manifest.fileOwnership.length === 0 && affectedPaths.size === 1) {
        resolvedFiles.sectionFiles.forEach((path) => affectedPaths.add(path));
        resolvedFiles.layoutFiles.forEach((path) => affectedPaths.add(path));
    }

    return Array.from(affectedPaths).sort((left, right) => left.localeCompare(right));
}

export function applyWorkspaceBackedBuilderMutations(
    options: ApplyWorkspaceBackedBuilderMutationsOptions,
): WorkspaceBackedBuilderMutationResult {
    const editor = inferEditorFromOperations(options.operations);
    const graph = buildGeneratedProjectGraphForCurrentPage({
        projectId: options.projectId,
        page: options.page,
        sectionsDraft: options.sectionsDraft,
        projectionMetadata: options.projectionMetadata,
        layoutOverrides: options.layoutOverrides,
        editor,
    });
    const pageGraph = graph.pages[0]!;
    const graphPatches = buildProjectGraphPatchFromBuilderOperations({
        id: pageGraph.id,
        slug: pageGraph.slug,
    }, options.operations);
    const dirtyPaths = resolveAffectedWorkspacePaths(
        options.manifest,
        options.projectionMetadata,
        pageGraph,
        graphPatches,
    );
    const preparedExecution = prepareVisualBuilderCmsEditExecution({
        page: {
            id: pageGraph.id,
            slug: pageGraph.slug,
            title: pageGraph.title,
        },
        model: buildBuilderPageModelFromSectionDrafts(options.sectionsDraft, {
            editorMode: 'builder',
            textEditorHtml: '',
            extraContent: {},
        }),
        contentJson: { sections: [] },
        operations: options.operations,
        generatedPage: pageGraph,
        manifest: ensureManifestOwnershipEntries(
            options.manifest,
            buildCurrentPageManifestEntries(options.manifest, pageGraph, options.projectionMetadata),
        ),
        dirtyPaths,
        editor: editor === 'ai' ? 'ai' : 'visual_builder',
        createdBy: editor === 'ai' ? 'ai' : 'visual_builder',
    });
    const preparedPageGraph = preparedExecution.generatedPage ?? pageGraph;
    const nextManifestBase = preparedExecution.manifest ?? options.manifest;
    const patchMetadata = collectPatchSectionMetadata(preparedPageGraph, graphPatches);
    const nextManifest = applyManifestEditorMetadata(nextManifestBase, {
        paths: dirtyPaths,
        editor,
        updatedAt: new Date().toISOString(),
        dirty: true,
        pageId: preparedPageGraph.id,
        pageSlug: preparedPageGraph.slug,
        sectionLocalIds: patchMetadata.sectionLocalIds,
        componentKeys: patchMetadata.componentKeys,
    });

    return {
        editor,
        graphPatches,
        dirtyPaths,
        pageGraph: preparedPageGraph,
        manifest: nextManifest,
    };
}

function buildPersistedManifestForCurrentPage(
    currentState: WorkspaceBackedBuilderState,
    nextPageGraph: GeneratedPage,
    layoutOverrides: WorkspaceBackedBuilderLayoutOverrides | undefined,
): WorkspaceManifest {
    const nextGraph = createGeneratedProjectGraph({
        projectId: currentState.manifest.projectId,
        name: nextPageGraph.title,
        pages: [nextPageGraph],
        layouts: [
            createGeneratedLayout({
                id: 'site-layout',
                key: 'site-layout',
                name: 'Site Layout',
                kind: 'site',
                filePath: 'src/layouts/SiteLayout.tsx',
                props: {
                    headerVariant: layoutOverrides?.headerVariant ?? null,
                    footerVariant: layoutOverrides?.footerVariant ?? null,
                },
                slots: ['header', 'content', 'footer'],
                sectionIds: nextPageGraph.sections.map((section) => section.id),
            }),
        ],
        routes: [buildDefaultProjectGraphPageRoute(nextPageGraph)],
        files: currentState.currentProjectGraph?.files ?? [],
        generation: currentState.currentProjectGraph?.generation,
    });
    const graphManifest = buildWorkspaceManifestFromProjectGraph(nextGraph, {
        manifestPath: WORKSPACE_MANIFEST_RELATIVE_PATH,
        updatedAt: new Date().toISOString(),
    });
    const currentPageEntry = graphManifest.generatedPages[0] ?? null;
    const otherPages = currentState.manifest.generatedPages.filter((entry) => entry.pageId !== nextPageGraph.id);

    return {
        ...ensureManifestOwnershipEntries(currentState.manifest, graphManifest.fileOwnership),
        manifestPath: WORKSPACE_MANIFEST_RELATIVE_PATH,
        updatedAt: graphManifest.updatedAt,
        generatedPages: currentPageEntry ? [...otherPages, currentPageEntry] : otherPages,
    };
}

function detectNotFoundError(error: unknown): boolean {
    if (!error || typeof error !== 'object') {
        return false;
    }

    const status = (error as { response?: { status?: number } }).response?.status;
    return status === 404;
}

export function useWorkspaceBackedBuilderAdapter({
    enabled = true,
    projectId,
    projectName,
    currentPage,
    currentRevisionCursor,
    getCurrentSectionsDraft,
    getCurrentLayoutOverrides,
    onPreviewRefresh,
}: UseWorkspaceBackedBuilderAdapterOptions) {
    const stateRef = useRef<WorkspaceBackedBuilderState>({
        loadedPageKey: null,
        manifest: normalizeWorkspaceManifest({
            projectId,
            manifestPath: WORKSPACE_MANIFEST_RELATIVE_PATH,
        }),
        projectionMetadata: createEmptyProjectionMetadata(),
        currentPageGraph: null,
        currentProjectGraph: null,
        pendingGraphPatches: [],
        dirtyPaths: [],
        lastEditor: null,
        revisionCursor: currentRevisionCursor,
        lastPersistedSignature: null,
    });

    const loadWorkspaceManifest = useCallback(async (): Promise<WorkspaceManifest> => {
        if (!enabled) {
            return normalizeWorkspaceManifest({
                projectId,
                manifestPath: WORKSPACE_MANIFEST_RELATIVE_PATH,
            });
        }

        try {
            const response = await axios.get<{ success?: boolean; content?: string }>(
                `/panel/projects/${projectId}/workspace/file`,
                {
                    params: {
                        path: WORKSPACE_MANIFEST_RELATIVE_PATH,
                    },
                },
            );
            const rawContent = typeof response.data?.content === 'string' ? response.data.content : '';
            const decoded = rawContent.trim() === '' ? null : JSON.parse(rawContent) as Partial<WorkspaceManifest>;
            return normalizeWorkspaceManifest(decoded);
        } catch (error) {
            if (detectNotFoundError(error)) {
                return normalizeWorkspaceManifest({
                    projectId,
                    manifestPath: WORKSPACE_MANIFEST_RELATIVE_PATH,
                });
            }

            throw error;
        }
    }, [enabled, projectId]);

    const loadWorkspaceProjectionMetadata = useCallback(async (): Promise<WorkspaceProjectionMetadata> => {
        if (!enabled) {
            return createEmptyProjectionMetadata();
        }

        try {
            const response = await axios.get<{ success?: boolean; structure?: { projection_metadata?: unknown } }>(
                `/panel/projects/${projectId}/workspace/structure`,
            );

            return normalizeProjectionMetadata(response.data?.structure?.projection_metadata);
        } catch {
            return createEmptyProjectionMetadata();
        }
    }, [enabled, projectId]);

    const syncFromBuilderDrafts = useCallback((options?: {
        editor?: WorkspaceEditorSource | null;
        sectionDrafts?: SectionDraft[];
    }): WorkspaceBackedBuilderState => {
        const currentSections = options?.sectionDrafts ?? getCurrentSectionsDraft();
        const editor = options?.editor ?? stateRef.current.lastEditor ?? 'visual_builder';
        const projectionMetadata = stateRef.current.projectionMetadata;
        const nextGraph = buildGeneratedProjectGraphForCurrentPage({
            projectId,
            page: currentPage,
            sectionsDraft: currentSections,
            projectionMetadata,
            layoutOverrides: getCurrentLayoutOverrides?.(),
            editor,
        });
        const pageGraph = nextGraph.pages[0] ?? null;
        const nextManifest = pageGraph
            ? (prepareVisualBuilderCmsEditExecution({
                page: {
                    id: pageGraph.id,
                    slug: pageGraph.slug,
                    title: pageGraph.title,
                },
                model: buildBuilderPageModelFromSectionDrafts(currentSections, {
                    editorMode: 'builder',
                    textEditorHtml: '',
                    extraContent: {},
                }),
                contentJson: { sections: [] },
                operations: [],
                generatedPage: pageGraph,
                manifest: ensureManifestOwnershipEntries(
                    stateRef.current.manifest,
                    buildCurrentPageManifestEntries(stateRef.current.manifest, pageGraph, projectionMetadata),
                ),
                dirtyPaths: stateRef.current.dirtyPaths,
                editor: editor === 'ai' ? 'ai' : 'visual_builder',
                createdBy: editor === 'ai' ? 'ai' : 'visual_builder',
            }).manifest ?? stateRef.current.manifest)
            : stateRef.current.manifest;

        stateRef.current = {
            ...stateRef.current,
            manifest: nextManifest,
            currentProjectGraph: nextGraph,
            currentPageGraph: pageGraph,
            lastEditor: editor,
            revisionCursor: currentRevisionCursor,
        };

        return stateRef.current;
    }, [currentPage, currentRevisionCursor, getCurrentLayoutOverrides, getCurrentSectionsDraft, projectId]);

    const loadWorkspaceBackedState = useCallback(async (): Promise<WorkspaceBackedBuilderState> => {
        const pageKey = `${projectId}:${normalizePageId(currentPage)}:${normalizePageSlug(currentPage)}`;
        const [manifest, projectionMetadata] = await Promise.all([
            loadWorkspaceManifest(),
            loadWorkspaceProjectionMetadata(),
        ]);

        stateRef.current = {
            ...stateRef.current,
            loadedPageKey: pageKey,
            manifest,
            projectionMetadata,
            revisionCursor: currentRevisionCursor,
        };

        const syncedState = syncFromBuilderDrafts({
            editor: stateRef.current.lastEditor,
        });
        const currentSignature = buildWorkspaceBackedPageSignature({
            page: currentPage,
            sectionsDraft: getCurrentSectionsDraft(),
            layoutOverrides: getCurrentLayoutOverrides?.(),
        });

        stateRef.current = {
            ...syncedState,
            lastPersistedSignature: currentSignature,
        };

        return stateRef.current;
    }, [currentPage, currentRevisionCursor, loadWorkspaceManifest, loadWorkspaceProjectionMetadata, projectId, syncFromBuilderDrafts]);

    useEffect(() => {
        if (!enabled) {
            return;
        }

        if (currentPage.id === null && normalizeText(currentPage.slug) === '') {
            return;
        }

        void loadWorkspaceBackedState();
    }, [currentPage.id, currentPage.slug, enabled, loadWorkspaceBackedState]);

    const recordBuilderOperations = useCallback((
        operations: BuilderUpdateOperation[],
        options?: {
            sectionDrafts?: SectionDraft[];
        },
    ): WorkspaceBackedBuilderMutationResult | null => {
        if (currentPage.id === null && normalizeText(currentPage.slug) === '') {
            return null;
        }

        const currentSections = options?.sectionDrafts ?? getCurrentSectionsDraft();
        const baseManifest = stateRef.current.manifest;
        const result = applyWorkspaceBackedBuilderMutations({
            projectId,
            page: currentPage,
            sectionsDraft: currentSections,
            projectionMetadata: stateRef.current.projectionMetadata,
            layoutOverrides: getCurrentLayoutOverrides?.(),
            manifest: baseManifest,
            operations,
        });

        stateRef.current = {
            ...stateRef.current,
            manifest: result.manifest,
            currentProjectGraph: createGeneratedProjectGraph({
                projectId,
                name: projectName,
                pages: [result.pageGraph],
                generation: stateRef.current.currentProjectGraph?.generation,
                files: stateRef.current.currentProjectGraph?.files ?? [],
            }),
            currentPageGraph: result.pageGraph,
            pendingGraphPatches: [
                ...stateRef.current.pendingGraphPatches,
                ...result.graphPatches,
            ],
            dirtyPaths: Array.from(new Set([...stateRef.current.dirtyPaths, ...result.dirtyPaths])),
            lastEditor: result.editor,
            revisionCursor: currentRevisionCursor,
        };

        return result;
    }, [currentPage, currentRevisionCursor, getCurrentLayoutOverrides, getCurrentSectionsDraft, projectId, projectName]);

    const recordLayoutOverrideChange = useCallback((editor: WorkspaceEditorSource = 'visual_builder') => {
        const synced = syncFromBuilderDrafts({
            editor,
        });

        if (!synced.currentPageGraph) {
            return synced;
        }

        const layoutFiles = resolveProjectionPageFiles(
            synced.projectionMetadata,
            currentPage,
        ).layoutFiles;
        const componentKeys = ['webu_header_01', 'webu_footer_01'];
        const nextManifest = applyManifestEditorMetadata(synced.manifest, {
            paths: layoutFiles,
            editor,
            updatedAt: new Date().toISOString(),
            dirty: true,
            pageId: synced.currentPageGraph.id,
            pageSlug: synced.currentPageGraph.slug,
            sectionLocalIds: synced.currentPageGraph.sections
                .filter((section) => section.kind === 'header' || section.kind === 'footer')
                .map((section) => section.localId),
            componentKeys,
        });

        stateRef.current = {
            ...synced,
            manifest: nextManifest,
            dirtyPaths: Array.from(new Set([...synced.dirtyPaths, ...layoutFiles])),
            lastEditor: editor,
        };

        return stateRef.current;
    }, [currentPage, syncFromBuilderDrafts]);

    const persistWorkspaceState = useCallback(async (options?: {
        revisionId?: number | null;
        revisionVersion?: number | null;
        regenerateWorkspace?: boolean;
    }): Promise<WorkspaceBackedBuilderPersistResult> => {
        const synced = syncFromBuilderDrafts();
        if (!synced.currentPageGraph) {
            return {
                success: false,
                manifest: null,
                dirtyPaths: [],
                errorMessage: 'No active page graph.',
            };
        }

        if (!enabled) {
            stateRef.current = {
                ...synced,
                pendingGraphPatches: [],
                dirtyPaths: [],
                revisionCursor: {
                    revisionId: options?.revisionId ?? synced.revisionCursor.revisionId,
                    revisionVersion: options?.revisionVersion ?? synced.revisionCursor.revisionVersion,
                },
                lastPersistedSignature: buildWorkspaceBackedPageSignature({
                    page: currentPage,
                    sectionsDraft: getCurrentSectionsDraft(),
                    layoutOverrides: getCurrentLayoutOverrides?.(),
                }),
            };

            return {
                success: true,
                manifest: synced.manifest,
                dirtyPaths: [],
                errorMessage: null,
            };
        }

        const currentSignature = buildWorkspaceBackedPageSignature({
            page: currentPage,
            sectionsDraft: getCurrentSectionsDraft(),
            layoutOverrides: getCurrentLayoutOverrides?.(),
        });
        const fallbackDirtyPaths = buildCurrentPageManifestEntries(
            synced.manifest,
            synced.currentPageGraph,
            synced.projectionMetadata,
        ).map((entry) => entry.path);
        const dirtyPaths = synced.dirtyPaths.length > 0
            ? synced.dirtyPaths
            : (
                synced.lastPersistedSignature !== null
                && synced.lastPersistedSignature !== currentSignature
            )
                ? fallbackDirtyPaths
                : [];

        try {
            if ((options?.regenerateWorkspace ?? true) && dirtyPaths.length > 0) {
                await axios.post(`/panel/projects/${projectId}/workspace/regenerate`, {
                    workspace_context: {
                        actor: 'visual_builder',
                        source: 'workspace_backed_builder_adapter',
                        operation_kind: 'apply_patch_set',
                        preview_refresh_requested: true,
                        reason: 'visual_builder_sync',
                        touched_paths: dirtyPaths,
                    },
                });
            }

            const refreshedManifest = dirtyPaths.length > 0
                ? await loadWorkspaceManifest()
                : synced.manifest;
            const nowIso = new Date().toISOString();
            const syntheticOperationId = `visual-builder:${projectId}:${Date.now()}`;
            let finalManifest = buildPersistedManifestForCurrentPage(
                {
                    ...synced,
                    manifest: refreshedManifest,
                },
                synced.currentPageGraph,
                getCurrentLayoutOverrides?.(),
            );
            finalManifest = {
                ...finalManifest,
                activeGenerationRunId: finalManifest.activeGenerationRunId,
                updatedAt: nowIso,
                fileOwnership: finalManifest.fileOwnership.map((entry) => (
                    dirtyPaths.includes(entry.path)
                        ? {
                            ...entry,
                            dirty: false,
                            lastEditor: synced.lastEditor ?? 'visual_builder',
                            lastOperationId: entry.lastOperationId ?? syntheticOperationId,
                            lastOperationKind: entry.lastOperationKind ?? 'apply_patch_set',
                            updatedAt: nowIso,
                        }
                        : entry
                )),
            };

            await axios.post(`/panel/projects/${projectId}/workspace/file`, {
                path: WORKSPACE_MANIFEST_RELATIVE_PATH,
                content: JSON.stringify(finalManifest, null, 2),
                workspace_context: {
                    actor: 'visual_builder',
                    source: 'workspace_backed_builder_adapter',
                    operation_kind: 'update_file',
                    preview_refresh_requested: true,
                    reason: 'persist_workspace_manifest',
                },
            });

            stateRef.current = {
                ...synced,
                manifest: finalManifest,
                pendingGraphPatches: [],
                dirtyPaths: [],
                revisionCursor: {
                    revisionId: options?.revisionId ?? synced.revisionCursor.revisionId,
                    revisionVersion: options?.revisionVersion ?? synced.revisionCursor.revisionVersion,
                },
                lastPersistedSignature: currentSignature,
            };

            onPreviewRefresh?.();

            return {
                success: true,
                manifest: finalManifest,
                dirtyPaths: [],
                errorMessage: null,
            };
        } catch (error) {
            return {
                success: false,
                manifest: synced.manifest,
                dirtyPaths,
                errorMessage: error instanceof Error ? error.message : 'Workspace sync failed.',
            };
        }
    }, [currentPage, enabled, getCurrentLayoutOverrides, getCurrentSectionsDraft, loadWorkspaceManifest, onPreviewRefresh, projectId, syncFromBuilderDrafts]);

    const getCurrentState = useCallback(() => stateRef.current, []);

    return {
        stateRef,
        getCurrentState,
        loadWorkspaceBackedState,
        syncFromBuilderDrafts,
        recordBuilderOperations,
        recordLayoutOverrideChange,
        persistWorkspaceState,
    };
}
