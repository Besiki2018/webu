import { CheckCircle2, Loader2, RefreshCw, Sparkles } from 'lucide-react';

import { useBuilderStore } from '@/builder/store/builderStore';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/contexts/LanguageContext';
import { cn } from '@/lib/utils';

interface AIGenerationOverlayProps {
    onRetry?: (() => void) | null;
    onCreateAnother?: (() => void) | null;
}

export function AIGenerationOverlay({
    onRetry = null,
    onCreateAnother = null,
}: AIGenerationOverlayProps) {
    const { t, locale } = useTranslation();
    const generationStage = useBuilderStore((state) => state.generationStage);
    const generationProgress = useBuilderStore((state) => state.generationProgress);
    const isGeorgian = locale.toLowerCase().startsWith('ka');

    if (!generationProgress.locked && !generationProgress.isFailed) {
        return null;
    }

    const statusCopy = {
        assistantLabel: isGeorgian ? 'AI გენერაცია' : 'AI generation',
        canvasHint: isGeorgian
            ? 'საიტის გენერირება პირდაპირ კანვასში მიმდინარეობს. ქვემოთ მიმდინარე ეტაპები ჩანს საბოლოო შედეგამდე.'
            : 'Site generation is happening directly in the canvas. The current stages stay here until the site is ready.',
        completed: isGeorgian ? 'შესრულებულია' : 'Completed',
        active: isGeorgian ? 'მიმდინარეობს' : 'In progress',
        pending: isGeorgian ? 'მომლოდინე' : 'Pending',
    };

    return (
        <div className="absolute inset-0 z-20 flex min-h-0 min-w-0 flex-col bg-[radial-gradient(circle_at_top,_rgba(255,247,237,0.95),_rgba(251,249,244,0.92)_46%,_rgba(244,239,230,0.96)_100%)] backdrop-blur-[2px]">
            <div className="mx-auto flex min-h-full w-full max-w-2xl flex-col justify-center gap-6 px-6 py-10 text-left">
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
                        {statusCopy.assistantLabel}
                    </div>
                    <h1 className="text-2xl font-semibold tracking-[-0.03em] text-[#1c1917]">
                        {generationProgress.headline ?? t('Preparing your website...')}
                    </h1>
                    <p className="mx-auto max-w-xl text-sm leading-7 text-[#625f57]">
                        {generationProgress.errorMessage ?? generationProgress.detail ?? t('Preparing generation.')}
                    </p>
                    {generationProgress.recoveryMessage ? (
                        <p className="mx-auto max-w-xl text-xs leading-6 text-[#8a857d]">
                            {generationProgress.recoveryMessage}
                        </p>
                    ) : null}
                    {!generationProgress.isFailed ? (
                        <p className="mx-auto max-w-xl text-xs leading-6 text-[#8a857d]">
                            {statusCopy.canvasHint}
                        </p>
                    ) : null}
                </div>

                {!generationProgress.isFailed ? (
                    <div className="space-y-3">
                        {generationProgress.steps.map((step) => (
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
                                                ? statusCopy.completed
                                                : step.status === 'active'
                                                    ? statusCopy.active
                                                    : statusCopy.pending
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
                                            ? statusCopy.completed
                                            : step.status === 'active'
                                                ? statusCopy.active
                                                : statusCopy.pending}
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
