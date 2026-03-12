import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { createSchedulePreviewRefresh } from '../schedulePreviewRefresh';

describe('createSchedulePreviewRefresh', () => {
    beforeEach(() => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
    });
    afterEach(() => {
        vi.useRealTimers();
    });

    it('runs callback after raf and delay', () => {
        const { schedule } = createSchedulePreviewRefresh();
        const fn = vi.fn();
        schedule(fn, 100);
        expect(fn).not.toHaveBeenCalled();
        vi.advanceTimersByTime(50);
        expect(fn).not.toHaveBeenCalled();
        vi.advanceTimersByTime(100);
        expect(fn).toHaveBeenCalledTimes(1);
    });

    it('second schedule cancels first and uses new delay', () => {
        const { schedule } = createSchedulePreviewRefresh();
        const first = vi.fn();
        const second = vi.fn();
        schedule(first, 200);
        vi.advanceTimersByTime(100);
        schedule(second, 50);
        vi.advanceTimersByTime(100);
        expect(first).not.toHaveBeenCalled();
        expect(second).toHaveBeenCalledTimes(1);
    });

    it('cancel prevents run', () => {
        const { schedule, cancel } = createSchedulePreviewRefresh();
        const fn = vi.fn();
        schedule(fn, 100);
        cancel();
        vi.advanceTimersByTime(200);
        expect(fn).not.toHaveBeenCalled();
    });
});
