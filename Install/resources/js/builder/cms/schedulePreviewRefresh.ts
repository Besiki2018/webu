/**
 * Preview refresh scheduler (Task 6 extraction).
 * Single scheduler with variable delay per call: schedule(fn, delayMs).
 * Each call cancels any pending run. Used by Cms for immediate (0ms) and auto-save (1500ms) preview refresh.
 */

export interface SchedulePreviewRefreshResult {
    schedule: (run: () => void, delayMs: number) => void;
    cancel: () => void;
}

export function createSchedulePreviewRefresh(): SchedulePreviewRefreshResult {
    let rafId: number | null = null;
    let timerId: ReturnType<typeof setTimeout> | null = null;
    let pendingRun: (() => void) | null = null;

    function cancel(): void {
        if (rafId !== null && typeof window !== 'undefined') {
            window.cancelAnimationFrame(rafId);
            rafId = null;
        }
        if (timerId !== null) {
            clearTimeout(timerId);
            timerId = null;
        }
        pendingRun = null;
    }

    function schedule(run: () => void, delayMs: number): void {
        cancel();
        pendingRun = run;
        if (typeof window === 'undefined') {
            run();
            return;
        }
        rafId = window.requestAnimationFrame(() => {
            rafId = null;
            timerId = setTimeout(() => {
                timerId = null;
                const fn = pendingRun;
                pendingRun = null;
                fn?.();
            }, delayMs);
        });
    }

    return { schedule, cancel };
}
