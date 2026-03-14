import { createGenerationRunState } from './generationPhases';
import { cloneData, cloneRecordData } from '../runtime/clone';
import type {
    ComponentProvenance,
    GeneratedAsset,
    GeneratedComponentInstance,
    GeneratedDependency,
    GeneratedFile,
    GeneratedLayout,
    GeneratedPage,
    GeneratedProjectGraph,
    GeneratedRoute,
    GeneratedSection,
    GeneratedSectionKind,
} from './types';

export const GENERATED_PROJECT_GRAPH_SCHEMA_VERSION = 1 as const;

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function cloneRecord<T extends Record<string, unknown>>(value: T | null | undefined): T {
    return cloneRecordData(value);
}

function normalizeText(value: string | null | undefined, fallback = ''): string {
    const normalized = typeof value === 'string' ? value.trim() : '';
    return normalized !== '' ? normalized : fallback;
}

function normalizeArray<T>(value: T[] | null | undefined): T[] {
    return Array.isArray(value) ? [...value] : [];
}

export function buildRoutePathFromSlug(slug: string): string {
    const normalizedSlug = normalizeText(slug, 'home').toLowerCase();
    return normalizedSlug === 'home' ? '/' : `/${normalizedSlug.replace(/^\/+/, '')}`;
}

export function resolveGeneratedSectionKindFromRegistryKey(registryKey: string | null | undefined): GeneratedSectionKind {
    const normalized = normalizeText(registryKey).toLowerCase();

    if (normalized.includes('header')) {
        return 'header';
    }

    if (normalized.includes('footer')) {
        return 'footer';
    }

    if (normalized.includes('hero')) {
        return 'hero';
    }

    if (normalized.includes('feature') || normalized.includes('services')) {
        return 'features';
    }

    if (normalized.includes('cta')) {
        return 'cta';
    }

    if (
        normalized.includes('heading')
        || normalized.includes('text')
        || normalized.includes('card')
        || normalized.includes('image')
        || normalized.includes('section')
    ) {
        return 'content';
    }

    return 'custom';
}

export function createComponentProvenance(input: Partial<ComponentProvenance> = {}): ComponentProvenance {
    return {
        source: input.source ?? 'ai',
        runId: input.runId ?? null,
        promptFingerprint: input.promptFingerprint ?? null,
        templateId: input.templateId ?? null,
        notes: normalizeArray(input.notes),
    };
}

export function createGeneratedComponentInstance(
    input: Partial<GeneratedComponentInstance> & Pick<GeneratedComponentInstance, 'id' | 'key'>
): GeneratedComponentInstance {
    return {
        id: normalizeText(input.id),
        key: normalizeText(input.key),
        registryKey: normalizeText(input.registryKey) || null,
        displayName: normalizeText(input.displayName, normalizeText(input.key, 'Component')),
        kind: input.kind ?? 'content',
        props: cloneRecord(input.props),
        children: normalizeArray(input.children).map((child) => createGeneratedComponentInstance(child)),
        parentId: normalizeText(input.parentId) || null,
        pageId: normalizeText(input.pageId) || null,
        sectionId: normalizeText(input.sectionId) || null,
        sourceFilePath: normalizeText(input.sourceFilePath) || null,
        editable: input.editable ?? true,
        provenance: createComponentProvenance(input.provenance),
        metadata: cloneRecord(input.metadata),
    };
}

export function createGeneratedSection(
    input: Partial<GeneratedSection> & Pick<GeneratedSection, 'id' | 'localId'>
): GeneratedSection {
    const registryKey = normalizeText(input.registryKey) || null;

    return {
        id: normalizeText(input.id),
        localId: normalizeText(input.localId),
        kind: input.kind ?? resolveGeneratedSectionKindFromRegistryKey(registryKey),
        registryKey,
        label: normalizeText(input.label) || null,
        order: typeof input.order === 'number' && Number.isFinite(input.order) ? input.order : 0,
        props: cloneRecord(input.props),
        components: normalizeArray(input.components).map((component) => createGeneratedComponentInstance(component)),
        sourceFilePath: normalizeText(input.sourceFilePath) || null,
        cmsBacked: input.cmsBacked ?? false,
        contentOwner: input.contentOwner ?? null,
        cmsFieldPaths: normalizeArray(input.cmsFieldPaths),
        visualFieldPaths: normalizeArray(input.visualFieldPaths),
        codeFieldPaths: normalizeArray(input.codeFieldPaths),
        syncDirection: input.syncDirection ?? null,
        conflictStatus: input.conflictStatus ?? null,
        metadata: cloneRecord(input.metadata),
    };
}

