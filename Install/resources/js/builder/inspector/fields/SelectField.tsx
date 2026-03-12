import type { BuilderFieldOption } from '@/builder/types/builderSchema';

interface SelectFieldProps {
    value: string;
    options: BuilderFieldOption[];
    onChange: (value: string) => void;
}

export function SelectField({ value, options, onChange }: SelectFieldProps) {
    return (
        <select
            value={value}
            onChange={(event) => onChange(event.target.value)}
            className="flex h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900"
        >
            {options.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    );
}
