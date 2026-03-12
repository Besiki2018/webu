import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');

function read(relativePath: string): string {
    return fs.readFileSync(path.join(ROOT, relativePath), 'utf8');
}

describe('CMS telemetry collectors contracts (P6-G1-01)', () => {
    it('keeps builder telemetry collector endpoint and canonical event names', () => {
        const cms = read('resources/js/Pages/Project/Cms.tsx');

        expect(cms).toContain('/panel/sites/${site.id}/cms/telemetry');
        expect(cms).toContain("schema_version: 'cms.telemetry.event.v1'");
        expect(cms).toContain("source: 'builder'");
        expect(cms).toContain('emitCmsBuilderTelemetry');
        expect(cms).toContain('cms_builder.open');
        expect(cms).toContain('cms_builder.save_draft');
        expect(cms).toContain('cms_builder.publish_page');
        expect(cms).toContain('cms_builder.publish_page_failed');
        expect(cms).toContain('cms_builder.page_detail_loaded');
        expect(cms).toContain("flow: 'builder_page_load'");
        expect(cms).toContain('section_count: contentPerf.sectionCount');
        expect(cms).toContain('json_node_count: contentPerf.jsonNodeCount');
        expect(cms).toContain('size_class: contentPerf.sizeClass');
        expect(cms).toContain("flow: 'publish'");
        expect(cms).toContain('trace_id: publishTraceId');
        expect(cms).toContain('duration_ms: Math.max(0, Date.now() - publishStartedAt)');
    });

    it('keeps runtime telemetry collector helper and public endpoint wiring in BuilderService runtime script', () => {
        const builderService = read('app/Services/BuilderService.php');

        expect(builderService).toContain("'telemetry_url' => $site ? $apiBaseUrl.\"/public/sites/{$site->id}/cms/telemetry\" : null");
        expect(builderService).toContain('function telemetryEndpointUrl()');
        expect(builderService).toContain('function postRuntimeTelemetry(eventName, routeInfo, meta)');
        expect(builderService).toContain("source: 'runtime'");
        expect(builderService).toContain('cms_runtime.route_hydrated');
        expect(builderService).toContain('cms_runtime.hydrate_failed');
        expect(builderService).toContain("keepalive: true");
    });

    it('documents P6-G1-01 telemetry collector scope and deferred storage pipeline tasks', () => {
        const doc = read('docs/architecture/CMS_TELEMETRY_COLLECTOR_P6_G1_01.md');
        const schema = read('docs/architecture/schemas/cms-telemetry-event.v1.schema.json');

        expect(doc).toContain('P6-G1-01');
        expect(doc).toContain('CmsTelemetryCollectorService');
        expect(doc).toContain('P6-G1-02');
        expect(doc).toContain('P6-G1-03');
        expect(doc).toContain('P6-G1-04');
        expect(doc).toContain('cms_runtime.route_hydrated');
        expect(doc).toContain('cms_builder.save_draft');

        expect(schema).toContain('cms.telemetry.event.v1');
        expect(schema).toContain('"source"');
        expect(schema).toContain('"events"');
    });
});
