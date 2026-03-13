import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
export const ROOT = path.resolve(TEST_DIR, '../../../../..');

export const CURRENT_BUILDER_DOC_PATHS = [
    'resources/js/builder/ARCHITECTURE.md',
    'resources/js/builder/README.md',
    'resources/js/builder/docs/RUNTIME_VERIFICATION.md',
    'resources/js/builder/docs/SCHEMA_DRIVEN_BUILDER_VERIFICATION.md',
    'resources/js/builder/docs/PHASE10_MIGRATION_REPORT.md',
    'docs/final-builder-consolidation-audit.md',
    'docs/registry-unification-audit.md',
    'docs/mutation-pipeline-audit.md',
] as const;

export function read(relativeOrAbsolutePath: string): string {
    const filePath = path.isAbsolute(relativeOrAbsolutePath)
        ? relativeOrAbsolutePath
        : path.join(ROOT, relativeOrAbsolutePath);
    return fs.readFileSync(filePath, 'utf8');
}

export function readCurrentBuilderDocs(): string {
    return CURRENT_BUILDER_DOC_PATHS.map((filePath) => read(filePath)).join('\n\n');
}
