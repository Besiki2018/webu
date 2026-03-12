import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import AdminLayout from '@/Layouts/AdminLayout';
import { AdminPageHeader } from '@/components/Admin/AdminPageHeader';
import { TanStackDataTable } from '@/components/Admin/TanStackDataTable';
import { useAdminLoading } from '@/hooks/useAdminLoading';
import { TableSkeleton, type TableColumnConfig } from '@/components/Admin/skeletons';
import { DataTableColumnHeader } from '@/components/ui/data-table-column-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    TableActionMenu,
    TableActionMenuContent,
    TableActionMenuItem,
    TableActionMenuLabel,
    TableActionMenuSeparator,
    TableActionMenuTrigger,
} from '@/components/ui/table-action-menu';
import type { AdminCmsSection, AdminCmsSectionsPageProps } from '@/types/admin';
import { toast } from 'sonner';
import { useTranslation } from '@/contexts/LanguageContext';
import { Plus, Pencil, Trash2, UploadCloud, Layers } from 'lucide-react';

interface SectionFormState {
    key: string;
    category: string;
    enabled: boolean;
    schema_json: string;
}

const DEFAULT_SCHEMA = JSON.stringify(
    {
        type: 'object',
        properties: {
            title: { type: 'string' },
            subtitle: { type: 'string' },
        },
        _meta: {
            label: 'New Section',
            description: 'Custom uploaded section',
            design_variant: 'custom/v1',
            backend_updatable: true,
            bindings: {
                title: 'content.title',
                subtitle: 'content.subtitle',
            },
        },
    },
    null,
    2
);

const skeletonColumns: TableColumnConfig[] = [
    { type: 'text', width: 'w-48' },
    { type: 'badge', width: 'w-24' },
    { type: 'text', width: 'w-28' },
    { type: 'badge', width: 'w-20' },
    { type: 'text', width: 'w-28' },
    { type: 'date', width: 'w-24' },
    { type: 'actions', width: 'w-12' },
];

