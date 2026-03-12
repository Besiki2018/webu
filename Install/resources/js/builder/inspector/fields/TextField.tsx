import { Input } from '@/components/ui/input';

interface TextFieldProps {
    value: string;
    placeholder?: string;
    onChange: (value: string) => void;
}

export function TextField({ value, placeholder, onChange }: TextFieldProps) {
    return <Input value={value} placeholder={placeholder} onChange={(event) => onChange(event.target.value)} />;
}
