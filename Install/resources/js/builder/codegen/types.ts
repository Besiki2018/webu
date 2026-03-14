export type GenerationPhase =
    | 'idle'
    | 'planning'
    | 'scaffolding'
    | 'writing_files'
    | 'installing'
    | 'building_preview'
    | 'ready'
    | 'failed';

export type GeneratedSectionKind = 'header' | 'hero' | 'features' | 'cta' | 'footer' | 'content' | 'custom';
export type GeneratedAssetKind = 'image' | 'video' | 'font' | 'icon' | 'document' | 'stylesheet' | 'other';
export type GeneratedFileKind = 'page' | 'layout' | 'section' | 'component' | 'asset' | 'route' | 'style' | 'config' | 'manifest' | 'other';
export type GeneratedFileLanguage = 'ts' | 'tsx' | 'js' | 'jsx' | 'json' | 'css' | 'md' | 'text' | 'other';
export type GeneratedFileSource = 'ai' | 'template' | 'system' | 'user';
export type GeneratedFileEditState = 'ai-generated' | 'user-edited' | 'mixed';
export type GeneratedFileOwnerType = 'project' | 'page' | 'layout' | 'section' | 'component' | 'asset' | 'route';
export type GeneratedDependencyKind = 'runtime' | 'development' | 'system';
export type GeneratedLayoutKind = 'site' | 'page' | 'custom';
export type GeneratedComponentKind = 'layout' | 'section' | 'content' | 'media' | 'navigation' | 'form' | 'custom';
export type PreviewBuildStatus = 'blocked' | 'pending' | 'ready' | 'failed';
export type WorkspaceEditorSource = 'ai' | 'visual_builder' | 'user' | 'system';
export type GeneratedContentOwner = 'cms' | 'builder_structure' | 'code' | 'mixed';
export type GeneratedContentSyncDirection = 'cms_to_workspace' | 'workspace_to_cms' | 'bidirectional' | 'none';
export type GeneratedContentConflictStatus = 'clean' | 'needs_workspace_sync' | 'needs_cms_sync' | 'requires_manual_merge';

export interface ComponentProvenance {
    source: GeneratedFileSource;
    runId: string | null;
    promptFingerprint: string | null;
    templateId: string | null;
    notes: string[];
}

export interface PreviewBuildState {
    status: PreviewBuildStatus;
    ready: boolean;
    buildId: string | null;
    previewUrl: string | null;
    artifactHash: string | null;
    workspaceHash: string | null;
    builtAt: string | null;
    errorMessage: string | null;
}

export interface GenerationRunState {
    runId: string | null;
    phase: GenerationPhase;
    message: string | null;
    errorMessage: string | null;
    startedAt: string | null;
    updatedAt: string | null;
    completedAt: string | null;
    failedAt: string | null;
    preview: PreviewBuildState;
}

export interface GeneratedDependency {
    name: string;
    version: string;
    kind: GeneratedDependencyKind;
    manager: 'npm' | 'pnpm' | 'yarn' | 'system';
    requestedBy: GeneratedFileSource;
}

export interface GeneratedComponentInstance {
    id: string;
    key: string;
    registryKey: string | null;
    displayName: string;
    kind: GeneratedComponentKind;
    props: Record<string, unknown>;
    children: GeneratedComponentInstance[];
    parentId: string | null;
    pageId: string | null;
    sectionId: string | null;
    sourceFilePath: string | null;
    editable: boolean;
    provenance: ComponentProvenance;
    metadata: Record<string, unknown>;
}

export interface GeneratedSection {
    id: string;
    localId: string;
    kind: GeneratedSectionKind;
    registryKey: string | null;
    label: string | null;
    order: number;
    props: Record<string, unknown>;
    components: GeneratedComponentInstance[];
    sourceFilePath: string | null;
    cmsBacked: boolean;
    contentOwner: GeneratedContentOwner | null;
    cmsFieldPaths: string[];
    visualFieldPaths: string[];
    codeFieldPaths: string[];
    syncDirection: GeneratedContentSyncDirection | null;
    conflictStatus: GeneratedContentConflictStatus | null;
    metadata: Record<string, unknown>;
}

export interface GeneratedLayout {
    id: string;
    key: string;
    name: string;
    kind: GeneratedLayoutKind;
    filePath: string | null;
    props: Record<string, unknown>;
    slots: string[];
    sectionIds: string[];
    metadata: Record<string, unknown>;
}

export interface GeneratedRoute {
    id: string;
    path: string;
    pageId: string;
    layoutId: string | null;
    entryFilePath: string | null;
    isIndex: boolean;
    metadata: Record<string, unknown>;
}

export interface GeneratedPage {
    id: string;
    slug: string;
    title: string;
    routeId: string | null;
    layoutId: string | null;
    entryFilePath: string | null;
    sections: GeneratedSection[];
    cmsBacked: boolean;
    contentOwner: GeneratedContentOwner | null;
    cmsFieldPaths: string[];
    visualFieldPaths: string[];
    codeFieldPaths: string[];
    syncDirection: GeneratedContentSyncDirection | null;
    conflictStatus: GeneratedContentConflictStatus | null;
    metadata: Record<string, unknown>;
}

