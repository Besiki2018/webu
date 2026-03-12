/**
 * Shared builder types — single source of truth for the schema-driven architecture.
 * All registry, schema, canvas, sidebar, and update pipeline use these contracts.
 * Future components follow the same pattern.
 */

import type { BuilderSection } from './visual/treeUtils';
import type { BuilderUpdateOperation } from './state/updatePipeline';

// Re-export from componentRegistry (canonical definitions live there)
export type {
    BuilderFieldType,
    BuilderFieldGroup,
    BuilderFieldOption,
    BuilderFieldDefinition,
    BuilderFieldGroupDefinition,
    BuilderVariantDefinition,
    BuilderResponsiveSupport,
    BuilderComponentSchema,
    BuilderResponsiveBreakpoint,
    BuilderInteractionState,
    BuilderCategory,
    BuilderProjectType,
} from './componentRegistry';

export type { ComponentRegistryEntry as BuilderComponentRegistryEntry } from './centralComponentRegistry';

// ---------------------------------------------------------------------------
// Field group standard (content | style | advanced | responsive | state)
// Sidebar and schema use these; do not invent per-component grouping.
// ---------------------------------------------------------------------------
export const FIELD_GROUP_STANDARD = [
    'content',
    'style',
    'advanced',
    'responsive',
    'state',
] as const;

export type BuilderFieldGroupStandard = (typeof FIELD_GROUP_STANDARD)[number];

// ---------------------------------------------------------------------------
// BuilderComponentDefaults — defaults for a component (serializable)
// ---------------------------------------------------------------------------
export type BuilderComponentDefaults = Record<string, unknown>;

// ---------------------------------------------------------------------------
// BuilderComponentVariant — variant option (id + label)
// ---------------------------------------------------------------------------
export interface BuilderComponentVariant {
    value: string;
    label?: string;
}

// ---------------------------------------------------------------------------
// Serializable builder data model (Phase 6).
// Page/section/component instances use this structure. Canvas rendering depends
// only on this + registry definitions; no hidden JSX assumptions.
// ---------------------------------------------------------------------------
export interface BuilderSerializableInstance {
    id: string;
    componentKey: string;
    variant?: string;
    props: Record<string, unknown>;
    children?: BuilderSerializableInstance[];
    responsiveOverrides?: Record<string, Record<string, unknown>>;
    metadata?: Record<string, unknown>;
}

/** Alias: BuilderComponentInstance matches BuilderSerializableInstance (responsive = responsiveOverrides). */
export interface BuilderComponentInstance {
    id: string;
    componentKey: string;
    variant?: string;
    props: Record<string, unknown>;
    children?: BuilderComponentInstance[];
    /** Per-breakpoint overrides; same as responsiveOverrides in serialized form. */
    responsive?: Record<string, Record<string, unknown>>;
    metadata?: Record<string, unknown>;
}

/** Map BuilderSection to serializable instance (id, componentKey, variant, props, children, responsiveOverrides, metadata). */
export function sectionToComponentInstance(section: BuilderSection): BuilderComponentInstance {
    const props = section.props && typeof section.props === 'object' && !Array.isArray(section.props)
        ? section.props
        : (() => {
            try {
                const p = JSON.parse(section.propsText || '{}');
                return p && typeof p === 'object' && !Array.isArray(p) ? p : {};
            } catch {
                return {};
            }
        })();
    const variant = typeof props.variant === 'string' ? props.variant : undefined;
    const responsive =
        props.responsive && typeof props.responsive === 'object' && !Array.isArray(props.responsive)
            ? (props.responsive as Record<string, Record<string, unknown>>)
            : undefined;
    const rawSections: unknown[] = Array.isArray(props.sections) ? props.sections : [];
    const children = rawSections
        .filter((s): s is Record<string, unknown> => s != null && typeof s === 'object' && !Array.isArray(s))
        .map((s) => {
            const localId = typeof s.localId === 'string' ? s.localId : '';
            const type = typeof s.type === 'string' ? s.type : (typeof s.key === 'string' ? s.key : '');
            const nestedProps: Record<string, unknown> = s.props && typeof s.props === 'object' && !Array.isArray(s.props)
                ? (s.props as Record<string, unknown>)
                : {};
            const nestedVariant = typeof nestedProps.variant === 'string' ? nestedProps.variant : undefined;
            return {
                id: localId,
                componentKey: type,
                variant: nestedVariant,
                props: nestedProps,
                metadata: undefined,
            } as BuilderComponentInstance;
        });
    return {
        id: section.localId,
        componentKey: section.type,
        variant,
        props,
        children: children.length > 0 ? children : undefined,
        responsive,
        metadata: section.bindingMeta ?? undefined,
    };
}

/** Convert BuilderComponentInstance to BuilderSerializableInstance (canonical serialized shape with responsiveOverrides). */
export function toSerializableInstance(instance: BuilderComponentInstance): BuilderSerializableInstance {
    return {
        id: instance.id,
        componentKey: instance.componentKey,
        variant: instance.variant,
        props: instance.props,
        children: instance.children?.map(toSerializableInstance),
        responsiveOverrides: instance.responsive,
        metadata: instance.metadata,
    };
}

// ---------------------------------------------------------------------------
// ResponsiveValue<T> — value with optional per-breakpoint overrides
// ---------------------------------------------------------------------------
export type ResponsiveBreakpoint = 'desktop' | 'tablet' | 'mobile';

export interface ResponsiveValue<T = unknown> {
    /** Base value (applies when no breakpoint override) */
    base?: T;
    desktop?: T;
    tablet?: T;
    mobile?: T;
}

// ---------------------------------------------------------------------------
// BuilderUpdatePayload — payload for the unified update pipeline
// Sidebar and chat both use this; no chat-specific bypass.
// ---------------------------------------------------------------------------
export type BuilderUpdatePayload = BuilderUpdateOperation;
