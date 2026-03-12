import { Input } from '@/components/ui/input';

interface SpacingFieldProps {
    value: string;
    onChange: (value: string) => void;
}

export function SpacingField({ value, onChange }: SpacingFieldProps) {
    return <Input value={value} placeholder="32px 24px" onChange={(event) => onChange(event.target.value)} />;
}
