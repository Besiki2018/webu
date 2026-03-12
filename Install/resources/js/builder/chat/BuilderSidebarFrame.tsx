import type { Ref } from 'react';

interface BuilderSidebarFrameProps {
    frameRef: Ref<HTMLIFrameElement>;
    src: string;
    title: string;
    onLoad: () => void;
}

export function BuilderSidebarFrame({
    frameRef,
    src,
    title,
    onLoad,
}: BuilderSidebarFrameProps) {
    return (
        <iframe
            ref={frameRef}
            src={src}
            title={title}
            className="workspace-builder-frame workspace-builder-frame--sidebar"
            sandbox="allow-same-origin allow-scripts allow-forms allow-modals allow-popups allow-downloads"
            onLoad={onLoad}
        />
    );
}
