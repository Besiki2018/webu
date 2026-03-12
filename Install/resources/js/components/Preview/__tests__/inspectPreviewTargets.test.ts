import { describe, expect, it, beforeEach } from 'vitest';
import {
    resolvePreviewTargetAtPoint,
    resolvePreviewTargetFromElement,
    resolveComponentInspectableTarget,
    resolveElementAtPoint,
    resolveSectionOnlyFallbackTarget,
} from '../inspectPreviewTargets';

describe('inspectPreviewTargets', () => {
    describe('resolveComponentInspectableTarget', () => {
        it('prefers leaf field (data-webu-field) over scope when both exist', () => {
            const doc = document.implementation.createHTMLDocument('');
            const section = doc.createElement('section');
            section.setAttribute('data-webu-section', 'hero');
            const scope = doc.createElement('div');
            scope.setAttribute('data-webu-field-scope', 'items.0');
            const title = doc.createElement('h3');
            title.setAttribute('data-webu-field', 'title');
            title.textContent = 'Card Title';
            scope.appendChild(title);
            section.appendChild(scope);
            doc.body.appendChild(section);

            const result = resolveComponentInspectableTarget(title);
            expect(result).toBe(title);
            expect(result?.getAttribute('data-webu-field')).toBe('title');
        });

        it('returns leaf field when clicking directly on it', () => {
            const doc = document.implementation.createHTMLDocument('');
            const img = doc.createElement('img');
            img.setAttribute('data-webu-field', 'heroImage');
            img.setAttribute('src', '/hero.jpg');
            doc.body.appendChild(img);

            expect(resolveComponentInspectableTarget(img)).toBe(img);
        });

        it('returns deepest scope when no leaf field exists', () => {
            const doc = document.implementation.createHTMLDocument('');
            const section = doc.createElement('section');
            section.setAttribute('data-webu-section', 'cards');
            const outerScope = doc.createElement('div');
            outerScope.setAttribute('data-webu-field-scope', 'items');
            const innerScope = doc.createElement('div');
            innerScope.setAttribute('data-webu-field-scope', 'items.0');
            const span = doc.createElement('span');
            span.textContent = 'Item text';
            innerScope.appendChild(span);
            outerScope.appendChild(innerScope);
            section.appendChild(outerScope);
            doc.body.appendChild(section);

            const result = resolveComponentInspectableTarget(span);
            expect(result).toBe(innerScope);
            expect(result?.getAttribute('data-webu-field-scope')).toBe('items.0');
        });

        it('returns null for element with no editable markers', () => {
            const doc = document.implementation.createHTMLDocument('');
            const div = doc.createElement('div');
            div.textContent = 'plain';
            doc.body.appendChild(div);

            expect(resolveComponentInspectableTarget(div)).toBeNull();
        });

        it('prefers data-webu-field-url for link targets', () => {
            const doc = document.implementation.createHTMLDocument('');
            const a = doc.createElement('a');
            a.setAttribute('data-webu-field-url', 'ctaLink');
            a.setAttribute('href', '/shop');
            a.textContent = 'Shop';
            doc.body.appendChild(a);

            expect(resolveComponentInspectableTarget(a)).toBe(a);
            expect(a.getAttribute('data-webu-field-url')).toBe('ctaLink');
        });
    });

    describe('resolvePreviewTargetFromElement', () => {
        it('returns canonical section target id for blank section area', () => {
            const doc = document.implementation.createHTMLDocument('');
            const section = doc.createElement('section');
            section.setAttribute('data-webu-section', 'webu_general_hero_01');
            section.setAttribute('data-webu-section-local-id', 'hero-1');
            const blank = doc.createElement('div');
            blank.textContent = 'Background';
            section.appendChild(blank);
            doc.body.appendChild(section);

            const resolution = resolvePreviewTargetFromElement(blank);
            expect(resolution.status).toBe('resolved');
            expect(resolution.target?.targetId).toBe('hero-1::section');
            expect(resolution.target?.kind).toBe('section');
        });
    });

    describe('resolveElementAtPoint', () => {
        let iframe: HTMLIFrameElement;
        let iframeDoc: Document;
        let elementsFromPointMock: (x: number, y: number) => Element[];

        beforeEach(() => {
            iframe = document.createElement('iframe');
            document.body.appendChild(iframe);
            iframeDoc = document.implementation.createHTMLDocument('');
            elementsFromPointMock = () => [];
            Object.defineProperty(iframe, 'contentDocument', { value: iframeDoc, configurable: true });
            Object.defineProperty(iframe, 'contentWindow', {
                value: { document: iframeDoc },
                configurable: true,
            });
            Object.defineProperty(iframeDoc, 'elementFromPoint', {
                value: () => null,
                configurable: true,
            });
            Object.defineProperty(iframeDoc, 'elementsFromPoint', {
                value: (x: number, y: number) => elementsFromPointMock(x, y),
                configurable: true,
            });
            iframe.getBoundingClientRect = () => ({
                left: 0,
                top: 0,
                width: 800,
                height: 600,
                right: 800,
                bottom: 600,
                x: 0,
                y: 0,
                toJSON: () => ({}),
            });
        });

        it('selects title over section when both at same point', () => {
            const section = iframeDoc.createElement('section');
            section.setAttribute('data-webu-section', 'hero');
            section.setAttribute('data-webu-section-local-id', 'hero-1');
            const title = iframeDoc.createElement('h1');
            title.setAttribute('data-webu-field', 'title');
            title.textContent = 'Launch faster';
            section.appendChild(title);
            iframeDoc.body.appendChild(section);

            elementsFromPointMock = () => [title, section];

            const result = resolveElementAtPoint(iframe, 1, 100, 100);
            expect(result).toBe(title);
            expect(result?.getAttribute('data-webu-field')).toBe('title');
        });

        it('selects image over card when both at same point', () => {
            const section = iframeDoc.createElement('section');
            section.setAttribute('data-webu-section', 'cards');
            const card = iframeDoc.createElement('article');
            card.setAttribute('data-webu-field-scope', 'items.0');
            const img = iframeDoc.createElement('img');
            img.setAttribute('data-webu-field', 'image');
            img.setAttribute('src', '/card.jpg');
            card.appendChild(img);
            section.appendChild(card);
            iframeDoc.body.appendChild(section);

            elementsFromPointMock = () => [img, card, section];

            const result = resolveElementAtPoint(iframe, 1, 150, 150);
            expect(result).toBe(img);
            expect(result?.getAttribute('data-webu-field')).toBe('image');
        });

        it('selects button over section when both at same point', () => {
            const section = iframeDoc.createElement('section');
            section.setAttribute('data-webu-section', 'hero');
            const btn = iframeDoc.createElement('a');
            btn.setAttribute('data-webu-field', 'ctaLink');
            btn.textContent = 'Shop now';
            section.appendChild(btn);
            iframeDoc.body.appendChild(section);

            elementsFromPointMock = () => [btn, section];

            const result = resolveElementAtPoint(iframe, 1, 200, 200);
            expect(result).toBe(btn);
            expect(result?.getAttribute('data-webu-field')).toBe('ctaLink');
        });

        it('selects repeater item scope when no leaf field at point', () => {
            const section = iframeDoc.createElement('section');
            section.setAttribute('data-webu-section', 'menu');
            const item = iframeDoc.createElement('a');
            item.setAttribute('data-webu-field-scope', 'menu_items.0');
            item.textContent = 'Shop';
            section.appendChild(item);
            iframeDoc.body.appendChild(section);

            elementsFromPointMock = () => [item, section];

            const result = resolveElementAtPoint(iframe, 1, 250, 250);
            expect(result).toBe(item);
            expect(result?.getAttribute('data-webu-field-scope')).toBe('menu_items.0');
        });

        it('selects smaller/more specific target when overlapping', () => {
            const section = iframeDoc.createElement('section');
            section.setAttribute('data-webu-section', 'hero');
            const wrapper = iframeDoc.createElement('div');
            wrapper.setAttribute('data-webu-field-scope', 'items');
            const title = iframeDoc.createElement('h2');
            title.setAttribute('data-webu-field', 'items.0.title');
            title.textContent = 'Starter';
            wrapper.appendChild(title);
            section.appendChild(wrapper);
            iframeDoc.body.appendChild(section);

            elementsFromPointMock = () => [title, wrapper, section];

            const result = resolveElementAtPoint(iframe, 1, 300, 300);
            expect(result).toBe(title);
            expect(result?.getAttribute('data-webu-field')).toBe('items.0.title');
        });
    });

    describe('resolveSectionOnlyFallbackTarget', () => {
        let iframe: HTMLIFrameElement;
        let iframeDoc: Document;
        let elementsFromPointMock: (x: number, y: number) => Element[];

        beforeEach(() => {
            iframe = document.createElement('iframe');
            document.body.appendChild(iframe);
            iframeDoc = document.implementation.createHTMLDocument('');
            elementsFromPointMock = () => [];
            Object.defineProperty(iframe, 'contentDocument', { value: iframeDoc, configurable: true });
            Object.defineProperty(iframe, 'contentWindow', {
                value: { document: iframeDoc },
                configurable: true,
            });
            Object.defineProperty(iframeDoc, 'elementFromPoint', {
                value: () => null,
                configurable: true,
            });
            Object.defineProperty(iframeDoc, 'elementsFromPoint', {
                value: (x: number, y: number) => elementsFromPointMock(x, y),
                configurable: true,
            });
            iframe.getBoundingClientRect = () => ({
                left: 0,
                top: 0,
                width: 800,
                height: 600,
                right: 800,
                bottom: 600,
                x: 0,
                y: 0,
                toJSON: () => ({}),
            });
        });

        it('returns null when a field target exists at the point (no section fallback)', () => {
            const section = iframeDoc.createElement('section');
            section.setAttribute('data-webu-section', 'hero');
            section.setAttribute('data-webu-section-local-id', 'hero-1');
            const title = iframeDoc.createElement('h1');
            title.setAttribute('data-webu-field', 'title');
            title.textContent = 'Title';
            section.appendChild(title);
            iframeDoc.body.appendChild(section);

            elementsFromPointMock = () => [title, section];

            const result = resolveSectionOnlyFallbackTarget(iframe, 1, {
                target: title,
                clientX: 100,
                clientY: 100,
            });
            expect(result).toBeNull();
        });

        it('returns section when no editable target at point and section has no component targets', () => {
            const section = iframeDoc.createElement('section');
            section.setAttribute('data-webu-section', 'hero');
            section.setAttribute('data-webu-section-local-id', 'hero-1');
            const div = iframeDoc.createElement('div');
            div.textContent = 'plain text';
            section.appendChild(div);
            iframeDoc.body.appendChild(section);

            elementsFromPointMock = () => [div, section];

            const result = resolveSectionOnlyFallbackTarget(iframe, 1, {
                target: div,
                clientX: 100,
                clientY: 100,
            });
            expect(result).toBe(section);
        });

        it('returns section when blank area is clicked inside a section that has editable child targets', () => {
            const section = iframeDoc.createElement('section');
            section.setAttribute('data-webu-section', 'hero');
            section.setAttribute('data-webu-section-local-id', 'section-0');

            const blankArea = iframeDoc.createElement('div');
            blankArea.textContent = 'Hero background';

            const title = iframeDoc.createElement('h1');
            title.setAttribute('data-webu-field', 'title');
            title.textContent = 'Launch faster';

            section.appendChild(blankArea);
            section.appendChild(title);
            iframeDoc.body.appendChild(section);

            elementsFromPointMock = () => [blankArea, section];

            const result = resolveSectionOnlyFallbackTarget(iframe, 1, {
                target: blankArea,
                clientX: 120,
                clientY: 120,
            });

            expect(result).toBe(section);
        });
    });

    describe('resolvePreviewTargetAtPoint', () => {
        let iframe: HTMLIFrameElement;
        let iframeDoc: Document;
        let elementsFromPointMock: (x: number, y: number) => Element[];

        beforeEach(() => {
            iframe = document.createElement('iframe');
            document.body.appendChild(iframe);
            iframeDoc = document.implementation.createHTMLDocument('');
            elementsFromPointMock = () => [];
            Object.defineProperty(iframe, 'contentDocument', { value: iframeDoc, configurable: true });
            Object.defineProperty(iframe, 'contentWindow', {
                value: { document: iframeDoc },
                configurable: true,
            });
            Object.defineProperty(iframeDoc, 'elementFromPoint', {
                value: () => null,
                configurable: true,
            });
            Object.defineProperty(iframeDoc, 'elementsFromPoint', {
                value: (x: number, y: number) => elementsFromPointMock(x, y),
                configurable: true,
            });
            iframe.getBoundingClientRect = () => ({
                left: 0,
                top: 0,
                width: 800,
                height: 600,
                right: 800,
                bottom: 600,
                x: 0,
                y: 0,
                toJSON: () => ({}),
            });
        });

        it('returns mapped field target with stable target id', () => {
            const section = iframeDoc.createElement('section');
            section.setAttribute('data-webu-section', 'webu_general_hero_01');
            section.setAttribute('data-webu-section-local-id', 'hero-1');
            const title = iframeDoc.createElement('h1');
            title.setAttribute('data-webu-field', 'title');
            title.textContent = 'Launch faster';
            section.appendChild(title);
            iframeDoc.body.appendChild(section);

            elementsFromPointMock = () => [title, section];

            const resolution = resolvePreviewTargetAtPoint(iframe, 1, {
                target: title,
                clientX: 100,
                clientY: 100,
            });

            expect(resolution.status).toBe('resolved');
            expect(resolution.reason).toBe('mapped-element');
            expect(resolution.target?.targetId).toBe('hero-1::title');
            expect(resolution.target?.parameterPath).toBe('title');
        });

        it('falls back to section target for blank area clicks inside a section', () => {
            const section = iframeDoc.createElement('section');
            section.setAttribute('data-webu-section', 'webu_general_hero_01');
            section.setAttribute('data-webu-section-local-id', 'hero-1');
            const blankArea = iframeDoc.createElement('div');
            blankArea.textContent = 'Background';
            const title = iframeDoc.createElement('h1');
            title.setAttribute('data-webu-field', 'title');
            title.textContent = 'Launch faster';
            section.appendChild(blankArea);
            section.appendChild(title);
            iframeDoc.body.appendChild(section);

            elementsFromPointMock = () => [blankArea, section];

            const resolution = resolvePreviewTargetAtPoint(iframe, 1, {
                target: blankArea,
                clientX: 120,
                clientY: 120,
            });

            expect(resolution.status).toBe('resolved');
            expect(resolution.reason).toBe('section-fallback');
            expect(resolution.target?.targetId).toBe('hero-1::section');
            expect(resolution.target?.kind).toBe('section');
        });

        it('returns root canvas fallback only when the page has a single top-level section', () => {
            const section = iframeDoc.createElement('section');
            section.setAttribute('data-webu-section', 'webu_general_hero_01');
            section.setAttribute('data-webu-section-local-id', 'hero-1');
            iframeDoc.body.appendChild(section);

            elementsFromPointMock = () => [];
            Object.defineProperty(iframeDoc, 'elementFromPoint', {
                configurable: true,
                value: () => iframeDoc.body,
            });

            const resolution = resolvePreviewTargetAtPoint(iframe, 1, {
                target: iframeDoc.body,
                clientX: 10,
                clientY: 10,
            });

            expect(resolution.status).toBe('resolved');
            expect(resolution.reason).toBe('root-canvas-fallback');
            expect(resolution.target?.targetId).toBe('hero-1::section');
        });

        it('flags editable DOM nodes that have no backing mapped node', () => {
            const section = iframeDoc.createElement('section');
            section.setAttribute('data-webu-section', 'webu_general_hero_01');
            section.setAttribute('data-webu-section-local-id', 'hero-1');
            const orphan = iframeDoc.createElement('h1');
            orphan.setAttribute('data-webu-field', 'ghostTitle');
            orphan.textContent = 'Orphan';
            section.appendChild(orphan);
            iframeDoc.body.appendChild(section);

            elementsFromPointMock = () => [orphan, section];

            const resolution = resolvePreviewTargetAtPoint(iframe, 1, {
                target: orphan,
                clientX: 50,
                clientY: 50,
            });

            expect(resolution.status).toBe('missing-backing-node');
            expect(resolution.target).toBeNull();
        });
    });
});
