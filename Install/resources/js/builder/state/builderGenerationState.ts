export type BuilderGenerationState = 'idle' | 'queued' | 'planning' | 'generating' | 'finalizing' | 'complete' | 'failed';

export interface BuilderGenerationStep {
    key: Extract<BuilderGenerationState, 'planning' | 'generating' | 'finalizing'>;
    label: string;
}

export const BUILDER_GENERATION_STEPS: BuilderGenerationStep[] = [
    { key: 'planning', label: 'Planning layout' },
    { key: 'generating', label: 'Creating sections' },
    { key: 'finalizing', label: 'Applying design' },
];

export function isBuilderGenerationBlocking(state: BuilderGenerationState): boolean {
    return state !== 'complete' && state !== 'failed';
}

export function getBuilderGenerationStepStatus(
    state: BuilderGenerationState,
    step: BuilderGenerationStep['key'],
): 'pending' | 'active' | 'complete' {
    const order: BuilderGenerationState[] = ['idle', 'queued', 'planning', 'generating', 'finalizing', 'complete'];
    const currentIndex = order.indexOf(state);
    const stepIndex = order.indexOf(step);

    if (currentIndex === -1 || stepIndex === -1) {
        return 'pending';
    }

    if (state === 'complete' || currentIndex > stepIndex) {
        return 'complete';
    }

    if (currentIndex === stepIndex) {
        return 'active';
    }

    return 'pending';
}

export function getBuilderGenerationHeadline(state: BuilderGenerationState): string {
    switch (state) {
        case 'queued':
            return 'Preparing your website...';
        case 'planning':
            return 'Planning your website...';
        case 'generating':
            return 'Generating your website...';
        case 'finalizing':
            return 'Finalizing your website...';
        case 'complete':
            return 'Website ready';
        case 'failed':
            return 'Website generation failed';
        case 'idle':
        default:
            return 'Preparing your website...';
    }
}

export function resolveBuilderGenerationState(status?: string | null): BuilderGenerationState {
    switch ((status ?? '').trim().toLowerCase()) {
        case 'queued':
            return 'queued';
        case 'planning':
            return 'planning';
        case 'generating':
            return 'generating';
        case 'finalizing':
            return 'finalizing';
        case 'completed':
        case 'complete':
            return 'complete';
        case 'failed':
            return 'failed';
        default:
            return 'idle';
    }
}
