import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout';
import { AdminPageHeader } from '@/components/Admin/AdminPageHeader';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/contexts/LanguageContext';
import type { PageProps } from '@/types';
import {
    ArrowLeft,
    CheckCircle2,
    ExternalLink,
    Eye,
    Globe,
    LayoutTemplate,
    RefreshCw,
    XCircle,
} from 'lucide-react';

interface TemplateSummary {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    category: string | null;
    version: string | null;
    thumbnail: string | null;
    thumbnail_url: string | null;
}

interface DemoSiteSummary {
    id: string;
    project_id: string | null;
    locale: string | null;
}

interface DemoSection {
    key: string;
    label: string;
    enabled: boolean;
    component: string;
    props: Record<string, unknown>;
    data: Record<string, unknown>;
}

interface DemoPage {
    slug: string;
    title: string;
    path: string;
    template_file?: string | null;
    preview_url: string | null;
    sections: DemoSection[];
}

interface DemoPayload {
    template: TemplateSummary;
    demo_site: DemoSiteSummary | null;
    meta: {
        generated_at: string;
        source: string;
        requested_page: string | null;
        active_page_slug: string;
    };
    module_flags: Record<string, boolean>;
    typography_tokens: Record<string, string>;
    stats: {
        page_count: number;
        section_count: number;
        module_enabled_count: number;
    };
    pages: DemoPage[];
    active_page: DemoPage;
}

interface TemplateDemoPageProps extends PageProps {
    template: TemplateSummary;
    demo: DemoPayload;
}

