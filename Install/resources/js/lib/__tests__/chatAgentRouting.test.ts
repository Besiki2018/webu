import { describe, expect, it } from 'vitest';
import { shouldPreferProjectEdit } from '../chatAgentRouting';

describe('chatAgentRouting', () => {
    it('prefers project file edit only for explicit code and workspace requests', () => {
        expect(shouldPreferProjectEdit('Make changes across the whole project codebase')).toBe(true);
        expect(shouldPreferProjectEdit('შედი ვორკსპეისში და component.tsx ფაილი შეცვალე')).toBe(true);
    });

    it('keeps broad builder and website requests on the unified builder agent', () => {
        expect(shouldPreferProjectEdit('AI მთლიან პროექტში იხედებოდეს და ყველა კომპონენტს ხედავდეს')).toBe(false);
        expect(shouldPreferProjectEdit('შექმენი საიტი ონლაინ მაღაზიისთვის')).toBe(false);
    });

    it('prefers full-project agent in code view even without explicit keywords', () => {
        expect(shouldPreferProjectEdit('შეცვალე ეს ლეიაუთი', { viewMode: 'code' })).toBe(true);
    });

    it('keeps targeted element edits on the builder path', () => {
        expect(shouldPreferProjectEdit('ჰედერის ტექსტი შეცვალე', { hasSelectedElement: true })).toBe(false);
        expect(shouldPreferProjectEdit('Featured products ეს წაშალე', { hasSelectedElement: true, viewMode: 'inspect' })).toBe(false);
    });
});
