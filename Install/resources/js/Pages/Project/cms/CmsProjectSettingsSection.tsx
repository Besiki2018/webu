import { Link } from '@inertiajs/react';
import { Loader2, Plus, Save } from 'lucide-react';

import { useTranslation } from '@/contexts/LanguageContext';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface SiteSettingsForm {
    name: string;
    locale: string;
    themePreset: string;
    fontKey: string;
    headingFontKey: string;
    bodyFontKey: string;
    buttonFontKey: string;
    contactEmail: string;
    contactPhone: string;
    contactAddress: string;
    socialFacebook: string;
    socialInstagram: string;
    socialTiktok: string;
    socialLinkedin: string;
    socialYoutube: string;
    socialX: string;
    socialWhatsapp: string;
    analyticsGa4: string;
    analyticsGtm: string;
    analyticsMetaPixel: string;
    logoMediaId: number | null;
}

interface UiTranslationField {
    key: string;
    label: string;
    hint: string;
}

interface CmsProjectSettingsSectionProps {
    activeSection: string;
    activeContentLocale: string;
    isSavingSettings: boolean;
    isSettingsLoading: boolean;
    localeDraftInput: string;
    onAddLocale: () => void;
    onDefaultLocaleChange: (value: string) => void;
    onLocaleDraftInputChange: (value: string) => void;
    onSave: () => void;
    onSettingsFormChange: <K extends keyof SiteSettingsForm>(field: K, value: SiteSettingsForm[K]) => void;
    onUiTranslationChange: (key: string, value: string) => void;
    projectId: number | string;
    settingsForm: SiteSettingsForm;
    siteLocales: string[];
    uiTranslationDraft: Record<string, string>;
    uiTranslationFields: UiTranslationField[];
}

function SettingsLoadingCard({ label }: { label: string }) {
    return (
        <Card>
            <CardContent className="flex items-center gap-2 py-6 text-sm text-muted-foreground">
                <Loader2 className="h-4 w-4 animate-spin" />
                {label}
            </CardContent>
        </Card>
    );
}

function SaveSettingsCard({
    disabled,
    isSaving,
    onSave,
    saveLabel,
}: {
    disabled: boolean;
    isSaving: boolean;
    onSave: () => void;
    saveLabel: string;
}) {
    return (
        <Card>
            <CardContent className="flex flex-wrap items-center gap-2 py-4">
                <Button onClick={onSave} disabled={disabled}>
                    {isSaving ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <Save className="h-4 w-4 mr-2" />}
                    {saveLabel}
                </Button>
            </CardContent>
        </Card>
    );
}

