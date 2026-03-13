/**
 * Central Component Registry — single source of truth for all builder components.
 * Builder UI, CMS, and Webu AI use this to know which components exist, their
 * editable parameters, categories, and metadata. AI may only add components that exist in the registry.
 */

import type { BuilderCanvasComponent } from './visual/registryComponents';
import { HEADER_SCHEMA } from '@/components/layout/Header/Header.schema';
import { FOOTER_SCHEMA } from '@/components/layout/Footer/Footer.schema';
import { HERO_SCHEMA } from '@/components/sections/Hero/Hero.schema';
import { FEATURES_DEFAULTS } from '@/components/sections/Features/Features.defaults';
import { CARDS_DEFAULTS } from '@/components/sections/Cards/Cards.defaults';
import { GRID_DEFAULTS } from '@/components/sections/Grid/Grid.defaults';
import {
    BuilderButtonCanvasSection,
    BuilderCardCanvasSection,
    BuilderCollectionCanvasSection,
    BuilderFooterCanvasSection,
    BuilderGenericCanvasSection,
    BuilderFormCanvasSection,
    BuilderHeadingCanvasSection,
    BuilderHeaderCanvasSection,
    BuilderHeroCanvasSection,
    BuilderImageCanvasSection,
    BuilderNewsletterCanvasSection,
    BuilderSectionCanvasSection,
    BuilderSpacerCanvasSection,
    BuilderTextCanvasSection,
    BuilderVideoCanvasSection,
} from './visual/registryComponents';
import { normalizeProjectSiteType, type ProjectSiteType } from './projectTypes';

export type ParameterType = 'string' | 'number' | 'boolean' | 'image' | 'collection' | 'richtext' | 'url';

export interface ParameterSchema {
    type: ParameterType;
    default?: string | number | boolean | null;
    title?: string;
    /** For enums / select */
    enum?: string[];
}

export type BuilderFieldType =
    | 'text'
    | 'richtext'
    | 'image'
    | 'video'
    | 'icon'
    | 'link'
    | 'menu'
    | 'repeater'
    | 'button-group'
    | 'color'
    | 'number'
    | 'boolean'
    | 'typography'
    | 'width'
    | 'height'
    | 'alignment'
    | 'spacing'
    | 'radius'
    | 'shadow'
    | 'overlay'
    | 'visibility'
    | 'select'
    | 'layout-variant'
    | 'style-variant';

export type BuilderFieldGroup = 'content' | 'layout' | 'style' | 'advanced' | 'responsive' | 'state' | 'states' | 'data' | 'bindings' | 'meta';
export type BuilderResponsiveBreakpoint = 'desktop' | 'tablet' | 'mobile';
export type BuilderInteractionState = 'normal' | 'hover' | 'focus' | 'active';
export type BuilderProjectType =
    | 'general'
    | 'marketing'
    | 'ecommerce'
    | 'booking'
    | 'blog'
    | 'portfolio'
    | 'saas'
    | 'restaurant'
    | 'medical'
    | 'services';

export interface BuilderFieldOption {
    label: string;
    value: string;
    description?: string;
}

export interface BuilderFieldDefinition {
    path: string;
    label: string;
    type: BuilderFieldType;
    group: BuilderFieldGroup;
    default?: unknown;
    options?: BuilderFieldOption[];
    placeholder?: string;
    description?: string;
    min?: number;
    max?: number;
    step?: number;
    units?: string[];
    accepts?: string[];
    itemFields?: BuilderFieldDefinition[];
    states?: BuilderInteractionState[];
    responsive?: boolean;
    chatEditable?: boolean;
    bindingCompatible?: boolean;
    projectTypes?: BuilderProjectType[];
}

export interface BuilderFieldGroupDefinition {
    key: BuilderFieldGroup;
    label: string;
    description?: string;
    fields: string[];
}

export interface BuilderVariantDefinition {
    kind: 'layout' | 'style';
    label: string;
    options: BuilderFieldOption[];
    default?: string;
}

export interface BuilderResponsiveSupport {
    enabled: boolean;
    breakpoints: BuilderResponsiveBreakpoint[];
    supportsVisibility: boolean;
    supportsResponsiveOverrides: boolean;
    supportsOverrides?: boolean;
    interactionStates: BuilderInteractionState[];
}

export interface BuilderCodegenMetadata {
    tagName: string;
    importPath: string | null;
    importName: string;
    importKind: 'default';
}

export interface BuilderComponentSchema {
    schemaVersion?: number;
    componentKey: string;
    displayName: string;
    category: BuilderCategory;
    icon?: string;
    defaultProps: Record<string, unknown>;
    fields: BuilderFieldDefinition[];
    editableFields?: string[];
    contentGroups?: BuilderFieldGroupDefinition[];
    styleGroups?: BuilderFieldGroupDefinition[];
    advancedGroups?: BuilderFieldGroupDefinition[];
    variants?: {
        layout?: string[];
        style?: string[];
    };
    variantDefinitions?: BuilderVariantDefinition[];
    responsive?: boolean;
    responsiveSupport?: BuilderResponsiveSupport;
    chatTargets?: string[];
    bindingFields?: string[];
    projectTypes?: BuilderProjectType[];
    serializable?: boolean;
    codegen?: BuilderCodegenMetadata | null;
}

export interface ComponentMetadata {
    icon?: string;
    supportsSpacing?: boolean;
    supportsBackground?: boolean;
    supportsAnimation?: boolean;
    /** Show in Advanced tab */
    supportsPadding?: boolean;
    supportsMargin?: boolean;
    supportsBorderRadius?: boolean;
}

export interface ComponentDefinition {
    /** Registry ID (section key), e.g. webu_general_hero_01 */
    id: string;
    /** Display name */
    name: string;
    /** Builder panel category */
    category: BuilderCategory;
    /** Editable parameters schema; used to generate parameter controls */
    parameters: Record<string, ParameterSchema>;
    /** Rich schema used by canvas selection, sidebar binding, and chat targeting. */
    schema?: BuilderComponentSchema;
    /** Visual builder renderer; defaults to a centralized category-based fallback. */
    canvasComponent?: BuilderCanvasComponent;
    metadata?: ComponentMetadata;
    projectTypes?: BuilderProjectType[];
    /** Normalized AI/builder governance category. Falls back to derived value when omitted. */
    governanceCategory?: BuilderGovernanceCategory;
}

export interface BuilderComponentRuntimeEntry {
    componentKey: string;
    displayName: string;
    category: BuilderCategory;
    component: BuilderCanvasComponent;
    schema: BuilderComponentSchema;
    defaults: Record<string, unknown>;
    projectTypes: BuilderProjectType[];
    codegen: BuilderCodegenMetadata | null;
}

export type BuilderCategory =
    | 'sections'
    | 'hero'
    | 'ecommerce'
    | 'marketing'
    | 'content'
    | 'header'
    | 'footer'
    | 'general'
    | 'layout'
    | 'booking'
    | 'blog'
    | 'portfolio';

export const BUILDER_GOVERNANCE_CATEGORIES = [
    'ecommerce',
    'booking',
    'landing',
    'marketing',
    'blog',
    'general',
] as const;

export type BuilderGovernanceCategory = (typeof BUILDER_GOVERNANCE_CATEGORIES)[number];

export interface BuilderGovernedComponent {
    type: string;
    label: string;
    category: BuilderGovernanceCategory;
    schema: BuilderComponentSchema;
}

/** Advanced settings applied to every component (padding, margin, background, border radius, animations). */
export const ADVANCED_SETTINGS: Record<string, ParameterSchema> = {
    padding: { type: 'string', title: 'Padding', default: '' },
    margin: { type: 'string', title: 'Margin', default: '' },
    background: { type: 'string', title: 'Background', default: '' },
    borderRadius: { type: 'string', title: 'Border radius', default: '' },
    animation: { type: 'string', title: 'Animation', default: '' },
};

const DEFAULT_BREAKPOINTS: BuilderResponsiveBreakpoint[] = ['desktop', 'tablet', 'mobile'];
const DEFAULT_INTERACTION_STATES: BuilderInteractionState[] = ['normal', 'hover', 'focus', 'active'];
const CODEGEN_TAG_OVERRIDES: Record<string, string> = {
    webu_header_01: 'Header',
    webu_footer_01: 'Footer',
    webu_general_hero_01: 'HeroSection',
    webu_general_heading_01: 'HeadingSection',
    webu_general_text_01: 'TextSection',
    webu_general_button_01: 'ButtonSection',
    webu_general_image_01: 'ImageSection',
    webu_general_video_01: 'VideoSection',
    webu_general_card_01: 'CardSection',
    webu_general_form_wrapper_01: 'FormSection',
    webu_general_newsletter_01: 'NewsletterSection',
    webu_general_spacer_01: 'SpacerSection',
};

const GROUP_LABELS: Record<BuilderFieldGroup, { label: string; description: string }> = {
    content: { label: 'Content', description: 'Primary text, media, and interactive content.' },
    layout: { label: 'Layout', description: 'Alignment, width, height, and layout structure.' },
    style: { label: 'Style', description: 'Visual styling such as colors, typography, overlay, and radius.' },
    advanced: { label: 'Advanced', description: 'Padding, margins, z-index, and implementation details.' },
    responsive: { label: 'Responsive', description: 'Breakpoint-specific overrides and visibility rules.' },
    state: { label: 'State', description: 'Interaction state overrides.' },
    states: { label: 'States', description: 'Interaction state overrides.' },
    data: { label: 'Data', description: 'Collection, source, and structured data bindings.' },
    bindings: { label: 'Bindings', description: 'Dynamic bindings and external value connections.' },
    meta: { label: 'Meta', description: 'Component metadata used by the builder.' },
};

function field(
    path: string,
    label: string,
    type: BuilderFieldType,
    group: BuilderFieldGroup,
    extra: Omit<BuilderFieldDefinition, 'path' | 'label' | 'type' | 'group'> = {}
): BuilderFieldDefinition {
    return {
        path,
        label,
        type,
        group,
        chatEditable: extra.chatEditable ?? true,
        bindingCompatible: extra.bindingCompatible ?? true,
        ...extra,
    };
}

function normalizeFieldGroup(group: BuilderFieldGroup): BuilderFieldGroup {
    return group === 'state' ? 'states' : group;
}

function humanizePath(path: string): string {
    return path
        .split('.')
        .filter(Boolean)
        .map((segment) => segment.replace(/_/g, ' '))
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
        .join(' ');
}

function toPascalCase(value: string): string {
    return value
        .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
        .split(/[^A-Za-z0-9]+/)
        .map((segment) => segment.trim())
        .filter(Boolean)
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
        .join('');
}

function stripRegistryAffixes(value: string): string {
    return value
        .replace(/^webu_/, '')
        .replace(/_(\d{2})$/, '')
        .replace(/^(general|ecom|header|footer|marketing|booking|blog|portfolio)_/, '');
}

function deriveCodegenBaseTagName(definition: ComponentDefinition, schema: BuilderComponentSchema): string {
    const override = CODEGEN_TAG_OVERRIDES[normalizeKey(definition.id)];
    if (override) {
        return override;
    }

    const displayCandidate = toPascalCase(schema.displayName || definition.name || definition.id);
    const fallbackBase = displayCandidate !== ''
        ? displayCandidate
        : toPascalCase(stripRegistryAffixes(definition.id));

    if (['header', 'footer', 'layout'].includes(schema.category)) {
        return fallbackBase;
    }

    return fallbackBase.endsWith('Section') ? fallbackBase : `${fallbackBase}Section`;
}

function buildCodegenCollisionCounts(): Map<string, number> {
    const counts = new Map<string, number>();

    Object.values(REGISTRY).forEach((definition) => {
        const sourceSchema = definition.schema ?? buildLegacySchema(definition);
        const candidate = deriveCodegenBaseTagName(definition, sourceSchema);
        counts.set(candidate, (counts.get(candidate) ?? 0) + 1);
    });

    return counts;
}

