import { useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import { useTranslation } from '@/contexts/LanguageContext';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Bot, Server, Loader2, Coins, Key, FolderOpen, Globe, Database, HardDrive, ShoppingCart } from 'lucide-react';
import FeatureManager, { type PlanFeature } from './FeatureManager';
import type { BillingPeriod } from '@/types/billing';

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

interface DomainSettings {
    subdomainsEnabled: boolean;
    customDomainsEnabled: boolean;
}

type PlanFieldToggleKey =
    | 'enable_subdomains'
    | 'enable_custom_domains'
    | 'allow_private_visibility'
    | 'enable_firebase'
    | 'allow_user_firebase_config'
    | 'enable_file_storage'
    | 'allow_user_ai_api_key'
    | 'enable_ecommerce'
    | 'enable_online_payments'
    | 'enable_installments'
    | 'enable_shipping'
    | 'enable_booking'
    | 'enable_booking_prepayment'
    | 'enable_custom_fonts';

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

interface PlanModuleAddonInput {
    code: string;
    name: string;
    addon_group: string;
    is_active: boolean;
    sort_order: number;
}

interface PlanFormProps {
    plan?: Plan;
    aiProviders: AiProvider[];
    builders: Builder[];
    planModules: PlanModule[];
    domainSettings?: DomainSettings;
    onCancel: () => void;
}

function formatProviderAllowlist(value: string[] | null | undefined): string {
    if (!Array.isArray(value) || value.length === 0) {
        return '';
    }

    return value.join(', ');
}

function parseProviderAllowlist(value: string): string[] | null {
    const normalized = value
        .split(',')
        .map((item) => item.trim().toLowerCase().replace(/[^a-z0-9._-]+/g, '-').replace(/^[-._]+|[-._]+$/g, ''))
        .filter((item) => item.length > 0);

    if (normalized.length === 0) {
        return null;
    }

    return Array.from(new Set(normalized));
}

function normalizeModuleCode(value: string): string {
    return value
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9._-]+/g, '-')
        .replace(/^[-._]+|[-._]+$/g, '');
}

