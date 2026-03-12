import { useCallback, useEffect, useMemo, useState } from 'react';
import { ColumnDef } from '@tanstack/react-table';
import { TanStackDataTable } from '@/components/Admin/TanStackDataTable';
import { DataTableColumnHeader } from '@/components/ui/data-table-column-header';
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
import type { OperationLogRecord, OperationLogsResponse } from '@/types/admin';

const CHANNEL_OPTIONS = ['all', 'build', 'publish', 'payment', 'subscription', 'booking', 'system'] as const;
const STATUS_OPTIONS = ['all', 'info', 'success', 'warning', 'error'] as const;

function formatDateTime(dateString: string | null, locale: string): string {
    if (!dateString) return '-';

    return new Intl.DateTimeFormat(locale, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(new Date(dateString));
}

export function OperationLogsTable() {
    const { t, locale } = useTranslation();
    const [rows, setRows] = useState<OperationLogRecord[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [channel, setChannel] = useState<string>('all');
    const [status, setStatus] = useState<string>('all');
    const [source, setSource] = useState<string>('all');
    const [currentPage, setCurrentPage] = useState(0);
    const [pageSize, setPageSize] = useState(20);
    const [pagination, setPagination] = useState({
        total: 0,
        lastPage: 1,
    });
    const [selected, setSelected] = useState<OperationLogRecord | null>(null);

    const fetchLogs = useCallback(async () => {
        setLoading(true);

        try {
            const params = new URLSearchParams({
                page: (currentPage + 1).toString(),
                per_page: pageSize.toString(),
            });

            if (search) params.append('search', search);
            if (channel !== 'all') params.append('channel', channel);
            if (status !== 'all') params.append('status', status);
            if (source !== 'all') params.append('source', source);

            const response = await fetch(`/admin/operation-logs/data?${params.toString()}`);
            const data: OperationLogsResponse = await response.json();

            setRows(data.data);
            setPagination({
                total: data.total,
                lastPage: data.last_page,
            });
        } catch (error) {
            console.error('Failed to fetch operation logs', error);
        } finally {
            setLoading(false);
        }
    }, [currentPage, pageSize, search, channel, status, source]);

    useEffect(() => {
        fetchLogs();
    }, [fetchLogs]);

    const availableSources = useMemo(() => {
        const sourceSet = new Set<string>();
        rows.forEach((row) => {
            if (row.source) sourceSet.add(row.source);
        });

        return Array.from(sourceSet).sort();
    }, [rows]);

    const columns: ColumnDef<OperationLogRecord>[] = [
        {
            accessorKey: 'occurred_at',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Occurred At')} />
            ),
            cell: ({ row }) => (
                <span className="text-sm whitespace-nowrap">
                    {formatDateTime(row.original.occurred_at, locale)}
                </span>
            ),
        },
        {
            accessorKey: 'channel',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Channel')} />
            ),
            cell: ({ row }) => (
                <Badge variant="outline" className="uppercase text-[10px] tracking-wide">
                    {row.original.channel}
                </Badge>
            ),
        },
        {
            accessorKey: 'status',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Status')} />
            ),
            cell: ({ row }) => {
                const value = row.original.status;
                const className = value === 'success'
                    ? 'bg-success/10 text-success hover:bg-success/10'
                    : value === 'error'
                    ? 'bg-destructive/10 text-destructive hover:bg-destructive/10'
                    : value === 'warning'
                    ? 'bg-yellow-500/10 text-yellow-600 dark:text-yellow-400 hover:bg-yellow-500/10'
                    : 'bg-muted text-muted-foreground';

                return (
                    <Badge variant="secondary" className={className}>
                        {value}
                    </Badge>
                );
            },
        },
        {
            accessorKey: 'event',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Event')} />
            ),
            cell: ({ row }) => (
                <span className="text-sm font-medium">{row.original.event}</span>
            ),
        },
        {
            accessorKey: 'project',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Project')} />
            ),
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground">
                    {row.original.project?.name || '-'}
                </span>
            ),
            enableSorting: false,
        },
        {
            accessorKey: 'domain',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Domain')} />
            ),
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground">{row.original.domain || '-'}</span>
            ),
            enableSorting: false,
        },
        {
            accessorKey: 'message',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Message')} />
            ),
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground truncate block max-w-[260px]">
                    {row.original.message || '-'}
                </span>
            ),
            enableSorting: false,
        },
        {
            id: 'actions',
            enableHiding: false,
            cell: ({ row }) => (
                <Button variant="ghost" size="sm" onClick={() => setSelected(row.original)}>
                    <Eye className="h-4 w-4" />
                </Button>
            ),
        },
    ];

    return (
        <div className="space-y-4">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div className="relative max-w-sm">
                    <Search className="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input
                        placeholder={t('Search logs...')}
                        value={search}
                        onChange={(e) => {
                            setSearch(e.target.value);
                            setCurrentPage(0);
                        }}
                        className="ps-9 w-[300px]"
                    />
                </div>

                <div className="flex flex-wrap gap-2 items-center">
                    <Select
                        value={channel}
                        onValueChange={(value) => {
                            setChannel(value);
                            setCurrentPage(0);
                        }}
                    >
                        <SelectTrigger className="w-[140px] h-8">
                            <SelectValue placeholder={t('Channel')} />
                        </SelectTrigger>
                        <SelectContent>
                            {CHANNEL_OPTIONS.map((option) => (
                                <SelectItem key={option} value={option}>
                                    {option === 'all' ? t('All Channels') : option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={status}
                        onValueChange={(value) => {
                            setStatus(value);
                            setCurrentPage(0);
                        }}
                    >
                        <SelectTrigger className="w-[120px] h-8">
                            <SelectValue placeholder={t('Status')} />
                        </SelectTrigger>
                        <SelectContent>
                            {STATUS_OPTIONS.map((option) => (
                                <SelectItem key={option} value={option}>
                                    {option === 'all' ? t('All Status') : option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={source}
                        onValueChange={(value) => {
                            setSource(value);
                            setCurrentPage(0);
                        }}
                    >
                        <SelectTrigger className="w-[190px] h-8">
                            <SelectValue placeholder={t('Source')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{t('All Sources')}</SelectItem>
                            {availableSources.map((item) => (
                                <SelectItem key={item} value={item}>
                                    {item}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8"
                        onClick={() => fetchLogs()}
                        disabled={loading}
                    >
                        <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                    </Button>
                </div>
            </div>

            {loading && rows.length === 0 ? (
                <div className="rounded-md border bg-card">
                    <div className="h-24 flex items-center justify-center">
                        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                    </div>
                </div>
            ) : (
                <TanStackDataTable
                    columns={columns}
                    data={rows}
                    showSearch={false}
                    serverPagination={{
                        pageCount: pagination.lastPage,
                        pageIndex: currentPage,
                        pageSize,
                        total: pagination.total,
                        onPageChange: (page) => setCurrentPage(page),
                        onPageSizeChange: (size) => {
                            setPageSize(size);
                            setCurrentPage(0);
                        },
                    }}
                />
            )}

            <Dialog open={!!selected} onOpenChange={() => setSelected(null)}>
                <DialogContent className="sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>{selected?.event || t('Log Details')}</DialogTitle>
                        <DialogDescription>
                            {selected?.message || t('Detailed operation context')}
                        </DialogDescription>
                    </DialogHeader>

                    {selected && (
                        <div className="space-y-3">
                            <div className="grid gap-3 text-sm sm:grid-cols-2">
                                <p><span className="text-muted-foreground">{t('Channel')}: </span>{selected.channel}</p>
                                <p><span className="text-muted-foreground">{t('Status')}: </span>{selected.status}</p>
                                <p><span className="text-muted-foreground">{t('Source')}: </span>{selected.source || '-'}</p>
                                <p><span className="text-muted-foreground">{t('Identifier')}: </span>{selected.identifier || '-'}</p>
                                <p><span className="text-muted-foreground">{t('Project')}: </span>{selected.project?.name || '-'}</p>
                                <p><span className="text-muted-foreground">{t('Domain')}: </span>{selected.domain || '-'}</p>
                            </div>

                            <pre className="rounded-md bg-muted p-3 text-xs overflow-x-auto">
                                {JSON.stringify(selected.context || {}, null, 2)}
                            </pre>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}
