/**
 * Phase 8 — Legacy system detection.
 * Ensures no direct component imports in renderer, and only the central registry wires real components.
 */

import { describe, expect, it } from 'vitest';
import * as fs from 'node:fs';
import * as path from 'node:path';

const BUILDER_ROOT = path.resolve(__dirname, '..');

function* walkTsFiles(dir: string): Generator<string> {
    if (!fs.existsSync(dir)) return;
    const entries = fs.readdirSync(dir, { withFileTypes: true });
    for (const e of entries) {
        const full = path.join(dir, e.name);
        if (e.isDirectory()) {
            if (e.name !== 'node_modules' && e.name !== '__tests__') yield* walkTsFiles(full);
        } else if (e.isFile() && (e.name.endsWith('.ts') || e.name.endsWith('.tsx'))) {
            yield full;
        }
    }
}

describe('Phase 8 — Legacy detection', () => {
    it('BuilderCanvas does not import Header/Footer/Hero directly', () => {
        const canvasPath = path.join(BUILDER_ROOT, 'visual/BuilderCanvas.tsx');
        const content = fs.readFileSync(canvasPath, 'utf8');
        expect(content).not.toMatch(/from\s+['\"][^'"]*layout\/Header['\"]/);
        expect(content).not.toMatch(/from\s+['\"][^'"]*layout\/Footer['\"]/);
        expect(content).not.toMatch(/from\s+['\"][^'"]*sections\/Hero['\"]/);
    });

    it('only centralComponentRegistry imports layout/Header, layout/Footer, sections/Hero in builder', () => {
        const centralPath = path.join(BUILDER_ROOT, 'centralComponentRegistry.ts');
        const filesWithImports: string[] = [];
        for (const file of walkTsFiles(BUILDER_ROOT)) {
            const content = fs.readFileSync(file, 'utf8');
            const rel = path.relative(BUILDER_ROOT, file);
            if (
                (content.includes("layout/Header") || content.includes('layout/Footer') || content.includes('sections/Hero'))
                && /from\s+['\"][^'"]*(layout\/Header|layout\/Footer|sections\/Hero)/.test(content)
            ) {
                filesWithImports.push(rel);
            }
        }
        const allowed = ['centralComponentRegistry.ts', 'componentRegistry.ts'];
        const onlyAllowed = filesWithImports.every((f) => allowed.some((a) => f === a || f.endsWith(a)));
        expect(onlyAllowed, `Only central registry (and main registry for schema) may import from section paths; found in: ${filesWithImports.join(', ')}`).toBe(true);
        expect(filesWithImports.length).toBeGreaterThan(0);
    });

    it('componentRegistry imports only schemas from Header/Footer/Hero, not components', () => {
        const regPath = path.join(BUILDER_ROOT, 'componentRegistry.ts');
        const content = fs.readFileSync(regPath, 'utf8');
        expect(content).not.toMatch(/import\s+.*\b(Header|Footer|Hero)\s+.*from\s+['\"][^'\"]*(layout\/Header|layout\/Footer|sections\/Hero)/);
        expect(content).toMatch(/Header\.schema|Footer\.schema|Hero\.schema/);
    });
});
