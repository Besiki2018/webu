import type { PageProps } from '@/types';

export interface WebuV2FeatureFlagsShape {
    codeFirstInitialGeneration: boolean;
    workspaceBackedVisualBuilder: boolean;
    imageToSiteImport: boolean;
    advancedAiWorkspaceEdits: boolean;
}

const DEFAULT_WEBU_V2_FEATURE_FLAGS: WebuV2FeatureFlagsShape = {
    codeFirstInitialGeneration: true,
    workspaceBackedVisualBuilder: true,
    imageToSiteImport: true,
    advancedAiWorkspaceEdits: true,
};

function normalizeBoolean(value: unknown, fallback: boolean): boolean {
    return typeof value === 'boolean' ? value : fallback;
}

export function getDefaultWebuV2FeatureFlags(): WebuV2FeatureFlagsShape {
    return { ...DEFAULT_WEBU_V2_FEATURE_FLAGS };
}

export function resolveWebuV2FeatureFlags(
    pageProps?: Partial<PageProps> | null,
): WebuV2FeatureFlagsShape {
    const raw = pageProps?.featureFlags?.webuV2;

    return {
        codeFirstInitialGeneration: normalizeBoolean(
            raw?.codeFirstInitialGeneration,
            DEFAULT_WEBU_V2_FEATURE_FLAGS.codeFirstInitialGeneration,
        ),
        workspaceBackedVisualBuilder: normalizeBoolean(
            raw?.workspaceBackedVisualBuilder,
            DEFAULT_WEBU_V2_FEATURE_FLAGS.workspaceBackedVisualBuilder,
        ),
        imageToSiteImport: normalizeBoolean(
            raw?.imageToSiteImport,
            DEFAULT_WEBU_V2_FEATURE_FLAGS.imageToSiteImport,
        ),
        advancedAiWorkspaceEdits: normalizeBoolean(
            raw?.advancedAiWorkspaceEdits,
            DEFAULT_WEBU_V2_FEATURE_FLAGS.advancedAiWorkspaceEdits,
        ),
    };
}