export function createGeneratedPage(
    input: Partial<GeneratedPage> & Pick<GeneratedPage, 'id' | 'slug' | 'title'>
): GeneratedPage {
    const sections = normalizeArray(input.sections)
        .map((section, index) => createGeneratedSection({
            ...section,
            order: typeof section.order === 'number' && Number.isFinite(section.order) ? section.order : index,
        }))
        .sort((left, right) => left.order - right.order);

    return {
        id: normalizeText(input.id),
        slug: normalizeText(input.slug, 'home').toLowerCase(),
        title: normalizeText(input.title, 'Untitled Page'),
        routeId: normalizeText(input.routeId) || null,
        layoutId: normalizeText(input.layoutId) || null,
        entryFilePath: normalizeText(input.entryFilePath) || null,
        sections,
        cmsBacked: input.cmsBacked ?? false,
        contentOwner: input.contentOwner ?? null,
        cmsFieldPaths: normalizeArray(input.cmsFieldPaths),
        visualFieldPaths: normalizeArray(input.visualFieldPaths),
        codeFieldPaths: normalizeArray(input.codeFieldPaths),
        syncDirection: input.syncDirection ?? null,
        conflictStatus: input.conflictStatus ?? null,
        metadata: cloneRecord(input.metadata),
    };
}

export function createGeneratedLayout(
    input: Partial<GeneratedLayout> & Pick<GeneratedLayout, 'id' | 'key' | 'name'>
): GeneratedLayout {
    return {
        id: normalizeText(input.id),
        key: normalizeText(input.key),
        name: normalizeText(input.name),
        kind: input.kind ?? 'site',
        filePath: normalizeText(input.filePath) || null,
        props: cloneRecord(input.props),
        slots: normalizeArray(input.slots).map((slot) => normalizeText(slot)).filter(Boolean),
        sectionIds: normalizeArray(input.sectionIds).map((id) => normalizeText(id)).filter(Boolean),
        metadata: cloneRecord(input.metadata),
    };
}

export function createGeneratedRoute(
    input: Partial<GeneratedRoute> & Pick<GeneratedRoute, 'id' | 'pageId'>
): GeneratedRoute {
    return {
        id: normalizeText(input.id),
        path: normalizeText(input.path, '/'),
        pageId: normalizeText(input.pageId),
        layoutId: normalizeText(input.layoutId) || null,
        entryFilePath: normalizeText(input.entryFilePath) || null,
        isIndex: input.isIndex ?? false,
        metadata: cloneRecord(input.metadata),
    };
}

export function createGeneratedFile(
    input: Partial<GeneratedFile> & Pick<GeneratedFile, 'id' | 'path' | 'contents'>
): GeneratedFile {
    return {
        id: normalizeText(input.id),
        path: normalizeText(input.path),
        kind: input.kind ?? 'other',
        language: input.language ?? 'other',
        contents: input.contents,
        checksum: normalizeText(input.checksum) || null,
        source: input.source ?? 'ai',
        editState: input.editState ?? 'ai-generated',
        ownerType: input.ownerType ?? 'project',
        ownerId: normalizeText(input.ownerId) || null,
        pageIds: normalizeArray(input.pageIds).map((id) => normalizeText(id)).filter(Boolean),
        componentIds: normalizeArray(input.componentIds).map((id) => normalizeText(id)).filter(Boolean),
        dependencies: normalizeArray(input.dependencies).map((name) => normalizeText(name)).filter(Boolean),
        metadata: cloneRecord(input.metadata),
    };
}

export function createGeneratedAsset(input: Partial<GeneratedAsset> & Pick<GeneratedAsset, 'id' | 'kind' | 'path'>): GeneratedAsset {
    return {
        id: normalizeText(input.id),
        kind: input.kind,
        path: normalizeText(input.path),
        publicPath: normalizeText(input.publicPath) || null,
        fileId: normalizeText(input.fileId) || null,
        source: input.source ?? 'ai',
        mimeType: normalizeText(input.mimeType) || null,
        metadata: cloneRecord(input.metadata),
    };
}

export function createGeneratedDependency(
    input: Partial<GeneratedDependency> & Pick<GeneratedDependency, 'name' | 'version'>
): GeneratedDependency {
    return {
        name: normalizeText(input.name),
        version: normalizeText(input.version),
        kind: input.kind ?? 'runtime',
        manager: input.manager ?? 'npm',
        requestedBy: input.requestedBy ?? 'ai',
    };
}

function collectPageComponents(page: GeneratedPage): GeneratedComponentInstance[] {
    const collected: GeneratedComponentInstance[] = [];

    const visit = (component: GeneratedComponentInstance, sectionId: string | null) => {
        const normalized = createGeneratedComponentInstance({
            ...component,
            pageId: component.pageId ?? page.id,
            sectionId: component.sectionId ?? sectionId,
        });

        collected.push(normalized);
        normalized.children.forEach((child) => visit(child, normalized.sectionId));
    };

    page.sections.forEach((section) => {
        section.components.forEach((component) => visit(component, section.id));
    });

    return collected;
}

