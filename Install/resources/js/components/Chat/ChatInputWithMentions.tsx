import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import { LexicalComposer } from '@lexical/react/LexicalComposer';
import { PlainTextPlugin } from '@lexical/react/LexicalPlainTextPlugin';
import { ContentEditable } from '@lexical/react/LexicalContentEditable';
import { HistoryPlugin } from '@lexical/react/LexicalHistoryPlugin';
import { OnChangePlugin } from '@lexical/react/LexicalOnChangePlugin';
import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import { LexicalErrorBoundary } from '@lexical/react/LexicalErrorBoundary';
import {
    $getRoot,
    $createParagraphNode,
    $createTextNode,
    COMMAND_PRIORITY_HIGH,
    KEY_ENTER_COMMAND,
    EditorState,
} from 'lexical';
import { Button } from '@/components/ui/button';
import { ArrowUp, Square, Loader2, X, MousePointerClick, Mic } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { ElementMention } from '@/types/inspector';
import { useTranslation } from '@/contexts/LanguageContext';
import { ChatAssetSearchPopover, type ChatAssetItem } from './ChatAssetSearchPopover';
import { useSpeechToText } from '@/hooks/useSpeechToText';
import { toast } from 'sonner';

interface ChatInputWithMentionsProps {
    /** Project ID for asset search */
    projectId?: string | null;
    /** Current text value (for controlled component) */
    value: string;
    /** Called when text changes */
    onChange: (value: string) => void;
    /** Called when form is submitted */
    onSubmit: (e?: React.FormEvent) => void;
    /** Whether the input is disabled */
    disabled?: boolean;
    /** Currently selected element to mention */
    selectedElement: ElementMention | null;
    /** Called to clear the selected element */
    onClearElement: () => void;
    /** Placeholder text */
    placeholder?: string;
    /** Whether a build is in progress (shows stop button) */
    isLoading?: boolean;
    /** Called when stop button is clicked */
    onCancel?: () => void;
    /** Optional custom action rendered in the footer start area */
    footerStartSlot?: ReactNode;
    /** Optional custom action rendered before the voice/send controls */
    footerEndSlot?: ReactNode;
    /** Visual treatment for different surfaces */
    variant?: 'default' | 'workspace';
}

// Theme for Lexical editor
const theme = {
    paragraph: 'mb-0',
    text: {
        base: '',
    },
};

// Handle errors
function onError(error: Error) {
    console.error('Lexical error:', error);
}

/**
 * Plugin to sync editor content with parent component.
 */
function SyncPlugin({
    value,
    onChange,
}: {
    value: string;
    onChange: (value: string) => void;
}) {
    const [editor] = useLexicalComposerContext();
    const isExternalUpdate = useRef(false);

    // Sync external value changes to editor
    useEffect(() => {
        if (isExternalUpdate.current) {
            isExternalUpdate.current = false;
            return;
        }

        editor.update(() => {
            const root = $getRoot();
            const currentText = root.getTextContent();

            if (currentText !== value) {
                root.clear();
                const paragraph = $createParagraphNode();
                if (value) {
                    paragraph.append($createTextNode(value));
                }
                root.append(paragraph);
            }
        });
    }, [editor, value]);

    // Sync editor changes to parent
    const handleChange = useCallback(
        (editorState: EditorState) => {
            editorState.read(() => {
                const text = $getRoot().getTextContent();
                if (text !== value) {
                    isExternalUpdate.current = true;
                    onChange(text);
                }
            });
        },
        [onChange, value]
    );

    return <OnChangePlugin onChange={handleChange} />;
}

/**
 * Plugin to handle Enter key submission.
 */
function EnterKeyPlugin({ onSubmit }: { onSubmit: () => void }) {
    const [editor] = useLexicalComposerContext();

    useEffect(() => {
        return editor.registerCommand(
            KEY_ENTER_COMMAND,
            (event: KeyboardEvent | null) => {
                if (event && !event.shiftKey) {
                    event.preventDefault();
                    onSubmit();
                    return true;
                }
                return false;
            },
            COMMAND_PRIORITY_HIGH
        );
    }, [editor, onSubmit]);

    return null;
}

