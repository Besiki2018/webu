import { getComponentSchema, type BuilderFieldDefinition } from './componentRegistry';
import type { StockImageOrientation } from '@/builder/assets/stockImageTypes';
import { resolveCmsMediaFieldOwner } from '@/builder/cmsIntegration/mediaOwnershipRouting';

export type ComponentImageRole =
    | 'logo'
    | 'hero'
    | 'background'
    | 'card'
    | 'gallery'
    | 'avatar'
    | 'cta'
    | 'content';

export interface ComponentImagePropSlot {
    path: string;
    role: ComponentImageRole;
    orientation: StockImageOrientation;
    owner?: 'cms' | 'builder' | 'code';
    label?: string;
}

export interface ComponentImageSlotEntry {
    component: string;
    imageProps: ComponentImagePropSlot[];
}

export const COMPONENT_IMAGE_SLOTS: Record<string, ComponentImageSlotEntry> = Object.freeze({
    webu_header_01: {
        component: 'webu_header_01',
        imageProps: [
            { path: 'logo_url', role: 'logo', orientation: 'square', label: 'Logo image' },
        ],
    },
    webu_general_hero_01: {
        component: 'webu_general_hero_01',
        imageProps: [
            { path: 'image', role: 'hero', orientation: 'landscape', label: 'Hero image' },
            { path: 'overlayImageUrl', role: 'hero', orientation: 'landscape', label: 'Overlay image' },
            { path: 'backgroundImage', role: 'background', orientation: 'landscape', label: 'Background image' },
            { path: 'statAvatars.*.url', role: 'avatar', orientation: 'portrait', label: 'Stat avatar' },
        ],
    },
    webu_general_cards_01: {
        component: 'webu_general_cards_01',
        imageProps: [
            { path: 'items.*.image', role: 'card', orientation: 'landscape', label: 'Card image' },
        ],
    },
    webu_general_grid_01: {
        component: 'webu_general_grid_01',
        imageProps: [
            { path: 'items.*.image', role: 'gallery', orientation: 'square', label: 'Gallery image' },
        ],
    },
    webu_general_cta_01: {
        component: 'webu_general_cta_01',
        imageProps: [
            { path: 'backgroundImage', role: 'cta', orientation: 'landscape', label: 'CTA background' },
        ],
    },
    webu_general_image_01: {
        component: 'webu_general_image_01',
        imageProps: [
            { path: 'image_url', role: 'content', orientation: 'landscape', label: 'Image' },
        ],
    },
    webu_general_card_01: {
        component: 'webu_general_card_01',
        imageProps: [
            { path: 'image_url', role: 'card', orientation: 'landscape', label: 'Card image' },
        ],
    },
    webu_general_testimonials_01: {
        component: 'webu_general_testimonials_01',
        imageProps: [
            { path: 'items.*.avatar', role: 'avatar', orientation: 'portrait', label: 'Testimonial avatar' },
            { path: 'items.*.image_url', role: 'avatar', orientation: 'portrait', label: 'Testimonial avatar' },
        ],
    },
});

function normalizeComponentKey(componentKey: string | null | undefined): string {
    return typeof componentKey === 'string' ? componentKey.trim() : '';
}

function normalizePath(path: string | string[] | null | undefined): string {
    if (Array.isArray(path)) {
        return path
            .map((segment) => String(segment).trim())
            .filter(Boolean)
            .join('.');
    }

    if (typeof path !== 'string') {
        return '';
    }

    return path
        .replace(/\[(\d+)\]/g, '.$1')
        .split('.')
        .map((segment) => segment.trim())
        .filter(Boolean)
        .join('.');
}

function pathMatches(pattern: string, path: string): boolean {
    const normalizedPattern = normalizePath(pattern);
    const normalizedPath = normalizePath(path);

    if (normalizedPattern === '' || normalizedPath === '') {
        return false;
    }

    const patternSegments = normalizedPattern.split('.');
    const pathSegments = normalizedPath.split('.');

    if (patternSegments.length !== pathSegments.length) {
        return false;
    }

    return patternSegments.every((segment, index) => segment === '*' || segment === pathSegments[index]);
}

