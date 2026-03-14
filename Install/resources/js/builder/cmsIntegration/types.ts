export type CmsFieldOwner = 'cms' | 'builder_structure' | 'code' | 'mixed';
export type CmsBindingSyncDirection = 'cms_to_workspace' | 'workspace_to_cms' | 'bidirectional' | 'none';
export type CmsBindingConflictStatus = 'clean' | 'needs_workspace_sync' | 'needs_cms_sync' | 'requires_manual_merge';
export type CmsBindingProvenanceEditor = 'ai' | 'visual_builder' | 'cms' | 'code_mode' | 'system' | 'user';
export type CmsEditRoute = 'content_change' | 'structure_change' | 'code_change' | 'mixed_change';
export type CmsMediaFieldOwner = 'cms' | 'builder' | 'code';
export type CmsMediaUpdateSource = 'stock_image' | 'media_library' | 'upload' | 'manual' | 'remove';

export interface CmsBindingProvenance {
    createdBy: CmsBindingProvenanceEditor;
    lastEditor: CmsBindingProvenanceEditor;
    createdAt: string | null;
    updatedAt: string | null;
    generatedDefault: boolean;
    userCustomized: boolean;
    requiresManualMerge: boolean;
}

export interface CmsFieldBinding {
    key: string;
    contentKey: string;
    propPath: string;
    owner: CmsFieldOwner;
    persistenceLocation: string;
    syncDirection: CmsBindingSyncDirection;
    conflictStatus: CmsBindingConflictStatus;
    fieldType: string;
    sectionLocalId: string;
    componentKey: string | null;
    registryFieldPath: string | null;
    group: string | null;
    bindingCompatible: boolean;
    staticDefaultOnly: boolean;
    visualOnly: boolean;
    codeOwned: boolean;
    provenance: CmsBindingProvenance;
    exampleValue?: unknown;
}

export interface CmsBoundSection {
    sectionId: string | null;
    localId: string;
    type: string;
    registryKey: string | null;
    label: string | null;
    cmsBacked: boolean;
    contentOwner: CmsFieldOwner;
    syncDirection: CmsBindingSyncDirection;
    conflictStatus: CmsBindingConflictStatus;
    fieldBindings: CmsFieldBinding[];
    contentFieldPaths: string[];
    visualFieldPaths: string[];
    codeFieldPaths: string[];
    staticDefaultFieldPaths: string[];
    bindingMeta: Record<string, unknown>;
    provenance: CmsBindingProvenance;
}

export interface CmsPageFieldBinding {
    key: string;
    owner: CmsFieldOwner;
    persistenceLocation: string;
    syncDirection: CmsBindingSyncDirection;
    conflictStatus: CmsBindingConflictStatus;
}

export interface CmsBoundPage {
    pageId: string | null;
    slug: string;
    title: string;
    seoTitle: string | null;
    seoDescription: string | null;
    cmsBacked: boolean;
    contentOwner: CmsFieldOwner;
    syncDirection: CmsBindingSyncDirection;
    conflictStatus: CmsBindingConflictStatus;
    pageFields: CmsPageFieldBinding[];
    sections: CmsBoundSection[];
    provenance: CmsBindingProvenance;
    metadata: Record<string, unknown>;
}

export interface CmsBindingModel {
    schemaVersion: 1;
    page: CmsBoundPage;
    sections: CmsBoundSection[];
    authorities: {
        content: 'cms';
        layout: 'cms_revision';
        code: 'workspace';
        preview: 'derived';
    };
    previewHydration: {
        source: 'cms_revision+workspace';
        builderReady: boolean;
    };
    metadata: Record<string, unknown>;
}

export interface CmsSyncPlanSectionEntry {
    localId: string;
    sectionType: string;
    owner: CmsFieldOwner;
    contentFieldPaths: string[];
    visualFieldPaths: string[];
    codeFieldPaths: string[];
    syncDirection: CmsBindingSyncDirection;
    conflictStatus: CmsBindingConflictStatus;
}

export interface CmsSyncPlanFileEffect {
    path: string;
    owner: CmsFieldOwner;
    syncDirection: CmsBindingSyncDirection;
    conflictStatus: CmsBindingConflictStatus;
    cmsBacked: boolean;
    sectionLocalIds: string[];
    contentFieldPaths: string[];
    visualFieldPaths: string[];
    codeFieldPaths: string[];
}

export interface CmsSyncPlan {
    schemaVersion: 1;
    contentAuthority: 'cms';
    layoutAuthority: 'cms_revision';
    codeAuthority: 'workspace';
    previewAuthority: 'derived';
    route: CmsEditRoute;
    requiresCmsRevisionSave: boolean;
    requiresWorkspaceMirror: boolean;
    requiresPreviewRefresh: boolean;
    pageId: string | null;
    pageSlug: string | null;
    sectionEffects: CmsSyncPlanSectionEntry[];
    fileEffects: CmsSyncPlanFileEffect[];
    provenance: CmsBindingProvenance;
}

export interface RoutedCmsEdit {
    route: CmsEditRoute;
    reasons: string[];
    contentFieldPaths: string[];
    structureFieldPaths: string[];
    codeFieldPaths: string[];
    sectionLocalIds: string[];
    touchedPaths: string[];
}

export interface CmsMediaBindingMetadata {
    owner: CmsMediaFieldOwner;
    assetUrl: string | null;
    mediaId: string | null;
    provider: string | null;
    providerImageId: string | null;
    importedBy: string | null;
    component: string | null;
    propPath: string;
    qualifiedPropPath: string;
    nestedSectionPath: number[] | null;
    projectId: string | null;
    sectionLocalId: string | null;
    pageSlug: string | null;
    source: CmsMediaUpdateSource;
    updatedAt: string | null;
}
