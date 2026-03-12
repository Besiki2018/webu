import { useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import AdminLayout from '@/Layouts/AdminLayout';
import { AdminPageHeader } from '@/components/Admin/AdminPageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Bug, Download, FileText, Play } from 'lucide-react';
import type { PageProps } from '@/types';

type BugEvent = {
    bugId: string;
    timestamp: string;
    severity: string;
    source: string;
    event: string;
    stack?: string | null;
    route?: string | null;
    frequency?: number;
    context?: Record<string, unknown>;
};

interface Props extends PageProps {
    event: BugEvent;
    reproFiles: string[];
    verifyLogs: string[];
    hasPatch: boolean;
    hasTicket: boolean;
    hasReport: boolean;
    reproDir: string;
    ticketPath: string | null;
}

export default function BugfixerShow({ auth, event, reproFiles, verifyLogs, hasPatch, hasTicket, hasReport }: Props) {
    const { flash } = usePage().props as { flash?: { success?: string; error?: string } };
    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    const runAutoFix = () => {
        router.post(route('admin.bugfixer.run'), { bugId: event.bugId });
    };

    return (
        <AdminLayout user={auth.user!} title={`Bug ${event.bugId}`}>
            <Head title={`Bug ${event.bugId}`} />
            <AdminPageHeader
                title={event.bugId}
                subtitle={`${event.severity} · ${event.source} · ${event.frequency ?? 1} occurrence(s)`}
                action={
                    <Button onClick={runAutoFix}>
                        <Play className="h-4 w-4 mr-2" />
                        Run Auto-Fix
                    </Button>
                }
            />

            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <Bug className="h-5 w-5" />
                            Event
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <p className="text-sm whitespace-pre-wrap">{event.event}</p>
                        {event.route && (
                            <p className="text-muted-foreground text-xs">Route: {event.route}</p>
                        )}
                        {event.stack && (
                            <pre className="mt-2 rounded bg-muted p-3 text-xs overflow-auto max-h-48">
                                {event.stack}
                            </pre>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <FileText className="h-5 w-5" />
                            Repro pack
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {reproFiles.length > 0 ? (
                            <>
                                <ul className="text-sm space-y-1 mb-3">
                                    {reproFiles.map((f) => (
                                        <li key={f}>{f}</li>
                                    ))}
                                </ul>
                                <a href={route('admin.bugfixer.repro-pack', { bugId: event.bugId })}>
                                    <Button variant="outline" size="sm">
                                        <Download className="h-4 w-4 mr-2" />
                                        Download repro pack
                                    </Button>
                                </a>
                            </>
                        ) : (
                            <p className="text-muted-foreground text-sm">No repro pack generated yet.</p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Verification logs</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {verifyLogs.length > 0 ? (
                            <ul className="text-sm space-y-1">
                                {verifyLogs.map((f) => {
                                    const step = f.replace(/\.log$/, '');
                                    return (
                                        <li key={f}>
                                            <a
                                                href={route('admin.bugfixer.verify-log', { bugId: event.bugId, step })}
                                                className="text-primary hover:underline"
                                            >
                                                {f}
                                            </a>
                                        </li>
                                    );
                                })}
                            </ul>
                        ) : (
                            <p className="text-muted-foreground text-sm">No verification logs yet.</p>
                        )}
                    </CardContent>
                </Card>

                <div className="flex flex-wrap gap-2 items-center">
                    {hasPatch && (
                        <>
                            <Badge variant="secondary">Patch attempted</Badge>
                            <a href={route('admin.bugfixer.patch', { bugId: event.bugId })}>
                                <Button variant="ghost" size="sm">View applied patch</Button>
                            </a>
                        </>
                    )}
                    {hasTicket && (
                        <>
                            <Badge variant="outline">Ticket created</Badge>
                            <a href={route('admin.bugfixer.ticket', { bugId: event.bugId })}>
                                <Button variant="ghost" size="sm">Download ticket</Button>
                            </a>
                        </>
                    )}
                    {hasReport && <Badge variant="default">Fixed</Badge>}
                </div>

                <div className="flex gap-2">
                    <Link href={route('admin.bugfixer')}>
                        <Button variant="outline">Back to list</Button>
                    </Link>
                </div>
            </div>
        </AdminLayout>
    );
}
