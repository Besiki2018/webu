import type { ComponentType } from 'react';
import type { BuilderFieldSchema } from './builderSchema';

export interface BuilderComponentDefinition {
    key: string;
    label: string;
    category: string;
    icon?: string;
    defaultProps: Record<string, unknown>;
    schema: BuilderFieldSchema[];
    renderer: ComponentType<Record<string, unknown>>;
    allowedChildren?: string[];
    slots?: Array<{ key: string; label: string }>;
}
