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
        expect(isBuilderGenerationBlocking('generating')).toBe(true);
        expect(isBuilderGenerationBlocking('finalizing')).toBe(true);
        expect(isBuilderGenerationBlocking('complete')).toBe(false);
        expect(isBuilderGenerationBlocking('failed')).toBe(false);
    });

    it('resolves deterministic step progress', () => {
        expect(getBuilderGenerationStepStatus('planning', 'planning')).toBe('active');
        expect(getBuilderGenerationStepStatus('generating', 'planning')).toBe('complete');
        expect(getBuilderGenerationStepStatus('generating', 'generating')).toBe('active');
        expect(getBuilderGenerationStepStatus('finalizing', 'generating')).toBe('complete');
        expect(getBuilderGenerationStepStatus('complete', 'finalizing')).toBe('complete');
    });

    it('returns stable phase headlines', () => {
        expect(getBuilderGenerationHeadline('queued')).toBe('Preparing your website...');
        expect(getBuilderGenerationHeadline('planning')).toBe('Planning your website...');
        expect(getBuilderGenerationHeadline('generating')).toBe('Generating your website...');
        expect(getBuilderGenerationHeadline('finalizing')).toBe('Finalizing your website...');
        expect(getBuilderGenerationHeadline('complete')).toBe('Website ready');
        expect(getBuilderGenerationHeadline('failed')).toBe('Website generation failed');
    });

    it('maps backend statuses into UI states', () => {
        expect(resolveBuilderGenerationState('queued')).toBe('queued');
        expect(resolveBuilderGenerationState('planning')).toBe('planning');
        expect(resolveBuilderGenerationState('generating')).toBe('generating');
        expect(resolveBuilderGenerationState('finalizing')).toBe('finalizing');
        expect(resolveBuilderGenerationState('completed')).toBe('complete');
        expect(resolveBuilderGenerationState('failed')).toBe('failed');
        expect(resolveBuilderGenerationState('unknown')).toBe('idle');
    });
});
