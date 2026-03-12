/**
 * Tests for useGeneratedCodePreview hook.
 */
import { renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { useGeneratedCodePreview } from '../useGeneratedCodePreview';

describe('useGeneratedCodePreview', () => {
    it('returns initial empty structure items and virtual files when no pages provided', () => {
        const { result } = renderHook(() =>
            useGeneratedCodePreview({ generatedPages: [], generatedPage: null }, 'preview'),
        );

        expect(result.current.builderStructureItems).toEqual([]);
        expect(result.current.generatedVirtualFiles).toEqual([]);
        expect(result.current.generatedVirtualFilePaths.size).toBe(0);
        expect(result.current.activeBuilderCodePage).toBeNull();
        expect(result.current.GENERATED_PAGE_PATH_PREFIX).toBe('derived-preview');
    });

    it('returns builderCodePages and setters', () => {
        const { result } = renderHook(() =>
            useGeneratedCodePreview({ generatedPages: [], generatedPage: null }, 'preview'),
        );

        expect(typeof result.current.setBuilderCodePages).toBe('function');
        expect(typeof result.current.setBuilderStructureItems).toBe('function');
        expect(result.current.structureSnapshotPageRef).toBeDefined();
    });
});
