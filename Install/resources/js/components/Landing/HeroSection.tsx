import { useState, useEffect } from 'react';
import { Link, router } from '@inertiajs/react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle } from 'lucide-react';
import { TrustedBy } from './TrustedBy';
import { useTranslation } from '@/contexts/LanguageContext';
import { ChatInputWithMentions } from '@/components/Chat/ChatInputWithMentions';
import axios from 'axios';

interface HeroSectionProps {
    auth: {
        user: { id: number; name: string; email: string } | null;
    };
    initialSuggestions: string[];
    initialTypingPrompts: string[];
    initialHeadline: string;
    initialSubtitle: string;
    isPusherConfigured?: boolean;
    canCreateProject?: boolean;
    cannotCreateReason?: string | null;
    content?: {
        headlines?: string[];
        subtitles?: string[];
        cta_button?: string;
    };
    trustedBy?: {
        enabled?: boolean;
        content?: Record<string, unknown>;
        items?: Array<Record<string, unknown>>;
    };
}

export function HeroSection({
    auth,
    initialSuggestions: _initialSuggestions,
    initialTypingPrompts,
    initialHeadline,
    initialSubtitle,
    isPusherConfigured: _isPusherConfigured = true,
    canCreateProject = true,
    cannotCreateReason = null,
    trustedBy,
}: HeroSectionProps) {
    const { t } = useTranslation();
    const [prompt, setPrompt] = useState('');
    const [typingPrompts, setTypingPrompts] = useState(initialTypingPrompts);
    const [headline, setHeadline] = useState(initialHeadline);
    const [subtitle, setSubtitle] = useState(initialSubtitle);

    // Update state when props change (e.g., after language switch)
    useEffect(() => {
        setTypingPrompts(initialTypingPrompts);
        if (initialHeadline !== headline) {
            setHeadline(initialHeadline);
        }
        if (initialSubtitle !== subtitle) {
            setSubtitle(initialSubtitle);
        }
    }, [initialTypingPrompts, initialHeadline, initialSubtitle, headline, subtitle]);

    // Compute disabled state for logged-in users (only plan/credits block; broadcast does not)
    const isDisabled = !!(auth.user && !canCreateProject);

    // Fetch AI-powered content after page loads (only suggestions and typing prompts)
    useEffect(() => {
        const fetchAiContent = async () => {
            try {
                const response = await axios.get('/landing/ai-content');
                if (response.data) {
                    setTypingPrompts(response.data.typingPrompts || initialTypingPrompts);
                }
            } catch {
                // Keep static content on error
            }
        };

        // Defer fetch to not block initial render
        const timeoutId = setTimeout(fetchAiContent, 100);
        return () => clearTimeout(timeoutId);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const handleSubmit = (e?: React.FormEvent) => {
        e?.preventDefault();
        if (!prompt.trim()) return;

        if (!auth.user) {
            // Save prompt for post-registration retrieval
            sessionStorage.setItem('landing_prompt', prompt.trim());
            router.visit('/register');
        } else {
            router.post('/projects', { prompt: prompt.trim() });
        }
    };

    return (
        <section className="webu-hero-section relative min-h-dvh flex flex-col items-center px-4 bg-background">
            <div className="webu-hero-section__inner relative z-10 w-full text-center">
                {/* Static headline */}
                <h1 className="webu-hero-section__title">
                    {headline}
                </h1>

                {/* Subtitle */}
                <p className="webu-hero-section__subtitle">
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
                <div className="webu-hero-section__prompt-shell">
                    <ChatInputWithMentions
                        value={prompt}
                        onChange={setPrompt}
                        onSubmit={handleSubmit}
                        disabled={isDisabled}
                        selectedElement={null}
                        onClearElement={() => {}}
                        placeholder={typingPrompts[0] ? t(typingPrompts[0]) : t('I want to build...')}
                        variant="workspace"
                    />
                </div>
            </div>

            {/* Trusted by */}
            {trustedBy?.enabled !== false && (
                <div className="mt-12 w-full max-w-4xl px-4">
                    <TrustedBy
                        content={trustedBy?.content}
                        items={trustedBy?.items as Array<{ name: string; initial: string; color: string; image_url?: string | null }>}
                    />
                </div>
            )}
        </section>
    );
}
