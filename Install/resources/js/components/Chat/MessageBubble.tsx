import { ChatMessage } from '@/types/chat';
import type { User } from '@/types';
import { cn } from '@/lib/utils';
import { MarkdownRenderer } from '@/components/Markdown/MarkdownRenderer';
import { ArrowRight, Copy, Edit2 } from 'lucide-react';
import { useTranslation } from '@/contexts/LanguageContext';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { AgentProgressInline } from '@/components/Chat/AgentProgressInline';
import type { BuildProgress } from '@/hooks/useBuilderChat';

interface MessageBubbleProps {
    message: ChatMessage;
    currentUser?: User | null;
    shouldType?: boolean;
    onTypingComplete?: () => void;
}

/** Codex-style: 1–2 characters per tick for a real typing feel. */
function getTypingChunkSize(contentLength: number, currentLength: number): number {
    const remaining = Math.max(contentLength - currentLength, 0);
    if (remaining > 800) return 2;
    return 1;
}

/** Variable delay per tick (25–55ms) so typing feels natural like Codex. */
function getTypingDelay(): number {
    return 25 + Math.floor(Math.random() * 30);
}

/**
 * Parse and render batch edit messages with nice styling.
 */
function BatchEditMessage({ content, t }: { content: string; t: (key: string, replacements?: Record<string, string | number>) => string }) {
    // Parse the batch edit message
    // Format: [BATCH_EDIT] Update multiple elements:\n1. <tagSelector>: "old" → "new"
    const lines = content.split('\n');
    const edits = lines.slice(1).map(line => {
        // Parse: 1. <h1.text-5xl>: "old" → "new"
        // Or: 1. <img> src: "old" → "new"
        const match = line.match(/^\d+\.\s*<([^>]+)>(?:\s*([^:]+))?:\s*"([^"]*)".*?"([^"]*)"$/);
        if (match) {
            return {
                selector: match[1],
                field: match[2]?.trim() || 'text',
                oldValue: match[3],
                newValue: match[4],
            };
        }
        return null;
    }).filter(Boolean);

    return (
        <div className="space-y-2">
            <div className="flex items-center gap-2 text-xs font-medium text-foreground/80">
                <Edit2 className="h-3.5 w-3.5" />
                <span>{t('Batch Edit')}</span>
                <span className="bg-foreground/20 px-1.5 py-0.5 rounded text-[10px]">
                    {edits.length} {edits.length === 1 ? t('change') : t('changes')}
                </span>
            </div>
            <div className="space-y-1.5">
                {edits.map((edit, i) => (
                    <div key={i} className="bg-foreground/10 rounded-lg px-3 py-2 text-xs">
                        <div className="flex items-center gap-1.5 text-foreground/70 mb-1">
                            <code className="bg-foreground/10 px-1.5 py-0.5 rounded text-[10px]">
                                {edit!.selector}
                            </code>
                            {edit!.field !== 'text' && (
                                <span className="text-foreground/50">{edit!.field}</span>
                            )}
                        </div>
                        <div className="flex items-center gap-2 flex-wrap">
                            <span className="text-foreground/60 line-through truncate max-w-[120px]" title={edit!.oldValue}>
                                {edit!.oldValue.length > 20 ? edit!.oldValue.slice(0, 20) + '...' : edit!.oldValue}
                            </span>
                            <ArrowRight className="h-3 w-3 text-foreground/50 shrink-0" />
                            <span className="text-foreground font-medium truncate max-w-[120px]" title={edit!.newValue}>
                                {edit!.newValue.length > 20 ? edit!.newValue.slice(0, 20) + '...' : edit!.newValue}
                            </span>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

/** Format date/time in site locale; use "Today"/"Yesterday" when applicable. */
function formatMessageTimestamp(
    timestamp: Date,
    locale: string,
    t: (key: string) => string
): { dateLabel: string; time: string } {
    const localeTag = locale.startsWith('ka') ? 'ka-GE' : locale.startsWith('en') ? 'en-US' : locale;
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    const tsDay = new Date(timestamp.getFullYear(), timestamp.getMonth(), timestamp.getDate());

    let dateLabel: string;
    if (tsDay.getTime() === today.getTime()) {
        dateLabel = t('Today');
    } else if (tsDay.getTime() === yesterday.getTime()) {
        dateLabel = t('Yesterday');
    } else {
        dateLabel = new Intl.DateTimeFormat(localeTag, {
            month: 'short',
            day: 'numeric',
        }).format(timestamp);
    }

    const time = new Intl.DateTimeFormat(localeTag, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(timestamp);

    return { dateLabel, time };
}

function ActionButton({
    label,
    icon: Icon,
    onClick,
    className,
}: {
    label: string;
    icon: typeof Copy;
    onClick?: () => void;
    className?: string;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            title={label}
            onClick={onClick}
            className={cn(
                'workspace-message-action-button',
                className
            )}
        >
            <Icon className="h-3.5 w-3.5" />
        </button>
    );
}

function formatLiveStatus(progress: Pick<BuildProgress, 'thinkingContent' | 'statusMessage' | 'actions' | 'toolCalls' | 'toolResults'>): string {
    if (progress.thinkingContent && progress.thinkingContent.trim() !== '') {
        return progress.thinkingContent.trim();
    }

    const latestAction = progress.actions[progress.actions.length - 1];
    if (latestAction) {
        return [latestAction.action, latestAction.target].filter(Boolean).join(' ').trim()
            || latestAction.details
            || 'Applying changes...';
    }

    const latestToolCall = progress.toolCalls[progress.toolCalls.length - 1];
    if (latestToolCall) {
        return `Running ${latestToolCall.tool}...`;
    }

    const latestToolResult = progress.toolResults[progress.toolResults.length - 1];
    if (latestToolResult) {
        return latestToolResult.success
            ? `${latestToolResult.tool} completed`
            : `${latestToolResult.tool} failed`;
    }

    if (progress.statusMessage && progress.statusMessage.trim() !== '') {
        return progress.statusMessage.trim();
    }

    return 'Working...';
}

function summarizeLiveActions(progress: Pick<BuildProgress, 'actions' | 'toolCalls' | 'toolResults'>): string[] {
    const actionRows = progress.actions
        .slice(-3)
        .map((item) => [item.action, item.target].filter(Boolean).join(' ').trim() || item.details)
        .filter((value) => value.trim() !== '');
    const toolRows = progress.toolCalls
        .slice(-2)
        .map((item) => `Tool: ${item.tool}`);
    const resultRows = progress.toolResults
        .slice(-2)
        .map((item) => `${item.tool}: ${item.success ? 'ok' : 'failed'}`);

    return [...actionRows, ...toolRows, ...resultRows].slice(-4);
}

interface PendingAssistantBubbleProps {
    progress: BuildProgress;
    label?: string | null;
    timestamp?: Date;
    showPipelineSteps?: boolean;
}

export function PendingAssistantBubble({
    progress,
    label = null,
    timestamp = new Date(),
    showPipelineSteps = true,
}: PendingAssistantBubbleProps) {
    const { t, locale } = useTranslation();
    const [pulseFrame, setPulseFrame] = useState(0);
    const { dateLabel, time } = formatMessageTimestamp(timestamp, locale, t);
    const liveStatus = label?.trim() || formatLiveStatus(progress);
    const liveRows = summarizeLiveActions(progress);
    const isThinking = Boolean(progress.thinkingContent?.trim()) && progress.actions.length === 0 && progress.toolCalls.length === 0;

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const id = window.setInterval(() => {
            setPulseFrame((current) => (current + 1) % 3);
        }, 360);

        return () => window.clearInterval(id);
    }, []);

    return (
        <div className="workspace-message-row workspace-message-row--assistant animate-fade-in">
            <div className="workspace-message-date workspace-message-date--start">
                <span className="workspace-message-date-strong">{dateLabel}</span>
                <span>{time}</span>
            </div>

            <div className="flex justify-start text-left">
                <div className="workspace-assistant-bubble workspace-assistant-bubble--live min-w-0 overflow-hidden break-words">
                    <div className="workspace-live-status-header">
                        <span className="workspace-live-status-dot" aria-hidden="true" />
                        <span className="workspace-live-status-kicker">{isThinking ? t('Thinking...') : t('Webu is working')}</span>
                    </div>
                    <div className="workspace-live-status-line">
                        <span>{liveStatus}</span>
                        <span className="workspace-live-status-pulse" aria-hidden="true">
                            {'.'.repeat(pulseFrame + 1)}
                        </span>
                        <span aria-hidden="true" className="workspace-typing-cursor" />
                    </div>
                    <AgentProgressInline
                        progress={progress}
                        currentStepLabel={label ?? undefined}
                        showPipelineSteps={showPipelineSteps}
                        className="mt-3"
                    />
                    {liveRows.length > 0 ? (
                        <div className="workspace-live-status-log">
                            {liveRows.map((row, index) => (
                                <div key={`${row}-${index}`} className="workspace-live-status-log-row">
                                    <span className="workspace-live-status-log-bullet" aria-hidden="true" />
                                    <span>{row}</span>
                                </div>
                            ))}
                        </div>
                    ) : null}
                </div>
            </div>
        </div>
    );
}

export function MessageBubble({ message, currentUser, shouldType = false, onTypingComplete }: MessageBubbleProps) {
    const { t, locale } = useTranslation();
    const isUser = message.type === 'user';
    const isActivity = message.type === 'activity';
    const showDiagnosticLog = typeof window !== 'undefined' && window.location.search.includes('debug=agent');
    const { dateLabel, time } = formatMessageTimestamp(message.timestamp, locale, t);
    const userName = currentUser?.name || 'User';
    const [displayedContent, setDisplayedContent] = useState(() => (shouldType ? '' : message.content));
    const typingTimeoutRef = useRef<number | null>(null);
    const onTypingCompleteRef = useRef(onTypingComplete);

    useEffect(() => {
        onTypingCompleteRef.current = onTypingComplete;
    }, [onTypingComplete]);

    const handleCopy = useCallback(async () => {
        if (typeof navigator === 'undefined' || !navigator.clipboard) return;
        try {
            await navigator.clipboard.writeText(message.content);
            toast.success(t('Copied'));
        } catch {
            toast.error(t('Could not copy'));
        }
    }, [message.content, t]);

    useEffect(() => {
        if (typingTimeoutRef.current !== null && typeof window !== 'undefined') {
            window.clearTimeout(typingTimeoutRef.current);
            typingTimeoutRef.current = null;
        }

        if (!shouldType || isUser || isActivity) {
            setDisplayedContent(message.content);
            return;
        }

        if (typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
            setDisplayedContent(message.content);
            onTypingCompleteRef.current?.();
            return;
        }

        setDisplayedContent('');
        let currentLength = 0;

        const revealNextChunk = () => {
            currentLength = Math.min(
                message.content.length,
                currentLength + getTypingChunkSize(message.content.length, currentLength),
            );
            setDisplayedContent(message.content.slice(0, currentLength));

            if (currentLength >= message.content.length) {
                typingTimeoutRef.current = null;
                onTypingCompleteRef.current?.();
                return;
            }

            typingTimeoutRef.current = window.setTimeout(revealNextChunk, getTypingDelay());
        };

        /* Codex-style: short pause before first character, then type. */
        typingTimeoutRef.current = window.setTimeout(revealNextChunk, 120);

        return () => {
            if (typingTimeoutRef.current !== null) {
                window.clearTimeout(typingTimeoutRef.current);
                typingTimeoutRef.current = null;
            }
        };
    }, [isActivity, isUser, message.content, shouldType]);

    // Keep activity updates in header/status only for a cleaner ChatGPT-like thread.
    if (isActivity) {
        return null;
    }

    if (isUser) {
        const isBatchEdit = message.content.startsWith('[BATCH_EDIT]');

        return (
            <div className="group workspace-message-row workspace-message-row--user animate-fade-in">
                <div className="workspace-message-date workspace-message-date--end">
                    <span className="workspace-message-date-strong">{dateLabel}</span>
                    <span>{time}</span>
                </div>
                <div className="flex justify-end">
                    <div
                        className="workspace-user-bubble overflow-hidden break-words"
                    >
                        <span className="sr-only">{userName}</span>
                        <div className="whitespace-normal">
                            {isBatchEdit ? (
                                <BatchEditMessage content={displayedContent} t={t} />
                            ) : (
                                <MarkdownRenderer
                                    content={displayedContent}
                                    className="!text-[16px] !leading-7 !text-[#3c3934] prose-p:!text-[#3c3934]"
                                />
                            )}
                        </div>
                    </div>
                </div>

                <div className="mt-1 flex justify-end">
                    <ActionButton label={t('Copy message')} icon={Copy} onClick={handleCopy} className="md:opacity-0 md:transition-opacity md:group-hover:opacity-100" />
                </div>
            </div>
        );
    }

    const isErrorMessage = message.content.startsWith('Error:');

    return (
        <div className="group workspace-message-row workspace-message-row--assistant animate-fade-in">
            <div className="workspace-message-date workspace-message-date--start">
                <span className="workspace-message-date-strong">{dateLabel}</span>
                <span>{time}</span>
            </div>

            <div className="flex justify-start text-left">
                <div
                    className={cn(
                        'workspace-assistant-bubble min-w-0 overflow-hidden break-words',
                        isErrorMessage && 'workspace-assistant-bubble--error'
                    )}
                >
                    <div
                        className={cn(
                            'text-[15px] sm:text-[16px] leading-[1.7] text-[#393733]',
                            isErrorMessage && 'text-[#9f2f2f]'
                        )}
                    >
                        <MarkdownRenderer
                            content={displayedContent}
                            className={cn(
                                '!max-w-none !text-[15px] sm:!text-[16px] !leading-[1.7]',
                                'prose-headings:!text-[#242320] prose-p:!text-[#393733] prose-strong:!text-[#1f1e1b]',
                                isErrorMessage && 'prose-p:!text-[#9f2f2f] prose-strong:!text-[#9f2f2f]'
                            )}
                        />
                        {shouldType && displayedContent !== message.content && (
                            <span aria-hidden="true" className="workspace-typing-cursor" />
                        )}
                    </div>
                    {message.actionLog && message.actionLog.length > 0 && (
                        <ul className="mt-2 space-y-0.5 text-sm text-muted-foreground">
                            {message.actionLog.map((line, i) => (
                                <li key={i}>{line}</li>
                            ))}
                        </ul>
                    )}
                    {message.appliedChanges && message.appliedChanges.length > 0 && (
                        <ul className="mt-2 space-y-0.5 text-xs text-muted-foreground">
                            {message.appliedChanges.map((entry, i) => (
                                <li key={i}>
                                    {entry.op ?? 'change'} {entry.component ? `· ${entry.component}` : ''}
                                    {entry.section_id ? ` (${entry.section_id})` : ''}
                                    {entry.summary?.length ? ` · ${entry.summary.join(' · ')}` : ''}
                                </li>
                            ))}
                        </ul>
                    )}
                    {message.scope && (
                        <div className="mt-1.5 text-xs text-muted-foreground">
                            <span className="font-medium">{t('Scope')}:</span> {message.scope}
                        </div>
                    )}
                    {showDiagnosticLog && message.diagnosticLog && message.diagnosticLog.length > 0 && (
                        <div className="mt-2 rounded-xl border border-black/5 bg-black/[0.03] px-3 py-2">
                            <div className="mb-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                                {t('Debug log')}
                            </div>
                            <ul className="space-y-0.5 text-xs text-muted-foreground">
                                {message.diagnosticLog.map((line, i) => (
                                    <li key={i}>{line}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            </div>

            <div className="mt-1 flex items-center gap-0.5">
                <ActionButton label={t('Copy message')} icon={Copy} onClick={handleCopy} className="md:opacity-0 md:transition-opacity md:group-hover:opacity-100" />
            </div>
        </div>
    );
}
