import { useCallback, useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/contexts/LanguageContext';

interface PricingConsoleProps {
    planId: number;
}

interface VersionItem {
    id: number;
    version_number: number;
    status: 'draft' | 'active' | 'archived';
    base_price: string | number;
    billing_period: string;
    currency: string;
}

interface CatalogPayload {
    active_version_id: number;
    versions: VersionItem[];
}

interface QuoteLineItem {
    type: 'addon' | 'rule';
    code: string;
    name: string;
    amount: number;
}

interface QuotePayload {
    plan: { id: number; name: string };
    version: { id: number; version_number: number; billing_period: string; currency: string };
    base_price: number;
    totals: { subtotal: number; final: number; delta: number };
    line_items: QuoteLineItem[];
}

export default function PricingConsole({ planId }: PricingConsoleProps) {
    const { t } = useTranslation();

    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [catalog, setCatalog] = useState<CatalogPayload | null>(null);
    const [selectedVersionId, setSelectedVersionId] = useState<number | null>(null);

    const [addonCodes, setAddonCodes] = useState('');
    const [usageOrders, setUsageOrders] = useState('');
    const [usageBookings, setUsageBookings] = useState('');
    const [quote, setQuote] = useState<QuotePayload | null>(null);

    const activeVersion = useMemo(() => {
        if (!catalog) return null;
        return catalog.versions.find((version) => version.id === catalog.active_version_id) ?? null;
    }, [catalog]);

    const loadCatalog = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await axios.get<CatalogPayload>(`/admin/plans/${planId}/pricing-catalog`);
            setCatalog(response.data);
            setSelectedVersionId(response.data.active_version_id);
        } catch (err) {
            const message = axios.isAxiosError(err) ? err.response?.data?.error : null;
            setError(message ?? t('Failed to load pricing catalog'));
        } finally {
            setLoading(false);
        }
    }, [planId, t]);

    useEffect(() => {
        void loadCatalog();
    }, [loadCatalog]);

    const createDraftVersion = async () => {
        if (!catalog) return;

        setLoading(true);
        setError(null);

        try {
            await axios.post(`/admin/plans/${planId}/pricing-catalog/versions`, {
                source_version_id: selectedVersionId ?? catalog.active_version_id,
            });

            await loadCatalog();
        } catch (err) {
            const message = axios.isAxiosError(err) ? err.response?.data?.error : null;
            setError(message ?? t('Failed to create draft version'));
        } finally {
            setLoading(false);
        }
    };

    const activateVersion = async (versionId: number) => {
        setLoading(true);
        setError(null);

        try {
            await axios.post(`/admin/plans/${planId}/pricing-catalog/versions/${versionId}/activate`, {
                reason: 'Activated from pricing console',
            });

            await loadCatalog();
        } catch (err) {
            const message = axios.isAxiosError(err) ? err.response?.data?.error : null;
            setError(message ?? t('Failed to activate pricing version'));
        } finally {
            setLoading(false);
        }
    };

    const previewQuote = async () => {
        setLoading(true);
        setError(null);

        const normalizedAddonCodes = addonCodes
            .split(',')
            .map((item) => item.trim())
            .filter((item) => item.length > 0);

        try {
            const response = await axios.post<{ quote: QuotePayload }>(`/admin/plans/${planId}/pricing-catalog/preview`, {
                version_id: selectedVersionId,
                addon_codes: normalizedAddonCodes,
                usage: {
                    orders: usageOrders === '' ? 0 : Number(usageOrders),
                    bookings: usageBookings === '' ? 0 : Number(usageBookings),
                },
            });

            setQuote(response.data.quote);
        } catch (err) {
            const message = axios.isAxiosError(err) ? err.response?.data?.error : null;
            setError(message ?? t('Failed to generate price preview'));
        } finally {
            setLoading(false);
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('Pricing Console')}</CardTitle>
                <CardDescription>
                    {t('Versioned catalog management with real-time price composition preview')}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {error && (
                    <div className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                        {error}
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-2">
                    <Button type="button" variant="outline" onClick={() => void loadCatalog()} disabled={loading}>
                        {t('Refresh')}
                    </Button>
                    <Button type="button" onClick={() => void createDraftVersion()} disabled={loading}>
                        {t('Create Draft Version')}
                    </Button>
                </div>

                <div className="grid gap-2">
                    {catalog?.versions.map((version) => (
                        <div key={version.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3">
                            <div className="space-y-1">
                                <div className="font-medium">
                                    {t('Version')} #{version.version_number}
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {version.base_price} {version.currency} · {version.billing_period}
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <Badge variant={version.status === 'active' ? 'default' : 'secondary'}>
                                    {version.status}
                                </Badge>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setSelectedVersionId(version.id)}
                                >
                                    {t('Use For Preview')}
                                </Button>
                                {version.status !== 'active' && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={() => void activateVersion(version.id)}
                                        disabled={loading}
                                    >
                                        {t('Activate')}
                                    </Button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>

                <div className="grid gap-3 md:grid-cols-3">
                    <div>
                        <div className="mb-1 text-sm font-medium">{t('Version ID')}</div>
                        <Input
                            value={selectedVersionId ?? ''}
                            onChange={(event) => setSelectedVersionId(Number(event.target.value))}
                            placeholder={String(catalog?.active_version_id ?? '')}
                        />
                    </div>
                    <div>
                        <div className="mb-1 text-sm font-medium">{t('Addon Codes (comma separated)')}</div>
                        <Input
                            value={addonCodes}
                            onChange={(event) => setAddonCodes(event.target.value)}
                            placeholder="ecommerce,inventory"
                        />
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2">
                        <div>
                            <div className="mb-1 text-sm font-medium">{t('Orders')}</div>
                            <Input value={usageOrders} onChange={(event) => setUsageOrders(event.target.value)} placeholder="0" />
                        </div>
                        <div>
                            <div className="mb-1 text-sm font-medium">{t('Bookings')}</div>
                            <Input value={usageBookings} onChange={(event) => setUsageBookings(event.target.value)} placeholder="0" />
                        </div>
                    </div>
                </div>

                <Button type="button" onClick={() => void previewQuote()} disabled={loading || !selectedVersionId}>
                    {t('Preview Price')}
                </Button>

                {quote && (
                    <div className="rounded-md border p-3">
                        <div className="mb-2 text-sm font-medium">
                            {t('Quote')} · {quote.version.currency} · {quote.version.billing_period}
                        </div>
                        <div className="mb-2 text-sm text-muted-foreground">
                            {t('Base')}: {quote.base_price} | {t('Delta')}: {quote.totals.delta} | {t('Final')}: {quote.totals.final}
                        </div>
                        <div className="space-y-1 text-sm">
                            {quote.line_items.map((item) => (
                                <div key={`${item.type}:${item.code}`} className="flex justify-between">
                                    <span>
                                        {item.type}: {item.name} ({item.code})
                                    </span>
                                    <span>{item.amount}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {activeVersion && (
                    <div className="text-xs text-muted-foreground">
                        {t('Active Version')}: #{activeVersion.version_number}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

