import {
    buildBuilderPageModelFromGeneratedPage,
    buildBuilderPageModelsFromProjectGraph,
} from '@/builder/codegen/projectGraphToBuilderModel';
import {
    buildDefaultProjectGraphPageRoute,
    createGeneratedAsset,
    createGeneratedComponentInstance,
    createGeneratedFile,
    createGeneratedLayout,
    createGeneratedPage,
    createGeneratedProjectGraph,
    createGeneratedSection,
} from '@/builder/codegen/projectGraph';
import { createWorkspacePlan } from '@/builder/codegen/workspacePlan';
import type { AiWorkspaceOperation, AiWorkspaceWriteOperation } from '@/builder/codegen/aiWorkspaceOps';
import type { GeneratedFile, GeneratedSectionKind } from '@/builder/codegen/types';
import { buildCmsBindingModelFromBuilderPageModel } from '@/builder/cmsIntegration/cmsBindingModel';
import {
    applyCmsBindingModelToGeneratedPage,
    applyCmsBindingModelToProjectGraph,
} from '@/builder/cmsIntegration/workspaceCmsSync';
import type { ProjectType } from '@/builder/projectTypes';
import { cloneData } from '@/builder/runtime/clone';

import { createImageImportDesignExtraction, type CreateImageImportDesignExtractionOptions } from './designExtractionContract';
import { inferImageImportLayout } from './layoutInference';
import { matchImageImportComponents } from './componentMatchmaking';
import type { ImageImportProjectPlan, ImageImportMode, ImageImportLayoutNodeKind, ImageImportDesignExtraction } from './types';
import { mapImageImportPhaseToGenerationPhase } from './imageImportState';

export interface CreateImageImportProjectPlanInput {
    projectId: string;
    projectName: string;
    pageId?: string | null;
    pageSlug?: string | null;
    pageTitle?: string | null;
    prompt?: string | null;
    projectType?: ProjectType;
    mode?: ImageImportMode;
    extraction?: ImageImportDesignExtraction | null;
    extractionOptions?: Omit<CreateImageImportDesignExtractionOptions, 'projectType' | 'mode'>;
}

function normalizeText(value: string | null | undefined, fallback = ''): string {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : fallback;
}

function toPascalCase(value: string): string {
    return value
        .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
        .split(/[^a-z0-9]+/i)
        .filter(Boolean)
        .map((part) => part[0]!.toUpperCase() + part.slice(1))
        .join('');
}

function sanitizeForJson<T>(value: T): T {
    return cloneData(value);
}

function inferSectionKind(nodeKind: ImageImportLayoutNodeKind): GeneratedSectionKind {
    switch (nodeKind) {
        case 'header':
            return 'header';
        case 'hero':
            return 'hero';
        case 'features':
            return 'features';
        case 'cta':
            return 'cta';
        case 'footer':
            return 'footer';
        default:
            return 'content';
    }
}

function buildSectionFileComponentName(filePath: string): string {
    const fileName = filePath.split('/').pop()?.replace(/\.tsx$/, '') ?? 'ImportedSection';
    return toPascalCase(fileName);
}

function renderListItems(items: string[], itemPrefix: string): string {
    return items.map((item, index) => `                    <li key="${itemPrefix}-${index}" className="rounded-2xl border border-black/10 bg-white/70 p-4">${item}</li>`).join('\n');
}

