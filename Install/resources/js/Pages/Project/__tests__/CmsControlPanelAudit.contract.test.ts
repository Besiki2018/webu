import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');
const cmsPagePath = path.join(ROOT, 'resources/js/Pages/Project/Cms.tsx');
const auditDocPath = path.join(ROOT, 'docs/qa/CMS_BUILDER_CONTROL_PANEL_AUDIT_D1.md');

function read(filePath: string): string {
    return fs.readFileSync(filePath, 'utf8');
}

describe('CMS builder control panel audit contracts', () => {
    it('keeps canonical control-group audit row builder and summary renderer in Cms builder', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('interface CanonicalControlGroupAuditRow');
        expect(cms).toContain('function buildCanonicalControlGroupAuditRows(fields: SchemaPrimitiveField[]): CanonicalControlGroupAuditRow[]');
        expect(cms).toContain('const renderControlGroupAuditSummary = (rows: CanonicalControlGroupAuditRow[], compact: boolean): ReactNode => {');
        expect(cms).toContain("data-webu-role={compact ? 'builder-control-group-audit-compact' : 'builder-control-group-audit'}");
        expect(cms).toContain("{t('Control Groups')}");
        expect(cms).toContain("{t('Panel audit')}");
    });

    it('renders control-group audit summary for selected section and fixed header/footer controls', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('const selectedSectionControlGroupAuditRows = useMemo(');
        expect(cms).toContain('const selectedFixedSectionControlGroupAuditRows = useMemo(');
        expect(cms).toContain('{renderControlGroupAuditSummary(selectedSectionControlGroupAuditRows, compact)}');
        expect(cms).toContain('{renderControlGroupAuditSummary(selectedFixedSectionControlGroupAuditRows, true)}');
    });

    it('reuses canonical control-group fieldset renderer for selected and fixed section panels', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('interface CanonicalControlGroupFieldSet');
        expect(cms).toContain('function buildCanonicalControlGroupFieldSets(fields: SchemaPrimitiveField[]): CanonicalControlGroupFieldSet[]');
        expect(cms).toContain('const renderCanonicalControlGroupFieldSets = (');
        expect(cms).toContain('function buildCanonicalPrimaryPanelTabFieldSetBuckets(');
        expect(cms).toContain("data-webu-role={compact ? 'builder-control-panel-primary-tabs-compact' : 'builder-control-panel-primary-tabs'}");
        expect(cms).toContain('data-webu-role="builder-control-panel-primary-tab-trigger"');
        expect(cms).toContain('data-webu-role="builder-control-panel-primary-tab-content"');
        expect(cms).toContain('data-webu-primary-tab={bucket.tab}');
        expect(cms).toContain("data-webu-role={compact ? 'builder-control-group-fieldsets-compact' : 'builder-control-group-fieldsets'}");
        expect(cms).toContain('data-webu-role="builder-control-group-fieldset"');
        expect(cms).toContain('data-webu-role="builder-control-group-fieldset-items"');
        expect(cms).toContain('{renderCanonicalControlGroupFieldSets(selectedSectionEditableSchemaFields, {');
        expect(cms).toContain('{renderCanonicalControlGroupFieldSets(selectedFixedSectionEditableFields, {');
    });

    it('documents sprint d1 control panel audit scope and follow-up path', () => {
        const doc = read(auditDocPath);

        expect(doc).toContain('CMS Builder Control Panel Audit (Sprint D1)');
        expect(doc).toContain('P3-D1-01');
        expect(doc).toContain('P3-D1-02');
        expect(doc).toContain('SchemaPrimitiveField.control_meta');
        expect(doc).toContain('selected page section editor controls');
        expect(doc).toContain('fixed header/footer editor controls');
        expect(doc).toContain('renderCanonicalControlGroupFieldSets');
        expect(doc).toContain('Follow-up (D1-04 / D2)');
        expect(doc).toContain('D1-04 / wrapper line `PROJECT_ROADMAP_TASKS_KA.md:154` completed');
        expect(doc).toContain('CMS_PHASE3_PRIMARY_TABS_WRAPPER_SUMMARY.md');
    });
});
