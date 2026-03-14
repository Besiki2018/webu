import { createGenerationRunState, getGenerationPhaseLabel, getPreviewGateDecision } from '@/builder/codegen/generationPhases';
import type { GenerationPhase, GenerationRunState } from '@/builder/codegen/types';

import type { ImageImportMode, ImageImportRunPhase } from './types';

export interface ImageImportRunState {
    mode: ImageImportMode;
    phase: ImageImportRunPhase;
    generation: GenerationRunState;
    previewGate: ReturnType<typeof getPreviewGateDecision>;
    message: string | null;
    errorMessage: string | null;
    warnings: string[];
}

export function mapImageImportPhaseToGenerationPhase(phase: ImageImportRunPhase): GenerationPhase {
    switch (phase) {
        case 'extracting_design':
        case 'inferring_layout':
        case 'matching_components':
            return 'planning';
        case 'building_graph':
            return 'scaffolding';
        case 'planning_workspace':
            return 'writing_files';
        case 'building_preview':
            return 'building_preview';
        case 'ready':
            return 'ready';
        case 'failed':
            return 'failed';
        case 'idle':
        default:
            return 'idle';
    }
}

export function createImageImportRunState(input: Partial<ImageImportRunState> = {}): ImageImportRunState {
    const phase = input.phase ?? 'idle';
    const generationPhase = mapImageImportPhaseToGenerationPhase(phase);
    const generation = createGenerationRunState({
        ...input.generation,
        phase: generationPhase,
        message: input.generation?.message ?? input.message ?? getGenerationPhaseLabel(generationPhase),
        errorMessage: input.generation?.errorMessage ?? input.errorMessage ?? null,
        preview: {
            buildId: input.generation?.preview.buildId ?? null,
            previewUrl: input.generation?.preview.previewUrl ?? null,
            artifactHash: input.generation?.preview.artifactHash ?? null,
            workspaceHash: input.generation?.preview.workspaceHash ?? null,
            builtAt: input.generation?.preview.builtAt ?? null,
            errorMessage: input.generation?.preview.errorMessage ?? null,
            ready: generationPhase === 'ready',
            status: generationPhase === 'ready'
                ? 'ready'
                : generationPhase === 'failed'
                    ? 'failed'
                    : generationPhase === 'idle'
                        ? 'blocked'
                        : 'pending',
        },
    });

    return {
        mode: input.mode ?? 'reference',
        phase,
        generation,
        previewGate: getPreviewGateDecision(generation),
        message: input.message ?? generation.message ?? null,
        errorMessage: input.errorMessage ?? generation.errorMessage ?? null,
        warnings: input.warnings ?? [],
    };
}

export function advanceImageImportRunState(
    current: ImageImportRunState,
    phase: ImageImportRunPhase,
    input: {
        message?: string | null;
        errorMessage?: string | null;
        warnings?: string[];
    } = {},
): ImageImportRunState {
    const generationPhase = mapImageImportPhaseToGenerationPhase(phase);
    const nowIso = new Date().toISOString();

    return createImageImportRunState({
        ...current,
        phase,
        message: input.message ?? getGenerationPhaseLabel(generationPhase),
        errorMessage: input.errorMessage ?? null,
        warnings: input.warnings ?? current.warnings,
        generation: {
            ...current.generation,
            phase: generationPhase,
            message: input.message ?? getGenerationPhaseLabel(generationPhase),
            errorMessage: input.errorMessage ?? null,
            updatedAt: nowIso,
            completedAt: generationPhase === 'ready' ? nowIso : current.generation.completedAt,
            failedAt: generationPhase === 'failed' ? nowIso : current.generation.failedAt,
            preview: {
                ...current.generation.preview,
                ready: generationPhase === 'ready',
                status: generationPhase === 'ready'
                    ? 'ready'
                    : generationPhase === 'failed'
                        ? 'failed'
                        : generationPhase === 'idle'
                            ? 'blocked'
                            : 'pending',
                builtAt: generationPhase === 'ready' ? nowIso : current.generation.preview.builtAt,
                errorMessage: input.errorMessage ?? null,
            },
        },
    });
}

export function isImageImportPreviewReady(state: ImageImportRunState): boolean {
    return state.phase === 'ready' && state.previewGate.allowPreview;
}
