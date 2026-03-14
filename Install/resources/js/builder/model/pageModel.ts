import { resolveComponentProps } from '@/builder/componentRegistry';
import { cloneRecordData } from '@/builder/runtime/clone';
import { isRecord, parseSectionProps, stringifySectionProps } from '@/builder/state/sectionProps';
import type { BuilderSection } from '@/builder/visual/treeUtils';

export type BuilderPageEditorMode = 'builder' | 'text';

export interface BuilderPageSectionInstance {
    localId: string;
    type: string;
    props: Record<string, unknown>;
    bindingMeta: Record<string, unknown> | null;
}

export interface BuilderPageModel {
    schemaVersion: number;
    editorMode: BuilderPageEditorMode;
    textEditorHtml: string;
    sections: BuilderPageSectionInstance[];
    extraContent: Record<string, unknown>;
}

export interface BuilderPageModelSectionInput {
    localId?: string | null;
    type?: string | null;
    props?: Record<string, unknown> | null;
    binding?: Record<string, unknown> | null;
    bindingMeta?: Record<string, unknown> | null;
}

export interface BuilderPageModelOptions {
    createLocalId?: (index: number) => string;
    fallbackSections?: BuilderPageModelSectionInput[];
    normalizeProps?: (sectionType: string, props: Record<string, unknown>) => Record<string, unknown>;
    denormalizeProps?: (sectionType: string, props: Record<string, unknown>) => Record<string, unknown>;
}

function cloneRecord(value: Record<string, unknown> | null | undefined): Record<string, unknown> {
    return cloneRecordData(value);
}

function normalizeSectionTypeKey(value: string | null | undefined, fallback: string): string {
    const normalized = typeof value === 'string' ? value.trim().toLowerCase() : '';
    return normalized !== '' ? normalized : fallback;
}

function resolveExplicitProps(
    sectionType: string,
    props: Record<string, unknown>,
    normalizeProps?: BuilderPageModelOptions['normalizeProps']
): Record<string, unknown> {
    const normalizedProps = normalizeProps
        ? normalizeProps(sectionType, cloneRecord(props))
        : cloneRecord(props);

    return resolveComponentProps(sectionType, normalizedProps);
}

function toSectionInstance(
    input: BuilderPageModelSectionInput,
    index: number,
    options: BuilderPageModelOptions = {}
): BuilderPageSectionInstance | null {
    const sectionType = normalizeSectionTypeKey(input.type, `section-${index + 1}`);
    if (sectionType === '') {
        return null;
    }

    const localId = typeof input.localId === 'string' && input.localId.trim() !== ''
        ? input.localId.trim()
        : (options.createLocalId ? options.createLocalId(index) : `section-${index + 1}`);
    const props = isRecord(input.props) ? input.props : {};
    const bindingMeta = isRecord(input.bindingMeta)
        ? cloneRecord(input.bindingMeta)
        : (isRecord(input.binding) ? cloneRecord(input.binding) : null);

    return {
        localId,
        type: sectionType,
        props: resolveExplicitProps(sectionType, props, options.normalizeProps),
        bindingMeta,
    };
}

export function buildBuilderPageModelFromContentJson(
    contentSource: unknown,
    options: BuilderPageModelOptions = {}
): BuilderPageModel {
    const contentRecord = isRecord(contentSource) ? cloneRecord(contentSource) : {};
    const rawSections = Array.isArray(contentRecord.sections)
        ? contentRecord.sections
        : (options.fallbackSections ?? []);
    const sections = rawSections.flatMap((entry, index) => {
        const sectionInput = isRecord(entry) ? {
            localId: typeof entry.localId === 'string' ? entry.localId : null,
            type: typeof entry.type === 'string' ? entry.type : null,
            props: isRecord(entry.props) ? entry.props : {},
            binding: isRecord(entry.binding) ? entry.binding : null,
        } satisfies BuilderPageModelSectionInput : null;

        if (!sectionInput) {
            return [];
        }

        const nextSection = toSectionInstance(sectionInput, index, options);
        return nextSection ? [nextSection] : [];
    });

    const editorMode: BuilderPageEditorMode = contentRecord.editor_mode === 'text' ? 'text' : 'builder';
    const textEditorHtml = typeof contentRecord.text_editor_html === 'string'
        ? contentRecord.text_editor_html
        : '';

    delete contentRecord.sections;
    delete contentRecord.editor_mode;
    delete contentRecord.text_editor_html;

    return {
        schemaVersion: 1,
        editorMode,
        textEditorHtml,
        sections,
        extraContent: contentRecord,
    };
}

