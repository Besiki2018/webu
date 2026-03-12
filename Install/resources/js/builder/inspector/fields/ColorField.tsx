interface ColorFieldProps {
    value: string;
    onChange: (value: string) => void;
}

export function ColorField({ value, onChange }: ColorFieldProps) {
    return (
        <div className="flex items-center gap-3">
            <input
                type="color"
                value={value || '#000000'}
                onChange={(event) => onChange(event.target.value)}
                className="h-10 w-12 rounded-lg border border-slate-200 bg-white p-1"
            />
            <input
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="flex h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm"
            />
        </div>
    );
}
