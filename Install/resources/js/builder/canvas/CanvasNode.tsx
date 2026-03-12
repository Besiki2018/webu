import { memo } from 'react';
import { getBuilderComponentDefinition } from '@/builder/components/registry';
import { useBuilderStore } from '@/builder/state/builderStore';

interface CanvasNodeProps {
    nodeId: string;
}

function CanvasNodeComponent({ nodeId }: CanvasNodeProps) {
    const node = useBuilderStore((state) => state.builderDocument.nodes[nodeId]);
    if (! node) {
        return null;
    }

    const definition = getBuilderComponentDefinition(node.componentKey ?? node.type, { allowFallback: true });
    if (! definition) {
        return null;
    }

    const Renderer = definition.renderer;

    if (node.meta?.hidden) {
        return null;
    }

    const mergedProps = {
        ...definition.defaultProps,
        ...node.props,
        ...(node.styles ?? {}),
    };

    return (
        <div
            data-builder-node-id={node.id}
            className="builder-canvas-node relative rounded-[22px] transition"
        >
            <Renderer {...mergedProps}>
                {node.children.length > 0 ? (
                    <div className="grid gap-4">
                        {node.children.map((childId) => (
                            <CanvasNode key={childId} nodeId={childId} />
                        ))}
                    </div>
                ) : null}
            </Renderer>
        </div>
    );
}

export const CanvasNode = memo(CanvasNodeComponent);
