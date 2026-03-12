import { Link, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-react';
import PlanForm from './Partials/PlanForm';
import type { PageProps } from '@/types';
import { useTranslation } from '@/contexts/LanguageContext';

interface AiProvider {
    id: number;
    name: string;
    type: string;
    is_default: boolean;
}

interface Builder {
    id: number;
    name: string;
}

interface DomainSettings {
    subdomainsEnabled: boolean;
    customDomainsEnabled: boolean;
}

interface PlanModule {
    code: string;
    label: string;
    description: string;
    group: string;
    plan_field: string | null;
    addon_code: string | null;
    default_active: boolean;
    sort_order: number;
    is_active: boolean;
}

interface CreatePageProps extends PageProps {
    aiProviders: AiProvider[];
    builders: Builder[];
    planModules: PlanModule[];
    domainSettings?: DomainSettings;
}

export default function Create({ aiProviders, builders, planModules, domainSettings }: CreatePageProps) {
    const { auth } = usePage<CreatePageProps>().props;
    const { t } = useTranslation();

    const handleCancel = () => {
        window.history.back();
    };

    return (
        <AdminLayout user={auth.user!} title={t('Create Plan')}>
            <div className="flex items-center justify-between mb-6">
                <div className="prose prose-sm dark:prose-invert">
                    <h1 className="text-2xl font-bold text-foreground">
                        {t('Create Plan')}
                    </h1>
                    <p className="text-muted-foreground">{t('Add a new subscription plan')}</p>
                </div>
                <Button variant="outline" asChild>
                    <Link href="/admin/plans">
                        <ArrowLeft className="h-4 w-4 me-2" />
                        {t('Back')}
                    </Link>
                </Button>
            </div>

            <div>
                <PlanForm
                    aiProviders={aiProviders}
                    builders={builders}
                    planModules={planModules}
                    domainSettings={domainSettings}
                    onCancel={handleCancel}
                />
            </div>
        </AdminLayout>
    );
}