function dedupeComponents(components: GeneratedComponentInstance[]): GeneratedComponentInstance[] {
    const byId = new Map<string, GeneratedComponentInstance>();

    components.forEach((component) => {
        if (component.id === '') {
            return;
        }

        byId.set(component.id, component);
    });

    return Array.from(byId.values());
}

export function createGeneratedProjectGraph(input: Partial<GeneratedProjectGraph> = {}): GeneratedProjectGraph {
    const pages = normalizeArray(input.pages).map((page) => createGeneratedPage({
        ...page,
        slug: normalizeText(page.slug, 'home'),
        title: normalizeText(page.title, 'Untitled Page'),
        id: normalizeText(page.id, normalizeText(page.slug, 'page')),
    }));
    const explicitComponents = normalizeArray(input.components).map((component) => createGeneratedComponentInstance(component));
    const derivedComponents = pages.flatMap((page) => collectPageComponents(page));

    return {
        schemaVersion: GENERATED_PROJECT_GRAPH_SCHEMA_VERSION,
        projectId: normalizeText(input.projectId) || null,
        name: normalizeText(input.name, 'Untitled Project'),
        prompt: normalizeText(input.prompt) || null,
        rootDir: normalizeText(input.rootDir) || null,
        pages,
        layouts: normalizeArray(input.layouts).map((layout) => createGeneratedLayout({
            ...layout,
            id: normalizeText(layout.id, layout.key ?? layout.name ?? 'layout'),
            key: normalizeText(layout.key, 'site'),
            name: normalizeText(layout.name, 'Site Layout'),
        })),
        routes: normalizeArray(input.routes).map((route) => createGeneratedRoute({
            ...route,
            id: normalizeText(route.id, `${route.pageId ?? 'page'}:route`),
            pageId: normalizeText(route.pageId, 'page'),
            path: normalizeText(route.path, '/'),
        })),
        components: dedupeComponents([...explicitComponents, ...derivedComponents]),
        assets: normalizeArray(input.assets).map((asset) => createGeneratedAsset({
            ...asset,
            id: normalizeText(asset.id, asset.path ?? 'asset'),
            kind: asset.kind ?? 'other',
            path: normalizeText(asset.path, 'asset'),
        })),
        files: normalizeArray(input.files).map((file) => createGeneratedFile({
            ...file,
            id: normalizeText(file.id, file.path ?? 'file'),
            path: normalizeText(file.path, 'file'),
            contents: typeof file.contents === 'string' ? file.contents : '',
        })),
        dependencies: normalizeArray(input.dependencies).map((dependency) => createGeneratedDependency({
            ...dependency,
            name: normalizeText(dependency.name, 'dependency'),
            version: normalizeText(dependency.version, 'latest'),
        })),
        generation: createGenerationRunState(input.generation),
        metadata: cloneRecord(input.metadata),
    };
}

export function collectGeneratedSections(graph: Pick<GeneratedProjectGraph, 'pages'>): GeneratedSection[] {
    return graph.pages.flatMap((page) => page.sections).sort((left, right) => left.order - right.order);
}

export function findGeneratedPage(graph: Pick<GeneratedProjectGraph, 'pages'>, pageIdOrSlug: string): GeneratedPage | null {
    const needle = normalizeText(pageIdOrSlug).toLowerCase();

    return graph.pages.find((page) => (
        page.id.toLowerCase() === needle || page.slug.toLowerCase() === needle
    )) ?? null;
}

export function findGeneratedSection(
    graph: Pick<GeneratedProjectGraph, 'pages'>,
    sectionIdOrLocalId: string
): { page: GeneratedPage; section: GeneratedSection } | null {
    const needle = normalizeText(sectionIdOrLocalId).toLowerCase();

    for (const page of graph.pages) {
        const section = page.sections.find((candidate) => (
            candidate.id.toLowerCase() === needle || candidate.localId.toLowerCase() === needle
        ));

        if (section) {
            return { page, section };
        }
    }

    return null;
}

export function buildDefaultProjectGraphPageRoute(page: Pick<GeneratedPage, 'id' | 'slug' | 'layoutId' | 'entryFilePath'>): GeneratedRoute {
    return createGeneratedRoute({
        id: `${page.id}:route`,
        pageId: page.id,
        layoutId: page.layoutId,
        entryFilePath: page.entryFilePath,
        path: buildRoutePathFromSlug(page.slug),
        isIndex: page.slug === 'home',
    });
}

export function cloneGeneratedProjectGraph(graph: GeneratedProjectGraph): GeneratedProjectGraph {
    return createGeneratedProjectGraph(cloneData(graph));
}

export function getGeneratedSectionPrimaryProps(section: GeneratedSection): Record<string, unknown> {
    const componentProps = section.components[0]?.props;
    const normalizedComponentProps = isRecord(componentProps) ? cloneRecord(componentProps) : {};
    return {
        ...normalizedComponentProps,
        ...cloneRecord(section.props),
    };
}
