import type { BuilderFieldSchema } from '@/builder/types/builderSchema';
import type { BuilderApiEndpoints } from '@/builder/api/builderApi';
import { ColorField } from './fields/ColorField';
import { ImagePickerField } from './fields/ImagePickerField';
import { LinkField } from './fields/LinkField';
import { SelectField } from './fields/SelectField';
import { SpacingField } from './fields/SpacingField';
import { TextAreaField } from './fields/TextAreaField';
import { TextField } from './fields/TextField';
import { ToggleField } from './fields/ToggleField';

interface InspectorFieldRendererProps {
    endpoints: BuilderApiEndpoints;
    field: BuilderFieldSchema;
    value: unknown;
    onChange: (value: unknown) => void;
}

export function InspectorFieldRenderer({ endpoints, field, value, onChange }: InspectorFieldRendererProps) {
    switch (field.control) {
        case 'textarea':
            return <TextAreaField value={typeof value === 'string' ? value : ''} placeholder={field.placeholder} onChange={onChange} />;
        case 'select':
            return <SelectField value={typeof value === 'string' ? value : ''} options={field.options ?? []} onChange={onChange} />;
        case 'toggle':
            return <ToggleField value={Boolean(value)} onChange={onChange} />;
        case 'image':
            return <ImagePickerField endpoints={endpoints} value={typeof value === 'string' ? value : ''} onChange={onChange} />;
        case 'color':
            return <ColorField value={typeof value === 'string' ? value : ''} onChange={onChange} />;
        case 'spacing':
            return <SpacingField value={typeof value === 'string' ? value : ''} onChange={onChange} />;
        case 'link':
            return <LinkField value={typeof value === 'string' ? value : ''} onChange={onChange} />;
        case 'text':
        default:
            return <TextField value={typeof value === 'string' ? value : ''} placeholder={field.placeholder} onChange={onChange} />;
    }
}
