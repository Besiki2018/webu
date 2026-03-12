import type { ReactNode } from 'react';

interface BuilderWorkspaceShellProps {
    isSidebarVisible: boolean;
    sidebarContent: ReactNode;
    previewContent: ReactNode;
}

export function BuilderWorkspaceShell({
    isSidebarVisible,
    sidebarContent,
    previewContent,
}: BuilderWorkspaceShellProps) {
    return (
        <div className="flex min-h-0 min-w-0 flex-1 overflow-hidden">
            {isSidebarVisible ? sidebarContent : null}
            {previewContent}
        </div>
    );
}
