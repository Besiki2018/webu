import type {
    WorkspaceEditorSource,
    WorkspaceManifest,
    WorkspaceManifestFileOwnership,
} from '@/builder/codegen/types';

export type ProjectionSource = 'custom' | 'cms-projection' | 'detached-projection' | null;
export type WorkspaceFileActor = WorkspaceEditorSource;
export type WorkspaceFileDiffStatus = 'clean' | 'created' | 'updated' | 'deleted' | 'moved';

export const WORKSPACE_OPERATION_LOG_RELATIVE_PATH = '.webu/workspace-operation-log.json';

export interface WorkspaceListFileEntry {
    path: string;
    name: string;
    size: number;
    is_dir: boolean;
    mod_time: string;
    source_kind?: 'workspace';
    is_editable?: boolean;
    is_generated_projection?: boolean;
    projection_role?: string | null;
    projection_source?: ProjectionSource;
}

export interface WorkspaceOperationFileSnapshot {
    exists: boolean;
    checksum: string | null;
    size: number;
    line_count: number;
}

export interface WorkspaceOperationLogEntry {
    id: string;
    timestamp: string | null;
    actor: WorkspaceFileActor;
    source: string;
    operation_kind: string;
    path: string;
    previous_path: string | null;
    reason: string | null;
    preview_refresh_requested: boolean;
    before: WorkspaceOperationFileSnapshot;
    after: WorkspaceOperationFileSnapshot;
}

export interface WorkspaceOperationLogDocument {
    schemaVersion: 1;
    projectId: string | null;
    entries: WorkspaceOperationLogEntry[];
    updatedAt: string | null;
}

export interface WorkspaceFileProvenance {
    generatedBy: 'ai' | 'user' | 'system' | 'template' | null;
    editState: 'ai-generated' | 'user-edited' | 'mixed';
    lastEditor: WorkspaceEditorSource | null;
    locked: boolean;
    templateOwned: boolean;
    dirty: boolean;
    lastOperationId: string | null;
    lastOperationKind: string | null;
}

export interface WorkspaceFileDiffMetadata {
    status: WorkspaceFileDiffStatus;
    operationKind: string | null;
    previousPath: string | null;
    changedAt: string | null;
    checksumBefore: string | null;
    checksumAfter: string | null;
    byteDelta: number | null;
    lineDelta: number | null;
}

export interface WorkspaceFileSelectionMeta {
    sourceKind: 'workspace' | 'derived-preview';
    isEditable: boolean;
    isGeneratedProjection: boolean;
    projectionRole?: string | null;
    projectionSource?: ProjectionSource;
    sourceLabel?: string | null;
    provenance?: WorkspaceFileProvenance | null;
    diff?: WorkspaceFileDiffMetadata | null;
}

export interface WorkspaceFileRecord {
    path: string;
    name: string;
    size: number;
    isDir: boolean;
    modTime: string;
    sourceKind: 'workspace';
    isEditable: boolean;
    isGeneratedProjection: boolean;
    projectionRole: string | null;
    projectionSource: ProjectionSource;
    sourceLabel: string | null;
    provenance: WorkspaceFileProvenance;
    diff: WorkspaceFileDiffMetadata;
}

