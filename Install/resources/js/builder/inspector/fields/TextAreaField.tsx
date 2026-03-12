import { Textarea } from '@/components/ui/textarea';

interface TextAreaFieldProps {
    value: string;
    placeholder?: string;
    onChange: (value: string) => void;
}

export function TextAreaField({ value, placeholder, onChange }: TextAreaFieldProps) {
    return <Textarea value={value} placeholder={placeholder} onChange={(event) => onChange(event.target.value)} rows={4} />;
}
