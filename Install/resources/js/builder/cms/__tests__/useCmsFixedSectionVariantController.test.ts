import { renderHook, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { useCmsFixedSectionVariantController } from '@/builder/cms/useCmsFixedSectionVariantController';

function buildOptions(overrides: Partial<Parameters<typeof useCmsFixedSectionVariantController>[0]> = {}) {
    return {
        selectedFixedSectionKey: 'webu_header_01',
        selectedFixedSectionLayoutVariantKey: 'header-1',
        headerVariant: 'webu_header_01',
        footerVariant: 'webu_footer_01',
        themeSettingsBase: {
            layout: {
                header_props: { layout_variant: 'header-1' },
                footer_props: { layout_variant: 'footer-1' },
            },
        },
        headerLayoutVariantOptions: [
            { key: 'header-1', label: 'Header 1' },
            { key: 'header-2', label: 'Header 2' },
        ],
        footerLayoutVariantOptions: [
            { key: 'footer-1', label: 'Footer 1' },
            { key: 'footer-2', label: 'Footer 2' },
        ],
        normalizeSectionTypeKey: (key: string) => key.trim().toLowerCase(),
        isHeaderSectionKey: (key: string | null | undefined) => typeof key === 'string' && key.startsWith('webu_header_'),
        isFooterSectionKey: (key: string | null | undefined) => typeof key === 'string' && key.startsWith('webu_footer_'),
        applyFixedSectionAliasProps: vi.fn((sectionKey: string, props: Record<string, unknown>) => ({ ...props, aliasedFor: sectionKey })),
        ensurePreviewSectionContainer: vi.fn(() => document.createElement('section')),
        highlightPreviewSection: vi.fn(),
        setThemeSettingsBase: vi.fn(),
        setSelectedSectionLocalId: vi.fn(),
        setSelectedFixedSectionKey: vi.fn(),
        setBuilderSidebarMode: vi.fn(),
        handleSaveBuilderGlobalLayout: vi.fn(async () => true),
        postPreviewLayoutOverride: vi.fn(),
        t: (key: string) => key,
        ...overrides,
    };
}

describe('useCmsFixedSectionVariantController', () => {
    it('keeps selected fixed section key in sync with the active header/footer layout variant', async () => {
        const options = buildOptions({
            selectedFixedSectionKey: 'webu_header_99',
            headerVariant: 'webu_header_02',
        });

        renderHook(() => useCmsFixedSectionVariantController(options));

        await waitFor(() => {
            expect(options.setSelectedFixedSectionKey).toHaveBeenCalledWith('webu_header_02');
        });
    });

    it('updates fixed section variants, preview override and save flow when the variant changes', async () => {
        const previewTarget = document.createElement('section');
        const options = buildOptions({
            ensurePreviewSectionContainer: vi.fn(() => previewTarget),
        });

        const { result } = renderHook(() => useCmsFixedSectionVariantController(options));

        result.current.handleFixedSectionVariantChange('header', 'header-2');

        expect(options.setThemeSettingsBase).toHaveBeenCalledWith({
            layout: {
                header_props: { layout_variant: 'header-2', aliasedFor: 'webu_header_01' },
                footer_props: { layout_variant: 'footer-1' },
            },
        });
        expect(options.postPreviewLayoutOverride).toHaveBeenCalledWith('header-2', 'footer-1');
        expect(options.setSelectedSectionLocalId).toHaveBeenCalledWith(null);
        expect(options.setSelectedFixedSectionKey).toHaveBeenCalledWith('webu_header_01');
        expect(options.setBuilderSidebarMode).toHaveBeenCalledWith('settings');
        expect(options.highlightPreviewSection).toHaveBeenCalledWith(previewTarget);
        expect(options.handleSaveBuilderGlobalLayout).toHaveBeenCalledWith(expect.objectContaining({
            silent: true,
            reloadAfterSave: false,
        }));
    });

    it('does not resave when the selected fixed section variant is unchanged', () => {
        const options = buildOptions({
            selectedFixedSectionLayoutVariantKey: 'header-2',
            themeSettingsBase: {
                layout: {
                    header_props: { layout_variant: 'header-2' },
                    footer_props: { layout_variant: 'footer-1' },
                },
            },
        });

        const { result } = renderHook(() => useCmsFixedSectionVariantController(options));

        result.current.handleFixedSectionVariantChange('header', 'header-2');

        expect(options.handleSaveBuilderGlobalLayout).not.toHaveBeenCalled();
        expect(options.postPreviewLayoutOverride).toHaveBeenCalledWith('header-2', 'footer-1');
        expect(options.setBuilderSidebarMode).toHaveBeenCalledWith('settings');
    });
});
