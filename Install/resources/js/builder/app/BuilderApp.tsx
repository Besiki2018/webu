import type { BuilderDocument } from '@/builder/types/builderDocument';
import type { BuilderApiEndpoints } from '@/builder/api/builderApi';
import { BuilderLayout } from './BuilderLayout';
import { BuilderProviders } from './BuilderProviders';

interface BuilderAppProps {
    project: {
        id: string;
        name: string;
        subdomain?: string | null;
        published_at?: string | null;
    };
    initialDocument: BuilderDocument;
    publishedDocument?: BuilderDocument | null;
    endpoints: BuilderApiEndpoints;
}

export function BuilderApp({
    project,
    initialDocument,
    publishedDocument = null,
    endpoints,
}: BuilderAppProps) {
    return (
        <BuilderProviders initialDocument={initialDocument} publishedDocument={publishedDocument} endpoints={endpoints}>
            <BuilderLayout project={project} endpoints={endpoints} />
        </BuilderProviders>
    );
}
