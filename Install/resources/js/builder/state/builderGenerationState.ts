export type BuilderGenerationState =
    | 'idle'
    | 'queued'
    | 'planning'
    | 'scaffolding'
    | 'writing_files'
    | 'building_preview'
    | 'ready'
    | 'failed';

export interface BuilderGenerationStep {
    key: Extract<BuilderGenerationState, 'planning' | 'scaffolding' | 'writing_files' | 'building_preview'>;
    label: string;
}

export const BUILDER_GENERATION_STEPS: BuilderGenerationStep[] = [
    { key: 'planning', label: 'Planning' },
    { key: 'scaffolding', label: 'Generating site structure' },
    { key: 'writing_files', label: 'Writing files' },
    { key: 'building_preview', label: 'Building preview' },
];

export function isBuilderGenerationBlocking(state: BuilderGenerationState): boolean {
    return state !== 'ready' && state !== 'failed';
}

export function getBuilderGenerationStepStatus(
    state: BuilderGenerationState,
    step: BuilderGenerationStep['key'],
): 'pending' | 'active' | 'complete' {
    const order: BuilderGenerationState[] = ['idle', 'queued', 'planning', 'scaffolding', 'writing_files', 'building_preview', 'ready'];
    const currentIndex = order.indexOf(state);
    const stepIndex = order.indexOf(step);

    if (currentIndex === -1 || stepIndex === -1) {
        return 'pending';
    }

    if (state === 'ready' || currentIndex > stepIndex) {
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
        case 'scaffolding':
            return 'Generating your site structure...';
        case 'writing_files':
            return 'Writing your project files...';
        case 'building_preview':
            return 'Building your preview...';
        case 'ready':
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
        case 'scaffolding':
        case 'generating':
        case 'generating_structure':
        case 'generating_site_structure':
            return 'scaffolding';
        case 'writing_files':
            return 'writing_files';
        case 'building_preview':
        case 'finalizing':
        case 'installing':
            return 'building_preview';
        case 'ready':
        case 'completed':
        case 'complete':
            return 'ready';
        case 'failed':
            return 'failed';
        default:
            return 'idle';
    }
}
