import { Head, Link } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';
import { CheckCircle2, Loader2, RefreshCw, Sparkles } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { useTranslation } from '@/contexts/LanguageContext';
import { isGeneratedSiteValidationMessage } from '@/builder/ai/validateGeneratedSite';
import {
    BUILDER_GENERATION_STEPS,
    getBuilderGenerationDefaultProgressMessage,
    getBuilderGenerationHeadline,
    getBuilderGenerationStepStatus,
    resolveBuilderGenerationState,
    type BuilderGenerationState,
} from '@/builder/state/builderGenerationState';

interface ProjectGenerationPayload {
    id: string;
    status: string;
    is_active: boolean;
    ready_for_builder: boolean;
    workspace_manifest_exists: boolean;
    workspace_preview_ready: boolean;
    workspace_preview_phase: string;
    active_generation_run_id?: string | null;
    progress_message?: string | null;
    error_message?: string | null;
    started_at?: string | null;
    completed_at?: string | null;
    failed_at?: string | null;
    status_url: string;
}

interface ProjectGenerationPageProps {
    project: {
        id: string;
        name: string;
        initial_prompt?: string | null;
    };
    generation: ProjectGenerationPayload;
    builderUrl: string;
    resumeDraftAvailable: boolean;
    resumeDraftMode: boolean;
    resumeDraftUrl: string | null;
    hideDraftUrl: string;
    resumeDraftPreviewUrl: string | null;
    createUrl: string;
}

