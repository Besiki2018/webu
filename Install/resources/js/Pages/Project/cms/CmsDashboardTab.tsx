import { Link } from '@inertiajs/react';
import {
    Banknote,
    CalendarDays,
    CheckCircle2,
    Clock3,
    ExternalLink,
    Globe,
    Image as ImageIcon,
    LayoutGrid,
    Loader2,
    MapPin,
    MessageSquare,
    PackageCheck,
    RefreshCw,
    ShoppingCart,
    TrendingUp,
    Type,
    Users,
    Wallet,
    Wand2,
    type LucideIcon,
} from 'lucide-react';

import { useTranslation } from '@/contexts/LanguageContext';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type DashboardViewMode = 'ecommerce' | 'crm' | 'general';

interface DashboardFeatureShortcut {
    key: string;
    tab: string;
    label: string;
    icon: LucideIcon;
}

interface RecentOrderItem {
    id: number;
    order_number?: string | null;
    items_count?: number | null;
    customer_name?: string | null;
    customer_email?: string | null;
    payment_status?: string | null;
    status: string;
    placed_at?: string | null;
    updated_at?: string | null;
    created_at?: string | null;
    currency?: string | null;
    grand_total?: string | number | null;
}

interface RecentCustomerItem {
    key: string;
    name: string;
    email: string | null;
    phone: string | null;
    orders: number;
    lastOrderAt: string | null;
}

interface RecentBookingItem {
    id: number;
    status: string;
    starts_at: string | null;
}

interface CmsDashboardTabProps {
    activeContentLocale: string;
    bookingModuleAvailable: boolean;
    builderCmsProductsCount: number;
    canUseMediaModule: boolean;
    dashboardBlogCounts: {
        total: number;
        published: number;
        draft: number;
    };
    dashboardBookingCounts: {
        total: number;
        upcoming: number;
        today: number;
        next7Days: number;
        byStatus: Record<string, number>;
    };
    dashboardBookingInboxCounts: Record<string, number>;
    dashboardBookingInboxTotal: number;
    dashboardBookingRecentItems: RecentBookingItem[];
    dashboardBookingServicesCount: number | null;
    dashboardEcommerceCategoriesCount: number | null;
    dashboardEcommerceCustomerStats: {
        total: number;
        repeat: number;
        vip: number;
        recent: RecentCustomerItem[];
    };
    dashboardEcommerceFinanceMetrics: {
        grossRevenueFormatted: string;
        paidRevenueFormatted: string;
        outstandingRevenueFormatted: string;
        averageOrderValueFormatted: string;
    };
    dashboardEcommerceOrderCounts: {
        total: number;
        byStatus: Record<string, number>;
        byPaymentStatus: Record<string, number>;
        grossRevenue: number;
        paidRevenue: number;
        outstandingRevenue: number;
        currency: string | null;
    };
    dashboardEcommerceRecentOrders: RecentOrderItem[];
    dashboardLatestContentUpdate: string | null;
    dashboardPageCounts: {
        total: number;
        published: number;
        draft: number;
    };
    dashboardPublishedCoverage: {
        pages: number;
        blog: number;
    };
    dashboardSummaryUpdatedAt: string | null;
    dashboardViewMode: DashboardViewMode;
    dashboardViewOptions: DashboardViewMode[];
    ecommerceModuleAvailable: boolean;
    hasBookingDashboardCapability: boolean;
    hasEcommerceDashboardCapability: boolean;
    isDashboardSummaryLoading: boolean;
    isEcommerceDashboardMode: boolean;
    isCrmDashboardMode: boolean;
    locale: string;
    mediaItemsCount: number;
    modulePlanName: string | null;
    moduleSummary: {
        available: number;
        total: number;
    };
    onDashboardViewModeChange: (mode: DashboardViewMode) => void;
    onRefreshDashboardSummary: () => void | Promise<void>;
    previewWebsiteUrl: string | null;
    projectId: number | string;
    projectName: string;
    publicDomain: string | null;
    siteStatus: string;
    templateSlug: string | null;
    visibleDashboardFeatureShortcuts: DashboardFeatureShortcut[];
    visibleVerticalFeatureBadges: string[];
}

function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString();
}

function formatCurrencyAmount(amount: number, currency: string, locale: string): string {
    try {
        return new Intl.NumberFormat(locale || 'ka', {
            style: 'currency',
            currency,
            maximumFractionDigits: 2,
        }).format(amount);
    } catch {
        return `${amount.toFixed(2)} ${currency}`;
    }
}

function MetricCard({
    icon: Icon,
    subtitle,
    title,
    toneClassName = 'text-muted-foreground',
    value,
}: {
    icon: LucideIcon;
    subtitle: string;
    title: string;
    toneClassName?: string;
    value: string;
}) {
    return (
        <div className="rounded-2xl border bg-background/90 p-4 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-xs text-muted-foreground">{title}</p>
                    <p className="mt-1 text-lg font-semibold">{value}</p>
                    <p className={`text-xs ${toneClassName}`}>{subtitle}</p>
                </div>
                <div className="rounded-xl border bg-muted/40 p-2 text-muted-foreground">
                    <Icon className="h-4 w-4" />
                </div>
            </div>
        </div>
    );
}

function DashboardViewSwitcher({
    options,
    value,
    onChange,
}: {
    options: DashboardViewMode[];
    value: DashboardViewMode;
    onChange: (mode: DashboardViewMode) => void;
}) {
    const { t } = useTranslation();

    if (options.length <= 1) {
        return null;
    }

    return (
        <Card className="border-dashed">
            <CardContent className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm font-semibold">{t('Dashboard View')}</p>
                    <p className="text-xs text-muted-foreground">{t('Choose dashboard layout by project module')}</p>
                </div>
                <div className="w-full sm:w-auto">
                    <select
                        value={value}
                        onChange={(event) => onChange(event.target.value as DashboardViewMode)}
                        className="h-10 w-full min-w-[220px] rounded-md border bg-background px-3 text-sm sm:w-auto"
                    >
                        {options.map((option) => (
                            <option key={`dashboard-view-${option}`} value={option}>
                                {option === 'ecommerce'
                                    ? t('Ecommerce Dashboard')
                                    : option === 'crm'
                                        ? t('CRM Dashboard')
                                        : t('General Dashboard')}
                            </option>
                        ))}
                    </select>
                </div>
            </CardContent>
        </Card>
    );
}

