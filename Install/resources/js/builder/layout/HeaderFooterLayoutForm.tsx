/**
 * Header/footer layout form for the visual builder (Task 6 extraction).
 * Renders site-level settings: header/footer variant, menu sources, footer contact, popup.
 * Parent owns state and passes form + onFormChange; this component is presentational.
 */

import { Layers } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export interface BuilderLayoutFormState {
    headerVariant: string;
    footerVariant: string;
    headerMenuKey: string;
    footerContactAddress: string;
    footerMenuKeyColumn2: string;
    footerMenuKeyColumn3: string;
    footerMenuKeyColumn4: string;
    footerMenuKeyColumn5: string;
    popupEnabled: boolean;
    popupHeadline: string;
    popupDescription: string;
    popupButtonLabel: string;
}

export interface MenuSourceOption {
    key: string;
    label: string;
    isSystem?: boolean;
}

export interface HeaderFooterLayoutFormProps {
    form: BuilderLayoutFormState;
    onFormChange: (patch: Partial<BuilderLayoutFormState>) => void;
    headerVariantOptions: { key: string; label: string }[];
    footerVariantOptions: { key: string; label: string }[];
    menuSourceOptions: MenuSourceOption[];
    onEditHeader: () => void;
    onEditFooter: () => void;
    onOpenMenus: () => void;
    normalizeMenuKey: (raw: string, fallback: string) => string;
    t: (key: string, params?: Record<string, string>) => string;
}