function resolveCodegenImportPath(category: BuilderCategory, tagName: string): string | null {
    if (category === 'header' || category === 'footer') {
        return `@/components/${tagName}`;
    }
    if (category === 'layout') {
        return `@/layouts/${tagName}`;
    }

    return `@/sections/${tagName}`;
}

function deriveUniqueCodegenTagName(
    definition: ComponentDefinition,
    schema: BuilderComponentSchema,
    collisionCounts: Map<string, number>
): string {
    const preferred = deriveCodegenBaseTagName(definition, schema);
    if ((collisionCounts.get(preferred) ?? 0) <= 1) {
        return preferred;
    }

    const registrySpecific = toPascalCase(normalizeKey(definition.id));
    if (schema.category === 'header' || schema.category === 'footer' || schema.category === 'layout') {
        return registrySpecific;
    }

    return registrySpecific.endsWith('Section') ? registrySpecific : `${registrySpecific}Section`;
}

function resolveCodegenMetadata(definition: ComponentDefinition, schema: BuilderComponentSchema): BuilderCodegenMetadata | null {
    if (schema.codegen === null) {
        return null;
    }

    const explicit = schema.codegen ?? null;
    const collisionCounts = buildCodegenCollisionCounts();
    const tagName = explicit?.tagName?.trim() || deriveUniqueCodegenTagName(definition, schema, collisionCounts);
    const importName = explicit?.importName?.trim() || tagName;
    const importKind = explicit?.importKind ?? 'default';
    const importPath = explicit?.importPath === undefined
        ? resolveCodegenImportPath(schema.category, tagName)
        : explicit.importPath;

    return {
        tagName,
        importName,
        importKind,
        importPath,
    };
}

function buildParameterSchemaMap(definition: ComponentDefinition): Record<string, ParameterSchema> {
    const base = { ...definition.parameters };
    const meta = definition.metadata ?? {};

    if (meta.supportsPadding || meta.supportsMargin || meta.supportsBackground || meta.supportsBorderRadius || meta.supportsAnimation) {
        Object.assign(base, ADVANCED_SETTINGS);
    }

    return base;
}

function buildDefaultPropsFromParameters(parameters: Record<string, ParameterSchema>): Record<string, unknown> {
    return Object.fromEntries(
        Object.entries(parameters)
            .filter(([, schema]) => schema.default !== undefined)
            .map(([path, schema]) => [path, schema.default ?? null])
    );
}

function inferLegacyFieldType(path: string, type: ParameterType): BuilderFieldType {
    const normalizedPath = path.trim().toLowerCase();

    if (normalizedPath.includes('video')) {
        return 'video';
    }
    if (normalizedPath.includes('icon')) {
        return 'icon';
    }
    if (normalizedPath.includes('menu') || normalizedPath.includes('navigation')) {
        return 'menu';
    }
    if (normalizedPath.includes('button') && normalizedPath.endsWith('s')) {
        return 'button-group';
    }
    if (normalizedPath.includes('items') || normalizedPath.includes('cards') || normalizedPath.includes('sociallinks')) {
        return 'repeater';
    }
    if (normalizedPath.includes('color') || normalizedPath.includes('background')) {
        return 'color';
    }
    if (normalizedPath.includes('overlay')) {
        return 'overlay';
    }
    if (normalizedPath.includes('typography') || normalizedPath.includes('font')) {
        return 'typography';
    }
    if (normalizedPath.includes('width')) {
        return 'width';
    }
    if (normalizedPath.includes('height')) {
        return 'height';
    }
    if (normalizedPath.includes('align')) {
        return 'alignment';
    }
    if (normalizedPath.includes('padding') || normalizedPath.includes('margin') || normalizedPath.includes('spacing') || normalizedPath.includes('gap')) {
        return 'spacing';
    }
    if (normalizedPath.includes('radius')) {
        return 'radius';
    }
    if (normalizedPath.includes('shadow')) {
        return 'shadow';
    }
    if (normalizedPath.includes('visibility') || normalizedPath.startsWith('hide_') || normalizedPath.startsWith('show_')) {
        return 'visibility';
    }
    if (normalizedPath === 'layout_variant' || normalizedPath === 'layoutvariant') {
        return 'layout-variant';
    }
    if (normalizedPath === 'style_variant' || normalizedPath === 'stylevariant') {
        return 'style-variant';
    }

    switch (type) {
        case 'image':
            return 'image';
        case 'number':
            return 'number';
        case 'boolean':
            return 'boolean';
        case 'collection':
            return 'menu';
        case 'richtext':
            return 'richtext';
        case 'url':
            return 'link';
        default:
            return 'text';
    }
}

function inferLegacyFieldGroup(path: string, type: BuilderFieldType): BuilderFieldGroup {
    const normalizedPath = path.trim().toLowerCase();

    if (normalizedPath.startsWith('responsive.')) {
        return 'responsive';
    }
    if (normalizedPath.startsWith('states.') || normalizedPath.startsWith('state.')) {
        return 'states';
    }
    if (normalizedPath.includes('binding')) {
        return 'bindings';
    }
    if (normalizedPath.includes('source') || normalizedPath.includes('collection') || normalizedPath.includes('provider')) {
        return 'data';
    }
    if (normalizedPath.includes('layout_variant') || normalizedPath.includes('layoutvariant') || type === 'layout-variant') {
        return 'layout';
    }
    if (normalizedPath.includes('style_variant') || normalizedPath.includes('stylevariant') || type === 'style-variant') {
        return 'style';
    }
    if (['color', 'overlay', 'radius', 'shadow', 'typography'].includes(type)) {
        return 'style';
    }
    if (['alignment', 'width', 'height'].includes(type)) {
        return 'layout';
    }
    if (type === 'spacing' || normalizedPath.includes('padding') || normalizedPath.includes('margin') || normalizedPath.includes('animation')) {
        return 'advanced';
    }
    if (type === 'visibility') {
        return 'responsive';
    }

    return 'content';
}

function inferFieldItemSchema(path: string, type: BuilderFieldType): BuilderFieldDefinition[] | undefined {
    const normalizedPath = path.trim().toLowerCase();

    if (type === 'menu') {
        const shared = [
            field('label', 'Label', 'text', 'content', { chatEditable: false }),
            field('url', 'URL', 'link', 'content', { chatEditable: false }),
        ];

        if (normalizedPath.includes('social')) {
            shared.push(field('icon', 'Icon', 'icon', 'content', { chatEditable: false }));
        }

        return shared;
    }

    if (type === 'button-group') {
        return [
            field('label', 'Label', 'text', 'content', { chatEditable: false }),
            field('url', 'URL', 'link', 'content', { chatEditable: false }),
            field('variant', 'Variant', 'style-variant', 'style', {
                chatEditable: false,
                options: [
                    { label: 'Primary', value: 'primary' },
                    { label: 'Secondary', value: 'secondary' },
                    { label: 'Ghost', value: 'ghost' },
                ],
            }),
        ];
    }

    if (type === 'repeater') {
        return [
            field('title', 'Title', 'text', 'content', { chatEditable: false }),
            field('description', 'Description', 'richtext', 'content', { chatEditable: false }),
            field('image', 'Image', 'image', 'content', { chatEditable: false }),
            field('url', 'URL', 'link', 'content', { chatEditable: false }),
        ];
    }

    return undefined;
}

function inferProjectTypes(category: BuilderCategory): BuilderProjectType[] {
    switch (category) {
        case 'ecommerce':
            return ['ecommerce'];
        case 'booking':
            return ['booking'];
        case 'blog':
            return ['blog'];
        case 'portfolio':
            return ['portfolio'];
        case 'marketing':
            return ['marketing', 'general', 'saas', 'services'];
        case 'header':
        case 'footer':
        case 'content':
        case 'general':
        case 'layout':
        case 'sections':
        default:
            return ['general', 'marketing', 'ecommerce', 'saas', 'restaurant', 'medical', 'services', 'portfolio', 'blog', 'booking'];
    }
}

function cloneFieldDefinition(definition: BuilderFieldDefinition): BuilderFieldDefinition {
    return {
        ...definition,
        group: normalizeFieldGroup(definition.group),
        options: definition.options ? definition.options.map((option) => ({ ...option })) : undefined,
        itemFields: definition.itemFields?.map((fieldDefinition) => cloneFieldDefinition(fieldDefinition)),
        projectTypes: definition.projectTypes ? [...definition.projectTypes] : undefined,
        states: definition.states ? [...definition.states] : undefined,
        description: definition.description ?? humanizePath(definition.path),
    };
}

function setDefaultValueAtPath(target: Record<string, unknown>, path: string, value: unknown): void {
    const segments = path.split('.').filter(Boolean);
    if (segments.length === 0) {
        return;
    }

    let cursor: Record<string, unknown> = target;
    segments.forEach((segment, index) => {
        const isLeaf = index === segments.length - 1;
        if (isLeaf) {
            if (cursor[segment] === undefined) {
                cursor[segment] = value;
            }
            return;
        }

        const existing = cursor[segment];
        if (!existing || typeof existing !== 'object' || Array.isArray(existing)) {
            cursor[segment] = {};
        }
        cursor = cursor[segment] as Record<string, unknown>;
    });
}

function mergeFieldDefinitions(primary: BuilderFieldDefinition[], fallback: BuilderFieldDefinition[]): BuilderFieldDefinition[] {
    const byPath = new Map<string, BuilderFieldDefinition>();

    [...fallback, ...primary].forEach((definition) => {
        const normalized = cloneFieldDefinition(definition);
        if (!normalized.itemFields) {
            normalized.itemFields = inferFieldItemSchema(normalized.path, normalized.type);
        }
        byPath.set(normalized.path, normalized);
    });

    return Array.from(byPath.values());
}

