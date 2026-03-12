import { useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import AdminLayout from '@/Layouts/AdminLayout';
import { AdminPageHeader } from '@/components/Admin/AdminPageHeader';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/contexts/LanguageContext';
import { ArrowLeft, Layout, ChevronRight, Undo2 } from 'lucide-react';
import { route } from 'ziggy-js';
import type { PageProps } from '@/types';

interface PageSection {
    id: number;
    section_type: string;
    order: number;
    settings_json: Record<string, unknown>;
}

interface Website {
    id: string;
    name: string;
    domain: string | null;
}

interface WebsitePage {
    id: number;
    slug: string;
    title: string;
    order: number;
    sections: PageSection[];
}

interface PageEditProps extends PageProps {
    website: Website;
    page: WebsitePage;
    title: string;
    sectionTypeLabels?: Record<string, string>;
    undoPreviousVersion?: number | null;
}

function sectionTypeLabel(type: string, labels?: Record<string, string>): string {
    return (labels && labels[type]) ?? type;
}

export default function PageEdit({ website, page, title, sectionTypeLabels = {}, undoPreviousVersion }: PageEditProps) {
    const { t } = useTranslation();
    const { auth, flash } = usePage<PageEditProps & { flash?: { success?: string; error?: string } }>().props;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    return (
        <AdminLayout user={auth.user} title={title}>
            <Head title={title} />
            <AdminPageHeader
                title={page.title}
                description={t('Edit sections. Change text, images, and buttons.')}
            />
            <div className="space-y-6">
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={route('admin.universal-cms.pages', website.id)}>
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            {t('Back to pages')}
                        </Link>
                    </Button>
                    {undoPreviousVersion != null && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                router.post(route('admin.universal-cms.revisions.undo', String(website.id)));
                            }}
                        >
                            <Undo2 className="h-4 w-4 mr-2" />
                            {t('Undo')}
                        </Button>
                    )}
                </div>
                <div className="space-y-3">
                    <h3 className="text-sm font-medium text-muted-foreground">{t('Sections')}</h3>
                    <div className="grid gap-3">
                        {page.sections.map((section, index) => (
                            <Card key={section.id}>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 py-4">
                                    <div className="flex items-center gap-2">
                                        <Layout className="h-4 w-4 text-muted-foreground" />
                                        <CardTitle className="text-base">
                                            {sectionTypeLabel(section.section_type, sectionTypeLabels)} ({index + 1})
                                        </CardTitle>
                                    </div>
                                    <Button asChild variant="ghost" size="sm">
                                        <Link
                                            href={route('admin.universal-cms.section-edit', [
                                                website.id,
                                                page.id,
                                                section.id,
                                            ])}
                                        >
                                            {t('Edit')}
                                            <ChevronRight className="h-4 w-4 ml-1" />
                                        </Link>
                                    </Button>
                                </CardHeader>
                            </Card>
                        ))}
                    </div>
                    {page.sections.length === 0 && (
                        <Card>
                            <CardContent className="py-8 text-center text-muted-foreground">
                                {t('No sections on this page.')}
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
