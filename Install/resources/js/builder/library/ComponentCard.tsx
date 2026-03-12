import { Button } from '@/components/ui/button';
import type { BuilderComponentDefinition } from '@/builder/types/builderComponent';

interface ComponentCardProps {
    definition: BuilderComponentDefinition;
    onInsert: (componentKey: string) => void;
}

export function ComponentCard({ definition, onInsert }: ComponentCardProps) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="text-sm font-semibold text-slate-900">{definition.label}</div>
            <div className="mt-1 text-xs uppercase tracking-[0.2em] text-slate-400">{definition.category}</div>
            <Button type="button" className="mt-4 w-full" onClick={() => onInsert(definition.key)}>
                Insert
            </Button>
        </div>
    );
}
