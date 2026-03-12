import { isRecord } from '@/builder/state/sectionProps';

export const CMS_LAYOUT_PRIMITIVE_SECTION_KEYS = ['container', 'grid', 'section'] as const;
export const CMS_NESTED_ADD_SECTION_KEYS = [
    'webu_general_text_01',
    'webu_general_heading_01',
    'webu_general_spacer_01',
    'webu_general_image_01',
    'webu_general_card_01',
] as const;

export function getSectionsArrayAtPath(parsed: Record<string, unknown>, path: number[]): unknown[] | null {
    if (path.length === 0) {
        return Array.isArray(parsed.sections) ? parsed.sections : null;
    }

    let current: unknown = parsed.sections;
    for (let index = 0; index < path.length - 1; index += 1) {
        if (!Array.isArray(current) || path[index] < 0 || path[index] >= current.length) {
            return null;
        }

        const section = current[path[index]];
        current = isRecord(section) && isRecord(section.props) && Array.isArray(section.props.sections)
            ? section.props.sections
            : null;
    }

    return Array.isArray(current) ? current : null;
}

export function getNestedSectionAtPath(
    parsed: Record<string, unknown>,
    path: number[],
): { type: string; props: Record<string, unknown> } | null {
    const sections = path.length === 0 ? null : getSectionsArrayAtPath(parsed, path.slice(0, -1));
    const index = path.length === 0 ? -1 : path[path.length - 1];
    if (!Array.isArray(sections) || index < 0 || index >= sections.length) {
        return null;
    }

    const section = sections[index];
    if (!isRecord(section)) {
        return null;
    }

    const type = typeof section.type === 'string'
        ? section.type
        : (typeof section.key === 'string' ? section.key : '');
    const props = isRecord(section.props) ? section.props : {};

    return { type, props };
}

export function updateNestedSectionPropsAtPath(
    parsed: Record<string, unknown>,
    path: number[],
    updater: (props: Record<string, unknown>) => Record<string, unknown>,
): Record<string, unknown> {
    if (path.length === 0) {
        return parsed;
    }

    const sections = Array.isArray(parsed.sections) ? [...parsed.sections] : [];
    const index = path[0];
    if (index < 0 || index >= sections.length) {
        return parsed;
    }

    const section = isRecord(sections[index]) ? { ...sections[index] } : {};
    const currentProps = isRecord(section.props) ? section.props : {};
    if (path.length === 1) {
        section.props = updater({ ...currentProps });
        sections[index] = section;
        return { ...parsed, sections };
    }

    section.props = updateNestedSectionPropsAtPath(currentProps, path.slice(1), updater);
    sections[index] = section;
    return { ...parsed, sections };
}

export function replaceNestedSectionsAtPath(
    parsed: Record<string, unknown>,
    path: number[],
    nextSections: unknown[],
): Record<string, unknown> {
    const replaceSections = (current: Record<string, unknown>, segments: number[]): Record<string, unknown> => {
        if (segments.length === 0) {
            return {
                ...current,
                sections: [...nextSections],
            };
        }

        const sections = Array.isArray(current.sections) ? [...current.sections] : [];
        const index = segments[0];
        const child = isRecord(sections[index]) ? sections[index] : {};
        const childProps = isRecord(child.props) ? child.props : {};

        sections[index] = {
            ...child,
            props: replaceSections(childProps, segments.slice(1)),
        };

        return {
            ...current,
            sections,
        };
    };

    return replaceSections(parsed, path);
}
