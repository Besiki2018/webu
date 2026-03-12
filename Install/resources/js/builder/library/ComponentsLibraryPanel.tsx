import { useMemo } from 'react';
import { useShallow } from 'zustand/shallow';
import { listBuilderComponentDefinitions } from '@/builder/components/registry';
import { useBuilderStore } from '@/builder/state/builderStore';
import { ComponentCard } from './ComponentCard';
import { createNodeFromComponentKey, resolveInsertParentId } from './componentInsertHelpers';

export function ComponentsLibraryPanel() {
    const {
        builderDocument,
        activePageId,
        selectedNodeId,
        insertNode,
    } = useBuilderStore(useShallow((state) => ({
        builderDocument: state.builderDocument,
        activePageId: state.activePageId,
        selectedNodeId: state.selectedNodeId,
        insertNode: state.insertNode,
    })));

    const groupedDefinitions = useMemo(() => {
        const groups = new Map<string, ReturnType<typeof listBuilderComponentDefinitions>>();
        for (const definition of listBuilderComponentDefinitions()) {
            const group = groups.get(definition.category) ?? [];
            group.push(definition);
            groups.set(definition.category, group);
        }

        return [...groups.entries()];
    }, []);

    return (
        <div className="flex h-full flex-col overflow-hidden">
            <div className="border-b border-slate-200 px-4 py-3">
                <div className="text-sm font-semibold text-slate-900">Components</div>
                <div className="text-xs text-slate-500">Insert registry-driven blocks into the active draft page.</div>
            </div>

            <div className="flex-1 space-y-6 overflow-y-auto p-4">
                {groupedDefinitions.map(([category, definitions]) => (
                    <section key={category}>
                        <h3 className="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{category}</h3>
                        <div className="grid gap-3">
                            {definitions.map((definition) => (
                                <ComponentCard
                                    key={definition.key}
                                    definition={definition}
                                    onInsert={(componentKey) => {
                                        const parentId = resolveInsertParentId(builderDocument, activePageId, selectedNodeId);
                                        insertNode(createNodeFromComponentKey(componentKey, parentId), parentId);
                                    }}
                                />
                            ))}
                        </div>
                    </section>
                ))}
            </div>
        </div>
    );
}
