import { describe, expect, it } from 'vitest';

import {
    appendAiNodeTag,
    buildAiNodeId,
    buildAiNodeTag,
    extractAiNodeIds,
    stripAiNodeTags,
} from '@/builder/runtime/elementHover';

describe('elementHover targeting helpers', () => {
    it('builds stable ai node ids from section scope and field path', () => {
        expect(buildAiNodeId('hero-1', 'title', 'webu_general_hero_01')).toBe('hero-1.title');
        expect(buildAiNodeId('hero-1', null, 'webu_general_hero_01')).toBe('hero-1');
        expect(buildAiNodeId(null, 'title', 'webu_general_hero_01')).toBe('webu_general_hero_01.title');
    });

    it('appends node tags without duplicating existing selections', () => {
        const first = appendAiNodeTag('', 'hero-1.title');
        const second = appendAiNodeTag(first, 'hero-1.title');
        const third = appendAiNodeTag(second, 'hero-1.subtitle');

        expect(first).toBe('@node(hero-1.title)\n');
        expect(second).toBe(first);
        expect(third).toContain('@node(hero-1.title)');
        expect(third).toContain('@node(hero-1.subtitle)');
    });

    it('extracts and strips node tags from prompt content', () => {
        const prompt = `${buildAiNodeTag('hero-1.title')}\n${buildAiNodeTag('hero-1.subtitle')}\nRewrite these texts`;

        expect(extractAiNodeIds(prompt)).toEqual(['hero-1.title', 'hero-1.subtitle']);
        expect(stripAiNodeTags(prompt)).toBe('Rewrite these texts');
    });
});
