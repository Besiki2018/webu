import { useState, useRef, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout';
import { useForm } from '@inertiajs/react';
import { useTranslation } from '@/contexts/LanguageContext';
import { toast } from 'sonner';
import { ColumnDef } from '@tanstack/react-table';
import { AdminPageHeader } from '@/components/Admin/AdminPageHeader';
import { TanStackDataTable } from '@/components/Admin/TanStackDataTable';
import { useAdminLoading } from '@/hooks/useAdminLoading';
import { TableSkeleton, TableColumnConfig } from '@/components/Admin/skeletons';
import { DataTableColumnHeader } from '@/components/ui/data-table-column-header';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    TableActionMenu,
    TableActionMenuTrigger,
    TableActionMenuContent,
    TableActionMenuItem,
    TableActionMenuSeparator,
} from '@/components/ui/table-action-menu';
import { Checkbox } from '@/components/ui/checkbox';
import {
    FileEdit,
    Trash2,
    Plus,
    Loader2,
    Search,
    Upload,
    File,
    X,
    Eye,
    Image as ImageIcon,
    CheckCircle2,
    XCircle,
    ExternalLink,
    LayoutGrid,
    List,
    Download,
    FolderInput,
} from 'lucide-react';
import type { PageProps } from '@/types';

interface Plan {
    id: number;
    name: string;
}

interface Template {
    id: number;
    slug: string;
    name: string;
    description: string;
    category: string;
    version: string;
    thumbnail: string | null;
    is_system: boolean;
    zip_path: string | null;
    metadata: object | null;
    plans: Plan[];
    created_at: string;
    updated_at: string;
}

interface TemplateMetadataPage {
    slug?: string;
    title?: string;
    sections?: string[];
}

interface TemplateMetadataSectionItem {
    key?: string;
    type?: string;
    enabled?: boolean;
    props?: Record<string, unknown>;
}

interface TemplateMetadata {
    vertical?: string;
    mobile_ready?: boolean;
    module_flags?: Record<string, boolean>;
    typography_tokens?: Record<string, string>;
    default_pages?: TemplateMetadataPage[];
    default_sections?: Record<string, Array<string | TemplateMetadataSectionItem>>;
}

interface TemplatePreviewSection {
    key: string;
    enabled: boolean;
    hasProps: boolean;
}