function renderHeaderSection(content: Record<string, unknown>): string {
    const logoText = normalizeText(content.logoText as string | undefined, 'Brand');
    const menuItems = Array.isArray(content.menu_items)
        ? content.menu_items
        : [{ label: 'Home', url: '/' }];
    const ctaText = normalizeText(content.ctaText as string | undefined, '');

    return `import React from 'react';

const menuItems = ${JSON.stringify(menuItems, null, 2)} as const;

export default function ImportedHeaderSection() {
    return (
        <header className="border-b border-black/10 bg-white/80 backdrop-blur">
            <div className="mx-auto flex max-w-6xl items-center justify-between gap-6 px-6 py-4">
                <div className="text-lg font-semibold">${logoText}</div>
                <nav className="hidden gap-6 text-sm text-slate-600 md:flex">
                    {menuItems.map((item, index) => (
                        <a key={index} href={typeof item.url === 'string' ? item.url : '#'} className="transition hover:text-slate-900">
                            {typeof item.label === 'string' ? item.label : 'Link'}
                        </a>
                    ))}
                </nav>
                ${ctaText !== '' ? `<a href="#" className="rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white">${ctaText}</a>` : '<div className="hidden md:block" />'}
            </div>
        </header>
    );
}
`;
}

function renderFooterSection(content: Record<string, unknown>): string {
    const logoText = normalizeText(content.logoText as string | undefined, 'Brand');
    const description = normalizeText((content.description as string | undefined) ?? (content.body as string | undefined), 'Generated from the uploaded reference.');
    const links = Array.isArray(content.links) ? content.links : [];

    return `import React from 'react';

const links = ${JSON.stringify(links, null, 2)} as const;

export default function ImportedFooterSection() {
    return (
        <footer className="border-t border-black/10 bg-slate-950 text-slate-200">
            <div className="mx-auto grid max-w-6xl gap-6 px-6 py-12 md:grid-cols-[1.5fr_1fr]">
                <div>
                    <p className="text-lg font-semibold">${logoText}</p>
                    <p className="mt-3 max-w-xl text-sm text-slate-400">${description}</p>
                </div>
                <div className="grid gap-2 text-sm">
                    {links.map((item, index) => (
                        <a key={index} href={typeof item.url === 'string' ? item.url : '#'} className="text-slate-300 transition hover:text-white">
                            {typeof item.label === 'string' ? item.label : 'Footer link'}
                        </a>
                    ))}
                </div>
            </div>
        </footer>
    );
}
`;
}

function renderHeroSection(content: Record<string, unknown>, mode: ImageImportMode): string {
    const title = normalizeText((content.title as string | undefined) ?? (content.headline as string | undefined), 'Design-driven hero');
    const subtitle = normalizeText(content.subtitle as string | undefined, 'A page direction derived from the uploaded reference.');
    const buttonText = normalizeText((content.buttonText as string | undefined) ?? (content.ctaLabel as string | undefined), 'Get started');
    const secondary = normalizeText((content.secondaryButtonText as string | undefined) ?? (content.secondaryCtaLabel as string | undefined), '');
    const image = normalizeText((content.image as string | undefined) ?? (content.image_url as string | undefined), '');

    return `import React from 'react';

export default function ImportedHeroSection() {
    return (
        <section className="mx-auto grid max-w-6xl gap-10 px-6 py-${mode === 'recreate' ? '24' : '20'} md:grid-cols-2 md:items-center">
            <div>
                <span className="inline-flex rounded-full border border-black/10 bg-white px-3 py-1 text-xs uppercase tracking-[0.2em] text-slate-500">Imported design</span>
                <h1 className="mt-6 text-4xl font-semibold tracking-tight text-slate-950 md:text-6xl">${title}</h1>
                <p className="mt-4 max-w-2xl text-lg text-slate-600">${subtitle}</p>
                <div className="mt-8 flex flex-wrap gap-3">
                    <a href="#" className="rounded-full bg-slate-950 px-5 py-3 text-sm font-medium text-white">${buttonText}</a>
                    ${secondary !== '' ? `<a href="#" className="rounded-full border border-black/10 px-5 py-3 text-sm font-medium text-slate-700">${secondary}</a>` : ''}
                </div>
            </div>
            ${image !== '' ? `<div className="overflow-hidden rounded-[2rem] border border-black/10 bg-white shadow-sm"><img src="${image}" alt="" className="h-full w-full object-cover" /></div>` : '<div className="rounded-[2rem] border border-dashed border-black/10 bg-white/70 p-10 text-sm text-slate-500">Reference-led visual composition</div>'}
        </section>
    );
}
`;
}

