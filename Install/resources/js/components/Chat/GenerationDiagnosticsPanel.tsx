import type { BuildGenerationDiagnostics } from '@/builder/ai/blueprintTypes';
import { cn } from '@/lib/utils';

interface GenerationDiagnosticsPanelProps {
    diagnostics: BuildGenerationDiagnostics;
    className?: string;
}

function formatStepLabel(step: string): string {
    return step.replace(/_/g, ' ');
}

export function GenerationDiagnosticsPanel({
    diagnostics,
    className,
}: GenerationDiagnosticsPanelProps) {
    return (
        <details
            open={diagnostics.rootCause !== null}
            className={cn(
                'rounded-[22px] border border-slate-200 bg-slate-950/[0.03] p-4 text-left',
                className,
            )}
        >
            <summary className="cursor-pointer list-none text-sm font-semibold text-slate-900">
                Generation trace
            </summary>

            <div className="mt-4 space-y-4 text-sm text-slate-700">
                <div className="grid gap-3 md:grid-cols-2">
                    <div>
                        <div className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                            Selected projectType
                        </div>
                        <div className="mt-1 font-mono text-xs text-slate-900">
                            {diagnostics.selectedProjectType ?? 'n/a'}
                        </div>
                    </div>
                    <div>
                        <div className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                            Selected businessType
                        </div>
                        <div className="mt-1 font-mono text-xs text-slate-900">
                            {diagnostics.selectedBusinessType ?? 'n/a'}
                        </div>
                    </div>
                    <div>
                        <div className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                            Selected sections
                        </div>
                        <div className="mt-1 font-mono text-xs text-slate-900">
                            {diagnostics.selectedSections.length > 0 ? diagnostics.selectedSections.join(', ') : 'n/a'}
                        </div>
                    </div>
                    <div>
                        <div className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                            Selected component keys
                        </div>
                        <div className="mt-1 font-mono text-xs text-slate-900">
                            {diagnostics.selectedComponentKeys.length > 0 ? diagnostics.selectedComponentKeys.join(', ') : 'n/a'}
                        </div>
                    </div>
                    <div>
                        <div className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                            Fallback used
                        </div>
                        <div className="mt-1 font-mono text-xs text-slate-900">
                            {diagnostics.fallbackUsed ? 'yes' : 'no'}
                        </div>
                    </div>
                    <div>
                        <div className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                            Root cause
                        </div>
                        <div className={cn(
                            'mt-1 font-mono text-xs',
                            diagnostics.rootCause ? 'text-red-700' : 'text-slate-900',
                        )}>
                            {diagnostics.rootCause ?? 'none'}
                        </div>
                    </div>
                </div>

                <div>
                    <div className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                        Timeline
                    </div>
                    <div className="mt-2 space-y-2">
                        {diagnostics.events.map((event, index) => (
                            <div
                                key={`${event.step}-${event.message}-${index}`}
                                className="rounded-2xl border border-slate-200 bg-white/70 p-3"
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-500">
                                        {formatStepLabel(event.step)}
                                    </span>
                                    <span className={cn(
                                        'rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.14em]',
                                        event.status === 'success' && 'bg-emerald-100 text-emerald-800',
                                        event.status === 'failure' && 'bg-red-100 text-red-700',
                                        event.status === 'info' && 'bg-slate-100 text-slate-700',
                                    )}>
                                        {event.status}
                                    </span>
                                </div>
                                <div className="mt-2 text-sm font-medium text-slate-900">
                                    {event.message}
                                </div>
                                <pre className="mt-2 overflow-x-auto rounded-xl bg-slate-950 px-3 py-2 text-[11px] leading-5 text-slate-100">
                                    {JSON.stringify(event.payload, null, 2)}
                                </pre>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </details>
    );
}