export function CmsDashboardTab({
    activeContentLocale,
    bookingModuleAvailable,
    builderCmsProductsCount,
    canUseMediaModule,
    dashboardBlogCounts,
    dashboardBookingCounts,
    dashboardBookingInboxCounts,
    dashboardBookingInboxTotal,
    dashboardBookingRecentItems,
    dashboardBookingServicesCount,
    dashboardEcommerceCategoriesCount,
    dashboardEcommerceCustomerStats,
    dashboardEcommerceFinanceMetrics,
    dashboardEcommerceOrderCounts,
    dashboardEcommerceRecentOrders,
    dashboardLatestContentUpdate,
    dashboardPageCounts,
    dashboardPublishedCoverage,
    dashboardSummaryUpdatedAt,
    dashboardViewMode,
    dashboardViewOptions,
    ecommerceModuleAvailable,
    hasBookingDashboardCapability,
    hasEcommerceDashboardCapability,
    isDashboardSummaryLoading,
    isEcommerceDashboardMode,
    isCrmDashboardMode,
    locale,
    mediaItemsCount,
    modulePlanName,
    moduleSummary,
    onDashboardViewModeChange,
    onRefreshDashboardSummary,
    previewWebsiteUrl,
    projectId,
    projectName,
    publicDomain,
    siteStatus,
    templateSlug,
    visibleDashboardFeatureShortcuts,
    visibleVerticalFeatureBadges,
}: CmsDashboardTabProps) {
    const { t } = useTranslation();
    const safeLocale = locale || 'ka';
    const showGeneralDashboard = !isEcommerceDashboardMode && !isCrmDashboardMode;

    return (
        <div className="space-y-4 min-w-0">
            <DashboardViewSwitcher
                options={dashboardViewOptions}
                value={dashboardViewMode}
                onChange={onDashboardViewModeChange}
            />

            {isEcommerceDashboardMode ? (
                <>
                    <div className="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]">
                        <Card className="min-w-0 overflow-hidden border-slate-200/80 bg-gradient-to-br from-slate-50 via-background to-background">
                            <CardContent className="p-0">
                                <div className="grid min-w-0 gap-0 2xl:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
                                    <div className="min-w-0 space-y-4 p-4 md:p-5 2xl:border-r">
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div className="space-y-2 min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Badge variant={siteStatus === 'published' ? 'default' : 'secondary'}>{siteStatus}</Badge>
                                                    <Badge variant="outline">{t('Plan')}: {modulePlanName ?? '—'}</Badge>
                                                    <Badge variant="outline">{t('Language')}: {activeContentLocale.toUpperCase()}</Badge>
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="text-xs uppercase tracking-[0.14em] text-muted-foreground">{t('Ecommerce Dashboard')}</p>
                                                    <h2 className="truncate text-xl font-semibold tracking-tight md:text-2xl">{projectName}</h2>
                                                    <p className="truncate text-sm text-muted-foreground">
                                                        {publicDomain ?? t('Not connected')}
                                                        <span className="mx-2">·</span>
                                                        {templateSlug ?? t('Not selected')}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="rounded-2xl border bg-background/90 px-3 py-2 text-right shadow-sm">
                                                <p className="text-[11px] uppercase tracking-wide text-muted-foreground">{t('Latest sync')}</p>
                                                <p className="mt-1 text-sm font-semibold">{dashboardSummaryUpdatedAt ? formatDate(dashboardSummaryUpdatedAt) : '—'}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {dashboardLatestContentUpdate ? `${t('Updated')}: ${formatDate(dashboardLatestContentUpdate)}` : t('No recent updates')}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="grid min-w-0 gap-3 sm:grid-cols-2">
                                            <MetricCard
                                                icon={Banknote}
                                                title={t('Revenue')}
                                                value={dashboardEcommerceFinanceMetrics.grossRevenueFormatted}
                                                subtitle={`${t('Paid')}: ${dashboardEcommerceFinanceMetrics.paidRevenueFormatted}`}
                                                toneClassName="text-emerald-600"
                                            />
                                            <MetricCard
                                                icon={ShoppingCart}
                                                title={t('Orders')}
                                                value={dashboardEcommerceOrderCounts.total.toLocaleString(safeLocale)}
                                                subtitle={`${t('Pending')}: ${(dashboardEcommerceOrderCounts.byStatus.pending ?? 0).toLocaleString(safeLocale)}`}
                                            />
                                            <MetricCard
                                                icon={Users}
                                                title={t('Customers')}
                                                value={dashboardEcommerceCustomerStats.total.toLocaleString(safeLocale)}
                                                subtitle={`${t('Repeat')}: ${dashboardEcommerceCustomerStats.repeat.toLocaleString(safeLocale)}`}
                                            />
                                            <MetricCard
                                                icon={TrendingUp}
                                                title={t('Avg Order')}
                                                value={dashboardEcommerceFinanceMetrics.averageOrderValueFormatted}
                                                subtitle={`${t('Outstanding')}: ${dashboardEcommerceFinanceMetrics.outstandingRevenueFormatted}`}
                                                toneClassName="text-amber-600"
                                            />
                                        </div>

                                        <div className="grid min-w-0 gap-3 sm:grid-cols-2">
                                            <div className="min-w-0 rounded-2xl border bg-background/90 p-3">
                                                <p className="text-xs text-muted-foreground">{t('Products')}</p>
                                                <p className="mt-1 text-lg font-semibold">{builderCmsProductsCount.toLocaleString(safeLocale)}</p>
                                                <p className="text-xs text-muted-foreground">{t('Categories')}: {(dashboardEcommerceCategoriesCount ?? 0).toLocaleString(safeLocale)}</p>
                                            </div>
                                            <div className="min-w-0 rounded-2xl border bg-background/90 p-3">
                                                <p className="text-xs text-muted-foreground">{t('Publishing Coverage')}</p>
                                                <p className="mt-1 text-lg font-semibold">{dashboardPublishedCoverage.pages}%</p>
                                                <div className="mt-2 h-1.5 rounded-full bg-muted">
                                                    <div className="h-1.5 rounded-full bg-emerald-500" style={{ width: `${dashboardPublishedCoverage.pages}%` }} />
                                                </div>
                                            </div>
                                            <div className="min-w-0 rounded-2xl border bg-background/90 p-3 sm:col-span-2">
                                                <p className="text-xs text-muted-foreground">{t('Blog Coverage')}</p>
                                                <p className="mt-1 text-lg font-semibold">{dashboardPublishedCoverage.blog}%</p>
                                                <div className="mt-2 h-1.5 rounded-full bg-muted">
                                                    <div className="h-1.5 rounded-full bg-sky-500" style={{ width: `${dashboardPublishedCoverage.blog}%` }} />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="min-w-0 space-y-4 border-t bg-muted/20 p-4 md:p-5 2xl:border-t-0">
                                        <div>
                                            <p className="text-xs uppercase tracking-wide text-muted-foreground">{t('Quick Access')}</p>
                                            <p className="text-sm font-semibold">{t('Ecommerce workflow shortcuts')}</p>
                                        </div>
                                        <div className="grid gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="justify-start bg-background"
                                                onClick={() => void onRefreshDashboardSummary()}
                                                disabled={isDashboardSummaryLoading}
                                            >
                                                {isDashboardSummaryLoading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <RefreshCw className="mr-2 h-4 w-4" />}
                                                {t('Refresh Dashboard Summary')}
                                            </Button>
                                            <Button asChild variant="outline" size="sm" className="justify-start bg-background">
                                                <Link href={`/project/${projectId}/cms?tab=ecommerce-orders`}>
                                                    <PackageCheck className="mr-2 h-4 w-4" />
                                                    {t('Open Orders')}
                                                </Link>
                                            </Button>
                                            <Button asChild variant="outline" size="sm" className="justify-start bg-background">
                                                <Link href={`/project/${projectId}/cms?tab=ecommerce-products`}>
                                                    <ShoppingCart className="mr-2 h-4 w-4" />
                                                    {t('Open Products')}
                                                </Link>
                                            </Button>
                                            <Button asChild variant="outline" size="sm" className="justify-start bg-background">
                                                <Link href={`/project/${projectId}/cms?tab=customers`}>
                                                    <Users className="mr-2 h-4 w-4" />
                                                    {t('Open Customers')}
                                                </Link>
                                            </Button>
                                            <Button asChild variant="outline" size="sm" className="justify-start bg-background">
                                                <Link href={`/project/${projectId}/cms?tab=discounts`}>
                                                    <Wallet className="mr-2 h-4 w-4" />
                                                    {t('Discounts')}
                                                </Link>
                                            </Button>
                                            <Button asChild variant="outline" size="sm" className="justify-start bg-background">
                                                <Link href={`/project/${projectId}/cms?tab=ecommerce-payments`}>
                                                    <Banknote className="mr-2 h-4 w-4" />
                                                    {t('Payments')}
                                                </Link>
                                            </Button>
                                            <Button asChild variant="outline" size="sm" className="justify-start bg-background">
                                                <Link href={`/project/${projectId}/cms?tab=ecommerce-shipping`}>
                                                    <MapPin className="mr-2 h-4 w-4" />
                                                    {t('Shipping')}
                                                </Link>
                                            </Button>
                                            {previewWebsiteUrl ? (
                                                <Button asChild variant="outline" size="sm" className="justify-start bg-background">
                                                    <a href={previewWebsiteUrl} target="_blank" rel="noreferrer">
                                                        <Globe className="mr-2 h-4 w-4" />
                                                        {t('Preview Website')}
                                                    </a>
                                                </Button>
                                            ) : null}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <div className="grid gap-4">
                            <Card className="border-slate-200/80">
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm">{t('Revenue')}</CardTitle>
                                    <CardDescription>{t('Paid vs outstanding ecommerce revenue')}</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {(() => {
                                        const gross = dashboardEcommerceOrderCounts.grossRevenue;
                                        const paid = dashboardEcommerceOrderCounts.paidRevenue;
                                        const outstanding = dashboardEcommerceOrderCounts.outstandingRevenue;
                                        const paidPercent = gross > 0 ? Math.round((paid / gross) * 100) : 0;
                                        const outstandingPercent = gross > 0 ? Math.round((outstanding / gross) * 100) : 0;

                                        return (
                                            <>
                                                <div className="space-y-3">
                                                    <div className="rounded-xl border p-3">
                                                        <div className="flex items-center justify-between gap-2">
                                                            <span className="text-xs text-muted-foreground">{t('Gross')}</span>
                                                            <span className="text-sm font-semibold">{dashboardEcommerceFinanceMetrics.grossRevenueFormatted}</span>
                                                        </div>
                                                    </div>
                                                    <div className="rounded-xl border p-3">
                                                        <div className="flex items-center justify-between gap-2">
                                                            <span className="text-xs text-muted-foreground">{t('Paid')}</span>
                                                            <span className="text-sm font-semibold">{dashboardEcommerceFinanceMetrics.paidRevenueFormatted}</span>
                                                        </div>
                                                        <div className="mt-2 h-1.5 rounded-full bg-muted">
                                                            <div className="h-1.5 rounded-full bg-emerald-500" style={{ width: `${paidPercent > 0 ? Math.max(4, Math.min(100, paidPercent)) : 0}%` }} />
                                                        </div>
                                                    </div>
                                                    <div className="rounded-xl border p-3">
                                                        <div className="flex items-center justify-between gap-2">
                                                            <span className="text-xs text-muted-foreground">{t('Outstanding')}</span>
                                                            <span className="text-sm font-semibold">{dashboardEcommerceFinanceMetrics.outstandingRevenueFormatted}</span>
                                                        </div>
                                                        <div className="mt-2 h-1.5 rounded-full bg-muted">
                                                            <div className="h-1.5 rounded-full bg-amber-500" style={{ width: `${outstandingPercent > 0 ? Math.max(4, Math.min(100, outstandingPercent)) : 0}%` }} />
                                                        </div>
                                                    </div>
                                                </div>
                                                <div className="grid grid-cols-2 gap-2">
                                                    <div className="rounded-xl border p-3">
                                                        <p className="text-xs text-muted-foreground">{t('Avg Order')}</p>
                                                        <p className="mt-1 font-semibold">{dashboardEcommerceFinanceMetrics.averageOrderValueFormatted}</p>
                                                    </div>
                                                    <div className="rounded-xl border p-3">
                                                        <p className="text-xs text-muted-foreground">{t('Orders')}</p>
                                                        <p className="mt-1 font-semibold">{dashboardEcommerceOrderCounts.total.toLocaleString(safeLocale)}</p>
                                                    </div>
                                                </div>
                                            </>
                                        );
                                    })()}
                                </CardContent>
                            </Card>

                            <Card className="border-slate-200/80">
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm">{t('Order Status')}</CardTitle>
                                    <CardDescription>{t('Current ecommerce order breakdown')}</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="grid grid-cols-2 gap-2 text-sm">
                                        {['pending', 'processing', 'completed', 'cancelled'].map((statusKey) => (
                                            <div key={`ecommerce-status-${statusKey}`} className="rounded-xl border p-3">
                                                <p className="text-xs text-muted-foreground">{t(statusKey)}</p>
                                                <p className="mt-1 font-semibold">{(dashboardEcommerceOrderCounts.byStatus[statusKey] ?? 0).toLocaleString(safeLocale)}</p>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="space-y-1.5">
                                        <div className="flex h-2 overflow-hidden rounded-full bg-muted">
                                            {(() => {
                                                const totalOrders = Math.max(1, dashboardEcommerceOrderCounts.total);
                                                const segments = [
                                                    { key: 'completed', count: dashboardEcommerceOrderCounts.byStatus.completed ?? 0, color: 'bg-emerald-500' },
                                                    { key: 'processing', count: dashboardEcommerceOrderCounts.byStatus.processing ?? 0, color: 'bg-sky-500' },
                                                    { key: 'pending', count: dashboardEcommerceOrderCounts.byStatus.pending ?? 0, color: 'bg-amber-500' },
                                                    { key: 'cancelled', count: dashboardEcommerceOrderCounts.byStatus.cancelled ?? 0, color: 'bg-rose-500' },
                                                ].filter((segment) => segment.count > 0);

                                                if (segments.length === 0) {
                                                    return <div className="h-full w-full bg-muted-foreground/20" />;
                                                }

                                                return segments.map((segment) => (
                                                    <div
                                                        key={`ecom-order-segment-${segment.key}`}
                                                        className={segment.color}
                                                        style={{ width: `${Math.max(4, Math.round((segment.count / totalOrders) * 100))}%` }}
                                                    />
                                                ));
                                            })()}
                                        </div>
                                        <div className="flex flex-wrap gap-1.5 text-[11px]">
                                            <Badge variant="outline">{t('Paid')}: {(dashboardEcommerceOrderCounts.byPaymentStatus.paid ?? 0).toLocaleString(safeLocale)}</Badge>
                                            <Badge variant="outline">{t('Pending')}: {(dashboardEcommerceOrderCounts.byPaymentStatus.pending ?? 0).toLocaleString(safeLocale)}</Badge>
                                            <Badge variant="outline">{t('Failed')}: {(dashboardEcommerceOrderCounts.byPaymentStatus.failed ?? 0).toLocaleString(safeLocale)}</Badge>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    <div className="grid gap-4 xl:grid-cols-[1.75fr_1fr]">
                        <Card className="border-slate-200/80 min-w-0">
                            <CardHeader>
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <CardTitle>{t('Recent Orders')}</CardTitle>
                                        <CardDescription>{t('Latest ecommerce orders and statuses')}</CardDescription>
                                    </div>
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={`/project/${projectId}/cms?tab=ecommerce-orders`}>{t('View All')}</Link>
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {dashboardEcommerceRecentOrders.length === 0 ? (
                                    <div className="py-8 text-center text-sm text-muted-foreground">
                                        {isDashboardSummaryLoading ? t('Loading orders...') : t('No orders yet')}
                                    </div>
                                ) : (
                                    <div className="overflow-x-auto rounded-xl border">
                                        <table className="min-w-[860px] w-full text-sm">
                                            <thead className="bg-muted/30">
                                                <tr className="border-b text-left text-xs uppercase tracking-wide text-muted-foreground">
                                                    <th className="px-4 py-3 font-medium">{t('Order')}</th>
                                                    <th className="px-4 py-3 font-medium">{t('Customer')}</th>
                                                    <th className="px-4 py-3 font-medium">{t('Payment')}</th>
                                                    <th className="px-4 py-3 font-medium">{t('Order Status')}</th>
                                                    <th className="px-4 py-3 font-medium">{t('Updated')}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {dashboardEcommerceRecentOrders.map((order) => {
                                                    const rowCurrency = (order.currency || dashboardEcommerceOrderCounts.currency || 'GEL').toUpperCase();
                                                    const rowAmount = Number.parseFloat(String(order.grand_total ?? '0')) || 0;
                                                    const safeAmount = formatCurrencyAmount(rowAmount, rowCurrency, safeLocale);

                                                    return (
                                                        <tr key={`dashboard-order-${order.id}`} className="border-b last:border-b-0 hover:bg-muted/20">
                                                            <td className="px-4 py-3 align-top">
                                                                <div className="space-y-1">
                                                                    <p className="font-semibold">{order.order_number}</p>
                                                                    <p className="text-xs text-muted-foreground">{t('Items')}: {(order.items_count ?? 0).toLocaleString(safeLocale)}</p>
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3 align-top">
                                                                <div className="min-w-0">
                                                                    <p className="truncate font-medium">{order.customer_name || order.customer_email || t('Unknown customer')}</p>
                                                                    <p className="truncate text-xs text-muted-foreground">{order.customer_email || '—'}</p>
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3 align-top">
                                                                <div className="space-y-1">
                                                                    <p className="font-semibold">{safeAmount}</p>
                                                                    <Badge variant={order.payment_status === 'paid' ? 'default' : order.payment_status === 'failed' ? 'secondary' : 'outline'}>
                                                                        {t(order.payment_status || 'unpaid')}
                                                                    </Badge>
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3 align-top">
                                                                <Badge variant={order.status === 'completed' || order.status === 'paid' ? 'default' : order.status === 'cancelled' || order.status === 'failed' ? 'secondary' : 'outline'}>
                                                                    {t(order.status)}
                                                                </Badge>
                                                            </td>
                                                            <td className="px-4 py-3 align-top text-xs text-muted-foreground">
                                                                {formatDate(order.placed_at || order.updated_at || order.created_at)}
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <div className="space-y-4">
                            <Card className="border-slate-200/80">
                                <CardHeader>
                                    <div className="flex items-center justify-between gap-2">
                                        <div>
                                            <CardTitle>{t('Customers')}</CardTitle>
                                            <CardDescription>{t('Top recent buyers')}</CardDescription>
                                        </div>
                                        <Button asChild variant="outline" size="sm">
                                            <Link href={`/project/${projectId}/cms?tab=customers`}>{t('Open Customers')}</Link>
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="grid grid-cols-3 gap-2 text-center">
                                        <div className="rounded-xl border p-2">
                                            <p className="text-[11px] text-muted-foreground">{t('Total')}</p>
                                            <p className="mt-1 text-sm font-semibold">{dashboardEcommerceCustomerStats.total.toLocaleString(safeLocale)}</p>
                                        </div>
                                        <div className="rounded-xl border p-2">
                                            <p className="text-[11px] text-muted-foreground">{t('Repeat')}</p>
                                            <p className="mt-1 text-sm font-semibold">{dashboardEcommerceCustomerStats.repeat.toLocaleString(safeLocale)}</p>
                                        </div>
                                        <div className="rounded-xl border p-2">
                                            <p className="text-[11px] text-muted-foreground">{t('VIP')}</p>
                                            <p className="mt-1 text-sm font-semibold">{dashboardEcommerceCustomerStats.vip.toLocaleString(safeLocale)}</p>
                                        </div>
                                    </div>
                                    {dashboardEcommerceCustomerStats.recent.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">{t('No customers yet')}</p>
                                    ) : (
                                        <div className="space-y-2">
                                            {dashboardEcommerceCustomerStats.recent.map((customer) => {
                                                const initials = customer.name
                                                    .split(/\s+/)
                                                    .filter(Boolean)
                                                    .slice(0, 2)
                                                    .map((part) => part[0]?.toUpperCase() ?? '')
                                                    .join('') || 'CU';

                                                return (
                                                    <div key={`dashboard-customer-${customer.key}`} className="flex items-center justify-between gap-3 rounded-xl border p-3">
                                                        <div className="flex min-w-0 items-center gap-3">
                                                            <div className="flex h-9 w-9 items-center justify-center rounded-full border bg-muted text-xs font-semibold">
                                                                {initials}
                                                            </div>
                                                            <div className="min-w-0">
                                                                <p className="truncate text-sm font-medium">{customer.name}</p>
                                                                <p className="truncate text-xs text-muted-foreground">{customer.email || customer.phone || '—'}</p>
                                                            </div>
                                                        </div>
                                                        <div className="text-right">
                                                            <p className="text-sm font-semibold">{customer.orders} {t('orders')}</p>
                                                            <p className="text-xs text-muted-foreground">{formatDate(customer.lastOrderAt)}</p>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            <Card className="border-slate-200/80">
                                <CardHeader className="pb-3">
                                    <CardTitle>{t('Catalog & Content')}</CardTitle>
                                    <CardDescription>{t('Store readiness and operational details')}</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                                        <div className="rounded-xl border p-3">
                                            <div className="flex items-center justify-between gap-2">
                                                <p className="text-xs text-muted-foreground">{t('Products')}</p>
                                                <ShoppingCart className="h-4 w-4 text-muted-foreground" />
                                            </div>
                                            <p className="mt-1 text-lg font-semibold">{builderCmsProductsCount.toLocaleString(safeLocale)}</p>
                                            <p className="text-xs text-muted-foreground">{t('Categories')}: {(dashboardEcommerceCategoriesCount ?? 0).toLocaleString(safeLocale)}</p>
                                        </div>
                                        <div className="rounded-xl border p-3">
                                            <div className="flex items-center justify-between gap-2">
                                                <p className="text-xs text-muted-foreground">{t('Pages')}</p>
                                                <CheckCircle2 className="h-4 w-4 text-muted-foreground" />
                                            </div>
                                            <p className="mt-1 text-lg font-semibold">{dashboardPageCounts.total.toLocaleString(safeLocale)}</p>
                                            <p className="text-xs text-muted-foreground">{t('published')}: {dashboardPageCounts.published}</p>
                                        </div>
                                    </div>

                                    <div className="space-y-2 rounded-xl border p-3">
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="text-sm font-medium">{t('Publishing Coverage')}</p>
                                            <Badge variant="outline">{dashboardPublishedCoverage.pages}%</Badge>
                                        </div>
                                        <div className="h-1.5 rounded-full bg-muted">
                                            <div className="h-1.5 rounded-full bg-emerald-500" style={{ width: `${dashboardPublishedCoverage.pages}%` }} />
                                        </div>
                                        <div className="flex items-center justify-between gap-2 text-xs">
                                            <span className="text-muted-foreground">{t('Blog Coverage')}</span>
                                            <span>{dashboardPublishedCoverage.blog}%</span>
                                        </div>
                                        <div className="h-1.5 rounded-full bg-muted">
                                            <div className="h-1.5 rounded-full bg-sky-500" style={{ width: `${dashboardPublishedCoverage.blog}%` }} />
                                        </div>
                                    </div>

                                    {hasBookingDashboardCapability ? (
                                        <div className="space-y-2 rounded-xl border p-3">
                                            <div className="flex items-center justify-between gap-2">
                                                <p className="text-sm font-medium">{t('Booking Overview')}</p>
                                                <Badge variant="outline">{dashboardBookingCounts.upcoming.toLocaleString(safeLocale)}</Badge>
                                            </div>
                                            <div className="grid grid-cols-2 gap-2 text-xs">
                                                <div className="rounded-lg border bg-muted/20 px-2 py-2">
                                                    <p className="text-muted-foreground">{t('Today')}</p>
                                                    <p className="mt-1 font-semibold">{dashboardBookingCounts.today.toLocaleString(safeLocale)}</p>
                                                </div>
                                                <div className="rounded-lg border bg-muted/20 px-2 py-2">
                                                    <p className="text-muted-foreground">{t('Inbox')}</p>
                                                    <p className="mt-1 font-semibold">{dashboardBookingInboxTotal.toLocaleString(safeLocale)}</p>
                                                </div>
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                <Button asChild size="sm" variant="outline">
                                                    <Link href={`/project/${projectId}/cms?tab=booking`}>{t('Open Booking Inbox')}</Link>
                                                </Button>
                                                <Button asChild size="sm" variant="outline">
                                                    <Link href={`/project/${projectId}/cms?tab=booking-calendar`}>{t('Open Calendar')}</Link>
                                                </Button>
                                            </div>
                                        </div>
                                    ) : null}
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </>
            ) : null}

            {isCrmDashboardMode ? (
                <>
                    <div className="grid gap-4 xl:grid-cols-[1.6fr_1fr]">
                        <Card className="overflow-hidden border-sky-200/70 bg-gradient-to-br from-sky-100/70 via-background to-background dark:from-sky-950/20">
                            <CardContent className="p-4 md:p-5">
                                <div className="flex flex-col gap-4">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div className="space-y-2">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge variant={siteStatus === 'published' ? 'default' : 'secondary'}>{siteStatus}</Badge>
                                                <Badge variant="outline">{t('Plan')}: {modulePlanName ?? '—'}</Badge>
                                                <Badge variant="outline">{t('Language')}: {activeContentLocale.toUpperCase()}</Badge>
                                            </div>
                                            <div>
                                                <p className="text-xs uppercase tracking-wide text-muted-foreground">{t('CRM Dashboard')}</p>
                                                <h2 className="text-xl font-semibold tracking-tight">{projectName}</h2>
                                                <p className="text-sm text-muted-foreground">
                                                    {publicDomain ?? t('Not connected')}
                                                    <span className="mx-2">·</span>
                                                    {templateSlug ?? t('Not selected')}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex flex-col gap-2 sm:min-w-[220px]">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="justify-start bg-background/80"
                                                onClick={() => void onRefreshDashboardSummary()}
                                                disabled={isDashboardSummaryLoading}
                                            >
                                                {isDashboardSummaryLoading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <RefreshCw className="mr-2 h-4 w-4" />}
                                                {t('Refresh Dashboard Summary')}
                                            </Button>
                                            <Button asChild variant="outline" size="sm" className="justify-start bg-background/80">
                                                <Link href={`/project/${projectId}/cms?tab=booking`}>
                                                    <MessageSquare className="mr-2 h-4 w-4" />
                                                    {t('Open Booking Inbox')}
                                                </Link>
                                            </Button>
                                            <Button asChild variant="outline" size="sm" className="justify-start bg-background/80">
                                                <Link href={`/project/${projectId}/cms?tab=booking-calendar`}>
                                                    <CalendarDays className="mr-2 h-4 w-4" />
                                                    {t('Open Calendar')}
                                                </Link>
                                            </Button>
                                            {previewWebsiteUrl ? (
                                                <Button asChild variant="outline" size="sm" className="justify-start bg-background/80">
                                                    <a href={previewWebsiteUrl} target="_blank" rel="noreferrer">
                                                        <Globe className="mr-2 h-4 w-4" />
                                                        {t('Preview Website')}
                                                    </a>
                                                </Button>
                                            ) : null}
                                        </div>
                                    </div>

                                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                        <MetricCard
                                            icon={CalendarDays}
                                            title={t('Bookings')}
                                            value={dashboardBookingCounts.total.toLocaleString(safeLocale)}
                                            subtitle={`${t('Upcoming')}: ${dashboardBookingCounts.upcoming.toLocaleString(safeLocale)}`}
                                        />
                                        <MetricCard
                                            icon={Clock3}
                                            title={t('Today')}
                                            value={dashboardBookingCounts.today.toLocaleString(safeLocale)}
                                            subtitle={`${t('Next 7 Days')}: ${dashboardBookingCounts.next7Days.toLocaleString(safeLocale)}`}
                                        />
                                        <MetricCard
                                            icon={MessageSquare}
                                            title={t('Inbox')}
                                            value={dashboardBookingInboxTotal.toLocaleString(safeLocale)}
                                            subtitle={`${t('Pending')}: ${(dashboardBookingInboxCounts.pending ?? 0).toLocaleString(safeLocale)}`}
                                        />
                                        <MetricCard
                                            icon={MapPin}
                                            title={t('Services')}
                                            value={(dashboardBookingServicesCount ?? 0).toLocaleString(safeLocale)}
                                            subtitle={`${t('Confirmed')}: ${(dashboardBookingCounts.byStatus.confirmed ?? 0).toLocaleString(safeLocale)}`}
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                            <Card className="border-slate-200/70">
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm">{t('Inbox Breakdown')}</CardTitle>
                                    <CardDescription>{t('Current booking inbox counters')}</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    {Object.entries(dashboardBookingInboxCounts).length === 0 ? (
                                        <p className="text-sm text-muted-foreground">{t('No inbox items')}</p>
                                    ) : (
                                        Object.entries(dashboardBookingInboxCounts).map(([key, count]) => (
                                            <div key={`booking-inbox-${key}`} className="flex items-center justify-between rounded-xl border p-3">
                                                <span className="text-sm">{t(key)}</span>
                                                <Badge variant="outline">{count.toLocaleString(safeLocale)}</Badge>
                                            </div>
                                        ))
                                    )}
                                </CardContent>
                            </Card>

                            <Card className="border-slate-200/70">
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm">{t('Booking Status')}</CardTitle>
                                    <CardDescription>{t('Live booking pipeline by status')}</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    {(['pending', 'confirmed', 'completed', 'cancelled', 'no_show'] as const).map((statusKey) => {
                                        const count = dashboardBookingCounts.byStatus[statusKey] ?? 0;
                                        const width = dashboardBookingCounts.total > 0 ? Math.round((count / dashboardBookingCounts.total) * 100) : 0;

                                        return (
                                            <div key={`booking-status-${statusKey}`} className="rounded-xl border p-3">
                                                <div className="mb-2 flex items-center justify-between gap-2 text-sm">
                                                    <span>{t(statusKey)}</span>
                                                    <span className="font-medium">{count.toLocaleString(safeLocale)}</span>
                                                </div>
                                                <div className="h-1.5 rounded-full bg-muted">
                                                    <div className="h-1.5 rounded-full bg-sky-500" style={{ width: `${Math.max(0, Math.min(100, width))}%` }} />
                                                </div>
                                            </div>
                                        );
                                    })}
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    <div className="grid gap-4 xl:grid-cols-[1.55fr_1fr]">
                        <Card className="border-slate-200/70">
                            <CardHeader>
                                <div className="flex items-center justify-between gap-2">
                                    <div>
                                        <CardTitle>{t('Recent Bookings')}</CardTitle>
                                        <CardDescription>{t('Latest appointments and booking statuses')}</CardDescription>
                                    </div>
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={`/project/${projectId}/cms?tab=booking`}>{t('View All')}</Link>
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {dashboardBookingRecentItems.length === 0 ? (
                                    <div className="py-8 text-center text-sm text-muted-foreground">
                                        {isDashboardSummaryLoading ? t('Loading bookings...') : t('No bookings yet')}
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        {dashboardBookingRecentItems.map((booking) => {
                                            const statusVariant = booking.status === 'completed'
                                                ? 'default'
                                                : booking.status === 'cancelled' || booking.status === 'no_show'
                                                    ? 'secondary'
                                                    : 'outline';
                                            const bookingDate = booking.starts_at ? new Date(booking.starts_at) : null;
                                            const isUpcoming = bookingDate ? bookingDate.getTime() >= Date.now() : false;

                                            return (
                                                <div key={`dashboard-booking-${booking.id}`} className="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3">
                                                    <div className="min-w-0">
                                                        <p className="text-sm font-semibold">{t('Booking')} #{booking.id}</p>
                                                        <p className="text-xs text-muted-foreground">{booking.starts_at ? formatDate(booking.starts_at) : '—'}</p>
                                                    </div>
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <Badge variant={statusVariant as 'default' | 'secondary' | 'outline'}>{t(booking.status)}</Badge>
                                                        <Badge variant={isUpcoming ? 'outline' : 'secondary'}>{isUpcoming ? t('Upcoming') : t('Past')}</Badge>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <div className="space-y-4">
                            <Card className="border-slate-200/70">
                                <CardHeader>
                                    <CardTitle>{t('CRM Snapshot')}</CardTitle>
                                    <CardDescription>{t('Booking-focused operational overview')}</CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-2 text-sm">
                                    <div className="flex items-center justify-between rounded-xl border p-3">
                                        <div>
                                            <p className="text-xs text-muted-foreground">{t('Services')}</p>
                                            <p className="font-semibold">{(dashboardBookingServicesCount ?? 0).toLocaleString(safeLocale)}</p>
                                        </div>
                                        <MapPin className="h-4 w-4 text-muted-foreground" />
                                    </div>
                                    <div className="flex items-center justify-between rounded-xl border p-3">
                                        <div>
                                            <p className="text-xs text-muted-foreground">{t('Confirmed')}</p>
                                            <p className="font-semibold">{(dashboardBookingCounts.byStatus.confirmed ?? 0).toLocaleString(safeLocale)}</p>
                                        </div>
                                        <CheckCircle2 className="h-4 w-4 text-muted-foreground" />
                                    </div>
                                    <div className="flex items-center justify-between rounded-xl border p-3">
                                        <div>
                                            <p className="text-xs text-muted-foreground">{t('Pending')}</p>
                                            <p className="font-semibold">{(dashboardBookingCounts.byStatus.pending ?? 0).toLocaleString(safeLocale)}</p>
                                        </div>
                                        <Clock3 className="h-4 w-4 text-muted-foreground" />
                                    </div>
                                    <div className="flex items-center justify-between rounded-xl border p-3">
                                        <div>
                                            <p className="text-xs text-muted-foreground">{t('Latest sync')}</p>
                                            <p className="text-xs font-semibold">{dashboardSummaryUpdatedAt ? formatDate(dashboardSummaryUpdatedAt) : '—'}</p>
                                        </div>
                                        <RefreshCw className="h-4 w-4 text-muted-foreground" />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border-slate-200/70">
                                <CardHeader>
                                    <CardTitle>{t('Quick Access')}</CardTitle>
                                    <CardDescription>{t('Booking workflow shortcuts')}</CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-2">
                                    <Button asChild variant="outline" className="justify-start">
                                        <Link href={`/project/${projectId}/cms?tab=booking`}>
                                            <MessageSquare className="mr-2 h-4 w-4" />
                                            {t('Booking Inbox')}
                                        </Link>
                                    </Button>
                                    <Button asChild variant="outline" className="justify-start">
                                        <Link href={`/project/${projectId}/cms?tab=booking-calendar`}>
                                            <CalendarDays className="mr-2 h-4 w-4" />
                                            {t('Calendar')}
                                        </Link>
                                    </Button>
                                    <Button asChild variant="outline" className="justify-start">
                                        <Link href={`/project/${projectId}/cms?tab=booking-services`}>
                                            <MapPin className="mr-2 h-4 w-4" />
                                            {t('Services')}
                                        </Link>
                                    </Button>
                                    <Button asChild variant="outline" className="justify-start">
                                        <Link href={`/project/${projectId}/cms?tab=booking-team`}>
                                            <Users className="mr-2 h-4 w-4" />
                                            {t('Team')}
                                        </Link>
                                    </Button>
                                    <Button asChild variant="outline" className="justify-start">
                                        <Link href={`/project/${projectId}/cms?tab=booking-finance`}>
                                            <Banknote className="mr-2 h-4 w-4" />
                                            {t('Finance')}
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </>
            ) : null}

            {showGeneralDashboard ? (
                <>
                    <Card className="min-w-0 overflow-hidden border-primary/20 bg-gradient-to-br from-primary/10 via-background to-background">
                        <CardContent className="p-4 md:p-5">
                            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div className="space-y-3">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge variant={siteStatus === 'published' ? 'default' : 'secondary'}>{siteStatus}</Badge>
                                        <Badge variant="outline">{t('Modules')}: {moduleSummary.available}/{moduleSummary.total}</Badge>
                                        {modulePlanName ? <Badge variant="outline">{t('Plan')}: {modulePlanName}</Badge> : null}
                                        <Badge variant="outline">{t('Language')}: {activeContentLocale.toUpperCase()}</Badge>
                                    </div>
                                    <div>
                                        <h2 className="text-lg font-semibold tracking-tight">{projectName}</h2>
                                        <p className="text-sm text-muted-foreground">
                                            {publicDomain ?? t('Not connected')}
                                            <span className="mx-2">·</span>
                                            {templateSlug ?? t('Not selected')}
                                        </p>
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                        <div className="rounded-xl border bg-background/80 p-3">
                                            <p className="text-xs text-muted-foreground">{t('Pages')}</p>
                                            <p className="mt-1 text-xl font-semibold">{dashboardPageCounts.total.toLocaleString(safeLocale)}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {dashboardPageCounts.published} {t('published')} · {dashboardPageCounts.draft} {t('draft')}
                                            </p>
                                        </div>
                                        <div className="rounded-xl border bg-background/80 p-3">
                                            <p className="text-xs text-muted-foreground">{t('Blog')}</p>
                                            <p className="mt-1 text-xl font-semibold">{dashboardBlogCounts.total.toLocaleString(safeLocale)}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {dashboardBlogCounts.published} {t('published')} · {dashboardBlogCounts.draft} {t('draft')}
                                            </p>
                                        </div>
                                        <div className="rounded-xl border bg-background/80 p-3">
                                            <p className="text-xs text-muted-foreground">{t('Media')}</p>
                                            <p className="mt-1 text-xl font-semibold">{canUseMediaModule ? mediaItemsCount.toLocaleString(safeLocale) : '—'}</p>
                                            <p className="text-xs text-muted-foreground">{canUseMediaModule ? t('Loaded items') : t('Module disabled')}</p>
                                        </div>
                                        <div className="rounded-xl border bg-background/80 p-3">
                                            <p className="text-xs text-muted-foreground">{t('Latest Content Update')}</p>
                                            <p className="mt-1 text-sm font-semibold">{dashboardLatestContentUpdate ? formatDate(dashboardLatestContentUpdate) : '—'}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {dashboardSummaryUpdatedAt ? `${t('Summary')}: ${formatDate(dashboardSummaryUpdatedAt)}` : t('Not refreshed yet')}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div className="flex flex-col gap-2 lg:min-w-[220px]">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="justify-start bg-background/80"
                                        onClick={() => void onRefreshDashboardSummary()}
                                        disabled={isDashboardSummaryLoading}
                                    >
                                        {isDashboardSummaryLoading ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <RefreshCw className="h-4 w-4 mr-2" />}
                                        {t('Refresh Dashboard Summary')}
                                    </Button>
                                    <Button asChild variant="outline" size="sm" className="justify-start bg-background/80">
                                        <Link href={`/project/${projectId}/cms?tab=pages`}>
                                            <LayoutGrid className="h-4 w-4 mr-2" />
                                            {t('Pages')}
                                        </Link>
                                    </Button>
                                    <Button asChild variant="outline" size="sm" className="justify-start bg-background/80">
                                        <Link href={`/project/${projectId}/cms?tab=blog-posts`}>
                                            <Type className="h-4 w-4 mr-2" />
                                            {t('Entries')}
                                        </Link>
                                    </Button>
                                    {previewWebsiteUrl ? (
                                        <Button asChild variant="outline" size="sm" className="justify-start bg-background/80">
                                            <a href={previewWebsiteUrl} target="_blank" rel="noreferrer">
                                                <ExternalLink className="h-4 w-4 mr-2" />
                                                {t('Preview Website')}
                                            </a>
                                        </Button>
                                    ) : null}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-2 2xl:grid-cols-4">
                        <Card className="border-emerald-200/70 bg-emerald-50/40 dark:bg-emerald-950/10">
                            <CardContent className="p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="text-xs text-muted-foreground">{t('Publishing Coverage')}</p>
                                        <p className="mt-1 text-2xl font-semibold">{dashboardPublishedCoverage.pages}%</p>
                                        <p className="text-xs text-muted-foreground">{t('Pages published')}</p>
                                    </div>
                                    <CheckCircle2 className="h-5 w-5 text-emerald-600" />
                                </div>
                                <div className="mt-3 h-1.5 rounded-full bg-emerald-100">
                                    <div className="h-1.5 rounded-full bg-emerald-500" style={{ width: `${dashboardPublishedCoverage.pages}%` }} />
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-sky-200/70 bg-sky-50/40 dark:bg-sky-950/10">
                            <CardContent className="p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="text-xs text-muted-foreground">{t('Blog Coverage')}</p>
                                        <p className="mt-1 text-2xl font-semibold">{dashboardPublishedCoverage.blog}%</p>
                                        <p className="text-xs text-muted-foreground">{t('Posts published')}</p>
                                    </div>
                                    <Type className="h-5 w-5 text-sky-600" />
                                </div>
                                <div className="mt-3 h-1.5 rounded-full bg-sky-100">
                                    <div className="h-1.5 rounded-full bg-sky-500" style={{ width: `${dashboardPublishedCoverage.blog}%` }} />
                                </div>
                            </CardContent>
                        </Card>

                        {hasEcommerceDashboardCapability ? (
                            <Card className="border-violet-200/70 bg-violet-50/40 dark:bg-violet-950/10">
                                <CardContent className="p-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="text-xs text-muted-foreground">{t('Ecommerce Orders')}</p>
                                            <p className="mt-1 text-2xl font-semibold">{dashboardEcommerceOrderCounts.total.toLocaleString(safeLocale)}</p>
                                            <p className="text-xs text-muted-foreground">{t('Paid')}: {(dashboardEcommerceOrderCounts.byPaymentStatus.paid ?? 0).toLocaleString(safeLocale)}</p>
                                        </div>
                                        <ShoppingCart className="h-5 w-5 text-violet-600" />
                                    </div>
                                </CardContent>
                            </Card>
                        ) : null}

                        {hasBookingDashboardCapability ? (
                            <Card className="border-amber-200/70 bg-amber-50/40 dark:bg-amber-950/10">
                                <CardContent className="p-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="text-xs text-muted-foreground">{t('Booking Upcoming')}</p>
                                            <p className="mt-1 text-2xl font-semibold">{dashboardBookingCounts.upcoming.toLocaleString(safeLocale)}</p>
                                            <p className="text-xs text-muted-foreground">{t('Inbox')}: {dashboardBookingInboxTotal.toLocaleString(safeLocale)}</p>
                                        </div>
                                        <MapPin className="h-5 w-5 text-amber-600" />
                                    </div>
                                </CardContent>
                            </Card>
                        ) : null}
                    </div>

                    <details open className="group rounded-2xl border bg-card/40 p-3 sm:p-4">
                        <summary className="flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg px-1 py-1">
                            <div>
                                <p className="text-sm font-semibold">{t('Module Widgets')}</p>
                                <p className="text-xs text-muted-foreground">{t('Content, ecommerce, and booking summaries in one place')}</p>
                            </div>
                            <Badge variant="outline">{t('Collapse / Expand')}</Badge>
                        </summary>

                        <div className="mt-3 grid gap-4 xl:grid-cols-2 2xl:grid-cols-3">
                            <Card className="min-w-0 xl:col-span-1">
                                <CardHeader>
                                    <CardTitle>{t('Content Overview')}</CardTitle>
                                    <CardDescription>{t('Quick counts for pages, blog, and media')}</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3 text-sm">
                                    <div className="rounded-xl border p-3">
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="font-medium">{t('Pages')}</span>
                                            <Badge variant="secondary">{dashboardPageCounts.total}</Badge>
                                        </div>
                                        <div className="mt-2 h-1.5 rounded-full bg-muted">
                                            <div className="h-1.5 rounded-full bg-primary" style={{ width: `${dashboardPublishedCoverage.pages}%` }} />
                                        </div>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {dashboardPageCounts.published} {t('published')} · {dashboardPageCounts.draft} {t('draft')}
                                        </p>
                                    </div>
                                    <div className="rounded-xl border p-3">
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="font-medium">{t('Blog')}</span>
                                            <Badge variant="secondary">{dashboardBlogCounts.total}</Badge>
                                        </div>
                                        <div className="mt-2 h-1.5 rounded-full bg-muted">
                                            <div className="h-1.5 rounded-full bg-sky-500" style={{ width: `${dashboardPublishedCoverage.blog}%` }} />
                                        </div>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {dashboardBlogCounts.published} {t('published')} · {dashboardBlogCounts.draft} {t('draft')}
                                        </p>
                                    </div>
                                    <div className="rounded-xl border p-3">
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="font-medium">{t('Media')}</span>
                                            <Badge variant="secondary">{canUseMediaModule ? mediaItemsCount : 0}</Badge>
                                        </div>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {canUseMediaModule ? t('Media count reflects loaded library items') : t('Module disabled')}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            {ecommerceModuleAvailable ? (
                                <Card className="min-w-0 xl:col-span-1 border-violet-200/60 bg-gradient-to-b from-violet-50/50 to-background dark:from-violet-950/10">
                                    <CardHeader>
                                        <CardTitle>{t('Ecommerce Overview')}</CardTitle>
                                        <CardDescription>{t('Products, revenue, and order flow')}</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-3 text-sm">
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            <div className="rounded-xl border bg-background/90 p-3">
                                                <p className="text-xs text-muted-foreground">{t('Products')}</p>
                                                <p className="mt-1 text-lg font-semibold">{builderCmsProductsCount.toLocaleString(safeLocale)}</p>
                                            </div>
                                            <div className="rounded-xl border bg-background/90 p-3">
                                                <p className="text-xs text-muted-foreground">{t('Categories')}</p>
                                                <p className="mt-1 text-lg font-semibold">{(dashboardEcommerceCategoriesCount ?? 0).toLocaleString(safeLocale)}</p>
                                            </div>
                                            <div className="rounded-xl border bg-background/90 p-3 sm:col-span-2">
                                                <p className="text-xs text-muted-foreground">{t('Revenue')}</p>
                                                <p className="mt-1 text-lg font-semibold">{dashboardEcommerceFinanceMetrics.grossRevenueFormatted}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {t('Paid')}: {dashboardEcommerceFinanceMetrics.paidRevenueFormatted} · {t('Outstanding')}: {dashboardEcommerceFinanceMetrics.outstandingRevenueFormatted}
                                                </p>
                                            </div>
                                            <div className="rounded-xl border bg-background/90 p-3">
                                                <p className="text-xs text-muted-foreground">{t('Orders')}</p>
                                                <p className="mt-1 text-lg font-semibold">{dashboardEcommerceOrderCounts.total.toLocaleString(safeLocale)}</p>
                                            </div>
                                            <div className="rounded-xl border bg-background/90 p-3">
                                                <p className="text-xs text-muted-foreground">{t('Avg Order')}</p>
                                                <p className="mt-1 text-lg font-semibold">{dashboardEcommerceFinanceMetrics.averageOrderValueFormatted}</p>
                                            </div>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <Badge variant="outline">{t('Pending')}: {dashboardEcommerceOrderCounts.byStatus.pending ?? 0}</Badge>
                                            <Badge variant="outline">{t('Processing')}: {dashboardEcommerceOrderCounts.byStatus.processing ?? 0}</Badge>
                                            <Badge variant="outline">{t('Completed')}: {dashboardEcommerceOrderCounts.byStatus.completed ?? 0}</Badge>
                                            <Badge variant="outline">{t('Cancelled')}: {dashboardEcommerceOrderCounts.byStatus.cancelled ?? 0}</Badge>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <Button asChild variant="outline" size="sm">
                                                <Link href={`/project/${projectId}/cms?tab=ecommerce-products`}>{t('Open Products')}</Link>
                                            </Button>
                                            <Button asChild variant="outline" size="sm">
                                                <Link href={`/project/${projectId}/cms?tab=ecommerce-orders`}>{t('Open Orders')}</Link>
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            ) : null}

                            {bookingModuleAvailable ? (
                                <Card className="min-w-0 xl:col-span-1 border-amber-200/60 bg-gradient-to-b from-amber-50/50 to-background dark:from-amber-950/10">
                                    <CardHeader>
                                        <CardTitle>{t('Booking Overview')}</CardTitle>
                                        <CardDescription>{t('Services, upcoming bookings, and inbox')}</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-3 text-sm">
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            <div className="rounded-xl border bg-background/90 p-3">
                                                <p className="text-xs text-muted-foreground">{t('Services')}</p>
                                                <p className="mt-1 text-lg font-semibold">{(dashboardBookingServicesCount ?? 0).toLocaleString(safeLocale)}</p>
                                            </div>
                                            <div className="rounded-xl border bg-background/90 p-3">
                                                <p className="text-xs text-muted-foreground">{t('Bookings')}</p>
                                                <p className="mt-1 text-lg font-semibold">{dashboardBookingCounts.total.toLocaleString(safeLocale)}</p>
                                            </div>
                                            <div className="rounded-xl border bg-background/90 p-3">
                                                <p className="text-xs text-muted-foreground">{t('Today')}</p>
                                                <p className="mt-1 text-lg font-semibold">{dashboardBookingCounts.today.toLocaleString(safeLocale)}</p>
                                            </div>
                                            <div className="rounded-xl border bg-background/90 p-3">
                                                <p className="text-xs text-muted-foreground">{t('Next 7 Days')}</p>
                                                <p className="mt-1 text-lg font-semibold">{dashboardBookingCounts.next7Days.toLocaleString(safeLocale)}</p>
                                            </div>
                                            <div className="rounded-xl border bg-background/90 p-3 sm:col-span-2">
                                                <p className="text-xs text-muted-foreground">{t('Inbox')}</p>
                                                <p className="mt-1 text-lg font-semibold">{dashboardBookingInboxTotal.toLocaleString(safeLocale)}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {t('Pending')}: {dashboardBookingCounts.byStatus.pending ?? 0} · {t('Confirmed')}: {dashboardBookingCounts.byStatus.confirmed ?? 0}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <Badge variant="outline">{t('Completed')}: {dashboardBookingCounts.byStatus.completed ?? 0}</Badge>
                                            <Badge variant="outline">{t('Cancelled')}: {dashboardBookingCounts.byStatus.cancelled ?? 0}</Badge>
                                            <Badge variant="outline">{t('No Show')}: {dashboardBookingCounts.byStatus.no_show ?? 0}</Badge>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <Button asChild variant="outline" size="sm">
                                                <Link href={`/project/${projectId}/cms?tab=booking`}>{t('Open Booking Inbox')}</Link>
                                            </Button>
                                            <Button asChild variant="outline" size="sm">
                                                <Link href={`/project/${projectId}/cms?tab=booking-calendar`}>{t('Open Calendar')}</Link>
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            ) : null}
                        </div>
                    </details>

                    <details open className="group rounded-2xl border bg-card/40 p-3 sm:p-4">
                        <summary className="flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg px-1 py-1">
                            <div>
                                <p className="text-sm font-semibold">{t('Quick Actions')}</p>
                                <p className="text-xs text-muted-foreground">{t('Shortcuts to common CMS operations')}</p>
                            </div>
                            <Badge variant="outline">{t('Collapse / Expand')}</Badge>
                        </summary>

                        <Card className="mt-3 min-w-0">
                            <CardHeader>
                                <CardTitle>{t('Quick Actions')}</CardTitle>
                                <CardDescription>{t('Use sidebar for full management. These shortcuts open key sections.')}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-2 2xl:grid-cols-3">
                                    <Button asChild variant="outline" className="justify-start">
                                        <Link href={`/project/${projectId}/cms?tab=pages`}>
                                            <LayoutGrid className="h-4 w-4 mr-2" />
                                            {t('Create / Manage Pages')}
                                        </Link>
                                    </Button>
                                    <Button asChild variant="outline" className="justify-start">
                                        <Link href={`/project/${projectId}/cms?tab=blog-posts&action=create`}>
                                            <Type className="h-4 w-4 mr-2" />
                                            {t('Add New')}
                                        </Link>
                                    </Button>
                                    {ecommerceModuleAvailable ? (
                                        <Button asChild variant="outline" className="justify-start">
                                            <Link href={`/project/${projectId}/cms?tab=ecommerce-add-product`}>
                                                <ShoppingCart className="h-4 w-4 mr-2" />
                                                {t('Add Product')}
                                            </Link>
                                        </Button>
                                    ) : null}
                                    {bookingModuleAvailable ? (
                                        <Button asChild variant="outline" className="justify-start">
                                            <Link href={`/project/${projectId}/cms?tab=booking-services`}>
                                                <MapPin className="h-4 w-4 mr-2" />
                                                {t('Manage Booking Services')}
                                            </Link>
                                        </Button>
                                    ) : null}
                                    {canUseMediaModule ? (
                                        <Button asChild variant="outline" className="justify-start">
                                            <Link href={`/project/${projectId}/cms?tab=media`}>
                                                <ImageIcon className="h-4 w-4 mr-2" />
                                                {t('Open Media')}
                                            </Link>
                                        </Button>
                                    ) : null}
                                    {previewWebsiteUrl ? (
                                        <Button asChild variant="outline" className="justify-start">
                                            <a href={previewWebsiteUrl} target="_blank" rel="noreferrer">
                                                <Globe className="h-4 w-4 mr-2" />
                                                {t('Preview Website')}
                                            </a>
                                        </Button>
                                    ) : null}
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="mt-3 min-w-0">
                            <CardHeader>
                                <CardTitle>{t('Completed Feature Map')}</CardTitle>
                                <CardDescription>{t('All major completed work areas in one place with direct section links')}</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-2 2xl:grid-cols-3">
                                    {visibleDashboardFeatureShortcuts.map((shortcut) => {
                                        const Icon = shortcut.icon;

                                        return (
                                            <Button key={`dashboard-feature-shortcut-${shortcut.key}`} asChild variant="outline" className="justify-start">
                                                <Link href={`/project/${projectId}/cms?tab=${shortcut.tab}`}>
                                                    <Icon className="h-4 w-4 mr-2" />
                                                    {shortcut.label}
                                                </Link>
                                            </Button>
                                        );
                                    })}
                                </div>

                                {visibleVerticalFeatureBadges.length > 0 ? (
                                    <>
                                        <div className="rounded-lg border bg-muted/30 px-3 py-2 text-xs text-muted-foreground">
                                            {t('Project-specific builder categories are available in Visual Builder. Open any page → Open Builder and use the matching widget groups.')}
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            {visibleVerticalFeatureBadges.map((badge) => (
                                                <Badge key={`dashboard-vertical-badge-${badge}`} variant="outline">{badge}</Badge>
                                            ))}
                                            <Button asChild size="sm" variant="outline" className="ms-auto">
                                                <Link href={`/project/${projectId}`}>
                                                    <Wand2 className="h-4 w-4 mr-2" />
                                                    {t('Open Visual Builder')}
                                                </Link>
                                            </Button>
                                        </div>
                                    </>
                                ) : null}
                            </CardContent>
                        </Card>
                    </details>
                </>
            ) : null}
        </div>
    );
}