function buildFoundationFields(definition: ComponentDefinition): BuilderFieldDefinition[] {
    const metadata = definition.metadata ?? {};
    const fields: BuilderFieldDefinition[] = [];

    if (metadata.supportsSpacing) {
        fields.push(
            field('layout.alignment', 'Alignment', 'alignment', 'layout', {
                default: 'left',
                chatEditable: false,
                bindingCompatible: false,
                options: [
                    { label: 'Left', value: 'left' },
                    { label: 'Center', value: 'center' },
                    { label: 'Right', value: 'right' },
                ],
            }),
            field('layout.width', 'Width', 'width', 'layout', {
                default: '',
                placeholder: 'auto, 100%, 48rem',
                chatEditable: false,
                bindingCompatible: false,
            }),
            field('layout.height', 'Min height', 'height', 'layout', {
                default: '',
                placeholder: 'auto, 420px',
                chatEditable: false,
                bindingCompatible: false,
            })
        );
    }

    if (metadata.supportsBackground) {
        fields.push(
            field('style.background_color', 'Background color', 'color', 'style', {
                default: '',
                chatEditable: false,
                bindingCompatible: false,
            }),
            field('style.text_color', 'Text color', 'color', 'style', {
                default: '',
                chatEditable: false,
                bindingCompatible: false,
            }),
            field('style.overlay_color', 'Overlay color', 'overlay', 'style', {
                default: '',
                chatEditable: false,
                bindingCompatible: false,
            }),
            field('style.overlay_opacity', 'Overlay opacity', 'number', 'style', {
                default: 0,
                min: 0,
                max: 1,
                step: 0.05,
                chatEditable: false,
                bindingCompatible: false,
            })
        );
    }

    if (metadata.supportsBorderRadius) {
        fields.push(
            field('style.border_radius', 'Border radius', 'radius', 'style', {
                default: '',
                placeholder: '12px',
                chatEditable: false,
                bindingCompatible: false,
            }),
            field('style.box_shadow', 'Shadow', 'shadow', 'style', {
                default: '',
                placeholder: '0 20px 40px rgba(0,0,0,0.12)',
                chatEditable: false,
                bindingCompatible: false,
            })
        );
    }

    if (metadata.supportsPadding || metadata.supportsMargin || metadata.supportsSpacing || metadata.supportsBackground || metadata.supportsAnimation) {
        fields.push(
            field('advanced.padding_top', 'Padding top', 'spacing', 'advanced', { default: '', chatEditable: false, bindingCompatible: false }),
            field('advanced.padding_bottom', 'Padding bottom', 'spacing', 'advanced', { default: '', chatEditable: false, bindingCompatible: false }),
            field('advanced.padding_left', 'Padding left', 'spacing', 'advanced', { default: '', chatEditable: false, bindingCompatible: false }),
            field('advanced.padding_right', 'Padding right', 'spacing', 'advanced', { default: '', chatEditable: false, bindingCompatible: false }),
            field('advanced.margin_top', 'Margin top', 'spacing', 'advanced', { default: '', chatEditable: false, bindingCompatible: false }),
            field('advanced.margin_bottom', 'Margin bottom', 'spacing', 'advanced', { default: '', chatEditable: false, bindingCompatible: false }),
            field('advanced.z_index', 'Z-index', 'number', 'advanced', { default: 0, chatEditable: false, bindingCompatible: false }),
            field('advanced.custom_class', 'Custom class', 'text', 'advanced', { default: '', chatEditable: false, bindingCompatible: false })
        );
    }

    if (metadata.supportsSpacing || metadata.supportsBackground) {
        const cap = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);
        DEFAULT_BREAKPOINTS.forEach((breakpoint) => {
            const labelPrefix = cap(breakpoint);
            fields.push(
                field(`responsive.${breakpoint}.background_color`, `${labelPrefix} background`, 'color', 'responsive', {
                    default: '',
                    responsive: true,
                    chatEditable: false,
                    bindingCompatible: false,
                }),
                field(`responsive.${breakpoint}.padding_top`, `${labelPrefix} padding top`, 'spacing', 'responsive', {
                    default: '',
                    responsive: true,
                    chatEditable: false,
                    bindingCompatible: false,
                }),
                field(`responsive.${breakpoint}.padding_bottom`, `${labelPrefix} padding bottom`, 'spacing', 'responsive', {
                    default: '',
                    responsive: true,
                    chatEditable: false,
                    bindingCompatible: false,
                }),
                field(`responsive.${breakpoint}.padding_left`, `${labelPrefix} padding left`, 'spacing', 'responsive', {
                    default: '',
                    responsive: true,
                    chatEditable: false,
                    bindingCompatible: false,
                }),
                field(`responsive.${breakpoint}.padding_right`, `${labelPrefix} padding right`, 'spacing', 'responsive', {
                    default: '',
                    responsive: true,
                    chatEditable: false,
                    bindingCompatible: false,
                }),
                field(`responsive.${breakpoint}.margin_top`, `${labelPrefix} margin top`, 'spacing', 'responsive', {
                    default: '',
                    responsive: true,
                    chatEditable: false,
                    bindingCompatible: false,
                }),
                field(`responsive.${breakpoint}.margin_bottom`, `${labelPrefix} margin bottom`, 'spacing', 'responsive', {
                    default: '',
                    responsive: true,
                    chatEditable: false,
                    bindingCompatible: false,
                }),
                field(`responsive.${breakpoint}.margin_left`, `${labelPrefix} margin left`, 'spacing', 'responsive', {
                    default: '',
                    responsive: true,
                    chatEditable: false,
                    bindingCompatible: false,
                }),
                field(`responsive.${breakpoint}.margin_right`, `${labelPrefix} margin right`, 'spacing', 'responsive', {
                    default: '',
                    responsive: true,
                    chatEditable: false,
                    bindingCompatible: false,
                }),
                field(`responsive.${breakpoint}.font_size`, `${labelPrefix} font size`, 'spacing', 'responsive', {
                    default: '',
                    responsive: true,
                    chatEditable: false,
                    bindingCompatible: false,
                }),
                field(`responsive.${breakpoint}.grid_columns`, `${labelPrefix} grid columns`, 'number', 'responsive', {
                    default: 0,
                    responsive: true,
                    chatEditable: false,
                    bindingCompatible: false,
                })
            );
        });

        fields.push(
            field('responsive.hide_on_desktop', 'Hide on desktop', 'visibility', 'responsive', { default: false, responsive: true, chatEditable: false, bindingCompatible: false }),
            field('responsive.hide_on_tablet', 'Hide on tablet', 'visibility', 'responsive', { default: false, responsive: true, chatEditable: false, bindingCompatible: false }),
            field('responsive.hide_on_mobile', 'Hide on mobile', 'visibility', 'responsive', { default: false, responsive: true, chatEditable: false, bindingCompatible: false })
        );
    }

    if (metadata.supportsBackground || metadata.supportsAnimation || metadata.supportsBorderRadius) {
        fields.push(
            field('states.hover.background_color', 'Hover background', 'color', 'states', { default: '', chatEditable: false, bindingCompatible: false }),
            field('states.hover.text_color', 'Hover text color', 'color', 'states', { default: '', chatEditable: false, bindingCompatible: false }),
            field('states.focus.box_shadow', 'Focus shadow', 'shadow', 'states', { default: '', chatEditable: false, bindingCompatible: false }),
            field('states.active.background_color', 'Active background', 'color', 'states', { default: '', chatEditable: false, bindingCompatible: false })
        );
    }

    return fields;
}

function buildLegacySchema(definition: ComponentDefinition): BuilderComponentSchema {
    const parameterMap = buildParameterSchemaMap(definition);

    return {
        componentKey: definition.id,
        displayName: definition.name,
        category: definition.category,
        icon: definition.metadata?.icon,
        defaultProps: buildDefaultPropsFromParameters(parameterMap),
        responsive: true,
        chatTargets: Object.keys(parameterMap),
        fields: Object.entries(parameterMap).map(([path, schema]) => {
            const type = inferLegacyFieldType(path, schema.type);
            return field(
                path,
                schema.title ?? humanizePath(path),
                type,
                inferLegacyFieldGroup(path, type),
                {
                    default: schema.default,
                    options: schema.enum?.map((value) => ({ label: value, value })),
                    projectTypes: inferProjectTypes(definition.category),
                }
            );
        }),
    };
}

function buildGroupDefinitions(fields: BuilderFieldDefinition[], groups: BuilderFieldGroup[]): BuilderFieldGroupDefinition[] {
    return groups.reduce<BuilderFieldGroupDefinition[]>((definitions, group) => {
            const normalizedGroup = normalizeFieldGroup(group);
            const fieldPaths = fields
                .filter((definition) => normalizeFieldGroup(definition.group) === normalizedGroup)
                .map((definition) => definition.path);

            if (fieldPaths.length === 0) {
                return definitions;
            }

            definitions.push({
                key: normalizedGroup,
                label: GROUP_LABELS[normalizedGroup].label,
                description: GROUP_LABELS[normalizedGroup].description,
                fields: fieldPaths,
            });

            return definitions;
        }, []);
}

function buildVariantDefinitions(schema: BuilderComponentSchema): BuilderVariantDefinition[] {
    const definitions: BuilderVariantDefinition[] = [];

    if (schema.variants?.layout && schema.variants.layout.length > 0) {
        definitions.push({
            kind: 'layout',
            label: 'Layout variants',
            options: schema.variants.layout.map((value) => ({ label: humanizePath(value), value })),
            default: typeof schema.defaultProps.layoutVariant === 'string'
                ? schema.defaultProps.layoutVariant
                : typeof schema.defaultProps.layout_variant === 'string'
                    ? schema.defaultProps.layout_variant
                    : schema.variants.layout[0],
        });
    }

    if (schema.variants?.style && schema.variants.style.length > 0) {
        definitions.push({
            kind: 'style',
            label: 'Style variants',
            options: schema.variants.style.map((value) => ({ label: humanizePath(value), value })),
            default: typeof schema.defaultProps.styleVariant === 'string'
                ? schema.defaultProps.styleVariant
                : typeof schema.defaultProps.style_variant === 'string'
                    ? schema.defaultProps.style_variant
                    : schema.variants.style[0],
        });
    }

    return definitions;
}

function normalizeResponsiveSupport(fields: BuilderFieldDefinition[], schema: BuilderComponentSchema): BuilderResponsiveSupport {
    const interactionStates: BuilderInteractionState[] = fields.some((definition) => normalizeFieldGroup(definition.group) === 'states')
        ? DEFAULT_INTERACTION_STATES
        : ['normal'];

    return schema.responsiveSupport ?? {
        enabled: schema.responsive !== false,
        breakpoints: DEFAULT_BREAKPOINTS,
        supportsVisibility: fields.some((definition) => definition.path.startsWith('responsive.hide_on_')),
        supportsResponsiveOverrides: fields.some((definition) => definition.path.startsWith('responsive.desktop.') || definition.path.startsWith('responsive.tablet.') || definition.path.startsWith('responsive.mobile.')),
        interactionStates,
    };
}

function normalizeSchema(definition: ComponentDefinition): BuilderComponentSchema {
    const sourceSchema = definition.schema ?? buildLegacySchema(definition);
    const mergedFields = mergeFieldDefinitions(sourceSchema.fields, buildFoundationFields(definition));
    const defaultProps = { ...(sourceSchema.defaultProps ?? {}) };

    mergedFields.forEach((definitionField) => {
        if (definitionField.default !== undefined) {
            setDefaultValueAtPath(defaultProps, definitionField.path, definitionField.default);
        }
    });

    const chatTargets = Array.from(new Set(
        (sourceSchema.chatTargets ?? mergedFields.filter((definitionField) => definitionField.chatEditable !== false).map((definitionField) => definitionField.path))
            .map((value) => value.trim())
            .filter(Boolean)
    ));
    const bindingFields = Array.from(new Set(
        (sourceSchema.bindingFields ?? mergedFields.filter((definitionField) => definitionField.bindingCompatible !== false).map((definitionField) => definitionField.path))
            .map((value) => value.trim())
            .filter(Boolean)
    ));
    const projectTypes = Array.from(new Set(sourceSchema.projectTypes ?? definition.projectTypes ?? inferProjectTypes(definition.category)));
    const variantDefinitions = sourceSchema.variantDefinitions ?? buildVariantDefinitions(sourceSchema);
    const normalizedBaseSchema: BuilderComponentSchema = {
        ...sourceSchema,
        schemaVersion: sourceSchema.schemaVersion ?? 2,
        componentKey: sourceSchema.componentKey,
        displayName: sourceSchema.displayName,
        category: sourceSchema.category,
        icon: sourceSchema.icon ?? definition.metadata?.icon,
        serializable: sourceSchema.serializable ?? true,
        defaultProps,
        fields: mergedFields,
        editableFields: sourceSchema.editableFields ?? mergedFields.filter((definitionField) => definitionField.chatEditable !== false).map((definitionField) => definitionField.path),
        responsive: sourceSchema.responsive ?? true,
        responsiveSupport: normalizeResponsiveSupport(mergedFields, sourceSchema),
        variants: sourceSchema.variants ?? {},
        variantDefinitions,
        chatTargets,
        bindingFields,
        projectTypes,
        contentGroups: sourceSchema.contentGroups ?? buildGroupDefinitions(mergedFields, ['content', 'data', 'bindings', 'meta']),
        styleGroups: sourceSchema.styleGroups ?? buildGroupDefinitions(mergedFields, ['layout', 'style', 'responsive', 'states']),
        advancedGroups: sourceSchema.advancedGroups ?? buildGroupDefinitions(mergedFields, ['advanced']),
    };

    return {
        ...normalizedBaseSchema,
        codegen: resolveCodegenMetadata(definition, normalizedBaseSchema),
    };
}

