import { useCallback, useEffect } from 'react';

import type { BuilderSidebarMode } from '@/builder/editingState';
import { isRecord } from '@/builder/state/sectionProps';

type StateUpdater<T> = (value: T | ((current: T) => T)) => void;

interface BuilderVariantOption {
    key: string;
    label: string;
}

interface SaveBuilderGlobalLayoutOptions {
    silent?: boolean;
    reloadAfterSave?: boolean;
    layoutOverrides?: Record<string, unknown>;
    themeSettingsOverride?: Record<string, unknown>;
}

interface UseCmsFixedSectionVariantControllerOptions {
    selectedFixedSectionKey: string | null;
    selectedFixedSectionLayoutVariantKey: string;
    headerVariant: string;
    footerVariant: string;
    themeSettingsBase: Record<string, unknown>;
    headerLayoutVariantOptions: BuilderVariantOption[];
    footerLayoutVariantOptions: BuilderVariantOption[];
    normalizeSectionTypeKey: (key: string) => string;
    isHeaderSectionKey: (key: string | null | undefined) => boolean;
    isFooterSectionKey: (key: string | null | undefined) => boolean;
    applyFixedSectionAliasProps: (sectionKey: string, props: Record<string, unknown>) => Record<string, unknown>;
    ensurePreviewSectionContainer: (sectionKey: string, label?: string | null) => HTMLElement | null | undefined;
    highlightPreviewSection: (element: HTMLElement | null) => void;
    setThemeSettingsBase: StateUpdater<Record<string, unknown>>;
    setSelectedSectionLocalId: StateUpdater<string | null>;
    setSelectedFixedSectionKey: StateUpdater<string | null>;
    setBuilderSidebarMode: (mode: BuilderSidebarMode) => void;
    handleSaveBuilderGlobalLayout: (options?: SaveBuilderGlobalLayoutOptions) => Promise<boolean>;
    postPreviewLayoutOverride: (headerVariant: string, footerVariant: string) => void;
    t: (key: string) => string;
}