function normalizeText(value: string | null | undefined): string | null {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

function asPositiveNumber(value: unknown): number {
    return typeof value === 'number' && Number.isFinite(value) ? value : 0;
}

function defaultWorkspaceOperationSnapshot(): WorkspaceOperationFileSnapshot {
    return {
        exists: false,
        checksum: null,
        size: 0,
        line_count: 0,
    };
}

export function normalizeWorkspaceOperationLogDocument(input: Partial<WorkspaceOperationLogDocument> | null | undefined): WorkspaceOperationLogDocument {
    return {
        schemaVersion: 1,
        projectId: normalizeText(input?.projectId) ?? null,
        entries: Array.isArray(input?.entries)
            ? input.entries
                .filter((entry) => !!entry && typeof entry === 'object')
                .map((entry, index) => ({
                    id: normalizeText(entry.id) ?? `workspace-op-${index + 1}`,
                    timestamp: normalizeText(entry.timestamp) ?? null,
                    actor: (entry.actor ?? 'user') as WorkspaceFileActor,
                    source: normalizeText(entry.source) ?? 'workspace_file_api',
                    operation_kind: normalizeText(entry.operation_kind) ?? 'update_file',
                    path: normalizeText(entry.path) ?? '',
                    previous_path: normalizeText(entry.previous_path) ?? null,
                    reason: normalizeText(entry.reason) ?? null,
                    preview_refresh_requested: entry.preview_refresh_requested !== false,
                    before: {
                        exists: entry.before?.exists === true,
                        checksum: normalizeText(entry.before?.checksum) ?? null,
                        size: asPositiveNumber(entry.before?.size),
                        line_count: asPositiveNumber(entry.before?.line_count),
                    },
                    after: {
                        exists: entry.after?.exists === true,
                        checksum: normalizeText(entry.after?.checksum) ?? null,
                        size: asPositiveNumber(entry.after?.size),
                        line_count: asPositiveNumber(entry.after?.line_count),
                    },
                }))
                .filter((entry) => entry.path !== '')
            : [],
        updatedAt: normalizeText(input?.updatedAt) ?? null,
    };
}

export function createEmptyWorkspaceOperationLog(projectId: string | null = null): WorkspaceOperationLogDocument {
    return normalizeWorkspaceOperationLogDocument({
        projectId,
        entries: [],
    });
}

export function getLatestWorkspaceOperationForPath(
    path: string,
    log: Pick<WorkspaceOperationLogDocument, 'entries'>,
): WorkspaceOperationLogEntry | null {
    for (const entry of log.entries) {
        if (entry.path === path || entry.previous_path === path) {
            return entry;
        }
    }

    return null;
}

export function buildWorkspaceFileDiffMetadata(
    path: string,
    log: Pick<WorkspaceOperationLogDocument, 'entries'>,
): WorkspaceFileDiffMetadata {
    const latest = getLatestWorkspaceOperationForPath(path, log);
    if (!latest) {
        return {
            status: 'clean',
            operationKind: null,
            previousPath: null,
            changedAt: null,
            checksumBefore: null,
            checksumAfter: null,
            byteDelta: null,
            lineDelta: null,
        };
    }

    let status: WorkspaceFileDiffStatus = 'updated';
    if (latest.operation_kind === 'create_file') {
        status = 'created';
    } else if (latest.operation_kind === 'delete_file') {
        status = 'deleted';
    } else if (latest.operation_kind === 'move_file') {
        status = 'moved';
    }

    const byteDelta = latest.after.exists || latest.before.exists
        ? latest.after.size - latest.before.size
        : null;
    const lineDelta = latest.after.exists || latest.before.exists
        ? latest.after.line_count - latest.before.line_count
        : null;

    return {
        status,
        operationKind: latest.operation_kind,
        previousPath: latest.previous_path,
        changedAt: latest.timestamp,
        checksumBefore: latest.before.checksum,
        checksumAfter: latest.after.checksum,
        byteDelta,
        lineDelta,
    };
}

export function buildWorkspaceFileProvenance(
    manifestEntry: WorkspaceManifestFileOwnership | null,
): WorkspaceFileProvenance {
    return {
        generatedBy: manifestEntry?.generatedBy ?? null,
        editState: manifestEntry?.editState ?? 'user-edited',
        lastEditor: manifestEntry?.lastEditor ?? null,
        locked: manifestEntry?.locked === true,
        templateOwned: manifestEntry?.templateOwned === true,
        dirty: manifestEntry?.dirty === true,
        lastOperationId: manifestEntry?.lastOperationId ?? null,
        lastOperationKind: manifestEntry?.lastOperationKind ?? null,
    };
}

export function buildWorkspaceFileRecord(
    entry: WorkspaceListFileEntry,
    manifest: Pick<WorkspaceManifest, 'fileOwnership'>,
    log: Pick<WorkspaceOperationLogDocument, 'entries'>,
): WorkspaceFileRecord {
    const manifestEntry = manifest.fileOwnership.find((item) => item.path === entry.path) ?? null;
    const provenance = buildWorkspaceFileProvenance(manifestEntry);
    const diff = buildWorkspaceFileDiffMetadata(entry.path, log);

    return {
        path: entry.path,
        name: entry.name,
        size: asPositiveNumber(entry.size),
        isDir: entry.is_dir === true,
        modTime: normalizeText(entry.mod_time) ?? '',
        sourceKind: 'workspace',
        isEditable: entry.is_editable !== false && provenance.locked !== true,
        isGeneratedProjection: entry.is_generated_projection === true,
        projectionRole: normalizeText(entry.projection_role) ?? null,
        projectionSource: entry.projection_source ?? null,
        sourceLabel: 'Workspace',
        provenance,
        diff,
    };
}

export function sortWorkspaceFileRecords(records: WorkspaceFileRecord[]): WorkspaceFileRecord[] {
    return [...records].sort((left, right) => {
        if (left.isDir !== right.isDir) {
            return left.isDir ? -1 : 1;
        }

        const leftGeneratedRank = left.provenance.generatedBy === 'ai' || left.provenance.generatedBy === 'system' || left.provenance.templateOwned
            ? 0
            : 1;
        const rightGeneratedRank = right.provenance.generatedBy === 'ai' || right.provenance.generatedBy === 'system' || right.provenance.templateOwned
            ? 0
            : 1;
        if (leftGeneratedRank !== rightGeneratedRank) {
            return leftGeneratedRank - rightGeneratedRank;
        }

        return left.path.localeCompare(right.path, undefined, { sensitivity: 'base' });
    });
}

export function buildWorkspaceFileRecords(
    entries: WorkspaceListFileEntry[],
    manifest: Pick<WorkspaceManifest, 'fileOwnership'>,
    log: Pick<WorkspaceOperationLogDocument, 'entries'>,
): WorkspaceFileRecord[] {
    return sortWorkspaceFileRecords(
        entries.map((entry) => buildWorkspaceFileRecord(entry, manifest, log)),
    );
}

export function buildWorkspaceFileSelectionMeta(record: WorkspaceFileRecord): WorkspaceFileSelectionMeta {
    return {
        sourceKind: record.sourceKind,
        isEditable: record.isEditable,
        isGeneratedProjection: record.isGeneratedProjection,
        projectionRole: record.projectionRole,
        projectionSource: record.projectionSource,
        sourceLabel: record.sourceLabel,
        provenance: record.provenance,
        diff: record.diff,
    };
}

export function describeWorkspaceFileProvenance(provenance: WorkspaceFileProvenance | null | undefined): string | null {
    if (!provenance) {
        return null;
    }

    if (provenance.generatedBy === 'system') {
        return 'System scaffold';
    }

    if (provenance.generatedBy === 'template') {
        return 'Template scaffold';
    }

    if (provenance.generatedBy === 'ai' && provenance.editState === 'ai-generated') {
        return 'AI generated';
    }

    if (provenance.editState === 'mixed') {
        return 'Mixed';
    }

    if (provenance.lastEditor === 'user') {
        return 'User edited';
    }

    if (provenance.lastEditor === 'visual_builder') {
        return 'Builder synced';
    }

    return provenance.generatedBy === 'user' ? 'User file' : null;
}
