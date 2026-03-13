import { describe, it, expect, beforeEach, vi } from 'vitest';
import {
  annotateEditableElements,
  buildDOMMap,
  buildDOMMapCached,
  invalidateDOMMapCache,
  getDOMMapDebugInfo,
  getDOMMapDebugOverlays,
  getElementAtPoint,
  getSectionAtPoint,
} from '../domMapper';
import { buildElementId } from '../../componentParameterMetadata';

/**
 * Create a minimal document with one section and fields for testing.
 */
function createTestDocument(): Document {
  const doc = document.implementation.createHTMLDocument('');
  const body = doc.body;

  const section = doc.createElement('section');
  section.setAttribute('data-webu-section', 'webu_general_hero_01');
  section.setAttribute('data-webu-section-local-id', 'hero-1');

  const titleEl = doc.createElement('h1');
  titleEl.setAttribute('data-webu-field', 'title');
  titleEl.textContent = 'Welcome';
  section.appendChild(titleEl);

  const subtitleEl = doc.createElement('p');
  subtitleEl.setAttribute('data-webu-field', 'subtitle');
  subtitleEl.textContent = 'Tagline';
  section.appendChild(subtitleEl);

  body.appendChild(section);
  return doc;
}

describe('domMapper', () => {
  beforeEach(() => {
    invalidateDOMMapCache();
  });

  describe('buildDOMMap', () => {
    it('builds map from document with data-webu-section and data-webu-field', () => {
      const doc = createTestDocument();
      const map = buildDOMMap(doc);

      expect(map.sections).toHaveLength(1);
      expect(map.sections[0]?.sectionKey).toBe('webu_general_hero_01');
      expect(map.sections[0]?.sectionLocalId).toBe('hero-1');
      expect(map.sections[0]?.shortName).toBe('HeroSection');
      expect(map.sections[0]?.elements.length).toBeGreaterThanOrEqual(2);

      const elementIds = map.sections[0]!.elements.map((e) => e.elementId);
      expect(elementIds).toContain('HeroSection.title');
      expect(elementIds).toContain('HeroSection.subtitle');

      expect(map.elementsById.get('HeroSection.title')).toBeDefined();
      expect(map.elementsById.get('HeroSection.subtitle')).toBeDefined();
      expect(map.builtAt).toBeGreaterThan(0);
    });

    it('returns empty map for document without sections', () => {
      const doc = document.implementation.createHTMLDocument('');
      const map = buildDOMMap(doc);
      expect(map.sections).toHaveLength(0);
      expect(map.elementsById.size).toBe(0);
    });

    it('maps inferred nested text fields and link paths from live props', () => {
      const doc = document.implementation.createHTMLDocument('');
      const section = doc.createElement('section');
      section.setAttribute('data-webu-section', 'webu_general_hero_01');
      section.setAttribute('data-webu-section-local-id', 'hero-annotated');

      const titleEl = doc.createElement('h1');
      titleEl.textContent = 'Restaurant title';
      section.appendChild(titleEl);

      const subtitleEl = doc.createElement('p');
      subtitleEl.textContent = 'Fresh food daily';
      section.appendChild(subtitleEl);

      const ctaEl = doc.createElement('a');
      ctaEl.textContent = 'Book a table';
      ctaEl.setAttribute('href', '/booking');
      section.appendChild(ctaEl);

      doc.body.appendChild(section);

      const annotated = annotateEditableElements(doc, [{
        localId: 'hero-annotated',
        sectionKey: 'webu_general_hero_01',
        props: {
          title: 'Restaurant title',
          subtitle: 'Fresh food daily',
          primary_cta: {
            label: 'Book a table',
            link: '/booking',
          },
        },
      }]);

      expect(annotated).toBeGreaterThan(0);
      expect(titleEl.getAttribute('data-webu-field')).toBe('title');
      expect(subtitleEl.getAttribute('data-webu-field')).toBe('subtitle');
      expect(ctaEl.getAttribute('data-webu-field')).toBe('primary_cta.label');
      expect(ctaEl.getAttribute('data-webu-field-url')).toBe('primary_cta.link');

      const map = buildDOMMap(doc);
      expect(map.elementsById.get('HeroSection.title')).toBeDefined();
      expect(map.elementsById.get('HeroSection.subtitle')).toBeDefined();
      expect(map.elementsById.get('HeroSection.primary_cta.label')).toBeDefined();
      expect(map.elementsById.get('HeroSection.primary_cta.link')).toBeDefined();
    });

    it('does not let hidden exact shims block visible inferred field bindings', () => {
      const doc = document.implementation.createHTMLDocument('');
      const section = doc.createElement('section');
      section.setAttribute('data-webu-section', 'webu_general_hero_01');
      section.setAttribute('data-webu-section-local-id', 'hero-hidden-shim');

      const hiddenTitle = doc.createElement('h2');
      hiddenTitle.setAttribute('data-webu-field', 'title');
      hiddenTitle.textContent = 'Legacy title';
      hiddenTitle.getBoundingClientRect = () => ({ left: 0, top: 0, width: 0, height: 0, right: 0, bottom: 0, x: 0, y: 0, toJSON: () => ({}) });

      const visibleTitle = doc.createElement('h2');
      visibleTitle.textContent = 'Runtime title';
      visibleTitle.getBoundingClientRect = () => ({ left: 0, top: 0, width: 320, height: 48, right: 320, bottom: 48, x: 0, y: 0, toJSON: () => ({}) });

      section.append(hiddenTitle, visibleTitle);
      doc.body.appendChild(section);

      annotateEditableElements(doc, [{
        localId: 'hero-hidden-shim',
        sectionKey: 'webu_general_hero_01',
        props: {
          title: 'Runtime title',
        },
      }]);

      expect(hiddenTitle.getAttribute('data-webu-field')).toBe('title');
      expect(visibleTitle.getAttribute('data-webu-field')).toBe('title');
    });

    it('annotates component scopes for compound and repeated child targets', () => {
      const doc = document.implementation.createHTMLDocument('');
      const section = doc.createElement('section');
      section.setAttribute('data-webu-section', 'webu_general_cards_01');
      section.setAttribute('data-webu-section-local-id', 'cards-annotated');

      const card = doc.createElement('article');
      const titleEl = doc.createElement('h3');
      titleEl.textContent = 'Starter';
      card.appendChild(titleEl);

      const linkEl = doc.createElement('a');
      linkEl.textContent = 'Read more';
      linkEl.setAttribute('href', '/starter');
      card.appendChild(linkEl);

      section.appendChild(card);
      doc.body.appendChild(section);

      annotateEditableElements(doc, [{
        localId: 'cards-annotated',
        sectionKey: 'webu_general_cards_01',
        props: {
          items: [
            {
              title: 'Starter',
              link: {
                label: 'Read more',
                url: '/starter',
              },
            },
          ],
        },
      }]);

      expect(titleEl.getAttribute('data-webu-field')).toBe('items.0.title');
      expect(linkEl.getAttribute('data-webu-field')).toBe('items.0.link.label');
      expect(linkEl.getAttribute('data-webu-field-url')).toBe('items.0.link.url');

      const map = buildDOMMap(doc);
      expect(map.elementsById.get(buildElementId('webu_general_cards_01', 'items.0.title'))).toBeDefined();
      expect(map.elementsById.get(buildElementId('webu_general_cards_01', 'items.0.link.label'))).toBeDefined();
    });
  });

  describe('buildDOMMapCached and invalidateDOMMapCache', () => {
    it('returns same map on second call (cache hit)', () => {
      const doc = createTestDocument();
      const map1 = buildDOMMapCached(doc);
      const map2 = buildDOMMapCached(doc);
      expect(map1).toBe(map2);
      expect(map1.sections).toHaveLength(1);
    });

    it('returns fresh map after invalidateDOMMapCache', () => {
      const doc = createTestDocument();
      const map1 = buildDOMMapCached(doc);
      invalidateDOMMapCache();
      const map2 = buildDOMMapCached(doc);
      expect(map1).not.toBe(map2);
      expect(map2.sections).toHaveLength(1);
    });

    it('uses different cache key for different section order', () => {
      const doc1 = createTestDocument();
      const doc2 = document.implementation.createHTMLDocument('');
      const section1 = doc1.querySelector('[data-webu-section]')!.cloneNode(true) as HTMLElement;
      section1.setAttribute('data-webu-section-local-id', 'hero-2');
      doc2.body.appendChild(section1);
      const section2 = doc1.querySelector('[data-webu-section]')!.cloneNode(true) as HTMLElement;
      section2.setAttribute('data-webu-section-local-id', 'hero-1');
      doc2.body.insertBefore(section2, doc2.body.firstChild);

      const map1 = buildDOMMapCached(doc1);
      invalidateDOMMapCache();
      const map2 = buildDOMMapCached(doc2);
      expect(map1.sections[0]?.sectionLocalId).toBe('hero-1');
      expect(map2.sections[0]?.sectionLocalId).toBe('hero-1');
      expect(map2.sections[1]?.sectionLocalId).toBe('hero-2');
    });
  });

  describe('getDOMMapDebugInfo', () => {
    it('returns section shortName, localId, and elementIds', () => {
      const doc = createTestDocument();
      const map = buildDOMMap(doc);
      const info = getDOMMapDebugInfo(map);

      expect(info.sections).toHaveLength(1);
      expect(info.sections[0]?.shortName).toBe('HeroSection');
      expect(info.sections[0]?.localId).toBe('hero-1');
      expect(info.sections[0]?.elementIds).toContain('HeroSection.title');
    });
  });

  describe('getElementAtPoint', () => {
    it('returns MappedElement when point is inside a data-webu-field', () => {
      const doc = createTestDocument();
      const map = buildDOMMap(doc);
      const titleEl = doc.querySelector('[data-webu-field="title"]') as HTMLElement;
      expect(titleEl).toBeTruthy();

      const elementFromPoint = vi.fn(() => titleEl);
      Object.defineProperty(doc, 'elementFromPoint', { value: elementFromPoint, configurable: true });

      const el = getElementAtPoint(doc, 10, 10, map);
      expect(el).not.toBeNull();
      expect(el?.elementId).toBe('HeroSection.title');
      expect(el?.parameterName).toBe('title');
    });

    it('returns null when point is not inside a mapped field', () => {
      const doc = createTestDocument();
      const map = buildDOMMap(doc);
      const div = doc.createElement('div');
      doc.body.appendChild(div);

      const elementFromPoint = vi.fn(() => div);
      Object.defineProperty(doc, 'elementFromPoint', { value: elementFromPoint, configurable: true });

      const el = getElementAtPoint(doc, 0, 0, map);
      expect(el).toBeNull();
    });

    it('resolves url-only mapped elements from data-webu-field-url', () => {
      const doc = document.implementation.createHTMLDocument('');
      const section = doc.createElement('section');
      section.setAttribute('data-webu-section', 'webu_general_hero_01');
      section.setAttribute('data-webu-section-local-id', 'hero-link-only');

      const linkEl = doc.createElement('a');
      linkEl.setAttribute('href', '/menu');
      linkEl.setAttribute('data-webu-field-url', 'primary_cta.link');
      linkEl.textContent = 'Open';
      section.appendChild(linkEl);
      doc.body.appendChild(section);

      const map = buildDOMMap(doc);
      Object.defineProperty(doc, 'elementFromPoint', { value: vi.fn(() => linkEl), configurable: true });

      const el = getElementAtPoint(doc, 10, 10, map);
      expect(el?.elementId).toBe('HeroSection.primary_cta.link');
      expect(el?.parameterName).toBe('primary_cta.link');
    });

    it('prefers component scope over section fallback when a compound child component is clicked', () => {
      const doc = document.implementation.createHTMLDocument('');
      const section = doc.createElement('section');
      section.setAttribute('data-webu-section', 'webu_header_01');
      section.setAttribute('data-webu-section-local-id', 'header-1');

      const linkEl = doc.createElement('a');
      linkEl.setAttribute('href', '/start');
      const labelEl = doc.createElement('span');
      labelEl.textContent = 'Get started';
      linkEl.appendChild(labelEl);
      section.appendChild(linkEl);
      doc.body.appendChild(section);

      annotateEditableElements(doc, [{
        localId: 'header-1',
        sectionKey: 'webu_header_01',
        props: {
          ctaLink: {
            label: 'Get started',
            url: '/start',
          },
        },
      }]);

      const map = buildDOMMap(doc);
      Object.defineProperty(doc, 'elementsFromPoint', {
        value: vi.fn(() => [labelEl, linkEl, section]),
        configurable: true,
      });
      Object.defineProperty(doc, 'elementFromPoint', { value: vi.fn(() => labelEl), configurable: true });

      const el = getElementAtPoint(doc, 10, 10, map);
      expect(el?.parameterName).toBe('ctaLink');
      expect(el?.elementId).toBe('HeaderSection.ctaLink');
    });

    it('resolves repeated item containers instead of the whole section when clicking nested cards', () => {
      const doc = document.implementation.createHTMLDocument('');
      const section = doc.createElement('section');
      section.setAttribute('data-webu-section', 'webu_general_cards_01');
      section.setAttribute('data-webu-section-local-id', 'cards-1');

      const card = doc.createElement('article');
      const cardBody = doc.createElement('div');
      card.appendChild(cardBody);
      const titleEl = doc.createElement('h3');
      titleEl.textContent = 'Starter';
      card.appendChild(titleEl);
      section.appendChild(card);
      doc.body.appendChild(section);

      annotateEditableElements(doc, [{
        localId: 'cards-1',
        sectionKey: 'webu_general_cards_01',
        props: {
          items: [
            {
              title: 'Starter',
              link: {
                label: 'Read more',
                url: '/starter',
              },
            },
          ],
        },
      }]);

      const map = buildDOMMap(doc);
      Object.defineProperty(doc, 'elementsFromPoint', {
        value: vi.fn(() => [cardBody, card, section]),
        configurable: true,
      });
      Object.defineProperty(doc, 'elementFromPoint', { value: vi.fn(() => cardBody), configurable: true });

      const el = getElementAtPoint(doc, 15, 15, map);
      expect(el?.parameterName).toBe('items.0');
      expect(el?.elementId).toBe(buildElementId('webu_general_cards_01', 'items.0'));
    });
  });

  describe('getSectionAtPoint', () => {
    it('returns section element when point is inside section', () => {
      const doc = createTestDocument();
      const section = doc.querySelector('[data-webu-section]') as HTMLElement;
      const titleEl = doc.querySelector('[data-webu-field="title"]') as HTMLElement;

      const elementFromPoint = vi.fn(() => titleEl);
      Object.defineProperty(doc, 'elementFromPoint', { value: elementFromPoint, configurable: true });

      const result = getSectionAtPoint(doc, 10, 10);
      expect(result).toBe(section);
    });

    it('returns null when point is outside any section', () => {
      const doc = createTestDocument();
      const div = doc.createElement('div');
      doc.body.insertBefore(div, doc.body.firstChild);

      const elementFromPoint = vi.fn(() => div);
      Object.defineProperty(doc, 'elementFromPoint', { value: elementFromPoint, configurable: true });

      const result = getSectionAtPoint(doc, 0, 0);
      expect(result).toBeNull();
    });
  });

  describe('getDOMMapDebugOverlays', () => {
    it('returns overlay boxes for sections and elements', () => {
      const doc = createTestDocument();
      const sectionEl = doc.querySelector('[data-webu-section]') as HTMLElement;
      const titleEl = doc.querySelector('[data-webu-field="title"]') as HTMLElement;
      sectionEl.getBoundingClientRect = () => ({ left: 0, top: 0, width: 800, height: 200, right: 800, bottom: 200, x: 0, y: 0, toJSON: () => ({}) });
      titleEl.getBoundingClientRect = () => ({ left: 10, top: 10, width: 100, height: 30, right: 110, bottom: 40, x: 10, y: 10, toJSON: () => ({}) });

      const boxes = getDOMMapDebugOverlays(doc);

      expect(boxes.length).toBeGreaterThanOrEqual(2);
      const sectionBox = boxes.find((b) => b.kind === 'section');
      const elementBox = boxes.find((b) => b.kind === 'element' && b.elementId === 'HeroSection.title');
      expect(sectionBox).toBeDefined();
      expect(sectionBox?.label).toContain('HeroSection');
      expect(sectionBox?.localId).toBe('hero-1');
      expect(elementBox).toBeDefined();
      expect(elementBox?.elementId).toBe('HeroSection.title');
    });
  });
});
