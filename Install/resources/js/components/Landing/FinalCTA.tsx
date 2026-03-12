import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle } from 'lucide-react';
import { useTranslation } from '@/contexts/LanguageContext';
import { ChatInputWithMentions } from '@/components/Chat/ChatInputWithMentions';

interface FinalCTAProps {
    auth: {
        user: { id: number; name: string; email: string } | null;
    };
    isPusherConfigured?: boolean;
    canCreateProject?: boolean;
    cannotCreateReason?: string | null;
    content?: Record<string, unknown>;
}

export function FinalCTA({
    auth,
    isPusherConfigured: _isPusherConfigured = true,
    canCreateProject = true,
    cannotCreateReason = null,
    content,
}: FinalCTAProps) {
    const { t } = useTranslation();

    // Extract content with defaults - DB content takes priority
    const title = (content?.title as string) || t('Ready to build something amazing?');
    const subtitle = (content?.subtitle as string) || t('Start building for free. No credit card required.');
    const [prompt, setPrompt] = useState('');

    // Compute disabled state for logged-in users (broadcast not required to create)
    const isDisabled = !!(auth.user && !canCreateProject);

    const handleSubmit = (e?: React.FormEvent) => {
        e?.preventDefault();
        if (!prompt.trim()) return;

        if (!auth.user) {
            sessionStorage.setItem('landing_prompt', prompt.trim());
            router.visit('/register');
        } else {
            router.post('/projects', { prompt: prompt.trim() });
        }
    };

    return (
        <section className="webu-final-cta py-16 lg:py-20 border-t border-border/60">
            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                {/* Headline */}
                <h2 className="webu-final-cta__title">
                    {title}
                </h2>

                {/* Subtitle */}
                <p className="text-base md:text-lg text-muted-foreground mb-10 max-w-2xl mx-auto leading-relaxed">
                    {subtitle}
                </p>

                {auth.user && !canCreateProject && (
                    <Alert variant="destructive" className="max-w-2xl mx-auto mb-4">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            {cannotCreateReason}{' '}
                            <Link href="/billing/plans" className="underline font-semibold">
                                {t('View Plans')}
                            </Link>
                        </AlertDescription>
                    </Alert>
                )}

                {/* Prompt Input */}
                <div className="webu-final-cta__prompt-shell">
                    <ChatInputWithMentions
                        value={prompt}
                        onChange={setPrompt}
                        onSubmit={handleSubmit}
                        disabled={isDisabled}
                        selectedElement={null}
                        onClearElement={() => {}}
                        placeholder={t('Describe what you want to build...')}
                        variant="workspace"
                    />
                </div>

                {/* Trust Note */}
                <p className="mt-6 text-sm text-muted-foreground">
                    {t('Start building today')}
                </p>
            </div>
        </section>
    );
}
