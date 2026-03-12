import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');

function read(relativePath: string): string {
    return fs.readFileSync(path.join(ROOT, relativePath), 'utf8');
}

describe('CMS builder editor performance baseline contracts (Roadmap 747)', () => {
    it('keeps builder page detail load telemetry measurement and size classification in Cms.tsx', () => {
        const cms = read('resources/js/Pages/Project/Cms.tsx');

        expect(cms).toContain('function summarizeBuilderPageContentForTelemetry');
        expect(cms).toContain('cms_builder.page_detail_loaded');
        expect(cms).toContain("flow: 'builder_page_load'");
        expect(cms).toContain('duration_ms: Math.max(0, Date.now() - loadStartedAt)');
        expect(cms).toContain('section_count: contentPerf.sectionCount');
        expect(cms).toContain('builder_node_count: contentPerf.builderNodeCount');
        expect(cms).toContain('json_node_count: contentPerf.jsonNodeCount');
        expect(cms).toContain('size_class: contentPerf.sizeClass');
        expect(cms).toContain('structuralCount >= 80 || jsonNodeCount >= 5000');
        expect(cms).toContain('structuralCount >= 30 || jsonNodeCount >= 1500');
    });

    it('keeps daily aggregate metrics keys and roadmap baseline doc for builder editor performance', () => {
        const agg = read('app/Services/CmsTelemetryAggregatedMetricsService.php');
        const doc = read('docs/architecture/CMS_BUILDER_EDITOR_PERFORMANCE_BASELINE.md');

        expect(agg).toContain("'cms_builder.page_detail_loaded'");
        expect(agg).toContain("'builder_editor_performance' => [");
        expect(agg).toContain("'page_detail_load_latency_p95_ms'");
        expect(agg).toContain("'page_detail_load_large_count'");
        expect(agg).toContain("'builder_editor_performance' => data_get");

        expect(doc).toContain('Builder editor performance (large pages, many nodes)');
        expect(doc).toContain('cms_builder.page_detail_loaded');
        expect(doc).toContain('page_detail_load_latency_p95_ms');
        expect(doc).toContain('structural_count >= 80');
        expect(doc).toContain('json_node_count >= 5000');
    });
});
