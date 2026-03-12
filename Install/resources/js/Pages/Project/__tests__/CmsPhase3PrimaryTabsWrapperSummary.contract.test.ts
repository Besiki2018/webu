import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');

function read(relativePath: string): string {
    return fs.readFileSync(path.join(ROOT, relativePath), 'utf8');
}

describe('CMS Phase 3 primary tabs wrapper summary contracts', () => {
    it('keeps shared canonical primary tab bucket mapping and tab renderer markers in Cms.tsx', () => {
        const cms = read('resources/js/Pages/Project/Cms.tsx');

        expect(cms).toContain("type CanonicalPrimaryPanelTab = 'content' | 'style' | 'advanced';");
        expect(cms).toContain('function mapCanonicalControlGroupToPrimaryPanelTab(group: CanonicalControlGroup): CanonicalPrimaryPanelTab');
        expect(cms).toContain('function buildCanonicalPrimaryPanelTabFieldSetBuckets(');
        expect(cms).toContain('const renderCanonicalControlGroupFieldSets = (');
        expect(cms).toContain('<Tabs');
        expect(cms).toContain('<TabsList');
        expect(cms).toContain('<TabsTrigger');
        expect(cms).toContain('<TabsContent');
        expect(cms).toContain("data-webu-role={compact ? 'builder-control-panel-primary-tabs-compact' : 'builder-control-panel-primary-tabs'}");
        expect(cms).toContain('data-webu-role="builder-control-panel-primary-tab-trigger"');
        expect(cms).toContain('data-webu-role="builder-control-panel-primary-tab-content"');
        expect(cms).toContain('data-webu-primary-tab={bucket.tab}');
    });

    it('reuses primary tab renderer for selected and fixed section settings panels', () => {
        const cms = read('resources/js/Pages/Project/Cms.tsx');

        expect(cms).toContain('{renderCanonicalControlGroupFieldSets(selectedSectionEditableSchemaFields, {');
        expect(cms).toContain('{renderCanonicalControlGroupFieldSets(selectedFixedSectionEditableFields, {');
        expect(cms).toContain('panelKey: `selected-section-${selectedSectionDraft.localId}');
        expect(cms).toContain('panelKey: `fixed-section-${selectedFixedSectionKey}`');
    });

    it('documents wrapper-level closure semantics and evidence mapping for roadmap line 154', () => {
        const doc = read('docs/qa/CMS_PHASE3_PRIMARY_TABS_WRAPPER_SUMMARY.md');

        expect(doc).toContain('PROJECT_ROADMAP_TASKS_KA.md:154');
        expect(doc).toContain('Closure Semantics (Wrapper Level)');
        expect(doc).toContain('buildCanonicalPrimaryPanelTabFieldSetBuckets(...)');
        expect(doc).toContain('renderCanonicalControlGroupFieldSets(...)');
        expect(doc).toContain('TabsTrigger');
        expect(doc).toContain('builder-control-panel-primary-tab-trigger');
        expect(doc).toContain('selected page section editor controls');
        expect(doc).toContain('fixed header/footer editor controls');
        expect(doc).toContain('CMS_PHASE3_RESPONSIVE_STATE_WRAPPER_SUMMARY.md');
        expect(doc).toContain('CMS_PHASE3_WRAPPER_CONTROL_GROUP_PARITY_SUMMARY.md');
        expect(doc).toContain('CMS_ELEMENT_PANEL_UX_CLEANUP_PHASE3_WRAPPER_SUMMARY.md');
    });
});
