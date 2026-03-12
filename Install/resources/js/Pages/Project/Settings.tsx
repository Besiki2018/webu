import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { ArrowLeft, FileText, FileDown } from 'lucide-react';
import { ProjectSettingsPanel } from '@/components/Project/ProjectSettingsPanel';
import AdminLayout from '@/Layouts/AdminLayout';
import type { PageProps } from '@/types';
import type { FirebaseConfig } from '@/types/storage';

interface Project {
    id: string;
    name: string;
    subdomain: string | null;
    published_title: string | null;
    published_description: string | null;
    published_visibility: string;
    share_image: string | null;
    custom_instructions: string | null;
    api_token: string | null;
    custom_domain: string | null;
    custom_domain_verified: boolean;
    custom_domain_ssl_status: string | null;
    domain_verification_token: string | null;
}

interface SubdomainUsage {
    used: number;
    limit: number | null;
    unlimited: boolean;
    remaining: number;
}

interface FirebaseSettings {
    enabled: boolean;
    canUseOwnConfig: boolean;
    usesSystemFirebase: boolean;
    customConfig: FirebaseConfig | null;
    systemConfigured: boolean;
    collectionPrefix: string;
    adminSdkConfigured: boolean;
    adminSdkStatus: {
        configured: boolean;
        is_system: boolean;
        project_id: string | null;
        client_email: string | null;
    };
}

interface StorageSettings {
    enabled: boolean;
    usedBytes: number;
    limitMb: number | null;
    unlimited: boolean;
}

interface CustomDomainUsage {
    used: number;
    limit: number | null;
    unlimited: boolean;
    remaining: number;
}

interface CustomDomainSettings {
    enabled: boolean;
    canCreateMore: boolean;
    usage: CustomDomainUsage;
    baseDomain: string | null;
}

interface ModuleRegistryItem {
    key: string;
    label: string;
    group: string;
    implemented: boolean;
    requested: boolean;
    globally_enabled: boolean;
    entitled: boolean;
    enabled: boolean;
    available: boolean;
    reason: string | null;
}

interface ModuleRegistryPayload {
    site_id: string;
    project_id: string;
    modules: ModuleRegistryItem[];
    summary: {
        total: number;
        available: number;
        disabled: number;
        not_entitled: number;
    };
}

interface SettingsProps {
    project: Project;
    baseDomain: string;
    canUseSubdomains: boolean;
    canCreateMoreSubdomains: boolean;
    canUsePrivateVisibility: boolean;
    subdomainUsage: SubdomainUsage;
    suggestedSubdomain: string;
    firebase?: FirebaseSettings;
    storage?: StorageSettings;
    customDomain?: CustomDomainSettings;
    subdomainsGloballyEnabled?: boolean;
    customDomainsGloballyEnabled?: boolean;
    moduleRegistry?: ModuleRegistryPayload;
}

export default function Settings({
    project,
    baseDomain,
    canUseSubdomains,
    canCreateMoreSubdomains,
    canUsePrivateVisibility,
    subdomainUsage,
    suggestedSubdomain,
    firebase,
    storage,
    customDomain,
    subdomainsGloballyEnabled,
    customDomainsGloballyEnabled,
    moduleRegistry,
}: SettingsProps) {
    const { auth } = usePage<PageProps>().props;

    return (
        <AdminLayout user={auth.user!} title={`Settings - ${project.name}`}>
            <Head title={`Settings - ${project.name}`} />

            <div className="space-y-4">
                <div className="rounded-lg border bg-background px-4 py-3 flex items-center justify-between">
                    <div>
                        <h1 className="text-sm font-semibold">{project.name}</h1>
                        <p className="text-xs text-muted-foreground">Project Settings</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => window.open(`/project/${project.id}/export-template`, '_blank', 'noopener')}
                        >
                            <FileDown className="h-4 w-4 mr-2" />
                            Export as template
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => window.open(`/project/${project.id}/export-template-pack`, '_blank', 'noopener')}
                        >
                            <FileDown className="h-4 w-4 mr-2" />
                            Export Template Pack (ZIP)
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/project/${project.id}/cms`}>
                                <FileText className="h-4 w-4 mr-2" />
                                CMS
                            </Link>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/project/${project.id}`}>
                                <ArrowLeft className="h-4 w-4 mr-2" />
                                Back
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="rounded-lg border bg-background overflow-hidden">
                    <ProjectSettingsPanel
                        project={project}
                        baseDomain={baseDomain}
                        canUseSubdomains={canUseSubdomains}
                        canCreateMoreSubdomains={canCreateMoreSubdomains}
                        canUsePrivateVisibility={canUsePrivateVisibility}
                        subdomainUsage={subdomainUsage}
                        suggestedSubdomain={suggestedSubdomain}
                        firebase={firebase}
                        storage={storage}
                        customDomain={customDomain}
                        subdomainsGloballyEnabled={subdomainsGloballyEnabled}
                        customDomainsGloballyEnabled={customDomainsGloballyEnabled}
                        moduleRegistry={moduleRegistry}
                    />
                </div>
            </div>
        </AdminLayout>
    );
}
