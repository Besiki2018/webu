import { describe, expect, it } from 'vitest';

import {
    advanceImageImportRunState,
    createImageImportRunState,
    isImageImportPreviewReady,
} from '@/builder/image-import/imageImportState';

describe('image-import imageImportState', () => {
    it('keeps preview blocked until the image import run reaches ready', () => {
        const extracting = createImageImportRunState({
            phase: 'extracting_design',
        });

        expect(extracting.previewGate.allowPreview).toBe(false);
        expect(extracting.previewGate.lockInspectMode).toBe(true);
        expect(isImageImportPreviewReady(extracting)).toBe(false);

        const ready = advanceImageImportRunState(extracting, 'ready');
        expect(ready.previewGate.allowPreview).toBe(true);
        expect(isImageImportPreviewReady(ready)).toBe(true);
    });
});