export function HeaderFooterLayoutForm({
    form,
    onFormChange,
    headerVariantOptions,
    footerVariantOptions,
    menuSourceOptions,
    onEditHeader,
    onEditFooter,
    onOpenMenus,
    normalizeMenuKey,
    t,
}: HeaderFooterLayoutFormProps) {
    return (
        <div className="space-y-2">
            <p className="text-xs text-muted-foreground">{t('საიტის პარამეტრები')}</p>
            <div className="space-y-1">
                <Label className="text-xs">{t('Header Component')}</Label>
                <select
                    value={form.headerVariant}
                    onChange={(event) => onFormChange({ headerVariant: event.target.value })}
                    className="w-full rounded-md border bg-background px-2 py-1.5 text-xs"
                >
                    {headerVariantOptions.map((option) => (
                        <option key={option.key} value={option.key}>{option.label}</option>
                    ))}
                </select>
            </div>
            <div className="space-y-1">
                <Label className="text-xs">{t('Footer Component')}</Label>
                <select
                    value={form.footerVariant}
                    onChange={(event) => onFormChange({ footerVariant: event.target.value })}
                    className="w-full rounded-md border bg-background px-2 py-1.5 text-xs"
                >
                    {footerVariantOptions.map((option) => (
                        <option key={option.key} value={option.key}>{option.label}</option>
                    ))}
                </select>
            </div>
            <div className="space-y-1">
                <Label className="text-xs">{t('Header Menu Source')}</Label>
                <select
                    value={form.headerMenuKey}
                    onChange={(event) => onFormChange({ headerMenuKey: normalizeMenuKey(event.target.value, 'header') })}
                    className="w-full rounded-md border bg-background px-2 py-1.5 text-xs"
                >
                    {menuSourceOptions.map((option) => (
                        <option key={`header-source-${option.key}`} value={option.key}>
                            {option.isSystem ? `${option.label} (${t('System')})` : option.label}
                        </option>
                    ))}
                </select>
            </div>
            <div className="space-y-2 rounded-md border bg-muted/20 p-2">
                <p className="text-xs font-medium">{t('Footer')}</p>
                <div className="space-y-1">
                    <Label className="text-xs">{t('Contact address')}</Label>
                    <Input
                        value={form.footerContactAddress}
                        onChange={(event) => onFormChange({ footerContactAddress: event.target.value })}
                        placeholder="451 Wall Street, UK, London"
                        className="h-8 text-xs"
                    />
                </div>
                <div className="space-y-1">
                    <Label className="text-xs">{t('Menu column 2')}</Label>
                    <select
                        value={form.footerMenuKeyColumn2}
                        onChange={(e) => onFormChange({ footerMenuKeyColumn2: normalizeMenuKey(e.target.value, 'recent-posts') })}
                        className="w-full rounded-md border bg-background px-2 py-1.5 text-xs"
                    >
                        {menuSourceOptions.map((opt) => (
                            <option key={`footer-col2-${opt.key}`} value={opt.key}>{opt.label}</option>
                        ))}
                    </select>
                </div>
                <div className="space-y-1">
                    <Label className="text-xs">{t('Menu column 3')}</Label>
                    <select
                        value={form.footerMenuKeyColumn3}
                        onChange={(e) => onFormChange({ footerMenuKeyColumn3: normalizeMenuKey(e.target.value, 'our-stores') })}
                        className="w-full rounded-md border bg-background px-2 py-1.5 text-xs"
                    >
                        {menuSourceOptions.map((opt) => (
                            <option key={`footer-col3-${opt.key}`} value={opt.key}>{opt.label}</option>
                        ))}
                    </select>
                </div>
                <div className="space-y-1">
                    <Label className="text-xs">{t('Menu column 4')}</Label>
                    <select
                        value={form.footerMenuKeyColumn4}
                        onChange={(e) => onFormChange({ footerMenuKeyColumn4: normalizeMenuKey(e.target.value, 'useful-links') })}
                        className="w-full rounded-md border bg-background px-2 py-1.5 text-xs"
                    >
                        {menuSourceOptions.map((opt) => (
                            <option key={`footer-col4-${opt.key}`} value={opt.key}>{opt.label}</option>
                        ))}
                    </select>
                </div>
                <div className="space-y-1">
                    <Label className="text-xs">{t('Menu column 5')}</Label>
                    <select
                        value={form.footerMenuKeyColumn5}
                        onChange={(e) => onFormChange({ footerMenuKeyColumn5: normalizeMenuKey(e.target.value, 'footer') })}
                        className="w-full rounded-md border bg-background px-2 py-1.5 text-xs"
                    >
                        {menuSourceOptions.map((opt) => (
                            <option key={`footer-col5-${opt.key}`} value={opt.key}>{opt.label}</option>
                        ))}
                    </select>
                </div>
            </div>
            <div className="space-y-2 rounded-md border bg-muted/20 p-2">
                <div className="flex items-center justify-between gap-2">
                    <Label className="text-xs">{t('Welcome Popup')}</Label>
                    <label className="inline-flex items-center gap-1 text-xs">
                        <input
                            type="checkbox"
                            checked={form.popupEnabled}
                            onChange={(event) => onFormChange({ popupEnabled: event.target.checked })}
                        />
                        <span>{form.popupEnabled ? t('On') : t('Off')}</span>
                    </label>
                </div>
                {form.popupEnabled ? (
                    <div className="space-y-2">
                        <div className="space-y-1">
                            <Label className="text-xs">{t('Popup Heading')}</Label>
                            <Input
                                value={form.popupHeadline}
                                onChange={(event) => onFormChange({ popupHeadline: event.target.value })}
                                placeholder={t('Subscribe and Get 25% Discount!')}
                                className="h-8 text-xs"
                            />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">{t('Popup Description')}</Label>
                            <Textarea
                                value={form.popupDescription}
                                onChange={(event) => onFormChange({ popupDescription: event.target.value })}
                                placeholder={t('Subscribe to the newsletter to receive updates about new products.')}
                                rows={3}
                                className="text-xs"
                            />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">{t('Popup Button')}</Label>
                            <Input
                                value={form.popupButtonLabel}
                                onChange={(event) => onFormChange({ popupButtonLabel: event.target.value })}
                                placeholder={t('Subscribe')}
                                className="h-8 text-xs"
                            />
                        </div>
                    </div>
                ) : null}
            </div>
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="w-full"
                onClick={onOpenMenus}
            >
                <Layers className="h-3.5 w-3.5 mr-1.5" />
                {t('Manage Menus')}
            </Button>
            <div className="grid grid-cols-1 gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="w-full justify-start"
                    onClick={onEditHeader}
                >
                    {t('Edit Header')}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="w-full justify-start"
                    onClick={onEditFooter}
                >
                    {t('Edit Footer')}
                </Button>
            </div>
        </div>
    );
}