function renderCollectionSection(kind: ImageImportLayoutNodeKind, content: Record<string, unknown>): string {
    const title = normalizeText(content.title as string | undefined, kind === 'testimonials' ? 'Testimonials' : 'Highlights');
    const subtitle = normalizeText(content.subtitle as string | undefined, '');
    const rawItems = Array.isArray(content.items) ? content.items : [];
    const items = rawItems.map((item) => {
        if (typeof item === 'string') {
            return item;
        }

        if (item && typeof item === 'object') {
            const titleValue = normalizeText((item as { title?: string }).title, '');
            const descriptionValue = normalizeText((item as { description?: string }).description, '');
            return [titleValue, descriptionValue].filter(Boolean).join(' - ') || 'Imported item';
        }

        return 'Imported item';
    });

    const gridClass = kind === 'gallery'
        ? 'md:grid-cols-3'
        : kind === 'product-grid'
            ? 'md:grid-cols-3'
            : kind === 'testimonials'
                ? 'md:grid-cols-2'
                : 'md:grid-cols-3';

    return `import React from 'react';

const items = ${JSON.stringify(items.length > 0 ? items : ['Imported item one', 'Imported item two', 'Imported item three'], null, 2)} as const;

export default function ImportedCollectionSection() {
    return (
        <section className="mx-auto max-w-6xl px-6 py-16">
            <div className="max-w-2xl">
                <h2 className="text-3xl font-semibold tracking-tight text-slate-950">${title}</h2>
                ${subtitle !== '' ? `<p className="mt-3 text-base text-slate-600">${subtitle}</p>` : ''}
            </div>
            <ul className="mt-8 grid gap-4 ${gridClass}">
${renderListItems(items, kind)}
            </ul>
        </section>
    );
}
`;
}

function renderFormSection(content: Record<string, unknown>): string {
    const title = normalizeText(content.title as string | undefined, 'Get in touch');
    const subtitle = normalizeText(content.subtitle as string | undefined, 'Generated from the uploaded reference.');
    const submitLabel = normalizeText((content.submitLabel as string | undefined) ?? (content.buttonLabel as string | undefined), 'Send');
    const fields = Array.isArray(content.fields) ? content.fields : ['name', 'email', 'message'];

    return `import React from 'react';

const fields = ${JSON.stringify(fields, null, 2)} as const;

export default function ImportedFormSection() {
    return (
        <section className="mx-auto max-w-6xl px-6 py-16">
            <div className="grid gap-8 rounded-[2rem] border border-black/10 bg-white p-8 shadow-sm md:grid-cols-[1fr_1.1fr]">
                <div>
                    <h2 className="text-3xl font-semibold tracking-tight text-slate-950">${title}</h2>
                    <p className="mt-3 text-base text-slate-600">${subtitle}</p>
                </div>
                <form className="grid gap-4">
                    {fields.map((field, index) => (
                        <label key={index} className="grid gap-2 text-sm text-slate-600">
                            <span className="capitalize">{String(field).replace(/_/g, ' ')}</span>
                            <input className="rounded-2xl border border-black/10 bg-slate-50 px-4 py-3 outline-none" placeholder={String(field)} />
                        </label>
                    ))}
                    <button type="button" className="rounded-full bg-slate-950 px-5 py-3 text-sm font-medium text-white">${submitLabel}</button>
                </form>
            </div>
        </section>
    );
}
`;
}

