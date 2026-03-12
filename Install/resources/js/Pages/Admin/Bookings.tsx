import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { AdminPageHeader } from '@/components/Admin/AdminPageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/contexts/LanguageContext';
import type { AdminBooking, AdminBookingsPageProps } from '@/types/admin';

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'completed') {
        return 'default';
    }

    if (status === 'cancelled' || status === 'no_show') {
        return 'destructive';
    }

    if (status === 'confirmed' || status === 'in_progress') {
        return 'secondary';
    }

    return 'outline';
}

function formatDateTime(value: string | null, locale: string): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString(locale);
}

function formatPayload(payload: Record<string, unknown>): string {
    const serialized = JSON.stringify(payload);
    if (!serialized || serialized === '{}') {
        return '';
    }

    return serialized.length > 240
        ? `${serialized.slice(0, 240)}...`
        : serialized;
}

export default function AdminBookings({
    user,
    bookings,
    pagination,
    filters,
    status_options,
    source_options,
    projects,
    sites,
}: AdminBookingsPageProps) {
    const { t, locale } = useTranslation();

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [source, setSource] = useState(filters.source ?? 'all');
    const [projectId, setProjectId] = useState(filters.project_id ?? '');
    const [siteId, setSiteId] = useState(filters.site_id ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [sort, setSort] = useState(filters.sort ?? 'starts_desc');
    const [perPage, setPerPage] = useState(String(filters.per_page ?? 20));

    const filteredSites = useMemo(() => {
        if (!projectId) {
            return sites;
        }

        return sites.filter((site) => site.project_id === projectId);
    }, [projectId, sites]);

    const navigate = (extra: Record<string, string | number | undefined> = {}) => {
        const params: Record<string, string | number | undefined> = {
            search: search.trim() || undefined,
            status: status !== 'all' ? status : undefined,
            source: source !== 'all' ? source : undefined,
            project_id: projectId || undefined,
            site_id: siteId || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            sort,
            per_page: Number(perPage) || 20,
            ...extra,
        };

        router.get(route('admin.bookings'), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setSearch('');
        setStatus('all');
        setSource('all');
        setProjectId('');
        setSiteId('');
        setDateFrom('');
        setDateTo('');
        setSort('starts_desc');
        setPerPage('20');

        router.get(route('admin.bookings'), {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const bookingRows = bookings.data ?? [];

    return (
        <AdminLayout user={user} title={t('Booking Oversight')}>
            <AdminPageHeader
                title={t('Booking Oversight')}
                subtitle={t('Track booking source, status, and timeline across all projects')}
            />

            <div className="mb-6 rounded-lg border bg-card p-4">
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <div className="space-y-2 xl:col-span-2">
                        <Label htmlFor="booking-search">{t('Search')}</Label>
                        <div className="flex gap-2">
                            <Input
                                id="booking-search"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        navigate({ page: 1 });
                                    }
                                }}
                                placeholder={t('Booking #, customer, site, project')}
                            />
                            <Button variant="outline" onClick={() => navigate({ page: 1 })}>
                                <Search className="me-2 h-4 w-4" />
                                {t('Search')}
                            </Button>
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label>{t('Status')}</Label>
                        <Select value={status} onValueChange={(value) => setStatus(value)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('All')}</SelectItem>
                                {status_options.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label>{t('Source')}</Label>
                        <Select value={source} onValueChange={(value) => setSource(value)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('All')}</SelectItem>
                                {source_options.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label>{t('Sort')}</Label>
                        <Select value={sort} onValueChange={(value) => setSort(value as 'starts_desc' | 'starts_asc' | 'updated_desc')}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="starts_desc">{t('Start time (newest)')}</SelectItem>
                                <SelectItem value="starts_asc">{t('Start time (oldest)')}</SelectItem>
                                <SelectItem value="updated_desc">{t('Recently updated')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label>{t('Project')}</Label>
                        <Select
                            value={projectId || 'all'}
                            onValueChange={(value) => {
                                const nextProjectId = value === 'all' ? '' : value;
                                setProjectId(nextProjectId);
                                setSiteId('');
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('All projects')}</SelectItem>
                                {projects.map((project) => (
                                    <SelectItem key={project.id} value={project.id}>
                                        {project.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label>{t('Site')}</Label>
                        <Select
                            value={siteId || 'all'}
                            onValueChange={(value) => setSiteId(value === 'all' ? '' : value)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('All sites')}</SelectItem>
                                {filteredSites.map((site) => (
                                    <SelectItem key={site.id} value={site.id}>
                                        {site.name || site.subdomain || site.primary_domain || site.id}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label>{t('From')}</Label>
                        <Input
                            type="date"
                            value={dateFrom}
                            onChange={(event) => setDateFrom(event.target.value)}
                        />
                    </div>

                    <div className="space-y-2">
                        <Label>{t('To')}</Label>
                        <Input
                            type="date"
                            value={dateTo}
                            onChange={(event) => setDateTo(event.target.value)}
                        />
                    </div>

                    <div className="space-y-2">
                        <Label>{t('Per page')}</Label>
                        <Select value={perPage} onValueChange={(value) => setPerPage(value)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="10">10</SelectItem>
                                <SelectItem value="20">20</SelectItem>
                                <SelectItem value="50">50</SelectItem>
                                <SelectItem value="100">100</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div className="mt-4 flex flex-wrap gap-2">
                    <Button onClick={() => navigate({ page: 1 })}>{t('Apply Filters')}</Button>
                    <Button variant="outline" onClick={resetFilters}>{t('Reset')}</Button>
                </div>
            </div>

            <div className="space-y-4">
                {bookingRows.length === 0 ? (
                    <Card>
                        <CardContent className="pt-6 text-sm text-muted-foreground">
                            {t('No bookings found for selected filters')}
                        </CardContent>
                    </Card>
                ) : (
                    bookingRows.map((booking) => (
                        <BookingCard key={booking.id} booking={booking} locale={locale} t={t} />
                    ))
                )}
            </div>

            <div className="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-card p-4">
                <p className="text-sm text-muted-foreground">
                    {t('Total')}: {pagination.total} · {t('Page')} {pagination.current_page} / {pagination.last_page}
                </p>
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        disabled={pagination.current_page <= 1}
                        onClick={() => navigate({ page: pagination.current_page - 1 })}
                    >
                        {t('Previous')}
                    </Button>
                    <Button
                        variant="outline"
                        disabled={pagination.current_page >= pagination.last_page}
                        onClick={() => navigate({ page: pagination.current_page + 1 })}
                    >
                        {t('Next')}
                    </Button>
                </div>
            </div>
        </AdminLayout>
    );
}

function BookingCard({
    booking,
    locale,
    t,
}: {
    booking: AdminBooking;
    locale: string;
    t: (key: string) => string;
}) {
    return (
        <Card>
            <CardHeader className="space-y-2">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <CardTitle className="text-base">{booking.booking_number}</CardTitle>
                    <div className="flex items-center gap-2">
                        <Badge variant={statusVariant(booking.status)}>{booking.status}</Badge>
                        <Badge variant="outline">{booking.source || 'unknown'}</Badge>
                    </div>
                </div>
                <CardDescription>
                    {booking.project.name || '—'} · {booking.site.name || booking.site.subdomain || booking.site.primary_domain || '—'}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                <div className="grid gap-2 text-sm md:grid-cols-2 lg:grid-cols-4">
                    <p><span className="text-muted-foreground">{t('Customer')}:</span> {booking.customer_name || '—'}</p>
                    <p><span className="text-muted-foreground">{t('Email')}:</span> {booking.customer_email || '—'}</p>
                    <p><span className="text-muted-foreground">{t('Service')}:</span> {booking.service.name || '—'}</p>
                    <p><span className="text-muted-foreground">{t('Staff')}:</span> {booking.staff_resource.name || '—'}</p>
                    <p><span className="text-muted-foreground">{t('Start')}:</span> {formatDateTime(booking.starts_at, locale)}</p>
                    <p><span className="text-muted-foreground">{t('End')}:</span> {formatDateTime(booking.ends_at, locale)}</p>
                    <p><span className="text-muted-foreground">{t('Events')}:</span> {booking.events_count}</p>
                    <p><span className="text-muted-foreground">{t('Updated')}:</span> {formatDateTime(booking.updated_at, locale)}</p>
                </div>

                <details className="rounded-md border p-3">
                    <summary className="cursor-pointer text-sm font-medium">
                        {t('Timeline')} ({booking.timeline.length})
                    </summary>
                    <div className="mt-3 space-y-2">
                        {booking.timeline.length === 0 ? (
                            <p className="text-sm text-muted-foreground">{t('No timeline events')}</p>
                        ) : (
                            booking.timeline.map((event) => (
                                <div key={event.id} className="rounded-md border bg-muted/30 p-2 text-sm">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="font-medium">{event.event_type}</span>
                                        <span className="text-xs text-muted-foreground">
                                            {formatDateTime(event.occurred_at, locale)}
                                        </span>
                                    </div>
                                    {formatPayload(event.payload_json) && (
                                        <p className="mt-1 break-all font-mono text-xs text-muted-foreground">
                                            {formatPayload(event.payload_json)}
                                        </p>
                                    )}
                                </div>
                            ))
                        )}
                    </div>
                </details>
            </CardContent>
        </Card>
    );
}
