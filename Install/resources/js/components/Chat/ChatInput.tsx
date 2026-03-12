import { useState, KeyboardEvent, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Send, Square, Mic } from 'lucide-react';
import { useTranslation } from '@/contexts/LanguageContext';
import { useSpeechToText } from '@/hooks/useSpeechToText';
import { ChatAssetSearchPopover, type ChatAssetItem } from './ChatAssetSearchPopover';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';

interface ChatInputProps {
    onSend: (message: string) => void;
    disabled?: boolean;
    onCancel?: () => void;
    projectId?: string | null;
}

export function ChatInput({ onSend, disabled = false, onCancel, projectId }: ChatInputProps) {
    const { t } = useTranslation();
    const [message, setMessage] = useState('');
    const [voiceError, setVoiceError] = useState<string | null>(null);

    const { isSupported, isListening, startListening, stopListening } = useSpeechToText({
        lang: 'ka-GE',
        onTranscript: (transcript) => {
            setVoiceError(null);
            setMessage(transcript);
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

    const appendAssetToMessage = useCallback((asset: ChatAssetItem) => {
        const line = `Asset: ${asset.reference}`;
        setMessage((prev) => (prev.trim() === '' ? line : `${prev.trimEnd()}\n${line}`));
    }, []);

    const handleSend = () => {
        const trimmed = message.trim();
        if (trimmed && !disabled) {
            if (isListening) {
                stopListening();
            }
            onSend(trimmed);
            setMessage('');
        }
    };

    const handleVoiceToggle = () => {
        if (disabled) return;

        setVoiceError(null);

        if (isListening) {
            stopListening();
            return;
        }

        startListening(message);
    };

    const handleKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
        // Enter to send, Shift+Enter for new line
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
    };

    return (
        <div className="border-t bg-background p-4">
            <div className="flex items-end gap-2 max-w-4xl mx-auto">
                <ChatAssetSearchPopover
                    projectId={projectId}
                    disabled={disabled}
                    onSelect={appendAssetToMessage}
                    className="h-11 w-11 shrink-0 rounded-xl"
                />

                <Textarea
                    value={message}
                    onChange={(e) => setMessage(e.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder="Type your message..."
                    disabled={disabled}
                    rows={1}
                    className="min-h-[44px] max-h-[200px] resize-none"
                />

                <Button
                    type="button"
                    onClick={handleVoiceToggle}
                    variant="ghost"
                    size="icon"
                    disabled={disabled || !isSupported}
                    className={cn(
                        'shrink-0 h-11 w-11 rounded-xl',
                        isListening && 'text-destructive hover:text-destructive bg-destructive/10 hover:bg-destructive/20'
                    )}
                    aria-label={isListening ? t('Stop voice input') : t('Start voice input')}
                    title={isListening ? t('Stop voice input') : t('Start voice input')}
                >
                    <Mic className={cn('h-4 w-4', isListening && 'animate-pulse')} />
                </Button>

                {onCancel ? (
                    <Button
                        onClick={onCancel}
                        variant="destructive"
                        size="icon"
                        className="shrink-0 h-11 w-11 rounded-xl"
                    >
                        <Square className="h-4 w-4" />
                        <span className="sr-only">Cancel</span>
                    </Button>
                ) : (
                    <Button
                        onClick={handleSend}
                        disabled={disabled || !message.trim()}
                        size="icon"
                        className="shrink-0 h-11 w-11 rounded-xl"
                    >
                        <Send className="h-4 w-4" />
                        <span className="sr-only">Send message</span>
                    </Button>
                )}
            </div>

            {voiceError && (
                <p className="mx-auto max-w-4xl pt-2 text-xs text-destructive">{voiceError}</p>
            )}

            <p className="sr-only">{t('Press Enter to send')}</p>
        </div>
    );
}
