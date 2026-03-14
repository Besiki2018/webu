import { describe, expect, it } from 'vitest';

import {
    canOpenPreview,
    createGenerationRunState,
    getPreviewGateDecision,
    isInspectModeLocked,
} from '@/builder/codegen/generationPhases';

describe('codegen generationPhases', () => {
    it('allows preview only when phase is ready', () => {
        expect(canOpenPreview('idle')).toBe(false);
        expect(canOpenPreview('planning')).toBe(false);
        expect(canOpenPreview('building_preview')).toBe(false);
        expect(canOpenPreview('failed')).toBe(false);
        expect(canOpenPreview('ready')).toBe(true);
    });

    it('locks inspect mode only while blocking generation phases are active', () => {
        expect(isInspectModeLocked('planning')).toBe(true);
        expect(isInspectModeLocked('scaffolding')).toBe(true);
        expect(isInspectModeLocked('building_preview')).toBe(true);
        expect(isInspectModeLocked('failed')).toBe(false);
        expect(isInspectModeLocked('ready')).toBe(false);
    });

    it('returns a preview gate decision for generation states', () => {
        const running = getPreviewGateDecision(createGenerationRunState({
            phase: 'writing_files',
        }));

        expect(running.allowPreview).toBe(false);
        expect(running.lockInspectMode).toBe(true);
        expect(running.reason).toBe('generation_in_progress');

        const ready = getPreviewGateDecision(createGenerationRunState({
            phase: 'ready',
            preview: {
                ready: true,
                status: 'ready',
            },
        }));

        expect(ready.allowPreview).toBe(true);
        expect(ready.lockInspectMode).toBe(false);
        expect(ready.reason).toBeNull();
    });
});
