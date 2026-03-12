import { Input } from '@/components/ui/input';

interface LinkFieldProps {
    value: string;
    onChange: (value: string) => void;
}

export function LinkField({ value, onChange }: LinkFieldProps) {
    return <Input type="url" value={value} placeholder="https://example.com" onChange={(event) => onChange(event.target.value)} />;
}
