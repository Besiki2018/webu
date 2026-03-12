import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { createScheduleDraftPersist } from '../scheduleDraftPersist';

describe('createScheduleDraftPersist', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('runs save once after requestAnimationFrame and debounce delay', () => {
        const save = vi.fn();
        const { schedule } = createScheduleDraftPersist({ debounceMs: 250 });

        schedule(save);
        expect(save).not.toHaveBeenCalled();

        vi.runAllTimers();
        expect(save).toHaveBeenCalledTimes(1);
    });

    it('cancels previous schedule when schedule is called again', () => {
        const save1 = vi.fn();
        const save2 = vi.fn();
        const { schedule } = createScheduleDraftPersist({ debounceMs: 250 });

        schedule(save1);
        schedule(save2);
        vi.runAllTimers();

        expect(save1).not.toHaveBeenCalled();
        expect(save2).toHaveBeenCalledTimes(1);
    });

    it('cancel prevents save from running', () => {
        const save = vi.fn();
        const { schedule, cancel } = createScheduleDraftPersist({ debounceMs: 250 });

        schedule(save);
        cancel();
        vi.runAllTimers();

        expect(save).not.toHaveBeenCalled();
    });
});
