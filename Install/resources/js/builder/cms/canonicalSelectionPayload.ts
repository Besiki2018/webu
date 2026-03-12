import type { BuilderBridgePageIdentity } from '@/builder/cms/embeddedBuilderBridgeContract';
import {
    buildSectionScopedEditableTarget,
    editableTargetToMessagePayload,
    type BuilderBreakpoint,
    type BuilderEditableTarget,
    type BuilderInteractionState,
    type BuilderSelectionMessagePayload,
} from '@/builder/editingState';

export interface CanonicalSelectionFallback {
    sectionLocalId?: string | null;
    sectionKey?: string | null;
    componentType?: string | null;
    componentName?: string | null;
    textPreview?: string | null;
}

interface BuildCanonicalBridgeSelectedTargetPayloadOptions {
    pageIdentity: BuilderBridgePageIdentity;
    target: BuilderEditableTarget | null;
    fallback?: CanonicalSelectionFallback | null;
    currentBreakpoint: BuilderBreakpoint;
    currentInteractionState: BuilderInteractionState;
}

export function buildCanonicalBridgeSelectedTargetPayload({
    pageIdentity,
    target,
    fallback = null,
    currentBreakpoint,
    currentInteractionState,
}: BuildCanonicalBridgeSelectedTargetPayloadOptions): BuilderSelectionMessagePayload | null {
    const sectionScopedTarget = buildSectionScopedEditableTarget(editableTargetToMessagePayload(target));
    const sectionLocalId = sectionScopedTarget?.sectionLocalId ?? fallback?.sectionLocalId ?? null;
    const sectionKey = sectionScopedTarget?.sectionKey ?? fallback?.sectionKey ?? null;
    const componentType = sectionScopedTarget?.componentType ?? fallback?.componentType ?? sectionKey;

    if (!sectionLocalId && !sectionKey && !componentType) {
        return null;
    }

    return {
        pageId: pageIdentity.pageId ?? null,
        pageSlug: pageIdentity.pageSlug ?? null,
        pageTitle: pageIdentity.pageTitle ?? null,
        sectionLocalId,
        sectionKey,
        componentType,
        componentName: sectionScopedTarget?.componentName ?? fallback?.componentName ?? null,
        textPreview: sectionScopedTarget?.textPreview ?? fallback?.textPreview ?? null,
        currentBreakpoint,
        currentInteractionState,
    };
}
