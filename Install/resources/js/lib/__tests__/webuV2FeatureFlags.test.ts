import { describe, expect, it } from 'vitest';

import { getDefaultWebuV2FeatureFlags, resolveWebuV2FeatureFlags } from '@/lib/webuV2FeatureFlags';

describe('webuV2FeatureFlags', () => {
    it('defaults rollout flags to enabled when shared props are missing', () => {
        expect(resolveWebuV2FeatureFlags()).toEqual(getDefaultWebuV2FeatureFlags());
    });

    it('reads shared inertia feature flags and normalizes missing keys', () => {
        expect(resolveWebuV2FeatureFlags({
            featureFlags: {
                webuV2: {
                    codeFirstInitialGeneration: false,
                    workspaceBackedVisualBuilder: false,
                    imageToSiteImport: true,
                    advancedAiWorkspaceEdits: false,
                },
            },
        })).toEqual({
            codeFirstInitialGeneration: false,
            workspaceBackedVisualBuilder: false,
            imageToSiteImport: true,
            advancedAiWorkspaceEdits: false,
        });
    });
});
