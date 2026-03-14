import { useEffect, useMemo, useState } from 'react';
import { CheckCircle2, Loader2, RefreshCw, Sparkles } from 'lucide-react';

import { useBuilderStore } from '@/builder/store/builderStore';
import {
    BUILDER_GENERATION_STEPS,
    getBuilderGenerationStepStatus,
} from '@/builder/state/builderGenerationState';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/contexts/LanguageContext';
import { cn } from '@/lib/utils';

interface AIGenerationOverlayProps {
    onRetry?: (() => void) | null;
    onCreateAnother?: (() => void) | null;
}

const FADE_OUT_DURATION_MS = 240;

export function AIGenerationOverlay({
    onRetry = null,
    onCreateAnother = null,
}: AIGenerationOverlayProps) {
    const { t, locale } = useTranslation();
    const generationStage = useBuilderStore((state) => state.generationStage);
    const generationProgress = useBuilderStore((state) => state.generationProgress);
    const generationDiagnostics = useBuilderStore((state) => state.generationDiagnostics);
    const isGeorgian = locale.toLowerCase().startsWith('ka');
    const shouldDisplay = generationProgress.locked || generationProgress.isFailed;
    const [isRendered, setIsRendered] = useState(shouldDisplay);
    const [isVisible, setIsVisible] = useState(shouldDisplay);

    useEffect(() => {
        if (shouldDisplay) {
            setIsRendered(true);
            const frameId = window.requestAnimationFrame(() => {
                setIsVisible(true);
            });

            return () => {
                window.cancelAnimationFrame(frameId);
            };
        }

        setIsVisible(false);
        const timeoutId = window.setTimeout(() => {
            setIsRendered(false);
        }, FADE_OUT_DURATION_MS);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [shouldDisplay]);

    const stageCopy = useMemo(() => ({
        assistantLabel: isGeorgian ? 'AI გენერაცია' : 'AI generation',
        canvasHint: isGeorgian
            ? 'გენერაცია პირდაპირ ამ კანვასში მიმდინარეობს. ჩატი რჩება გახსნილი, ხოლო კანვასი დაიბლოკება საბოლოო შედეგამდე.'
            : 'Generation is running directly inside this canvas. The chat stays usable while the canvas remains locked until the final result is ready.',
        completed: isGeorgian ? 'შესრულებულია' : 'Completed',
        active: isGeorgian ? 'მიმდინარეობს' : 'In progress',
        pending: isGeorgian ? 'მომლოდინე' : 'Pending',
        failed: isGeorgian ? 'ვერ დასრულდა' : 'Failed',
    }), [isGeorgian]);

    const mappedSteps = useMemo(() => {
        if (generationProgress.steps.length > 0) {
            return generationProgress.steps;
        }

        return BUILDER_GENERATION_STEPS.map((step) => ({
            key: step.key,
            label: step.label,
            status: getBuilderGenerationStepStatus(generationStage, step.key),
            detail: null,
        }));
    }, [generationProgress.steps, generationStage]);

    if (!isRendered) {
        return null;
    }

    return (
        <div
            aria-busy={generationProgress.locked}
            aria-label="Generating your website..."
            className={cn(
                'pointer-events-auto absolute inset-0 z-20 flex min-h-0 min-w-0 flex-col bg-[radial-gradient(circle_at_top,_rgba(255,247,237,0.96),_rgba(251,249,244,0.94)_46%,_rgba(244,239,230,0.98)_100%)] backdrop-blur-[2px] transition-opacity duration-200',
                isVisible ? 'opacity-100' : 'opacity-0',
            )}
        >
            <div className="mx-auto flex min-h-full w-full max-w-3xl flex-col justify-center gap-6 px-6 py-10 text-left">
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-[22px] bg-white/90 text-[#b7791f] shadow-[0_18px_36px_rgba(15,23,42,0.08)]">
                    {generationProgress.isFailed ? (
                        <RefreshCw className="h-7 w-7" />
                    ) : generationProgress.readyForBuilder ? (
                        <CheckCircle2 className="h-7 w-7" />
                    ) : (
                        <Sparkles className="h-7 w-7" />
                    )}
                </div>

                <div className="space-y-2 text-center">
                    <div className="text-[11px] font-semibold uppercase tracking-[0.22em] text-[#8a857d]">
                        {stageCopy.assistantLabel}
                    </div>
                    <h1 className="text-2xl font-semibold tracking-[-0.03em] text-[#1c1917]">
                        {generationProgress.headline ?? t('Preparing your website...')}
                    </h1>
                    <p className="mx-auto max-w-2xl text-sm leading-7 text-[#625f57]">
                        {generationProgress.errorMessage ?? generationProgress.detail ?? t('Preparing generation.')}
                    </p>
                    {!generationProgress.isFailed ? (
                        <p className="mx-auto max-w-2xl text-xs leading-6 text-[#8a857d]">
                            {stageCopy.canvasHint}
                        </p>
                    ) : null}
                    {generationDiagnostics?.designQualityReport ? (
                        <div className="mx-auto inline-flex items-center gap-2 rounded-full border border-[#e7dfd4] bg-white/80 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-[#625f57]">
                            <Sparkles className="h-3.5 w-3.5 text-[#b7791f]" />
                            <span>
                                {isGeorgian ? 'დიზაინის ხარისხი' : 'Design quality'}
                                {' '}
                                {generationDiagnostics.designQualityReport.overallScore}/100
                            </span>
                        </div>
                    ) : null}
                </div>

                {!generationProgress.isFailed ? (
                    <div className="space-y-3">
                        {mappedSteps.map((step) => (
                            <div
                                key={step.key}
                                className={cn(
                                    'flex items-center justify-between gap-4 rounded-[22px] border px-5 py-4 backdrop-blur',
                                    step.status === 'complete' && 'border-emerald-200 bg-white/88 text-emerald-900',
                                    step.status === 'active' && 'border-amber-200 bg-amber-50/92 text-amber-950 shadow-[0_16px_32px_rgba(245,158,11,0.10)]',
                                    step.status === 'pending' && 'border-[#e7dfd4] bg-white/72 text-[#78716c]',
                                )}
                            >
                                <div className="min-w-0">
                                    <div className="text-base font-semibold">{step.label}</div>
                                    <div className="mt-1 text-sm text-current/75">
                                        {step.detail ?? (
                                            step.status === 'complete'
                                                ? stageCopy.completed
                                                : step.status === 'active'
                                                    ? stageCopy.active
                                                    : stageCopy.pending
                                        )}
                                    </div>
                                </div>

                                <div className="flex shrink-0 items-center gap-2">
                                    {step.status === 'active' ? (
                                        <Loader2 className="h-5 w-5 animate-spin" />
                                    ) : step.status === 'complete' ? (
                                        <CheckCircle2 className="h-5 w-5" />
                                    ) : (
                                        <span className="h-3 w-3 rounded-full bg-current/45" />
                                    )}
                                    <span className="text-[11px] font-semibold uppercase tracking-[0.18em]">
                                        {step.status === 'complete'
                                            ? stageCopy.completed
                                            : step.status === 'active'
                                                ? stageCopy.active
                                                : stageCopy.pending}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="mx-auto w-full max-w-xl rounded-[30px] border border-red-200 bg-white p-8 shadow-[0_24px_80px_rgba(28,25,23,0.08)]">
                        <div className="flex items-center gap-3">
                            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-700">
                                <Sparkles className="h-5 w-5" />
                            </div>
                            <div>
                                <h2 className="text-xl font-semibold text-[#1c1917]">
                                    {t('Website generation failed')}
                                </h2>
                                <p className="mt-1 text-sm text-[#78716c]">
                                    {generationStage === 'failed'
                                        ? t('The builder stayed open, but the generated result did not pass validation.')
                                        : t('The builder stayed open, but this generation run could not complete.')}
                                </p>
                            </div>
                        </div>

                        {(onRetry || onCreateAnother) ? (
                            <div className="mt-6 flex flex-wrap gap-3">
                                {onRetry ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={onRetry}
                                        className="rounded-full"
                                    >
                                        {t('Retry generation')}
                                    </Button>
                                ) : null}
                                {onCreateAnother ? (
                                    <Button
                                        type="button"
                                        onClick={onCreateAnother}
                                        className="rounded-full"
                                    >
                                        {t('Create another project')}
                                    </Button>
                                ) : null}
                            </div>
                        ) : null}
                    </div>
                )}
            </div>
        </div>
    );
}
