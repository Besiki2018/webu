import { describe, expect, it } from 'vitest';

import {
    buildBuilderPreviewUrl,
    buildVisualBuilderSidebarUrl,
} from '@/builder/chat/useBuilderWorkspace';

describe('useBuilderWorkspace helpers', () => {
    it('builds the embedded sidebar iframe url from the project id', () => {
        expect(buildVisualBuilderSidebarUrl('project-123')).toBe('/project/project-123/cms?tab=editor&embedded=sidebar');
    });

    it('builds inspect preview urls with the builder flag', () => {
        expect(buildBuilderPreviewUrl('inspect', 'http://127.0.0.1:8000/preview', null))
            .toBe('http://127.0.0.1:8000/preview?builder=1');
        expect(buildBuilderPreviewUrl('inspect', 'http://127.0.0.1:8000/preview?draft=1', null))
            .toBe('http://127.0.0.1:8000/preview?draft=1&builder=1');
        expect(
            buildBuilderPreviewUrl(
                'inspect',
                'http://127.0.0.1:8000/preview',
                'http://127.0.0.1:8000/preview?header_variant=split',
            ),
        ).toBe('http://127.0.0.1:8000/preview?header_variant=split&builder=1');
    });

    it('returns the base preview url outside inspect mode', () => {
        expect(buildBuilderPreviewUrl('preview', 'http://127.0.0.1:8000/preview', 'http://127.0.0.1:8000/preview?header_variant=split'))
            .toBe('http://127.0.0.1:8000/preview');
        expect(buildBuilderPreviewUrl('design', null, 'http://127.0.0.1:8000/preview?header_variant=split')).toBeNull();
    });
});