function renderContentSection(content: Record<string, unknown>): string {
    const title = normalizeText(content.title as string | undefined, 'Imported section');
    const body = normalizeText((content.body as string | undefined) ?? (content.description as string | undefined), 'Content adapted from the uploaded reference.');
    const image = normalizeText((content.image_url as string | undefined) ?? (content.image as string | undefined), '');

    return `import React from 'react';

export default function ImportedContentSection() {
    return (
        <section className="mx-auto max-w-5xl px-6 py-16">
            <div className="grid gap-8 rounded-[2rem] border border-black/10 bg-white p-8 shadow-sm md:grid-cols-2 md:items-center">
                <div>
                    <h2 className="text-3xl font-semibold tracking-tight text-slate-950">${title}</h2>
                    <p className="mt-4 text-base text-slate-600">${body}</p>
                </div>
                ${image !== '' ? `<div className="overflow-hidden rounded-[1.5rem] border border-black/10"><img src="${image}" alt="" className="h-full w-full object-cover" /></div>` : '<div className="rounded-[1.5rem] border border-dashed border-black/10 bg-slate-50 p-10 text-sm text-slate-500">Imported visual reference</div>'}
            </div>
        </section>
    );
}
`;
}

function renderImportedSectionFile(
    kind: ImageImportLayoutNodeKind,
    props: Record<string, unknown>,
    mode: ImageImportMode,
): string {
    switch (kind) {
        case 'header':
            return renderHeaderSection(props);
        case 'footer':
            return renderFooterSection(props);
        case 'hero':
            return renderHeroSection(props, mode);
        case 'features':
        case 'grid':
        case 'gallery':
        case 'product-grid':
        case 'testimonials':
        case 'faq':
            return renderCollectionSection(kind, props);
        case 'form':
            return renderFormSection(props);
        case 'cta':
        case 'content':
        case 'generated':
        default:
            return renderContentSection(props);
    }
}

function renderSiteLayoutFile(): string {
    return `import type { ReactNode } from 'react';

interface SiteLayoutProps {
    header?: ReactNode;
    footer?: ReactNode;
    children: ReactNode;
}

export default function SiteLayout({ header, footer, children }: SiteLayoutProps) {
    return (
        <div className="min-h-screen bg-[var(--webu-surface)] text-[var(--webu-text)]">
            {header}
            <main>{children}</main>
            {footer}
        </div>
    );
}
`;
}

function renderAppFile(pageImportPath: string): string {
    return `import '${'./styles/globals.css'}';
import Page from '${pageImportPath}';

export default function App() {
    return <Page />;
}
`;
}

function renderGlobalsCss(extraction: ImageImportDesignExtraction): string {
    const surface = extraction.styleDirection.isDark ? '#0f172a' : '#f8fafc';
    const text = extraction.styleDirection.isDark ? '#e2e8f0' : '#0f172a';
    const muted = extraction.styleDirection.isDark ? '#94a3b8' : '#475569';
    const accent = extraction.styleDirection.primaryStyle === 'bold'
        ? '#ea580c'
        : extraction.styleDirection.primaryStyle === 'corporate'
            ? '#1d4ed8'
            : extraction.styleDirection.primaryStyle === 'minimal'
                ? '#0f172a'
                : '#0891b2';
    const radius = extraction.styleDirection.borderTreatment === 'sharp'
        ? '1rem'
        : extraction.styleDirection.borderTreatment === 'soft'
            ? '1.5rem'
            : '2rem';

    return `:root {
    --webu-surface: ${surface};
    --webu-text: ${text};
    --webu-muted: ${muted};
    --webu-accent: ${accent};
    --webu-radius: ${radius};
}

* {
    box-sizing: border-box;
}

html, body, #root {
    margin: 0;
    min-height: 100%;
}

body {
    background: var(--webu-surface);
    color: var(--webu-text);
    font-family: "IBM Plex Sans", "Inter", system-ui, sans-serif;
}

a {
    color: inherit;
    text-decoration: none;
}

img {
    display: block;
    max-width: 100%;
}
`;
}

function renderReferenceJson(extraction: ImageImportDesignExtraction, layoutWarnings: string[]): string {
    return JSON.stringify({
        mode: extraction.mode,
        projectType: extraction.projectType,
        styleDirection: extraction.styleDirection,
        warnings: [...extraction.warnings, ...layoutWarnings],
        blocks: extraction.blocks.map((block) => ({
            id: block.id,
            kind: block.kind,
            order: block.order,
            confidence: block.confidence,
            evidence: block.evidence,
        })),
    }, null, 2);
}

