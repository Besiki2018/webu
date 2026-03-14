export type BuilderGenerationState =
    | 'idle'
    | 'analyzing_prompt'
    | 'planning_structure'
    | 'selecting_components'
    | 'generating_content'
    | 'assembling_page'
    | 'validating_result'
    | 'rendering_preview'
    | 'completed'
    | 'failed';

export interface BuilderGenerationStep {
    key: Extract<
        BuilderGenerationState,
        'analyzing_prompt'
        | 'planning_structure'
        | 'selecting_components'
        | 'generating_content'
        | 'assembling_page'
        | 'validating_result'
        | 'rendering_preview'
    >;
    label: string;
}

export interface ResolveBuilderGenerationStateOptions {
    readyForBuilder?: boolean;
}

export const BUILDER_GENERATION_STEPS: BuilderGenerationStep[] = [
    { key: 'analyzing_prompt', label: 'Analyzing prompt' },
    { key: 'planning_structure', label: 'Planning blueprint' },
    { key: 'selecting_components', label: 'Selecting components' },
    { key: 'generating_content', label: 'Generating content' },
    { key: 'assembling_page', label: 'Assembling page' },
    { key: 'validating_result', label: 'Validating result' },
    { key: 'rendering_preview', label: 'Rendering preview' },
];

export function isBuilderGenerationBlocking(state: BuilderGenerationState): boolean {
    return state !== 'completed' && state !== 'failed';
}

export function getBuilderGenerationStepStatus(
    state: BuilderGenerationState,
    step: BuilderGenerationStep['key'],
): 'pending' | 'active' | 'complete' {
    const order: BuilderGenerationState[] = [
        'idle',
        'analyzing_prompt',
        'planning_structure',
        'selecting_components',
        'generating_content',
        'assembling_page',
        'validating_result',
        'rendering_preview',
        'completed',
    ];
    const currentIndex = order.indexOf(state);
    const stepIndex = order.indexOf(step);

    if (currentIndex === -1 || stepIndex === -1) {
        return 'pending';
    }

    if (state === 'completed' || currentIndex > stepIndex) {
        return 'complete';
    }

    if (currentIndex === stepIndex) {
        return 'active';
    }

    return 'pending';
}

export function getBuilderGenerationHeadline(state: BuilderGenerationState): string {
    switch (state) {
        case 'analyzing_prompt':
            return 'Analyzing your prompt...';
        case 'planning_structure':
            return 'Planning the blueprint...';
        case 'selecting_components':
            return 'Selecting components...';
        case 'generating_content':
            return 'Generating content...';
        case 'assembling_page':
            return 'Assembling the page...';
        case 'validating_result':
            return 'Validating the result...';
        case 'rendering_preview':
            return 'Rendering the preview...';
        case 'completed':
            return 'Website ready';
        case 'failed':
            return 'Website generation failed';
        case 'idle':
        default:
            return 'Preparing your website...';
    }
}

export function getBuilderGenerationDefaultProgressMessage(state: BuilderGenerationState): string {
    switch (state) {
        case 'analyzing_prompt':
            return 'Analyzing your prompt.';
        case 'planning_structure':
            return 'Planning the blueprint.';
        case 'selecting_components':
            return 'Selecting the best components for the blueprint.';
        case 'generating_content':
            return 'Generating content for every section.';
        case 'assembling_page':
            return 'Assembling the page tree and writing files.';
        case 'validating_result':
            return 'Validating the generated output before preview.';
        case 'rendering_preview':
            return 'Rendering the preview and validating workspace readiness.';
        case 'completed':
            return 'Website ready.';
        case 'failed':
            return 'We could not finish creating this website.';
        case 'idle':
        default:
            return 'Preparing generation.';
    }
}

export function resolveBuilderGenerationState(
    status?: string | null,
    options: ResolveBuilderGenerationStateOptions = {},
): BuilderGenerationState {
    if (options.readyForBuilder) {
        return 'completed';
    }

    switch ((status ?? '').trim().toLowerCase()) {
        case 'queued':
        case 'planning':
        case 'analyzing_prompt':
            return 'analyzing_prompt';
        case 'planning_structure':
        case 'scaffolding':
        case 'generating':
        case 'generating_structure':
        case 'generating_site_structure':
            return 'planning_structure';
        case 'selecting_components':
            return 'selecting_components';
        case 'generating_content':
            return 'generating_content';
        case 'assembling_page':
        case 'writing_files':
            return 'assembling_page';
        case 'validating_result':
        case 'validation':
        case 'validating':
            return 'validating_result';
        case 'rendering_preview':
        case 'building_preview':
        case 'finalizing':
        case 'installing':
        case 'ready':
        case 'completed':
        case 'complete':
            return 'rendering_preview';
        case 'failed':
            return 'failed';
        default:
            return 'idle';
    }
}
