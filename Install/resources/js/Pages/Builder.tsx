import AdminLayout from '@/Layouts/AdminLayout';
import { BuilderApp } from '@/builder/app/BuilderApp';
import type { BuilderDocument } from '@/builder/types/builderDocument';
import type { BuilderApiEndpoints } from '@/builder/api/builderApi';
import type { PageProps } from '@/types';

interface BuilderPageProps extends PageProps {
    project: {
        id: string;
        name: string;
        subdomain?: string | null;
        published_at?: string | null;
    };
    site: {
        id: string;
        name: string;
        locale: string;
        status: string;
    };
    builderDocument: BuilderDocument;
    publishedBuilderDocument?: BuilderDocument | null;
    builderApi: BuilderApiEndpoints;
}

export default function BuilderPage({
    auth,
    project,
    builderDocument,
    publishedBuilderDocument,
    builderApi,
}: BuilderPageProps) {
    return (
        <AdminLayout user={auth.user} title={`${project.name} Builder`} hideChrome fullWidth variant="cms">
            <BuilderApp
                project={project}
                initialDocument={builderDocument}
                publishedDocument={publishedBuilderDocument}
                endpoints={builderApi}
            />
        </AdminLayout>
    );
}