function renderPageFile(
    pageTitle: string,
    sectionImports: Array<{ componentName: string; importPath: string; kind: ImageImportLayoutNodeKind }>,
): string {
    const headerImport = sectionImports.find((entry) => entry.kind === 'header') ?? null;
    const footerImport = sectionImports.find((entry) => entry.kind === 'footer') ?? null;
    const contentImports = sectionImports.filter((entry) => entry.kind !== 'header' && entry.kind !== 'footer');

    return `import SiteLayout from '@/layouts/SiteLayout';
${sectionImports.map((entry) => `import ${entry.componentName} from '${entry.importPath}';`).join('\n')}

export default function Page() {
    return (
        <SiteLayout
            header={${headerImport ? `<${headerImport.componentName} />` : 'undefined'}}
            footer={${footerImport ? `<${footerImport.componentName} />` : 'undefined'}}
        >
            <>
${contentImports.map((entry) => `                <${entry.componentName} />`).join('\n')}
            </>
        </SiteLayout>
    );
}

Page.displayName = '${pageTitle.replace(/'/g, "\\'")}Page';
`;
}

function createWorkspaceOperationForFile(file: GeneratedFile): AiWorkspaceWriteOperation {
    const updatePaths = new Set([
        'src/App.tsx',
        'src/layouts/SiteLayout.tsx',
        'src/pages/home/Page.tsx',
        'src/styles/globals.css',
    ]);

    return {
        kind: updatePaths.has(file.path) ? 'update_file' : 'create_file',
        path: file.path,
        content: file.contents,
        reason: 'image_import_graph',
    };
}

