/**
 * Hook that returns a stable draft-persist scheduler (Task 6).
 * Use in Cms or any component that needs raf+debounce for structural draft saves.
 */

import { useMemo } from 'react';
import { createScheduleDraftPersist, type ScheduleDraftPersistResult } from './scheduleDraftPersist';

const DEFAULT_DEBOUNCE_MS = 250;

/**
 * Returns a stable scheduler instance. Cancel is called automatically on unmount
 * if the consumer uses it in a useLayoutEffect cleanup.
 */
export function useDraftPersistSchedule(debounceMs: number = DEFAULT_DEBOUNCE_MS): ScheduleDraftPersistResult {
    return useMemo(() => createScheduleDraftPersist({ debounceMs }), [debounceMs]);
}