export function useCmsFixedSectionVariantController({
    selectedFixedSectionKey,
    selectedFixedSectionLayoutVariantKey,
    headerVariant,
    footerVariant,
    themeSettingsBase,
    headerLayoutVariantOptions,
    footerLayoutVariantOptions,
    normalizeSectionTypeKey,
    isHeaderSectionKey,
    isFooterSectionKey,
    applyFixedSectionAliasProps,
    ensurePreviewSectionContainer,
    highlightPreviewSection,
    setThemeSettingsBase,
    setSelectedSectionLocalId,
    setSelectedFixedSectionKey,
    setBuilderSidebarMode,
    handleSaveBuilderGlobalLayout,
    postPreviewLayoutOverride,
    t,
}: UseCmsFixedSectionVariantControllerOptions) {
    useEffect(() => {
        if (!selectedFixedSectionKey) {
            return;
        }

        if (isHeaderSectionKey(selectedFixedSectionKey)) {
            const normalizedHeaderKey = normalizeSectionTypeKey(headerVariant || 'webu_header_01') || 'webu_header_01';
            if (normalizedHeaderKey !== selectedFixedSectionKey) {
                setSelectedFixedSectionKey(normalizedHeaderKey);
            }
            return;
        }

        if (isFooterSectionKey(selectedFixedSectionKey)) {
            const normalizedFooterKey = normalizeSectionTypeKey(footerVariant || 'webu_footer_01') || 'webu_footer_01';
            if (normalizedFooterKey !== selectedFixedSectionKey) {
                setSelectedFixedSectionKey(normalizedFooterKey);
            }
        }
    }, [
        footerVariant,
        headerVariant,
        isFooterSectionKey,
        isHeaderSectionKey,
        normalizeSectionTypeKey,
        selectedFixedSectionKey,
        setSelectedFixedSectionKey,
    ]);

    const handleFixedSectionVariantChange = useCallback((kind: 'header' | 'footer', nextVariantKey: string) => {
        const fixedSectionKey = kind === 'header'
            ? (normalizeSectionTypeKey(selectedFixedSectionKey && isHeaderSectionKey(selectedFixedSectionKey) ? selectedFixedSectionKey : headerVariant || 'webu_header_01') || 'webu_header_01')
            : (normalizeSectionTypeKey(selectedFixedSectionKey && isFooterSectionKey(selectedFixedSectionKey) ? selectedFixedSectionKey : footerVariant || 'webu_footer_01') || 'webu_footer_01');
        const variantOptions = kind === 'header' ? headerLayoutVariantOptions : footerLayoutVariantOptions;
        const fallbackVariantKey = variantOptions[0]?.key ?? (kind === 'header' ? 'header-1' : 'footer-1');
        const normalizedVariantKey = nextVariantKey.trim().toLowerCase();
        const safeVariantKey = variantOptions.some((option) => option.key === normalizedVariantKey)
            ? normalizedVariantKey
            : fallbackVariantKey;
        if (safeVariantKey === '') {
            return;
        }

        const currentVariantKey = kind === 'header'
            ? (isHeaderSectionKey(selectedFixedSectionKey) ? selectedFixedSectionLayoutVariantKey : '')
            : (isFooterSectionKey(selectedFixedSectionKey) ? selectedFixedSectionLayoutVariantKey : '');
        const nextThemeSettingsOverride = (() => {
            const next = isRecord(themeSettingsBase) ? { ...themeSettingsBase } : {};
            const layout = isRecord(next.layout) ? { ...next.layout } : {};
            const propStorageKey = kind === 'header' ? 'header_props' : 'footer_props';
            const currentProps = isRecord(layout[propStorageKey]) ? { ...layout[propStorageKey] } : {};
            currentProps.layout_variant = safeVariantKey;
            layout[propStorageKey] = applyFixedSectionAliasProps(fixedSectionKey, currentProps);
            next.layout = layout;
            return next;
        })();
        setThemeSettingsBase(nextThemeSettingsOverride);

        const layoutForMessage = isRecord(themeSettingsBase?.layout) ? themeSettingsBase.layout : {};
        const headerPropsForMessage = isRecord(layoutForMessage.header_props) ? layoutForMessage.header_props : {};
        const footerPropsForMessage = isRecord(layoutForMessage.footer_props) ? layoutForMessage.footer_props : {};
        const headerVariantForPreview = kind === 'header' ? safeVariantKey : (typeof headerPropsForMessage.layout_variant === 'string' ? headerPropsForMessage.layout_variant : 'header-1');
        const footerVariantForPreview = kind === 'footer' ? safeVariantKey : (typeof footerPropsForMessage.layout_variant === 'string' ? footerPropsForMessage.layout_variant : 'footer-1');
        postPreviewLayoutOverride(headerVariantForPreview, footerVariantForPreview);

        const previewTarget = ensurePreviewSectionContainer(fixedSectionKey, kind === 'header' ? t('Header') : t('Footer'));
        setSelectedSectionLocalId(null);
        setSelectedFixedSectionKey(fixedSectionKey);
        setBuilderSidebarMode('settings');
        highlightPreviewSection(previewTarget ?? null);

        if (currentVariantKey === safeVariantKey) {
            return;
        }

        void handleSaveBuilderGlobalLayout({
            silent: true,
            reloadAfterSave: false,
            themeSettingsOverride: nextThemeSettingsOverride,
        });
    }, [
        applyFixedSectionAliasProps,
        ensurePreviewSectionContainer,
        footerLayoutVariantOptions,
        footerVariant,
        handleSaveBuilderGlobalLayout,
        headerLayoutVariantOptions,
        headerVariant,
        highlightPreviewSection,
        isFooterSectionKey,
        isHeaderSectionKey,
        normalizeSectionTypeKey,
        postPreviewLayoutOverride,
        selectedFixedSectionKey,
        selectedFixedSectionLayoutVariantKey,
        setBuilderSidebarMode,
        setSelectedFixedSectionKey,
        setSelectedSectionLocalId,
        setThemeSettingsBase,
        t,
        themeSettingsBase,
    ]);

    return {
        handleFixedSectionVariantChange,
    };
}