const toReadable = (value: string): string =>
    value
        .replace(/[_.-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (char) => char.toUpperCase());

export default function TemplateDemo() {
    const { t } = useTranslation();
    const { template, demo, auth } = usePage<TemplateDemoPageProps>().props;

    const [payload, setPayload] = useState<DemoPayload>(demo);
    const [activePageSlug, setActivePageSlug] = useState<string>(
        demo.meta.active_page_slug || demo.active_page?.slug || demo.pages[0]?.slug || 'home'
    );
    const [refreshing, setRefreshing] = useState(false);
    const [previewRefreshToken, setPreviewRefreshToken] = useState<number>(Date.now());
    const [loadError, setLoadError] = useState<string | null>(null);

    const activePage = useMemo(() => {
        const found = payload.pages.find((page) => page.slug === activePageSlug);
        return found ?? payload.active_page ?? payload.pages[0] ?? null;
    }, [activePageSlug, payload.active_page, payload.pages]);

    const previewUrl = useMemo(() => {
        if (!activePage?.preview_url) {
            return null;
        }

        if (typeof window === 'undefined') {
            return activePage.preview_url;
        }

        try {
            const url = new URL(activePage.preview_url, window.location.origin);
            url.searchParams.set('preview_ts', String(previewRefreshToken));
            return `${url.pathname}${url.search}`;
        } catch {
            return activePage.preview_url;
        }
    }, [activePage?.preview_url, previewRefreshToken]);

    const fetchDemo = async (pageSlug?: string) => {
        const response = await axios.get<DemoPayload>(
            route('admin.ai-templates.demo-data', template.id),
            {
                params: pageSlug ? { page: pageSlug } : {},
            }
        );

        setPayload(response.data);
        const nextSlug = pageSlug
            ?? response.data.meta.active_page_slug
            ?? response.data.active_page?.slug
            ?? response.data.pages[0]?.slug;

        if (nextSlug) {
            setActivePageSlug(nextSlug);
        }
    };

    const handleSwitchPage = (pageSlug: string) => {
        if (refreshing || pageSlug === activePageSlug) {
            return;
        }

        setLoadError(null);
        setActivePageSlug(pageSlug);
        setPreviewRefreshToken(Date.now());
    };

    const handleRefresh = async () => {
        if (refreshing) {
            return;
        }

        setLoadError(null);
        setRefreshing(true);

        try {
            await fetchDemo(activePage?.slug);
            setPreviewRefreshToken(Date.now());
        } catch {
            setLoadError(t('Failed to refresh demo from backend.'));
        } finally {
            setRefreshing(false);
        }
    };

    return (
        <AdminLayout user={auth.user!} title={`${template.name} ${t('Demo')}`}>
            <Head title={`${template.name} · ${t('Live Demo')}`} />

            <AdminPageHeader
                title={`${template.name} · ${t('Live Demo')}`}
                subtitle={t('Demo preview now uses the same CMS page revisions and component structure used in editor.')}
                action={(
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={() => router.visit(route('admin.ai-templates'))}
                        >
                            <ArrowLeft className="me-2 h-4 w-4" />
                            {t('Back to templates')}
                        </Button>
                        <Button variant="outline" onClick={handleRefresh} disabled={refreshing}>
                            <RefreshCw className={`me-2 h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />
                            {t('Refresh from backend')}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => window.open(route('admin.ai-templates.live-builder', template.id), '_blank', 'noopener,noreferrer')}
                        >
                            <ExternalLink className="me-2 h-4 w-4" />
                            {t('Open Site Builder')}
                        </Button>
                    </div>
                )}
            />

            <div className="grid gap-4 lg:grid-cols-[320px_minmax(0,1fr)]">
                <aside className="space-y-4">
                    <div className="rounded-xl border bg-white p-4">
                        <div className="flex items-start gap-3">
                            {template.thumbnail_url ? (
                                <img src={template.thumbnail_url} alt={template.name} className="h-20 w-28 rounded-md border object-cover" />
                            ) : (
                                <div className="flex h-20 w-28 items-center justify-center rounded-md border bg-slate-100">
                                    <LayoutTemplate className="h-5 w-5 text-slate-500" />
                                </div>
                            )}
                            <div className="min-w-0">
                                <p className="font-semibold">{template.name}</p>
                                <p className="text-xs text-slate-500">{template.slug}</p>
                                <div className="mt-2 flex flex-wrap gap-1">
                                    <Badge variant="secondary" className="text-xs">{template.category ?? 'general'}</Badge>
                                    <Badge variant="outline" className="text-xs">v{template.version ?? '1.0.0'}</Badge>
                                </div>
                            </div>
                        </div>
                    </div>

                    {payload.demo_site && (
                        <div className="rounded-xl border bg-white p-4">
                            <p className="text-sm font-semibold">{t('Demo Site')}</p>
                            <div className="mt-3 space-y-1 text-xs text-slate-600">
                                <p><span className="font-semibold">Site ID:</span> {payload.demo_site.id}</p>
                                <p><span className="font-semibold">Project ID:</span> {payload.demo_site.project_id ?? '-'}</p>
                                <p><span className="font-semibold">Locale:</span> {payload.demo_site.locale ?? '-'}</p>
                            </div>
                        </div>
                    )}

                    <div className="rounded-xl border bg-white p-4">
                        <p className="text-sm font-semibold">{t('Pages')}</p>
                        <div className="mt-3 space-y-2">
                            {payload.pages.map((page) => (
                                <button
                                    key={page.slug}
                                    type="button"
                                    onClick={() => handleSwitchPage(page.slug)}
                                    disabled={refreshing}
                                    className={`w-full rounded-md border px-3 py-2 text-left transition ${
                                        page.slug === activePage?.slug
                                            ? 'border-primary bg-primary/5'
                                            : 'hover:bg-slate-50'
                                    }`}
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="text-sm font-medium">{page.title}</span>
                                        <Badge variant="outline" className="text-[10px]">
                                            {page.sections.length}
                                        </Badge>
                                    </div>
                                    <p className="mt-1 text-xs text-slate-500">{page.path}</p>
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-xl border bg-white p-4">
                        <p className="text-sm font-semibold">{t('Demo stats')}</p>
                        <div className="mt-3 grid grid-cols-3 gap-2 text-center">
                            <div className="rounded-md border p-2">
                                <p className="text-lg font-bold">{payload.stats.page_count}</p>
                                <p className="text-[11px] text-slate-500">{t('Pages')}</p>
                            </div>
                            <div className="rounded-md border p-2">
                                <p className="text-lg font-bold">{payload.stats.section_count}</p>
                                <p className="text-[11px] text-slate-500">{t('Sections')}</p>
                            </div>
                            <div className="rounded-md border p-2">
                                <p className="text-lg font-bold">{payload.stats.module_enabled_count}</p>
                                <p className="text-[11px] text-slate-500">{t('Modules')}</p>
                            </div>
                        </div>
                    </div>

                    <div className="rounded-xl border bg-white p-4">
                        <p className="text-sm font-semibold">{t('Modules')}</p>
                        <div className="mt-3 space-y-2">
                            {Object.entries(payload.module_flags).map(([moduleKey, enabled]) => (
                                <div key={moduleKey} className="flex items-center justify-between rounded-md border px-2 py-1.5 text-xs">
                                    <span>{toReadable(moduleKey)}</span>
                                    {enabled ? (
                                        <Badge variant="secondary" className="gap-1">
                                            <CheckCircle2 className="h-3 w-3" />
                                            {t('On')}
                                        </Badge>
                                    ) : (
                                        <Badge variant="outline" className="gap-1">
                                            <XCircle className="h-3 w-3" />
                                            {t('Off')}
                                        </Badge>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-xl border bg-white p-4">
                        <p className="text-sm font-semibold">{t('Typography tokens')}</p>
                        <div className="mt-3 space-y-2">
                            {Object.entries(payload.typography_tokens).map(([tokenKey, tokenValue]) => (
                                <div key={tokenKey} className="rounded-md border px-2 py-1.5 text-xs">
                                    <p className="text-slate-500">{toReadable(tokenKey)}</p>
                                    <p className="font-medium">{tokenValue}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </aside>

                <section className="space-y-4">
                    <div className="rounded-xl border bg-white">
                        <div className="flex items-center justify-between border-b px-4 py-2 text-xs text-slate-500">
                            <div className="flex items-center gap-2">
                                <Globe className="h-3.5 w-3.5" />
                                <span>{activePage?.path ?? '/'}</span>
                            </div>
                            <span>{payload.meta.generated_at}</span>
                        </div>
                        <div className="space-y-3 p-4">
                            {loadError && (
                                <div className="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
                                    {loadError}
                                </div>
                            )}

                            <div className="rounded-xl border bg-slate-50 p-3">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <h2 className="text-lg font-bold text-slate-900">{activePage?.title ?? t('Demo page')}</h2>
                                        <p className="text-xs text-slate-500">{activePage?.path ?? '/'}</p>
                                    </div>
                                    {previewUrl && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => window.open(previewUrl, '_blank', 'noopener,noreferrer')}
                                        >
                                            <Eye className="me-2 h-4 w-4" />
                                            {t('Open Preview')}
                                        </Button>
                                    )}
                                </div>
                            </div>

                            <div className="overflow-hidden rounded-xl border bg-slate-100">
                                {previewUrl ? (
                                    <iframe
                                        key={previewUrl}
                                        src={previewUrl}
                                        title={`${template.name} preview ${activePage?.slug ?? 'page'}`}
                                        className="h-[660px] w-full border-0 bg-white"
                                    />
                                ) : (
                                    <div className="flex h-[240px] items-center justify-center px-4 text-sm text-slate-500">
                                        {t('No preview URL is available for this page yet.')}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="rounded-xl border bg-white p-4">
                        <p className="text-sm font-semibold">{t('Components on this page')}</p>
                        <p className="mt-1 text-xs text-slate-500">
                            {t('This list is read from the same CMS page revision used by editor preview.')}
                        </p>

                        <div className="mt-3 space-y-3">
                            {(activePage?.sections ?? []).length === 0 && (
                                <div className="rounded-md border bg-slate-50 px-3 py-2 text-sm text-slate-500">
                                    {t('No components found for this page.')}
                                </div>
                            )}

                            {(activePage?.sections ?? []).map((section, index) => (
                                <article key={`${section.key}:${index}`} className="rounded-lg border p-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p className="text-sm font-semibold">{index + 1}. {section.label || toReadable(section.key)}</p>
                                            <p className="text-xs text-slate-500">{section.key}</p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Badge variant="outline" className="text-[10px]">{section.component}</Badge>
                                            {section.enabled ? (
                                                <Badge variant="secondary" className="text-[10px]">{t('Enabled')}</Badge>
                                            ) : (
                                                <Badge variant="outline" className="text-[10px]">{t('Disabled')}</Badge>
                                            )}
                                        </div>
                                    </div>

                                    <pre className="mt-2 max-h-52 overflow-auto rounded-md bg-slate-950 p-3 text-xs text-slate-100">
                                        {JSON.stringify(section.props ?? {}, null, 2)}
                                    </pre>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>
            </div>
        </AdminLayout>
    );
}
