import type {
    BuilderBreakpoint,
    BuilderEditableTarget,
    BuilderInteractionState,
} from '@/builder/editingState';

export interface SelectedTargetContext {
    page_id?: number | null;
    page_slug?: string | null;
    page_title?: string | null;
    section_id?: string | null;
    section_key?: string | null;
    component_type?: string | null;
    component_name?: string | null;
    parameter_path?: string | null;
    component_path?: string | null;
    element_id?: string | null;
    editable_fields?: string[];
    variants?: BuilderEditableTarget['variants'] | null;
    allowed_updates?: BuilderEditableTarget['allowedUpdates'] | null;
    current_breakpoint?: BuilderBreakpoint | null;
    current_interaction_state?: BuilderInteractionState | null;
    responsive_context?: BuilderEditableTarget['responsiveContext'] | null;
}

export function buildSelectedTargetContext(
    target: BuilderEditableTarget | null,
    options?: {
        currentBreakpoint?: BuilderBreakpoint | null;
        currentInteractionState?: BuilderInteractionState | null;
    },
): SelectedTargetContext | null {
    if (!target) {
        return null;
    }

    const responsiveContext = target.responsiveContext
        ? {
            ...target.responsiveContext,
            currentBreakpoint: options?.currentBreakpoint ?? target.responsiveContext.currentBreakpoint,
            currentInteractionState: options?.currentInteractionState ?? target.responsiveContext.currentInteractionState,
        }
        : null;

    return {
        page_id: target.pageId ?? null,
        page_slug: target.pageSlug ?? null,
        page_title: target.pageTitle ?? null,
        section_id: target.sectionLocalId ?? target.sectionId ?? null,
        section_key: target.sectionKey ?? null,
        component_type: target.componentType ?? null,
        component_name: target.componentName ?? null,
        parameter_path: target.path ?? null,
        component_path: target.componentPath ?? target.path ?? null,
        element_id: target.elementId ?? null,
        editable_fields: target.editableFields ?? [],
        variants: target.variants ?? null,
        allowed_updates: target.allowedUpdates ?? null,
        current_breakpoint: options?.currentBreakpoint ?? target.responsiveContext?.currentBreakpoint ?? null,
        current_interaction_state: options?.currentInteractionState ?? target.responsiveContext?.currentInteractionState ?? null,
        responsive_context: responsiveContext,
    };
}

export function selectedTargetIsMappable(target: BuilderEditableTarget | null): boolean {
    if (!target) {
        return true;
    }

    if (!target.path) {
        return Array.isArray(target.editableFields) && target.editableFields.length > 0;
    }

    return Boolean(
        target.elementId
        && target.allowedUpdates
        && target.allowedUpdates.fieldPaths.length > 0
        && target.allowedUpdates.operationTypes.length > 0
    );
}
