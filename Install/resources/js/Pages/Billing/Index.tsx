import { Link, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { usePageLoading } from '@/hooks/usePageLoading';
import { useTranslation } from '@/contexts/LanguageContext';
import { BillingSkeleton } from './BillingSkeleton';
import CurrentSubscriptionCard from './Partials/CurrentSubscriptionCard';
import BankTransferPending from './Partials/BankTransferPending';
import NoSubscriptionAlert from './Partials/NoSubscriptionAlert';
import BillingHistoryTable from './Partials/BillingHistoryTable';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { CalendarClock, ChartNoAxesColumn, CreditCard, Sparkles, TrendingDown, TrendingUp } from 'lucide-react';
import type { BillingPageProps } from '@/types/billing';

export default function Index({
    subscription,
    pendingSubscription,
    transactions,
    billingOverview,
}: BillingPageProps) {
    const { auth } = usePage<BillingPageProps>().props;
    const { isLoading } = usePageLoading();
    const { t, locale, isRtl } = useTranslation();

    const dateLocale = isRtl ? 'ar-SA' : locale;

    const formatCurrency = (amount: number, currency: string = 'USD') => {
        return new Intl.NumberFormat(dateLocale, {
            style: 'currency',
            currency,
            minimumFractionDigits: amount % 1 === 0 ? 0 : 2,
        }).format(amount);
    };

    const formatCredits = (value: number) => {
        if (value >= 1_000_000) {
            return `${(value / 1_000_000).toFixed(1)}M`;
        }
        if (value >= 1_000) {
            return `${(value / 1_000).toFixed(1)}K`;
        }

        return value.toLocaleString();
    };

    const formatDate = (value: string | null) => {
        if (!value) {
            return t('Not set');
        }

        return new Date(value).toLocaleDateString(dateLocale, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    };

    const getRenewalBadge = () => {
        switch (billingOverview.renewal.state) {
            case 'upcoming':
                return <Badge variant="secondary">{t('Upcoming')}</Badge>;
            case 'due':
                return <Badge variant="destructive">{t('Payment Due')}</Badge>;
            case 'past_due':
                return <Badge variant="destructive">{t('Past Due')}</Badge>;
            case 'grace':
                return <Badge variant="outline">{t('Grace')}</Badge>;
            case 'suspended':
                return <Badge variant="destructive">{t('Suspended')}</Badge>;
            case 'lifetime':
                return <Badge variant="default">{t('Lifetime')}</Badge>;
            default:
                return <Badge variant="outline">{t('No Renewal')}</Badge>;
        }
    };

    return (
        <AdminLayout user={auth.user!} title={t('Billing')}>
            {isLoading ? (
                <BillingSkeleton />
            ) : (
            <div className="space-y-6">
                {/* Page Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="prose prose-sm dark:prose-invert">
                        <h1 className="text-2xl font-bold text-foreground">
                            {t('Billing')}
                        </h1>
                        <p className="text-muted-foreground">
                            {t('Manage your subscription and view billing history')}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/billing/usage">{t('Usage')}</Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/billing/referral">{t('Referral')}</Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/billing/plans">
                                {subscription ? t('Change Plan') : t('Choose Plan')}
                            </Link>
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base flex items-center gap-2">
                            <CreditCard className="h-4 w-4" />
                            {t('Billing Overview')}
                        </CardTitle>
                        <CardDescription>
                            {t('Plan, renewal, monthly usage and upgrade/downgrade shortcuts')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-6 lg:grid-cols-3">
                        <div className="space-y-3 rounded-md border p-4">
                            <p className="text-sm text-muted-foreground">{t('Current Plan')}</p>
                            {billingOverview.current_plan ? (
                                <>
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="text-lg font-semibold">{billingOverview.current_plan.name}</p>
                                        {billingOverview.current_plan.is_unlimited_credits && (
                                            <Badge variant="secondary">{t('Unlimited Credits')}</Badge>
                                        )}
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {formatCurrency(billingOverview.current_plan.price)}
                                        {' / '}
                                        {t(billingOverview.current_plan.billing_period)}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {billingOverview.current_plan.max_projects === null
                                            ? t('Unlimited projects')
                                            : t(':count projects', { count: billingOverview.current_plan.max_projects })}
                                    </p>
                                </>
                            ) : (
                                <p className="text-sm text-muted-foreground">{t('No active plan')}</p>
                            )}
                        </div>

                        <div className="space-y-3 rounded-md border p-4">
                            <div className="flex items-center justify-between gap-2">
                                <p className="text-sm text-muted-foreground">{t('Renewal')}</p>
                                {getRenewalBadge()}
                            </div>
                            <p className="text-base font-semibold flex items-center gap-2">
                                <CalendarClock className="h-4 w-4" />
                                {formatDate(billingOverview.renewal.date)}
                            </p>
                            {typeof billingOverview.renewal.days_until === 'number' && billingOverview.renewal.days_until >= 0 && (
                                <p className="text-sm text-muted-foreground">
                                    {t(':count days remaining', {
                                        count: billingOverview.renewal.days_until.toLocaleString(),
                                    })}
                                </p>
                            )}
                            <div className="h-px bg-border" />
                            <div className="text-sm space-y-2">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">{t('Credits Used')}</span>
                                    <span className="font-medium">
                                        {formatCredits(billingOverview.usage.build_credits.used)}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">{t('Credits Remaining')}</span>
                                    <span className="font-medium">
                                        {billingOverview.usage.build_credits.is_unlimited
                                            ? t('Unlimited')
                                            : `${formatCredits(billingOverview.usage.build_credits.remaining)} / ${formatCredits(billingOverview.usage.build_credits.monthly_limit)}`}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="space-y-3 rounded-md border p-4">
                            <p className="text-sm text-muted-foreground flex items-center gap-2">
                                <ChartNoAxesColumn className="h-4 w-4" />
                                {t('Usage & Actions')}
                            </p>
                            <div className="text-sm space-y-2">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">{t('Orders')}</span>
                                    <span className="font-medium">
                                        {billingOverview.usage.orders.used.toLocaleString()}
                                        {' / '}
                                        {billingOverview.usage.orders.limit === null
                                            ? t('Unlimited')
                                            : billingOverview.usage.orders.limit.toLocaleString()}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">{t('Bookings')}</span>
                                    <span className="font-medium">
                                        {billingOverview.usage.bookings.used.toLocaleString()}
                                        {' / '}
                                        {billingOverview.usage.bookings.limit === null
                                            ? t('Unlimited')
                                            : billingOverview.usage.bookings.limit.toLocaleString()}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">{t('Overage Balance')}</span>
                                    <span className="font-medium">
                                        {formatCredits(billingOverview.usage.build_credits.overage_balance)}
                                    </span>
                                </div>
                            </div>
                            <div className="h-px bg-border" />
                            <div className="grid gap-2 sm:grid-cols-2">
                                <Button variant="outline" asChild>
                                    <Link href="/billing/usage">
                                        <Sparkles className="h-4 w-4 me-2" />
                                        {t('View Usage')}
                                    </Link>
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href="/billing/plans">
                                        {t('All Plans')}
                                    </Link>
                                </Button>
                                {billingOverview.recommendations.upgrade && (
                                    <Button asChild>
                                        <Link href={`/billing/plans?intent=upgrade&target_plan=${billingOverview.recommendations.upgrade.plan_id}`}>
                                            <TrendingUp className="h-4 w-4 me-2" />
                                            {t('Upgrade')}
                                        </Link>
                                    </Button>
                                )}
                                {billingOverview.recommendations.downgrade && (
                                    <Button variant="secondary" asChild>
                                        <Link href={`/billing/plans?intent=downgrade&target_plan=${billingOverview.recommendations.downgrade.plan_id}`}>
                                            <TrendingDown className="h-4 w-4 me-2" />
                                            {t('Downgrade')}
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Current Subscription or No Subscription Alert */}
                {subscription ? (
                    <CurrentSubscriptionCard subscription={subscription} />
                ) : pendingSubscription ? (
                    <BankTransferPending subscription={pendingSubscription} />
                ) : (
                    <NoSubscriptionAlert />
                )}

                {/* Billing History */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{t('Billing History')}</CardTitle>
                        <CardDescription>{t('Your recent transactions and invoices')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <BillingHistoryTable transactions={transactions} />
                    </CardContent>
                </Card>
            </div>
            )}
        </AdminLayout>
    );
}
