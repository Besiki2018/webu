import { router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { AdminPageHeader } from '@/components/Admin/AdminPageHeader';
import { StatsCard } from '@/components/Admin/StatsCard';
import { AiUsageCard } from '@/components/Admin/AiUsageCard';
import { ReferralStatsCard } from '@/components/Admin/ReferralStatsCard';
import { StorageStatsCard } from '@/components/Admin/StorageStatsCard';
import { FirebaseStatusCard } from '@/components/Admin/FirebaseStatusCard';
import { SubscriptionPieChart } from '@/components/Admin/Charts/SubscriptionPieChart';
import { TrendLineChart } from '@/components/Admin/Charts/TrendLineChart';
import { AiUsageTrendChart } from '@/components/Admin/Charts/AiUsageTrendChart';
import { AiProviderPieChart } from '@/components/Admin/Charts/AiProviderPieChart';
import { OverviewSkeleton } from '@/components/Admin/OverviewSkeleton';
import { useAdminLoading } from '@/hooks/useAdminLoading';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Users,
    CreditCard,
    DollarSign,
    FolderOpen,
    TrendingUp,
    RefreshCw,
    Globe,
    FileText,
    Image,
    Newspaper,
    Calendar,
    Settings,
    Package,
    ShoppingCart,
    HardDrive,
} from 'lucide-react';
import { User } from '@/types';
import { useTranslation } from '@/contexts/LanguageContext';
import type {
    OverviewStats,
    ChangeMetrics,
    SubscriptionDistributionItem,
    AiUsageStats,
    AiUsageByProvider,
    AiUsageTrendItem,
    AiSpendControlStats,
    ReferralStats,
    StorageStats,
    CmsModuleStats,
    FirebaseStats,
    TrendData,
} from '@/types/admin';

interface Props {
    user: User;
    stats: OverviewStats;
    changes: ChangeMetrics;
    subscriptionDistribution: SubscriptionDistributionItem[];
    aiUsage: AiUsageStats;
    aiUsageByProvider: AiUsageByProvider[];
    aiUsageTrend: AiUsageTrendItem[];
    aiSpendControl: AiSpendControlStats;
    referralStats: ReferralStats;
    storageStats: StorageStats;
    cmsModuleStats: CmsModuleStats;
    firebaseStats: FirebaseStats;
    trends: TrendData;
}

