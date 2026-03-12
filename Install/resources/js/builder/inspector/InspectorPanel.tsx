import { useEffect } from 'react';
import { useShallow } from 'zustand/shallow';
import type { BuilderApiEndpoints } from '@/builder/api/builderApi';
import { getBuilderComponentDefinition } from '@/builder/components/registry';
import { useBuilderStore } from '@/builder/state/builderStore';
import { resolveBuilderNodeLabel } from '@/builder/utils/document';
import { InspectorFieldRenderer } from './InspectorFieldRenderer';

interface InspectorPanelProps {
    endpoints: BuilderApiEndpoints;
}

export function InspectorPanel({ endpoints }: InspectorPanelProps) {
    const {
        builderDocument,
        selectedNodeId,
        activeInspectorTab,
        setResolvedSchemaKey,
        patchNodeProps,
        patchNodeStyles,
        setActiveInspectorTab,
    } = useBuilderStore(useShallow((state) => ({
        builderDocument: state.builderDocument,
        selectedNodeId: state.selectedNodeId,
        activeInspectorTab: state.activeInspectorTab,
        setResolvedSchemaKey: state.setResolvedSchemaKey,
        patchNodeProps: state.patchNodeProps,
        patchNodeStyles: state.patchNodeStyles,
        setActiveInspectorTab: state.setActiveInspectorTab,
    })));

    const selectedNode = selectedNodeId ? builderDocument.nodes[selectedNodeId] : null;
    const definition = getBuilderComponentDefinition(selectedNode?.componentKey ?? selectedNode?.type ?? null);
    const schema = definition?.schema ?? [];

    useEffect(() => {
        setResolvedSchemaKey(definition?.key ?? null);
    }, [definition?.key, setResolvedSchemaKey]);

    if (! selectedNode) {
        return (
            <div className="flex h-full items-center justify-center p-6 text-center text-sm text-slate-500">
                Select a node in the canvas or layers panel to edit its schema-driven fields.
            </div>
        );
    }

    if (schema.length === 0) {
        return (
            <div className="p-6 text-sm text-slate-500">
                No inspector schema is registered for this node.
            </div>
        );
    }

    const fields = schema.filter((field) => {
        if (activeInspectorTab === 'content') {
            return field.target !== 'styles';
        }

        return field.target === 'styles';
    });

    return (
        <div className="flex h-full flex-col">
            <div className="border-b border-slate-200 px-4 py-4">
                <div className="text-sm font-semibold text-slate-900">{resolveBuilderNodeLabel(selectedNode)}</div>
                <div className="mt-1 text-xs uppercase tracking-[0.2em] text-slate-400">{definition?.label ?? 'Unregistered component'}</div>

                <div className="mt-4 grid grid-cols-2 gap-2 rounded-full bg-slate-100 p-1">
                    {(['content', 'style'] as const).map((tab) => (
                        <button
                            key={tab}
                            type="button"
                            onClick={() => setActiveInspectorTab(tab)}
                            className={`rounded-full px-3 py-2 text-sm font-medium ${activeInspectorTab === tab ? 'bg-white shadow-sm' : 'text-slate-500'}`}
                        >
                            {tab === 'content' ? 'Content' : 'Styles'}
                        </button>
                    ))}
                </div>
            </div>

            <div className="flex-1 space-y-5 overflow-y-auto p-4">
                {fields.map((field) => {
                    const source = field.target === 'styles' ? (selectedNode.styles ?? {}) : selectedNode.props;
                    const value = source[field.key] ?? field.defaultValue ?? '';

                    return (
                        <div key={field.key} className="space-y-2">
                            <div>
                                <label className="text-sm font-medium text-slate-900">{field.label}</label>
                                {field.description ? (
                                    <p className="mt-1 text-xs text-slate-500">{field.description}</p>
                                ) : null}
                            </div>
                            <InspectorFieldRenderer
                                endpoints={endpoints}
                                field={field}
                                value={value}
                                onChange={(nextValue) => {
                                    if (field.target === 'styles') {
                                        patchNodeStyles(selectedNode.id, { [field.key]: nextValue }, `styles:${selectedNode.id}:${field.key}`);
                                    } else {
                                        patchNodeProps(selectedNode.id, { [field.key]: nextValue }, `props:${selectedNode.id}:${field.key}`);
                                    }
                                }}
                            />
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
