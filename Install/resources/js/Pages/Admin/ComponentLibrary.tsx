import { Head, Link, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { AdminPageHeader } from '@/components/Admin/AdminPageHeader';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/contexts/LanguageContext';
import {
    Pencil,
    LayoutTemplate,
} from 'lucide-react';
import type { PageProps } from '@/types';
import { resolveBuilderWidgetIcon } from '@/lib/resolveBuilderWidgetIcon';

interface SectionItem {
    id: number;
    key: string;
    category: string;
    category_label: string;
    label: string;
    description: string;
    location_hint: string;
    enabled: boolean;
    preview_url: string;
}

interface GroupedSection {
    category: string;
    category_label: string;
    items: SectionItem[];
}

type ComponentLibraryPageProps = PageProps<{
    groupedSections: GroupedSection[];
    cmsSectionsUrl: string;
}>;

export default function ComponentLibrary() {
    const { t } = useTranslation();
    const { auth, groupedSections = [], cmsSectionsUrl } = usePage<ComponentLibraryPageProps>().props;
    const user = auth.user;
    const pageTitle = 'კომპონენტები';

    if (!user) {
        return null;
    }

    return (
        <AdminLayout user={user} title={pageTitle}>
            <Head title={pageTitle} />
            <AdminPageHeader
                title={pageTitle}
                description="აქ ჩანს ყველა კომპონენტი. ბარათზე დაჭერით იხსნება preview, ხოლო ცვლილება კეთდება CMS Sections-ში."
            />

            <div className="mb-4 flex flex-wrap gap-2">
                <Button variant="outline" size="sm" asChild>
                    <Link href={cmsSectionsUrl}>
                        <Pencil className="mr-1.5 h-4 w-4" />
                        CMS Sections
                    </Link>
                </Button>
            </div>

            <Card className="mb-6">
                <CardContent className="flex flex-col gap-1 pt-6 text-sm text-muted-foreground">
                    <p>ნახვა: ბარათზე დაჭერით იხსნება კომპონენტის preview.</p>
                    <p>რედაქტირება: `CMS Sections` გვერდიდან.</p>
                    <p>`webu/...` ნიშნავს დიზაინის source ფოლდერს, `CMS სექცია` ნიშნავს builder section-ს.</p>
                </CardContent>
            </Card>

            {groupedSections.length === 0 ? (
                <Card>
                    <CardContent className="pt-6">
                        <p className="text-muted-foreground">{t('No components yet. Import defaults or add in CMS Sections.')}</p>
                        <Button variant="outline" size="sm" className="mt-3" asChild>
                            <Link href={cmsSectionsUrl}>{t('Open CMS Sections')}</Link>
                        </Button>
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-8">
                    {groupedSections.map((group) => (
                        <Card key={group.category}>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <LayoutTemplate className="h-5 w-5" />
                                    {group.category_label}
                                </CardTitle>
                                <CardDescription>
                                    {group.items.length} კომპონენტი
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                                    {group.items.map((item) => {
                                        const IconComponent = resolveBuilderWidgetIcon(item.key, item.category);
                                        return (
                                            <a
                                                key={item.id}
                                                href={item.preview_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                title={`${item.label} (${item.key})`}
                                                className="flex min-h-[150px] flex-col items-center gap-2 rounded-lg border bg-background p-3 text-center transition hover:border-primary/50 hover:bg-primary/5"
                                            >
                                                <div className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                                    <IconComponent className="h-4 w-4" />
                                                </div>
                                                <div className="flex w-full flex-1 flex-col items-center gap-1">
                                                    <p className="w-full break-words text-xs font-medium leading-4">{item.label}</p>
                                                    <p className="w-full break-all text-[10px] leading-4 text-muted-foreground">{item.key}</p>
                                                    <p className="w-full text-[10px] leading-4 text-muted-foreground">{item.location_hint}</p>
                                                </div>
                                            </a>
                                        );
                                    })}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