export default function Overview({
    user,
    stats,
    changes,
    subscriptionDistribution,
    aiUsage,
    aiUsageByProvider,
    aiUsageTrend,
    aiSpendControl,
    referralStats,
    storageStats,
    cmsModuleStats,
    firebaseStats,
    trends,
}: Props) {
    const { t } = useTranslation();
    const { isLoading, isRefreshing, startRefresh, endRefresh } = useAdminLoading();

    const handleRefresh = () => {
        startRefresh();
        router.post(
            '/admin/refresh-stats',
            {},
            {
                preserveScroll: true,
                onFinish: () => endRefresh(),
            }
        );
    };

    const formatBytes = (bytes: number) => {
        if (!bytes || bytes <= 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let value = bytes;
        let unitIndex = 0;
        while (value >= 1024 && unitIndex < units.length - 1) {
            value /= 1024;
            unitIndex += 1;
        }
        return `${value.toLocaleString(undefined, {
            maximumFractionDigits: unitIndex === 0 ? 0 : 1,
        })} ${units[unitIndex]}`;
    };

    const formatTokens = (tokens: number) => {
        if (tokens >= 1_000_000) return `${(tokens / 1_000_000).toFixed(1)}M`;
        if (tokens >= 1_000) return `${(tokens / 1_000).toFixed(1)}K`;
        return tokens.toLocaleString();
    };

    const formatUsd = (value: number) => {
        return `$${value.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 6,
        })}`;
    };

    const formatDateTime = (value: string | null) => {
        if (!value) return t('N/A');
        return new Date(value).toLocaleString();
    };

    const resolveSiteLabel = (item: {
        primary_domain: string | null;
        subdomain: string | null;
        site_name: string | null;
        project_name: string;
    }) => {
        if (item.primary_domain) return item.primary_domain;
        if (item.subdomain) return item.subdomain;
        if (item.site_name) return item.site_name;
        return item.project_name;
    };

    return (
        <AdminLayout user={user} title={t('Overview')}>
            <AdminPageHeader
                title={t('Overview')}
                subtitle={t('Dashboard overview and key metrics')}
                action={
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={handleRefresh}
                        disabled={isLoading}
                    >
                        <RefreshCw
                            className={`h-4 w-4 me-2 ${isRefreshing ? 'animate-spin' : ''}`}
                        />
                        {t('Refresh')}
                    </Button>
                }
            />

            {isLoading ? (
                <OverviewSkeleton />
            ) : (
                <>
                    {/* Core Stats Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                        <StatsCard
                            title={t('Total Users')}
                            value={stats.total_users.toLocaleString()}
                            change={changes.users}
                            icon={Users}
                        />
                        <StatsCard
                            title={t('Active Subs')}
                            value={stats.active_subscriptions.toLocaleString()}
                            change={changes.subscriptions}
                            icon={CreditCard}
                        />
                        <StatsCard
                            title={t('MRR')}
                            value={`$${stats.mrr.toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            })}`}
                            icon={TrendingUp}
                        />
                        <StatsCard
                            title={t('Revenue (MTD)')}
                            value={`$${stats.revenue_mtd.toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            })}`}
                            change={changes.revenue}
                            icon={DollarSign}
                        />
                        <StatsCard
                            title={t('Total Projects')}
                            value={stats.total_projects.toLocaleString()}
                            change={changes.projects}
                            icon={FolderOpen}
                        />
                    </div>

                    {/* Trend Chart */}
                    <Card className="mb-8">
                        <CardHeader>
                            <CardTitle className="text-lg">{t('30-Day Trends')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <TrendLineChart data={trends} />
                        </CardContent>
                    </Card>

                    {/* Distribution Charts Row */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">
                                    {t('Subscriptions by Plan')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <SubscriptionPieChart data={subscriptionDistribution} />
                            </CardContent>
                        </Card>

                        <ReferralStatsCard stats={referralStats} />
                    </div>

                    {/* AI Usage Section */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                        <AiUsageCard stats={aiUsage} />

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">{t('Usage by Provider')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <AiProviderPieChart data={aiUsageByProvider} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">{t('Token Usage Trend')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <AiUsageTrendChart data={aiUsageTrend} />
                            </CardContent>
                        </Card>
                    </div>

                    {/* AI Spend Control */}
                    <div className="mb-8 space-y-6">
                        <div className="space-y-1">
                            <h2 className="text-lg font-semibold tracking-tight">{t('AI Spend Control')}</h2>
                            <p className="text-sm text-muted-foreground">
                                {t('Track available tokens, provider limits, per-site costs, and exact request-level AI spending.')}
                            </p>
                        </div>

                        <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-lg">{t('Token Availability')}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <p className="text-sm text-muted-foreground">{t('Platform Remaining')}</p>
                                            <p className="text-xl font-semibold">{formatTokens(aiSpendControl.availability.platform_remaining_tokens)}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-muted-foreground">{t('Overage Balance')}</p>
                                            <p className="text-xl font-semibold">{formatTokens(aiSpendControl.availability.platform_overage_tokens)}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-muted-foreground">{t('Users With Tokens')}</p>
                                            <p className="text-lg font-semibold">{aiSpendControl.availability.users_with_token_balance.toLocaleString()}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-muted-foreground">{t('Unlimited Users')}</p>
                                            <p className="text-lg font-semibold">{aiSpendControl.availability.users_with_unlimited_tokens.toLocaleString()}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-muted-foreground">{t('Users With Own Key')}</p>
                                            <p className="text-lg font-semibold">{aiSpendControl.availability.users_using_own_keys.toLocaleString()}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-muted-foreground">{t('Connected Providers')}</p>
                                            <p className="text-lg font-semibold">
                                                {aiSpendControl.availability.connected_providers}/{aiSpendControl.availability.active_providers}
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-lg">{t('Provider Limits & Cost')}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {aiSpendControl.provider_limits.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">{t('No providers configured')}</p>
                                    ) : (
                                        <div className="max-h-[320px] overflow-auto rounded-md border">
                                            <table className="w-full text-sm">
                                                <thead className="bg-muted/40 sticky top-0">
                                                    <tr>
                                                        <th className="text-start px-3 py-2">{t('Provider')}</th>
                                                        <th className="text-start px-3 py-2">{t('Model')}</th>
                                                        <th className="text-end px-3 py-2">{t('Max')}</th>
                                                        <th className="text-end px-3 py-2">{t('Month Tokens')}</th>
                                                        <th className="text-end px-3 py-2">{t('Month Cost')}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {aiSpendControl.provider_limits.map((provider) => (
                                                        <tr key={provider.provider_id} className="border-t">
                                                            <td className="px-3 py-2 align-top">
                                                                <div className="flex items-center gap-2">
                                                                    <span>{provider.provider}</span>
                                                                    <Badge variant={provider.status === 'active' ? 'default' : 'secondary'}>
                                                                        {provider.status}
                                                                    </Badge>
                                                                    {!provider.has_credentials && (
                                                                        <Badge variant="outline">{t('No key')}</Badge>
                                                                    )}
                                                                </div>
                                                            </td>
                                                            <td className="px-3 py-2 align-top">{provider.default_model}</td>
                                                            <td className="px-3 py-2 text-end align-top">
                                                                {provider.max_tokens_per_request.toLocaleString()}
                                                            </td>
                                                            <td className="px-3 py-2 text-end align-top">{formatTokens(provider.month_tokens)}</td>
                                                            <td className="px-3 py-2 text-end align-top">{formatUsd(provider.month_cost)}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">{t('Cost by Site / Project')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {aiSpendControl.site_spend.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">{t('No AI usage records yet')}</p>
                                ) : (
                                    <div className="max-h-[360px] overflow-auto rounded-md border">
                                        <table className="w-full text-sm">
                                            <thead className="bg-muted/40 sticky top-0">
                                                <tr>
                                                    <th className="text-start px-3 py-2">{t('Site')}</th>
                                                    <th className="text-start px-3 py-2">{t('Project')}</th>
                                                    <th className="text-start px-3 py-2">{t('Owner')}</th>
                                                    <th className="text-end px-3 py-2">{t('Requests')}</th>
                                                    <th className="text-end px-3 py-2">{t('Tokens')}</th>
                                                    <th className="text-end px-3 py-2">{t('Total Cost')}</th>
                                                    <th className="text-end px-3 py-2">{t('Avg / Request')}</th>
                                                    <th className="text-start px-3 py-2">{t('Last Usage')}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {aiSpendControl.site_spend.map((item, index) => (
                                                    <tr key={`${item.project_id ?? 'unknown'}-${index}`} className="border-t">
                                                        <td className="px-3 py-2">{resolveSiteLabel(item)}</td>
                                                        <td className="px-3 py-2">{item.project_name}</td>
                                                        <td className="px-3 py-2">{item.owner_name}</td>
                                                        <td className="px-3 py-2 text-end">{item.request_count.toLocaleString()}</td>
                                                        <td className="px-3 py-2 text-end">{formatTokens(item.total_tokens)}</td>
                                                        <td className="px-3 py-2 text-end font-medium">{formatUsd(item.total_cost)}</td>
                                                        <td className="px-3 py-2 text-end">{formatUsd(item.avg_cost_per_request)}</td>
                                                        <td className="px-3 py-2">{formatDateTime(item.last_used_at)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">{t('Recent AI Usage Events')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {aiSpendControl.recent_usage.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">{t('No AI usage events yet')}</p>
                                ) : (
                                    <div className="max-h-[420px] overflow-auto rounded-md border">
                                        <table className="w-full text-sm">
                                            <thead className="bg-muted/40 sticky top-0">
                                                <tr>
                                                    <th className="text-start px-3 py-2">{t('Time')}</th>
                                                    <th className="text-start px-3 py-2">{t('User')}</th>
                                                    <th className="text-start px-3 py-2">{t('Site / Project')}</th>
                                                    <th className="text-start px-3 py-2">{t('Provider / Model')}</th>
                                                    <th className="text-start px-3 py-2">{t('Action')}</th>
                                                    <th className="text-end px-3 py-2">{t('Tokens')}</th>
                                                    <th className="text-end px-3 py-2">{t('Cost')}</th>
                                                    <th className="text-start px-3 py-2">{t('Source')}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {aiSpendControl.recent_usage.map((item) => (
                                                    <tr key={item.id} className="border-t">
                                                        <td className="px-3 py-2">{formatDateTime(item.created_at)}</td>
                                                        <td className="px-3 py-2">
                                                            <div className="leading-tight">
                                                                <p>{item.user_name}</p>
                                                                <p className="text-xs text-muted-foreground">{item.user_email ?? t('N/A')}</p>
                                                            </div>
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <div className="leading-tight">
                                                                <p>{resolveSiteLabel({
                                                                    primary_domain: item.primary_domain,
                                                                    subdomain: item.subdomain,
                                                                    site_name: item.site_name,
                                                                    project_name: item.project_name ?? t('Unknown Project'),
                                                                })}</p>
                                                                <p className="text-xs text-muted-foreground">{item.project_name ?? t('Unknown Project')}</p>
                                                            </div>
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <div className="leading-tight">
                                                                <p>{item.provider}</p>
                                                                <p className="text-xs text-muted-foreground">{item.model ?? t('Unknown model')}</p>
                                                            </div>
                                                        </td>
                                                        <td className="px-3 py-2">{item.action}</td>
                                                        <td className="px-3 py-2 text-end">{item.total_tokens.toLocaleString()}</td>
                                                        <td className="px-3 py-2 text-end">{formatUsd(item.estimated_cost)}</td>
                                                        <td className="px-3 py-2">
                                                            <Badge variant={item.used_own_api_key ? 'secondary' : 'default'}>
                                                                {item.used_own_api_key ? t('Own Key') : t('Platform')}
                                                            </Badge>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Storage & Firebase Section */}
                    <div className="mb-8">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-lg font-semibold tracking-tight">
                                {t('CMS / Booking / Commerce Overview')}
                            </h2>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                            <StatsCard
                                title={t('CMS Sites')}
                                value={cmsModuleStats.sites_total.toLocaleString()}
                                icon={Globe}
                            />
                            <StatsCard
                                title={t('CMS Pages')}
                                value={cmsModuleStats.pages_total.toLocaleString()}
                                icon={FileText}
                            />
                            <StatsCard
                                title={t('Media Assets')}
                                value={cmsModuleStats.media_assets_total.toLocaleString()}
                                icon={Image}
                            />
                            <StatsCard
                                title={t('Media Asset Storage')}
                                value={formatBytes(cmsModuleStats.media_assets_bytes)}
                                icon={HardDrive}
                            />
                            <StatsCard
                                title={t('Blog Posts')}
                                value={cmsModuleStats.blog_posts_total.toLocaleString()}
                                icon={Newspaper}
                            />
                            <StatsCard
                                title={t('Booking Services')}
                                value={cmsModuleStats.booking_services_total.toLocaleString()}
                                icon={Settings}
                            />
                            <StatsCard
                                title={t('Bookings')}
                                value={cmsModuleStats.bookings_total.toLocaleString()}
                                icon={Calendar}
                            />
                            <StatsCard
                                title={t('Store Products')}
                                value={cmsModuleStats.ecommerce_products_total.toLocaleString()}
                                icon={Package}
                            />
                            <StatsCard
                                title={t('Store Orders')}
                                value={cmsModuleStats.ecommerce_orders_total.toLocaleString()}
                                icon={ShoppingCart}
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <StorageStatsCard stats={storageStats} />
                        <FirebaseStatusCard stats={firebaseStats} />
                    </div>
                </>
            )}
        </AdminLayout>
    );
}