interface TemplatePreviewPage {
    slug: string;
    title: string;
    sections: TemplatePreviewSection[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedTemplates {
    data: Template[];
    current_page: number;
    from: number;
    last_page: number;
    links: PaginationLink[];
    per_page: number;
    to: number;
    total: number;
}

interface TemplatesPageProps extends PageProps {
    templates: PaginatedTemplates;
    plans: Plan[];
    filters?: {
        search?: string;
        per_page?: number;
    };
}

// Skeleton column configuration for Templates table
const skeletonColumns: TableColumnConfig[] = [
    { type: 'text', width: 'w-64' },     // Template (name + description)
    { type: 'text', width: 'w-32' },     // Slug
    { type: 'text', width: 'w-32' },     // Plans
    { type: 'actions', width: 'w-12' },  // Actions
];

const TEMPLATE_PREVIEW_PALETTES: Record<string, { wrapper: string; panel: string; accent: string; bar: string }> = {
    ecommerce: {
        wrapper: 'bg-gradient-to-br from-emerald-100 to-cyan-100 dark:from-emerald-950/70 dark:to-cyan-950/70',
        panel: 'bg-white/95 dark:bg-slate-900/95',
        accent: 'bg-emerald-500',
        bar: 'bg-slate-800/90',
    },
    business: {
        wrapper: 'bg-gradient-to-br from-sky-100 to-indigo-100 dark:from-sky-950/70 dark:to-indigo-950/70',
        panel: 'bg-white/95 dark:bg-slate-900/95',
        accent: 'bg-sky-500',
        bar: 'bg-slate-800/90',
    },
    medical: {
        wrapper: 'bg-gradient-to-br from-cyan-100 to-teal-100 dark:from-cyan-950/70 dark:to-teal-950/70',
        panel: 'bg-white/95 dark:bg-slate-900/95',
        accent: 'bg-cyan-500',
        bar: 'bg-slate-800/90',
    },
    restaurant: {
        wrapper: 'bg-gradient-to-br from-amber-100 to-orange-100 dark:from-amber-950/70 dark:to-orange-950/70',
        panel: 'bg-white/95 dark:bg-slate-900/95',
        accent: 'bg-amber-500',
        bar: 'bg-slate-800/90',
    },
    legal: {
        wrapper: 'bg-gradient-to-br from-slate-200 to-zinc-200 dark:from-slate-900 dark:to-zinc-900',
        panel: 'bg-white/95 dark:bg-slate-900/95',
        accent: 'bg-slate-600',
        bar: 'bg-slate-700/90',
    },
    portfolio: {
        wrapper: 'bg-gradient-to-br from-rose-100 to-pink-100 dark:from-rose-950/70 dark:to-pink-950/70',
        panel: 'bg-white/95 dark:bg-slate-900/95',
        accent: 'bg-rose-500',
        bar: 'bg-slate-800/90',
    },
    fashion: {
        wrapper: 'bg-gradient-to-br from-pink-100 to-rose-100 dark:from-pink-950/70 dark:to-rose-950/70',
        panel: 'bg-white/95 dark:bg-slate-900/95',
        accent: 'bg-pink-500',
        bar: 'bg-slate-800/90',
    },
    tech: {
        wrapper: 'bg-gradient-to-br from-blue-100 to-indigo-100 dark:from-blue-950/70 dark:to-indigo-950/70',
        panel: 'bg-white/95 dark:bg-slate-900/95',
        accent: 'bg-blue-500',
        bar: 'bg-slate-800/90',
    },
    nature: {
        wrapper: 'bg-gradient-to-br from-lime-100 to-green-100 dark:from-lime-950/70 dark:to-green-950/70',
        panel: 'bg-white/95 dark:bg-slate-900/95',
        accent: 'bg-lime-600',
        bar: 'bg-slate-800/90',
    },
    default: {
        wrapper: 'bg-gradient-to-br from-violet-100 to-fuchsia-100 dark:from-violet-950/70 dark:to-fuchsia-950/70',
        panel: 'bg-white/95 dark:bg-slate-900/95',
        accent: 'bg-violet-500',
        bar: 'bg-slate-800/90',
    },
};

/** Slug substrings → palette key for visual variety per template */
const SLUG_PALETTE_HINTS: Array<{ match: string; key: keyof typeof TEMPLATE_PREVIEW_PALETTES }> = [
    { match: 'fashion', key: 'fashion' },
    { match: 'cosmetics', key: 'fashion' },
    { match: 'beauty', key: 'fashion' },
    { match: 'jewelry', key: 'fashion' },
    { match: 'luxury', key: 'fashion' },
    { match: 'electronics', key: 'tech' },
    { match: 'gaming', key: 'tech' },
    { match: 'digital', key: 'tech' },
    { match: 'phone', key: 'tech' },
    { match: 'organic', key: 'nature' },
    { match: 'grocery', key: 'nature' },
    { match: 'food', key: 'restaurant' },
    { match: 'restaurant', key: 'restaurant' },
    { match: 'coffee', key: 'restaurant' },
    { match: 'booking', key: 'business' },
    { match: 'business', key: 'business' },
    { match: 'portfolio', key: 'portfolio' },
    { match: 'agency', key: 'portfolio' },
    { match: 'medical', key: 'medical' },
    { match: 'legal', key: 'legal' },
];

export default function Index() {
    const { templates, plans, auth, filters } = usePage<TemplatesPageProps>().props;
    const { t } = useTranslation();
    const { isLoading } = useAdminLoading();
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [editModalOpen, setEditModalOpen] = useState(false);
    const [editingTemplate, setEditingTemplate] = useState<Template | null>(null);
    const [previewModalOpen, setPreviewModalOpen] = useState(false);
    const [previewTemplate, setPreviewTemplate] = useState<Template | null>(null);
    const [searchValue, setSearchValue] = useState(filters?.search ?? '');
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [templateToDelete, setTemplateToDelete] = useState<Template | null>(null);
    type ViewMode = 'table' | 'gallery';
    const [viewMode, setViewMode] = useState<ViewMode>('gallery');
    const [importDialogOpen, setImportDialogOpen] = useState(false);
    const [importFile, setImportFile] = useState<File | null>(null);
    const [importName, setImportName] = useState('');
    const [importSlug, setImportSlug] = useState('');
    const [importing, setImporting] = useState(false);
    const [importSummary, setImportSummary] = useState<{ name: string; slug: string; pages_count: number; bindings_count: number; warnings: string[] } | null>(null);
    const [importPreviewError, setImportPreviewError] = useState<string | null>(null);
    const [importPreviewLoading, setImportPreviewLoading] = useState(false);

    // File upload refs and state
    const createThumbnailInputRef = useRef<HTMLInputElement>(null);
    const createZipInputRef = useRef<HTMLInputElement>(null);
    const editThumbnailInputRef = useRef<HTMLInputElement>(null);
    const editZipInputRef = useRef<HTMLInputElement>(null);
    const [createThumbnailFileName, setCreateThumbnailFileName] = useState<string | null>(null);
    const [createZipFileName, setCreateZipFileName] = useState<string | null>(null);
    const [editThumbnailFileName, setEditThumbnailFileName] = useState<string | null>(null);
    const [editZipFileName, setEditZipFileName] = useState<string | null>(null);

    // Create form
    const { data: createData, setData: setCreateData, post: createPost, processing: createProcessing, errors: createErrors, reset: createReset } = useForm({
        name: '',
        description: '',
        thumbnail: null as File | null,
        zip_file: null as File | null,
        plan_ids: [] as number[],
    });

    // Edit form
    const { data: editData, setData: setEditData, post: editPost, processing: editProcessing, errors: editErrors } = useForm({
        name: '',
        description: '',
        thumbnail: null as File | null,
        zip_file: null as File | null,
        plan_ids: [] as number[],
        _method: 'PUT' as const,
    });

    const handleSearch = (value: string) => {
        setSearchValue(value);
        router.get(
            route('admin.templates'),
            { search: value, page: 1 },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handlePageChange = (page: number) => {
        router.get(
            route('admin.templates'),
            { search: searchValue, page: page + 1 },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handlePageSizeChange = (size: number) => {
        router.get(
            route('admin.templates'),
            { search: searchValue, per_page: size, page: 1 },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleDeleteConfirm = () => {
        if (templateToDelete) {
            router.delete(route('admin.ai-templates.destroy', templateToDelete.id), {
                onSuccess: () => {
                    setDeleteDialogOpen(false);
                    setTemplateToDelete(null);
                    toast.success(t('Template deleted successfully'));
                },
                onError: () => toast.error(t('Failed to delete template')),
            });
        }
    };

    const handleImportFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        setImportFile(file);
        setImportSummary(null);
        setImportPreviewError(null);
        if (!file) return;
        setImportPreviewLoading(true);
        const formData = new FormData();
        formData.append('file', file);
        try {
            const { data } = await axios.post<{ valid?: boolean; name?: string; slug?: string; pages_count?: number; bindings_count?: number; warnings?: string[]; error?: string }>(
                route('admin.templates.import-pack-preview'),
                formData,
                { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
            );
            if (data.valid && data.name !== undefined) {
                setImportSummary({
                    name: data.name ?? '',
                    slug: data.slug ?? '',
                    pages_count: data.pages_count ?? 0,
                    bindings_count: data.bindings_count ?? 0,
                    warnings: data.warnings ?? [],
                });
            } else {
                setImportPreviewError(data.error || t('Validation failed'));
            }
        } catch (err: unknown) {
            const msg = axios.isAxiosError(err) ? (err.response?.data as { error?: string } | undefined)?.error : undefined;
            setImportPreviewError(msg ?? t('Failed to validate pack'));
        } finally {
            setImportPreviewLoading(false);
        }
    };

    const handleImportSubmit = async () => {
        if (!importFile) {
            toast.error(t('Please select a ZIP file'));
            return;
        }
        setImporting(true);
        const formData = new FormData();
        formData.append('file', importFile);
        if (importName.trim()) formData.append('name', importName.trim());
        if (importSlug.trim()) formData.append('slug', importSlug.trim());
        try {
            const { data } = await axios.post<{ success?: boolean; template?: { id: number; name: string; slug: string }; error?: string; warnings?: string[] }>(
                route('admin.templates.import-pack'),
                formData,
                { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
            );
            if (data.success && data.template) {
                toast.success(t('Template imported successfully'));
                setImportDialogOpen(false);
                setImportFile(null);
                setImportName('');
                setImportSlug('');
                setImportSummary(null);
                setImportPreviewError(null);
                router.reload();
            } else {
                toast.error(data.error || t('Import failed'));
            }
        } catch (e: unknown) {
            const msg = axios.isAxiosError(e) ? (e.response?.data as { error?: string } | undefined)?.error : undefined;
            toast.error(msg ?? t('Import failed'));
        } finally {
            setImporting(false);
        }
    };

    const openCreateModal = () => setCreateModalOpen(true);
    const closeCreateModal = () => {
        setCreateModalOpen(false);
        createReset();
        setCreateThumbnailFileName(null);
        setCreateZipFileName(null);
    };

    const openEditModal = (template: Template) => {
        setEditingTemplate(template);
        setEditData({
            name: template.name,
            description: template.description,
            thumbnail: null,
            zip_file: null,
            plan_ids: template.plans.map(p => p.id),
            _method: 'PUT',
        });
        setEditModalOpen(true);
    };

    const closeEditModal = () => {
        setEditModalOpen(false);
        setEditingTemplate(null);
        setEditThumbnailFileName(null);
        setEditZipFileName(null);
    };

    // Set edit preview when editingTemplate changes
    useEffect(() => {
        if (editingTemplate) {
            setEditThumbnailFileName(editingTemplate.thumbnail ? t('Existing image') : null);
            setEditZipFileName(editingTemplate.zip_path ? t('Existing file') : null);
        } else {
            setEditThumbnailFileName(null);
            setEditZipFileName(null);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [editingTemplate?.id]);

    useEffect(() => {
        setSearchValue(filters?.search ?? '');
    }, [filters?.search]);

    const getTemplateMetadata = (template: Template): TemplateMetadata => {
        if (!template.metadata || typeof template.metadata !== 'object' || Array.isArray(template.metadata)) {
            return {};
        }

        return template.metadata as TemplateMetadata;
    };

    const buildSlug = (value: string, fallback: string): string => {
        const slug = value
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');

        return slug || fallback;
    };

    const normalizeSections = (sections: Array<string | TemplateMetadataSectionItem> | undefined): TemplatePreviewSection[] => {
        if (!Array.isArray(sections)) {
            return [];
        }

        const normalized: TemplatePreviewSection[] = [];
        const dedupe = new Set<string>();

        sections.forEach((section, index) => {
            if (typeof section === 'string') {
                const key = section.trim();
                if (!key || dedupe.has(key)) {
                    return;
                }

                dedupe.add(key);
                normalized.push({
                    key,
                    enabled: true,
                    hasProps: false,
                });
                return;
            }

            if (!section || typeof section !== 'object') {
                return;
            }

            const keyCandidate = (section.key ?? section.type ?? `section-${index}`).toString().trim();
            if (!keyCandidate || dedupe.has(keyCandidate)) {
                return;
            }

            dedupe.add(keyCandidate);
            normalized.push({
                key: keyCandidate,
                enabled: section.enabled !== false,
                hasProps: Boolean(section.props && Object.keys(section.props).length > 0),
            });
        });

        return normalized;
    };

    const mergeSections = (
        primary: TemplatePreviewSection[],
        secondary: TemplatePreviewSection[]
    ): TemplatePreviewSection[] => {
        if (secondary.length === 0) {
            return primary;
        }

        if (primary.length === 0) {
            return secondary;
        }

        const merged = [...primary];
        const keys = new Set(primary.map((section) => section.key));

        secondary.forEach((section) => {
            if (keys.has(section.key)) {
                return;
            }

            keys.add(section.key);
            merged.push(section);
        });

        return merged;
    };

    const getPreviewPagesDetailed = (template: Template): TemplatePreviewPage[] => {
        const metadata = getTemplateMetadata(template);
        const pages = new Map<string, TemplatePreviewPage>();

        if (Array.isArray(metadata.default_pages)) {
            metadata.default_pages.forEach((page, index) => {
                if (!page || typeof page !== 'object') {
                    return;
                }

                const title = (page.title ?? page.slug ?? `Page ${index + 1}`).toString().trim() || `Page ${index + 1}`;
                const slug = buildSlug((page.slug ?? title ?? '').toString(), `page-${index + 1}`);
                const sections = normalizeSections(page.sections);

                pages.set(slug, {
                    slug,
                    title,
                    sections,
                });
            });
        }

        if (metadata.default_sections && typeof metadata.default_sections === 'object') {
            Object.entries(metadata.default_sections).forEach(([pageSlug, rawSections]) => {
                const slug = buildSlug(pageSlug, 'page');
                const sectionDefinitions = normalizeSections(rawSections);

                if (pages.has(slug)) {
                    const existing = pages.get(slug)!;
                    pages.set(slug, {
                        ...existing,
                        sections: mergeSections(existing.sections, sectionDefinitions),
                    });
                    return;
                }

                pages.set(slug, {
                    slug,
                    title: toReadableLabel(pageSlug),
                    sections: sectionDefinitions,
                });
            });
        }

        return Array.from(pages.values());
    };

    const getPreviewPalette = (template: Template) => {
        const slug = template.slug.toLowerCase();
        const category = (template.category || '').toLowerCase();

        for (const { match, key } of SLUG_PALETTE_HINTS) {
            if (slug.includes(match) && key in TEMPLATE_PREVIEW_PALETTES) {
                return TEMPLATE_PREVIEW_PALETTES[key];
            }
        }

        if (slug.includes('ecommerce') || category.includes('ecommerce')) return TEMPLATE_PREVIEW_PALETTES.ecommerce;
        if (slug.includes('medical') || category.includes('medical') || slug.includes('vet')) return TEMPLATE_PREVIEW_PALETTES.medical;
        if (slug.includes('restaurant') || category.includes('restaurant')) return TEMPLATE_PREVIEW_PALETTES.restaurant;
        if (slug.includes('legal') || category.includes('legal')) return TEMPLATE_PREVIEW_PALETTES.legal;
        if (slug.includes('business') || category.includes('business') || category.includes('construction')) return TEMPLATE_PREVIEW_PALETTES.business;
        if (slug.includes('portfolio') || slug.includes('agency')) return TEMPLATE_PREVIEW_PALETTES.portfolio;

        return TEMPLATE_PREVIEW_PALETTES.default;
    };

    const getPreviewPageTitles = (template: Template, limit?: number): string[] => {
        const titles = getPreviewPagesDetailed(template)
            .map((page) => page.title)
            .filter((value): value is string => Boolean(value));

        return typeof limit === 'number' ? titles.slice(0, limit) : titles;
    };

    const getPreviewSectionKeys = (template: Template, limit?: number): string[] => {
        const sectionKeys = new Set<string>();

        getPreviewPagesDetailed(template).forEach((page) => {
            page.sections.forEach((section) => {
                if (section.key.trim() !== '') {
                    sectionKeys.add(section.key);
                }
            });
        });

        const keys = Array.from(sectionKeys);

        return typeof limit === 'number' ? keys.slice(0, limit) : keys;
    };

    const getEnabledModuleKeys = (template: Template, limit?: number): string[] => {
        const metadata = getTemplateMetadata(template);
        if (!metadata.module_flags || typeof metadata.module_flags !== 'object') {
            return [];
        }

        const keys = Object.entries(metadata.module_flags)
            .filter(([, enabled]) => Boolean(enabled))
            .map(([moduleKey]) => moduleKey);

        return typeof limit === 'number' ? keys.slice(0, limit) : keys;
    };

    const getTypographyTokens = (template: Template): Record<string, string> => {
        const metadata = getTemplateMetadata(template);

        if (!metadata.typography_tokens || typeof metadata.typography_tokens !== 'object') {
            return {};
        }

        return metadata.typography_tokens;
    };

    const toReadableLabel = (value: string): string =>
        value
            .replace(/[_-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .replace(/\b\w/g, (char) => char.toUpperCase());

    const resolveThumbnailUrl = (thumbnail: string | null): string | null => {
        if (!thumbnail) return null;
        if (thumbnail.startsWith('http://') || thumbnail.startsWith('https://') || thumbnail.startsWith('/')) {
            return thumbnail;
        }

        return `/storage/${thumbnail}`;
    };

    const renderGeneratedPreview = (template: Template, compact = false) => {
        const palette = getPreviewPalette(template);
        const sectionKeys = getPreviewSectionKeys(template, 6);
        const pageTitles = getPreviewPageTitles(template, 4);
        const moduleKeys = getEnabledModuleKeys(template, 4);

        if (compact) {
            const bar = 'bar' in palette ? (palette as { bar?: string }).bar : 'bg-slate-800/90';
            return (
                <div className={`rounded-lg border-2 border-border/50 overflow-hidden shadow-md ${palette.wrapper}`}>
                    <div className={`h-5 flex items-center gap-1 px-1.5 ${bar}`}>
                        <span className="h-1.5 w-1.5 rounded-full bg-red-500/90" />
                        <span className="h-1.5 w-1.5 rounded-full bg-amber-500/90" />
                        <span className="h-1.5 w-1.5 rounded-full bg-emerald-500/90" />
                        <span className="flex-1 min-w-0 truncate text-[8px] text-white/70 pl-1 font-mono">
                            {template.slug}
                        </span>
                    </div>
                    <div className={`p-1.5 ${palette.panel} border-t border-border/50`}>
                        <div className={`h-1.5 rounded w-2/3 ${palette.accent} opacity-90`} />
                        <div className="mt-1 flex gap-0.5">
                            <div className="h-1.5 flex-1 rounded bg-muted-foreground/25" />
                            <div className="h-1.5 w-1/3 rounded bg-muted-foreground/20" />
                        </div>
                        <div className="mt-1 grid grid-cols-2 gap-0.5">
                            <div className="h-2 rounded bg-muted-foreground/20" />
                            <div className="h-2 rounded bg-muted-foreground/15" />
                        </div>
                    </div>
                </div>
            );
        }

        return (
            <div className={`h-[320px] rounded-lg border overflow-hidden p-4 ${palette.wrapper}`}>
                <div className={`h-full rounded-lg border shadow-sm ${palette.panel} p-4 flex flex-col gap-3`}>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-1.5">
                            <span className="h-2.5 w-2.5 rounded-full bg-red-400/80" />
                            <span className="h-2.5 w-2.5 rounded-full bg-amber-400/80" />
                            <span className="h-2.5 w-2.5 rounded-full bg-emerald-400/80" />
                        </div>
                        <Badge variant="outline" className="text-[10px] uppercase tracking-wide">
                            {toReadableLabel(template.category || 'general')}
                        </Badge>
                    </div>

                    <div className={`h-2 w-1/3 rounded-full ${palette.accent}`} />
                    <div className="space-y-1">
                        <div className="h-2 rounded-full bg-muted-foreground/30 w-full" />
                        <div className="h-2 rounded-full bg-muted-foreground/20 w-4/5" />
                    </div>

                    <div className="flex flex-wrap gap-1">
                        {pageTitles.length > 0 ? (
                            pageTitles.map((pageTitle) => (
                                <span key={pageTitle} className="rounded-full border px-2 py-0.5 text-[10px] text-muted-foreground">
                                    {toReadableLabel(pageTitle)}
                                </span>
                            ))
                        ) : (
                            <span className="rounded-full border px-2 py-0.5 text-[10px] text-muted-foreground">
                                {t('Starter page set')}
                            </span>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-2 mt-auto">
                        {(sectionKeys.length > 0 ? sectionKeys : ['hero', 'content', 'cta', 'contact'])
                            .slice(0, 4)
                            .map((sectionKey) => (
                                <div key={sectionKey} className="rounded-md border bg-background/70 p-2">
                                    <p className="text-[10px] text-muted-foreground truncate">
                                        {toReadableLabel(sectionKey)}
                                    </p>
                                </div>
                            ))}
                    </div>

                    {moduleKeys.length > 0 && (
                        <div className="flex flex-wrap gap-1">
                            {moduleKeys.map((moduleKey) => (
                                <Badge key={moduleKey} variant="secondary" className="text-[10px]">
                                    {toReadableLabel(moduleKey)}
                                </Badge>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        );
    };

    const openPreviewModal = (template: Template) => {
        setPreviewTemplate(template);
        setPreviewModalOpen(true);
    };

    const previewSectionKeys = previewTemplate ? getPreviewSectionKeys(previewTemplate, 8) : [];
    const previewPagesDetailed = previewTemplate ? getPreviewPagesDetailed(previewTemplate) : [];
    const previewModuleMatrix = previewTemplate
        ? Object.entries(getTemplateMetadata(previewTemplate).module_flags ?? {})
        : [];
    const previewTypographyTokens = previewTemplate ? Object.entries(getTypographyTokens(previewTemplate)) : [];
    const previewPageCount = previewTemplate ? getPreviewPageTitles(previewTemplate).length : 0;
    const previewSectionCount = previewTemplate ? getPreviewSectionKeys(previewTemplate).length : 0;
    const previewModuleCount = previewTemplate ? getEnabledModuleKeys(previewTemplate).length : 0;

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        createPost(route('admin.ai-templates.store'), {
            forceFormData: true,
            onSuccess: () => {
                closeCreateModal();
                toast.success(t('Template created successfully'));
            },
            onError: () => toast.error(t('Failed to create template')),
        });
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingTemplate) return;
        editPost(route('admin.ai-templates.update', editingTemplate.id), {
            forceFormData: true,
            onSuccess: () => {
                closeEditModal();
                toast.success(t('Template updated successfully'));
            },
            onError: () => toast.error(t('Failed to update template')),
        });
    };

    const columns: ColumnDef<Template>[] = [
        {
            accessorKey: 'name',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Template')} />
            ),
            cell: ({ row }) => {
                const thumbnailUrl = resolveThumbnailUrl(row.original.thumbnail);

                return (
                    <div className="flex items-start gap-3">
                        {thumbnailUrl ? (
                            <img
                                src={thumbnailUrl}
                                alt={`${row.original.name} preview`}
                                className="h-12 w-20 rounded-md border object-cover bg-muted shrink-0"
                                loading="lazy"
                            />
                        ) : (
                            <div className="shrink-0">
                                {renderGeneratedPreview(row.original, true)}
                            </div>
                        )}

                        <div className="min-w-0">
                            <div className="flex items-center gap-2">
                                <span className="font-medium">{row.original.name}</span>
                                {row.original.is_system && (
                                    <Badge variant="secondary" className="text-xs">
                                        {t('System')}
                                    </Badge>
                                )}
                            </div>
                            <div className="text-sm text-muted-foreground line-clamp-1">
                                {row.original.description}
                            </div>
                            <div className="mt-1 flex items-center gap-3">
                                <button
                                    type="button"
                                    onClick={() => openPreviewModal(row.original)}
                                    className="text-xs font-semibold text-primary hover:underline"
                                >
                                    {t('View details')}
                                </button>
                                <a
                                    href={route('admin.ai-templates.live-demo', row.original.id)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-xs font-semibold text-primary hover:underline"
                                >
                                    {t('Open demo')}
                                </a>
                                <a
                                    href={route('admin.ai-templates.live-builder', row.original.id)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-xs font-semibold text-primary hover:underline"
                                >
                                    {t('Open site builder')}
                                </a>
                            </div>
                        </div>
                    </div>
                );
            },
        },
        {
            accessorKey: 'slug',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Slug')} />
            ),
            cell: ({ row }) => (
                <code className="text-sm text-muted-foreground">{row.original.slug}</code>
            ),
        },
        {
            accessorKey: 'plans',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Plans')} />
            ),
            cell: ({ row }) => (
                <div className="flex flex-wrap gap-1">
                    {row.original.is_system ? (
                        <Badge variant="outline" className="text-xs">
                            {t('All Plans')}
                        </Badge>
                    ) : row.original.plans.length > 0 ? (
                        row.original.plans.map((plan) => (
                            <Badge key={plan.id} variant="secondary" className="text-xs">
                                {plan.name}
                            </Badge>
                        ))
                    ) : (
                        <span className="text-sm text-muted-foreground">{t('None')}</span>
                    )}
                </div>
            ),
        },
        {
            id: 'actions',
            enableHiding: false,
            cell: ({ row }) => {
                const template = row.original;
                return (
                    <TableActionMenu>
                        <TableActionMenuTrigger />
                        <TableActionMenuContent>
                            <TableActionMenuItem onClick={() => openPreviewModal(template)}>
                                <Eye className="me-2 h-4 w-4" />
                                {t('Preview')}
                            </TableActionMenuItem>
                            <TableActionMenuItem
                                onClick={() => router.visit(route('admin.ai-templates.live-demo', template.id))}
                            >
                                <ExternalLink className="me-2 h-4 w-4" />
                                {t('Open Demo')}
                            </TableActionMenuItem>
                            <TableActionMenuItem
                                onClick={() => router.visit(route('admin.ai-templates.live-builder', template.id))}
                            >
                                <ExternalLink className="me-2 h-4 w-4" />
                                {t('Open Site Builder')}
                            </TableActionMenuItem>
                            <TableActionMenuItem
                                onClick={() => window.open(route('admin.ai-templates.export-pack', template.id), '_blank', 'noopener')}
                            >
                                <Download className="me-2 h-4 w-4" />
                                {t('Export Template Pack')}
                            </TableActionMenuItem>
                            <TableActionMenuSeparator />
                            {!template.is_system && (
                                <>
                                    <TableActionMenuItem onClick={() => openEditModal(template)}>
                                        <FileEdit className="me-2 h-4 w-4" />
                                        {t('Edit')}
                                    </TableActionMenuItem>
                                    <TableActionMenuSeparator />
                                </>
                            )}
                            <TableActionMenuItem
                                variant="destructive"
                                disabled={template.is_system}
                                className={template.is_system ? 'opacity-50 cursor-not-allowed' : ''}
                                onClick={() => {
                                    if (!template.is_system) {
                                        setTemplateToDelete(template);
                                        setDeleteDialogOpen(true);
                                    }
                                }}
                            >
                                <Trash2 className="me-2 h-4 w-4" />
                                {t('Delete')}
                            </TableActionMenuItem>
                        </TableActionMenuContent>
                    </TableActionMenu>
                );
            },
        },
    ];

    if (isLoading) {
        return (
            <AdminLayout user={auth.user!} title={t('AI Templates')}>
                <AdminPageHeader
                    title={t('AI Templates')}
                    subtitle={t('Manage AI starter templates')}
                />
                <TableSkeleton columns={skeletonColumns} rows={10} showSearch filterCount={0} />
            </AdminLayout>
        );
    }

    return (
        <AdminLayout user={auth.user!} title={t('AI Templates')}>
            <Head title={t('AI Templates')} />

            <AdminPageHeader
                title={t('AI Templates')}
                subtitle={t('Manage AI starter templates')}
                action={
                    <div className="flex items-center gap-2">
                        <Button variant="outline" onClick={() => setImportDialogOpen(true)}>
                            <FolderInput className="h-4 w-4 me-2" />
                            {t('Import Template Pack')}
                        </Button>
                        <Button onClick={openCreateModal}>
                            <Plus className="h-4 w-4 me-2" />
                            {t('Add Template')}
                        </Button>
                    </div>
                }
            />

            <div className="space-y-4">
                {/* Search + View toggle */}
                <div className="flex items-center justify-between gap-4 flex-wrap">
                    <div className="relative max-w-sm">
                        <Search className="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input
                            placeholder={t('Search templates...')}
                            value={searchValue}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="ps-9 w-[300px]"
                        />
                    </div>
                    <div className="flex items-center gap-1 rounded-lg border p-1 bg-muted/30">
                        <Button
                            type="button"
                            variant={viewMode === 'gallery' ? 'secondary' : 'ghost'}
                            size="sm"
                            className="h-8 px-2"
                            onClick={() => setViewMode('gallery')}
                            title={t('Gallery view – see all templates visually')}
                        >
                            <LayoutGrid className="h-4 w-4" />
                        </Button>
                        <Button
                            type="button"
                            variant={viewMode === 'table' ? 'secondary' : 'ghost'}
                            size="sm"
                            className="h-8 px-2"
                            onClick={() => setViewMode('table')}
                            title={t('Table view')}
                        >
                            <List className="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                {/* Gallery view – visual cards */}
                {viewMode === 'gallery' && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                        {templates.data.map((template) => {
                            const thumbnailUrl = resolveThumbnailUrl(template.thumbnail);
                            const liveDemoHref = route('admin.ai-templates.live-demo', template.id);
                            return (
                                <div
                                    key={template.id}
                                    className="group rounded-xl border-2 border-border/80 bg-card overflow-hidden flex flex-col shadow-md hover:shadow-xl hover:border-primary/30 transition-all duration-200"
                                >
                                    <a
                                        href={liveDemoHref}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="relative block aspect-[4/3] bg-muted/20 overflow-hidden shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary ring-inset"
                                    >
                                        {thumbnailUrl ? (
                                            <img
                                                src={thumbnailUrl}
                                                alt={template.name}
                                                className="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-200"
                                                loading="lazy"
                                            />
                                        ) : (
                                            <div className="w-full h-full flex items-center justify-center p-3 min-h-[140px]">
                                                {renderGeneratedPreview(template, true)}
                                            </div>
                                        )}
                                        <div className="absolute inset-0 flex items-center justify-center bg-black/0 group-hover:bg-black/20 transition-colors pointer-events-none">
                                            <span className="opacity-0 group-hover:opacity-100 text-white text-sm font-medium bg-black/50 px-3 py-1.5 rounded-full transition-opacity">
                                                {t('View live design')}
                                            </span>
                                        </div>
                                    </a>
                                    <div className="p-3 flex flex-col flex-1 min-w-0">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <span className="font-semibold text-sm truncate" title={template.name}>
                                                {template.name}
                                            </span>
                                            {template.is_system && (
                                                <Badge variant="secondary" className="text-[10px] shrink-0">
                                                    {t('System')}
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-xs text-muted-foreground mt-0.5 line-clamp-2">
                                            {template.description}
                                        </p>
                                        <div className="mt-2 flex flex-wrap gap-1">
                                            <Badge variant="outline" className="text-[10px] capitalize">
                                                {template.category || t('General')}
                                            </Badge>
                                        </div>
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            <a
                                                href={liveDemoHref}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
                                            >
                                                <Eye className="h-3.5 w-3.5" />
                                                {t('View live design')}
                                            </a>
                                            <a
                                                href={route('admin.ai-templates.live-builder', template.id)}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground"
                                            >
                                                <ExternalLink className="h-3.5 w-3.5" />
                                                {t('Open builder')}
                                            </a>
                                            <button
                                                type="button"
                                                onClick={() => openPreviewModal(template)}
                                                className="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground"
                                            >
                                                {t('Details')}
                                            </button>
                                            <a
                                                href={route('admin.ai-templates.export-pack', template.id)}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground"
                                            >
                                                <Download className="h-3.5 w-3.5" />
                                                {t('Export Pack')}
                                            </a>
                                        </div>
                                        {!template.is_system && (
                                            <div className="mt-2 pt-2 border-t flex gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-7 text-xs"
                                                    onClick={() => openEditModal(template)}
                                                >
                                                    <FileEdit className="h-3 w-3 me-1" />
                                                    {t('Edit')}
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-7 text-xs text-destructive hover:text-destructive"
                                                    onClick={() => {
                                                        setTemplateToDelete(template);
                                                        setDeleteDialogOpen(true);
                                                    }}
                                                >
                                                    <Trash2 className="h-3 w-3 me-1" />
                                                    {t('Delete')}
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* Pagination for gallery */}
                {viewMode === 'gallery' && templates.data.length > 0 && (
                    <div className="flex items-center justify-between gap-4 flex-wrap">
                        <p className="text-sm text-muted-foreground">
                            {t('Showing {{from}}-{{to}} of {{total}} templates', {
                                from: templates.from,
                                to: templates.to,
                                total: templates.total,
                            })}
                        </p>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={templates.current_page <= 1}
                                onClick={() => handlePageChange(templates.current_page - 2)}
                            >
                                {t('Previous')}
                            </Button>
                            <span className="text-sm text-muted-foreground">
                                {t('Page {{current}} of {{last}}', {
                                    current: templates.current_page,
                                    last: templates.last_page,
                                })}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={templates.current_page >= templates.last_page}
                                onClick={() => handlePageChange(templates.current_page)}
                            >
                                {t('Next')}
                            </Button>
                        </div>
                    </div>
                )}

                {/* Table view */}
                {viewMode === 'table' && (
                <TanStackDataTable
                    columns={columns}
                    data={templates.data}
                    showSearch={false}
                    serverPagination={{
                        pageCount: templates.last_page,
                        pageIndex: templates.current_page - 1,
                        pageSize: templates.per_page,
                        total: templates.total,
                        onPageChange: handlePageChange,
                        onPageSizeChange: handlePageSizeChange,
                    }}
                />
                )}
            </div>

            {/* Preview Dialog */}
            <Dialog
                open={previewModalOpen}
                onOpenChange={(open) => {
                    setPreviewModalOpen(open);
                    if (!open) {
                        setPreviewTemplate(null);
                    }
                }}
            >
                <DialogContent className="max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>{previewTemplate?.name ?? t('Template Preview')}</DialogTitle>
                        <DialogDescription>
                            {previewTemplate?.description ?? t('Visual preview of selected template')}
                        </DialogDescription>
                    </DialogHeader>

                    {previewTemplate && (
                        <div className="space-y-4">
                            <div className="rounded-lg border bg-muted/20 overflow-hidden">
                                {resolveThumbnailUrl(previewTemplate.thumbnail) ? (
                                    <img
                                        src={resolveThumbnailUrl(previewTemplate.thumbnail)!}
                                        alt={`${previewTemplate.name} preview`}
                                        className="w-full h-[320px] object-cover"
                                    />
                                ) : (
                                    renderGeneratedPreview(previewTemplate)
                                )}
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                <div className="rounded-md border p-3">
                                    <p className="text-muted-foreground">{t('Slug')}</p>
                                    <p className="font-medium">{previewTemplate.slug}</p>
                                </div>
                                <div className="rounded-md border p-3">
                                    <p className="text-muted-foreground">{t('Category')}</p>
                                    <p className="font-medium capitalize">{previewTemplate.category || t('General')}</p>
                                </div>
                                <div className="rounded-md border p-3">
                                    <p className="text-muted-foreground">{t('Version')}</p>
                                    <p className="font-medium">{previewTemplate.version || '1.0.0'}</p>
                                </div>
                                <div className="rounded-md border p-3">
                                    <p className="text-muted-foreground">{t('Pages in metadata')}</p>
                                    <p className="font-medium">
                                        {previewPageCount}
                                    </p>
                                </div>
                                <div className="rounded-md border p-3">
                                    <p className="text-muted-foreground">{t('Section blocks')}</p>
                                    <p className="font-medium">
                                        {previewSectionCount}
                                    </p>
                                </div>
                                <div className="rounded-md border p-3">
                                    <p className="text-muted-foreground">{t('Enabled modules')}</p>
                                    <p className="font-medium">
                                        {previewModuleCount}
                                    </p>
                                </div>
                            </div>

                            {previewSectionKeys.length > 0 && (
                                <div className="space-y-2">
                                    <p className="text-sm text-muted-foreground">{t('Detected sections')}</p>
                                    <div className="flex flex-wrap gap-2">
                                        {previewSectionKeys.map((sectionKey) => (
                                            <Badge key={sectionKey} variant="outline" className="text-xs">
                                                {toReadableLabel(sectionKey)}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {previewPagesDetailed.length > 0 && (
                                <div className="space-y-2">
                                    <p className="text-sm text-muted-foreground">{t('Page blueprint')}</p>
                                    <div className="max-h-[300px] overflow-y-auto space-y-3 pe-1">
                                        {previewPagesDetailed.map((page) => (
                                            <div key={page.slug} className="rounded-md border p-3 space-y-2">
                                                <div className="flex items-center justify-between gap-2">
                                                    <p className="font-medium">{page.title}</p>
                                                    <Badge variant="outline" className="text-xs">
                                                        /{page.slug}
                                                    </Badge>
                                                </div>
                                                <div className="flex flex-wrap gap-2">
                                                    {page.sections.length > 0 ? (
                                                        page.sections.map((section) => (
                                                            <Badge
                                                                key={`${page.slug}:${section.key}`}
                                                                variant={section.enabled ? 'secondary' : 'outline'}
                                                                className="text-xs gap-1"
                                                            >
                                                                {section.enabled ? (
                                                                    <CheckCircle2 className="h-3 w-3" />
                                                                ) : (
                                                                    <XCircle className="h-3 w-3" />
                                                                )}
                                                                {toReadableLabel(section.key)}
                                                                {section.hasProps ? ' *' : ''}
                                                            </Badge>
                                                        ))
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground">
                                                            {t('No section definitions')}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {previewModuleMatrix.length > 0 && (
                                <div className="space-y-2">
                                    <p className="text-sm text-muted-foreground">{t('Module matrix')}</p>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                                        {previewModuleMatrix.map(([moduleKey, enabled]) => (
                                            <div key={moduleKey} className="rounded-md border p-2 flex items-center justify-between gap-2">
                                                <span className="text-sm font-medium">{toReadableLabel(moduleKey)}</span>
                                                {enabled ? (
                                                    <Badge variant="secondary" className="text-xs gap-1">
                                                        <CheckCircle2 className="h-3 w-3" />
                                                        {t('Enabled')}
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline" className="text-xs gap-1">
                                                        <XCircle className="h-3 w-3" />
                                                        {t('Disabled')}
                                                    </Badge>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {previewTypographyTokens.length > 0 && (
                                <div className="space-y-2">
                                    <p className="text-sm text-muted-foreground">{t('Typography tokens')}</p>
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-2">
                                        {previewTypographyTokens.map(([tokenKey, tokenValue]) => (
                                            <div key={tokenKey} className="rounded-md border p-2">
                                                <p className="text-xs text-muted-foreground">{toReadableLabel(tokenKey)}</p>
                                                <p className="text-sm font-medium">{tokenValue}</p>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            <div className="pt-2 flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    onClick={() => {
                                        if (!previewTemplate) {
                                            return;
                                        }

                                        setPreviewModalOpen(false);
                                        router.visit(route('admin.ai-templates.demo', previewTemplate.id));
                                    }}
                                >
                                    <ExternalLink className="me-2 h-4 w-4" />
                                    {t('Open Full Demo')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        if (!previewTemplate) {
                                            return;
                                        }

                                        window.open(route('admin.ai-templates.live-builder', previewTemplate.id), '_blank', 'noopener,noreferrer');
                                    }}
                                >
                                    <ExternalLink className="me-2 h-4 w-4" />
                                    {t('Open Site Builder')}
                                </Button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Dialog */}
            <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Delete Template')}</DialogTitle>
                        <DialogDescription>
                            {templateToDelete?.is_system ? (
                                <span className="text-destructive font-medium">
                                    {t('System templates cannot be deleted.')}
                                </span>
                            ) : (
                                <>{t('Are you sure you want to delete ":name"? This action cannot be undone.', { name: templateToDelete?.name || '' })}</>
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    {!templateToDelete?.is_system && (
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setDeleteDialogOpen(false)}>
                                {t('Cancel')}
                            </Button>
                            <Button variant="destructive" onClick={handleDeleteConfirm}>
                                {t('Delete Template')}
                            </Button>
                        </DialogFooter>
                    )}
                </DialogContent>
            </Dialog>

            {/* Import Template Pack Dialog */}
            <Dialog open={importDialogOpen} onOpenChange={(o) => { setImportDialogOpen(o); if (!o) { setImportFile(null); setImportName(''); setImportSlug(''); setImportSummary(null); setImportPreviewError(null); } }}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{t('Import Template Pack')}</DialogTitle>
                        <DialogDescription>
                            {t('Upload a Webu Template Pack ZIP. Layout and bindings are preserved; CSS and tokens can be updated.')}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label>{t('ZIP file')}</Label>
                            <Input
                                type="file"
                                accept=".zip"
                                className="mt-1"
                                onChange={handleImportFileChange}
                            />
                            {importPreviewLoading && (
                                <p className="text-sm text-muted-foreground mt-1 flex items-center gap-2">
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                    {t('Validating…')}
                                </p>
                            )}
                            {importPreviewError && (
                                <p className="text-sm text-destructive mt-1">{importPreviewError}</p>
                            )}
                            {importSummary && !importPreviewError && (
                                <div className="mt-2 rounded-md border bg-muted/30 p-3 text-sm">
                                    <p className="font-medium">{importSummary.name}</p>
                                    <p className="text-muted-foreground">{t('Slug')}: {importSummary.slug}</p>
                                    <p className="text-muted-foreground">{t('Pages')}: {importSummary.pages_count} · {t('Bindings')}: {importSummary.bindings_count}</p>
                                    {importSummary.warnings.length > 0 && (
                                        <ul className="mt-1 list-disc list-inside text-amber-600 dark:text-amber-400 text-xs">
                                            {importSummary.warnings.slice(0, 3).map((w, i) => (
                                                <li key={i}>{w}</li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            )}
                        </div>
                        <div>
                            <Label>{t('Template name (optional)')}</Label>
                            <Input
                                value={importName}
                                onChange={(e) => setImportName(e.target.value)}
                                placeholder={importSummary?.name ?? t('From manifest if empty')}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <Label>{t('Slug (optional)')}</Label>
                            <Input
                                value={importSlug}
                                onChange={(e) => setImportSlug(e.target.value)}
                                placeholder={importSummary?.slug ?? t('From manifest if empty')}
                                className="mt-1"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setImportDialogOpen(false)}>{t('Cancel')}</Button>
                        <Button onClick={handleImportSubmit} disabled={!importFile || importing || !!importPreviewError}>
                            {importing ? <Loader2 className="h-4 w-4 animate-spin me-2" /> : null}
                            {t('Import')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Create Modal */}
            <Dialog open={createModalOpen} onOpenChange={setCreateModalOpen}>
                <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{t('Add Template')}</DialogTitle>
                        <DialogDescription>
                            {t('Upload a new starter template for AI projects')}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleCreateSubmit} className="space-y-4">
                        {/* Name */}
                        <div className="space-y-2">
                            <Label htmlFor="create-name">{t('Template Name')} *</Label>
                            <Input
                                id="create-name"
                                value={createData.name}
                                onChange={(e) => setCreateData('name', e.target.value)}
                                placeholder={t('e.g. Landing Page, Dashboard, E-commerce')}
                                required
                            />
                            {createErrors.name && (
                                <p className="text-sm text-destructive">{createErrors.name}</p>
                            )}
                        </div>

                        {/* Description */}
                        <div className="space-y-2">
                            <Label htmlFor="create-description">{t('Description')} *</Label>
                            <Textarea
                                id="create-description"
                                value={createData.description}
                                onChange={(e) => setCreateData('description', e.target.value)}
                                placeholder={t('Brief description of what this template includes')}
                                rows={2}
                                required
                            />
                            {createErrors.description && (
                                <p className="text-sm text-destructive">{createErrors.description}</p>
                            )}
                        </div>

                        {/* Thumbnail */}
                        <div className="space-y-2">
                            <Label>{t('Preview Thumbnail')}</Label>
                            <div className="flex items-center gap-3">
                                {createThumbnailFileName ? (
                                    <>
                                        <ImageIcon className="h-5 w-5 text-muted-foreground shrink-0" />
                                        <span className="text-sm flex-1 truncate">{createThumbnailFileName}</span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="h-8 w-8"
                                            onClick={() => {
                                                setCreateThumbnailFileName(null);
                                                setCreateData('thumbnail', null);
                                            }}
                                        >
                                            <X className="h-4 w-4" />
                                        </Button>
                                    </>
                                ) : (
                                    <>
                                        <input
                                            ref={createThumbnailInputRef}
                                            type="file"
                                            accept="image/*"
                                            className="hidden"
                                            onChange={(e) => {
                                                const file = e.target.files?.[0];
                                                if (file) {
                                                    setCreateThumbnailFileName(file.name);
                                                    setCreateData('thumbnail', file);
                                                }
                                            }}
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => createThumbnailInputRef.current?.click()}
                                        >
                                            <Upload className="h-4 w-4 me-2" />
                                            {t('Choose Image')}
                                        </Button>
                                        <span className="text-xs text-muted-foreground">{t('Optional')}</span>
                                    </>
                                )}
                            </div>
                            {createErrors.thumbnail && (
                                <p className="text-sm text-destructive">{createErrors.thumbnail}</p>
                            )}
                        </div>

                        {/* ZIP File */}
                        <div className="space-y-2">
                            <Label>{t('Template ZIP File')} *</Label>
                            <div className="flex items-center gap-3">
                                {createZipFileName ? (
                                    <>
                                        <File className="h-5 w-5 text-muted-foreground shrink-0" />
                                        <span className="text-sm flex-1 truncate">{createZipFileName}</span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="h-8 w-8"
                                            onClick={() => {
                                                setCreateZipFileName(null);
                                                setCreateData('zip_file', null);
                                            }}
                                        >
                                            <X className="h-4 w-4" />
                                        </Button>
                                    </>
                                ) : (
                                    <>
                                        <input
                                            ref={createZipInputRef}
                                            type="file"
                                            accept=".zip"
                                            className="hidden"
                                            onChange={(e) => {
                                                const file = e.target.files?.[0];
                                                if (file) {
                                                    setCreateZipFileName(file.name);
                                                    setCreateData('zip_file', file);
                                                }
                                            }}
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => createZipInputRef.current?.click()}
                                        >
                                            <Upload className="h-4 w-4 me-2" />
                                            {t('Choose ZIP File')}
                                        </Button>
                                        <span className="text-xs text-muted-foreground">{t('Max 10MB')}</span>
                                    </>
                                )}
                            </div>
                            {createErrors.zip_file && (
                                <p className="text-sm text-destructive">{createErrors.zip_file}</p>
                            )}
                        </div>

                        {/* Available to Plans */}
                        <div className="space-y-2">
                            <Label>{t('Available to Plans')}</Label>
                            <div className="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto border rounded-md p-3">
                                {plans.map((plan) => (
                                    <div key={plan.id} className="flex items-center gap-2">
                                        <Checkbox
                                            id={`create-plan-${plan.id}`}
                                            checked={createData.plan_ids.includes(plan.id)}
                                            onCheckedChange={(checked) => {
                                                if (checked) {
                                                    setCreateData('plan_ids', [...createData.plan_ids, plan.id]);
                                                } else {
                                                    setCreateData('plan_ids', createData.plan_ids.filter(id => id !== plan.id));
                                                }
                                            }}
                                        />
                                        <Label htmlFor={`create-plan-${plan.id}`} className="text-sm font-normal cursor-pointer">
                                            {plan.name}
                                        </Label>
                                    </div>
                                ))}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {t('Select which plans can use this template. System templates are always available to all plans.')}
                            </p>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeCreateModal}>
                                {t('Cancel')}
                            </Button>
                            <Button type="submit" disabled={createProcessing}>
                                {createProcessing ? (
                                    <>
                                        <Loader2 className="h-4 w-4 me-2 animate-spin" />
                                        {t('Uploading...')}
                                    </>
                                ) : (
                                    t('Upload Template')
                                )}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Modal */}
            <Dialog open={editModalOpen} onOpenChange={setEditModalOpen}>
                <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{t('Edit Template')}</DialogTitle>
                        <DialogDescription>
                            {t('Update template details and files')}
                        </DialogDescription>
                    </DialogHeader>
                    {editingTemplate && (
                        <form onSubmit={handleEditSubmit} className="space-y-4">
                            {/* Name */}
                            <div className="space-y-2">
                                <Label htmlFor="edit-name">{t('Template Name')} *</Label>
                                <Input
                                    id="edit-name"
                                    value={editData.name}
                                    onChange={(e) => setEditData('name', e.target.value)}
                                    placeholder={t('e.g. Landing Page, Dashboard, E-commerce')}
                                    required
                                />
                                {editErrors.name && (
                                    <p className="text-sm text-destructive">{editErrors.name}</p>
                                )}
                            </div>

                            {/* Description */}
                            <div className="space-y-2">
                                <Label htmlFor="edit-description">{t('Description')} *</Label>
                                <Textarea
                                    id="edit-description"
                                    value={editData.description}
                                    onChange={(e) => setEditData('description', e.target.value)}
                                    placeholder={t('Brief description of what this template includes')}
                                    rows={2}
                                    required
                                />
                                {editErrors.description && (
                                    <p className="text-sm text-destructive">{editErrors.description}</p>
                                )}
                            </div>

                            {/* Thumbnail */}
                            <div className="space-y-2">
                                <Label>{t('Preview Thumbnail')}</Label>
                                <div className="flex items-center gap-3">
                                    {editThumbnailFileName ? (
                                        <>
                                            <ImageIcon className="h-5 w-5 text-muted-foreground shrink-0" />
                                            <span className="text-sm flex-1 truncate">
                                                {editingTemplate.thumbnail && !editData.thumbnail ? t('Existing image') : editThumbnailFileName}
                                            </span>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="h-8 w-8"
                                                onClick={() => {
                                                    setEditThumbnailFileName(null);
                                                    setEditData('thumbnail', null);
                                                }}
                                            >
                                                <X className="h-4 w-4" />
                                            </Button>
                                        </>
                                    ) : (
                                        <>
                                            <input
                                                ref={editThumbnailInputRef}
                                                type="file"
                                                accept="image/*"
                                                className="hidden"
                                                onChange={(e) => {
                                                    const file = e.target.files?.[0];
                                                    if (file) {
                                                        setEditThumbnailFileName(file.name);
                                                        setEditData('thumbnail', file);
                                                    }
                                                }}
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => editThumbnailInputRef.current?.click()}
                                            >
                                                <Upload className="h-4 w-4 me-2" />
                                                {t('Choose Image')}
                                            </Button>
                                            <span className="text-xs text-muted-foreground">{t('Leave empty to keep current')}</span>
                                        </>
                                    )}
                                </div>
                                {editErrors.thumbnail && (
                                    <p className="text-sm text-destructive">{editErrors.thumbnail}</p>
                                )}
                            </div>

                            {/* ZIP File */}
                            <div className="space-y-2">
                                <Label>{t('Template ZIP File')}</Label>
                                <div className="flex items-center gap-3">
                                    {editZipFileName ? (
                                        <>
                                            <File className="h-5 w-5 text-muted-foreground shrink-0" />
                                            <span className="text-sm flex-1 truncate">
                                                {editingTemplate.zip_path && !editData.zip_file ? t('Existing file') : editZipFileName}
                                            </span>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="h-8 w-8"
                                                onClick={() => {
                                                    setEditZipFileName(null);
                                                    setEditData('zip_file', null);
                                                }}
                                            >
                                                <X className="h-4 w-4" />
                                            </Button>
                                        </>
                                    ) : (
                                        <>
                                            <input
                                                ref={editZipInputRef}
                                                type="file"
                                                accept=".zip"
                                                className="hidden"
                                                onChange={(e) => {
                                                    const file = e.target.files?.[0];
                                                    if (file) {
                                                        setEditZipFileName(file.name);
                                                        setEditData('zip_file', file);
                                                    }
                                                }}
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => editZipInputRef.current?.click()}
                                            >
                                                <Upload className="h-4 w-4 me-2" />
                                                {t('Choose ZIP File')}
                                            </Button>
                                            <span className="text-xs text-muted-foreground">{t('Leave empty to keep current')}</span>
                                        </>
                                    )}
                                </div>
                                {editErrors.zip_file && (
                                    <p className="text-sm text-destructive">{editErrors.zip_file}</p>
                                )}
                            </div>

                            {/* Available to Plans */}
                            <div className="space-y-2">
                                <Label>{t('Available to Plans')}</Label>
                                {editingTemplate?.is_system ? (
                                    <div className="border rounded-md p-3 bg-muted/50">
                                        <p className="text-sm text-muted-foreground">
                                            {t('System templates are automatically available to all plans.')}
                                        </p>
                                    </div>
                                ) : (
                                    <>
                                        <div className="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto border rounded-md p-3">
                                            {plans.map((plan) => (
                                                <div key={plan.id} className="flex items-center gap-2">
                                                    <Checkbox
                                                        id={`edit-plan-${plan.id}`}
                                                        checked={editData.plan_ids.includes(plan.id)}
                                                        onCheckedChange={(checked) => {
                                                            if (checked) {
                                                                setEditData('plan_ids', [...editData.plan_ids, plan.id]);
                                                            } else {
                                                                setEditData('plan_ids', editData.plan_ids.filter(id => id !== plan.id));
                                                            }
                                                        }}
                                                    />
                                                    <Label htmlFor={`edit-plan-${plan.id}`} className="text-sm font-normal cursor-pointer">
                                                        {plan.name}
                                                    </Label>
                                                </div>
                                            ))}
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {t('Select which plans can use this template.')}
                                        </p>
                                    </>
                                )}
                            </div>

                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={closeEditModal}>
                                    {t('Cancel')}
                                </Button>
                                <Button type="submit" disabled={editProcessing}>
                                    {editProcessing ? (
                                        <>
                                            <Loader2 className="h-4 w-4 me-2 animate-spin" />
                                            {t('Saving...')}
                                        </>
                                    ) : (
                                        t('Save Changes')
                                    )}
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
