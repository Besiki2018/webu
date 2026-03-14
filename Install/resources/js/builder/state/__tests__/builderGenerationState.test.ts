import { describe, expect, it } from 'vitest';

import {
    getBuilderGenerationHeadline,
    getBuilderGenerationStepStatus,
    isBuilderGenerationBlocking,
    resolveBuilderGenerationState,
} from '@/builder/state/builderGenerationState';

describe('builderGenerationState', () => {
    it('treats every phase before complete as blocking', () => {
        expect(isBuilderGenerationBlocking('idle')).toBe(true);
        expect(isBuilderGenerationBlocking('queued')).toBe(true);
        expect(isBuilderGenerationBlocking('planning')).toBe(true);
        expect(isBuilderGenerationBlocking('scaffolding')).toBe(true);
        expect(isBuilderGenerationBlocking('writing_files')).toBe(true);
        expect(isBuilderGenerationBlocking('building_preview')).toBe(true);
        expect(isBuilderGenerationBlocking('ready')).toBe(false);
        expect(isBuilderGenerationBlocking('failed')).toBe(false);
    });

    it('resolves deterministic step progress', () => {
        expect(getBuilderGenerationStepStatus('planning', 'planning')).toBe('active');
        expect(getBuilderGenerationStepStatus('scaffolding', 'planning')).toBe('complete');
        expect(getBuilderGenerationStepStatus('scaffolding', 'scaffolding')).toBe('active');
        expect(getBuilderGenerationStepStatus('writing_files', 'scaffolding')).toBe('complete');
        expect(getBuilderGenerationStepStatus('building_preview', 'writing_files')).toBe('complete');
        expect(getBuilderGenerationStepStatus('ready', 'building_preview')).toBe('complete');
    });

    it('returns stable phase headlines', () => {
        expect(getBuilderGenerationHeadline('queued')).toBe('Preparing your website...');
        expect(getBuilderGenerationHeadline('planning')).toBe('Planning your website...');
        expect(getBuilderGenerationHeadline('scaffolding')).toBe('Generating your site structure...');
        expect(getBuilderGenerationHeadline('writing_files')).toBe('Writing your project files...');
        expect(getBuilderGenerationHeadline('building_preview')).toBe('Building your preview...');
        expect(getBuilderGenerationHeadline('ready')).toBe('Website ready');
        expect(getBuilderGenerationHeadline('failed')).toBe('Website generation failed');
    });

    it('maps backend statuses into UI states', () => {
        expect(resolveBuilderGenerationState('queued')).toBe('queued');
        expect(resolveBuilderGenerationState('planning')).toBe('planning');
        expect(resolveBuilderGenerationState('generating')).toBe('scaffolding');
        expect(resolveBuilderGenerationState('writing_files')).toBe('writing_files');
        expect(resolveBuilderGenerationState('finalizing')).toBe('building_preview');
        expect(resolveBuilderGenerationState('completed')).toBe('ready');
        expect(resolveBuilderGenerationState('ready')).toBe('ready');
        expect(resolveBuilderGenerationState('failed')).toBe('failed');
        expect(resolveBuilderGenerationState('unknown')).toBe('idle');
    });
});
