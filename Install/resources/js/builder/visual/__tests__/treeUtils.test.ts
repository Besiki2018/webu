import { describe, it, expect } from 'vitest';
import {
    insertSection,
    removeSection,
    findSection,
    getSectionById,
    moveSection,
    updateSectionProps,
    type BuilderSection,
} from '../treeUtils';

function section(localId: string, type: string, propsText = '{}'): BuilderSection {
    return { localId, type, propsText, propsError: null };
}

describe('treeUtils', () => {
    it('getSectionById returns section by localId', () => {
        const sections = [section('a', 'hero'), section('b', 'section')];
        expect(getSectionById(sections, 'b')).toEqual(sections[1]);
        expect(getSectionById(sections, 'c')).toBeUndefined();
    });

    it('moveSection moves section before target', () => {
        const sections = [section('a', 'hero'), section('b', 'cta'), section('c', 'footer')];
        const next = moveSection(sections, 'c', 'a', 'before');
        expect(next.map((s) => s.localId)).toEqual(['c', 'a', 'b']);
    });

    it('moveSection moves section after target', () => {
        const sections = [section('a', 'hero'), section('b', 'cta'), section('c', 'footer')];
        const next = moveSection(sections, 'a', 'c', 'after');
        expect(next.map((s) => s.localId)).toEqual(['b', 'c', 'a']);
    });

    it('moveSection no-op when section not found', () => {
        const sections = [section('a', 'hero')];
        expect(moveSection(sections, 'x', 'a', 'before')).toEqual(sections);
    });

    it('removeSection returns new array without section', () => {
        const sections = [section('a', 'hero'), section('b', 'cta')];
        const next = removeSection(sections, 'a');
        expect(next).toHaveLength(1);
        expect(findSection(next, 'a')).toBeUndefined();
        expect(findSection(next, 'b')).toBeDefined();
    });

    it('moveSection to end (after last) works like drag to canvas drop', () => {
        const sections = [section('a', 'hero'), section('b', 'cta'), section('c', 'footer')];
        const next = moveSection(sections, 'a', 'c', 'after');
        expect(next.map((s) => s.localId)).toEqual(['b', 'c', 'a']);
    });

    it('updateSectionProps updates props and clears propsError', () => {
        const sections = [
            { ...section('a', 'hero', '{"title":"Hi"}'), propsError: 'parse error' as string | null },
        ];
        const next = updateSectionProps(sections, 'a', (p) => ({ ...p, title: 'Bye' }));
        expect(next).toHaveLength(1);
        expect(JSON.parse(next[0].propsText).title).toBe('Bye');
        expect(next[0].propsError).toBeNull();
    });
});
