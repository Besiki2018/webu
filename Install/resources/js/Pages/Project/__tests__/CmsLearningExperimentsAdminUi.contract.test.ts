import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');

function read(relativePath: string): string {
    return fs.readFileSync(path.join(ROOT, relativePath), 'utf8');
}

describe('CMS learning/experiments admin UI contract (Phase 6 summary)', () => {
    it('keeps activity-section admin UI wired to learning rules and experiments endpoints', () => {
        const cms = read('resources/js/Pages/Project/Cms.tsx');

        expect(cms).toContain("const isLearningAdminActivitySection = isAdminUser && activeSection === 'activity'");
        expect(cms).toContain('/panel/sites/${site.id}/cms/learning/rules');
        expect(cms).toContain('/panel/sites/${site.id}/cms/learning/experiments');
        expect(cms).toContain('/cms/learning/rules/${rule.id}/disable');
        expect(cms).toContain('/cms/learning/experiments/${experiment.id}/disable');
        expect(cms).toContain('data-webu-learning-admin-panel="activity"');
        expect(cms).toContain('data-webu-learning-rules-list');
        expect(cms).toContain('data-webu-learning-experiments-list');
        expect(cms).toContain('data-webu-learning-rule-detail-json');
        expect(cms).toContain('data-webu-learning-experiment-detail-json');
    });

    it('documents the Phase 6 admin UI baseline and backend dependency routes', () => {
        const doc = read('docs/architecture/CMS_LEARNING_EXPERIMENTS_ADMIN_UI_P6_BASELINE.md');

        expect(doc).toContain('Admin UI for learning and experiments');
        expect(doc).toContain('Activity');
        expect(doc).toContain('PanelLearningController');
        expect(doc).toContain('/cms/learning/rules');
        expect(doc).toContain('/cms/learning/experiments');
        expect(doc).toContain('P6-G2-04');
    });
});
