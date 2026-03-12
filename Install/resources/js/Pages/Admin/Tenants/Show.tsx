import { useState, useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import AdminLayout from '@/Layouts/AdminLayout';
import { AdminPageHeader } from '@/components/Admin/AdminPageHeader';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/contexts/LanguageContext';
import { Building2, Download, Eye, Globe, Search, ShieldOff, ShieldCheck } from 'lucide-react';
import type { PageProps } from '@/types';

interface Website {
    id: string;
    name: string;
    domain: string | null;
    created_at: string;
    view_site_url?: string | null;
}

interface Owner {
    id: number;
    name: string;
    email: string;
}

interface Tenant {
    id: string;
    name: string;
    slug: string;
    status: string;
    plan: string | null;
    websites_count: number;
    owner: Owner | null;
}

interface ShowTenantProps extends PageProps {
    tenant: Tenant;
    websites: Website[];
    search: string;
    title: string;
}

export default function ShowTenant({ tenant, websites, search = '', title }: ShowTenantProps) {
    const { t } = useTranslation();
    const { auth, flash } = usePage<ShowTenantProps & { flash?: { success?: string; error?: string } }>().props;
    const [searchInput, setSearchInput] = useState(search);
    const [deleting, setDeleting] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    const exportTenantUrl = route('admin.tenants.export', tenant.id);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('admin.tenants.show', tenant.id), { search: searchInput || undefined }, { preserveState: true });
    };

    const handleStatusChange = () => {
        const nextStatus = tenant.status === 'active' ? 'suspended' : 'active';
        router.put(route('admin.tenants.update-status'), { tenant_id: tenant.id, status: nextStatus });
    };

    const handleDeleteTenant = () => {
        if (!confirm(t('Permanently delete this tenant and all its websites? This cannot be undone.'))) return;
        setDeleting(true);
        router.delete(route('admin.tenants.delete-tenant'), {
            data: { tenant_id: tenant.id },
            onFinish: () => setDeleting(false),
            onSuccess: () => {
                toast.success(t('Tenant deleted'));
                router.visit(route('admin.tenants.index'));
            },
            onError: () => toast.error(t('Failed to delete tenant')),
        });
    };

    return (
        <AdminLayout user={auth.user} title={title}>
            <Head title={title} />
            <AdminPageHeader
                title={tenant.name}
                description={t('Tenant detail: websites, export, suspend, delete.')}
            />
            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="flex items-center gap-2">
                                <Building2 className="h-5 w-5" />
                                <span className="font-medium">{tenant.slug}</span>
                                <Badge variant={tenant.status === 'active' ? 'default' : 'secondary'}>
                                    {tenant.status}
                                </Badge>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleStatusChange}
                                    title={tenant.status === 'active' ? t('Suspend tenant') : t('Activate tenant')}
                                >
                                    {tenant.status === 'active' ? (
                                        <><ShieldOff className="h-4 w-4 mr-2" />{t('Suspend')}</>
                                    ) : (
                                        <><ShieldCheck className="h-4 w-4 mr-2" />{t('Activate')}</>
                                    )}
                                </Button>
                                <Button variant="outline" size="sm" asChild>
                                    <a href={exportTenantUrl} download>
                                        <Download className="h-4 w-4 mr-2" />
                                        {t('Export tenant')}
                                    </a>
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-muted-foreground">
                            {t('Owner')}: {tenant.owner?.email ?? tenant.owner?.name ?? '—'}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {t('Websites')}: {tenant.websites_count}
                        </p>
                    </CardContent>
                </Card>

                <div>
                    <div className="flex flex-wrap items-center justify-between gap-3 mb-3">
                        <h3 className="text-lg font-medium">{t('Websites')}</h3>
                        <form onSubmit={handleSearch} className="flex gap-2">
                            <div className="relative">
                                <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input
                                    type="search"
                                    placeholder={t('Search by domain, name or ID')}
                                    value={searchInput}
                                    onChange={(e) => setSearchInput(e.target.value)}
                                    className="pl-8 w-48 sm:w-64"
                                />
                            </div>
                            <Button type="submit" variant="secondary" size="sm">{t('Search')}</Button>
                        </form>
                    </div>
                    <div className="grid gap-2">
                        {websites.map((w) => (
                            <Card key={w.id}>
                                <CardContent className="flex flex-row flex-wrap items-center justify-between gap-2 py-3">
                                    <div className="flex items-center gap-2">
                                        <Globe className="h-4 w-4 text-muted-foreground shrink-0" />
                                        <span>{w.name}</span>
                                        {w.domain && (
                                            <span className="text-sm text-muted-foreground">{w.domain}</span>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Link
                                            href={route('admin.universal-cms.pages', w.id)}
                                            className="text-sm text-primary inline-flex items-center gap-1"
                                        >
                                            {t('Edit pages')}
                                        </Link>
                                        {w.view_site_url && (
                                            <a
                                                href={w.view_site_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="text-sm text-muted-foreground hover:text-primary inline-flex items-center gap-1"
                                            >
                                                <Eye className="h-4 w-4" />
                                                {t('View site')}
                                            </a>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </div>

                <Card className="border-destructive/50">
                    <CardHeader>
                        <h3 className="text-lg font-medium text-destructive">{t('Danger zone')}</h3>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-muted-foreground mb-3">
                            {t('Permanently delete this tenant and all its websites, media and data.')}
                        </p>
                        <Button variant="destructive" size="sm" disabled={deleting} onClick={handleDeleteTenant}>
                            {deleting ? t('Deleting…') : t('Delete tenant')}
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
