import { describe, it, expect } from 'vitest';
import { renderHook } from '@testing-library/react';
import { useDraftPersistSchedule } from '../useDraftPersistSchedule';

describe('useDraftPersistSchedule', () => {
    it('returns schedule and cancel functions', () => {
        const { result } = renderHook(() => useDraftPersistSchedule(250));
        expect(result.current).toHaveProperty('schedule');
        expect(result.current).toHaveProperty('cancel');
        expect(typeof result.current.schedule).toBe('function');
        expect(typeof result.current.cancel).toBe('function');
    });

    it('returns stable reference when debounceMs does not change', () => {
        const { result, rerender } = renderHook(() => useDraftPersistSchedule(250));
        const first = result.current;
        rerender();
        expect(result.current).toBe(first);
    });

    it('uses default debounce when no argument', () => {
        const { result } = renderHook(() => useDraftPersistSchedule());
        expect(result.current).toHaveProperty('schedule');
        result.current.schedule(() => {});
    });
});
