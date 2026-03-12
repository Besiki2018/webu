import { Loader2, Check, AlertCircle } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { BuildProgress } from '@/hooks/useBuilderChat';

export type AgentPhase =
    | 'connecting'
    | 'analyzing'
    | 'locating'
    | 'editing'
    | 'uploading'
    | 'updating_preview'
    | 'completed'
    | 'failed';

/** Ordered pipeline steps shown during agent execution (Lovable/Codex-style). */
export const AGENT_PIPELINE_STEPS: AgentPhase[] = [
    'analyzing',
    'locating',
    'editing',
    'uploading',
    'updating_preview',
    'completed',
];

interface AgentProgressInlineProps {
    progress: BuildProgress;
    /** Optional: current step label override */
    currentStepLabel?: string;
    /** When true, show the full pipeline strip (Analyzing → … → Done) */
    showPipelineSteps?: boolean;
    className?: string;
}

const PHASE_LABELS: Record<AgentPhase, string> = {
    connecting: 'Connecting...',
    analyzing: 'Analyzing...',
    locating: 'Understanding request...',
    editing: 'Applying changes...',
    uploading: 'Uploading media...',
    updating_preview: 'Updating preview...',
    completed: 'Done',
    failed: 'Failed',
};

function inferPhase(progress: BuildProgress): AgentPhase {
    if (progress.status === 'connecting') return 'connecting';
    if (progress.status === 'failed') return 'failed';
    if (progress.status === 'completed' || progress.status === 'cancelled') return 'completed';

    if (progress.status === 'running') {
        if (progress.thinkingContent) return 'analyzing';
        const last = progress.actions[progress.actions.length - 1];
        if (last) {
            const a = (last.action ?? '').toLowerCase();
            const c = (last.category ?? '').toLowerCase();
            if (c === 'analyzing' || a.includes('analyz')) return 'analyzing';
            if (c === 'reading' || a.includes('read') || a.includes('locat')) return 'locating';
            if (c === 'modifying' || c === 'creating' || a.includes('edit') || a.includes('updat')) return 'editing';
            if (a.includes('upload') || a.includes('image') || a.includes('media')) return 'uploading';
            if (a.includes('preview') || a.includes('refresh') || a.includes('render')) return 'updating_preview';
            return 'editing';
        }
        return 'analyzing';
    }

    return 'analyzing';
}

function pipelineStepIndex(phase: AgentPhase): number {
    const i = AGENT_PIPELINE_STEPS.indexOf(phase);
    return i >= 0 ? i : 0;
}

export function AgentProgressInline({ progress, currentStepLabel, showPipelineSteps = false, className }: AgentProgressInlineProps) {
    const phase = inferPhase(progress);
    const isActive = progress.status === 'running' || progress.status === 'connecting';
    const isDone = phase === 'completed';
    const isFailed = phase === 'failed';

    const label = currentStepLabel ?? (progress.thinkingContent && progress.thinkingContent.length < 60
        ? progress.thinkingContent
        : PHASE_LABELS[phase]);

    const currentStepIndex = pipelineStepIndex(phase);

    return (
        <div className={cn('flex flex-col gap-2', className)}>
            {showPipelineSteps && (isActive || isDone || isFailed) && (
                <div className="flex flex-wrap items-center gap-1 text-xs">
                    {AGENT_PIPELINE_STEPS.map((step, index) => {
                        const isCurrent = phase === step;
                        const isPast = currentStepIndex > index;
                        const isFuture = currentStepIndex < index;
                        return (
                            <span key={step} className="flex items-center gap-1">
                                <span
                                    className={cn(
                                        'rounded px-1.5 py-0.5 font-medium',
                                        isCurrent && 'bg-primary/20 text-primary',
                                        isPast && 'text-muted-foreground',
                                        isFuture && 'text-muted-foreground/60'
                                    )}
                                >
                                    {PHASE_LABELS[step]}
                                </span>
                                {index < AGENT_PIPELINE_STEPS.length - 1 && (
                                    <span className="text-muted-foreground/50">→</span>
                                )}
                            </span>
                        );
                    })}
                </div>
            )}
            <div
                className={cn(
                    'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm w-fit',
                    isActive && 'border-primary/30 bg-primary/5',
                    isDone && 'border-green-300 bg-green-50 text-green-800 dark:bg-green-950/40 dark:text-green-200',
                    isFailed && 'border-red-200 bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-200'
                )}
            >
                {isActive && <Loader2 className="h-4 w-4 shrink-0 animate-spin text-primary" />}
                {isDone && <Check className="h-4 w-4 shrink-0 text-green-600" />}
                {isFailed && <AlertCircle className="h-4 w-4 shrink-0 text-red-600" />}
                <span className="font-medium">{label}</span>
            </div>
        </div>
    );
}

interface AgentActionLogProps {
    items: string[];
    title?: string;
    className?: string;
}

export function AgentActionLog({ items, title = 'Completed changes', className }: AgentActionLogProps) {
    if (items.length === 0) return null;

    return (
        <div className={cn('mt-3 rounded-lg border border-border/60 bg-muted/30 px-3 py-2', className)}>
            <div className="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1.5">
                {title}
            </div>
            <ul className="space-y-1 text-sm">
                {items.map((line, i) => (
                    <li key={i} className="flex items-center gap-2">
                        <span className="text-primary">•</span>
                        <span>{line}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
