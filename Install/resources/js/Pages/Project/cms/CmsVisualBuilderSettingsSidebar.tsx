import type { ReactNode } from 'react';
import { ArrowLeft, Copy, Settings2, Trash2 } from 'lucide-react';

import {
    HeaderFooterLayoutForm,
    type BuilderLayoutFormState,
    type MenuSourceOption,
} from '@/builder/layout/HeaderFooterLayoutForm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type TranslationFn = (key: string, params?: Record<string, string>) => string;

interface LayoutVariantOption {
    key: string;
    label: string;
}

interface CmsVisualBuilderSettingsSidebarProps {
    builderLayoutForm: BuilderLayoutFormState;
    fixedSectionFieldSetsContent: ReactNode;
    fixedSectionInspectorSummaryContent: ReactNode;
    fixedSectionAuditSummaryContent: ReactNode;
    fixedSectionVariantOptions: LayoutVariantOption[];
    footerVariantOptions: LayoutVariantOption[];
    hasSelectedFixedSection: boolean;
    hasSelectedSection: boolean;
    headerMenuSourceValue: string;
    headerVariantOptions: LayoutVariantOption[];
    isSavingBuilderLayout: boolean;
    menuSourceOptions: MenuSourceOption[];
    mode: 'embedded' | 'standalone';
    normalizeMenuKey: (raw: string, fallback: string) => string;
    onBuilderLayoutFormChange: (patch: Partial<BuilderLayoutFormState>) => void;
    onCloseFixedSection: () => void;
    onCopySelectedSectionJson: () => void;
    onDuplicateSelectedSection: () => void;
    onEditFooter: () => void;
    onEditHeader: () => void;
    onHeaderMenuSourceChange: (value: string) => void;
    onOpenElementsSidebar: () => void;
    onOpenMenus: () => void;
    onOpenSiteSettings: () => void;
    onRemoveSelectedSection: () => void;
    onSelectedFixedSectionLayoutVariantChange: (value: string) => void;
    selectedFixedSectionKind: 'footer' | 'header' | null;
    selectedFixedSectionVariantLabel: string | null;
    selectedFixedSectionLayoutVariantKey: string;
    selectedSectionEditableFieldsContent: ReactNode;
    selectedSectionIndex: number;
    selectedSectionLabel: string | null;
    showHeaderMenuSourceHint: boolean;
    showHeaderMenuSourceSelector: boolean;
    t: TranslationFn;
}

