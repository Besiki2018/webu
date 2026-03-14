import { describe, expect, it } from 'vitest';

import {
    getComponentImageSlots,
    resolveComponentImageSlot,
} from '@/builder/componentImageSlots';

describe('componentImageSlots', () => {
    it('maps header logo fields as image-capable slots', () => {
        expect(resolveComponentImageSlot('webu_header_01', 'logo_url')).toEqual(
            expect.objectContaining({
                path: 'logo_url',
                role: 'logo',
                orientation: 'square',
                owner: 'cms',
            }),
        );
    });

    it('maps hero content, background, and repeater avatar image slots', () => {
        expect(resolveComponentImageSlot('webu_general_hero_01', 'image')).toEqual(
            expect.objectContaining({
                role: 'hero',
                orientation: 'landscape',
                owner: 'cms',
            }),
        );
        expect(resolveComponentImageSlot('webu_general_hero_01', 'backgroundImage')).toEqual(
            expect.objectContaining({
                role: 'background',
                orientation: 'landscape',
                owner: 'builder',
            }),
        );
        expect(resolveComponentImageSlot('webu_general_hero_01', 'statAvatars.0.url')).toEqual(
            expect.objectContaining({
                role: 'avatar',
                orientation: 'portrait',
                owner: 'cms',
            }),
        );
    });

    it('matches wildcard repeater image slots for cards, gallery/grid, and avatars', () => {
        expect(resolveComponentImageSlot('webu_general_cards_01', 'items.2.image')).toEqual(
            expect.objectContaining({
                role: 'card',
                orientation: 'landscape',
            }),
        );
        expect(resolveComponentImageSlot('webu_general_grid_01', 'items.1.image')).toEqual(
            expect.objectContaining({
                role: 'gallery',
                orientation: 'square',
            }),
        );
        expect(resolveComponentImageSlot('webu_general_testimonials_01', 'items.0.avatar')).toEqual(
            expect.objectContaining({
                role: 'avatar',
                orientation: 'portrait',
            }),
        );
    });

    it('keeps schema-derived image slots available for simple image sections', () => {
        const slots = getComponentImageSlots('webu_general_image_01');

        expect(slots.map((slot) => slot.path)).toContain('image_url');
    });
});
