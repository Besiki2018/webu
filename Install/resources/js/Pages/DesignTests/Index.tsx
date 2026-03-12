import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { AdminPageHeader } from '@/components/Admin/AdminPageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { CheckCircle2, AlertTriangle, RefreshCw, LayoutDashboard } from 'lucide-react';
import type { PageProps } from '@/types';

type DesignIssue = {
    code: string;
    message: string;
    severity: string;
};

type TestResult = {
    vertical: string;
    label: string;
    template_slug: string;
    design_score: number;
    passed: boolean;
    design_issues: DesignIssue[];
    logged: boolean;
};

interface DesignTestsProps extends PageProps {
    threshold: number;
    results: TestResult[];
}

export default function DesignTestsIndex({ auth, threshold, results }: DesignTestsProps) {
    const passedCount = results.filter((r) => r.passed).length;
    const failedCount = results.length - passedCount;

    const runAgain = () => {
        router.reload();
    };

    return (
        <AdminLayout user={auth.user!} title="Design Tests">
            <Head title="Design Tests" />
            <AdminPageHeader
                title="Design Tests"
                subtitle="Generated sites and design quality scores per vertical. Scores below threshold are logged as design issues."
            />

            <div className="space-y-6">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium flex items-center gap-2">
                            <LayoutDashboard className="h-4 w-4" />
                            Summary
                        </CardTitle>
                        <Button variant="outline" size="sm" onClick={runAgain} className="gap-2">
                            <RefreshCw className="h-4 w-4" />
                            Run again
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <p className="text-muted-foreground text-sm">
                            Threshold: <strong>{threshold}</strong> — Verticals passing:{' '}
                            <strong className="text-green-600">{passedCount}</strong> / {results.length}
                            {failedCount > 0 && (
                                <span className="text-amber-600"> — Failing: {failedCount} (logged)</span>
                            )}
                        </p>
                    </CardContent>
                </Card>

                <div className="grid gap-4">
                    {results.map((r) => (
                        <Card key={r.vertical} className={r.passed ? '' : 'border-amber-500/50'}>
                            <CardHeader className="pb-2">
                                <div className="flex flex-row items-center justify-between">
                                    <CardTitle className="text-base flex items-center gap-2">
                                        {r.passed ? (
                                            <CheckCircle2 className="h-5 w-5 text-green-600" />
                                        ) : (
                                            <AlertTriangle className="h-5 w-5 text-amber-600" />
                                        )}
                                        {r.label}
                                    </CardTitle>
                                    <div className="flex items-center gap-2">
                                        <Badge variant={r.passed ? 'default' : 'destructive'}>
                                            Score: {r.design_score}
                                        </Badge>
                                        {r.logged && (
                                            <Badge variant="secondary">Logged</Badge>
                                        )}
                                    </div>
                                </div>
                                <p className="text-muted-foreground text-sm">Template: {r.template_slug}</p>
                            </CardHeader>
                            {r.design_issues.length > 0 && (
                                <CardContent className="pt-0">
                                    <p className="text-sm font-medium text-muted-foreground mb-2">Issues:</p>
                                    <ul className="list-disc list-inside text-sm space-y-1">
                                        {r.design_issues.map((issue, i) => (
                                            <li key={i}>
                                                <span className="font-mono text-xs text-muted-foreground">{issue.code}</span>
                                                {' — '}
                                                {issue.message}
                                                {issue.severity !== 'info' && (
                                                    <Badge variant="outline" className="ml-2 text-xs">
                                                        {issue.severity}
                                                    </Badge>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            )}
                        </Card>
                    ))}
                </div>
            </div>
        </AdminLayout>
    );
}