function labelForModuleGroup(group: string): string {
    const normalized = group.trim().toLowerCase();

    switch (normalized) {
        case 'commerce':
            return 'Commerce';
        case 'commerce_advanced':
            return 'Commerce Advanced';
        case 'booking':
            return 'Booking';
        case 'booking_advanced':
            return 'Booking Advanced';
        case 'publishing':
            return 'Publishing';
        case 'integrations':
            return 'Integrations';
        case 'cms':
            return 'CMS';
        case 'design':
            return 'Design';
        case 'ai':
            return 'AI';
        default:
            return normalized
                .replace(/[_-]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .replace(/\b\w/g, (char) => char.toUpperCase());
    }
}

export default function PlanForm({ plan, aiProviders, builders, planModules, domainSettings, onCancel }: PlanFormProps) {
    const { t } = useTranslation();
    const isEdit = !!plan;
    const initialModuleAddons = useMemo<PlanModuleAddonInput[]>(() => {
        const addOns: PlanModuleAddonInput[] = [];
        const seen = new Set<string>();

        planModules
            .slice()
            .sort((a, b) => a.sort_order - b.sort_order)
            .forEach((module) => {
                const addonCode = module.addon_code ? normalizeModuleCode(module.addon_code) : '';
                if (addonCode === '' || seen.has(addonCode)) {
                    return;
                }
                seen.add(addonCode);

                addOns.push({
                    code: addonCode,
                    name: module.label,
                    addon_group: module.group || 'module',
                    is_active: Boolean(module.is_active),
                    sort_order: module.sort_order,
                });
            });

        return addOns;
    }, [planModules]);
    const [isUnlimitedCredits, setIsUnlimitedCredits] = useState(
        plan?.monthly_build_credits === -1
    );
    const [allowedPaymentProvidersInput, setAllowedPaymentProvidersInput] = useState(
        formatProviderAllowlist(plan?.allowed_payment_providers)
    );
    const [allowedInstallmentProvidersInput, setAllowedInstallmentProvidersInput] = useState(
        formatProviderAllowlist(plan?.allowed_installment_providers)
    );
    const [allowedCourierProvidersInput, setAllowedCourierProvidersInput] = useState(
        formatProviderAllowlist(plan?.allowed_courier_providers)
    );
    const [allowedTypographyFontKeysInput, setAllowedTypographyFontKeysInput] = useState(
        formatProviderAllowlist(plan?.allowed_typography_font_keys)
    );

    const { data, setData, post, put, processing, errors } = useForm({
        name: plan?.name ?? '',
        description: plan?.description ?? '',
        price: plan?.price ?? 0,
        billing_period: plan?.billing_period ?? 'monthly' as BillingPeriod,
        features: plan?.features ?? [] as PlanFeature[],
        is_active: plan?.is_active ?? true,
        is_popular: plan?.is_popular ?? false,
        ai_provider_id: plan?.ai_provider_id ?? null as number | null,
        fallback_ai_provider_ids: plan?.fallback_ai_provider_ids ?? [] as number[],
        builder_id: plan?.builder_id ?? null as number | null,
        monthly_build_credits: plan?.monthly_build_credits ?? 0,
        allow_user_ai_api_key: plan?.allow_user_ai_api_key ?? false,
        max_projects: plan?.max_projects ?? null as number | null,
        // Subdomain settings
        enable_subdomains: plan?.enable_subdomains ?? false,
        max_subdomains_per_user: plan?.max_subdomains_per_user ?? null as number | null,
        allow_private_visibility: plan?.allow_private_visibility ?? false,
        // Custom domain settings
        enable_custom_domains: plan?.enable_custom_domains ?? false,
        max_custom_domains_per_user: plan?.max_custom_domains_per_user ?? null as number | null,
        // Firebase settings
        enable_firebase: plan?.enable_firebase ?? false,
        allow_user_firebase_config: plan?.allow_user_firebase_config ?? false,
        // File storage settings
        enable_file_storage: plan?.enable_file_storage ?? false,
        enable_booking_prepayment: plan?.enable_booking_prepayment ?? false,
        enable_ecommerce: plan?.enable_ecommerce ?? true,
        enable_booking: plan?.enable_booking ?? true,
        max_products: plan?.max_products ?? null as number | null,
        max_monthly_orders: plan?.max_monthly_orders ?? null as number | null,
        max_monthly_bookings: plan?.max_monthly_bookings ?? null as number | null,
        enable_online_payments: plan?.enable_online_payments ?? true,
        enable_installments: plan?.enable_installments ?? true,
        allowed_payment_providers: plan?.allowed_payment_providers ?? null as string[] | null,
        allowed_installment_providers: plan?.allowed_installment_providers ?? null as string[] | null,
        enable_shipping: plan?.enable_shipping ?? true,
        allowed_courier_providers: plan?.allowed_courier_providers ?? null as string[] | null,
        enable_custom_fonts: plan?.enable_custom_fonts ?? true,
        allowed_typography_font_keys: plan?.allowed_typography_font_keys ?? null as string[] | null,
        module_addons: initialModuleAddons,
        max_storage_mb: plan?.max_storage_mb ?? null as number | null,
        max_file_size_mb: plan?.max_file_size_mb ?? 10,
        allowed_file_types: plan?.allowed_file_types ?? null as string[] | null,
    });

    const moduleAddonStateByCode = useMemo(() => {
        const map = new Map<string, PlanModuleAddonInput>();
        (data.module_addons ?? []).forEach((addon) => {
            const code = normalizeModuleCode(addon.code);
            if (code === '') {
                return;
            }
            map.set(code, addon);
        });

        return map;
    }, [data.module_addons]);

    const groupedPlanModules = useMemo(() => {
        const groups = new Map<string, PlanModule[]>();
        const sorted = [...planModules].sort((a, b) => a.sort_order - b.sort_order);

        sorted.forEach((module) => {
            const key = module.group || 'general';
            const current = groups.get(key) ?? [];
            current.push(module);
            groups.set(key, current);
        });

        return Array.from(groups.entries()).map(([group, modules]) => ({
            group,
            label: labelForModuleGroup(group),
            modules,
        }));
    }, [planModules]);

    const getPlanFieldState = (field: PlanFieldToggleKey): boolean => {
        return Boolean((data as Record<string, unknown>)[field]);
    };

    const setPlanFieldState = (field: PlanFieldToggleKey, value: boolean) => {
        setData(field, value);
    };

    const setModuleAddonState = (addonCode: string, isActive: boolean, module: PlanModule) => {
        const normalizedAddonCode = normalizeModuleCode(addonCode);
        if (normalizedAddonCode === '') {
            return;
        }

        const current = Array.isArray(data.module_addons) ? data.module_addons : [];
        const next = [...current];
        const index = next.findIndex((item) => normalizeModuleCode(item.code) === normalizedAddonCode);
        const payload: PlanModuleAddonInput = {
            code: normalizedAddonCode,
            name: module.label,
            addon_group: module.group || 'module',
            is_active: isActive,
            sort_order: module.sort_order,
        };

        if (index === -1) {
            next.push(payload);
        } else {
            next[index] = {
                ...next[index],
                ...payload,
            };
        }

        setData('module_addons', next);
    };

    const moduleToggleChecked = (module: PlanModule): boolean => {
        const planFieldRaw = (module.plan_field || '').trim();
        const planField = planFieldRaw !== '' ? (planFieldRaw as PlanFieldToggleKey) : null;
        const addonCode = normalizeModuleCode(module.addon_code || '');
        const hasPlanField = planField !== null;
        const hasAddonCode = addonCode !== '';

        const planFieldEnabled = planField ? getPlanFieldState(planField) : Boolean(module.default_active);
        const addonEnabled = hasAddonCode
            ? Boolean(moduleAddonStateByCode.get(addonCode)?.is_active ?? module.is_active ?? module.default_active)
            : true;

        if (hasPlanField && hasAddonCode) {
            return planFieldEnabled && addonEnabled;
        }
        if (hasPlanField) {
            return planFieldEnabled;
        }
        if (hasAddonCode) {
            return addonEnabled;
        }

        return Boolean(module.is_active ?? module.default_active);
    };

    const applyPlanFieldDependencies = (field: PlanFieldToggleKey, checked: boolean) => {
        if (field === 'enable_ecommerce' && !checked) {
            setData('enable_online_payments', false);
            setData('enable_installments', false);
            setData('enable_shipping', false);
            setData('allowed_payment_providers', null);
            setData('allowed_installment_providers', null);
            setData('allowed_courier_providers', null);
            setAllowedPaymentProvidersInput('');
            setAllowedInstallmentProvidersInput('');
            setAllowedCourierProvidersInput('');
        }

        if (field === 'enable_online_payments' && !checked) {
            setData('enable_installments', false);
            setData('allowed_payment_providers', null);
            setData('allowed_installment_providers', null);
            setAllowedPaymentProvidersInput('');
            setAllowedInstallmentProvidersInput('');
        }

        if (field === 'enable_installments' && !checked) {
            setData('allowed_installment_providers', null);
            setAllowedInstallmentProvidersInput('');
        }

        if (field === 'enable_shipping' && !checked) {
            setData('allowed_courier_providers', null);
            setAllowedCourierProvidersInput('');
        }
    };

    const handleModuleToggle = (module: PlanModule, checked: boolean) => {
        const planFieldRaw = (module.plan_field || '').trim();
        const planField = planFieldRaw !== '' ? (planFieldRaw as PlanFieldToggleKey) : null;
        const addonCode = normalizeModuleCode(module.addon_code || '');

        if (planField) {
            setPlanFieldState(planField, checked);
            applyPlanFieldDependencies(planField, checked);
        }

        if (addonCode !== '') {
            setModuleAddonState(addonCode, checked, module);
        }
    };

    const findModuleByAddonCode = (addonCode: string): PlanModule | null => {
        const normalizedAddonCode = normalizeModuleCode(addonCode);
        if (normalizedAddonCode === '') {
            return null;
        }

        return planModules.find((module) => normalizeModuleCode(module.addon_code || '') === normalizedAddonCode) ?? null;
    };

    const syncAddonToggle = (addonCode: string, checked: boolean) => {
        const module = findModuleByAddonCode(addonCode);
        if (!module) {
            return;
        }

        setModuleAddonState(addonCode, checked, module);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isEdit) {
            put(route('admin.plans.update', plan.id), {
                onSuccess: () => toast.success(t('Plan updated successfully')),
                onError: () => toast.error(t('Failed to update plan')),
            });
        } else {
            post(route('admin.plans.store'), {
                onSuccess: () => toast.success(t('Plan created successfully')),
                onError: () => toast.error(t('Failed to create plan')),
            });
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Left Column */}
                <div className="space-y-6">
                    {/* Basic Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('Basic Information')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">{t('Plan Name')} *</Label>
                                <Input
                                    id="name"
                                    placeholder={t('e.g. Pro, Business, Enterprise')}
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className={errors.name ? 'border-destructive' : ''}
                                />
                                {errors.name && (
                                    <p className="text-sm text-destructive">{errors.name}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">{t('Description')}</Label>
                                <Textarea
                                    id="description"
                                    placeholder={t('Brief description of the plan')}
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    rows={2}
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="price">{t('Price')} *</Label>
                                    <Input
                                        id="price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="9.99"
                                        value={data.price}
                                        onChange={(e) => setData('price', parseFloat(e.target.value) || 0)}
                                        className={errors.price ? 'border-destructive' : ''}
                                    />
                                    {errors.price && (
                                        <p className="text-sm text-destructive">{errors.price}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="billing_period">{t('Billing Period')} *</Label>
                                    <Select
                                        value={data.billing_period}
                                        onValueChange={(value: BillingPeriod) => setData('billing_period', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="monthly">{t('Monthly')}</SelectItem>
                                            <SelectItem value="yearly">{t('Yearly')}</SelectItem>
                                            <SelectItem value="lifetime">{t('Lifetime')}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Display Options */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('Display Options')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between p-4 border rounded-lg">
                                <div className="space-y-0.5">
                                    <Label>{t('Active')}</Label>
                                    <p className="text-sm text-muted-foreground">
                                        {t('Plan is visible and available for purchase')}
                                    </p>
                                </div>
                                <Switch
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData('is_active', checked)}
                                />
                            </div>

                            <div className="flex items-center justify-between p-4 border rounded-lg">
                                <div className="space-y-0.5">
                                    <Label>{t('Mark as Popular')}</Label>
                                    <p className="text-sm text-muted-foreground">
                                        {t('Highlight this plan as the recommended option')}
                                    </p>
                                </div>
                                <Switch
                                    checked={data.is_popular}
                                    onCheckedChange={(checked) => setData('is_popular', checked)}
                                />
                            </div>

                            <div className="flex items-center justify-between p-4 border rounded-lg">
                                <div className="space-y-0.5">
                                    <Label>{t('Booking Prepayment')}</Label>
                                    <p className="text-sm text-muted-foreground">
                                        {t('Allow booking prepayment for this plan')}
                                    </p>
                                </div>
                                <Switch
                                    checked={data.enable_booking_prepayment}
                                    onCheckedChange={(checked) => setData('enable_booking_prepayment', checked)}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Project Limit */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <FolderOpen className="h-4 w-4" />
                                {t('Project Limit')}
                            </CardTitle>
                            <CardDescription>
                                {t('Set the maximum number of projects users can create')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between p-4 border rounded-lg">
                                <div className="space-y-0.5">
                                    <Label>{t('Unlimited Projects')}</Label>
                                    <p className="text-sm text-muted-foreground">
                                        {t('Allow users to create unlimited projects')}
                                    </p>
                                </div>
                                <Switch
                                    checked={data.max_projects === null}
                                    onCheckedChange={(checked) => {
                                        setData('max_projects', checked ? null : 1);
                                    }}
                                />
                            </div>

                            {data.max_projects !== null && (
                                <div className="space-y-2">
                                    <Label htmlFor="max_projects">{t('Maximum Projects')}</Label>
                                    <Input
                                        id="max_projects"
                                        type="number"
                                        min="0"
                                        placeholder="3"
                                        value={data.max_projects}
                                        onChange={(e) => setData('max_projects', parseInt(e.target.value) || 0)}
                                        className={errors.max_projects ? 'border-destructive' : ''}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {t('Set to 0 for no projects allowed')}
                                    </p>
                                    {errors.max_projects && (
                                        <p className="text-sm text-destructive">{errors.max_projects}</p>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Module Entitlements Matrix */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('Module Entitlements')}</CardTitle>
                            <CardDescription>
                                {t('Manage all available modules in one place for this plan')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {groupedPlanModules.map((group) => (
                                <div key={group.group} className="space-y-3">
                                    <div className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                        {t(group.label)}
                                    </div>
                                    <div className="space-y-2">
                                        {group.modules.map((module) => (
                                            <div key={module.code} className="flex items-center justify-between p-3 border rounded-lg">
                                                <div className="space-y-0.5 pe-3">
                                                    <Label>{t(module.label)}</Label>
                                                    <p className="text-xs text-muted-foreground">
                                                        {t(module.description)}
                                                    </p>
                                                </div>
                                                <Switch
                                                    checked={moduleToggleChecked(module)}
                                                    onCheckedChange={(checked) => handleModuleToggle(module, checked)}
                                                />
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    {/* Commerce & Booking Limits */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <ShoppingCart className="h-4 w-4" />
                                {t('Commerce & Booking Limits')}
                            </CardTitle>
                            <CardDescription>
                                {t('Enable modules and enforce hard usage caps on backend')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between p-4 border rounded-lg">
                                <div className="space-y-0.5">
                                    <Label>{t('Enable Ecommerce Module')}</Label>
                                    <p className="text-sm text-muted-foreground">
                                        {t('Allow ecommerce APIs and panel actions for this plan')}
                                    </p>
                                </div>
                                <Switch
                                    checked={data.enable_ecommerce}
                                    onCheckedChange={(checked) => {
                                        setData('enable_ecommerce', checked);
                                        syncAddonToggle('ecommerce', checked);
                                        if (!checked) {
                                            setData('enable_online_payments', false);
                                            setData('enable_installments', false);
                                            setData('allowed_payment_providers', null);
                                            setData('allowed_installment_providers', null);
                                            setData('enable_shipping', false);
                                            setData('allowed_courier_providers', null);
                                            setAllowedPaymentProvidersInput('');
                                            setAllowedInstallmentProvidersInput('');
                                            setAllowedCourierProvidersInput('');
                                            syncAddonToggle('payments-installments', false);
                                            syncAddonToggle('shipping', false);
                                            syncAddonToggle('inventory', false);
                                            syncAddonToggle('accounting', false);
                                            syncAddonToggle('rs-integration', false);
                                        }
                                    }}
                                />
                            </div>

                            {data.enable_ecommerce && (
                                <>
                                    <div className="flex items-center justify-between p-4 border rounded-lg">
                                        <div className="space-y-0.5">
                                            <Label>{t('Enable Online Payments')}</Label>
                                            <p className="text-sm text-muted-foreground">
                                                {t('Allow checkout through online payment providers')}
                                            </p>
                                        </div>
                                        <Switch
                                            checked={data.enable_online_payments}
                                            onCheckedChange={(checked) => {
                                                setData('enable_online_payments', checked);
                                                syncAddonToggle('payments-installments', checked);
                                                if (!checked) {
                                                    setData('enable_installments', false);
                                                    setData('allowed_payment_providers', null);
                                                    setData('allowed_installment_providers', null);
                                                    setAllowedPaymentProvidersInput('');
                                                    setAllowedInstallmentProvidersInput('');
                                                }
                                            }}
                                        />
                                    </div>

                                    {data.enable_online_payments && (
                                        <>
                                            <div className="flex items-center justify-between p-4 border rounded-lg">
                                                <div className="space-y-0.5">
                                                    <Label>{t('Enable Installments')}</Label>
                                                    <p className="text-sm text-muted-foreground">
                                                        {t('Allow installment checkout for this plan')}
                                                    </p>
                                                </div>
                                                <Switch
                                                    checked={data.enable_installments}
                                                    onCheckedChange={(checked) => {
                                                        setData('enable_installments', checked);
                                                        if (!checked) {
                                                            setData('allowed_installment_providers', null);
                                                            setAllowedInstallmentProvidersInput('');
                                                        }
                                                    }}
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="allowed_payment_providers">
                                                    {t('Allowed Payment Providers (optional)')}
                                                </Label>
                                                <Input
                                                    id="allowed_payment_providers"
                                                    placeholder="bank-of-georgia, fleet"
                                                    value={allowedPaymentProvidersInput}
                                                    onChange={(e) => {
                                                        const value = e.target.value;
                                                        setAllowedPaymentProvidersInput(value);
                                                        setData('allowed_payment_providers', parseProviderAllowlist(value));
                                                    }}
                                                    className={errors.allowed_payment_providers ? 'border-destructive' : ''}
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    {t('Comma-separated provider slugs. Leave empty to allow all providers.')}
                                                </p>
                                                {errors.allowed_payment_providers && (
                                                    <p className="text-sm text-destructive">{errors.allowed_payment_providers}</p>
                                                )}
                                            </div>

                                            {data.enable_installments && (
                                                <div className="space-y-2">
                                                    <Label htmlFor="allowed_installment_providers">
                                                        {t('Allowed Installment Providers (optional)')}
                                                    </Label>
                                                    <Input
                                                        id="allowed_installment_providers"
                                                        placeholder="fleet"
                                                        value={allowedInstallmentProvidersInput}
                                                        onChange={(e) => {
                                                            const value = e.target.value;
                                                            setAllowedInstallmentProvidersInput(value);
                                                            setData('allowed_installment_providers', parseProviderAllowlist(value));
                                                        }}
                                                        className={errors.allowed_installment_providers ? 'border-destructive' : ''}
                                                    />
                                                    <p className="text-xs text-muted-foreground">
                                                        {t('Comma-separated provider slugs. Leave empty to allow installments on all allowed payment providers.')}
                                                    </p>
                                                    {errors.allowed_installment_providers && (
                                                        <p className="text-sm text-destructive">{errors.allowed_installment_providers}</p>
                                                    )}
                                                </div>
                                            )}
                                        </>
                                    )}

                                    <div className="flex items-center justify-between p-4 border rounded-lg">
                                        <div className="space-y-0.5">
                                            <Label>{t('Enable Shipping Methods')}</Label>
                                            <p className="text-sm text-muted-foreground">
                                                {t('Allow courier rates and shipping selection during checkout')}
                                            </p>
                                        </div>
                                        <Switch
                                            checked={data.enable_shipping}
                                            onCheckedChange={(checked) => {
                                                setData('enable_shipping', checked);
                                                syncAddonToggle('shipping', checked);
                                                if (!checked) {
                                                    setData('allowed_courier_providers', null);
                                                    setAllowedCourierProvidersInput('');
                                                }
                                            }}
                                        />
                                    </div>

                                    {data.enable_shipping && (
                                        <div className="space-y-2">
                                            <Label htmlFor="allowed_courier_providers">
                                                {t('Allowed Courier Providers (optional)')}
                                            </Label>
                                            <Input
                                                id="allowed_courier_providers"
                                                placeholder="manual-courier"
                                                value={allowedCourierProvidersInput}
                                                onChange={(e) => {
                                                    const value = e.target.value;
                                                    setAllowedCourierProvidersInput(value);
                                                    setData('allowed_courier_providers', parseProviderAllowlist(value));
                                                }}
                                                className={errors.allowed_courier_providers ? 'border-destructive' : ''}
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                {t('Comma-separated courier slugs. Leave empty to allow all shipping providers.')}
                                            </p>
                                            {errors.allowed_courier_providers && (
                                                <p className="text-sm text-destructive">{errors.allowed_courier_providers}</p>
                                            )}
                                        </div>
                                    )}

                                    <div className="flex items-center justify-between p-4 border rounded-lg">
                                        <div className="space-y-0.5">
                                            <Label>{t('Unlimited Products')}</Label>
                                            <p className="text-sm text-muted-foreground">
                                                {t('Remove product count cap for this plan')}
                                            </p>
                                        </div>
                                        <Switch
                                            checked={data.max_products === null}
                                            onCheckedChange={(checked) => setData('max_products', checked ? null : 100)}
                                        />
                                    </div>
                                    {data.max_products !== null && (
                                        <div className="space-y-2">
                                            <Label htmlFor="max_products">{t('Maximum Products')}</Label>
                                            <Input
                                                id="max_products"
                                                type="number"
                                                min="0"
                                                placeholder="100"
                                                value={data.max_products ?? ''}
                                                onChange={(e) => setData('max_products', parseInt(e.target.value) || 0)}
                                                className={errors.max_products ? 'border-destructive' : ''}
                                            />
                                            {errors.max_products && (
                                                <p className="text-sm text-destructive">{errors.max_products}</p>
                                            )}
                                        </div>
                                    )}

                                    <div className="flex items-center justify-between p-4 border rounded-lg">
                                        <div className="space-y-0.5">
                                            <Label>{t('Unlimited Monthly Orders')}</Label>
                                            <p className="text-sm text-muted-foreground">
                                                {t('Remove monthly order cap for this plan')}
                                            </p>
                                        </div>
                                        <Switch
                                            checked={data.max_monthly_orders === null}
                                            onCheckedChange={(checked) => setData('max_monthly_orders', checked ? null : 1000)}
                                        />
                                    </div>
                                    {data.max_monthly_orders !== null && (
                                        <div className="space-y-2">
                                            <Label htmlFor="max_monthly_orders">{t('Maximum Monthly Orders')}</Label>
                                            <Input
                                                id="max_monthly_orders"
                                                type="number"
                                                min="0"
                                                placeholder="1000"
                                                value={data.max_monthly_orders ?? ''}
                                                onChange={(e) => setData('max_monthly_orders', parseInt(e.target.value) || 0)}
                                                className={errors.max_monthly_orders ? 'border-destructive' : ''}
                                            />
                                            {errors.max_monthly_orders && (
                                                <p className="text-sm text-destructive">{errors.max_monthly_orders}</p>
                                            )}
                                        </div>
                                    )}
                                </>
                            )}

                            <div className="flex items-center justify-between p-4 border rounded-lg">
                                <div className="space-y-0.5">
                                    <Label>{t('Enable Booking Module')}</Label>
                                    <p className="text-sm text-muted-foreground">
                                        {t('Allow booking APIs and panel actions for this plan')}
                                    </p>
                                </div>
                                <Switch
                                    checked={data.enable_booking}
                                    onCheckedChange={(checked) => {
                                        setData('enable_booking', checked);
                                        syncAddonToggle('booking', checked);
                                        if (!checked) {
                                            setData('enable_booking_prepayment', false);
                                            syncAddonToggle('booking-team-scheduling', false);
                                            syncAddonToggle('booking-finance', false);
                                            syncAddonToggle('booking-advanced-calendar', false);
                                        }
                                    }}
                                />
                            </div>

                            {data.enable_booking && (
                                <>
                                    <div className="flex items-center justify-between p-4 border rounded-lg">
                                        <div className="space-y-0.5">
                                            <Label>{t('Unlimited Monthly Bookings')}</Label>
                                            <p className="text-sm text-muted-foreground">
                                                {t('Remove monthly booking cap for this plan')}
                                            </p>
                                        </div>
                                        <Switch
                                            checked={data.max_monthly_bookings === null}
                                            onCheckedChange={(checked) => setData('max_monthly_bookings', checked ? null : 1000)}
                                        />
                                    </div>
                                    {data.max_monthly_bookings !== null && (
                                        <div className="space-y-2">
                                            <Label htmlFor="max_monthly_bookings">{t('Maximum Monthly Bookings')}</Label>
                                            <Input
                                                id="max_monthly_bookings"
                                                type="number"
                                                min="0"
                                                placeholder="1000"
                                                value={data.max_monthly_bookings ?? ''}
                                                onChange={(e) => setData('max_monthly_bookings', parseInt(e.target.value) || 0)}
                                                className={errors.max_monthly_bookings ? 'border-destructive' : ''}
                                            />
                                            {errors.max_monthly_bookings && (
                                                <p className="text-sm text-destructive">{errors.max_monthly_bookings}</p>
                                            )}
                                        </div>
                                    )}
                                </>
                            )}
                        </CardContent>
                    </Card>

                    {/* Typography Access */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('Typography Access')}</CardTitle>
                            <CardDescription>
                                {t('Control font-pack access and custom font uploads for this plan')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between p-4 border rounded-lg">
                                <div className="space-y-0.5">
                                    <Label>{t('Enable Custom Font Uploads')}</Label>
                                    <p className="text-sm text-muted-foreground">
                                        {t('Allow tenant-specific font uploads in CMS typography settings')}
                                    </p>
                                </div>
                                <Switch
                                    checked={data.enable_custom_fonts}
                                    onCheckedChange={(checked) => setData('enable_custom_fonts', checked)}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="allowed_typography_font_keys">
                                    {t('Allowed Typography Font Keys (optional)')}
                                </Label>
                                <Input
                                    id="allowed_typography_font_keys"
                                    placeholder="tbc-contractica, tbc-contractica-alt"
                                    value={allowedTypographyFontKeysInput}
                                    onChange={(e) => {
                                        const value = e.target.value;
                                        setAllowedTypographyFontKeysInput(value);
                                        setData('allowed_typography_font_keys', parseProviderAllowlist(value));
                                    }}
                                    className={errors.allowed_typography_font_keys ? 'border-destructive' : ''}
                                />
                                <p className="text-xs text-muted-foreground">
                                    {t('Comma-separated font keys. Leave empty to allow all registered fonts for the tenant.')}
                                </p>
                                {errors.allowed_typography_font_keys && (
                                    <p className="text-sm text-destructive">{errors.allowed_typography_font_keys}</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* User API Keys */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <Key className="h-4 w-4" />
                                {t('User API Keys')}
                            </CardTitle>
                            <CardDescription>
                                {t('Allow users to use their own AI provider API keys')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between p-4 border rounded-lg">
                                <div className="space-y-0.5">
                                    <Label>{t('Allow Own API Keys')}</Label>
                                    <p className="text-sm text-muted-foreground">
                                        {t('Users can configure their own API keys for AI providers')}
                                    </p>
                                </div>
                                <Switch
                                    checked={data.allow_user_ai_api_key}
                                    onCheckedChange={(checked) => setData('allow_user_ai_api_key', checked)}
                                />
                            </div>
                            {data.allow_user_ai_api_key && (
                                <p className="text-xs text-muted-foreground p-3 bg-muted rounded-md border">
                                    {t('When users provide their own API keys, usage will not be deducted from their credits')}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Custom Subdomains - only show if globally enabled */}
                    {domainSettings?.subdomainsEnabled !== false && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base flex items-center gap-2">
                                    <Globe className="h-4 w-4" />
                                    {t('Custom Subdomains')}
                                </CardTitle>
                                <CardDescription>
                                    {t('Allow users to publish projects to custom subdomains')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between p-4 border rounded-lg">
                                    <div className="space-y-0.5">
                                        <Label>{t('Enable Custom Subdomains')}</Label>
                                        <p className="text-sm text-muted-foreground">
                                            {t('Users can publish projects to custom subdomains')}
                                        </p>
                                    </div>
                                    <Switch
                                        checked={data.enable_subdomains}
                                        onCheckedChange={(checked) => setData('enable_subdomains', checked)}
                                    />
                                </div>

                                {data.enable_subdomains && (
                                    <>
                                        <div className="flex items-center justify-between p-4 border rounded-lg">
                                            <div className="space-y-0.5">
                                                <Label>{t('Unlimited Subdomains')}</Label>
                                                <p className="text-sm text-muted-foreground">
                                                    {t('Allow unlimited custom subdomains per user')}
                                                </p>
                                            </div>
                                            <Switch
                                                checked={data.max_subdomains_per_user === null}
                                                onCheckedChange={(checked) => {
                                                    setData('max_subdomains_per_user', checked ? null : 1);
                                                }}
                                            />
                                        </div>

                                        {data.max_subdomains_per_user !== null && (
                                            <div className="space-y-2">
                                                <Label htmlFor="max_subdomains">{t('Maximum Subdomains')}</Label>
                                                <Input
                                                    id="max_subdomains"
                                                    type="number"
                                                    min="0"
                                                    placeholder="5"
                                                    value={data.max_subdomains_per_user ?? ''}
                                                    onChange={(e) => setData('max_subdomains_per_user', parseInt(e.target.value) || 0)}
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    {t('Set maximum number of subdomains per user')}
                                                </p>
                                            </div>
                                        )}
                                    </>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* Custom Domains - only show if globally enabled */}
                    {domainSettings?.customDomainsEnabled !== false && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base flex items-center gap-2">
                                    <Globe className="h-4 w-4" />
                                    {t('Custom Domains')}
                                </CardTitle>
                                <CardDescription>
                                    {t('Allow users to use custom domains for their projects')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between p-4 border rounded-lg">
                                    <div className="space-y-0.5">
                                        <Label>{t('Enable Custom Domains')}</Label>
                                        <p className="text-sm text-muted-foreground">
                                            {t('Users can connect their own domains to projects')}
                                        </p>
                                    </div>
                                    <Switch
                                        checked={data.enable_custom_domains}
                                        onCheckedChange={(checked) => setData('enable_custom_domains', checked)}
                                    />
                                </div>

                                {data.enable_custom_domains && (
                                    <>
                                        <div className="flex items-center justify-between p-4 border rounded-lg">
                                            <div className="space-y-0.5">
                                                <Label>{t('Unlimited Custom Domains')}</Label>
                                                <p className="text-sm text-muted-foreground">
                                                    {t('Allow unlimited custom domains per user')}
                                                </p>
                                            </div>
                                            <Switch
                                                checked={data.max_custom_domains_per_user === null}
                                                onCheckedChange={(checked) => {
                                                    setData('max_custom_domains_per_user', checked ? null : 1);
                                                }}
                                            />
                                        </div>

                                        {data.max_custom_domains_per_user !== null && (
                                            <div className="space-y-2">
                                                <Label htmlFor="max_custom_domains">{t('Maximum Custom Domains')}</Label>
                                                <Input
                                                    id="max_custom_domains"
                                                    type="number"
                                                    min="0"
                                                    placeholder="3"
                                                    value={data.max_custom_domains_per_user ?? ''}
                                                    onChange={(e) => setData('max_custom_domains_per_user', parseInt(e.target.value) || 0)}
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    {t('Set maximum number of custom domains per user')}
                                                </p>
                                            </div>
                                        )}
                                    </>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* Private Visibility */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('Private Project Visibility')}
                            </CardTitle>
                            <CardDescription>
                                {t('Control whether users can make their projects private')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between p-4 border rounded-lg">
                                <div className="space-y-0.5">
                                    <Label>{t('Allow Private Visibility')}</Label>
                                    <p className="text-sm text-muted-foreground">
                                        {t('Users can set their projects to private (not publicly accessible)')}
                                    </p>
                                </div>
                                <Switch
                                    checked={data.allow_private_visibility}
                                    onCheckedChange={(checked) => setData('allow_private_visibility', checked)}
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Right Column */}
                <div className="space-y-6">
                    {/* AI Configuration */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <Bot className="h-4 w-4" />
                                {t('AI Provider')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="ai_provider_id">{t('Primary AI Provider')}</Label>
                                <Select
                                    value={data.ai_provider_id?.toString() ?? 'system_default'}
                                    onValueChange={(value) =>
                                        setData('ai_provider_id', value === 'system_default' ? null : parseInt(value))
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('System Default')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="system_default">{t('System Default')}</SelectItem>
                                        {aiProviders.map((provider) => (
                                            <SelectItem key={provider.id} value={provider.id.toString()}>
                                                {provider.name} ({provider.type})
                                                {provider.is_default && ` - ${t('Default')}`}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-xs text-muted-foreground">
                                    {t('Select which AI provider to use for this plan')}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Builder Configuration */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <Server className="h-4 w-4" />
                                {t('AI Builder')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="builder_id">{t('Primary Builder')}</Label>
                                <Select
                                    value={data.builder_id?.toString() ?? 'system_default'}
                                    onValueChange={(value) =>
                                        setData('builder_id', value === 'system_default' ? null : parseInt(value))
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('System Default')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="system_default">{t('System Default')}</SelectItem>
                                        {builders.map((builder) => (
                                            <SelectItem key={builder.id} value={builder.id.toString()}>
                                                {builder.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-xs text-muted-foreground">
                                    {t('Select which builder service to use for this plan')}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Build Credits */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <Coins className="h-4 w-4" />
                                {t('Build Credits')}
                            </CardTitle>
                            <CardDescription>
                                {t('Set the monthly AI usage credits for this plan')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between p-4 border rounded-lg">
                                <div className="space-y-0.5">
                                    <Label>{t('Unlimited Credits')}</Label>
                                    <p className="text-sm text-muted-foreground">
                                        {t('Allow unlimited AI usage')}
                                    </p>
                                </div>
                                <Switch
                                    checked={isUnlimitedCredits}
                                    onCheckedChange={(checked) => {
                                        setIsUnlimitedCredits(checked);
                                        setData('monthly_build_credits', checked ? -1 : 0);
                                    }}
                                />
                            </div>

                            {!isUnlimitedCredits && (
                                <div className="space-y-2">
                                    <Label htmlFor="monthly_build_credits">{t('Monthly Token Limit')}</Label>
                                    <Input
                                        id="monthly_build_credits"
                                        type="number"
                                        min="0"
                                        step="1000"
                                        placeholder="1000000"
                                        value={data.monthly_build_credits === -1 ? '' : data.monthly_build_credits}
                                        onChange={(e) => setData('monthly_build_credits', parseInt(e.target.value) || 0)}
                                        className={errors.monthly_build_credits ? 'border-destructive' : ''}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {t('Total AI tokens allowed per month (input + output)')}
                                    </p>
                                    {errors.monthly_build_credits && (
                                        <p className="text-sm text-destructive">{errors.monthly_build_credits}</p>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Firebase Database */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <Database className="h-4 w-4" />
                                {t('Firebase Database')}
                            </CardTitle>
                            <CardDescription>
                                {t('Configure Firebase Firestore database access for projects')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between p-4 border rounded-lg">
                                <div className="space-y-0.5">
                                    <Label>{t('Enable Firebase')}</Label>
                                    <p className="text-sm text-muted-foreground">
                                        {t('Allow projects to use Firebase Firestore database')}
                                    </p>
                                </div>
                                <Switch
                                    checked={data.enable_firebase}
                                    onCheckedChange={(checked) => setData('enable_firebase', checked)}
                                />
                            </div>

                            {data.enable_firebase && (
                                <div className="flex items-center justify-between p-4 border rounded-lg">
                                    <div className="space-y-0.5">
                                        <Label>{t('Allow Custom Firebase Config')}</Label>
                                        <p className="text-sm text-muted-foreground">
                                            {t('Users can configure their own Firebase project')}
                                        </p>
                                    </div>
                                    <Switch
                                        checked={data.allow_user_firebase_config}
                                        onCheckedChange={(checked) => setData('allow_user_firebase_config', checked)}
                                    />
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* File Storage */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <HardDrive className="h-4 w-4" />
                                {t('File Storage')}
                            </CardTitle>
                            <CardDescription>
                                {t('Configure file storage limits for projects')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between p-4 border rounded-lg">
                                <div className="space-y-0.5">
                                    <Label>{t('Enable File Storage')}</Label>
                                    <p className="text-sm text-muted-foreground">
                                        {t('Allow projects to upload and store files')}
                                    </p>
                                </div>
                                <Switch
                                    checked={data.enable_file_storage}
                                    onCheckedChange={(checked) => setData('enable_file_storage', checked)}
                                />
                            </div>

                            {data.enable_file_storage && (
                                <>
                                    <div className="flex items-center justify-between p-4 border rounded-lg">
                                        <div className="space-y-0.5">
                                            <Label>{t('Unlimited Storage')}</Label>
                                            <p className="text-sm text-muted-foreground">
                                                {t('Allow unlimited file storage per project')}
                                            </p>
                                        </div>
                                        <Switch
                                            checked={data.max_storage_mb === null}
                                            onCheckedChange={(checked) => {
                                                setData('max_storage_mb', checked ? null : 100);
                                            }}
                                        />
                                    </div>

                                    {data.max_storage_mb !== null && (
                                        <div className="space-y-2">
                                            <Label htmlFor="max_storage_mb">{t('Maximum Storage (MB)')}</Label>
                                            <Input
                                                id="max_storage_mb"
                                                type="number"
                                                min="0"
                                                placeholder="100"
                                                value={data.max_storage_mb}
                                                onChange={(e) => setData('max_storage_mb', parseInt(e.target.value) || 0)}
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                {t('Total storage limit per project in megabytes')}
                                            </p>
                                        </div>
                                    )}

                                    <div className="space-y-2">
                                        <Label htmlFor="max_file_size_mb">{t('Maximum File Size (MB)')}</Label>
                                        <Input
                                            id="max_file_size_mb"
                                            type="number"
                                            min="1"
                                            max="500"
                                            placeholder="10"
                                            value={data.max_file_size_mb}
                                            onChange={(e) => setData('max_file_size_mb', parseInt(e.target.value) || 10)}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            {t('Maximum size for individual file uploads')}
                                        </p>
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    {/* Features */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('Features')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FeatureManager
                                features={data.features}
                                onChange={(features) => setData('features', features)}
                                error={errors.features}
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Form Actions */}
            <div className="flex justify-end gap-4 pt-4 border-t">
                <Button type="button" variant="outline" onClick={onCancel}>
                    {t('Cancel')}
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing && <Loader2 className="h-4 w-4 me-2 animate-spin" />}
                    {isEdit ? t('Save Changes') : t('Create Plan')}
                </Button>
            </div>
        </form>
    );
}
