/**
 * Tree operations for builder sections (flat list + optional nested in props).
 * Used for insert before/after/inside without hardcoding in UI.
 * Task 9: single source of truth is state tree; all add/delete/update/reorder mutate tree only.
 */

/** Recursive layout tree node (audit Task 9). Used for future full tree-driven canvas. */
export interface BuilderNode {
    id: string;
    type: string;
    props?: Record<string, unknown>;
    styles?: Record<string, unknown>;
    children?: BuilderNode[];
}

export interface BuilderSection {
    localId: string;
    type: string;
    props?: Record<string, unknown>;
    propsText: string;
    propsError: string | null;
    bindingMeta?: Record<string, unknown> | null;
}

export type DropPosition = 'before' | 'after' | 'inside';

function parseProps(propsText: string): Record<string, unknown> | null {
    try {
        const parsed: unknown = JSON.parse(propsText || '{}');
        return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? (parsed as Record<string, unknown>) : null;
    } catch {
        return null;
    }
}

function getSectionProps(section: BuilderSection): Record<string, unknown> | null {
    if (section.props && typeof section.props === 'object' && !Array.isArray(section.props)) {
        return section.props;
    }

    return parseProps(section.propsText);
}

/** Layout primitives that can contain child sections (short and full Webu keys). */
export const CONTAINER_SECTION_TYPES = new Set([
    'container',
    'grid',
    'section',
    'columns',
    'column',
    'wrapper',
    'webu_general_section_01',
    'webu_general_container_01',
    'webu_general_grid_01',
    'webu_general_columns_01',
]);

export function isContainerSection(section: BuilderSection): boolean {
    const type = (section.type || '').trim().toLowerCase();
    if (CONTAINER_SECTION_TYPES.has(type)) return true;
    if (type.includes('_section_') || type.includes('_container_') || type.includes('_grid_') || type.includes('_columns_')) return true;
    return false;
}

/**
 * Resolve insert index for top-level sections.
 * - before target → index of target
 * - after target → index of target + 1
 * - inside container → handled by adding to container's props.sections
 */
export function getInsertIndex(
    sections: BuilderSection[],
    targetSectionLocalId: string | null,
    position: DropPosition
): number {
    if (position === 'inside') {
        // Insert as first child of container; caller may handle nested insert.
        return -1;
    }
    if (targetSectionLocalId === null) {
        return position === 'before' ? 0 : sections.length;
    }
    const index = sections.findIndex((s) => s.localId === targetSectionLocalId);
    if (index === -1) {
        return sections.length;
    }
    return position === 'before' ? index : index + 1;
}

/**
 * Insert a new section into the top-level list (before/after).
 * Returns new array; does not mutate.
 */
export function insertSection(
    sections: BuilderSection[],
    newSection: BuilderSection,
    targetSectionLocalId: string | null,
    position: DropPosition
): BuilderSection[] {
    if (position === 'inside') {
        // Nested insert: find container and add to its props.sections.
        if (targetSectionLocalId === null) return [...sections, newSection];
        const containerIndex = sections.findIndex((s) => s.localId === targetSectionLocalId);
        if (containerIndex === -1) return [...sections, newSection];
        const container = sections[containerIndex];
        if (!isContainerSection(container)) {
            return [...sections, newSection];
        }
        const props = getSectionProps(container) || {};
        const innerSections = Array.isArray(props.sections) ? (props.sections as unknown[]) : [];
        const newInner = [
            ...innerSections,
            { type: newSection.type, props: getSectionProps(newSection) || {} },
        ];
        const nextProps = { ...props, sections: newInner };
        const updatedContainer: BuilderSection = {
            ...container,
            props: nextProps,
            propsText: JSON.stringify(nextProps, null, 2),
        };
        const next = [...sections];
        next[containerIndex] = updatedContainer;
        return next;
    }

    const insertIndex = getInsertIndex(sections, targetSectionLocalId, position);
    const safeIndex = Math.max(0, Math.min(insertIndex, sections.length));
    const next = [...sections];
    next.splice(safeIndex, 0, newSection);
    return next;
}

export function findSection(sections: BuilderSection[], localId: string): BuilderSection | undefined {
    return sections.find((s) => s.localId === localId);
}

export function updateSectionProps(
    sections: BuilderSection[],
    localId: string,
    updater: (props: Record<string, unknown>) => Record<string, unknown>
): BuilderSection[] {
    const index = sections.findIndex((s) => s.localId === localId);
    if (index === -1) return sections;
    const section = sections[index];
    const props = getSectionProps(section) || {};
    const nextProps = updater(props);
    const next = [...sections];
    next[index] = { ...section, props: nextProps, propsText: JSON.stringify(nextProps, null, 2), propsError: null };
    return next;
}

export function removeSection(sections: BuilderSection[], localId: string): BuilderSection[] {
    return sections.filter((s) => s.localId !== localId);
}

export function duplicateSection(sections: BuilderSection[], localId: string, newLocalId: string): BuilderSection[] {
    const index = sections.findIndex((s) => s.localId === localId);
    if (index === -1) return sections;
    const copy = { ...sections[index], localId: newLocalId };
    const next = [...sections];
    next.splice(index + 1, 0, copy);
    return next;
}

/** Reorder: move section to before/after target. Returns new array; does not mutate. */
export function moveSection(
    sections: BuilderSection[],
    sectionLocalId: string,
    targetSectionLocalId: string | null,
    position: DropPosition
): BuilderSection[] {
    const section = findSection(sections, sectionLocalId);
    if (!section) return sections;
    const without = removeSection(sections, sectionLocalId);
    return insertSection(without, section, targetSectionLocalId, position);
}

/** Alias for findSection for tree-style API. */
export function getSectionById(sections: BuilderSection[], localId: string): BuilderSection | undefined {
    return findSection(sections, localId);
}
