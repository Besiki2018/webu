export type BuilderFieldControl =
    | 'text'
    | 'textarea'
    | 'select'
    | 'toggle'
    | 'image'
    | 'color'
    | 'spacing'
    | 'link';

export interface BuilderFieldOption {
    label: string;
    value: string;
}

export interface BuilderFieldSchema {
    key: string;
    label: string;
    control: BuilderFieldControl;
    target?: 'props' | 'styles' | 'meta';
    description?: string;
    placeholder?: string;
    defaultValue?: unknown;
    options?: BuilderFieldOption[];
}