export interface GeneratedAsset {
    id: string;
    kind: GeneratedAssetKind;
    path: string;
    publicPath: string | null;
    fileId: string | null;
    source: GeneratedFileSource;
    mimeType: string | null;
    metadata: Record<string, unknown>;
}

export interface GeneratedFile {
    id: string;
    path: string;
    kind: GeneratedFileKind;
    language: GeneratedFileLanguage;
    contents: string;
    checksum: string | null;
    source: GeneratedFileSource;
    editState: GeneratedFileEditState;
    ownerType: GeneratedFileOwnerType;
    ownerId: string | null;
    pageIds: string[];
    componentIds: string[];
    dependencies: string[];
    metadata: Record<string, unknown>;
}

export interface GeneratedProjectGraph {
    schemaVersion: 1;
    projectId: string | null;
    name: string;
    prompt: string | null;
    rootDir: string | null;
    pages: GeneratedPage[];
    layouts: GeneratedLayout[];
    routes: GeneratedRoute[];
    components: GeneratedComponentInstance[];
    assets: GeneratedAsset[];
    files: GeneratedFile[];
    dependencies: GeneratedDependency[];
    generation: GenerationRunState;
    metadata: Record<string, unknown>;
}

export interface WorkspaceManifestPageEntry {
    pageId: string;
    slug: string;
    title: string;
    routePath: string;
    entryFilePath: string | null;
    layoutId: string | null;
    sectionIds: string[];
    cmsBacked: boolean;
    contentOwner: GeneratedContentOwner | null;
    cmsFieldPaths: string[];
    visualFieldPaths: string[];
    codeFieldPaths: string[];
    syncDirection: GeneratedContentSyncDirection | null;
    conflictStatus: GeneratedContentConflictStatus | null;
}

export interface WorkspaceManifestFileOwnership {
    path: string;
    kind: GeneratedFileKind;
    ownerType: GeneratedFileOwnerType;
    ownerId: string | null;
    generatedBy: GeneratedFileSource;
    editState: GeneratedFileEditState;
    pageIds: string[];
    componentIds: string[];
    activeGenerationRunId: string | null;
    checksum: string | null;
    sectionLocalIds: string[];
    componentKeys: string[];
    originatingPageId: string | null;
    originatingPageSlug: string | null;
    lastEditor: WorkspaceEditorSource | null;
    dirty: boolean;
    updatedAt: string | null;
    locked: boolean;
    templateOwned: boolean;
    lastOperationId: string | null;
    lastOperationKind: string | null;
    cmsBacked: boolean;
    contentOwner: GeneratedContentOwner | null;
    cmsFieldPaths: string[];
    visualFieldPaths: string[];
    codeFieldPaths: string[];
    syncDirection: GeneratedContentSyncDirection | null;
    conflictStatus: GeneratedContentConflictStatus | null;
}

export interface WorkspaceManifestComponentProvenance {
    componentId: string;
    registryKey: string | null;
    pageId: string | null;
    sectionId: string | null;
    source: GeneratedFileSource;
    filePaths: string[];
    runId: string | null;
    lastEditor: WorkspaceEditorSource | null;
}

export interface WorkspaceManifestPreviewBuildInfo {
    ready: boolean;
    phase: GenerationPhase;
    buildId: string | null;
    previewUrl: string | null;
    artifactHash: string | null;
    workspaceHash: string | null;
    builtAt: string | null;
    errorMessage: string | null;
}

export interface WorkspaceManifest {
    schemaVersion: 1;
    projectId: string | null;
    rootDir: string | null;
    manifestPath: string;
    activeGenerationRunId: string | null;
    generatedPages: WorkspaceManifestPageEntry[];
    fileOwnership: WorkspaceManifestFileOwnership[];
    componentProvenance: WorkspaceManifestComponentProvenance[];
    preview: WorkspaceManifestPreviewBuildInfo;
    cmsBinding: Record<string, unknown> | null;
    updatedAt: string | null;
}

export interface GeneratedSectionInput {
    localId: string;
    kind: GeneratedSectionKind;
    registryKey: string | null;
    label: string | null;
    props: Record<string, unknown>;
}

export type ProjectGraphPatchInstruction =
    | {
        kind: 'insert-section';
        source: 'builder';
        pageId: string;
        pageSlug: string;
        afterSectionId?: string | null;
        insertIndex?: number | null;
        section: GeneratedSectionInput;
    }
    | {
        kind: 'update-section-props';
        source: 'builder';
        pageId: string;
        pageSlug: string;
        sectionId: string;
        propsPatch: Record<string, unknown>;
    }
    | {
        kind: 'unset-section-props';
        source: 'builder';
        pageId: string;
        pageSlug: string;
        sectionId: string;
        paths: string[];
    }
    | {
        kind: 'delete-section';
        source: 'builder';
        pageId: string;
        pageSlug: string;
        sectionId: string;
    }
    | {
        kind: 'move-section';
        source: 'builder';
        pageId: string;
        pageSlug: string;
        sectionId: string;
        toIndex: number;
    }
    | {
        kind: 'duplicate-section';
        source: 'builder';
        pageId: string;
        pageSlug: string;
        sectionId: string;
        newSectionId: string;
    }
    | {
        kind: 'unsupported-builder-operation';
        source: 'builder';
        pageId: string;
        pageSlug: string;
        operationKind: string;
        reason: string;
        metadata?: Record<string, unknown>;
    };