export async function createImageImportProjectPlan(
    input: CreateImageImportProjectPlanInput,
): Promise<ImageImportProjectPlan> {
    const pageSlug = normalizeText(input.pageSlug, 'home').toLowerCase();
    const pageTitle = normalizeText(input.pageTitle, pageSlug === 'home' ? 'Home' : toPascalCase(pageSlug));
    const pageId = normalizeText(input.pageId, `page-${pageSlug}`);
    const mode = input.mode ?? input.extraction?.mode ?? 'reference';
    const extraction = input.extraction ?? await createImageImportDesignExtraction({
        image: input.extractionOptions?.image ?? '',
        sourceKind: input.extractionOptions?.sourceKind,
        sourceLabel: input.extractionOptions?.sourceLabel,
        backendProvider: input.extractionOptions?.backendProvider ?? null,
        layoutVisionProvider: input.extractionOptions?.layoutVisionProvider ?? null,
        styleVisionProvider: input.extractionOptions?.styleVisionProvider ?? null,
        extractionProvider: input.extractionOptions?.extractionProvider ?? null,
        contentGeneratorProvider: input.extractionOptions?.contentGeneratorProvider ?? null,
        imageDetector: input.extractionOptions?.imageDetector ?? null,
        stockImageProvider: input.extractionOptions?.stockImageProvider ?? null,
        projectType: input.projectType ?? 'landing',
        mode,
        preferredStyle: input.extractionOptions?.preferredStyle ?? null,
    });
    const layout = inferImageImportLayout(extraction);
    const componentMatches = matchImageImportComponents(extraction, layout, {
        pageSlug,
    });

    const sectionImports: Array<{ componentName: string; importPath: string; kind: ImageImportLayoutNodeKind; filePath: string }> = [];
    const generatedFiles: GeneratedFile[] = [];

    const sections = layout.nodes.map((node, index) => {
        const match = componentMatches[index]!;
        const componentName = buildSectionFileComponentName(match.sourceFilePath ?? `ImportedSection${index + 1}.tsx`);
        const filePath = match.sourceFilePath ?? `src/sections/imported/${componentName}.tsx`;
        const fileContents = renderImportedSectionFile(node.kind, sanitizeForJson(match.props), extraction.mode);

        generatedFiles.push(createGeneratedFile({
            id: filePath,
            path: filePath,
            contents: fileContents,
            kind: 'section',
            language: 'tsx',
            source: match.matchKind === 'generated' ? 'user' : 'ai',
            editState: 'ai-generated',
            ownerType: match.ownerType === 'layout' ? 'layout' : 'component',
            ownerId: match.nodeId,
            pageIds: [pageId],
            componentIds: [match.nodeId],
            metadata: {
                registryKey: match.registryKey,
                imageImport: true,
                generatedComponent: match.generatedComponent,
            },
        }));

        sectionImports.push({
            componentName,
            importPath: `@/${filePath.replace(/^src\//, '').replace(/\.tsx$/, '')}`,
            kind: node.kind,
            filePath,
        });

        return createGeneratedSection({
            id: `${pageId}:${node.id}`,
            localId: `image-${index + 1}`,
            kind: inferSectionKind(node.kind),
            registryKey: match.registryKey,
            label: node.sectionLabel,
            order: index,
            props: sanitizeForJson(match.props),
            sourceFilePath: filePath,
            metadata: {
                imageImportNodeKind: node.kind,
                matchKind: match.matchKind,
                preserveHierarchy: node.preserveHierarchy,
                preserveSpacing: node.preserveSpacing,
            },
            components: [
                createGeneratedComponentInstance({
                    id: `${pageId}:${node.id}:component`,
                    key: componentName,
                    registryKey: match.registryKey,
                    displayName: match.displayName,
                    kind: node.kind === 'header' || node.kind === 'footer'
                        ? 'layout'
                        : node.kind === 'hero' || node.kind === 'features' || node.kind === 'cta'
                            ? 'section'
                            : 'content',
                    props: sanitizeForJson(match.props),
                    pageId,
                    sectionId: `${pageId}:${node.id}`,
                    sourceFilePath: filePath,
                    provenance: {
                        source: 'ai',
                        runId: `${input.projectId}:image-import`,
                        promptFingerprint: null,
                        templateId: null,
                        notes: [
                            'image-import',
                            extraction.mode,
                            match.matchKind,
                        ],
                    },
                    metadata: {
                        imageImport: true,
                        interactiveModule: node.interactiveModule,
                    },
                }),
            ],
        });
    });

    const pageFilePath = `src/pages/${pageSlug}/Page.tsx`;
    const layoutFilePath = 'src/layouts/SiteLayout.tsx';
    const appFilePath = 'src/App.tsx';
    const globalsFilePath = 'src/styles/globals.css';
    const referenceFilePath = `public/imports/${pageSlug}-design-reference.json`;

    generatedFiles.push(
        createGeneratedFile({
            id: pageFilePath,
            path: pageFilePath,
            contents: renderPageFile(pageTitle, sectionImports),
            kind: 'page',
            language: 'tsx',
            source: 'ai',
            editState: 'ai-generated',
            ownerType: 'page',
            ownerId: pageId,
            pageIds: [pageId],
        }),
        createGeneratedFile({
            id: layoutFilePath,
            path: layoutFilePath,
            contents: renderSiteLayoutFile(),
            kind: 'layout',
            language: 'tsx',
            source: 'ai',
            editState: 'ai-generated',
            ownerType: 'layout',
            ownerId: 'site-layout',
            pageIds: [pageId],
        }),
        createGeneratedFile({
            id: appFilePath,
            path: appFilePath,
            contents: renderAppFile(`@/pages/${pageSlug}/Page`),
            kind: 'page',
            language: 'tsx',
            source: 'ai',
            editState: 'ai-generated',
            ownerType: 'project',
            ownerId: input.projectId,
            pageIds: [pageId],
        }),
        createGeneratedFile({
            id: globalsFilePath,
            path: globalsFilePath,
            contents: renderGlobalsCss(extraction),
            kind: 'style',
            language: 'css',
            source: 'ai',
            editState: 'ai-generated',
            ownerType: 'project',
            ownerId: input.projectId,
            pageIds: [pageId],
        }),
        createGeneratedFile({
            id: referenceFilePath,
            path: referenceFilePath,
            contents: renderReferenceJson(extraction, layout.warnings),
            kind: 'asset',
            language: 'json',
            source: 'system',
            editState: 'ai-generated',
            ownerType: 'asset',
            ownerId: `${pageId}:reference`,
            pageIds: [pageId],
        }),
    );

    const page = createGeneratedPage({
        id: pageId,
        slug: pageSlug,
        title: pageTitle,
        layoutId: 'site-layout',
        routeId: `${pageId}:route`,
        entryFilePath: pageFilePath,
        sections,
        metadata: {
            imageImport: {
                mode: extraction.mode,
                warnings: [...extraction.warnings, ...layout.warnings],
            },
        },
    });

    const bindingModel = buildCmsBindingModelFromBuilderPageModel({
        page: {
            id: page.id,
            slug: page.slug,
            title: page.title,
        },
        model: buildBuilderPageModelFromGeneratedPage(page),
        editor: 'ai',
        createdBy: 'ai',
    });
    const boundPage = applyCmsBindingModelToGeneratedPage(page, bindingModel);
    const graph = applyCmsBindingModelToProjectGraph(createGeneratedProjectGraph({
        projectId: input.projectId,
        name: input.projectName,
        prompt: input.prompt ?? null,
        pages: [boundPage],
        layouts: [
            createGeneratedLayout({
                id: 'site-layout',
                key: 'site-layout',
                name: 'Site Layout',
                kind: 'site',
                filePath: layoutFilePath,
                props: {
                    importedMode: extraction.mode,
                },
                slots: ['header', 'content', 'footer'],
                sectionIds: boundPage.sections.map((section) => section.id),
                metadata: {
                    imageImport: true,
                },
            }),
        ],
        routes: [buildDefaultProjectGraphPageRoute(boundPage)],
        files: generatedFiles,
        assets: [
            createGeneratedAsset({
                id: `${pageId}:reference`,
                kind: 'document',
                path: referenceFilePath,
                publicPath: `/${referenceFilePath.replace(/^public\//, '')}`,
                source: 'system',
                metadata: {
                    imageImport: true,
                    sourceKind: extraction.sourceKind,
                    sourceLabel: extraction.sourceLabel,
                },
            }),
        ],
        generation: {
            runId: `${input.projectId}:image-import`,
            phase: mapImageImportPhaseToGenerationPhase('planning_workspace'),
            message: 'Image import ready for workspace materialization',
            errorMessage: null,
            startedAt: null,
            updatedAt: null,
            completedAt: null,
            failedAt: null,
            preview: {
                ready: false,
                status: 'pending',
                buildId: null,
                previewUrl: null,
                artifactHash: null,
                workspaceHash: null,
                builtAt: null,
                errorMessage: null,
            },
        },
        metadata: {
            imageImport: true,
            projectType: input.projectType ?? extraction.projectType,
            mode: extraction.mode,
        },
    }), bindingModel);

    const workspacePlan = createWorkspacePlan(graph);
    const builderModels = buildBuilderPageModelsFromProjectGraph(graph);
    const workspaceOperations: AiWorkspaceOperation[] = [
        {
            kind: 'scaffold_project',
            summary: 'Seed the base project scaffold before applying image-import files.',
            reason: 'image_import_scaffold',
        },
        {
            kind: 'apply_patch_set',
            summary: 'Write image-import generated workspace files.',
            reason: 'image_import_graph',
            operations: generatedFiles.map((file) => createWorkspaceOperationForFile(file)),
        },
    ];

    return {
        extraction,
        layout,
        componentMatches,
        projectGraph: graph,
        workspacePlan,
        builderModels,
        workspaceOperations,
    };
}
