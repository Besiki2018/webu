import type {
    GeneratedComponentInstance,
    GeneratedProjectGraph,
    WorkspaceManifest,
    WorkspaceManifestComponentProvenance,
    WorkspaceManifestFileOwnership,
    WorkspaceManifestPreviewBuildInfo,
    WorkspaceEditorSource,
} from './types';

export const WORKSPACE_MANIFEST_RELATIVE_PATH = '.webu/workspace-manifest.json';

function normalizeText(value: string | null | undefined): string | null {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

function uniqueBy<T>(items: T[], key: (item: T) => string): T[] {
    const seen = new Set<string>();
    const unique: T[] = [];

    items.forEach((item) => {
        const itemKey = key(item);
        if (itemKey === '' || seen.has(itemKey)) {
            return;
        }

        seen.add(itemKey);
        unique.push(item);
    });

    return unique;
}

function collectComponents(graph: GeneratedProjectGraph): GeneratedComponentInstance[] {
    return uniqueBy([
        ...graph.components,
        ...graph.pages.flatMap((page) => page.sections.flatMap((section) => section.components)),
    ], (component) => component.id);
}

export function buildWorkspaceManifestPreviewInfo(graph: Pick<GeneratedProjectGraph, 'generation'>): WorkspaceManifestPreviewBuildInfo {
    return {
        ready: graph.generation.phase === 'ready' && graph.generation.preview.ready === true,
        phase: graph.generation.phase,
        buildId: graph.generation.preview.buildId,
        previewUrl: graph.generation.preview.previewUrl,
        artifactHash: graph.generation.preview.artifactHash,
        workspaceHash: graph.generation.preview.workspaceHash,
        builtAt: graph.generation.preview.builtAt,
        errorMessage: graph.generation.preview.errorMessage ?? graph.generation.errorMessage,
    };
}

export function buildWorkspaceManifestFromProjectGraph(
    graph: GeneratedProjectGraph,
    input: {
        manifestPath?: string;
        updatedAt?: string | null;
    } = {},
): WorkspaceManifest {
    const components = collectComponents(graph);
    const fileOwnership: WorkspaceManifestFileOwnership[] = graph.files.map((file) => ({
        path: file.path,
        kind: file.kind,
        ownerType: file.ownerType,
        ownerId: file.ownerId,
        generatedBy: file.source,
        editState: file.editState,
        pageIds: [...file.pageIds],
        componentIds: [...file.componentIds],
        activeGenerationRunId: graph.generation.runId,
        checksum: file.checksum,
        sectionLocalIds: [],
        componentKeys: [],
        originatingPageId: file.pageIds[0] ?? null,
        originatingPageSlug: graph.pages.find((page) => page.id === (file.pageIds[0] ?? null))?.slug ?? null,
        lastEditor: file.source === 'ai' ? 'ai' : null,
        dirty: false,
        updatedAt: null,
        locked: false,
        templateOwned: false,
        lastOperationId: null,
        lastOperationKind: null,
        cmsBacked: false,
        contentOwner: null,
        cmsFieldPaths: [],
        visualFieldPaths: [],
        codeFieldPaths: [],
        syncDirection: null,
        conflictStatus: null,
    }));

    const componentProvenance: WorkspaceManifestComponentProvenance[] = components.map((component) => ({
        componentId: component.id,
        registryKey: component.registryKey,
        pageId: component.pageId,
        sectionId: component.sectionId,
        source: component.provenance.source,
        filePaths: component.sourceFilePath ? [component.sourceFilePath] : [],
        runId: component.provenance.runId,
        lastEditor: component.provenance.source === 'ai' ? 'ai' : null,
    }));

    return {
        schemaVersion: 1,
        projectId: graph.projectId,
        rootDir: graph.rootDir,
        manifestPath: input.manifestPath ?? WORKSPACE_MANIFEST_RELATIVE_PATH,
        activeGenerationRunId: graph.generation.runId,
        generatedPages: graph.pages.map((page) => ({
            pageId: page.id,
            slug: page.slug,
            title: page.title,
            routePath: graph.routes.find((route) => route.pageId === page.id)?.path ?? (page.slug === 'home' ? '/' : `/${page.slug}`),
            entryFilePath: page.entryFilePath,
            layoutId: page.layoutId,
            sectionIds: page.sections.map((section) => section.id),
            cmsBacked: page.cmsBacked,
            contentOwner: page.contentOwner,
            cmsFieldPaths: [...page.cmsFieldPaths],
            visualFieldPaths: [...page.visualFieldPaths],
            codeFieldPaths: [...page.codeFieldPaths],
            syncDirection: page.syncDirection,
            conflictStatus: page.conflictStatus,
        })),
        fileOwnership,
        componentProvenance,
        preview: buildWorkspaceManifestPreviewInfo(graph),
        cmsBinding: isRecordLike(graph.metadata.webu_cms_binding)
            ? graph.metadata.webu_cms_binding as Record<string, unknown>
            : null,
        updatedAt: input.updatedAt ?? null,
    };
}

function isRecordLike(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

export function normalizeWorkspaceManifest(input: Partial<WorkspaceManifest> | null | undefined): WorkspaceManifest {
    return {
        schemaVersion: 1,
        projectId: normalizeText(input?.projectId) ?? null,
        rootDir: normalizeText(input?.rootDir) ?? null,
        manifestPath: normalizeText(input?.manifestPath) ?? WORKSPACE_MANIFEST_RELATIVE_PATH,
        activeGenerationRunId: normalizeText(input?.activeGenerationRunId) ?? null,
        generatedPages: Array.isArray(input?.generatedPages)
            ? input.generatedPages.map((entry) => ({
                ...entry,
                cmsBacked: entry.cmsBacked === true,
                contentOwner: entry.contentOwner ?? null,
                cmsFieldPaths: Array.isArray(entry.cmsFieldPaths) ? [...entry.cmsFieldPaths] : [],
                visualFieldPaths: Array.isArray(entry.visualFieldPaths) ? [...entry.visualFieldPaths] : [],
                codeFieldPaths: Array.isArray(entry.codeFieldPaths) ? [...entry.codeFieldPaths] : [],
                syncDirection: entry.syncDirection ?? null,
                conflictStatus: entry.conflictStatus ?? null,
            }))
            : [],
        fileOwnership: Array.isArray(input?.fileOwnership)
            ? input.fileOwnership.map((entry) => ({
                ...entry,
                sectionLocalIds: Array.isArray(entry.sectionLocalIds) ? [...entry.sectionLocalIds] : [],
                componentKeys: Array.isArray(entry.componentKeys) ? [...entry.componentKeys] : [],
                originatingPageId: entry.originatingPageId ?? null,
                originatingPageSlug: entry.originatingPageSlug ?? null,
                lastEditor: entry.lastEditor ?? null,
                dirty: entry.dirty === true,
                updatedAt: entry.updatedAt ?? null,
                locked: entry.locked === true,
                templateOwned: entry.templateOwned === true,
                lastOperationId: normalizeText(entry.lastOperationId) ?? null,
                lastOperationKind: normalizeText(entry.lastOperationKind) ?? null,
                cmsBacked: entry.cmsBacked === true,
                contentOwner: entry.contentOwner ?? null,
                cmsFieldPaths: Array.isArray(entry.cmsFieldPaths) ? [...entry.cmsFieldPaths] : [],
                visualFieldPaths: Array.isArray(entry.visualFieldPaths) ? [...entry.visualFieldPaths] : [],
                codeFieldPaths: Array.isArray(entry.codeFieldPaths) ? [...entry.codeFieldPaths] : [],
                syncDirection: entry.syncDirection ?? null,
                conflictStatus: entry.conflictStatus ?? null,
            }))
            : [],
        componentProvenance: Array.isArray(input?.componentProvenance)
            ? input.componentProvenance.map((entry) => ({
                ...entry,
                lastEditor: entry.lastEditor ?? null,
            }))
            : [],
        preview: {
            ready: input?.preview?.ready === true,
            phase: input?.preview?.phase ?? 'idle',
            buildId: normalizeText(input?.preview?.buildId) ?? null,
            previewUrl: normalizeText(input?.preview?.previewUrl) ?? null,
            artifactHash: normalizeText(input?.preview?.artifactHash) ?? null,
            workspaceHash: normalizeText(input?.preview?.workspaceHash) ?? null,
            builtAt: normalizeText(input?.preview?.builtAt) ?? null,
            errorMessage: normalizeText(input?.preview?.errorMessage) ?? null,
        },
        cmsBinding: isRecordLike(input?.cmsBinding) ? { ...input?.cmsBinding } : null,
        updatedAt: normalizeText(input?.updatedAt) ?? null,
    };
}

export function markManifestFileUserEdited(manifest: WorkspaceManifest, path: string): WorkspaceManifest {
    return {
        ...manifest,
        fileOwnership: manifest.fileOwnership.map((entry) => (
            entry.path === path
                ? { ...entry, editState: 'user-edited', lastEditor: 'visual_builder', dirty: true }
                : entry
        )),
    };
}

export function isWorkspacePreviewReady(manifest: Pick<WorkspaceManifest, 'preview'>): boolean {
    return manifest.preview.ready === true && manifest.preview.phase === 'ready';
}

export function applyManifestEditorMetadata(
    manifest: WorkspaceManifest,
    input: {
        paths: string[];
        editor: WorkspaceEditorSource;
        updatedAt: string | null;
        dirty?: boolean;
        pageId?: string | null;
        pageSlug?: string | null;
        sectionLocalIds?: string[];
        componentKeys?: string[];
    },
): WorkspaceManifest {
    const pathSet = new Set(input.paths);
    const sectionLocalIds = input.sectionLocalIds ?? [];
    const componentKeys = input.componentKeys ?? [];

    return {
        ...manifest,
        fileOwnership: manifest.fileOwnership.map((entry) => {
            if (!pathSet.has(entry.path)) {
                return entry;
            }

            return {
                ...entry,
                dirty: input.dirty ?? true,
                lastEditor: input.editor,
                updatedAt: input.updatedAt,
                originatingPageId: input.pageId ?? entry.originatingPageId,
                originatingPageSlug: input.pageSlug ?? entry.originatingPageSlug,
                sectionLocalIds: Array.from(new Set([...entry.sectionLocalIds, ...sectionLocalIds])),
                componentKeys: Array.from(new Set([...entry.componentKeys, ...componentKeys])),
                lastOperationKind: entry.lastOperationKind ?? 'apply_patch_set',
            };
        }),
        componentProvenance: manifest.componentProvenance.map((entry) => (
            componentKeys.includes(entry.registryKey ?? '')
                ? {
                    ...entry,
                    lastEditor: input.editor,
                }
                : entry
        )),
    };
}

export function ensureManifestOwnershipEntries(
    manifest: WorkspaceManifest,
    entries: WorkspaceManifestFileOwnership[],
): WorkspaceManifest {
    const existing = new Map(manifest.fileOwnership.map((entry) => [entry.path, entry]));

    entries.forEach((entry) => {
        const prior = existing.get(entry.path);
        existing.set(entry.path, {
            ...prior,
            ...entry,
            pageIds: entry.pageIds.length > 0
                ? [...entry.pageIds]
                : [...(prior?.pageIds ?? [])],
            componentIds: entry.componentIds.length > 0
                ? [...entry.componentIds]
                : [...(prior?.componentIds ?? [])],
            sectionLocalIds: Array.from(new Set([
                ...(prior?.sectionLocalIds ?? []),
                ...entry.sectionLocalIds,
            ])),
            componentKeys: Array.from(new Set([
                ...(prior?.componentKeys ?? []),
                ...entry.componentKeys,
            ])),
            originatingPageId: entry.originatingPageId ?? prior?.originatingPageId ?? null,
            originatingPageSlug: entry.originatingPageSlug ?? prior?.originatingPageSlug ?? null,
            lastEditor: entry.lastEditor ?? prior?.lastEditor ?? null,
            dirty: entry.dirty ?? prior?.dirty ?? false,
            updatedAt: entry.updatedAt ?? prior?.updatedAt ?? null,
            locked: entry.locked ?? prior?.locked ?? false,
            templateOwned: entry.templateOwned ?? prior?.templateOwned ?? false,
            lastOperationId: entry.lastOperationId ?? prior?.lastOperationId ?? null,
            lastOperationKind: entry.lastOperationKind ?? prior?.lastOperationKind ?? null,
            cmsBacked: entry.cmsBacked ?? prior?.cmsBacked ?? false,
            contentOwner: entry.contentOwner ?? prior?.contentOwner ?? null,
            cmsFieldPaths: Array.from(new Set([
                ...(prior?.cmsFieldPaths ?? []),
                ...(entry.cmsFieldPaths ?? []),
            ])),
            visualFieldPaths: Array.from(new Set([
                ...(prior?.visualFieldPaths ?? []),
                ...(entry.visualFieldPaths ?? []),
            ])),
            codeFieldPaths: Array.from(new Set([
                ...(prior?.codeFieldPaths ?? []),
                ...(entry.codeFieldPaths ?? []),
            ])),
            syncDirection: entry.syncDirection ?? prior?.syncDirection ?? null,
            conflictStatus: entry.conflictStatus ?? prior?.conflictStatus ?? null,
        });
    });

    return {
        ...manifest,
        fileOwnership: Array.from(existing.values()).sort((left, right) => left.path.localeCompare(right.path)),
    };
}