function isPlainObject(value: unknown): value is Record<string, unknown> {
    return !!value && typeof value === 'object' && !Array.isArray(value);
}

/** Alias groups: when an override sets one key, propagate to siblings so pickFirstValue works regardless of order. */
const PROP_ALIAS_GROUPS: readonly (readonly string[])[] = [
    ['title', 'headline', 'heading'],
    ['subtitle', 'subheading', 'description', 'body', 'copy', 'text'],
    ['button', 'buttonText', 'button_text', 'buttonLabel', 'button_label', 'ctaText', 'cta_text', 'ctaLabel', 'cta_label', 'label', 'submit_label'],
    ['button_url', 'buttonLink', 'button_link', 'buttonUrl', 'ctaLink', 'cta_link', 'ctaUrl', 'cta_url', 'href', 'url'],
    ['backgroundImage', 'background_image', 'image', 'image_url', 'imageUrl'],
];

export function getComponentPropAliasGroup(key: string): readonly string[] | null {
    for (const group of PROP_ALIAS_GROUPS) {
        if (group.includes(key)) return group;
    }
    return null;
}

export function expandComponentPropAliasPath(path: string | null | undefined): string[] {
    const normalizedSegments = String(path ?? '')
        .split('.')
        .map((segment) => segment.trim())
        .filter(Boolean);
    if (normalizedSegments.length === 0) {
        return [];
    }

    const lastSegment = normalizedSegments[normalizedSegments.length - 1] ?? '';
    const aliasGroup = getComponentPropAliasGroup(lastSegment);
    if (!aliasGroup) {
        return [normalizedSegments.join('.')];
    }

    const prefix = normalizedSegments.slice(0, -1);
    return Array.from(new Set(aliasGroup.map((alias) => [...prefix, alias].join('.'))));
}

function normalizeComparablePropPath(path: string): string {
    return path
        .split('.')
        .map((segment) => segment.trim())
        .filter((segment) => segment !== '' && !/^\d+$/.test(segment))
        .join('.');
}

function collectSchemaComparablePropPaths(schema: BuilderComponentSchema | null): Set<string> {
    const comparablePaths = new Set<string>();

    schema?.fields.forEach((field) => {
        const fieldPath = normalizeComparablePropPath(field.path);
        if (fieldPath !== '') {
            comparablePaths.add(fieldPath);
        }

        field.itemFields?.forEach((itemField) => {
            const nestedPath = normalizeComparablePropPath(`${field.path}.${itemField.path}`);
            if (nestedPath !== '') {
                comparablePaths.add(nestedPath);
            }
        });
    });

    return comparablePaths;
}

function shouldPropagateAliasPath(
    aliasPath: string,
    comparableSchemaPaths: Set<string> | null,
): boolean {
    if (!comparableSchemaPaths || comparableSchemaPaths.size === 0) {
        return false;
    }

    const comparableAliasPath = normalizeComparablePropPath(aliasPath);
    return comparableSchemaPaths.has(comparableAliasPath);
}

function mergeResolvedProps(
    base: Record<string, unknown>,
    overrides: Record<string, unknown>,
    comparableSchemaPaths: Set<string> | null = null,
    pathPrefix: string[] = [],
): Record<string, unknown> {
    const next: Record<string, unknown> = { ...base };
    const explicitOverridePaths = new Set(
        Object.keys(overrides)
            .map((key) => [...pathPrefix, key].join('.'))
            .filter(Boolean),
    );

    Object.entries(overrides).forEach(([key, value]) => {
        const current = next[key];
        if (isPlainObject(current) && isPlainObject(value)) {
            next[key] = mergeResolvedProps(current, value, comparableSchemaPaths, [...pathPrefix, key]);
            return;
        }

        next[key] = value;
        const group = getComponentPropAliasGroup(key);
        if (group && !isPlainObject(value)) {
            group.forEach((alias) => {
                const aliasPath = [...pathPrefix, alias].join('.');
                if (aliasPath === [...pathPrefix, key].join('.')) {
                    return;
                }
                if (explicitOverridePaths.has(aliasPath)) {
                    return;
                }
                if (shouldPropagateAliasPath(aliasPath, comparableSchemaPaths)) {
                    next[alias] = value;
                }
            });
        }
    });

    return next;
}

function parseComponentProps(input: unknown): Record<string, unknown> {
    if (typeof input === 'string') {
        try {
            const parsed: unknown = JSON.parse(input || '{}');
            return isPlainObject(parsed) ? parsed : {};
        } catch {
            return {};
        }
    }

    return isPlainObject(input) ? input : {};
}

function resolveCanvasComponent(definition: ComponentDefinition, schema: BuilderComponentSchema): BuilderCanvasComponent {
    if (definition.canvasComponent) {
        return definition.canvasComponent;
    }

    const normalizedKey = schema.componentKey.trim().toLowerCase().replace(/-/g, '_');

    if (schema.category === 'header' || normalizedKey.includes('header')) {
        return BuilderHeaderCanvasSection;
    }

    if (schema.category === 'footer' || normalizedKey.includes('footer')) {
        return BuilderFooterCanvasSection;
    }

    if (normalizedKey.includes('hero')) {
        return BuilderHeroCanvasSection;
    }

    if (normalizedKey.includes('heading')) {
        return BuilderHeadingCanvasSection;
    }

    if (normalizedKey.includes('newsletter')) {
        return BuilderNewsletterCanvasSection;
    }

    if (normalizedKey.includes('form')) {
        return BuilderFormCanvasSection;
    }

    if (normalizedKey.includes('image')) {
        return BuilderImageCanvasSection;
    }

    if (normalizedKey.includes('video')) {
        return BuilderVideoCanvasSection;
    }

    if (normalizedKey.includes('button')) {
        return BuilderButtonCanvasSection;
    }

    if (normalizedKey.includes('spacer')) {
        return BuilderSpacerCanvasSection;
    }

    if (normalizedKey.includes('card')) {
        return BuilderCardCanvasSection;
    }

    if (normalizedKey.includes('text')) {
        return BuilderTextCanvasSection;
    }

    if (normalizedKey.includes('section')) {
        return BuilderSectionCanvasSection;
    }

    if (schema.category === 'ecommerce' || normalizedKey.includes('product') || normalizedKey.includes('category')) {
        return BuilderCollectionCanvasSection;
    }

    return BuilderGenericCanvasSection;
}

const runtimeEntryCache = new Map<string, BuilderComponentRuntimeEntry>();

function builderFieldTypeToJsonType(type: BuilderFieldType): 'string' | 'number' | 'integer' | 'boolean' {
    switch (type) {
        case 'number':
            return 'number';
        case 'boolean':
        case 'visibility':
            return 'boolean';
        default:
            return 'string';
    }
}

function setNestedSchemaProperty(
    properties: Record<string, unknown>,
    path: string,
    definition: BuilderFieldDefinition
): void {
    const segments = path.split('.').filter(Boolean);
    if (segments.length === 0) {
        return;
    }

    let cursor = properties;
    segments.forEach((segment, index) => {
        const isLeaf = index === segments.length - 1;
        const existing = cursor[segment];

        if (isLeaf) {
            cursor[segment] = {
                type: builderFieldTypeToJsonType(definition.type),
                title: definition.label,
                default: definition.default,
                enum: definition.options?.map((option) => option.value),
                description: definition.description,
                control_group: normalizeFieldGroup(definition.group),
                builder_field_type: definition.type,
                chat_editable: definition.chatEditable !== false,
                binding_compatible: definition.bindingCompatible !== false,
                responsive: definition.responsive === true,
                min: definition.min,
                max: definition.max,
                step: definition.step,
                units: definition.units,
                accepts: definition.accepts,
                project_types: definition.projectTypes,
                item_fields: definition.itemFields?.map((fieldDefinition) => serializeFieldDefinition(fieldDefinition)),
                interaction_states: definition.states,
            };
            return;
        }

        if (!existing || typeof existing !== 'object' || Array.isArray(existing)) {
            cursor[segment] = {
                type: 'object',
                properties: {},
                title: segment,
            };
        }

        const nextContainer = cursor[segment] as { properties?: Record<string, unknown> };
        if (!nextContainer.properties) {
            nextContainer.properties = {};
        }
        cursor = nextContainer.properties;
    });
}

function serializeFieldDefinition(definition: BuilderFieldDefinition): Record<string, unknown> {
    return {
        path: definition.path,
        label: definition.label,
        type: definition.type,
        group: normalizeFieldGroup(definition.group),
        default: definition.default,
        description: definition.description,
        placeholder: definition.placeholder,
        options: definition.options?.map((option) => ({ ...option })),
        min: definition.min,
        max: definition.max,
        step: definition.step,
        units: definition.units,
        accepts: definition.accepts,
        responsive: definition.responsive === true,
        chat_editable: definition.chatEditable !== false,
        binding_compatible: definition.bindingCompatible !== false,
        project_types: definition.projectTypes,
        interaction_states: definition.states,
        item_fields: definition.itemFields?.map((fieldDefinition) => serializeFieldDefinition(fieldDefinition)),
    };
}

/** Features section schema with nested itemFields for repeater editing. */
const FEATURES_BUILDER_SCHEMA: BuilderComponentSchema = {
    schemaVersion: 2,
    componentKey: 'webu_general_features_01',
    displayName: 'Features',
    category: 'sections',
    icon: 'grid',
    responsive: true,
    defaultProps: { ...FEATURES_DEFAULTS },
    chatTargets: ['title', 'items', 'variant', 'backgroundColor', 'textColor'],
    fields: [
        field('title', 'Section title', 'text', 'content', { default: FEATURES_DEFAULTS.title }),
        field('items', 'Feature items', 'repeater', 'content', {
            default: FEATURES_DEFAULTS.items,
            itemFields: [
                field('icon', 'Icon', 'icon', 'content', { default: '' }),
                field('title', 'Title', 'text', 'content', { default: '' }),
                field('description', 'Description', 'richtext', 'content', { default: '' }),
            ],
        }),
        field('variant', 'Design variant', 'layout-variant', 'layout', { default: 'features-1' }),
        field('backgroundColor', 'Background', 'color', 'style', { default: '' }),
        field('textColor', 'Text color', 'color', 'style', { default: '' }),
    ],
};

/** Cards section schema with nested itemFields for repeater editing. */
const CARDS_BUILDER_SCHEMA: BuilderComponentSchema = {
    schemaVersion: 2,
    componentKey: 'webu_general_cards_01',
    displayName: 'Cards',
    category: 'sections',
    icon: 'grid',
    responsive: true,
    defaultProps: { ...CARDS_DEFAULTS },
    chatTargets: ['title', 'items', 'variant', 'backgroundColor', 'textColor'],
    fields: [
        field('title', 'Section title', 'text', 'content', { default: CARDS_DEFAULTS.title }),
        field('items', 'Cards', 'repeater', 'content', {
            default: CARDS_DEFAULTS.items,
            itemFields: [
                field('image', 'Image', 'image', 'content', { default: '' }),
                field('imageAlt', 'Image alt', 'text', 'content', { default: '' }),
                field('title', 'Title', 'text', 'content', { default: '' }),
                field('description', 'Description', 'richtext', 'content', { default: '' }),
                field('link', 'Link', 'link', 'content', { default: '#' }),
            ],
        }),
        field('variant', 'Design variant', 'layout-variant', 'layout', { default: 'cards-1' }),
        field('backgroundColor', 'Background', 'color', 'style', { default: '' }),
        field('textColor', 'Text color', 'color', 'style', { default: '' }),
    ],
};

