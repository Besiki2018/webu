/**
 * Draft persist scheduler (Task 6 extraction).
 * Encapsulates requestAnimationFrame + debounced setTimeout so structural changes
 * trigger a single save after 250ms. Used by Cms for scheduleStructuralDraftPersistRef.
 */

export interface ScheduleDraftPersistOptions {
    debounceMs: number;
}

export interface ScheduleDraftPersistResult {
    schedule: (save: () => void) => void;
    cancel: () => void;
}

/**
 * Creates a scheduler that debounces save calls: schedule(save) will run save once
 * after the next animation frame and then after debounceMs, cancelling any pending run.
 */
export function createScheduleDraftPersist(options: { debounceMs: number }): ScheduleDraftPersistResult {
    const { debounceMs } = options;
    let rafId: number | null = null;
    let timerId: ReturnType<typeof setTimeout> | null = null;
    let pendingSave: (() => void) | null = null;

    function cancel(): void {
        if (rafId !== null && typeof window !== 'undefined') {
            window.cancelAnimationFrame(rafId);
            rafId = null;
        }
        if (timerId !== null) {
            clearTimeout(timerId);
            timerId = null;
        }
        pendingSave = null;
    }

    function schedule(save: () => void): void {
        cancel();
        pendingSave = save;
        if (typeof window === 'undefined') {
            save();
            return;
        }
        rafId = window.requestAnimationFrame(() => {
            rafId = null;
            timerId = setTimeout(() => {
                timerId = null;
                const fn = pendingSave;
                pendingSave = null;
                fn?.();
            }, debounceMs);
        });
    }

    return { schedule, cancel };
}
