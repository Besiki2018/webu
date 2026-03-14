import type { GenerationPhase, GenerationRunState, PreviewBuildState } from './types';

export const GENERATION_PHASE_ORDER: readonly GenerationPhase[] = [
    'idle',
    'planning',
    'scaffolding',
    'writing_files',
    'installing',
    'building_preview',
    'ready',
    'failed',
] as const;

export const BLOCKING_GENERATION_PHASES = new Set<GenerationPhase>([
    'planning',
    'scaffolding',
    'writing_files',
    'installing',
    'building_preview',
]);

export interface PreviewGateDecision {
    phase: GenerationPhase;
    allowPreview: boolean;
    lockInspectMode: boolean;
    reason: string | null;
}

export function createPreviewBuildState(input: Partial<PreviewBuildState> = {}): PreviewBuildState {
    return {
        status: input.status ?? 'blocked',
        ready: input.ready ?? false,
        buildId: input.buildId ?? null,
        previewUrl: input.previewUrl ?? null,
        artifactHash: input.artifactHash ?? null,
        workspaceHash: input.workspaceHash ?? null,
        builtAt: input.builtAt ?? null,
        errorMessage: input.errorMessage ?? null,
    };
}

export function createGenerationRunState(input: Partial<GenerationRunState> = {}): GenerationRunState {
    return {
        runId: input.runId ?? null,
        phase: input.phase ?? 'idle',
        message: input.message ?? null,
        errorMessage: input.errorMessage ?? null,
        startedAt: input.startedAt ?? null,
        updatedAt: input.updatedAt ?? null,
        completedAt: input.completedAt ?? null,
        failedAt: input.failedAt ?? null,
        preview: createPreviewBuildState(input.preview),
    };
}

export function isBlockingGenerationPhase(phase: GenerationPhase): boolean {
    return BLOCKING_GENERATION_PHASES.has(phase);
}

export function isGenerationReady(phase: GenerationPhase): boolean {
    return phase === 'ready';
}

export function canOpenPreview(input: GenerationPhase | Pick<GenerationRunState, 'phase'>): boolean {
    const phase = typeof input === 'string' ? input : input.phase;
    return phase === 'ready';
}

export function isInspectModeLocked(input: GenerationPhase | Pick<GenerationRunState, 'phase'>): boolean {
    const phase = typeof input === 'string' ? input : input.phase;
    return isBlockingGenerationPhase(phase);
}

export function getPreviewGateDecision(input: GenerationPhase | Pick<GenerationRunState, 'phase'>): PreviewGateDecision {
    const phase = typeof input === 'string' ? input : input.phase;

    if (phase === 'ready') {
        return {
            phase,
            allowPreview: true,
            lockInspectMode: false,
            reason: null,
        };
    }

    if (isBlockingGenerationPhase(phase)) {
        return {
            phase,
            allowPreview: false,
            lockInspectMode: true,
            reason: 'generation_in_progress',
        };
    }

    if (phase === 'failed') {
        return {
            phase,
            allowPreview: false,
            lockInspectMode: false,
            reason: 'generation_failed',
        };
    }

    return {
        phase,
        allowPreview: false,
        lockInspectMode: false,
        reason: 'preview_not_ready',
    };
}

export function getGenerationPhaseLabel(phase: GenerationPhase): string {
    switch (phase) {
        case 'planning':
            return 'Planning';
        case 'scaffolding':
            return 'Scaffolding workspace';
        case 'writing_files':
            return 'Writing files';
        case 'installing':
            return 'Installing dependencies';
        case 'building_preview':
            return 'Building preview';
        case 'ready':
            return 'Ready';
        case 'failed':
            return 'Failed';
        case 'idle':
        default:
            return 'Idle';
    }
}
