import { useEffect, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import AdminLayout from '@/Layouts/AdminLayout';
import { AdminPageHeader } from '@/components/Admin/AdminPageHeader';
import { UniversalCmsMediaPicker } from '@/components/Admin/UniversalCmsMediaPicker';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/contexts/LanguageContext';
import { ArrowLeft, ImageIcon } from 'lucide-react';
import type { PageProps } from '@/types';

interface Website {
    id: string;
    name: string;
    domain: string | null;
}

interface WebsitePage {
    id: number;
    title: string;
    slug: string;
}

interface PageSection {
    id: number;
    section_type: string;
    order: number;
    settings_json: Record<string, unknown>;
}

interface SectionEditProps extends PageProps {
    website: Website;
    page: WebsitePage;
    section: PageSection;
    title: string;
    mediaBaseUrl?: string;
}

export default function SectionEdit({ website, page, section, title, mediaBaseUrl = '' }: SectionEditProps) {
    const { t } = useTranslation();
    const { auth, flash } = usePage<SectionEditProps & { flash?: { success?: string; error?: string } }>().props;
    const [mediaPickerOpen, setMediaPickerOpen] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);
    const settings = (section.settings_json || {}) as Record<string, string>;

    const { data, setData, put, processing, errors } = useForm({
        title: settings.title ?? '',
        subtitle: settings.subtitle ?? '',
        button_text: settings.button_text ?? '',
        button_link: settings.button_link ?? '',
        image: settings.image ?? '',
        background_color: settings.background_color ?? '',
        alignment: settings.alignment ?? 'center',
        heading: settings.heading ?? '',
        description: (settings.description as string) ?? '',
        spacing: settings.spacing ?? '',
        text_size: settings.text_size ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.universal-cms.section-update', [website.id, page.id, section.id]));
    };

    return (
        <AdminLayout user={auth.user} title={title}>
            <Head title={title} />
            <AdminPageHeader
                title={t('Edit section')}
                description={t('Change text, image, and style. Updates appear on the site.')}
            />
            <div className="space-y-6">
                <Button variant="ghost" size="sm" asChild>
                    <Link href={route('admin.universal-cms.page-edit', [website.id, page.id])}>
                        <ArrowLeft className="h-4 w-4 mr-2" />
                        {t('Back to page')}
                    </Link>
                </Button>
                <form onSubmit={submit}>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">{section.section_type}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="title">{t('Title')}</Label>
                                    <Input
                                        id="title"
                                        value={data.title}
                                        onChange={(e) => setData('title', e.target.value)}
                                        placeholder={t('Main heading')}
                                    />
                                    {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="heading">{t('Heading')}</Label>
                                    <Input
                                        id="heading"
                                        value={data.heading}
                                        onChange={(e) => setData('heading', e.target.value)}
                                        placeholder={t('Section heading')}
                                    />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="subtitle">{t('Subtitle')}</Label>
                                <Input
                                    id="subtitle"
                                    value={data.subtitle}
                                    onChange={(e) => setData('subtitle', e.target.value)}
                                    placeholder={t('Short description')}
                                />
                                {errors.subtitle && <p className="text-sm text-destructive">{errors.subtitle}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="description">{t('Description')}</Label>
                                <textarea
                                    id="description"
                                    className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder={t('Longer text')}
                                />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="button_text">{t('Button text')}</Label>
                                    <Input
                                        id="button_text"
                                        value={data.button_text}
                                        onChange={(e) => setData('button_text', e.target.value)}
                                        placeholder={t('e.g. Book Now')}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="button_link">{t('Button link')}</Label>
                                    <Input
                                        id="button_link"
                                        value={data.button_link}
                                        onChange={(e) => setData('button_link', e.target.value)}
                                        placeholder="/contact"
                                    />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="image">{t('Image')}</Label>
                                <div className="flex gap-2">
                                    <Input
                                        id="image"
                                        value={data.image}
                                        onChange={(e) => setData('image', e.target.value)}
                                        placeholder={t('Image path or URL')}
                                    />
                                    <Button type="button" variant="outline" size="icon" onClick={() => setMediaPickerOpen(true)} title={t('Select from library')}>
                                        <ImageIcon className="h-4 w-4" />
                                    </Button>
                                </div>
                                {data.image && (
                                    <div className="mt-2 relative w-32 h-24 rounded border bg-muted overflow-hidden">
                                        <img
                                            src={data.image.startsWith('http') ? data.image : `${mediaBaseUrl}/${data.image}`}
                                            alt=""
                                            className="w-full h-full object-cover"
                                        />
                                    </div>
                                )}
                                <UniversalCmsMediaPicker
                                    websiteId={website.id}
                                    open={mediaPickerOpen}
                                    onOpenChange={setMediaPickerOpen}
                                    onSelect={(path) => setData('image', path)}
                                    uploadRoute={route('admin.universal-cms.media.upload', website.id)}
                                    listRoute={route('admin.universal-cms.media.index', website.id)}
                                    destroyRoute={route('admin.universal-cms.media.destroy', website.id)}
                                />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="background_color">{t('Background color')}</Label>
                                    <Input
                                        id="background_color"
                                        value={data.background_color}
                                        onChange={(e) => setData('background_color', e.target.value)}
                                        placeholder="#ffffff"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="alignment">{t('Alignment')}</Label>
                                    <select
                                        id="alignment"
                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        value={data.alignment}
                                        onChange={(e) => setData('alignment', e.target.value)}
                                    >
                                        <option value="left">{t('Left')}</option>
                                        <option value="center">{t('Center')}</option>
                                        <option value="right">{t('Right')}</option>
                                    </select>
                                </div>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="spacing">{t('Space around section')}</Label>
                                    <Input
                                        id="spacing"
                                        value={data.spacing}
                                        onChange={(e) => setData('spacing', e.target.value)}
                                        placeholder={t('e.g. medium')}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="text_size">{t('Text size')}</Label>
                                    <Input
                                        id="text_size"
                                        value={data.text_size}
                                        onChange={(e) => setData('text_size', e.target.value)}
                                        placeholder={t('e.g. large')}
                                    />
                                </div>
                            </div>
                            <Button type="submit" disabled={processing}>
                                {processing ? t('Saving...') : t('Save section')}
                            </Button>
                        </CardContent>
                    </Card>
                    <Card className="mt-6">
                        <CardHeader>
                            <CardTitle className="text-sm font-medium text-muted-foreground">{t('Preview')}</CardTitle>
                        </CardHeader>
                        <CardContent className="rounded-lg border bg-muted/30 p-4">
                            <div className="space-y-2" style={{ textAlign: (data.alignment || 'center') as React.CSSProperties['textAlign'] }}>
                                {(data.title || data.heading) && (
                                    <h3 className="text-xl font-semibold">{data.title || data.heading}</h3>
                                )}
                                {data.subtitle && <p className="text-muted-foreground">{data.subtitle}</p>}
                                {data.description && <p className="text-sm">{data.description}</p>}
                                {data.button_text && (
                                    <span className="inline-block rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground">
                                        {data.button_text}
                                    </span>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AdminLayout>
    );
}