/**
 * Chat input component with Lexical editor and element mention support.
 */
export function ChatInputWithMentions({
    projectId,
    value,
    onChange,
    onSubmit,
    disabled = false,
    selectedElement,
    onClearElement,
    placeholder,
    isLoading = false,
    onCancel,
    footerStartSlot,
    footerEndSlot,
    variant = 'default',
}: ChatInputWithMentionsProps) {
    const { t } = useTranslation();
    const [voiceError, setVoiceError] = useState<string | null>(null);
    const isWorkspaceVariant = variant === 'workspace';
    const defaultPlaceholder = t('Describe what you want to build...');
    const actualPlaceholder = placeholder || defaultPlaceholder;

    const { isSupported, isListening, startListening, stopListening } = useSpeechToText({
        lang: 'ka-GE',
        onTranscript: (transcript) => {
            setVoiceError(null);
            onChange(transcript);
        },
        onError: (error) => {
            setVoiceError(t(error.message));

            if (error.code === 'permission-denied') {
                toast.error(t('Microphone permission is blocked'), {
                    description: t('Allow microphone access in your browser settings, then try again.'),
                });
            }
        },
    });

    const handleAssetSelect = useCallback(
        (asset: ChatAssetItem) => {
            const line = `Asset: ${asset.reference}`;
            const nextValue = value.trim() === '' ? line : `${value.trimEnd()}\n${line}`;
            onChange(nextValue);
        },
        [onChange, value]
    );

    const handleCategorySelect = useCallback(
        (categoryPrompt: string) => {
            onChange(categoryPrompt);
        },
        [onChange]
    );

    const handleVoiceToggle = useCallback(() => {
        if (disabled) return;

        setVoiceError(null);

        if (isListening) {
            stopListening();
            return;
        }

        startListening(value);
    }, [disabled, isListening, startListening, stopListening, value]);

    const handleSubmit = useCallback(() => {
        if (!value.trim() && !selectedElement) {
            return;
        }

        if (isListening) {
            stopListening();
        }

        onSubmit();
    }, [value, selectedElement, onSubmit, isListening, stopListening]);

    const initialConfig = {
        namespace: 'ChatInput',
        theme,
        onError,
    };

    return (
        <div
            className={cn(
                'relative overflow-hidden rounded-[2rem] transition-all',
                isWorkspaceVariant
                    ? 'workspace-composer'
                    : 'border border-border/70 bg-[var(--chat-input-bg)] shadow-[0_1px_6px_rgba(15,23,42,0.08)] focus-within:border-foreground/25 focus-within:shadow-[0_10px_24px_rgba(15,23,42,0.12)]'
            )}
        >
                {/* Element mention chip */}
                {selectedElement && (
                    <div className={cn(isWorkspaceVariant ? 'workspace-composer-chip-row' : 'px-4 pt-3 pb-1')}>
                        <ElementChip element={selectedElement} onRemove={onClearElement} />
                    </div>
                )}

                <div className={cn(isWorkspaceVariant ? 'workspace-composer-main' : 'px-4 pt-3 sm:px-5')}>
                    <LexicalComposer initialConfig={initialConfig}>
                        <div className="relative flex-1 min-w-0">
                            <PlainTextPlugin
                                contentEditable={
                                    <ContentEditable
                                        className={cn(
                                            'w-full',
                                            isWorkspaceVariant ? 'workspace-composer-editor' : 'text-base leading-6',
                                            'focus:outline-none focus:ring-0 max-h-[220px] overflow-y-auto',
                                            !isWorkspaceVariant && 'min-h-[88px]',
                                            disabled && 'opacity-50 cursor-not-allowed pointer-events-none'
                                        )}
                                    />
                                }
                                placeholder={
                                    <div
                                        className={cn(
                                            'pointer-events-none absolute',
                                            isWorkspaceVariant ? 'workspace-composer-placeholder' : 'text-base leading-6 text-muted-foreground/85'
                                        )}
                                    >
                                        {actualPlaceholder}
                                    </div>
                                }
                                ErrorBoundary={LexicalErrorBoundary}
                            />
                            <SyncPlugin value={value} onChange={onChange} />
                            <HistoryPlugin />
                            <EnterKeyPlugin onSubmit={handleSubmit} />
                        </div>
                    </LexicalComposer>
                </div>

                <div className={cn(isWorkspaceVariant ? 'workspace-composer-footer' : 'flex items-center justify-between gap-3 px-4 pb-4 pt-2 sm:px-5')}>
                    <div className="flex min-w-0 items-center gap-2">
                        <ChatAssetSearchPopover
                            projectId={projectId}
                            disabled={disabled}
                            onSelect={handleAssetSelect}
                            onSelectCategory={handleCategorySelect}
                            className={cn(
                                'h-9 w-9 rounded-full',
                                isWorkspaceVariant
                                    ? 'workspace-composer-icon'
                                    : 'border border-border/70 bg-background/90 hover:bg-accent'
                            )}
                        />
                        {footerStartSlot}
                    </div>

                    <div className="shrink-0 flex items-center gap-2">
                        {footerEndSlot}
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            disabled={disabled || !isSupported}
                            onClick={handleVoiceToggle}
                            className={cn(
                                'h-9 w-9 rounded-full',
                                isWorkspaceVariant
                                    ? 'workspace-composer-icon workspace-composer-voice'
                                    : 'border border-border/70 text-muted-foreground hover:bg-accent hover:text-foreground',
                                isListening && 'text-destructive hover:text-destructive bg-destructive/10 hover:bg-destructive/20'
                            )}
                            aria-label={isListening ? t('Stop voice input') : t('Start voice input')}
                            title={isListening ? t('Stop voice input') : t('Start voice input')}
                        >
                            <Mic className={cn('h-4 w-4', isListening && 'animate-pulse')} />
                        </Button>

                        {isLoading && onCancel ? (
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                onClick={onCancel}
                                className={cn(
                                    'h-9 w-9 rounded-full text-white hover:text-white',
                                    isWorkspaceVariant ? 'workspace-composer-submit workspace-composer-stop' : 'bg-black hover:bg-black/90'
                                )}
                                aria-label={t('Stop generating')}
                                title={t('Stop generating')}
                            >
                                <Square className="h-4 w-4" />
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                size="icon"
                                disabled={(!value.trim() && !selectedElement) || disabled}
                                onClick={handleSubmit}
                                className={cn(
                                    'h-9 w-9 rounded-full text-white',
                                    isWorkspaceVariant ? 'workspace-composer-submit' : 'bg-black hover:bg-black/90'
                                )}
                                aria-label={t('Send message')}
                                title={t('Send message')}
                            >
                                {isLoading ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <ArrowUp className="h-4 w-4" />
                                )}
                            </Button>
                        )}
                    </div>
                </div>

                {voiceError && (
                    <p className="px-4 pb-3 text-xs text-destructive">{voiceError}</p>
                )}
        </div>
    );
}

/**
 * Element chip component for displaying selected element.
 */
interface ElementChipProps {
    element: ElementMention;
    onRemove: () => void;
}

function ElementChip({ element, onRemove }: ElementChipProps) {
    // Extract class from selector for display
    const getDisplaySelector = () => {
        if (element.selector.startsWith('#')) {
            return element.selector;
        }
        const match = element.selector.match(/\.([^:\s>]+)/);
        return match ? `.${match[1]}` : '';
    };

    return (
        <div className="inline-flex items-center gap-1.5 rounded-full border border-border/70 bg-muted px-2.5 py-1 text-xs font-medium text-foreground">
            <MousePointerClick className="h-3 w-3" />
            <span className="font-mono">
                {element.tagName}{getDisplaySelector()}
            </span>
            {element.textPreview && (
                <span className="text-muted-foreground truncate max-w-[150px]" title={element.textPreview}>
                    &quot;{element.textPreview.length > 20 ? element.textPreview.substring(0, 20) + '...' : element.textPreview}&quot;
                </span>
            )}
            <button
                type="button"
                onClick={onRemove}
                className="workspace-element-chip-remove"
            >
                <X className="h-3 w-3" />
            </button>
        </div>
    );
}

export default ChatInputWithMentions;
