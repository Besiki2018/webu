import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import AdminLayout from '@/Layouts/AdminLayout';
import { AdminPageHeader } from '@/components/Admin/AdminPageHeader';
import { TanStackDataTable } from '@/components/Admin/TanStackDataTable';
import { useAdminLoading } from '@/hooks/useAdminLoading';
import { TableSkeleton, type TableColumnConfig } from '@/components/Admin/skeletons';
import { DataTableColumnHeader } from '@/components/ui/data-table-column-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
    TableActionMenu,
    TableActionMenuContent,
    TableActionMenuItem,
    TableActionMenuLabel,
    TableActionMenuTrigger,
} from '@/components/ui/table-action-menu';
import type {
    AdminProject,
    AdminProjectOwner,
    AdminProjectTemplate,
    AdminProjectsPageProps,
} from '@/types/admin';
import { Plus, Pencil } from 'lucide-react';
import { toast } from 'sonner';
import { useTranslation } from '@/contexts/LanguageContext';
import { THEME_PRESETS } from '@/lib/theme-presets';

const skeletonColumns: TableColumnConfig[] = [
    { type: 'text', width: 'w-48' },
    { type: 'avatar-text', width: 'w-40' },
    { type: 'badge', width: 'w-24' },
    { type: 'badge', width: 'w-24' },
    { type: 'date', width: 'w-24' },
    { type: 'actions', width: 'w-12' },
];

interface ProjectFormData {
    name: string;
    description: string;
    owner_user_id: string;
    is_public: boolean;
    template_id: string;
    theme_preset: string;
}

