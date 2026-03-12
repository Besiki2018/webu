import { Head, Link, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { AdminPageHeader } from '@/components/Admin/AdminPageHeader';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/contexts/LanguageContext';
import { Building2, ChevronRight, Globe } from 'lucide-react';
import type { PageProps } from '@/types';

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

interface PaginatedTenants {
    data: Tenant[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface StorageEstimate {
    bytes: number;
    files: number;
    human: string;
}

interface TenantsIndexProps extends PageProps {
    tenants: PaginatedTenants;
    storageEstimates: Record<string, StorageEstimate>;
    title: string;
}

export default function TenantsIndex({ tenants, storageEstimates = {}, title }: TenantsIndexProps) {
    const { t } = useTranslation();
    const { auth } = usePage<TenantsIndexProps>().props;

    return (
        <AdminLayout user={auth.user} title={title}>
            <Head title={title} />
            <AdminPageHeader
                title={title}
                description={t('Multi-tenant isolation: view tenants, websites count, export or delete.')}
            />
            <div className="space-y-6">
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {tenants.data.map((tenant) => (
                        <Card key={tenant.id}>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <div className="flex items-center gap-2">
                                    <Building2 className="h-4 w-4 text-muted-foreground" />
                                    <span className="font-medium truncate">{tenant.name}</span>
                                </div>
                                <Badge variant={tenant.status === 'active' ? 'default' : 'secondary'}>
                                    {tenant.status}
                                </Badge>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    {tenant.owner?.email ?? tenant.owner?.name ?? '—'}
                                </p>
                                <div className="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-sm text-muted-foreground">
                                    <span className="flex items-center gap-1">
                                        <Globe className="h-4 w-4" />
                                        {tenant.websites_count} {t('websites')}
                                    </span>
                                    {storageEstimates[tenant.id] && (
                                        <span>
                                            {storageEstimates[tenant.id].human}
                                            {storageEstimates[tenant.id].files > 0 && ` (${storageEstimates[tenant.id].files} ${t('files')})`}
                                        </span>
                                    )}
                                </div>
                                <Link
                                    href={route('admin.tenants.show', tenant.id)}
                                    className="mt-3 inline-flex items-center text-sm font-medium text-primary"
                                >
                                    {t('View')}
                                    <ChevronRight className="h-4 w-4 ml-1" />
                                </Link>
                            </CardContent>
                        </Card>
                    ))}
                </div>
                {tenants.last_page > 1 && (
                    <div className="flex justify-center gap-2">
                        {Array.from({ length: tenants.last_page }, (_, i) => i + 1).map((page) => (
                            <Link
                                key={page}
                                href={route('admin.tenants.index', { page })}
                                className={`px-3 py-1 rounded ${page === tenants.current_page ? 'bg-primary text-primary-foreground' : 'bg-muted'}`}
                            >
                                {page}
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
