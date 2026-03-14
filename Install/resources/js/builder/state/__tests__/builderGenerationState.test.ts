import { describe, expect, it } from 'vitest';

import {
    getBuilderGenerationDefaultProgressMessage,
    getBuilderGenerationHeadline,
    getBuilderGenerationStepStatus,
    isBuilderGenerationBlocking,
    resolveBuilderGenerationState,
} from '@/builder/state/builderGenerationState';

describe('builderGenerationState', () => {
    it('treats every phase before complete as blocking', () => {
        expect(isBuilderGenerationBlocking('idle')).toBe(true);
        expect(isBuilderGenerationBlocking('analyzing_prompt')).toBe(true);
        expect(isBuilderGenerationBlocking('planning_structure')).toBe(true);
        expect(isBuilderGenerationBlocking('selecting_components')).toBe(true);
        expect(isBuilderGenerationBlocking('generating_content')).toBe(true);
        expect(isBuilderGenerationBlocking('assembling_page')).toBe(true);
        expect(isBuilderGenerationBlocking('validating_result')).toBe(true);
        expect(isBuilderGenerationBlocking('rendering_preview')).toBe(true);
        expect(isBuilderGenerationBlocking('completed')).toBe(false);
        expect(isBuilderGenerationBlocking('failed')).toBe(false);
    });

    it('maps backend statuses into the normalized builder generation state machine', () => {
        expect(resolveBuilderGenerationState('queued')).toBe('analyzing_prompt');
        expect(resolveBuilderGenerationState('analyzing_prompt')).toBe('analyzing_prompt');
        expect(resolveBuilderGenerationState('planning_structure')).toBe('planning_structure');
        expect(resolveBuilderGenerationState('selecting_components')).toBe('selecting_components');
        expect(resolveBuilderGenerationState('generating_content')).toBe('generating_content');
        expect(resolveBuilderGenerationState('writing_files')).toBe('assembling_page');
        expect(resolveBuilderGenerationState('validating_result')).toBe('validating_result');
        expect(resolveBuilderGenerationState('rendering_preview')).toBe('rendering_preview');
        expect(resolveBuilderGenerationState('ready')).toBe('rendering_preview');
        expect(resolveBuilderGenerationState('ready', { readyForBuilder: true })).toBe('completed');
        expect(resolveBuilderGenerationState('failed')).toBe('failed');
        expect(resolveBuilderGenerationState('unknown')).toBe('idle');
    });

    it('returns stable phase headlines', () => {
        expect(getBuilderGenerationHeadline('analyzing_prompt')).toBe('Understanding your project...');
        expect(getBuilderGenerationHeadline('planning_structure')).toBe('Planning the layout...');
        expect(getBuilderGenerationHeadline('selecting_components')).toBe('Selecting components...');
        expect(getBuilderGenerationHeadline('generating_content')).toBe('Generating content...');
        expect(getBuilderGenerationHeadline('assembling_page')).toBe('Assembling the page...');
        expect(getBuilderGenerationHeadline('validating_result')).toBe('Optimizing the design...');
        expect(getBuilderGenerationHeadline('rendering_preview')).toBe('Finalizing the preview...');
        expect(getBuilderGenerationHeadline('completed')).toBe('Website ready');
        expect(getBuilderGenerationHeadline('failed')).toBe('Website generation failed');
    });

    it('returns stable default progress copy for each stage', () => {
        expect(getBuilderGenerationDefaultProgressMessage('analyzing_prompt')).toBe('Understanding the project requirements.');
        expect(getBuilderGenerationDefaultProgressMessage('planning_structure')).toBe('Planning the layout and blueprint.');
        expect(getBuilderGenerationDefaultProgressMessage('assembling_page')).toBe('Assembling the page tree and writing files.');
        expect(getBuilderGenerationDefaultProgressMessage('validating_result')).toBe('Improving spacing, hierarchy, CTA clarity, and overall design quality.');
        expect(getBuilderGenerationDefaultProgressMessage('completed')).toBe('Website ready.');
    });

    it('marks earlier steps complete and the current step active', () => {
        expect(getBuilderGenerationStepStatus('analyzing_prompt', 'analyzing_prompt')).toBe('active');
        expect(getBuilderGenerationStepStatus('planning_structure', 'analyzing_prompt')).toBe('complete');
        expect(getBuilderGenerationStepStatus('planning_structure', 'planning_structure')).toBe('active');
        expect(getBuilderGenerationStepStatus('assembling_page', 'planning_structure')).toBe('complete');
        expect(getBuilderGenerationStepStatus('assembling_page', 'selecting_components')).toBe('complete');
        expect(getBuilderGenerationStepStatus('assembling_page', 'assembling_page')).toBe('active');
        expect(getBuilderGenerationStepStatus('validating_result', 'assembling_page')).toBe('complete');
        expect(getBuilderGenerationStepStatus('validating_result', 'validating_result')).toBe('active');
        expect(getBuilderGenerationStepStatus('rendering_preview', 'validating_result')).toBe('complete');
        expect(getBuilderGenerationStepStatus('completed', 'rendering_preview')).toBe('complete');
        expect(getBuilderGenerationStepStatus('assembling_page', 'rendering_preview')).toBe('pending');
    });
});
