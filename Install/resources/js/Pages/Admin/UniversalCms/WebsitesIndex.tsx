import { useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import AdminLayout from '@/Layouts/AdminLayout';
import { AdminPageHeader } from '@/components/Admin/AdminPageHeader';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/contexts/LanguageContext';
import { Globe, FileText, RefreshCw, ChevronRight, Trash2, ImageIcon } from 'lucide-react';
import type { PageProps } from '@/types';

interface WebsiteSite {
    id: string;
    name: string;
    status: string;
}

interface Website {
    id: string;
    name: string;
    domain: string | null;
    website_pages_count: number;
    site: WebsiteSite | null;
}

interface PaginatedWebsites {
    data: Website[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface WebsitesIndexProps extends PageProps {
    websites: PaginatedWebsites;
    title: string;
}

export default function WebsitesIndex({ websites, title }: WebsitesIndexProps) {
    const { t } = useTranslation();
    const { auth, flash } = usePage<WebsitesIndexProps & { flash?: { success?: string; error?: string } }>().props;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    return (
        <AdminLayout
            user={auth.user}
            title={title}
        >
            <Head title={title} />
            <AdminPageHeader
                title={title}
                description={t('Manage content, images, and sections of generated sites. No technical knowledge required.')}
            />
            <div className="space-y-6">
                <div className="flex justify-end gap-2">
                    <Button
                        variant="outline"
                        onClick={() => router.post(route('admin.universal-cms.sync'))}
                    >
                        <RefreshCw className="h-4 w-4 mr-2" />
                        {t('Sync from existing sites')}
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => router.post(route('admin.universal-cms.cleanup'))}
                    >
                        <Trash2 className="h-4 w-4 mr-2" />
                        {t('Cleanup dummy content')}
                    </Button>
                </div>
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {websites.data.map((website) => (
                        <Card key={website.id}>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <div className="flex items-center gap-2">
                                    <Globe className="h-5 w-5 text-muted-foreground" />
                                    <CardTitle className="text-base">{website.name}</CardTitle>
                                </div>
                                {website.site?.status && (
                                    <Badge variant={website.site.status === 'published' ? 'default' : 'secondary'}>
                                        {website.site.status}
                                    </Badge>
                                )}
                            </CardHeader>
                            <CardContent>
                                {website.domain && (
                                    <p className="text-sm text-muted-foreground truncate">{website.domain}</p>
                                )}
                                <p className="text-sm mt-1">
                                    {t('{count} pages', { count: String(website.website_pages_count) })}
                                </p>
                                <div className="mt-2 flex flex-col gap-1">
                                    <Button asChild variant="ghost" size="sm" className="w-full justify-between">
                                        <Link href={route('admin.universal-cms.pages', website.id)}>
                                            <span className="flex items-center gap-1">
                                                <FileText className="h-4 w-4" />
                                                {t('Edit pages')}
                                            </span>
                                            <ChevronRight className="h-4 w-4" />
                                        </Link>
                                    </Button>
                                    <Button asChild variant="ghost" size="sm" className="w-full justify-between">
                                        <Link href={route('admin.universal-cms.media.library', website.id)}>
                                            <span className="flex items-center gap-1">
                                                <ImageIcon className="h-4 w-4" />
                                                {t('Media library')}
                                            </span>
                                            <ChevronRight className="h-4 w-4" />
                                        </Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
                {websites.data.length === 0 && (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Globe className="h-12 w-12 text-muted-foreground mb-4" />
                            <p className="text-muted-foreground text-center">
                                {t('No websites yet. Sync from existing sites or generate a new site.')}
                            </p>
                            <Button
                                className="mt-4"
                                onClick={() => router.post(route('admin.universal-cms.sync'))}
                            >
                                <RefreshCw className="h-4 w-4 mr-2" />
                                {t('Sync from existing sites')}
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AdminLayout>
    );
}