export default function ProjectGeneration({
    project,
    generation: initialGeneration,
    builderUrl,
    resumeDraftAvailable,
    resumeDraftMode,
    resumeDraftUrl,
    hideDraftUrl,
    resumeDraftPreviewUrl,
    createUrl,
}: ProjectGenerationPageProps) {
    const { t } = useTranslation();
    const [generation, setGeneration] = useState<ProjectGenerationPayload>(initialGeneration);
    const redirectTimeoutRef = useRef<number | null>(null);
    const redirectScheduledRef = useRef(false);

    useEffect(() => {
        setGeneration(initialGeneration);
        redirectScheduledRef.current = false;
        if (redirectTimeoutRef.current !== null) {
            window.clearTimeout(redirectTimeoutRef.current);
            redirectTimeoutRef.current = null;
        }
    }, [initialGeneration, project.id]);

    const generationReadyForBuilder = generation.ready_for_builder === true;
    const generationState = useMemo<BuilderGenerationState>(() => (
        resolveBuilderGenerationState(generation.status, {
            readyForBuilder: generationReadyForBuilder,
        })
    ), [generation.status, generationReadyForBuilder]);

    const generationMessage = (generation.progress_message ?? '').trim() !== ''
        ? (generation.progress_message ?? '').trim()
        : getBuilderGenerationDefaultProgressMessage(generationState);
    const generationFailureIsValidation = isGeneratedSiteValidationMessage(generation.error_message);
    const generationFailureMessage = generation.error_message || t('We could not finish creating this website.');
    const generationFailureRecoveryMessage = generationFailureIsValidation
        ? t('The generated site failed validation before preview, so the builder stayed locked. Retry generation or repair the prompt.')
        : null;

    const timelineSteps = useMemo(() => (
        BUILDER_GENERATION_STEPS.map((step) => ({
            ...step,
            status: getBuilderGenerationStepStatus(generationState, step.key),
        }))
    ), [generationState]);

    useEffect(() => {
        if (!generationReadyForBuilder || redirectScheduledRef.current) {
            return;
        }

        redirectScheduledRef.current = true;
        redirectTimeoutRef.current = window.setTimeout(() => {
            window.location.replace(builderUrl);
        }, 700);

        return () => {
            if (redirectTimeoutRef.current !== null) {
                window.clearTimeout(redirectTimeoutRef.current);
                redirectTimeoutRef.current = null;
            }
        };
    }, [builderUrl, generationReadyForBuilder]);

    useEffect(() => {
        if (!generation.status_url || generationReadyForBuilder || generationState === 'failed') {
            return;
        }

        let cancelled = false;
        let timeoutId: number | null = null;

        const schedulePoll = (delay = 1200) => {
            if (cancelled) {
                return;
            }

            timeoutId = window.setTimeout(() => {
                void pollStatus();
            }, delay);
        };

        const pollStatus = async () => {
            try {
                const response = await axios.get<{ generation?: ProjectGenerationPayload | null }>(generation.status_url);
                if (cancelled) {
                    return;
                }

                const nextGeneration = response.data?.generation;
                if (nextGeneration) {
                    setGeneration(nextGeneration);
                    if (nextGeneration.ready_for_builder === true || nextGeneration.status === 'failed') {
                        return;
                    }
                }
            } catch {
                if (cancelled) {
                    return;
                }
            }

            schedulePoll();
        };

        schedulePoll();

        return () => {
            cancelled = true;
            if (timeoutId !== null) {
                window.clearTimeout(timeoutId);
            }
        };
    }, [generation.status_url, generationReadyForBuilder, generationState]);

    useEffect(() => () => {
        if (redirectTimeoutRef.current !== null) {
            window.clearTimeout(redirectTimeoutRef.current);
        }
    }, []);

    return (
        <>
            <Head title={t('Generating website')} />

            <div className="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(255,247,237,0.98),_rgba(251,249,244,0.95)_46%,_rgba(241,245,249,0.98)_100%)] text-[#1c1917]">
                <div className="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-6 py-8 lg:px-10">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <div className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[#8a857d]">
                                {t('AI-first build')}
                            </div>
                            <h1 className="mt-2 text-2xl font-semibold tracking-[-0.03em]">
                                {project.name}
                            </h1>
                        </div>

                        <div className="flex items-center gap-3">
                            <Link href={createUrl} className="text-sm font-medium text-[#57534e] underline-offset-4 hover:underline">
                                {t('Back to create')}
                            </Link>
                        </div>
                    </div>

                    <div className="mt-8 grid flex-1 gap-6 lg:grid-cols-[minmax(0,420px)_minmax(0,1fr)]">
                        <section className="rounded-[32px] border border-white/70 bg-white/85 p-6 shadow-[0_24px_70px_rgba(15,23,42,0.08)] backdrop-blur">
                            <div className="flex h-14 w-14 items-center justify-center rounded-[20px] bg-amber-50 text-[#b7791f]">
                                {generationState === 'failed' ? (
                                    <RefreshCw className="h-6 w-6" />
                                ) : generationReadyForBuilder ? (
                                    <CheckCircle2 className="h-6 w-6" />
                                ) : (
                                    <Sparkles className="h-6 w-6" />
                                )}
                            </div>

                            <div className="mt-5">
                                <h2 className="text-2xl font-semibold tracking-[-0.03em]">
                                    {t(getBuilderGenerationHeadline(generationState))}
                                </h2>
                                <p className="mt-3 text-sm leading-7 text-[#625f57]">
                                    {generationState === 'completed'
                                        ? t('Generation completed. Redirecting you to the builder.')
                                        : generationState === 'failed'
                                            ? generationFailureMessage
                                            : generationMessage}
                                </p>
                                {generationState === 'failed' && generationFailureRecoveryMessage ? (
                                    <p className="mt-2 text-sm leading-7 text-[#8a857d]">
                                        {generationFailureRecoveryMessage}
                                    </p>
                                ) : null}
                            </div>

                            {project.initial_prompt ? (
                                <div className="mt-5 rounded-[22px] border border-[#ece6dc] bg-[#fcfbf8] px-4 py-3">
                                    <div className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#8a857d]">
                                        {t('Prompt')}
                                    </div>
                                    <p className="mt-2 text-sm leading-6 text-[#44403c]">
                                        {project.initial_prompt}
                                    </p>
                                </div>
                            ) : null}

                            <div className="mt-6 space-y-3">
                                {timelineSteps.map((step) => (
                                    <div
                                        key={step.key}
                                        className={cn(
                                            'flex items-center justify-between gap-4 rounded-[22px] border px-4 py-3 transition-colors',
                                            step.status === 'complete' && 'border-emerald-200 bg-emerald-50/80 text-emerald-900',
                                            step.status === 'active' && 'border-amber-200 bg-amber-50 text-amber-950',
                                            step.status === 'pending' && 'border-[#e7dfd4] bg-[#fcfbf8] text-[#78716c]',
                                        )}
                                    >
                                        <div>
                                            <div className="text-sm font-semibold">{t(step.label)}</div>
                                            <div className="mt-1 text-xs uppercase tracking-[0.16em] text-current/70">
                                                {step.status === 'complete'
                                                    ? t('Completed')
                                                    : step.status === 'active'
                                                        ? t('In progress')
                                                        : t('Pending')}
                                            </div>
                                        </div>

                                        {step.status === 'active' ? (
                                            <Loader2 className="h-4 w-4 animate-spin" />
                                        ) : step.status === 'complete' ? (
                                            <CheckCircle2 className="h-4 w-4" />
                                        ) : (
                                            <span className="h-2.5 w-2.5 rounded-full bg-current/40" />
                                        )}
                                    </div>
                                ))}
                            </div>

                            <div className="mt-6 flex flex-wrap gap-3">
                                {generationState === 'failed' ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => window.location.reload()}
                                        className="rounded-full"
                                    >
                                        {generationFailureIsValidation ? t('Retry generation') : t('Refresh status')}
                                    </Button>
                                ) : null}

                                {resumeDraftAvailable ? (
                                    <Button asChild variant="outline" className="rounded-full">
                                        <Link href={resumeDraftMode ? hideDraftUrl : (resumeDraftUrl ?? hideDraftUrl)}>
                                            {resumeDraftMode ? t('Hide draft') : t('Resume draft')}
                                        </Link>
                                    </Button>
                                ) : null}

                                <Button
                                    asChild
                                    variant={generationState === 'failed' ? 'default' : 'secondary'}
                                    className="rounded-full"
                                >
                                    <Link href={createUrl}>
                                        {generationState === 'failed' && generationFailureIsValidation
                                            ? t('Repair prompt')
                                            : t('Create another project')}
                                    </Link>
                                </Button>
                            </div>
                        </section>

                        <section className="rounded-[32px] border border-white/70 bg-white/78 p-4 shadow-[0_24px_70px_rgba(15,23,42,0.06)] backdrop-blur">
                            {resumeDraftMode && resumeDraftPreviewUrl ? (
                                <div className="flex h-full min-h-[520px] flex-col overflow-hidden rounded-[28px] border border-[#e7dfd4] bg-[#f8f5ef]">
                                    <div className="flex items-center justify-between border-b border-[#e7dfd4] px-5 py-3">
                                        <div>
                                            <div className="text-sm font-semibold">{t('Resumed draft preview')}</div>
                                            <div className="text-xs text-[#78716c]">
                                                {t('This is a read-only preview while AI generation is still running.')}
                                            </div>
                                        </div>
                                        <span className="rounded-full bg-white px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-[#6b7280]">
                                            {t('Read only')}
                                        </span>
                                    </div>

                                    <iframe
                                        src={resumeDraftPreviewUrl}
                                        title={t('Resumed draft preview')}
                                        className="min-h-[460px] w-full flex-1 bg-white"
                                    />
                                </div>
                            ) : (
                                <div className="flex h-full min-h-[520px] items-center justify-center rounded-[28px] border border-dashed border-[#ddd6cb] bg-[#fcfbf8] px-8 text-center">
                                    <div className="max-w-md">
                                        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-[20px] bg-white text-[#b7791f] shadow-sm">
                                            <Sparkles className="h-6 w-6" />
                                        </div>
                                        <h3 className="mt-5 text-xl font-semibold tracking-[-0.02em]">
                                            {t('Builder stays locked until the AI build finishes')}
                                        </h3>
                                        <p className="mt-3 text-sm leading-7 text-[#625f57]">
                                            {t('The system is generating the blueprint, selecting components, filling content, assembling the page, and rendering the preview before the builder opens.')}
                                        </p>
                                        {resumeDraftAvailable ? (
                                            <p className="mt-3 text-sm leading-7 text-[#625f57]">
                                                {t('If you want to inspect the last saved draft while this session is still running, use Resume draft.')}
                                            </p>
                                        ) : null}
                                    </div>
                                </div>
                            )}
                        </section>
                    </div>
                </div>
            </div>
        </>
    );
}
