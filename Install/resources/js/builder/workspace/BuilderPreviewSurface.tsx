import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type { ChatViewMode } from '@/builder/chat/chatPageUtils';

interface BuilderPreviewSurfaceProps {
    isSidebarVisible: boolean;
    viewMode: ChatViewMode;
    settingsContent: ReactNode;
    codeContent: ReactNode;
    previewContent: ReactNode;
}

export function BuilderPreviewSurface({
    isSidebarVisible,
    viewMode,
    settingsContent,
    codeContent,
    previewContent,
}: BuilderPreviewSurfaceProps) {
    return (
        <div
            className={cn(
                'workspace-preview-panel flex min-w-0 basis-0 flex-1 flex-col overflow-hidden',
                isSidebarVisible && 'workspace-preview-panel--sidebar-open',
            )}
        >
            <div className="relative flex-1 min-h-0 min-w-0 overflow-hidden">
                {viewMode === 'settings'
                    ? settingsContent
                    : viewMode === 'code'
                        ? codeContent
                        : previewContent}
            </div>
        </div>
    );
}
