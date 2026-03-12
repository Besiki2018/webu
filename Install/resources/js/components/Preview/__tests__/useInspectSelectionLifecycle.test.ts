/**
 * Tests for useInspectSelectionLifecycle and buildElementMentionFromTarget.
 */
import { describe, expect, it } from 'vitest';
import { buildElementMentionFromTarget } from '../useInspectSelectionLifecycle';

describe('buildElementMentionFromTarget', () => {
    it('builds mention from section element', () => {
        const section = document.createElement('section');
        section.setAttribute('data-webu-section', 'webu_general_hero_01');
        section.setAttribute('data-webu-section-local-id', 'hero-1');
        section.textContent = 'Launch faster';

        const mention = buildElementMentionFromTarget(section);
        expect(mention).not.toBeNull();
        expect(mention?.sectionKey).toBe('webu_general_hero_01');
        expect(mention?.sectionLocalId).toBe('hero-1');
        expect(mention?.textPreview).toContain('Launch');
        expect(mention?.parameterName).toBeUndefined();
        expect(mention?.targetId).toBe('hero-1::section');
        expect(mention?.id).toBe('hero-1::section');
    });

    it('builds mention from field element with parameter path', () => {
        const section = document.createElement('section');
        section.setAttribute('data-webu-section', 'webu_general_hero_01');
        section.setAttribute('data-webu-section-local-id', 'hero-1');

        const titleEl = document.createElement('h1');
        titleEl.setAttribute('data-webu-field', 'title');
        titleEl.textContent = 'Hero title';
        section.appendChild(titleEl);

        const mention = buildElementMentionFromTarget(titleEl);
        expect(mention).not.toBeNull();
        expect(mention?.parameterName).toBe('title');
        expect(mention?.elementId).toBeTruthy();
        expect(mention?.elementId).toContain('title');
        expect(mention?.targetId).toBe('hero-1::title');
        expect(mention?.id).toBe('hero-1::title');
    });

    it('returns null for element without section', () => {
        const div = document.createElement('div');
        div.textContent = 'orphan';
        const mention = buildElementMentionFromTarget(div);
        expect(mention).toBeNull();
    });
});