export function CmsProjectSettingsSection({
    activeSection,
    activeContentLocale,
    isSavingSettings,
    isSettingsLoading,
    localeDraftInput,
    onAddLocale,
    onDefaultLocaleChange,
    onLocaleDraftInputChange,
    onSave,
    onSettingsFormChange,
    onUiTranslationChange,
    projectId,
    settingsForm,
    siteLocales,
    uiTranslationDraft,
    uiTranslationFields,
}: CmsProjectSettingsSectionProps) {
    const { t } = useTranslation();

    if (activeSection === 'settings-general') {
        return (
            <div className="space-y-4">
                {isSettingsLoading ? <SettingsLoadingCard label={t('Loading settings...')} /> : null}

                <div className="grid gap-4 2xl:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)]">
                    <div className="space-y-4 min-w-0">
                        <Card className="min-w-0">
                            <CardHeader className="pb-3">
                                <CardTitle>{t('Site profile')}</CardTitle>
                                <CardDescription>{t('Name, locale, and publishing defaults.')}</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-3 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>{t('Site Name')}</Label>
                                        <Input
                                            value={settingsForm.name}
                                            onChange={(event) => onSettingsFormChange('name', event.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>{t('Default Locale')}</Label>
                                        <select
                                            value={settingsForm.locale}
                                            onChange={(event) => onDefaultLocaleChange(event.target.value)}
                                            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                        >
                                            {siteLocales.map((localeCode) => (
                                                <option key={localeCode} value={localeCode}>{localeCode}</option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="min-w-0">
                            <CardHeader className="pb-3">
                                <CardTitle>{t('Languages')}</CardTitle>
                                <CardDescription>{t('Manage editing and site languages.')}</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_220px]">
                                    <div className="space-y-2">
                                        <Label>{t('Available Site Languages')}</Label>
                                        <div className="flex flex-wrap gap-2">
                                            {siteLocales.map((localeCode) => (
                                                <Badge key={`site-locale-${localeCode}`} variant={localeCode === activeContentLocale ? 'default' : 'secondary'}>
                                                    {localeCode}
                                                </Badge>
                                            ))}
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>{t('Current Language')}</Label>
                                        <div className="w-full rounded-md border bg-muted/20 px-3 py-2 text-sm text-foreground/90">
                                            {activeContentLocale.toUpperCase()}
                                        </div>
                                    </div>
                                </div>

                                <div className="grid gap-2 md:grid-cols-[minmax(0,1fr)_auto]">
                                    <Input
                                        value={localeDraftInput}
                                        onChange={(event) => onLocaleDraftInputChange(event.target.value)}
                                        placeholder={t('Add locale code (e.g. de, fr)')}
                                    />
                                    <Button type="button" variant="outline" onClick={onAddLocale}>
                                        <Plus className="h-4 w-4 mr-2" />
                                        {t('Add Language')}
                                    </Button>
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    {t('Contact, menu, page content, and UI dictionary save for selected editing language.')}
                                </p>
                            </CardContent>
                        </Card>

                        <Card className="min-w-0">
                            <CardHeader className="pb-3">
                                <CardTitle>{t('Contact Details')}</CardTitle>
                                <CardDescription>{t('Public business contact information.')}</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-3 md:grid-cols-3">
                                    <div className="space-y-2">
                                        <Label>{t('Contact Email')}</Label>
                                        <Input
                                            value={settingsForm.contactEmail}
                                            onChange={(event) => onSettingsFormChange('contactEmail', event.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>{t('Contact Phone')}</Label>
                                        <Input
                                            value={settingsForm.contactPhone}
                                            onChange={(event) => onSettingsFormChange('contactPhone', event.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>{t('Contact Address')}</Label>
                                        <Input
                                            value={settingsForm.contactAddress}
                                            onChange={(event) => onSettingsFormChange('contactAddress', event.target.value)}
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="min-w-0">
                            <CardHeader className="pb-3">
                                <CardTitle>{t('Public Links')}</CardTitle>
                                <CardDescription>{t('Social profiles and public link destinations.')}</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>{t('Facebook URL')}</Label>
                                    <Input
                                        value={settingsForm.socialFacebook}
                                        onChange={(event) => onSettingsFormChange('socialFacebook', event.target.value)}
                                        placeholder="https://facebook.com/your-page"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>{t('Instagram URL')}</Label>
                                    <Input
                                        value={settingsForm.socialInstagram}
                                        onChange={(event) => onSettingsFormChange('socialInstagram', event.target.value)}
                                        placeholder="https://instagram.com/your-page"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>{t('TikTok URL')}</Label>
                                    <Input
                                        value={settingsForm.socialTiktok}
                                        onChange={(event) => onSettingsFormChange('socialTiktok', event.target.value)}
                                        placeholder="https://tiktok.com/@your-page"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>{t('LinkedIn URL')}</Label>
                                    <Input
                                        value={settingsForm.socialLinkedin}
                                        onChange={(event) => onSettingsFormChange('socialLinkedin', event.target.value)}
                                        placeholder="https://linkedin.com/company/your-page"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>{t('YouTube URL')}</Label>
                                    <Input
                                        value={settingsForm.socialYoutube}
                                        onChange={(event) => onSettingsFormChange('socialYoutube', event.target.value)}
                                        placeholder="https://youtube.com/@your-channel"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>{t('X (Twitter) URL')}</Label>
                                    <Input
                                        value={settingsForm.socialX}
                                        onChange={(event) => onSettingsFormChange('socialX', event.target.value)}
                                        placeholder="https://x.com/your-page"
                                    />
                                </div>
                                <div className="space-y-2 md:col-span-2">
                                    <Label>{t('WhatsApp Link')}</Label>
                                    <Input
                                        value={settingsForm.socialWhatsapp}
                                        onChange={(event) => onSettingsFormChange('socialWhatsapp', event.target.value)}
                                        placeholder="https://wa.me/995..."
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-4 min-w-0">
                        <Card className="min-w-0">
                            <CardHeader className="pb-3">
                                <CardTitle>{t('Interface Labels')}</CardTitle>
                                <CardDescription>{t('Theme UI text for the current editing language.')}</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                {uiTranslationFields.map((field) => (
                                    <div key={`ui-translation-${field.key}`} className="space-y-2">
                                        <Label>{t(field.label)}</Label>
                                        <Input
                                            value={uiTranslationDraft[field.key] ?? ''}
                                            onChange={(event) => onUiTranslationChange(field.key, event.target.value)}
                                            placeholder={t(field.hint)}
                                        />
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        <Card className="min-w-0">
                            <CardHeader className="pb-3">
                                <CardTitle>{t('Design Shortcuts')}</CardTitle>
                                <CardDescription>{t('Visual and typography controls live in the design section.')}</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <Button asChild variant="outline" size="sm" className="w-full justify-start">
                                    <Link href={`/project/${projectId}/cms?tab=design-branding`}>{t('Branding')}</Link>
                                </Button>
                                <Button asChild variant="outline" size="sm" className="w-full justify-start">
                                    <Link href={`/project/${projectId}/cms?tab=design-layout`}>{t('Layout')}</Link>
                                </Button>
                                <Button asChild variant="outline" size="sm" className="w-full justify-start">
                                    <Link href={`/project/${projectId}/cms?tab=design-presets`}>{t('Presets')}</Link>
                                </Button>
                            </CardContent>
                        </Card>

                        <SaveSettingsCard
                            disabled={isSavingSettings}
                            isSaving={isSavingSettings}
                            onSave={onSave}
                            saveLabel={t('Save Settings')}
                        />
                    </div>
                </div>
            </div>
        );
    }

    if (activeSection === 'settings-integrations') {
        return (
            <div className="space-y-4">
                {isSettingsLoading ? <SettingsLoadingCard label={t('Loading settings...')} /> : null}

                <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
                    <Card className="min-w-0">
                        <CardHeader className="pb-3">
                            <CardTitle>{t('Analytics IDs')}</CardTitle>
                            <CardDescription>{t('Connect your analytics and tracking tools.')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-3 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>{t('Google Analytics (GA4)')}</Label>
                                    <Input
                                        value={settingsForm.analyticsGa4}
                                        onChange={(event) => onSettingsFormChange('analyticsGa4', event.target.value)}
                                        placeholder="G-XXXXXXXXXX"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>{t('Google Tag Manager')}</Label>
                                    <Input
                                        value={settingsForm.analyticsGtm}
                                        onChange={(event) => onSettingsFormChange('analyticsGtm', event.target.value)}
                                        placeholder="GTM-XXXXXXX"
                                    />
                                </div>
                                <div className="space-y-2 md:col-span-2">
                                    <Label>{t('Meta Pixel')}</Label>
                                    <Input
                                        value={settingsForm.analyticsMetaPixel}
                                        onChange={(event) => onSettingsFormChange('analyticsMetaPixel', event.target.value)}
                                        placeholder="123456789012345"
                                    />
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                <Button onClick={onSave} disabled={isSavingSettings}>
                                    {isSavingSettings ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <Save className="h-4 w-4 mr-2" />}
                                    {t('Save Settings')}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="space-y-4">
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle>{t('Related Settings')}</CardTitle>
                                <CardDescription>{t('Other project-level configuration lives in neighboring sections.')}</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <Button asChild variant="outline" size="sm" className="w-full justify-start">
                                    <Link href={`/project/${projectId}/cms?tab=domain`}>{t('Domain')}</Link>
                                </Button>
                                <Button asChild variant="outline" size="sm" className="w-full justify-start">
                                    <Link href={`/project/${projectId}/cms?tab=design-layout`}>{t('Layout')}</Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        );
    }

    if (activeSection === 'settings-team') {
        return (
            <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
                <Card className="min-w-0">
                    <CardHeader className="pb-3">
                        <CardTitle>{t('Team & Roles')}</CardTitle>
                        <CardDescription>{t('This subsection is prepared for project member access and permission controls.')}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm text-muted-foreground">
                        <p>{t('Project-level role management is not exposed in this workspace yet.')}</p>
                        <p>{t('When member permissions are enabled, invite, role, and access controls will appear here.')}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle>{t('Current Scope')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">{t('Site Name')}</span>
                            <span className="font-medium">{settingsForm.name || t('Untitled Site')}</span>
                        </div>
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">{t('Default Locale')}</span>
                            <span className="font-medium">{settingsForm.locale || '—'}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        );
    }

    if (activeSection === 'settings-webhooks') {
        return (
            <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
                <Card className="min-w-0">
                    <CardHeader className="pb-3">
                        <CardTitle>{t('Webhooks')}</CardTitle>
                        <CardDescription>{t('Send CMS events to external systems when this module is enabled.')}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                            {t('No webhook endpoints are configured for this project yet.')}
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {['site.updated', 'page.published', 'product.updated'].map((eventKey) => (
                                <Badge key={`webhook-event-${eventKey}`} variant="outline">{eventKey}</Badge>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle>{t('Status')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm text-muted-foreground">
                        <p>{t('Outbound delivery is currently inactive for this project.')}</p>
                        <p>{t('When webhooks are enabled, endpoint URLs, signing secrets, and retry settings will appear here.')}</p>
                    </CardContent>
                </Card>
            </div>
        );
    }

    return null;
}
