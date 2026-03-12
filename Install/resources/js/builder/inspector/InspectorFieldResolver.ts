import { collectSchemaPrimitiveFieldDescriptors, type SchemaPrimitiveScalarType } from '@/lib/schemaPrimitiveFields';

export type CanonicalControlGroup = 'content' | 'layout' | 'style' | 'advanced' | 'responsive' | 'states' | 'data' | 'bindings' | 'meta';
export type CanonicalPrimaryPanelTab = 'content' | 'layout' | 'style' | 'advanced';
export type InspectorSchemaControlType =
    | 'text'
    | 'textarea'
    | 'color'
    | 'image'
    | 'icon'
    | 'link'
    | 'menu'
    | 'select'
    | 'toggle'
    | 'number'
    | 'spacing'
    | 'alignment';

export interface CanonicalControlMetadata {
    type: SchemaPrimitiveScalarType;
    label: string;
    group: CanonicalControlGroup;
    responsive: boolean;
    stateful: boolean;
    dynamic_capable: boolean;
}

export interface SchemaPrimitiveField {
    path: string[];
    type: SchemaPrimitiveScalarType;
    label: string;
    definition: Record<string, unknown>;
    control_meta: CanonicalControlMetadata;
}

function normalizeCanonicalControlGroup(value: unknown): CanonicalControlGroup | null {
    if (typeof value !== 'string') {
        return null;
    }

    const normalized = value.trim().toLowerCase();
    if (normalized === '') {
        return null;
    }

    if (normalized === 'state') {
        return 'states';
    }
    if (normalized === 'binding') {
        return 'bindings';
    }

    if (['content', 'layout', 'style', 'advanced', 'responsive', 'states', 'data', 'bindings', 'meta'].includes(normalized)) {
        return normalized as CanonicalControlGroup;
    }

    return null;
}

function readOptionalBoolean(value: unknown): boolean | null {
    return typeof value === 'boolean' ? value : null;
}

function inferCanonicalControlGroupForSchemaField(
    path: string[],
    label: string,
    definition: Record<string, unknown>,
): CanonicalControlGroup {
    const explicit = normalizeCanonicalControlGroup(definition.control_group ?? definition.group);
    if (explicit) {
        return explicit;
    }

    const lastKey = String(path[path.length - 1] ?? '').trim().toLowerCase();
    if (lastKey === 'layout_variant' || lastKey === 'style_variant') {
        return 'style';
    }

    const root = String(path[0] ?? '').trim().toLowerCase();
    const rootGroup = normalizeCanonicalControlGroup(root);
    if (rootGroup) {
        return rootGroup;
    }

    const normalizedLabel = label.trim().toLowerCase();
    if (normalizedLabel.startsWith('style:')) return 'style';
    if (normalizedLabel.startsWith('advanced:')) return 'advanced';
    if (normalizedLabel.startsWith('responsive:')) return 'responsive';
    if (normalizedLabel.startsWith('state:') || normalizedLabel.startsWith('states:')) return 'states';
    if (normalizedLabel.startsWith('data:')) return 'data';
    if (normalizedLabel.startsWith('binding:') || normalizedLabel.startsWith('bindings:')) return 'bindings';
    if (normalizedLabel.startsWith('meta:')) return 'meta';

    return 'content';
}

function inferCanonicalDynamicCapabilityForSchemaField(
    path: string[],
    type: SchemaPrimitiveScalarType,
    group: CanonicalControlGroup,
    definition: Record<string, unknown>,
): boolean {
    const explicitDynamicCapable = readOptionalBoolean(definition.dynamic_capable ?? definition['dynamic-capable']);
    if (explicitDynamicCapable !== null) {
        return explicitDynamicCapable;
    }

    const explicitSupportsDynamic = readOptionalBoolean(definition.supports_dynamic);
    if (explicitSupportsDynamic !== null) {
        return explicitSupportsDynamic;
    }

    if (type !== 'string') {
        return false;
    }

    if (['style', 'responsive', 'states', 'advanced', 'meta'].includes(group)) {
        return false;
    }

    const normalizedPath = path.map((segment) => segment.trim().toLowerCase()).filter(Boolean);
    const last = normalizedPath[normalizedPath.length - 1] ?? '';
    const format = typeof definition.format === 'string' ? definition.format.trim().toLowerCase() : '';
    if (last === 'html_id' || /(^|_)(icon_class|css_class|class_name)$/.test(last)) {
        return false;
    }
    if (format === 'color' || format === 'hex-color') {
        return false;
    }
    if (/(^|[._-])(color|colour|opacity|radius|padding|margin)($|[._-])/.test(normalizedPath.join('.'))) {
        return false;
    }

    return true;
}

