import { Fragment } from 'react';
import { useBuilderStore } from '@/builder/state/builderStore';
import { CanvasNode } from './CanvasNode';

const EMPTY_CHILDREN: string[] = [];

export function CanvasRenderer() {
    const rootChildIds = useBuilderStore((state) => {
        const page = state.builderDocument.pages[state.activePageId] ?? state.builderDocument.pages[state.builderDocument.rootPageId];
        if (! page) {
            return EMPTY_CHILDREN;
        }

        return state.builderDocument.nodes[page.rootNodeId]?.children ?? EMPTY_CHILDREN;
    });

    return (
        <Fragment>
            {rootChildIds.map((nodeId) => (
                <CanvasNode key={nodeId} nodeId={nodeId} />
            ))}
        </Fragment>
    );
}