export function builderPageModelToContentJson(
    model: BuilderPageModel,
    options: BuilderPageModelOptions = {}
): Record<string, unknown> {
    if (model.editorMode === 'text') {
        return {
            ...cloneRecord(model.extraContent),
            editor_mode: 'text',
            text_editor_html: model.textEditorHtml,
            sections: [],
        };
    }

    const sections = model.sections.map((section, index) => {
        const sectionType = normalizeSectionTypeKey(section.type, `section-${index + 1}`);
        const nextProps = options.denormalizeProps
            ? options.denormalizeProps(sectionType, cloneRecord(section.props))
            : cloneRecord(section.props);

        return {
            type: sectionType,
            props: nextProps,
            localId: section.localId,
            ...(isRecord(section.bindingMeta) ? { binding: cloneRecord(section.bindingMeta) } : {}),
        };
    });

    return {
        ...cloneRecord(model.extraContent),
        editor_mode: 'builder',
        text_editor_html: '',
        sections,
    };
}

export function builderPageModelToSectionDrafts(model: BuilderPageModel): BuilderSection[] {
    return model.sections.map((section) => ({
        localId: section.localId,
        type: section.type,
        props: cloneRecord(section.props),
        propsText: stringifySectionProps(section.props),
        propsError: null,
        bindingMeta: isRecord(section.bindingMeta) ? cloneRecord(section.bindingMeta) : null,
    }));
}

export function buildBuilderPageModelFromSectionDrafts(
    sections: BuilderSection[],
    input: {
        editorMode?: BuilderPageEditorMode;
        textEditorHtml?: string;
        extraContent?: Record<string, unknown>;
    } = {},
    options: BuilderPageModelOptions = {}
): BuilderPageModel {
    const normalizedSections = normalizeBuilderSectionDrafts(sections, options).map((section) => ({
        localId: section.localId,
        type: section.type,
        props: cloneRecord(section.props ?? {}),
        bindingMeta: isRecord(section.bindingMeta) ? cloneRecord(section.bindingMeta) : null,
    }));

    return {
        schemaVersion: 1,
        editorMode: input.editorMode ?? 'builder',
        textEditorHtml: input.textEditorHtml ?? '',
        sections: normalizedSections,
        extraContent: cloneRecord(input.extraContent ?? {}),
    };
}

export function normalizeBuilderSectionDraft(
    section: BuilderSection,
    options: BuilderPageModelOptions = {}
): BuilderSection {
    const normalizedType = normalizeSectionTypeKey(section.type, section.type?.trim() || 'section');
    const parsedProps = parseSectionProps(section.propsText);
    const fallbackProps = isRecord(section.props) ? section.props : {};
    const explicitProps = resolveExplicitProps(
        normalizedType,
        parsedProps ?? fallbackProps,
        options.normalizeProps
    );

    return {
        ...section,
        type: normalizedType,
        props: explicitProps,
        propsText: parsedProps === null
            ? (typeof section.propsText === 'string' && section.propsText.trim() !== ''
                ? section.propsText
                : stringifySectionProps(explicitProps))
            : stringifySectionProps(explicitProps),
        propsError: section.propsError ?? null,
        bindingMeta: isRecord(section.bindingMeta) ? cloneRecord(section.bindingMeta) : null,
    };
}

export function normalizeBuilderSectionDrafts(
    sections: BuilderSection[],
    options: BuilderPageModelOptions = {}
): BuilderSection[] {
    return sections.map((section) => normalizeBuilderSectionDraft(section, options));
}

export function getBuilderSectionExplicitProps(section: BuilderSection): Record<string, unknown> {
    const parsedProps = parseSectionProps(section.propsText);
    if (parsedProps !== null) {
        return parsedProps;
    }

    return isRecord(section.props) ? cloneRecord(section.props) : {};
}