function humanizePropertyKey(value: string): string {
    return value
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (match) => match.toUpperCase());
}

export function buildCanonicalControlMetadataForSchemaField(
    path: string[],
    type: SchemaPrimitiveScalarType,
    label: string,
    definition: Record<string, unknown>,
): CanonicalControlMetadata {
    const group = inferCanonicalControlGroupForSchemaField(path, label, definition);
    const responsive = readOptionalBoolean(definition.responsive) ?? group === 'responsive';
    const stateful = readOptionalBoolean(definition.stateful) ?? group === 'states';
    const dynamicCapable = inferCanonicalDynamicCapabilityForSchemaField(path, type, group, definition);

    return {
        type,
        label,
        group,
        responsive,
        stateful,
        dynamic_capable: dynamicCapable,
    };
}

export function collectInspectorSchemaPrimitiveFields(
    properties: Record<string, unknown>,
    currentValues: unknown = null,
): SchemaPrimitiveField[] {
    return collectSchemaPrimitiveFieldDescriptors(properties, { values: currentValues }).map((field) => ({
        ...field,
        control_meta: buildCanonicalControlMetadataForSchemaField(field.path, field.type, field.label, field.definition),
    }));
}

export function getMinimalSchemaFieldLabel(field: SchemaPrimitiveField): string {
    const leafKey = field.path[field.path.length - 1] ?? field.label;
    const normalizedLeafKey = leafKey.trim().toLowerCase();
    const compactOverrideByLeafKey: Record<string, string> = {
        bg_color: 'Background',
        overlay_color: 'Overlay',
        overlay_opacity_percent: 'Overlay Opacity',
        fg_color: 'Text Color',
        bd_color: 'Border Color',
        border_radius_px: 'Radius',
        padding_y_px: 'Padding Y',
        padding_x_px: 'Padding X',
        margin_y_px: 'Margin Y',
        margin_x_px: 'Margin X',
        opacity_percent: 'Opacity',
        html_id: 'ID',
        css_class: 'Class',
        custom_css: 'Custom CSS',
        button_preset: 'Button Preset',
        card_preset: 'Card Preset',
        input_preset: 'Input Preset',
        position_mode: 'Position',
        z_index: 'Z-Index',
        top_px: 'Top',
        right_px: 'Right',
        bottom_px: 'Bottom',
        left_px: 'Left',
        data_testid: 'Test ID',
        data_tracking: 'Tracking',
    };

    if (compactOverrideByLeafKey[normalizedLeafKey]) {
        return compactOverrideByLeafKey[normalizedLeafKey];
    }

    const compactFromTitle = field.label
        .replace(/^Content:\s*/i, '')
        .replace(/^Style:\s*/i, '')
        .replace(/^Advanced:\s*/i, '')
        .replace(/^Responsive:\s*(Desktop|Tablet|Mobile)\s*/i, '')
        .replace(/^State:\s*(Normal|Hover|Focus|Active)\s*/i, '')
        .replace(/\(\s*Preview Tag\s*\)/ig, '')
        .replace(/\(\s*Token-backed\s*\)/ig, '')
        .replace(/\(\s*Scoped to Element\s*\)/ig, '')
        .replace(/\(\s*Preview Override\s*\)/ig, '')
        .replace(/\(\s*component\s*\)/ig, '')
        .replace(/\bOverride\b/ig, '')
        .replace(/\s{2,}/g, ' ')
        .trim();
    if (compactFromTitle !== '') {
        return compactFromTitle;
    }

    return humanizePropertyKey(leafKey);
}

export function getSchemaFieldControlType(field: SchemaPrimitiveField): InspectorSchemaControlType | null {
    const raw = field.definition.builder_field_type ?? field.definition.builderFieldType;
    if (typeof raw !== 'string' || raw.trim() === '') return null;
    const normalized = raw.trim().toLowerCase();
    switch (normalized) {
        case 'text':
            return 'text';
        case 'richtext':
            return 'textarea';
        case 'color':
            return 'color';
        case 'image':
            return 'image';
        case 'icon':
            return 'icon';
        case 'link':
            return 'link';
        case 'menu':
            return 'menu';
        case 'select':
        case 'layout-variant':
        case 'style-variant':
            return 'select';
        case 'boolean':
            return 'toggle';
        case 'number':
            return 'number';
        case 'spacing':
            return 'spacing';
        case 'alignment':
            return 'alignment';
        default:
            return null;
    }
}
