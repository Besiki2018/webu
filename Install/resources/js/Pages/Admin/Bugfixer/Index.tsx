import { useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import { Head, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import AdminLayout from '@/Layouts/AdminLayout';
import { AdminPageHeader } from '@/components/Admin/AdminPageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Bug, CheckCheck, Loader2, Play, Settings2 } from 'lucide-react';
import type { PageProps } from '@/types';

type BugFixStatus = 'pending' | 'running' | 'fixed' | 'ticket' | 'failed';

type BugEventItem = {
    bugId: string;
    timestamp: string;
    severity: string;
    source: string;
    event: string;
    frequency?: number;
    route?: string | null;
    fix_status?: 'pending' | 'fixed' | 'ticket';
    has_report?: boolean;
    has_ticket?: boolean;
};

type Config = {
    autoFixEnabled: boolean;
    severityThreshold: string;
    humanApprovalRequired: boolean;
};

type RunAutoFixResponse = {
    ok: boolean;
    bugId: string;
    message: string;
    fix_status: 'pending' | 'fixed' | 'ticket';
    has_report: boolean;
    has_ticket: boolean;
};

interface Props extends PageProps {
    events: BugEventItem[];
    config: Config;
}

const severityColor: Record<string, 'destructive' | 'default' | 'secondary'> = {
    critical: 'destructive',
    high: 'destructive',
    medium: 'default',
    low: 'secondary',
};

function normalizeInitialFixStatus(status?: BugEventItem['fix_status']): BugFixStatus {
    if (status === 'fixed' || status === 'ticket') {
        return status;
    }

    return 'pending';
}

function normalizeResponseFixStatus(response: RunAutoFixResponse): BugFixStatus {
    if (response.fix_status === 'fixed' || response.fix_status === 'ticket') {
        return response.fix_status;
    }

    return response.ok ? 'pending' : 'failed';
}

function labelForFixStatus(status: BugFixStatus): string {
    switch (status) {
        case 'running':
            return 'Running';
        case 'fixed':
            return 'Fixed';
        case 'ticket':
            return 'Needs Review';
        case 'failed':
            return 'Failed';
        default:
            return 'Pending';
    }
}

function badgeVariantForFixStatus(status: BugFixStatus): 'destructive' | 'default' | 'secondary' {
    switch (status) {
        case 'fixed':
            return 'default';
        case 'failed':
            return 'destructive';
        default:
            return 'secondary';
    }
}

export default function BugfixerIndex({ auth, events, config }: Props) {
    const { flash } = usePage().props as { flash?: { success?: string; error?: string } };

    const [selectedBugIds, setSelectedBugIds] = useState<string[]>([]);
    const [runningBugIds, setRunningBugIds] = useState<string[]>([]);
    const [bulkRunning, setBulkRunning] = useState(false);
    const [fixStatusByBugId, setFixStatusByBugId] = useState<Record<string, BugFixStatus>>(
        () => Object.fromEntries(events.map((ev) => [ev.bugId, normalizeInitialFixStatus(ev.fix_status)]))
    );

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        setFixStatusByBugId((prev) => {
            const next = { ...prev };

            events.forEach((ev) => {
                const initialStatus = normalizeInitialFixStatus(ev.fix_status);
                if (!next[ev.bugId] || initialStatus === 'fixed' || initialStatus === 'ticket') {
                    next[ev.bugId] = initialStatus;
                }
            });

            return next;
        });
    }, [events]);

    const eventIds = useMemo(() => events.map((ev) => ev.bugId), [events]);
    const allSelected = eventIds.length > 0 && selectedBugIds.length === eventIds.length;

    useEffect(() => {
        setSelectedBugIds((prev) => prev.filter((id) => eventIds.includes(id)));
    }, [eventIds]);

    const markRunning = (bugIds: string[]) => {
        setRunningBugIds((prev) => Array.from(new Set([...prev, ...bugIds])));
    };

    const unmarkRunning = (bugId: string) => {
        setRunningBugIds((prev) => prev.filter((id) => id !== bugId));
    };

    const isRunning = (bugId: string) => runningBugIds.includes(bugId);

    const updateConfig = (key: keyof Config, value: boolean | string) => {
        router.put(route('admin.bugfixer.settings.update'), {
            ...config,
            [key]: value,
        });
    };

    const executeAutoFix = async (
        bugId: string,
        options: { suppressToast?: boolean } = {}
    ): Promise<BugFixStatus> => {
        try {
            const { data } = await axios.post<RunAutoFixResponse>(
                route('admin.bugfixer.run'),
                { bugId },
                {
                    headers: {
                        Accept: 'application/json',
                    },
                }
            );

            const nextStatus = normalizeResponseFixStatus(data);
            setFixStatusByBugId((prev) => ({ ...prev, [bugId]: nextStatus }));

            if (!options.suppressToast) {
                if (data.ok) {
                    toast.success(data.message);
                } else {
                    toast.error(data.message);
                }
            }

            return nextStatus;
        } catch (error) {
            let message = 'Auto-fix run failed.';

            if (axios.isAxiosError(error)) {
                message = (error.response?.data as { message?: string } | undefined)?.message ?? error.message;
            }

            setFixStatusByBugId((prev) => ({ ...prev, [bugId]: 'failed' }));

            if (!options.suppressToast) {
                toast.error(message);
            }

            return 'failed';
        } finally {
            unmarkRunning(bugId);
        }
    };

    const runAutoFix = async (bugId: string) => {
        if (bulkRunning || isRunning(bugId)) {
            return;
        }

        markRunning([bugId]);
        setFixStatusByBugId((prev) => ({ ...prev, [bugId]: 'running' }));
        await executeAutoFix(bugId);
    };

    const runBulkAutoFix = async () => {
        const bugIds = selectedBugIds.filter((id) => !isRunning(id));

        if (bugIds.length === 0) {
            toast.error('Select at least one bug.');

            return;
        }

        setBulkRunning(true);
        markRunning(bugIds);
        setFixStatusByBugId((prev) => {
            const next = { ...prev };
            bugIds.forEach((id) => {
                next[id] = 'running';
            });

            return next;
        });

        let fixed = 0;
        let ticket = 0;
        let failed = 0;
        let pending = 0;

        for (const bugId of bugIds) {
            const status = await executeAutoFix(bugId, { suppressToast: true });
            if (status === 'fixed') {
                fixed += 1;
            } else if (status === 'ticket') {
                ticket += 1;
            } else if (status === 'failed') {
                failed += 1;
            } else {
                pending += 1;
            }
        }

        setBulkRunning(false);
        setSelectedBugIds([]);

        toast.success(
            `Bulk auto-fix completed. Fixed: ${fixed}, Needs Review: ${ticket}, Failed: ${failed}, Pending: ${pending}.`
        );
    };

    return (
        <AdminLayout user={auth.user!} title="AI Bug Fixer">
            <Head title="AI Bug Fixer" />
            <div className="min-w-0 max-w-full overflow-x-hidden">
                <AdminPageHeader
                    title="AI Auto Bug Fixer"
                    subtitle="Watch logs, build repro, apply verified patches only. Auto-fix runs for high/critical only."
                />

                <Card className="mb-6 min-w-0 max-w-full">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <Settings2 className="h-5 w-5" />
                            Controls
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-center gap-6">
                        <div className="flex items-center space-x-2">
                            <Switch
                                id="autoFix"
                                checked={config.autoFixEnabled}
                                onCheckedChange={(v) => updateConfig('autoFixEnabled', v)}
                            />
                            <Label htmlFor="autoFix">Auto-fix enabled</Label>
                        </div>
                        <div className="flex items-center gap-2">
                            <Label>Severity threshold</Label>
                            <Select
                                value={config.severityThreshold}
                                onValueChange={(v) => updateConfig('severityThreshold', v)}
                            >
                                <SelectTrigger className="w-40">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="critical">Critical only</SelectItem>
                                    <SelectItem value="high">High+</SelectItem>
                                    <SelectItem value="medium">Medium+</SelectItem>
                                    <SelectItem value="low">All</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex items-center space-x-2">
                            <Switch
                                id="approval"
                                checked={config.humanApprovalRequired}
                                onCheckedChange={(v) => updateConfig('humanApprovalRequired', v)}
                            />
                            <Label htmlFor="approval">Require approval before apply</Label>
                        </div>
                    </CardContent>
                </Card>

                <Card className="min-w-0 max-w-full">
                    <CardHeader className="min-w-0">
                        <CardTitle className="flex items-center gap-2 text-lg min-w-0">
                            <Bug className="h-5 w-5 shrink-0" />
                            <span className="truncate">Bug events (by severity, frequency)</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="min-w-0 max-w-full overflow-x-hidden">
                        {events.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No bug events yet.</p>
                        ) : (
                            <>
                                <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3">
                                    <div className="flex items-center gap-3">
                                        <Checkbox
                                            id="bugfixer-select-all"
                                            checked={allSelected}
                                            onCheckedChange={(checked) => {
                                                if (checked === true) {
                                                    setSelectedBugIds(eventIds);
                                                } else {
                                                    setSelectedBugIds([]);
                                                }
                                            }}
                                        />
                                        <Label htmlFor="bugfixer-select-all" className="text-sm">
                                            Select all
                                        </Label>
                                        <span className="text-xs text-muted-foreground">
                                            {selectedBugIds.length} selected
                                        </span>
                                    </div>
                                    <Button
                                        size="sm"
                                        onClick={runBulkAutoFix}
                                        disabled={selectedBugIds.length === 0 || bulkRunning}
                                    >
                                        {bulkRunning ? (
                                            <Loader2 className="h-4 w-4 mr-1 animate-spin" />
                                        ) : (
                                            <CheckCheck className="h-4 w-4 mr-1" />
                                        )}
                                        {bulkRunning ? 'Running...' : 'Bulk Auto-Fix'}
                                    </Button>
                                </div>

                                <ul className="space-y-3 min-w-0 max-w-full">
                                    {events.map((ev) => {
                                        const fixStatus = fixStatusByBugId[ev.bugId] ?? normalizeInitialFixStatus(ev.fix_status);
                                        const running = isRunning(ev.bugId);
                                        const checked = selectedBugIds.includes(ev.bugId);

                                        return (
                                            <li
                                                key={ev.bugId}
                                                className="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 min-w-0"
                                            >
                                                <div className="flex min-w-0 flex-1 items-start gap-3 overflow-hidden">
                                                    <Checkbox
                                                        checked={checked}
                                                        disabled={running || bulkRunning}
                                                        className="mt-1 shrink-0"
                                                        onCheckedChange={(isChecked) => {
                                                            setSelectedBugIds((prev) => {
                                                                if (isChecked === true) {
                                                                    return Array.from(new Set([...prev, ev.bugId]));
                                                                }

                                                                return prev.filter((id) => id !== ev.bugId);
                                                            });
                                                        }}
                                                    />
                                                    <div className="min-w-0 flex-1 overflow-hidden">
                                                        <div className="flex items-center gap-2 flex-wrap">
                                                            <Badge variant={severityColor[ev.severity] ?? 'secondary'} className="shrink-0">
                                                                {ev.severity}
                                                            </Badge>
                                                            <Badge
                                                                variant={badgeVariantForFixStatus(fixStatus)}
                                                                className={fixStatus === 'fixed' ? 'bg-emerald-600 text-white hover:bg-emerald-600' : undefined}
                                                            >
                                                                {running && <Loader2 className="h-3 w-3 mr-1 animate-spin" />}
                                                                {labelForFixStatus(fixStatus)}
                                                            </Badge>
                                                            <span className="text-muted-foreground text-xs truncate">{ev.source}</span>
                                                            {ev.frequency != null && ev.frequency > 1 && (
                                                                <span className="text-muted-foreground text-xs shrink-0">×{ev.frequency}</span>
                                                            )}
                                                        </div>
                                                        <p className="mt-1 truncate text-sm">{ev.event}</p>
                                                        {ev.route && (
                                                            <p className="text-muted-foreground text-xs truncate">{ev.route}</p>
                                                        )}
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2 shrink-0">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => router.visit(route('admin.bugfixer.show', { bugId: ev.bugId }))}
                                                    >
                                                        View
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        disabled={running || bulkRunning}
                                                        onClick={() => {
                                                            void runAutoFix(ev.bugId);
                                                        }}
                                                    >
                                                        {running ? (
                                                            <Loader2 className="h-4 w-4 mr-1 animate-spin" />
                                                        ) : (
                                                            <Play className="h-4 w-4 mr-1" />
                                                        )}
                                                        {running ? 'Running...' : 'Run Auto-Fix'}
                                                    </Button>
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
