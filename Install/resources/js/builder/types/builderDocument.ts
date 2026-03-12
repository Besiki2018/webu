import type { BuilderNode } from './builderNode';
import type { BuilderPage } from './builderPage';

export interface BuilderDocument {
    projectId: string;
    pages: Record<string, BuilderPage>;
    nodes: Record<string, BuilderNode>;
    rootPageId: string;
    version: number;
    updatedAt?: string | null;
    publishedAt?: string | null;
}