/** Grid section schema with nested itemFields for repeater editing. */
const GRID_BUILDER_SCHEMA: BuilderComponentSchema = {
    schemaVersion: 2,
    componentKey: 'webu_general_grid_01',
    displayName: 'Grid',
    category: 'sections',
    icon: 'grid',
    responsive: true,
    defaultProps: { ...GRID_DEFAULTS },
    chatTargets: ['title', 'items', 'columns', 'variant', 'backgroundColor', 'textColor'],
    fields: [
        field('title', 'Section title', 'text', 'content', { default: GRID_DEFAULTS.title }),
        field('items', 'Grid items', 'repeater', 'content', {
            default: GRID_DEFAULTS.items,
            itemFields: [
                field('image', 'Image', 'image', 'content', { default: '' }),
                field('imageAlt', 'Image alt', 'text', 'content', { default: '' }),
                field('title', 'Title', 'text', 'content', { default: '' }),
                field('link', 'Link', 'link', 'content', { default: '#' }),
            ],
        }),
        field('columns', 'Columns', 'number', 'layout', { default: GRID_DEFAULTS.columns, min: 1, max: 6 }),
        field('variant', 'Design variant', 'layout-variant', 'layout', { default: 'grid-1' }),
        field('backgroundColor', 'Background', 'color', 'style', { default: '' }),
        field('textColor', 'Text color', 'color', 'style', { default: '' }),
    ],
};

/** Heading section schema — full content model. */
const HEADING_BUILDER_SCHEMA: BuilderComponentSchema = {
    schemaVersion: 2,
    componentKey: 'webu_general_heading_01',
    displayName: 'Heading',
    category: 'content',
    icon: 'heading',
    responsive: true,
    defaultProps: { eyebrow: '', headline: 'New heading', title: 'New heading', subtitle: '', description: '', color: '#111111', background_color: '#ffffff' },
    chatTargets: ['eyebrow', 'headline', 'title', 'subtitle', 'description'],
    fields: [
        field('eyebrow', 'Eyebrow / Badge', 'text', 'content', { default: '' }),
        field('headline', 'Heading', 'text', 'content', { default: 'New heading' }),
        field('title', 'Title (alias)', 'text', 'content', { default: 'New heading' }),
        field('subtitle', 'Subtitle', 'text', 'content', { default: '' }),
        field('description', 'Description', 'richtext', 'content', { default: '' }),
        field('color', 'Text color', 'color', 'style', { default: '#111111' }),
        field('background_color', 'Background', 'color', 'style', { default: '#ffffff' }),
    ],
};

/** Text section schema — full content model. */
const TEXT_BUILDER_SCHEMA: BuilderComponentSchema = {
    schemaVersion: 2,
    componentKey: 'webu_general_text_01',
    displayName: 'Text',
    category: 'content',
    icon: 'text',
    responsive: true,
    defaultProps: { body: 'Add your text here.', color: '#374151', background_color: '#ffffff' },
    chatTargets: ['body'],
    fields: [
        field('body', 'Text', 'richtext', 'content', { default: 'Add your text here.' }),
        field('color', 'Text color', 'color', 'style', { default: '#374151' }),
        field('background_color', 'Background', 'color', 'style', { default: '#ffffff' }),
    ],
};

/** Image section schema — full content model. */
const IMAGE_BUILDER_SCHEMA: BuilderComponentSchema = {
    schemaVersion: 2,
    componentKey: 'webu_general_image_01',
    displayName: 'Image',
    category: 'content',
    icon: 'image',
    responsive: true,
    defaultProps: { image_url: '', image_alt: 'Image', image_link: '' },
    chatTargets: ['image_url', 'image_alt', 'image_link', 'caption'],
    fields: [
        field('image_url', 'Image URL', 'image', 'content', { default: '' }),
        field('image_alt', 'Alt text', 'text', 'content', { default: 'Image' }),
        field('image_link', 'Link URL', 'link', 'content', { default: '' }),
        field('caption', 'Caption', 'text', 'content', { default: '' }),
    ],
};

/** Button section schema — full content model. */
const BUTTON_BUILDER_SCHEMA: BuilderComponentSchema = {
    schemaVersion: 2,
    componentKey: 'webu_general_button_01',
    displayName: 'Button',
    category: 'content',
    icon: 'mouse-pointer',
    responsive: true,
    defaultProps: { button: 'Click here', button_url: '#', color: '#ffffff', background_color: '#111111' },
    chatTargets: ['button', 'button_url'],
    fields: [
        field('button', 'Button label', 'text', 'content', { default: 'Click here' }),
        field('button_url', 'Button URL', 'link', 'content', { default: '#' }),
        field('color', 'Text color', 'color', 'style', { default: '#ffffff' }),
        field('background_color', 'Background', 'color', 'style', { default: '#111111' }),
    ],
};

/** Spacer section schema — layout model. */
const SPACER_BUILDER_SCHEMA: BuilderComponentSchema = {
    schemaVersion: 2,
    componentKey: 'webu_general_spacer_01',
    displayName: 'Spacer',
    category: 'layout',
    icon: 'space',
    responsive: true,
    defaultProps: { height: 56, background_color: '#ffffff' },
    chatTargets: ['height'],
    fields: [
        field('height', 'Height (px)', 'number', 'layout', { default: 56, min: 24, max: 240 }),
        field('background_color', 'Background', 'color', 'style', { default: '#ffffff' }),
    ],
};

/** Section container schema — layout model. */
const SECTION_BUILDER_SCHEMA: BuilderComponentSchema = {
    schemaVersion: 2,
    componentKey: 'webu_general_section_01',
    displayName: 'Section',
    category: 'layout',
    icon: 'layout',
    responsive: true,
    defaultProps: { title: 'Section Title', body: '', background_color: '#f8fafc', text_color: '#111827', padding_y: 32, max_width: 1200 },
    chatTargets: ['title', 'body'],
    fields: [
        field('title', 'Section title', 'text', 'content', { default: 'Section Title' }),
        field('body', 'Description', 'richtext', 'content', { default: '' }),
        field('background_color', 'Background', 'color', 'style', { default: '#f8fafc' }),
        field('text_color', 'Text color', 'color', 'style', { default: '#111827' }),
        field('padding_y', 'Vertical padding (px)', 'number', 'layout', { default: 32, min: 0, max: 120 }),
        field('max_width', 'Max width (px)', 'number', 'layout', { default: 1200, min: 320, max: 1920 }),
    ],
};

/** Card section schema — full content model. */
const CARD_BUILDER_SCHEMA: BuilderComponentSchema = {
    schemaVersion: 2,
    componentKey: 'webu_general_card_01',
    displayName: 'Card',
    category: 'content',
    icon: 'square',
    responsive: true,
    defaultProps: { title: 'Card title', body: 'Card content.', image_url: '', image_alt: '', link_url: '', linkLabel: 'Read more' },
    chatTargets: ['title', 'body', 'image_url', 'image_alt', 'link_url', 'linkLabel'],
    fields: [
        field('title', 'Title', 'text', 'content', { default: 'Card title' }),
        field('body', 'Body', 'richtext', 'content', { default: 'Card content.' }),
        field('image_url', 'Image', 'image', 'content', { default: '' }),
        field('image_alt', 'Image alt', 'text', 'content', { default: '' }),
        field('link_url', 'Link URL', 'link', 'content', { default: '' }),
        field('linkLabel', 'Link label', 'text', 'content', { default: 'Read more' }),
    ],
};

/** Form section schema — full content model. */
const FORM_BUILDER_SCHEMA: BuilderComponentSchema = {
    schemaVersion: 2,
    componentKey: 'webu_general_form_wrapper_01',
    displayName: 'Form',
    category: 'content',
    icon: 'file-input',
    responsive: true,
    defaultProps: {
        title: 'Contact us',
        subtitle: '',
        submit_label: 'Submit',
        namePlaceholder: 'Your name',
        emailPlaceholder: 'Email address',
        messagePlaceholder: 'Project details',
    },
    chatTargets: ['title', 'subtitle', 'submit_label', 'namePlaceholder', 'emailPlaceholder', 'messagePlaceholder'],
    fields: [
        field('title', 'Form title', 'text', 'content', { default: 'Contact us' }),
        field('subtitle', 'Subtitle / description', 'richtext', 'content', { default: '' }),
        field('submit_label', 'Submit button', 'text', 'content', { default: 'Submit' }),
        field('namePlaceholder', 'Name placeholder', 'text', 'content', { default: 'Your name' }),
        field('emailPlaceholder', 'Email placeholder', 'text', 'content', { default: 'Email address' }),
        field('messagePlaceholder', 'Message placeholder', 'text', 'content', { default: 'Project details' }),
    ],
};

/** Newsletter section schema — full content model. */
const NEWSLETTER_BUILDER_SCHEMA: BuilderComponentSchema = {
    schemaVersion: 2,
    componentKey: 'webu_general_newsletter_01',
    displayName: 'Newsletter',
    category: 'marketing',
    icon: 'mail',
    responsive: true,
    defaultProps: { title: 'Subscribe', subtitle: '', buttonText: 'Subscribe', placeholder: 'Your email' },
    chatTargets: ['title', 'subtitle', 'buttonText', 'placeholder'],
    fields: [
        field('title', 'Title', 'text', 'content', { default: 'Subscribe' }),
        field('subtitle', 'Subtitle', 'richtext', 'content', { default: '' }),
        field('buttonText', 'Button text', 'text', 'content', { default: 'Subscribe' }),
        field('placeholder', 'Email placeholder', 'text', 'content', { default: 'Your email' }),
    ],
};

/** Navigation section schema — full content model with menu itemFields. */
const NAVIGATION_BUILDER_SCHEMA: BuilderComponentSchema = {
    schemaVersion: 2,
    componentKey: 'webu_general_navigation_01',
    displayName: 'Navigation',
    category: 'layout',
    icon: 'menu',
    responsive: true,
    defaultProps: { links: [], ariaLabel: 'Navigation', variant: 'navigation-1', alignment: 'left' },
    chatTargets: ['links', 'ariaLabel'],
    fields: [
        field('links', 'Links', 'menu', 'content', {
            default: [],
            itemFields: [
                field('label', 'Label', 'text', 'content', { default: '' }),
                field('url', 'URL', 'link', 'content', { default: '#' }),
            ],
        }),
        field('ariaLabel', 'Aria label', 'text', 'content', { default: 'Navigation' }),
        field('variant', 'Design variant', 'layout-variant', 'layout', { default: 'navigation-1' }),
        field('alignment', 'Alignment', 'alignment', 'layout', { default: 'left' }),
        field('backgroundColor', 'Background', 'color', 'style', { default: '' }),
        field('textColor', 'Text color', 'color', 'style', { default: '' }),
    ],
};

/** CTA section schema — full content model. */
const CTA_BUILDER_SCHEMA: BuilderComponentSchema = {
    schemaVersion: 2,
    componentKey: 'webu_general_cta_01',
    displayName: 'CTA',
    category: 'marketing',
    icon: 'zap',
    responsive: true,
    defaultProps: { title: 'Ready to start?', subtitle: '', buttonText: 'Get started', buttonLink: '#' },
    chatTargets: ['title', 'subtitle', 'buttonText', 'buttonLink'],
    fields: [
        field('title', 'Title', 'text', 'content', { default: 'Ready to start?' }),
        field('subtitle', 'Subtitle', 'richtext', 'content', { default: '' }),
        field('buttonText', 'Button text', 'text', 'content', { default: 'Get started' }),
        field('buttonLink', 'Button link', 'link', 'content', { default: '#' }),
        field('backgroundColor', 'Background', 'color', 'style', { default: '' }),
        field('textColor', 'Text color', 'color', 'style', { default: '' }),
    ],
};

/** Video section schema — full content model. */
const VIDEO_BUILDER_SCHEMA: BuilderComponentSchema = {
    schemaVersion: 2,
    componentKey: 'webu_general_video_01',
    displayName: 'Video',
    category: 'content',
    icon: 'video',
    responsive: true,
    defaultProps: { video_url: '', caption: '' },
    chatTargets: ['video_url', 'caption'],
    fields: [
        field('video_url', 'Video URL', 'video', 'content', { default: '' }),
        field('caption', 'Caption', 'text', 'content', { default: '' }),
    ],
};

