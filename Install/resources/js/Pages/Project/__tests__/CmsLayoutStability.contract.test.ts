import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');
const cmsPagePath = path.join(ROOT, 'resources/js/Pages/Project/Cms.tsx');
const adminLayoutPath = path.join(ROOT, 'resources/js/Layouts/AdminLayout.tsx');

function read(filePath: string): string {
    return fs.readFileSync(filePath, 'utf8');
}

describe('CMS layout stability contract', () => {
    it('keeps CMS admin shell overflow guards on main/content wrappers', () => {
        const adminLayout = read(adminLayoutPath);

        expect(adminLayout).toContain("cms-admin-shell__main overflow-x-hidden");
        expect(adminLayout).toContain("cms-admin-shell__content overflow-x-hidden");
    });

    it('keeps visual builder and CMS modal z-index layering above builder canvas', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('cms-visual-builder fixed inset-0 z-[120] bg-background');
        expect(cms).toContain("const cmsModalOverlayClassName = 'z-[220]';");
        expect(cms).toContain("const cmsModalContentClassName = 'z-[221]';");
    });

    it('applies CMS modal z-index classes to dialog and alert dialog overlays in Cms page', () => {
        const cms = read(cmsPagePath);

        const dialogUsages = [...cms.matchAll(/<DialogContent\b([\s\S]*?)>/g)];
        expect(dialogUsages.length).toBeGreaterThan(0);
        dialogUsages.forEach((match) => {
            const tag = match[0];
            expect(tag).toContain('overlayClassName={cmsModalOverlayClassName}');
            expect(tag).toContain('cmsModalContentClassName');
        });

        const alertDialogUsages = [...cms.matchAll(/<AlertDialogContent\b([\s\S]*?)>/g)];
        expect(alertDialogUsages.length).toBeGreaterThan(0);
        alertDialogUsages.forEach((match) => {
            const tag = match[0];
            expect(tag).toContain('overlayClassName={cmsModalOverlayClassName}');
            expect(tag).toContain('className={cmsModalContentClassName}');
        });
    });

    it('keeps media picker dialog full-screen-safe overflow constraints', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('h-[calc(100vh-1rem)] w-[calc(100vw-1rem)]');
        expect(cms).toContain('overflow-hidden p-0');
        expect(cms).toContain('grid-rows-[auto_minmax(0,1fr)]');
        expect(cms).toContain('overflow-y-auto overflow-x-hidden');
    });
});
