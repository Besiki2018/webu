import { useCallback, useEffect, useMemo, useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Loader2, RefreshCw, Search, Eye } from 'lucide-react';
import { useTranslation } from '@/contexts/LanguageContext';

interface ProjectOperationLogsCardProps {
    projectId: string;
}

interface ProjectOperationLog {
    id: number;
    channel: string;
    event: string;
    status: 'info' | 'success' | 'warning' | 'error';
    source: string | null;
    domain: string | null;
    identifier: string | null;
    message: string | null;
    context: Record<string, unknown>;
    occurred_at: string | null;
}

function formatDateTime(dateString: string | null, locale: string): string {
    if (!dateString) return '-';

    return new Intl.DateTimeFormat(locale, {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(new Date(dateString));
}

export function ProjectOperationLogsCard({ projectId }: ProjectOperationLogsCardProps) {
    const { t, locale } = useTranslation();
    const [logs, setLogs] = useState<ProjectOperationLog[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [channel, setChannel] = useState('all');
    const [status, setStatus] = useState('all');
    const [selected, setSelected] = useState<ProjectOperationLog | null>(null);

    const channelsQuery = useMemo(() => {
        if (channel !== 'all') return channel;

        return 'build,publish,payment,subscription,booking,system';
    }, [channel]);

    const fetchLogs = useCallback(async () => {
        setLoading(true);

        try {
            const params = new URLSearchParams({
                limit: '80',
                channels: channelsQuery,
            });

            if (status !== 'all') params.append('status', status);
            if (search) params.append('search', search);

            const response = await fetch(`/project/${projectId}/operation-logs?${params.toString()}`);
            const payload = await response.json();
            setLogs(payload.data || []);
        } catch (error) {
            console.error('Failed to fetch project operation logs', error);
        } finally {
            setLoading(false);
        }
    }, [projectId, channelsQuery, search, status]);

    useEffect(() => {
        fetchLogs();
    }, [fetchLogs]);

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('Operation Logs')}</CardTitle>
                <CardDescription>
                    {t('Track recent build, publish, and payment related events for this project.')}
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="relative max-w-sm">
                        <Search className="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={t('Search logs...')}
                            className="ps-9 w-[300px]"
                        />
                    </div>

                    <div className="flex flex-wrap gap-2 items-center">
                        <Select value={channel} onValueChange={setChannel}>
                            <SelectTrigger className="w-[150px] h-8">
                                <SelectValue placeholder={t('Channel')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('All Channels')}</SelectItem>
                                <SelectItem value="build">build</SelectItem>
                                <SelectItem value="publish">publish</SelectItem>
                                <SelectItem value="payment">payment</SelectItem>
                                <SelectItem value="subscription">subscription</SelectItem>
                                <SelectItem value="system">system</SelectItem>
                            </SelectContent>
                        </Select>

                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger className="w-[120px] h-8">
                                <SelectValue placeholder={t('Status')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('All Status')}</SelectItem>
                                <SelectItem value="info">info</SelectItem>
                                <SelectItem value="success">success</SelectItem>
                                <SelectItem value="warning">warning</SelectItem>
                                <SelectItem value="error">error</SelectItem>
                            </SelectContent>
                        </Select>

                        <Button
                            variant="outline"
                            size="sm"
                            className="h-8"
                            disabled={loading}
                            onClick={fetchLogs}
                        >
                            <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                        </Button>
                    </div>
                </div>

                <div className="rounded-md border">
                    {loading ? (
                        <div className="h-24 flex items-center justify-center">
                            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                        </div>
                    ) : logs.length === 0 ? (
                        <div className="h-24 flex items-center justify-center text-sm text-muted-foreground">
                            {t('No logs found for selected filters.')}
                        </div>
                    ) : (
                        <div className="max-h-[420px] overflow-auto divide-y">
                            {logs.map((log) => {
                                const statusClass = log.status === 'success'
                                    ? 'bg-success/10 text-success hover:bg-success/10'
                                    : log.status === 'error'
                                    ? 'bg-destructive/10 text-destructive hover:bg-destructive/10'
                                    : log.status === 'warning'
                                    ? 'bg-yellow-500/10 text-yellow-600 dark:text-yellow-400 hover:bg-yellow-500/10'
                                    : 'bg-muted text-muted-foreground';

                                return (
                                    <div key={log.id} className="p-3 flex items-start justify-between gap-3">
                                        <div className="min-w-0 space-y-1">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <Badge variant="outline" className="uppercase text-[10px] tracking-wide">
                                                    {log.channel}
                                                </Badge>
                                                <Badge variant="secondary" className={statusClass}>
                                                    {log.status}
                                                </Badge>
                                                <span className="text-xs text-muted-foreground">
                                                    {formatDateTime(log.occurred_at, locale)}
                                                </span>
                                            </div>

                                            <p className="text-sm font-medium break-all">{log.event}</p>
                                            {log.message && (
                                                <p className="text-xs text-muted-foreground break-all">{log.message}</p>
                                            )}
                                            {log.domain && (
                                                <p className="text-xs text-muted-foreground">Domain: {log.domain}</p>
                                            )}
                                        </div>

                                        <Button variant="ghost" size="sm" onClick={() => setSelected(log)}>
                                            <Eye className="h-4 w-4" />
                                        </Button>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </CardContent>

            <Dialog open={!!selected} onOpenChange={() => setSelected(null)}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{selected?.event || t('Log Details')}</DialogTitle>
                        <DialogDescription>{selected?.message || ''}</DialogDescription>
                    </DialogHeader>

                    {selected && (
                        <div className="space-y-3 text-sm">
                            <div className="grid gap-2 sm:grid-cols-2">
                                <p><span className="text-muted-foreground">{t('Channel')}: </span>{selected.channel}</p>
                                <p><span className="text-muted-foreground">{t('Status')}: </span>{selected.status}</p>
                                <p><span className="text-muted-foreground">{t('Source')}: </span>{selected.source || '-'}</p>
                                <p><span className="text-muted-foreground">{t('Identifier')}: </span>{selected.identifier || '-'}</p>
                                <p><span className="text-muted-foreground">{t('Domain')}: </span>{selected.domain || '-'}</p>
                                <p><span className="text-muted-foreground">{t('Occurred At')}: </span>{formatDateTime(selected.occurred_at, locale)}</p>
                            </div>

                            <pre className="rounded-md bg-muted p-3 text-xs overflow-x-auto">
                                {JSON.stringify(selected.context || {}, null, 2)}
                            </pre>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </Card>
    );
}