/** Product Grid schema — ecommerce content model. */
const PRODUCT_GRID_BUILDER_SCHEMA: BuilderComponentSchema = {
    schemaVersion: 2,
    componentKey: 'webu_ecom_product_grid_01',
    displayName: 'Product Grid',
    category: 'ecommerce',
    icon: 'grid-3x3',
    responsive: true,
    defaultProps: {
        title: 'Products',
        subtitle: '',
        add_to_cart_label: 'Add to cart',
        cta_label: 'View item',
        productCount: 12,
        columns_desktop: 4,
    },
    chatTargets: ['title', 'subtitle', 'add_to_cart_label', 'cta_label', 'productCount'],
    fields: [
        field('title', 'Title', 'text', 'content', { default: 'Products' }),
        field('subtitle', 'Subtitle', 'text', 'content', { default: '' }),
        field('add_to_cart_label', 'Add to cart label', 'text', 'content', { default: 'Add to cart' }),
        field('cta_label', 'CTA label', 'text', 'content', { default: 'View item' }),
        field('productCount', 'Product count', 'number', 'content', { default: 12, min: 1, max: 48 }),
        field('columns_desktop', 'Columns (desktop)', 'number', 'layout', { default: 4, min: 2, max: 6 }),
    ],
};

/** Cart Page schema — ecommerce content model. */
const CART_PAGE_BUILDER_SCHEMA: BuilderComponentSchema = {
    schemaVersion: 2,
    componentKey: 'webu_ecom_cart_page_01',
    displayName: 'Cart',
    category: 'ecommerce',
    icon: 'shopping-cart',
    responsive: true,
    defaultProps: { title: 'Your cart', emptyMessage: 'Your cart is empty.' },
    chatTargets: ['title', 'emptyMessage'],
    fields: [
        field('title', 'Title', 'text', 'content', { default: 'Your cart' }),
        field('emptyMessage', 'Empty message', 'text', 'content', { default: 'Your cart is empty.' }),
    ],
};

const REGISTRY: Record<string, ComponentDefinition> = {
    webu_general_hero_01: {
        id: 'webu_general_hero_01',
        name: 'Hero Section',
        category: 'sections',
        parameters: {
            title: { type: 'string', default: 'Welcome', title: 'Title' },
            subtitle: { type: 'string', default: '', title: 'Subtitle' },
            buttonText: { type: 'string', default: 'Get started', title: 'Button text' },
            buttonLink: { type: 'url', default: '#', title: 'Button link' },
            backgroundImage: { type: 'image', title: 'Background image' },
        },
        schema: HERO_SCHEMA,
        metadata: { icon: 'hero', supportsSpacing: true, supportsBackground: true, supportsAnimation: true },
    },
    webu_general_heading_01: {
        id: 'webu_general_heading_01',
        name: 'Heading',
        category: 'content',
        parameters: {
            eyebrow: { type: 'string', default: '', title: 'Eyebrow / Badge' },
            headline: { type: 'string', default: 'New heading', title: 'Heading' },
            title: { type: 'string', default: 'New heading', title: 'Title (alias)' },
            subtitle: { type: 'string', default: '', title: 'Subtitle' },
            description: { type: 'string', default: '', title: 'Description' },
            color: { type: 'string', default: '#111111', title: 'Text color' },
            background_color: { type: 'string', default: '#ffffff', title: 'Background' },
        },
        schema: HEADING_BUILDER_SCHEMA,
        metadata: { icon: 'heading', supportsSpacing: true, supportsBackground: true },
    },
    webu_general_text_01: {
        id: 'webu_general_text_01',
        name: 'Text',
        category: 'content',
        parameters: {
            body: { type: 'richtext', default: 'Add your text here.', title: 'Text' },
            color: { type: 'string', default: '#374151', title: 'Text color' },
            background_color: { type: 'string', default: '#ffffff', title: 'Background' },
        },
        schema: TEXT_BUILDER_SCHEMA,
        metadata: { icon: 'text', supportsSpacing: true, supportsBackground: true },
    },
    webu_general_image_01: {
        id: 'webu_general_image_01',
        name: 'Image',
        category: 'content',
        parameters: {
            image_url: { type: 'image', default: '', title: 'Image URL' },
            image_alt: { type: 'string', default: 'Image', title: 'Alt text' },
            image_link: { type: 'url', default: '', title: 'Link URL' },
            caption: { type: 'string', default: '', title: 'Caption' },
        },
        schema: IMAGE_BUILDER_SCHEMA,
        metadata: { icon: 'image', supportsSpacing: true },
    },
    webu_general_button_01: {
        id: 'webu_general_button_01',
        name: 'Button',
        category: 'content',
        parameters: {
            button: { type: 'string', default: 'Click here', title: 'Button label' },
            button_url: { type: 'url', default: '#', title: 'Button URL' },
            color: { type: 'string', default: '#ffffff', title: 'Text color' },
            background_color: { type: 'string', default: '#111111', title: 'Background' },
        },
        schema: BUTTON_BUILDER_SCHEMA,
        metadata: { icon: 'mouse-pointer', supportsSpacing: true },
    },
    webu_general_spacer_01: {
        id: 'webu_general_spacer_01',
        name: 'Spacer',
        category: 'layout',
        parameters: {
            height: { type: 'number', default: 56, title: 'Height (px)' },
            background_color: { type: 'string', default: '#ffffff', title: 'Background' },
        },
        schema: SPACER_BUILDER_SCHEMA,
        metadata: { icon: 'space', supportsSpacing: false },
    },
    webu_general_section_01: {
        id: 'webu_general_section_01',
        name: 'Section',
        category: 'layout',
        parameters: {
            title: { type: 'string', default: 'Section Title', title: 'Section title' },
            body: { type: 'string', default: '', title: 'Description' },
            background_color: { type: 'string', default: '#f8fafc', title: 'Background' },
            text_color: { type: 'string', default: '#111827', title: 'Text color' },
            padding_y: { type: 'number', default: 32, title: 'Vertical padding (px)' },
            max_width: { type: 'number', default: 1200, title: 'Max width (px)' },
        },
        schema: SECTION_BUILDER_SCHEMA,
        metadata: { icon: 'layout', supportsSpacing: true, supportsBackground: true, supportsPadding: true },
    },
    webu_general_newsletter_01: {
        id: 'webu_general_newsletter_01',
        name: 'Newsletter',
        category: 'marketing',
        parameters: {
            title: { type: 'string', default: 'Subscribe', title: 'Title' },
            subtitle: { type: 'string', default: '', title: 'Subtitle' },
            buttonText: { type: 'string', default: 'Subscribe', title: 'Button text' },
            placeholder: { type: 'string', default: 'Your email', title: 'Email placeholder' },
        },
        schema: NEWSLETTER_BUILDER_SCHEMA,
        metadata: { icon: 'mail', supportsSpacing: true, supportsBackground: true },
    },
    webu_general_cta_01: {
        id: 'webu_general_cta_01',
        name: 'CTA',
        category: 'marketing',
        parameters: {
            title: { type: 'string', default: 'Ready to start?', title: 'Title' },
            subtitle: { type: 'string', default: '', title: 'Subtitle' },
            buttonText: { type: 'string', default: 'Get started', title: 'Button text' },
            buttonLink: { type: 'url', default: '#', title: 'Button link' },
        },
        schema: CTA_BUILDER_SCHEMA,
        metadata: { icon: 'zap', supportsSpacing: true, supportsBackground: true },
    },
    webu_general_features_01: {
        id: 'webu_general_features_01',
        name: 'Features',
        category: 'sections',
        parameters: {
            title: { type: 'string', default: 'Features', title: 'Section title' },
            items: { type: 'collection', default: '[]', title: 'Feature items' },
            variant: { type: 'string', default: 'features-1', title: 'Design variant' },
            backgroundColor: { type: 'string', default: '', title: 'Background' },
            textColor: { type: 'string', default: '', title: 'Text color' },
            padding: { type: 'string', default: '', title: 'Padding' },
            spacing: { type: 'string', default: '', title: 'Spacing' },
        },
        schema: FEATURES_BUILDER_SCHEMA,
        metadata: { icon: 'grid', supportsSpacing: true, supportsBackground: true },
    },
    webu_general_cards_01: {
        id: 'webu_general_cards_01',
        name: 'Cards',
        category: 'sections',
        parameters: {
            title: { type: 'string', default: 'Cards', title: 'Section title' },
            items: { type: 'collection', default: '[]', title: 'Cards' },
            variant: { type: 'string', default: 'cards-1', title: 'Design variant' },
            backgroundColor: { type: 'string', default: '', title: 'Background' },
            textColor: { type: 'string', default: '', title: 'Text color' },
            padding: { type: 'string', default: '', title: 'Padding' },
            spacing: { type: 'string', default: '', title: 'Spacing' },
        },
        schema: CARDS_BUILDER_SCHEMA,
        metadata: { icon: 'grid', supportsSpacing: true, supportsBackground: true, supportsBorderRadius: true },
    },
    webu_general_grid_01: {
        id: 'webu_general_grid_01',
        name: 'Grid',
        category: 'sections',
        parameters: {
            title: { type: 'string', default: 'Grid', title: 'Section title' },
            items: { type: 'collection', default: '[]', title: 'Grid items' },
            columns: { type: 'number', default: 3, title: 'Columns' },
            variant: { type: 'string', default: 'grid-1', title: 'Design variant' },
            backgroundColor: { type: 'string', default: '', title: 'Background' },
            textColor: { type: 'string', default: '', title: 'Text color' },
            padding: { type: 'string', default: '', title: 'Padding' },
            spacing: { type: 'string', default: '', title: 'Spacing' },
        },
        schema: GRID_BUILDER_SCHEMA,
        metadata: { icon: 'grid', supportsSpacing: true, supportsBackground: true },
    },
    webu_general_navigation_01: {
        id: 'webu_general_navigation_01',
        name: 'Navigation',
        category: 'layout',
        parameters: {
            links: { type: 'collection', default: '[]', title: 'Links' },
            ariaLabel: { type: 'string', default: 'Navigation', title: 'Aria label' },
            variant: { type: 'string', default: 'navigation-1', title: 'Design variant' },
            alignment: { type: 'string', default: 'left', title: 'Alignment' },
            backgroundColor: { type: 'string', default: '', title: 'Background' },
            textColor: { type: 'string', default: '', title: 'Text color' },
            padding: { type: 'string', default: '', title: 'Padding' },
            spacing: { type: 'string', default: '', title: 'Spacing' },
        },
        schema: NAVIGATION_BUILDER_SCHEMA,
        metadata: { icon: 'menu', supportsSpacing: true, supportsBackground: true },
    },
    webu_general_card_01: {
        id: 'webu_general_card_01',
        name: 'Card',
        category: 'content',
        parameters: {
            title: { type: 'string', default: 'Card title', title: 'Title' },
            body: { type: 'string', default: 'Card content.', title: 'Body' },
            image_url: { type: 'image', title: 'Image' },
            image_alt: { type: 'string', default: '', title: 'Image alt' },
            link_url: { type: 'url', default: '', title: 'Link URL' },
            linkLabel: { type: 'string', default: 'Read more', title: 'Link label' },
            buttonText: { type: 'string', default: 'Read more', title: 'Button label (alias)' },
        },
        schema: CARD_BUILDER_SCHEMA,
        metadata: { icon: 'square', supportsSpacing: true, supportsBackground: true, supportsBorderRadius: true },
    },
    webu_general_form_wrapper_01: {
        id: 'webu_general_form_wrapper_01',
        name: 'Form',
        category: 'content',
        parameters: {
            title: { type: 'string', default: 'Contact us', title: 'Form title' },
            subtitle: { type: 'string', default: '', title: 'Subtitle / description' },
            submit_label: { type: 'string', default: 'Submit', title: 'Submit button' },
            namePlaceholder: { type: 'string', default: 'Your name', title: 'Name placeholder' },
            emailPlaceholder: { type: 'string', default: 'Email address', title: 'Email placeholder' },
            messagePlaceholder: { type: 'string', default: 'Project details', title: 'Message placeholder' },
        },
        schema: FORM_BUILDER_SCHEMA,
        metadata: { icon: 'file-input', supportsSpacing: true, supportsBackground: true },
    },
    webu_ecom_product_grid_01: {
        id: 'webu_ecom_product_grid_01',
        name: 'Product Grid',
        category: 'ecommerce',
        parameters: {
            title: { type: 'string', default: 'Products', title: 'Title' },
            subtitle: { type: 'string', default: '', title: 'Subtitle' },
            productsSource: { type: 'collection', title: 'Products source' },
            productCount: { type: 'number', default: 12, title: 'Product count' },
            products_per_page: { type: 'number', default: 24, title: 'Products per page' },
            add_to_cart_label: { type: 'string', default: 'Add to cart', title: 'Add to cart label' },
            cta_label: { type: 'string', default: 'View item', title: 'CTA label (alias)' },
            show_filters: { type: 'boolean', default: false, title: 'Show filters' },
            show_sort: { type: 'boolean', default: false, title: 'Show sort' },
            columns_desktop: { type: 'number', default: 4, title: 'Columns (desktop)' },
            layout_style: { type: 'string', default: 'grid', title: 'Layout style', enum: ['grid', 'list'] },
        },
        schema: PRODUCT_GRID_BUILDER_SCHEMA,
        metadata: { icon: 'grid-3x3', supportsSpacing: true, supportsBackground: true },
    },
    webu_ecom_featured_categories_01: {
        id: 'webu_ecom_featured_categories_01',
        name: 'Featured Categories',
        category: 'ecommerce',
        parameters: {
            title: { type: 'string', default: 'Categories', title: 'Title' },
            subtitle: { type: 'string', default: '', title: 'Subtitle' },
            categoriesSource: { type: 'collection', title: 'Categories source' },
            cta_label: { type: 'string', default: 'View category', title: 'CTA label' },
        },
        metadata: { icon: 'layers', supportsSpacing: true, supportsBackground: true },
    },
    webu_ecom_category_list_01: {
        id: 'webu_ecom_category_list_01',
        name: 'Category List',
        category: 'ecommerce',
        parameters: {
            title: { type: 'string', default: 'Shop by category', title: 'Title' },
            subtitle: { type: 'string', default: '', title: 'Subtitle' },
        },
        metadata: { icon: 'list', supportsSpacing: true, supportsBackground: true },
    },
    webu_ecom_cart_page_01: {
        id: 'webu_ecom_cart_page_01',
        name: 'Cart',
        category: 'ecommerce',
        parameters: {
            title: { type: 'string', default: 'Your cart', title: 'Title' },
            emptyMessage: { type: 'string', default: 'Your cart is empty.', title: 'Empty message' },
        },
        schema: CART_PAGE_BUILDER_SCHEMA,
        metadata: { icon: 'shopping-cart', supportsSpacing: true },
    },
    webu_ecom_product_detail_01: {
        id: 'webu_ecom_product_detail_01',
        name: 'Product Detail',
        category: 'ecommerce',
        parameters: {
            add_to_cart_label: { type: 'string', default: 'Add to cart', title: 'Add to cart label' },
            quantity_label: { type: 'string', default: 'Quantity', title: 'Quantity label' },
        },
        metadata: { icon: 'package', supportsSpacing: true },
    },
    webu_general_video_01: {
        id: 'webu_general_video_01',
        name: 'Video',
        category: 'content',
        parameters: {
            video_url: { type: 'url', default: '', title: 'Video URL' },
            caption: { type: 'string', default: '', title: 'Caption' },
        },
        schema: VIDEO_BUILDER_SCHEMA,
        metadata: { icon: 'video', supportsSpacing: true },
    },
    webu_general_testimonials_01: {
        id: 'webu_general_testimonials_01',
        name: 'Testimonials',
        category: 'sections',
        parameters: {
            title: { type: 'string', default: 'Testimonials', title: 'Section title' },
            items: { type: 'collection', default: '[]', title: 'Testimonial items' },
            variant: { type: 'string', default: 'testimonials-1', title: 'Design variant' },
        },
        metadata: { icon: 'message-square', supportsSpacing: true, supportsBackground: true },
    },
    faq_accordion_plus: {
        id: 'faq_accordion_plus',
        name: 'FAQ',
        category: 'sections',
        parameters: {
            title: { type: 'string', default: 'FAQ', title: 'Section title' },
            items: { type: 'collection', default: '[]', title: 'FAQ items' },
            variant: { type: 'string', default: 'faq-1', title: 'Design variant' },
        },
        metadata: { icon: 'help-circle', supportsSpacing: true, supportsBackground: true },
    },
    webu_general_banner_01: {
        id: 'webu_general_banner_01',
        name: 'Banner',
        category: 'marketing',
        parameters: {
            title: { type: 'string', default: 'Banner title', title: 'Title' },
            subtitle: { type: 'string', default: '', title: 'Subtitle' },
            cta_label: { type: 'string', default: 'Learn more', title: 'Button label' },
            cta_url: { type: 'url', default: '#', title: 'Button URL' },
        },
        metadata: { icon: 'layout', supportsSpacing: true, supportsBackground: true },
    },
    webu_general_offcanvas_menu_01: {
        id: 'webu_general_offcanvas_menu_01',
        name: 'Offcanvas Menu',
        category: 'layout',
        parameters: {
            trigger_label: { type: 'string', default: 'Open menu', title: 'Trigger label' },
            title: { type: 'string', default: 'Shop navigation', title: 'Panel title' },
            subtitle: { type: 'string', default: '', title: 'Panel subtitle' },
            menu_items: { type: 'collection', default: '[]', title: 'Menu items' },
            footer_label: { type: 'string', default: 'Shop all', title: 'Footer CTA label' },
            footer_url: { type: 'url', default: '/shop', title: 'Footer CTA URL' },
        },
        metadata: { icon: 'menu', supportsSpacing: true },
    },
    webu_header_01: {
        id: 'webu_header_01',
        name: 'Header',
        category: 'header',
        parameters: {
            logo_url: { type: 'image', title: 'Logo' },
            logo_alt: { type: 'string', default: '', title: 'Logo alt' },
            menu_items: { type: 'string', title: 'Menu (JSON)' },
            ctaText: { type: 'string', default: 'Get started', title: 'CTA button text' },
            ctaLink: { type: 'url', default: '#', title: 'CTA button link' },
        },
        schema: HEADER_SCHEMA,
        metadata: { icon: 'layout', supportsSpacing: false },
    },
    webu_footer_01: {
        id: 'webu_footer_01',
        name: 'Footer',
        category: 'footer',
        parameters: {
            copyright: { type: 'string', default: '© 2024', title: 'Copyright' },
            links: { type: 'string', title: 'Links (JSON)' },
            socialLinks: { type: 'string', title: 'Social links (JSON)' },
        },
        schema: FOOTER_SCHEMA,
        metadata: { icon: 'layout', supportsSpacing: false },
    },
};