export default function CmsSections({
    user,
    sections,
    categories,
    filters,
    stats,
}: AdminCmsSectionsPageProps) {
    const { t, locale } = useTranslation();
    const { isLoading } = useAdminLoading();

    const [searchValue, setSearchValue] = useState(filters.search);
    const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
    const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
    const [selectedSection, setSelectedSection] = useState<AdminCmsSection | null>(null);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [createFile, setCreateFile] = useState<File | null>(null);
    const [editFile, setEditFile] = useState<File | null>(null);
    const [formData, setFormData] = useState<SectionFormState>({
        key: '',
        category: categories[0] ?? 'marketing',
        enabled: true,
        schema_json: DEFAULT_SCHEMA,
    });

    const categoriesWithFallback = useMemo(() => {
        const all = new Set<string>(categories);
        if (formData.category) {
            all.add(formData.category);
        }

        return [...all].sort();
    }, [categories, formData.category]);

    const resetForm = () => {
        setFormData({
            key: '',
            category: categories[0] ?? 'marketing',
            enabled: true,
            schema_json: DEFAULT_SCHEMA,
        });
        setFormErrors({});
        setCreateFile(null);
        setEditFile(null);
    };

    const navigateWithFilters = (next: Record<string, string | undefined>) => {
        const params = {
            search: filters.search || undefined,
            category: filters.category || 'all',
            status: filters.status || 'all',
            ...next,
        };

        if (!params.search) {
            delete params.search;
        }

        router.get(route('admin.cms-sections'), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const applySearch = () => {
        navigateWithFilters({
            search: searchValue.trim() || undefined,
        });
    };

    const toFormData = (mode: 'create' | 'edit'): FormData => {
        const payload = new FormData();

        if (mode === 'create') {
            payload.append('key', formData.key.trim());
        } else if (formData.key.trim() !== '') {
            payload.append('key', formData.key.trim());
        }

        payload.append('category', formData.category.trim());
        payload.append('enabled', formData.enabled ? '1' : '0');

        const file = mode === 'create' ? createFile : editFile;
        if (file) {
            payload.append('schema_file', file);
        } else {
            payload.append('schema_json', formData.schema_json);
        }

        return payload;
    };

    const handleCreate = () => {
        router.post(route('admin.cms-sections.store'), toFormData('create'), {
            forceFormData: true,
            onSuccess: () => {
                setIsCreateDialogOpen(false);
                resetForm();
                toast.success(t('Section template uploaded successfully'));
            },
            onError: (errors) => {
                setFormErrors(errors as Record<string, string>);
                toast.error((Object.values(errors)[0] as string) ?? t('Failed to upload section'));
            },
        });
    };

    const handleEdit = () => {
        if (!selectedSection) {
            return;
        }

        const payload = toFormData('edit');
        payload.append('_method', 'put');

        router.post(route('admin.cms-sections.update', selectedSection.id), payload, {
            forceFormData: true,
            onSuccess: () => {
                setIsEditDialogOpen(false);
                setSelectedSection(null);
                resetForm();
                toast.success(t('Section template updated successfully'));
            },
            onError: (errors) => {
                setFormErrors(errors as Record<string, string>);
                toast.error((Object.values(errors)[0] as string) ?? t('Failed to update section'));
            },
        });
    };

    const handleDelete = (section: AdminCmsSection) => {
        if (!window.confirm(t('Delete this section template?'))) {
            return;
        }

        router.delete(route('admin.cms-sections.destroy', section.id), {
            onSuccess: () => toast.success(t('Section template deleted')),
            onError: () => toast.error(t('Failed to delete section template')),
        });
    };

    const openEditDialog = (section: AdminCmsSection) => {
        setSelectedSection(section);
        setFormData({
            key: section.key,
            category: section.category,
            enabled: section.enabled,
            schema_json: JSON.stringify(section.schema_json ?? {}, null, 2),
        });
        setFormErrors({});
        setEditFile(null);
        setIsEditDialogOpen(true);
    };

    const handleImportDefaults = () => {
        router.post(route('admin.cms-sections.import-defaults'), {}, {
            onSuccess: () => toast.success(t('Default design pack imported')),
            onError: () => toast.error(t('Failed to import default section pack')),
        });
    };

    const columns: ColumnDef<AdminCmsSection>[] = [
        {
            accessorKey: 'key',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Section')} />
            ),
            cell: ({ row }) => {
                const section = row.original;
                return (
                    <div className="space-y-1">
                        <p className="font-medium">{section.meta.label || section.key}</p>
                        <p className="text-xs text-muted-foreground">{section.key}</p>
                        {section.meta.description && (
                            <p className="text-xs text-muted-foreground line-clamp-1">
                                {section.meta.description}
                            </p>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'category',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Category')} />
            ),
            cell: ({ row }) => (
                <Badge variant="outline">{row.original.category}</Badge>
            ),
        },
        {
            accessorKey: 'meta.design_variant',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Design Variant')} />
            ),
            cell: ({ row }) => row.original.meta.design_variant ?? '—',
        },
        {
            accessorKey: 'enabled',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Status')} />
            ),
            cell: ({ row }) => (
                <Badge variant={row.original.enabled ? 'default' : 'secondary'}>
                    {row.original.enabled ? t('Enabled') : t('Disabled')}
                </Badge>
            ),
        },
        {
            id: 'bindings',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Backend Bindings')} />
            ),
            cell: ({ row }) => {
                const bindings = row.original.meta.bindings ?? {};
                const entries = Object.entries(bindings);

                if (entries.length === 0) {
                    return <span className="text-muted-foreground">—</span>;
                }

                return (
                    <div className="space-y-1">
                        <p className="text-sm">{entries.length} {t('fields')}</p>
                        <p className="text-xs text-muted-foreground line-clamp-1">
                            {entries.slice(0, 2).map(([field, target]) => `${field}→${target}`).join(', ')}
                        </p>
                    </div>
                );
            },
        },
        {
            accessorKey: 'updated_at',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Updated')} />
            ),
            cell: ({ row }) => row.original.updated_at
                ? new Date(row.original.updated_at).toLocaleDateString(locale)
                : '—',
        },
        {
            id: 'actions',
            enableHiding: false,
            cell: ({ row }) => {
                const section = row.original;

                return (
                    <TableActionMenu>
                        <TableActionMenuTrigger />
                        <TableActionMenuContent align="end">
                            <TableActionMenuLabel>{t('Actions')}</TableActionMenuLabel>
                            <TableActionMenuItem onClick={() => openEditDialog(section)}>
                                <Pencil className="me-2 h-4 w-4" />
                                {t('Edit')}
                            </TableActionMenuItem>
                            <TableActionMenuSeparator />
                            <TableActionMenuItem
                                variant="destructive"
                                onClick={() => handleDelete(section)}
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

    return (
        <AdminLayout user={user} title={t('CMS Sections')}>
            <AdminPageHeader
                title={t('CMS Sections')}
                subtitle={t('Upload and categorize reusable section designs')}
                action={
                    <div className="flex items-center gap-2">
                        <Button variant="outline" onClick={handleImportDefaults}>
                            <Layers className="h-4 w-4 me-2" />
                            {t('Import Default Pack')}
                        </Button>
                        <Dialog
                            open={isCreateDialogOpen}
                            onOpenChange={(open) => {
                                setIsCreateDialogOpen(open);
                                if (!open) {
                                    resetForm();
                                }
                            }}
                        >
                            <DialogTrigger asChild>
                                <Button>
                                    <Plus className="h-4 w-4 me-2" />
                                    {t('Upload Section')}
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="sm:max-w-3xl">
                                <DialogHeader>
                                    <DialogTitle>{t('Upload Section Template')}</DialogTitle>
                                    <DialogDescription>
                                        {t('Create a section in a target category and map it to backend-updatable schema.')}
                                    </DialogDescription>
                                </DialogHeader>
                                <SectionForm
                                    mode="create"
                                    categories={categoriesWithFallback}
                                    formData={formData}
                                    setFormData={setFormData}
                                    file={createFile}
                                    setFile={setCreateFile}
                                    errors={formErrors}
                                    t={t}
                                />
                                <DialogFooter>
                                    <Button variant="outline" onClick={() => setIsCreateDialogOpen(false)}>
                                        {t('Cancel')}
                                    </Button>
                                    <Button onClick={handleCreate}>
                                        <UploadCloud className="h-4 w-4 me-2" />
                                        {t('Upload')}
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                }
            />

            <div className="mb-6 rounded-lg border bg-card p-4">
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-6">
                    <div className="space-y-2 lg:col-span-2">
                        <Label htmlFor="section-search">{t('Search')}</Label>
                        <div className="flex gap-2">
                            <Input
                                id="section-search"
                                value={searchValue}
                                onChange={(event) => setSearchValue(event.target.value)}
                                placeholder={t('Search key/category')}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        applySearch();
                                    }
                                }}
                            />
                            <Button variant="outline" onClick={applySearch}>
                                {t('Search')}
                            </Button>
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label>{t('Category')}</Label>
                        <Select
                            value={filters.category || 'all'}
                            onValueChange={(value) => navigateWithFilters({ category: value })}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('All')}</SelectItem>
                                {categories.map((category) => (
                                    <SelectItem key={category} value={category}>
                                        {category}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label>{t('Status')}</Label>
                        <Select
                            value={filters.status}
                            onValueChange={(value: 'all' | 'enabled' | 'disabled') => navigateWithFilters({ status: value })}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('All')}</SelectItem>
                                <SelectItem value="enabled">{t('Enabled')}</SelectItem>
                                <SelectItem value="disabled">{t('Disabled')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label>{t('Totals')}</Label>
                        <div className="text-sm rounded-md border px-3 py-2 bg-muted/40">
                            {stats.total} / {stats.enabled}
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label>{t('Preset Pack')}</Label>
                        <div className="text-sm rounded-md border px-3 py-2 bg-muted/40">
                            {stats.preset_count} {t('designs')}
                        </div>
                    </div>
                </div>
            </div>

            {isLoading ? (
                <TableSkeleton columns={skeletonColumns} rows={10} showSearch={false} />
            ) : (
                <TanStackDataTable
                    columns={columns}
                    data={sections}
                    showSearch={false}
                    showPagination={sections.length > 10}
                />
            )}

            <Dialog
                open={isEditDialogOpen}
                onOpenChange={(open) => {
                    setIsEditDialogOpen(open);
                    if (!open) {
                        setSelectedSection(null);
                        resetForm();
                    }
                }}
            >
                <DialogContent className="sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>{t('Edit Section Template')}</DialogTitle>
                        <DialogDescription>
                            {t('Update category, enabled state, and backend schema mapping.')}
                        </DialogDescription>
                    </DialogHeader>
                    <SectionForm
                        mode="edit"
                        categories={categoriesWithFallback}
                        formData={formData}
                        setFormData={setFormData}
                        file={editFile}
                        setFile={setEditFile}
                        errors={formErrors}
                        t={t}
                    />
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsEditDialogOpen(false)}>
                            {t('Cancel')}
                        </Button>
                        <Button onClick={handleEdit}>
                            <Pencil className="h-4 w-4 me-2" />
                            {t('Save Changes')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}

interface SectionFormProps {
    mode: 'create' | 'edit';
    categories: string[];
    formData: SectionFormState;
    setFormData: (value: SectionFormState) => void;
    file: File | null;
    setFile: (value: File | null) => void;
    errors: Record<string, string>;
    t: (key: string, replacements?: Record<string, string | number>) => string;
}

function SectionForm({
    mode,
    categories,
    formData,
    setFormData,
    file,
    setFile,
    errors,
    t,
}: SectionFormProps) {
    return (
        <div className="space-y-4 py-4">
            <div className="grid gap-3 md:grid-cols-2">
                <div className="space-y-2">
                    <Label>{t('Key')}</Label>
                    <Input
                        value={formData.key}
                        onChange={(event) =>
                            setFormData({
                                ...formData,
                                key: event.target.value.toLowerCase().replace(/\s+/g, '_'),
                            })
                        }
                        placeholder="hero_split_image"
                        disabled={mode === 'edit'}
                        className={errors.key ? 'border-destructive' : ''}
                    />
                    {errors.key && <p className="text-xs text-destructive">{errors.key}</p>}
                </div>
                <div className="space-y-2">
                    <Label>{t('Category')}</Label>
                    <Input
                        value={formData.category}
                        list="section-category-list"
                        onChange={(event) =>
                            setFormData({
                                ...formData,
                                category: event.target.value,
                            })
                        }
                        placeholder="marketing"
                        className={errors.category ? 'border-destructive' : ''}
                    />
                    <datalist id="section-category-list">
                        {categories.map((category) => (
                            <option key={category} value={category} />
                        ))}
                    </datalist>
                    {errors.category && <p className="text-xs text-destructive">{errors.category}</p>}
                </div>
            </div>

            <div className="rounded-md border p-3 flex items-center justify-between">
                <div>
                    <p className="text-sm font-medium">{t('Enabled')}</p>
                    <p className="text-xs text-muted-foreground">{t('Section is available in CMS editor')}</p>
                </div>
                <Switch
                    checked={formData.enabled}
                    onCheckedChange={(checked) =>
                        setFormData({
                            ...formData,
                            enabled: checked,
                        })
                    }
                />
            </div>

            <div className="space-y-2">
                <Label>{t('Schema JSON File (optional)')}</Label>
                <Input
                    type="file"
                    accept=".json,application/json,text/plain"
                    onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                />
                {file ? (
                    <p className="text-xs text-muted-foreground">{file.name}</p>
                ) : (
                    <p className="text-xs text-muted-foreground">
                        {t('If no file is selected, schema JSON text below will be used.')}
                    </p>
                )}
                {errors.schema_file && <p className="text-xs text-destructive">{errors.schema_file}</p>}
            </div>

            <div className="space-y-2">
                <Label>{t('Schema JSON')}</Label>
                <Textarea
                    value={formData.schema_json}
                    onChange={(event) =>
                        setFormData({
                            ...formData,
                            schema_json: event.target.value,
                        })
                    }
                    rows={14}
                    className={errors.schema_json ? 'border-destructive' : ''}
                />
                {errors.schema_json && <p className="text-xs text-destructive">{errors.schema_json}</p>}
            </div>
        </div>
    );
}
