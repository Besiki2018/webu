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
    aiNodeId?: string | null;
    currentValue?: string | null;
}

function appendElementContextBlock(finalPrompt: string, elementContext: BuilderChatElementContext, index?: number): string {
    const heading = index === undefined ? '[Selected Element]' : `[Selected Element ${index + 1}]`;
    let nextPrompt = `${finalPrompt}\n\n${heading}\n<${elementContext.tagName}${elementContext.selector ? ` (${elementContext.selector})` : ''}>${elementContext.textPreview ? ` containing "${elementContext.textPreview}"` : ''}\nSelector: ${elementContext.selector}`;
    if (elementContext.aiNodeId) {
        nextPrompt += `\nAI Node ID: ${elementContext.aiNodeId}`;
    }
    if (elementContext.currentValue) {
        nextPrompt += `\nCurrent Value: ${elementContext.currentValue}`;
    }
    if (elementContext.elementId) {
        nextPrompt += `\nElement ID: ${elementContext.elementId}`;
    }
    if (elementContext.componentType) {
        nextPrompt += `\nComponent Type: ${elementContext.componentType}`;
    }
    if (elementContext.componentPath || elementContext.parameterName) {
        nextPrompt += `\nComponent Path: ${elementContext.componentPath ?? elementContext.parameterName}`;
    }
    if (Array.isArray(elementContext.editableFields) && elementContext.editableFields.length > 0) {
        nextPrompt += `\nEditable Fields: ${elementContext.editableFields.join(', ')}`;
    }
    if (elementContext.variants?.layout) {
        nextPrompt += `\nLayout Variant: ${elementContext.variants.layout.active ?? 'default'} (allowed: ${elementContext.variants.layout.options.join(', ')})`;
    }
    if (elementContext.variants?.style) {
        nextPrompt += `\nStyle Variant: ${elementContext.variants.style.active ?? 'default'} (allowed: ${elementContext.variants.style.options.join(', ')})`;
    }
    if (elementContext.allowedUpdates) {
        nextPrompt += `\nAllowed Operations: ${elementContext.allowedUpdates.operationTypes.join(', ')}`;
        nextPrompt += `\nAllowed Field Paths: ${elementContext.allowedUpdates.fieldPaths.join(', ')}`;
        if (elementContext.allowedUpdates.scope === 'element') {
            nextPrompt += `\nIf the user explicitly asks for a broader same-section change, keep it within this section and only use: ${elementContext.allowedUpdates.sectionFieldPaths.join(', ')}`;
        }
    }
    if (elementContext.currentBreakpoint) {
        nextPrompt += `\nCurrent Breakpoint: ${elementContext.currentBreakpoint}`;
    }
    if (elementContext.currentInteractionState) {
        nextPrompt += `\nCurrent Interaction State: ${elementContext.currentInteractionState}`;
    }
    if (elementContext.responsiveContext) {
        nextPrompt += `\nAvailable Breakpoints: ${elementContext.responsiveContext.availableBreakpoints.join(', ')}`;
        nextPrompt += `\nAvailable Interaction States: ${elementContext.responsiveContext.availableInteractionStates.join(', ')}`;
        if (elementContext.responsiveContext.supportsResponsiveOverrides) {
            nextPrompt += `\nResponsive Field Paths (${elementContext.responsiveContext.currentBreakpoint}): ${elementContext.responsiveContext.responsiveFieldPaths.join(', ')}`;
        }
        if (elementContext.responsiveContext.currentInteractionState !== 'normal' && elementContext.responsiveContext.stateFieldPaths.length > 0) {
            nextPrompt += `\nState Field Paths (${elementContext.responsiveContext.currentInteractionState}): ${elementContext.responsiveContext.stateFieldPaths.join(', ')}`;
        }
    }

    return nextPrompt;
}

export function buildBuilderChatPrompt(
    content: string,
    elementContext?: BuilderChatElementContext,
    elementContexts: BuilderChatElementContext[] = [],
): string {
    let finalPrompt = content.trim();
    const contexts = elementContexts.length > 0
        ? elementContexts
        : (elementContext ? [elementContext] : []);

    if (contexts.length === 0) {
        return finalPrompt;
    }

    contexts.forEach((context, index) => {
        finalPrompt = appendElementContextBlock(finalPrompt, context, contexts.length > 1 ? index : undefined);
    });

    return finalPrompt;
}
