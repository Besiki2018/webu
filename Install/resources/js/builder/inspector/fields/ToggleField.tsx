import { Switch } from '@/components/ui/switch';

interface ToggleFieldProps {
    value: boolean;
    onChange: (value: boolean) => void;
}

export function ToggleField({ value, onChange }: ToggleFieldProps) {
    return <Switch checked={value} onCheckedChange={onChange} />;
}