function inferRoleFromProbe(probe: string): ComponentImageRole {
    if (/(^|\.)(logo|logo_url|logoimageurl)($|\.)/.test(probe)) {
        return 'logo';
    }

    if (/(^|\.)(avatar|portrait|profile|author|staff|team|testimonial)($|\.)/.test(probe)) {
        return 'avatar';
    }

    if (/(^|\.)(backgroundimage|background_image|cover)($|\.)/.test(probe)) {
        return probe.includes('cta') ? 'cta' : 'background';
    }

    if (/(^|\.)(gallery|portfolio|grid)($|\.)/.test(probe)) {
        return 'gallery';
    }

    if (/(^|\.)(card|feature|service)($|\.)/.test(probe)) {
        return 'card';
    }

    if (/(^|\.)(hero|overlay)($|\.)/.test(probe)) {
        return 'hero';
    }

    return 'content';
}

function orientationForRole(role: ComponentImageRole): StockImageOrientation {
    switch (role) {
        case 'logo':
            return 'square';
        case 'avatar':
            return 'portrait';
        case 'gallery':
            return 'square';
        default:
            return 'landscape';
    }
}

function fallbackSlotForPath(componentKey: string, path: string, label?: string): ComponentImagePropSlot {
    const probe = `${componentKey}.${path}.${label ?? ''}`.toLowerCase().replace(/\s+/g, '');
    const role = inferRoleFromProbe(probe);
    const owner = resolveCmsMediaFieldOwner(componentKey, path).owner;

    return {
        path,
        role,
        orientation: orientationForRole(role),
        owner,
        label,
    };
}

function collectDerivedImageSlots(fields: BuilderFieldDefinition[], componentKey: string, prefix: string[] = []): ComponentImagePropSlot[] {
    const slots: ComponentImagePropSlot[] = [];

    fields.forEach((field) => {
        const fieldSegments = normalizePath(field.path).split('.').filter(Boolean);
        const absoluteSegments = [...prefix, ...fieldSegments];
        const absolutePath = absoluteSegments.join('.');

        if (field.type === 'image') {
            slots.push(fallbackSlotForPath(componentKey, absolutePath, field.label));
        }

        if ((field.type === 'repeater' || field.type === 'menu' || field.type === 'button-group') && Array.isArray(field.itemFields)) {
            slots.push(...collectDerivedImageSlots(field.itemFields, componentKey, [...absoluteSegments, '*']));
        }
    });

    return slots;
}

function dedupeSlots(slots: ComponentImagePropSlot[]): ComponentImagePropSlot[] {
    const seen = new Set<string>();

    return slots.filter((slot) => {
        const key = `${slot.path}:${slot.role}:${slot.orientation}`;
        if (seen.has(key)) {
            return false;
        }

        seen.add(key);

        return true;
    });
}

function withResolvedOwner(componentKey: string, slot: ComponentImagePropSlot): ComponentImagePropSlot {
    return {
        ...slot,
        owner: slot.owner ?? resolveCmsMediaFieldOwner(componentKey, slot.path).owner,
    };
}

export function getComponentImageSlotEntry(componentKey: string | null | undefined): ComponentImageSlotEntry | null {
    const normalizedKey = normalizeComponentKey(componentKey);
    if (normalizedKey === '') {
        return null;
    }

    const explicit = COMPONENT_IMAGE_SLOTS[normalizedKey];
    const schema = getComponentSchema(normalizedKey);
    const derived = schema ? collectDerivedImageSlots(schema.fields, normalizedKey) : [];

    if (!explicit && derived.length === 0) {
        return null;
    }

    return {
        component: normalizedKey,
        imageProps: dedupeSlots([
            ...(explicit?.imageProps ?? []),
            ...derived.filter((slot) => !explicit?.imageProps.some((candidate) => candidate.path === slot.path)),
        ]).map((slot) => withResolvedOwner(normalizedKey, slot)),
    };
}

export function getComponentImageSlots(componentKey: string | null | undefined): ComponentImagePropSlot[] {
    return getComponentImageSlotEntry(componentKey)?.imageProps ?? [];
}

export function resolveComponentImageSlot(
    componentKey: string | null | undefined,
    path: string | string[] | null | undefined,
): ComponentImagePropSlot | null {
    const normalizedPath = normalizePath(path);
    if (normalizedPath === '') {
        return null;
    }

    return getComponentImageSlots(componentKey).find((slot) => pathMatches(slot.path, normalizedPath)) ?? null;
}