export function CmsVisualBuilderSettingsSidebar({
    builderLayoutForm,
    fixedSectionAuditSummaryContent,
    fixedSectionFieldSetsContent,
    fixedSectionInspectorSummaryContent,
    fixedSectionVariantOptions,
    footerVariantOptions,
    hasSelectedFixedSection,
    hasSelectedSection,
    headerMenuSourceValue,
    headerVariantOptions,
    isSavingBuilderLayout,
    menuSourceOptions,
    mode,
    normalizeMenuKey,
    onBuilderLayoutFormChange,
    onCloseFixedSection,
    onCopySelectedSectionJson,
    onDuplicateSelectedSection,
    onEditFooter,
    onEditHeader,
    onHeaderMenuSourceChange,
    onOpenElementsSidebar,
    onOpenMenus,
    onOpenSiteSettings,
    onRemoveSelectedSection,
    onSelectedFixedSectionLayoutVariantChange,
    selectedFixedSectionKind,
    selectedFixedSectionLayoutVariantKey,
    selectedFixedSectionVariantLabel,
    selectedSectionEditableFieldsContent,
    selectedSectionIndex,
    selectedSectionLabel,
    showHeaderMenuSourceHint,
    showHeaderMenuSourceSelector,
    t,
}: CmsVisualBuilderSettingsSidebarProps) {
    const isStandalone = mode === 'standalone';
    const fixedSectionTypeLabel = selectedFixedSectionKind === 'header' ? t('Header') : t('Footer');
    const emptyState = (
        <div className="rounded-lg border border-dashed bg-background/80 p-4 text-center text-xs text-muted-foreground">
            {t('Select a component on the right to edit its settings')}
        </div>
    );

    return (
        <div className="rounded-lg border p-2 space-y-2">
            {isStandalone ? (
                <>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="w-full"
                        onClick={onOpenElementsSidebar}
                    >
                        <ArrowLeft className="h-3.5 w-3.5 mr-1.5" />
                        {t('Elements')}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="w-full"
                        onClick={onOpenSiteSettings}
                    >
                        <Settings2 className="h-3.5 w-3.5 mr-1.5" />
                        {t('Site Settings')}
                    </Button>
                    <p className="text-xs font-medium text-muted-foreground">{t('Settings')}</p>
                </>
            ) : null}

            {hasSelectedSection ? (
                <>
                    <div className="flex items-center gap-2 flex-wrap">
                        <Badge variant="secondary" className="text-[11px]">
                            #{selectedSectionIndex + 1}
                        </Badge>
                        <div className="min-w-0 rounded-md border bg-muted/20 px-2 py-1.5 text-xs text-foreground/90 truncate">
                            {selectedSectionLabel ?? t('Section')}
                        </div>
                        {isStandalone ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-7 text-xs shrink-0"
                                onClick={onCopySelectedSectionJson}
                            >
                                <Copy className="h-3.5 w-3.5 mr-1" />
                                {t('Copy JSON')}
                            </Button>
                        ) : null}
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className={isStandalone ? 'h-7 text-xs' : 'text-xs'}
                            onClick={onDuplicateSelectedSection}
                        >
                            <Copy className="h-3.5 w-3.5 mr-1" />
                            {t('Duplicate')}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className={`${isStandalone ? 'h-7 ' : ''}text-xs text-destructive hover:text-destructive`}
                            onClick={onRemoveSelectedSection}
                        >
                            <Trash2 className="h-3.5 w-3.5 mr-1" />
                            {t('Remove')}
                        </Button>
                    </div>
                    {selectedSectionEditableFieldsContent}
                </>
            ) : hasSelectedFixedSection ? (
                <div className="space-y-3">
                    {isStandalone ? (
                        <>
                            <div className="flex items-center justify-between gap-2">
                                <Badge variant="secondary" className="text-[11px]">
                                    {fixedSectionTypeLabel}
                                    {selectedFixedSectionVariantLabel ? ` · ${selectedFixedSectionVariantLabel}` : ''}
                                </Badge>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={onCloseFixedSection}
                                >
                                    {t('Back')}
                                </Button>
                            </div>
                            <p className="text-xs text-muted-foreground">{t('გლობალური სექციის რედაქტირება')}</p>
                        </>
                    ) : (
                        <div className="min-w-0 rounded-md border bg-muted/20 px-2 py-1.5 text-xs text-foreground/90 truncate">
                            {selectedFixedSectionVariantLabel}
                        </div>
                    )}

                    <div className="space-y-1 rounded-md border bg-muted/20 p-2">
                        <Label className="text-xs">
                            {selectedFixedSectionKind === 'header' ? t('Header Version') : t('Footer Version')}
                        </Label>
                        <select
                            value={selectedFixedSectionLayoutVariantKey}
                            onChange={(event) => onSelectedFixedSectionLayoutVariantChange(event.target.value)}
                            disabled={isSavingBuilderLayout}
                            className="w-full rounded-md border bg-background px-2 py-1.5 text-xs"
                        >
                            {(selectedFixedSectionKind === 'header' ? headerVariantOptions : footerVariantOptions).map((option) => (
                                <option key={`selected-fixed-variant-${option.key}`} value={option.key}>
                                    {option.label}
                                </option>
                            ))}
                            {selectedFixedSectionKind == null
                                ? fixedSectionVariantOptions.map((option) => (
                                    <option key={`fallback-fixed-variant-${option.key}`} value={option.key}>
                                        {option.label}
                                    </option>
                                ))
                                : null}
                        </select>
                        {isSavingBuilderLayout ? (
                            <p className="text-[11px] text-muted-foreground">{t('Updating preview...')}</p>
                        ) : null}
                    </div>

                    {showHeaderMenuSourceSelector ? (
                        <div className="space-y-1 rounded-md border bg-muted/20 p-2">
                            <Label className="text-xs">{t('Header Menu Source')}</Label>
                            <select
                                value={headerMenuSourceValue}
                                onChange={(event) => onHeaderMenuSourceChange(event.target.value)}
                                className="w-full rounded-md border bg-background px-2 py-1.5 text-xs"
                            >
                                {menuSourceOptions.map((option) => (
                                    <option key={`header-source-fixed-${option.key}`} value={option.key}>
                                        {option.isSystem ? `${option.label} (${t('System')})` : option.label}
                                    </option>
                                ))}
                            </select>
                            {showHeaderMenuSourceHint ? (
                                <p className="text-[11px] text-muted-foreground">
                                    {t('Menu links are loaded automatically from Menu Builder.')}
                                </p>
                            ) : null}
                        </div>
                    ) : null}

                    <div className="grid gap-2">
                        {fixedSectionInspectorSummaryContent}
                        {fixedSectionAuditSummaryContent}
                        {fixedSectionFieldSetsContent}
                    </div>
                </div>
            ) : isStandalone ? (
                <HeaderFooterLayoutForm
                    form={builderLayoutForm}
                    onFormChange={onBuilderLayoutFormChange}
                    headerVariantOptions={headerVariantOptions}
                    footerVariantOptions={footerVariantOptions}
                    menuSourceOptions={menuSourceOptions}
                    onEditHeader={onEditHeader}
                    onEditFooter={onEditFooter}
                    onOpenMenus={onOpenMenus}
                    normalizeMenuKey={normalizeMenuKey}
                    t={t}
                />
            ) : (
                emptyState
            )}
        </div>
    );
}
