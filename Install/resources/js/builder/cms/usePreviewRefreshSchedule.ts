/**
 * Hook that returns a stable preview-refresh scheduler (Task 6).
 * Use in Cms for immediate (0ms) and auto-save (e.g. 1500ms) preview refresh; each call cancels any pending run.
 */

import { useMemo } from 'react';
import { createSchedulePreviewRefresh, type SchedulePreviewRefreshResult } from './schedulePreviewRefresh';

export function usePreviewRefreshSchedule(): SchedulePreviewRefreshResult {
    return useMemo(() => createSchedulePreviewRefresh(), []);
}