const EXPLICIT_GOVERNANCE_CATEGORY_BY_ID: Partial<Record<string, BuilderGovernanceCategory>> = {
    webu_general_newsletter_01: 'marketing',
    webu_general_banner_01: 'landing',
};

const PROJECT_SITE_ALLOWED_CATEGORIES: Record<ProjectSiteType, readonly BuilderGovernanceCategory[]> = {
    ecommerce: ['ecommerce', 'general'],
    booking: ['booking', 'general'],
    landing: ['landing', 'marketing', 'general'],
    website: ['landing', 'marketing', 'blog', 'general'],
};

/**
 * CMS/live-preview payloads often use short section types instead of canonical builder ids.
 * Resolve those aliases to the canonical registry entry so inspect/sidebar editing keeps working.
 */
const COMPONENT_LOOKUP_ALIASES: Record<string, string> = {
    header: 'webu_header_01',
    footer: 'webu_footer_01',
    hero: 'webu_general_hero_01',
    hero_split_image: 'webu_general_hero_01',
    hero_split: 'webu_general_hero_01',
    hero_banner: 'webu_general_hero_01',
    hero_section: 'webu_general_hero_01',
    heading: 'webu_general_heading_01',
    text: 'webu_general_text_01',
    content: 'webu_general_text_01',
    rich_text_block: 'webu_general_text_01',
    image: 'webu_general_image_01',
    button: 'webu_general_button_01',
    spacer: 'webu_general_spacer_01',
    section: 'webu_general_section_01',
    newsletter: 'webu_general_newsletter_01',
    cta: 'webu_general_cta_01',
    features: 'webu_general_features_01',
    services: 'webu_general_features_01',
    services_grid_cards: 'webu_general_features_01',
    cards: 'webu_general_cards_01',
    grid: 'webu_general_grid_01',
    testimonials: 'webu_general_testimonials_01',
};

/** Normalize section key for lookup (lowercase, trim). */
function normalizeKey(key: string): string {
    return key.trim().toLowerCase().replace(/-/g, '_');
}

function resolveComponentLookupKey(registryId: string): string {
    const normalizedKey = normalizeKey(registryId);
    return COMPONENT_LOOKUP_ALIASES[normalizedKey] ?? normalizedKey;
}

export function resolveComponentRegistryKey(registryId: string): string | null {
    const definition = getComponent(registryId);
    if (definition) {
        return definition.id;
    }

    const normalizedKey = resolveComponentLookupKey(registryId);
    return normalizedKey === '' ? null : normalizedKey;
}

/**
 * Get all available component IDs. Used by AI and builder panel.
 */
export function getAvailableComponents(): string[] {
    return Object.keys(REGISTRY);
}

/**
 * Get component definition by registry ID. Returns null if not registered.
 */
export function getComponent(registryId: string): ComponentDefinition | null {
    const key = resolveComponentLookupKey(registryId);
    return REGISTRY[key] ?? REGISTRY[registryId] ?? null;
}

function resolveGovernanceCategory(
    definition: ComponentDefinition,
    schema: BuilderComponentSchema,
): BuilderGovernanceCategory {
    if (definition.governanceCategory) {
        return definition.governanceCategory;
    }

    const explicitCategory = EXPLICIT_GOVERNANCE_CATEGORY_BY_ID[definition.id];
    if (explicitCategory) {
        return explicitCategory;
    }

    if (definition.id.startsWith('webu_ecom_') || schema.category === 'ecommerce') {
        return 'ecommerce';
    }

    if (definition.id.includes('booking') || schema.category === 'booking') {
        return 'booking';
    }

    if (definition.id.includes('blog') || schema.category === 'blog') {
        return 'blog';
    }

    return 'general';
}

export function getGovernedComponent(registryId: string): BuilderGovernedComponent | null {
    const definition = getComponent(registryId);
    if (!definition) {
        return null;
    }

    const schema = getComponentSchema(definition.id);
    if (!schema) {
        return null;
    }

    return {
        type: schema.componentKey,
        label: schema.displayName,
        category: resolveGovernanceCategory(definition, schema),
        schema,
    };
}

export function getGovernedComponentCatalog(): BuilderGovernedComponent[] {
    return getAvailableComponents()
        .map((registryId) => getGovernedComponent(registryId))
        .filter((entry): entry is BuilderGovernedComponent => entry !== null);
}

export function getAllowedComponents(projectType: ProjectSiteType | string): BuilderGovernedComponent[] {
    const projectSiteType = normalizeProjectSiteType(projectType);
    const allowedCategories = new Set(PROJECT_SITE_ALLOWED_CATEGORIES[projectSiteType]);

    return getGovernedComponentCatalog().filter((component) => allowedCategories.has(component.category));
}

