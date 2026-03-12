import { describe, expect, it } from 'vitest';

import { buildPageComponentCode, sectionsDraftToCode } from './codeGenerator';
import { parseLayoutCode } from './parseLayoutCode';

describe('codeGenerator', () => {
    it('emits JSX from explicit section props without depending on propsText', () => {
        const code = sectionsDraftToCode([{
            localId: 'hero-1',
            type: 'webu_general_hero_01',
            props: {
                title: 'Online store',
                buttonText: 'Shop now',
            },
            propsText: '',
        }]);

        expect(code).toContain('<HeroSection');
        expect(code).toContain('title="Online store"');
        expect(code).toContain('buttonText="Shop now"');
    });

    it('builds export-ready page code from deterministic builder data', () => {
        const code = buildPageComponentCode([
            {
                localId: 'header-1',
                type: 'webu_header_01',
                props: {
                    logoText: 'Acme',
                    ctaText: 'Shop now',
                },
                propsText: '',
            },
            {
                localId: 'hero-1',
                type: 'webu_general_hero_01',
                props: {
                    title: 'Online store',
                    buttonText: 'Buy now',
                },
                propsText: '',
            },
            {
                localId: 'footer-1',
                type: 'webu_footer_01',
                props: {
                    logoText: 'Acme',
                    copyright: '© 2026 Acme',
                },
                propsText: '',
            },
        ], {
            pageName: 'Home',
            revisionSource: 'latest',
        });

        expect(code).toContain("import Header from '@/components/Header';");
        expect(code).toContain("import Footer from '@/components/Footer';");
        expect(code).toContain("import HeroSection from '@/sections/HeroSection';");
        expect(code).toContain('const pageData = {');
        expect(code).toContain('"pageName": "Home"');
        expect(code).toContain('"type": "webu_general_hero_01"');
        expect(code).toContain('"type": "webu_footer_01"');
        expect(code).toContain('"title": "Online store"');
        expect(code).toContain('const sectionComponents = {');
        expect(code).toContain('"webu_header_01": Header');
        expect(code).toContain('"webu_footer_01": Footer');
        expect(code).toContain('"webu_general_hero_01": HeroSection');
        expect(code).toContain('data-webu-section={section.type}');
        expect(code).toContain('data-webu-section-local-id={section.id}');
        expect(code).toContain('<SectionComponent {...section.props} />');
    });

    it('parses generated pageData back into builder sections for reverse sync', () => {
        const generatedCode = buildPageComponentCode([
            {
                localId: 'hero-1',
                type: 'webu_general_hero_01',
                props: {
                    title: 'Online store',
                    buttonText: 'Buy now',
                },
                propsText: '',
            },
        ], {
            pageName: 'Home',
        });

        const result = parseLayoutCode(generatedCode);

        expect(result.ok).toBe(true);
        expect(result.sections).toHaveLength(1);
        expect(result.sections[0]).toMatchObject({
            type: 'webu_general_hero_01',
            props: {
                title: 'Online store',
                buttonText: 'Buy now',
            },
        });
    });
});
