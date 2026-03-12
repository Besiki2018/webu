import { Link, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-react';
import PlanForm from './Partials/PlanForm';
import PricingConsole from './Partials/PricingConsole';
import type { PageProps } from '@/types';
import type { BillingPeriod } from '@/types/billing';
import { useTranslation } from '@/contexts/LanguageContext';

interface PlanFeature {
    name: string;
    included: boolean;
}

interface Plan {
    id: number;
    name: string;
    description: string | null;
    price: number;
    billing_period: BillingPeriod;
    features: PlanFeature[];
    is_active: boolean;
    is_popular: boolean;
    ai_provider_id: number | null;
    fallback_ai_provider_ids: number[] | null;
    builder_id: number | null;
    monthly_build_credits: number | null;
    allow_user_ai_api_key: boolean;
    max_projects: number | null;
    // Subdomain settings
    enable_subdomains: boolean;
    max_subdomains_per_user: number | null;
    allow_private_visibility: boolean;
    // Custom domain settings
    enable_custom_domains: boolean;
    max_custom_domains_per_user: number | null;
    // Firebase settings
    enable_firebase: boolean;
    allow_user_firebase_config: boolean;
    // File storage settings
    enable_file_storage: boolean;
    enable_booking_prepayment: boolean;
    enable_ecommerce: boolean;
    enable_booking: boolean;
    max_products: number | null;
    max_monthly_orders: number | null;
    max_monthly_bookings: number | null;
    enable_online_payments: boolean;
    enable_installments: boolean;
    allowed_payment_providers: string[] | null;
    allowed_installment_providers: string[] | null;
    enable_shipping: boolean;
    allowed_courier_providers: string[] | null;
    enable_custom_fonts: boolean;
    allowed_typography_font_keys: string[] | null;
    max_storage_mb: number | null;
    max_file_size_mb: number;
    allowed_file_types: string[] | null;
}

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

interface EditPageProps extends PageProps {
    plan: Plan;
    aiProviders: AiProvider[];
    builders: Builder[];
    planModules: PlanModule[];
    domainSettings?: DomainSettings;
}

export default function Edit({ plan, aiProviders, builders, planModules, domainSettings }: EditPageProps) {
    const { auth } = usePage<EditPageProps>().props;
    const { t } = useTranslation();

    const handleCancel = () => {
        window.history.back();
    };

    // Normalize features to new format if they're in old format (string[])
    const normalizedPlan = {
        ...plan,
        features: plan.features.map((feature) => {
            if (typeof feature === 'string') {
                return { name: feature, included: true };
            }
            return feature;
        }),
    };

    return (
        <AdminLayout user={auth.user!} title={t('Edit :name Plan', { name: plan.name })}>
            <div className="flex items-center justify-between mb-6">
                <div className="prose prose-sm dark:prose-invert">
                    <h1 className="text-2xl font-bold text-foreground">
                        {t('Edit :name Plan', { name: plan.name })}
                    </h1>
                    <p className="text-muted-foreground">{t('Update plan configuration')}</p>
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
                    plan={normalizedPlan}
                    aiProviders={aiProviders}
                    builders={builders}
                    planModules={planModules}
                    domainSettings={domainSettings}
                    onCancel={handleCancel}
                />
            </div>

            <div className="mt-6">
                <PricingConsole planId={plan.id} />
            </div>
        </AdminLayout>
    );
}
