import type {
    BuilderTargetAllowedUpdates,
    BuilderTargetResponsiveContext,
    BuilderTargetVariants,
} from '@/builder/editingState';

export interface BuilderChatElementContext {
    tagName: string;
    selector: string;
    textPreview: string;
    sectionLocalId?: string;
    sectionKey?: string;
    componentType?: string;
    componentPath?: string;
    parameterName?: string;
    elementId?: string;
    editableFields?: string[];
    variants?: BuilderTargetVariants | null;
    allowedUpdates?: BuilderTargetAllowedUpdates | null;
    currentBreakpoint?: 'desktop' | 'tablet' | 'mobile' | null;
    currentInteractionState?: 'normal' | 'hover' | 'focus' | 'active' | null;
    responsiveContext?: BuilderTargetResponsiveContext | null;
}

export function buildBuilderChatPrompt(content: string, elementContext?: BuilderChatElementContext): string {
    let finalPrompt = content.trim();
    if (!elementContext) {
        return finalPrompt;
    }

    finalPrompt += `\n\n[Selected Element]\n<${elementContext.tagName}${elementContext.selector ? ` (${elementContext.selector})` : ''}>${elementContext.textPreview ? ` containing "${elementContext.textPreview}"` : ''}\nSelector: ${elementContext.selector}`;
    if (elementContext.elementId) {
        finalPrompt += `\nElement ID: ${elementContext.elementId}`;
    }
    if (elementContext.componentType) {
        finalPrompt += `\nComponent Type: ${elementContext.componentType}`;
    }
    if (elementContext.componentPath || elementContext.parameterName) {
        finalPrompt += `\nComponent Path: ${elementContext.componentPath ?? elementContext.parameterName}`;
    }
    if (Array.isArray(elementContext.editableFields) && elementContext.editableFields.length > 0) {
        finalPrompt += `\nEditable Fields: ${elementContext.editableFields.join(', ')}`;
    }
    if (elementContext.variants?.layout) {
        finalPrompt += `\nLayout Variant: ${elementContext.variants.layout.active ?? 'default'} (allowed: ${elementContext.variants.layout.options.join(', ')})`;
    }
    if (elementContext.variants?.style) {
        finalPrompt += `\nStyle Variant: ${elementContext.variants.style.active ?? 'default'} (allowed: ${elementContext.variants.style.options.join(', ')})`;
    }
    if (elementContext.allowedUpdates) {
        finalPrompt += `\nAllowed Operations: ${elementContext.allowedUpdates.operationTypes.join(', ')}`;
        finalPrompt += `\nAllowed Field Paths: ${elementContext.allowedUpdates.fieldPaths.join(', ')}`;
        if (elementContext.allowedUpdates.scope === 'element') {
            finalPrompt += `\nIf the user explicitly asks for a broader same-section change, keep it within this section and only use: ${elementContext.allowedUpdates.sectionFieldPaths.join(', ')}`;
        }
    }
    if (elementContext.currentBreakpoint) {
        finalPrompt += `\nCurrent Breakpoint: ${elementContext.currentBreakpoint}`;
    }
    if (elementContext.currentInteractionState) {
        finalPrompt += `\nCurrent Interaction State: ${elementContext.currentInteractionState}`;
    }
    if (elementContext.responsiveContext) {
        finalPrompt += `\nAvailable Breakpoints: ${elementContext.responsiveContext.availableBreakpoints.join(', ')}`;
        finalPrompt += `\nAvailable Interaction States: ${elementContext.responsiveContext.availableInteractionStates.join(', ')}`;
        if (elementContext.responsiveContext.supportsResponsiveOverrides) {
            finalPrompt += `\nResponsive Field Paths (${elementContext.responsiveContext.currentBreakpoint}): ${elementContext.responsiveContext.responsiveFieldPaths.join(', ')}`;
        }
        if (elementContext.responsiveContext.currentInteractionState !== 'normal' && elementContext.responsiveContext.stateFieldPaths.length > 0) {
            finalPrompt += `\nState Field Paths (${elementContext.responsiveContext.currentInteractionState}): ${elementContext.responsiveContext.stateFieldPaths.join(', ')}`;
        }
    }

    return finalPrompt;
}
