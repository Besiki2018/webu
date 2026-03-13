import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

import { readCurrentBuilderDocs } from './builderContractTestUtils';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');
const cmsPagePath = path.join(ROOT, 'resources/js/Pages/Project/Cms.tsx');
const templateImportServicePath = path.join(ROOT, 'app/Services/TemplateImportService.php');
function read(filePath: string): string {
    return fs.readFileSync(filePath, 'utf8');
}

describe('CMS custom CSS scoping parity contracts (D3-03)', () => {
    it('keeps builder custom CSS sanitization + recursive scoping helpers for element-level isolation', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('let builderCustomCssScopeSequence = 0;');
        expect(cms).toContain('function normalizeCustomCssScopingInput(raw: string): string {');
        expect(cms).toContain(".replace(/<\\s*\\/?\\s*style\\b[^>]*>/gi, '')");
        expect(cms).toContain(".replace(/\\/\\*[\\s\\S]*?\\*\\//g, '')");
        expect(cms).toContain('.replace(/@(?:import|charset|namespace)\\b[\\s\\S]*?;/gi, \'\')');
        expect(cms).toContain('function splitCssSelectorListForScoping(selectorList: string): string[] {');
        expect(cms).toContain('function prefixCssSelectorForScope(selector: string, scopeSelector: string): string {');
        expect(cms).toContain('function findMatchingCssBraceIndex(css: string, openIndex: number): number {');
        expect(cms).toContain('function scopeCustomCssTextRecursively(rawCss: string, scopeSelector: string): string {');
        expect(cms).toContain('/^@(?:media|supports|container|layer)\\b/i.test(header)');
        expect(cms).toContain('/^@(?:keyframes|-webkit-keyframes|-moz-keyframes|font-face|property)\\b/i.test(header)');
        expect(cms).toContain('function upsertBuilderScopedCustomCss(container: HTMLElement, customCss: string, customCssSeed?: string): void {');
        expect(cms).toContain("container.setAttribute('data-webu-builder-custom-css-scope-id', scopeId);");
        expect(cms).toContain("container.setAttribute('data-webu-builder-custom-css-scope', scopeId);");
        expect(cms).toContain("styleNode.setAttribute('data-webu-builder-custom-css-style-for', scopeId);");
        expect(cms).toContain("container.setAttribute('data-webu-builder-custom-css-scoped', '1');");
        expect(cms).toContain("container.setAttribute('data-webu-builder-custom-css-scope-hash', `${scopeId}:${scopedCss.length}`);");
        expect(cms).toContain('upsertBuilderScopedCustomCss(container, customCss, htmlId || normalizedSectionType);');
    });

    it('keeps runtime scoping helpers and markers aligned with builder behavior', () => {
        const service = read(templateImportServicePath);

        expect(service).toContain('var runtimeCustomCssScopeSequence = 0;');
        expect(service).toContain('function normalizeCustomCssScopingInput(raw) {');
        expect(service).toContain("replace(/<\\s*\\/?\\s*style\\b[^>]*>/gi, '')");
        expect(service).toContain("replace(/\\/\\*[\\s\\S]*?\\*\\//g, '')");
        expect(service).toContain("replace(/@(?:import|charset|namespace)\\b[\\s\\S]*?;/gi, '')");
        expect(service).toContain('function splitCssSelectorListForScoping(selectorList) {');
        expect(service).toContain('function prefixCssSelectorForScope(selector, scopeSelector) {');
        expect(service).toContain('function findMatchingCssBraceIndex(css, openIndex) {');
        expect(service).toContain('function scopeCustomCssTextRecursively(rawCss, scopeSelector) {');
        expect(service).toContain('/^@(?:media|supports|container|layer)\\b/i.test(header)');
        expect(service).toContain('/^@(?:keyframes|-webkit-keyframes|-moz-keyframes|font-face|property)\\b/i.test(header)');
        expect(service).toContain('function upsertRuntimeScopedCustomCss(container, customCss, customCssSeed) {');
        expect(service).toContain("container.setAttribute('data-webu-runtime-custom-css-scope-id', scopeId);");
        expect(service).toContain("container.setAttribute('data-webu-runtime-custom-css-scope', scopeId);");
        expect(service).toContain("styleNode.setAttribute('data-webu-runtime-custom-css-style-for', scopeId);");
        expect(service).toContain("container.setAttribute('data-webu-runtime-custom-css-scoped', '1');");
        expect(service).toContain("container.setAttribute('data-webu-runtime-custom-css-scope-hash', scopeId + ':' + String(scopedCss.length));");
        expect(service).toContain('upsertRuntimeScopedCustomCss(');
    });

    it('documents D3-03 scoping model, sanitization rules, and parity markers', () => {
        const doc = readCurrentBuilderDocs();

        expect(doc).toContain('componentRegistry.ts');
        expect(doc).toContain('updatePipeline.ts');
        expect(doc).toContain('schema-driven builder');
        expect(doc).toContain('Sidebar generates controls from schema');
    });
});