export function isComponentAllowedForProjectSiteType(
    registryId: string,
    projectType: ProjectSiteType | string,
): boolean {
    const component = getGovernedComponent(registryId);
    if (!component) {
        return false;
    }

    const allowedCategories = new Set(PROJECT_SITE_ALLOWED_CATEGORIES[normalizeProjectSiteType(projectType)]);
    return allowedCategories.has(component.category);
}

/**
 * Get all unique categories in display order.
 */
export const REGISTRY_CATEGORY_ORDER: BuilderCategory[] = [
    'header',
    'sections',
    'content',
    'marketing',
    'ecommerce',
    'layout',
    'footer',
    'general',
    'booking',
    'blog',
    'portfolio',
];

export function getCategories(): BuilderCategory[] {
    const set = new Set<BuilderCategory>();
    Object.values(REGISTRY).forEach((def) => set.add(def.category));
    return REGISTRY_CATEGORY_ORDER.filter((c) => set.has(c));
}

/**
 * Get components grouped by category (for builder panel).
 */
export function getComponentsByCategory(): Array<{ category: BuilderCategory; components: ComponentDefinition[] }> {
    const byCategory = new Map<BuilderCategory, ComponentDefinition[]>();
    Object.values(REGISTRY).forEach((def) => {
        const list = byCategory.get(def.category) ?? [];
        list.push(def);
        byCategory.set(def.category, list);
    });
    return REGISTRY_CATEGORY_ORDER.filter((c) => byCategory.has(c)).map((category) => ({
        category,
        components: byCategory.get(category)!,
    }));
}

export function getComponentSchema(registryId: string): BuilderComponentSchema | null {
    const def = getComponent(registryId);
    if (!def) {
        return null;
    }

    return normalizeSchema(def);
}

export function getEditableFieldPaths(registryId: string): string[] {
    const schema = getComponentSchema(registryId);
    if (!schema) {
        return [];
    }

    return schema.fields
        .filter((fieldDefinition) => fieldDefinition.chatEditable !== false)
        .map((fieldDefinition) => fieldDefinition.path);
}

/**
 * Get editable parameter names for a component. Used by AI and parameter panel.
 */
export function getEditableParameters(registryId: string): string[] {
    const fieldPaths = getEditableFieldPaths(registryId);
    if (fieldPaths.length > 0) {
        return Array.from(new Set(fieldPaths.map((path) => path.split('.').filter(Boolean)[0]).filter(Boolean)));
    }

    const def = getComponent(registryId);
    if (!def) return [];
    return Object.keys(def.parameters);
}

/**
 * Get full parameter schema for a component (including advanced settings if metadata supports them).
 */
export function getParameterSchema(registryId: string): Record<string, ParameterSchema> {
    const def = getComponent(registryId);
    if (!def) return {};

    return buildParameterSchemaMap(def);
}

export function getDefaultProps(registryId: string): Record<string, unknown> {
    const schema = getComponentSchema(registryId);
    if (schema) {
        return { ...schema.defaultProps };
    }

    return buildDefaultPropsFromParameters(getParameterSchema(registryId));
}

export function getSupportedProjectTypes(registryId: string): BuilderProjectType[] {
    const entry = getComponentRuntimeEntry(registryId);
    return entry?.projectTypes ?? [];
}

export function getComponentRuntimeEntry(registryId: string): BuilderComponentRuntimeEntry | null {
    const def = getComponent(registryId);
    if (!def) {
        return null;
    }

    const cacheKey = def.id;
    const cached = runtimeEntryCache.get(cacheKey);
    if (cached) {
        return cached;
    }

    const schema = normalizeSchema(def);
    const entry: BuilderComponentRuntimeEntry = {
        componentKey: schema.componentKey,
        displayName: schema.displayName,
        category: schema.category,
        component: resolveCanvasComponent(def, schema),
        schema,
        defaults: { ...schema.defaultProps },
        projectTypes: [...(schema.projectTypes ?? [])],
        codegen: schema.codegen ?? null,
    };

    runtimeEntryCache.set(cacheKey, entry);

    return entry;
}

export function getComponentCanvasComponent(registryId: string): BuilderCanvasComponent | null {
    return getComponentRuntimeEntry(registryId)?.component ?? null;
}

export function resolveComponentProps(registryId: string, propsInput: unknown): Record<string, unknown> {
    const runtimeEntry = getComponentRuntimeEntry(registryId);
    const defaults = runtimeEntry?.defaults ?? getDefaultProps(registryId);
    const overrides = parseComponentProps(propsInput);
    const comparableSchemaPaths = collectSchemaComparablePropPaths(runtimeEntry?.schema ?? null);

    return mergeResolvedProps(defaults, overrides, comparableSchemaPaths);
}

export function getComponentSchemaJson(registryId: string): Record<string, unknown> | null {
    const schema = getComponentSchema(registryId);
    if (!schema) {
        return null;
    }

    const properties: Record<string, unknown> = {};
    schema.fields.forEach((fieldDefinition) => {
        setNestedSchemaProperty(properties, fieldDefinition.path, fieldDefinition);
    });

    return {
        schema_version: schema.schemaVersion ?? 2,
        type: 'object',
        component_key: schema.componentKey,
        display_name: schema.displayName,
        category: schema.category,
        icon: schema.icon ?? null,
        serializable: schema.serializable !== false,
        responsive: schema.responsive === true,
        responsive_support: schema.responsiveSupport ?? null,
        variants: schema.variants ?? {},
        variant_definitions: schema.variantDefinitions ?? [],
        codegen: schema.codegen ?? null,
        default_props: schema.defaultProps,
        editable_fields: schema.editableFields ?? [],
        chat_targets: schema.chatTargets ?? [],
        binding_fields: schema.bindingFields ?? [],
        project_types: schema.projectTypes ?? [],
        content_groups: schema.contentGroups ?? [],
        style_groups: schema.styleGroups ?? [],
        advanced_groups: schema.advancedGroups ?? [],
        fields: schema.fields.map((fieldDefinition) => serializeFieldDefinition(fieldDefinition)),
        properties,
        _meta: {
            schema_version: schema.schemaVersion ?? 2,
            label: schema.displayName,
            component_key: schema.componentKey,
            category: schema.category,
            icon: schema.icon ?? null,
            serializable: schema.serializable !== false,
            editable_fields: schema.editableFields ?? [],
            chat_targets: schema.chatTargets ?? [],
            binding_fields: schema.bindingFields ?? [],
            project_types: schema.projectTypes ?? [],
            content_groups: schema.contentGroups ?? [],
            style_groups: schema.styleGroups ?? [],
            advanced_groups: schema.advancedGroups ?? [],
            responsive_support: schema.responsiveSupport ?? null,
            variant_definitions: schema.variantDefinitions ?? [],
            codegen: schema.codegen ?? null,
        },
    };
}

export function getComponentCodegenMetadata(registryId: string): BuilderCodegenMetadata | null {
    return getComponentRuntimeEntry(registryId)?.codegen ?? null;
}

export function getComponentRegistryIdByCodegenTagName(tagName: string): string | null {
    const normalizedTagName = tagName.trim();
    if (normalizedTagName === '') {
        return null;
    }

    const entry = Object.keys(REGISTRY).find((registryId) => {
        const codegen = getComponentCodegenMetadata(registryId);
        return codegen?.tagName === normalizedTagName;
    });

    return entry ?? null;
}

export function getCodegenTagMapSnapshot(): Record<string, string> {
    return Object.fromEntries(
        Object.keys(REGISTRY)
            .map((registryId) => {
                const codegen = getComponentCodegenMetadata(registryId);
                return codegen?.tagName
                    ? [registryId, codegen.tagName]
                    : null;
            })
            .filter((entry): entry is [string, string] => entry !== null)
    );
}

/**
 * Check if a component ID is valid (exists in registry). AI must only add components that pass this.
 */
export function isValidComponent(registryId: string): boolean {
    return getComponent(registryId) !== null;
}

/**
 * Short display name for sidebar/cards: 1–2 words (e.g. "Account", "Product Grid").
 * Use in builder library so keys like webu_ecom_account_dashboard_01 show as "Account".
 */
export function getShortDisplayName(registryId: string, fallbackLabel: string): string {
    const def = getComponent(registryId);
    if (def?.name) {
        const words = def.name.trim().split(/\s+/);
        if (words.length <= 2) return def.name;
        return words[0]!;
    }
    const trimmed = fallbackLabel.trim();
    // If fallback looks like a translation key or raw section key (e.g. builder.section.xxx or webu_ecom_account_dashboard_01), derive a short name
    if (trimmed.includes('.') || /^webu_[a-z0-9_]+_\d{2}$/i.test(trimmed)) {
        const part = trimmed.includes('.') ? trimmed.split('.').pop()! : trimmed;
        const withoutSuffix = part.replace(/_\d{2}$/, '');
        const segments = withoutSuffix.split('_').filter(Boolean);
        // Skip "webu" and category (e.g. ecom, general); use rest for name (e.g. account_dashboard -> Account Dashboard)
        const nameParts = segments[0]?.toLowerCase() === 'webu' && segments.length > 2 ? segments.slice(2) : segments;
        const words = nameParts.slice(0, 2);
        const human = words.map((w) => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join(' ');
        return human || trimmed.slice(0, 12) + '…';
    }
    if (trimmed.length <= 14) return trimmed;
    const firstWords = trimmed.split(/\s+/).slice(0, 2).join(' ');
    return firstWords || trimmed.slice(0, 12) + '…';
}

/**
 * Registry as plain object (for serialization / API).
 */
export function getRegistrySnapshot(): Record<string, ComponentDefinition & {
    id: string;
    type?: string;
    label?: string;
    governance_category?: BuilderGovernanceCategory;
    schema_json?: Record<string, unknown>;
}> {
    return Object.fromEntries(
        Object.entries(REGISTRY).map(([id, def]) => {
            const governed = getGovernedComponent(id);

            return [id, {
                ...def,
                id,
                type: governed?.type ?? id,
                label: governed?.label ?? def.name,
                governance_category: governed?.category,
                schema: governed?.schema ?? getComponentSchema(id) ?? undefined,
                schema_json: getComponentSchemaJson(id) ?? undefined,
                codegen: getComponentCodegenMetadata(id) ?? undefined,
            }];
        })
    );
}

export const componentRegistry = {
    getAvailableComponents,
    getGovernedComponent,
    getGovernedComponentCatalog,
    getAllowedComponents,
    getComponent,
    getCategories,
    getComponentsByCategory,
    getComponentSchema,
    getComponentRuntimeEntry,
    getComponentCanvasComponent,
    getEditableFieldPaths,
    getEditableParameters,
    getParameterSchema,
    getDefaultProps,
    getSupportedProjectTypes,
    resolveComponentProps,
    getComponentSchemaJson,
    getComponentCodegenMetadata,
    getComponentRegistryIdByCodegenTagName,
    getCodegenTagMapSnapshot,
    isValidComponent,
    resolveComponentRegistryKey,
    getShortDisplayName,
    isComponentAllowedForProjectSiteType,
    getRegistrySnapshot,
    ADVANCED_SETTINGS,
    REGISTRY_CATEGORY_ORDER,
    BUILDER_GOVERNANCE_CATEGORIES,
};

/** Re-export central component registry (header, hero, footer with component, schema, defaults). */
export {
    componentRegistry as centralComponentRegistry,
    getCentralRegistryEntry,
    getRegistryKeyByComponentId,
    isInCentralRegistry,
    REGISTRY_ID_TO_KEY,
} from './centralComponentRegistry';

export type { ComponentRegistryEntry } from './centralComponentRegistry';

export default componentRegistry;
