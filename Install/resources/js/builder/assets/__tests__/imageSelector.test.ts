import { describe, expect, it } from 'vitest';

import {
    buildStockImageImportContext,
    inferStockImageOrientation,
    inferStockImageQuery,
} from '@/builder/assets/imageSelector';

describe('imageSelector', () => {
    it('prefers landscape orientation for hero-like contexts', () => {
        expect(inferStockImageOrientation({
            fieldLabel: 'Hero Image',
            sectionType: 'webu_general_hero_01',
        })).toBe('landscape');
    });

    it('prefers portrait orientation for team/avatar contexts', () => {
        expect(inferStockImageOrientation({
            fieldLabel: 'Team Avatar',
            sectionType: 'team',
        })).toBe('portrait');
    });

    it('uses component image slot metadata for logo orientation', () => {
        expect(inferStockImageOrientation({
            fieldLabel: 'Logo image',
            componentKey: 'webu_header_01',
            fieldPath: 'logo_url',
        })).toBe('square');
    });

    it('builds contextual search queries for gallery fields', () => {
        expect(inferStockImageQuery({
            fieldLabel: 'Gallery Image',
            projectName: 'Veterinary Clinic',
        })).toBe('Veterinary Clinic lifestyle photography');
    });

    it('builds import context for visual builder usage', () => {
        expect(buildStockImageImportContext('project-1', {
            fieldLabel: 'Hero Image',
            fieldPath: 'image',
            componentKey: 'webu_general_hero_01',
            sectionLocalId: 'hero-1',
            pageSlug: 'home',
        })).toEqual({
            project_id: 'project-1',
            imported_by: 'visual_builder',
            section_local_id: 'hero-1',
            component_key: 'webu_general_hero_01',
            prop_path: 'image',
            page_slug: 'home',
            query: 'modern business',
        });
    });

    it('uses wildcard component image slots when building card image queries', () => {
        expect(inferStockImageQuery({
            fieldLabel: 'Cards 1 / Image',
            fieldPath: 'items.0.image',
            componentKey: 'webu_general_cards_01',
            projectName: 'Veterinary Clinic',
        })).toBe('Veterinary Clinic service photo');
    });
});
