export type BuilderNodeType = 'page' | 'section' | 'component' | 'slot' | 'text' | 'image' | 'button';

export interface BuilderNodeMeta {
    locked?: boolean;
    hidden?: boolean;
    label?: string;
}

export interface BuilderNode {
    id: string;
    type: BuilderNodeType;
    componentKey?: string;
    parentId: string | null;
    children: string[];
    props: Record<string, unknown>;
    styles?: Record<string, unknown>;
    bindings?: Record<string, unknown>;
    meta?: BuilderNodeMeta;
}
