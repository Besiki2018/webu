import type { ComponentType, ReactNode } from 'react';

import type { PreviewViewport } from '@/components/Preview/PreviewViewportMenu';
import type { InspectPreviewProps } from '@/components/Preview/InspectPreview';
import { buildBuilderPreviewUrl } from './useBuilderWorkspace';

interface BuilderPreviewFrameProps extends Omit<InspectPreviewProps, 'mode' | 'viewport' | 'previewUrl'> {
    InspectPreviewComponent: ComponentType<InspectPreviewProps>;
    viewMode: 'preview' | 'inspect' | 'design';
    previewViewport: PreviewViewport;
    effectivePreviewUrl: string | null;
    effectivePreviewUrlWithOverrides: string | null;
}

export function BuilderPreviewFrame({
    InspectPreviewComponent,
    viewMode,
    previewViewport,
    effectivePreviewUrl,
    effectivePreviewUrlWithOverrides,
    ...previewProps
}: BuilderPreviewFrameProps): ReactNode {
    const previewUrl = buildBuilderPreviewUrl(
        viewMode,
        effectivePreviewUrl,
        effectivePreviewUrlWithOverrides,
    );

    return (
        <InspectPreviewComponent
            {...previewProps}
            mode={viewMode}
            viewport={previewViewport}
            previewUrl={previewUrl}
        />
    );
}