export default function Projects({
    user,
    projects,
    pagination,
    filters,
    owners,
    templates,
    build_status_options,
}: AdminProjectsPageProps) {
    const { t, locale } = useTranslation();
    const { isLoading } = useAdminLoading();

    const [searchValue, setSearchValue] = useState(filters.search ?? '');
    const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
    const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
    const [selectedProject, setSelectedProject] = useState<AdminProject | null>(null);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [formData, setFormData] = useState<ProjectFormData>({
        name: '',
        description: '',
        owner_user_id: filters.owner_user_id || '',
        is_public: false,
        template_id: 'automatic',
        theme_preset: 'automatic',
    });

    const ownerMap = useMemo(() => {
        return new Map(owners.map((owner) => [owner.id.toString(), owner]));
    }, [owners]);

    const resetForm = () => {
        setFormData({
            name: '',
            description: '',
            owner_user_id: filters.owner_user_id || '',
            is_public: false,
            template_id: 'automatic',
            theme_preset: 'automatic',
        });
        setFormErrors({});
    };

    const navigateWithFilters = (extra: Record<string, string | number | undefined>) => {
        const params: Record<string, string | number | undefined> = {
            search: filters.search || undefined,
            state: filters.state,
            owner_user_id: filters.owner_user_id || undefined,
            build_status: filters.build_status,
            publish_status: filters.publish_status,
            sort: filters.sort,
            per_page: filters.per_page,
            ...extra,
        };

        if (!params.search) {
            delete params.search;
        }

        if (!params.owner_user_id) {
            delete params.owner_user_id;
        }

        router.get(route('admin.projects'), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const applySearch = () => {
        navigateWithFilters({
            search: searchValue.trim() || undefined,
            page: 1,
        });
    };

    const handleCreate = () => {
        router.post(route('admin.projects.store'), {
            ...formData,
            template_id: formData.template_id === 'automatic' ? null : Number(formData.template_id),
            theme_preset: formData.theme_preset === 'automatic' ? null : formData.theme_preset,
        }, {
            onSuccess: () => {
                setIsCreateDialogOpen(false);
                resetForm();
                toast.success(t('Project created successfully'));
            },
            onError: (errors) => {
                setFormErrors(errors as Record<string, string>);
            },
        });
    };

    const handleEdit = () => {
        if (!selectedProject) {
            return;
        }

        router.put(route('admin.projects.update', selectedProject.id), {
            ...formData,
            template_id: formData.template_id === 'automatic' ? null : Number(formData.template_id),
            theme_preset: formData.theme_preset === 'automatic' ? null : formData.theme_preset,
        }, {
            onSuccess: () => {
                setIsEditDialogOpen(false);
                setSelectedProject(null);
                resetForm();
                toast.success(t('Project updated successfully'));
            },
            onError: (errors) => {
                setFormErrors(errors as Record<string, string>);
            },
        });
    };

    const openEditDialog = (project: AdminProject) => {
        setSelectedProject(project);
        setFormData({
            name: project.name,
            description: project.description ?? '',
            owner_user_id: project.owner?.id?.toString() ?? '',
            is_public: project.is_public,
            template_id: project.template?.id ? project.template.id.toString() : 'automatic',
            theme_preset: project.theme_preset ?? 'automatic',
        });
        setFormErrors({});
        setIsEditDialogOpen(true);
    };

    const columns: ColumnDef<AdminProject>[] = [
        {
            accessorKey: 'name',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Project')} />
            ),
            cell: ({ row }) => {
                const project = row.original;
                return (
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <span className="font-medium">{project.name}</span>
                            <Badge variant={project.is_public ? 'default' : 'secondary'}>
                                {project.is_public ? t('Public') : t('Private')}
                            </Badge>
                            {project.deleted_at && (
                                <Badge variant="destructive">{t('Trashed')}</Badge>
                            )}
                        </div>
                        <p className="text-xs text-muted-foreground">{project.id}</p>
                        {project.description && (
                            <p className="text-sm text-muted-foreground line-clamp-1">
                                {project.description}
                            </p>
                        )}
                        {project.template && (
                            <p className="text-xs text-muted-foreground">
                                {t('Template')}: {project.template.name}
                            </p>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'owner',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Owner')} />
            ),
            cell: ({ row }) => {
                const owner = row.original.owner;
                if (!owner) {
                    return <span className="text-muted-foreground">-</span>;
                }

                return (
                    <div>
                        <p className="font-medium">{owner.name}</p>
                        <p className="text-sm text-muted-foreground">{owner.email}</p>
                    </div>
                );
            },
        },
        {
            accessorKey: 'build_status',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Build')} />
            ),
            cell: ({ row }) => {
                const status = row.original.build_status || 'none';
                return <Badge variant="outline">{status}</Badge>;
            },
        },
        {
            accessorKey: 'is_published',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Published')} />
            ),
            cell: ({ row }) => {
                const project = row.original;
                return (
                    <div className="space-y-1">
                        <Badge variant={project.is_published ? 'default' : 'secondary'}>
                            {project.is_published ? t('Published') : t('Unpublished')}
                        </Badge>
                        <p className="text-xs text-muted-foreground">
                            {project.custom_domain || project.subdomain || '-'}
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
            cell: ({ row }) => new Date(row.original.updated_at).toLocaleDateString(locale),
        },
        {
            id: 'actions',
            enableHiding: false,
            cell: ({ row }) => {
                const project = row.original;
                return (
                    <TableActionMenu>
                        <TableActionMenuTrigger />
                        <TableActionMenuContent align="end">
                            <TableActionMenuLabel>{t('Actions')}</TableActionMenuLabel>
                            <TableActionMenuItem onClick={() => openEditDialog(project)}>
                                <Pencil className="me-2 h-4 w-4" />
                                {t('Edit')}
                            </TableActionMenuItem>
                        </TableActionMenuContent>
                    </TableActionMenu>
                );
            },
        },
    ];

    return (
        <AdminLayout user={user} title={t('Projects')}>
            <AdminPageHeader
                title={t('Projects')}
                subtitle={t('Manage and assign all projects')}
                action={
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
                                {t('Create Project')}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>{t('Create Project')}</DialogTitle>
                                <DialogDescription>
                                    {t('Create a new project and assign it to a user')}
                                </DialogDescription>
                            </DialogHeader>

                            <ProjectForm
                                formData={formData}
                                setFormData={setFormData}
                                owners={owners}
                                ownerMap={ownerMap}
                                templates={templates}
                                formErrors={formErrors}
                                t={t}
                            />

                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    onClick={() => setIsCreateDialogOpen(false)}
                                >
                                    {t('Cancel')}
                                </Button>
                                <Button onClick={handleCreate}>{t('Create Project')}</Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                }
            />

            <div className="mb-6 rounded-lg border bg-card p-4">
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-6">
                    <div className="space-y-2 lg:col-span-2">
                        <Label htmlFor="project-search">{t('Search')}</Label>
                        <div className="flex gap-2">
                            <Input
                                id="project-search"
                                placeholder={t('Search projects, owner, or ID')}
                                value={searchValue}
                                onChange={(event) => setSearchValue(event.target.value)}
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
                        <Label>{t('Owner')}</Label>
                        <Select
                            value={filters.owner_user_id || 'all'}
                            onValueChange={(value) =>
                                navigateWithFilters({
                                    owner_user_id: value === 'all' ? undefined : value,
                                    page: 1,
                                })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={t('All owners')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('All owners')}</SelectItem>
                                {owners.map((owner: AdminProjectOwner) => (
                                    <SelectItem key={owner.id} value={owner.id.toString()}>
                                        {owner.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label>{t('State')}</Label>
                        <Select
                            value={filters.state}
                            onValueChange={(value) =>
                                navigateWithFilters({
                                    state: value,
                                    page: 1,
                                })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">{t('Active')}</SelectItem>
                                <SelectItem value="trashed">{t('Trashed')}</SelectItem>
                                <SelectItem value="all">{t('All')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label>{t('Build')}</Label>
                        <Select
                            value={filters.build_status}
                            onValueChange={(value) =>
                                navigateWithFilters({
                                    build_status: value,
                                    page: 1,
                                })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('All')}</SelectItem>
                                <SelectItem value="none">{t('None')}</SelectItem>
                                {build_status_options.map((status) => (
                                    <SelectItem key={status} value={status}>
                                        {status}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label>{t('Publish')}</Label>
                        <Select
                            value={filters.publish_status}
                            onValueChange={(value) =>
                                navigateWithFilters({
                                    publish_status: value,
                                    page: 1,
                                })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('All')}</SelectItem>
                                <SelectItem value="published">{t('Published')}</SelectItem>
                                <SelectItem value="unpublished">{t('Unpublished')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </div>

            {isLoading ? (
                <TableSkeleton columns={skeletonColumns} rows={10} showSearch={false} />
            ) : (
                <TanStackDataTable
                    columns={columns}
                    data={projects.data}
                    showSearch={false}
                    serverPagination={{
                        pageCount: pagination.last_page,
                        pageIndex: pagination.current_page - 1,
                        pageSize: pagination.per_page,
                        total: pagination.total,
                        onPageChange: (page) =>
                            navigateWithFilters({
                                page: page + 1,
                            }),
                        onPageSizeChange: (size) =>
                            navigateWithFilters({
                                per_page: size,
                                page: 1,
                            }),
                    }}
                />
            )}

            <Dialog
                open={isEditDialogOpen}
                onOpenChange={(open) => {
                    setIsEditDialogOpen(open);
                    if (!open) {
                        setSelectedProject(null);
                        resetForm();
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Edit Project')}</DialogTitle>
                        <DialogDescription>
                            {t('Update project details and assignment')}
                        </DialogDescription>
                    </DialogHeader>

                    <ProjectForm
                        formData={formData}
                        setFormData={setFormData}
                        owners={owners}
                        ownerMap={ownerMap}
                        templates={templates}
                        formErrors={formErrors}
                        t={t}
                    />

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsEditDialogOpen(false)}>
                            {t('Cancel')}
                        </Button>
                        <Button onClick={handleEdit}>{t('Save Changes')}</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}

interface ProjectFormProps {
    formData: ProjectFormData;
    setFormData: (value: ProjectFormData) => void;
    owners: AdminProjectOwner[];
    ownerMap: Map<string, AdminProjectOwner>;
    templates: AdminProjectTemplate[];
    formErrors: Record<string, string>;
    t: (key: string, replacements?: Record<string, string | number>) => string;
}

function ProjectForm({
    formData,
    setFormData,
    owners,
    ownerMap,
    templates,
    formErrors,
    t,
}: ProjectFormProps) {
    return (
        <div className="space-y-4 py-4">
            <div className="space-y-2">
                <Label htmlFor="project-name">{t('Project Name')}</Label>
                <Input
                    id="project-name"
                    value={formData.name}
                    onChange={(event) => setFormData({ ...formData, name: event.target.value })}
                    className={formErrors.name ? 'border-destructive' : ''}
                />
                {formErrors.name && (
                    <p className="text-sm text-destructive">{formErrors.name}</p>
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor="project-description">{t('Description')}</Label>
                <Textarea
                    id="project-description"
                    value={formData.description}
                    onChange={(event) =>
                        setFormData({ ...formData, description: event.target.value })
                    }
                    rows={4}
                    className={formErrors.description ? 'border-destructive' : ''}
                />
                {formErrors.description && (
                    <p className="text-sm text-destructive">{formErrors.description}</p>
                )}
            </div>

            <div className="space-y-2">
                <Label>{t('Owner')}</Label>
                <Select
                    value={formData.owner_user_id || 'none'}
                    onValueChange={(value) =>
                        setFormData({
                            ...formData,
                            owner_user_id: value === 'none' ? '' : value,
                        })
                    }
                >
                    <SelectTrigger className={formErrors.owner_user_id ? 'border-destructive' : ''}>
                        <SelectValue placeholder={t('Select owner')} />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">{t('Select owner')}</SelectItem>
                        {owners.map((owner) => (
                            <SelectItem key={owner.id} value={owner.id.toString()}>
                                {owner.name} ({owner.email})
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {formData.owner_user_id && ownerMap.get(formData.owner_user_id) && (
                    <p className="text-xs text-muted-foreground">
                        {ownerMap.get(formData.owner_user_id)?.email}
                    </p>
                )}
                {formErrors.owner_user_id && (
                    <p className="text-sm text-destructive">{formErrors.owner_user_id}</p>
                )}
            </div>

            <div className="space-y-2">
                <Label>{t('Template')}</Label>
                <Select
                    value={formData.template_id}
                    onValueChange={(value) =>
                        setFormData({
                            ...formData,
                            template_id: value,
                        })
                    }
                >
                    <SelectTrigger className={formErrors.template_id ? 'border-destructive' : ''}>
                        <SelectValue placeholder={t('Automatic')} />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="automatic">{t('Automatic')}</SelectItem>
                        {templates.map((template) => (
                            <SelectItem key={template.id} value={template.id.toString()}>
                                {template.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {formErrors.template_id && (
                    <p className="text-sm text-destructive">{formErrors.template_id}</p>
                )}
            </div>

            <div className="space-y-2">
                <Label>{t('Theme preset')}</Label>
                <Select
                    value={formData.theme_preset}
                    onValueChange={(value) =>
                        setFormData({
                            ...formData,
                            theme_preset: value,
                        })
                    }
                >
                    <SelectTrigger className={formErrors.theme_preset ? 'border-destructive' : ''}>
                        <SelectValue placeholder={t('Automatic')} />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="automatic">{t('Automatic')}</SelectItem>
                        {THEME_PRESETS.map((preset) => (
                            <SelectItem key={preset.id} value={preset.id}>
                                {t(preset.name)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {formErrors.theme_preset && (
                    <p className="text-sm text-destructive">{formErrors.theme_preset}</p>
                )}
            </div>

            <div className="flex items-center justify-between rounded-md border p-3">
                <div>
                    <p className="text-sm font-medium">{t('Public visibility')}</p>
                    <p className="text-xs text-muted-foreground">
                        {t('Allow public preview of this project')}
                    </p>
                </div>
                <Switch
                    checked={formData.is_public}
                    onCheckedChange={(value) =>
                        setFormData({ ...formData, is_public: value })
                    }
                />
            </div>
        </div>
    );
}
