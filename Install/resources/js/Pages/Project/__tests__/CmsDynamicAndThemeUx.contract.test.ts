import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');
const cmsPagePath = path.join(ROOT, 'resources/js/Pages/Project/Cms.tsx');
const panelSiteServicePath = path.join(ROOT, 'app/Cms/Services/CmsPanelSiteService.php');

function read(filePath: string): string {
    return fs.readFileSync(filePath, 'utf8');
}

describe('CMS dynamic bindings and theme UX contracts', () => {
    it('keeps dynamic binding hook metadata parsing and UI actions in Cms builder controls', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('const dynamicControls = bindingMeta.dynamic_controls;');
        expect(cms).toContain('supports_dynamic');
        expect(cms).toContain('binding_namespaces');
        expect(cms).toContain("const renderDynamicBindingHint =");
        expect(cms).toContain("const renderDynamicBindingActions =");
        expect(cms).toContain("{t('Dynamic')}");
        expect(cms).toContain("{t('Clear')}");
        expect(cms).toContain('onApplyExpression');
    });

    it('keeps site settings design-presets token editing and preset UI in Cms page', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain("'design-presets'");
        expect(cms).toContain('theme_token_layers?: ThemeTokenLayeringPayload;');
        expect(cms).toContain('const updateThemeColorToken = useCallback');
        expect(cms).toContain('theme_tokens.colors.primary');
        expect(cms).toContain('theme_tokens.radii.base');
        expect(cms).toContain('theme_tokens.spacing.md');
        expect(cms).toContain('theme_tokens.shadows.card');
        expect(cms).toContain('theme_tokens.breakpoints.lg');
        expect(cms).toContain("['default', 'minimal', 'modern', 'editorial', 'commerce']");
    });

    it('keeps backend site settings persistence normalized through theme token validation and layered resolution', () => {
        const service = read(panelSiteServicePath);

        expect(service).toContain('protected CmsThemeTokenLayerResolver $themeTokenLayers');
        expect(service).toContain('protected CmsThemeTokenValueValidator $themeTokenValidator');
        expect(service).toContain('$this->themeTokenLayers->resolveForSite($site)');
        expect(service).toContain('$nextThemeSettings = $this->normalizeCanonicalThemeTokenSettings($nextThemeSettings);');
        expect(service).toContain('$this->themeTokenValidator->assertValidThemeSettings($nextThemeSettings);');
        expect(service).toContain("$siteUpdates['theme_settings'] = $normalizedThemeSettings;");
    });
});
