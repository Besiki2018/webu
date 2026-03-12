import { beforeEach, describe, expect, it } from 'vitest';
import { readPersistedStructurePanelState } from '../chatPageUtils';

describe('readPersistedStructurePanelState', () => {
    beforeEach(() => {
        window.localStorage.clear();
    });

    it('keeps the structure panel closed by default even if an older session persisted it open', () => {
        window.localStorage.setItem('webu:chat:structure-panel:project-1', JSON.stringify({
            open: true,
            position: { x: 160, y: 220 },
        }));

        expect(readPersistedStructurePanelState('project-1', false).open).toBe(false);
    });

    it('falls back to the default closed state and default position when storage is empty', () => {
        expect(readPersistedStructurePanelState('project-2', false)).toEqual({
            open: false,
            position: { x: 24, y: 72 },
        });
    });
});
