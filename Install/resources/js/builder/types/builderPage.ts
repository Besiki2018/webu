export interface BuilderPage {
    id: string;
    title: string;
    slug: string;
    rootNodeId: string;
    status: 'draft' | 'published';
}
