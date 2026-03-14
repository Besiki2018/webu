import { useState, useEffect, useMemo } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { SidebarProvider, SidebarInset, SidebarTrigger } from '@/components/ui/sidebar';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Toaster } from '@/components/ui/sonner';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { AppSidebar } from '@/components/Sidebar/AppSidebar';
import { PromptInput } from '@/components/Dashboard/PromptInput';
import { ChatPageSkeleton } from '@/components/Skeleton';
import { ThemeToggle } from '@/components/ThemeToggle';
import { LanguageSelector } from '@/components/LanguageSelector';
import { NotificationBell } from '@/components/Notifications/NotificationBell';
import { GlobalCredits } from '@/components/Header/GlobalCredits';
import { useNotifications } from '@/hooks/useNotifications';
import { useUserChannel } from '@/hooks/useUserChannel';
import { usePageTransition } from '@/hooks/usePageTransition';
import { useTranslation } from '@/contexts/LanguageContext';
import { filterCreatePromptExamples } from '@/lib/createPromptCatalog';
import { CreateProps, PageProps } from '@/types';
import type { UserCredits, UserNotification } from '@/types/notifications';
import type { BroadcastConfig } from '@/hooks/useBuilderPusher';
import { LogOut, AlertCircle } from 'lucide-react';
import { route } from 'ziggy-js';
import { DemoResetNotice } from '@/components/DemoResetNotice';
import axios from 'axios';
import { resolveWebuV2FeatureFlags } from '@/lib/webuV2FeatureFlags';

const CREATE_PENDING_REDIRECT_STORAGE_KEY = 'webu-create-pending-redirect';

export default function Create({
    user,
    isPusherConfigured: _isPusherConfigured,
    canCreateProject,
    cannotCreateReason,
    suggestions: initialSuggestions,
    typingPrompts: initialTypingPrompts,
    templates,
    readyTemplates: _readyTemplates = [],
    pendingRedirectUrl = null,
}: CreateProps) {
    const { t } = useTranslation();
    const [suggestions, setSuggestions] = useState(() => filterCreatePromptExamples(initialSuggestions));
    const [typingPrompts, setTypingPrompts] = useState(() => filterCreatePromptExamples(initialTypingPrompts));
    const [isLoadingAi, setIsLoadingAi] = useState(true);
    const pageProps = usePage<PageProps & {
        errors?: { prompt?: string };
        broadcastConfig: BroadcastConfig | null;
        userCredits: UserCredits | null;
        unreadNotificationCount: number;
    }>().props;
    const { errors, broadcastConfig, userCredits, unreadNotificationCount } = pageProps;
    const webuV2Flags = useMemo(
        () => resolveWebuV2FeatureFlags(pageProps),
        [pageProps.featureFlags],
    );
    const { isNavigating, destinationUrl } = usePageTransition();

    // Notification state
    const {
        notifications,
        unreadCount,
        isLoading: isLoadingNotifications,
        addNotification,
        markAsRead,
        markAllAsRead,
    } = useNotifications(unreadNotificationCount);

    // Credits state
    const [credits, setCredits] = useState<UserCredits | null>(userCredits);

    // Subscribe to user channel for real-time updates
    useUserChannel({
        userId: user.id,
        broadcastConfig,
        enabled: !!broadcastConfig?.key,
        onNotification: (notification: UserNotification) => {
            addNotification(notification);
            // Show toast for important notifications
            if (notification.type === 'credits_low') {
                toast(notification.title, {
                    description: notification.message,
                });
            }
        },
        onCreditsUpdated: (updated) => {
            setCredits({
                remaining: updated.remaining,
                monthlyLimit: updated.monthlyLimit,
                isUnlimited: updated.isUnlimited,
                usingOwnKey: updated.usingOwnKey,
            });
        },
    });

    // Update state when props change (e.g., after language switch)
    useEffect(() => {
        setSuggestions(filterCreatePromptExamples(initialSuggestions));
        setTypingPrompts(filterCreatePromptExamples(initialTypingPrompts));
    }, [initialSuggestions, initialTypingPrompts]);

    // Show toast when there are errors
    useEffect(() => {
        if (errors?.prompt) {
            toast.error(errors.prompt);
        }
    }, [errors]);

    // Fetch AI-powered content after page loads
    useEffect(() => {
        const fetchAiContent = async () => {
            try {
                const response = await axios.get('/create/ai-content');
                if (response.data) {
                    setSuggestions(filterCreatePromptExamples(response.data.suggestions || initialSuggestions));
                    setTypingPrompts(filterCreatePromptExamples(response.data.typingPrompts || initialTypingPrompts));
                }
            } catch {
                // Keep static content on error
            } finally {
                setIsLoadingAi(false);
            }
        };

        // Defer fetch to not block initial render
        const timeoutId = setTimeout(fetchAiContent, 100);
        return () => clearTimeout(timeoutId);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const hasPendingRedirect = window.sessionStorage.getItem(CREATE_PENDING_REDIRECT_STORAGE_KEY) === '1';
        if (!hasPendingRedirect) {
            return;
        }

        if (pendingRedirectUrl) {
            window.sessionStorage.removeItem(CREATE_PENDING_REDIRECT_STORAGE_KEY);
            window.location.replace(pendingRedirectUrl);
            return;
        }

        const timeoutId = window.setTimeout(() => {
            window.sessionStorage.removeItem(CREATE_PENDING_REDIRECT_STORAGE_KEY);
        }, 15000);

        return () => window.clearTimeout(timeoutId);
    }, [pendingRedirectUrl]);

    const handlePromptSubmit = (
        value: string,
        templateId: number | null,
        themePreset: string | null,
        mode: 'ai' | 'manual'
    ) => {
        if (mode === 'manual') {
            window.sessionStorage.setItem(CREATE_PENDING_REDIRECT_STORAGE_KEY, '1');
            router.post('/projects', {
                mode,
                project_name: value,
                template_id: templateId,
                theme_preset: themePreset,
            });
            return;
        }

        window.sessionStorage.setItem(CREATE_PENDING_REDIRECT_STORAGE_KEY, '1');
        router.post('/projects', {
            mode: 'ai',
            prompt: value,
            template_id: templateId,
            theme_preset: themePreset,
        });
    };

    const ALLOWED_GENERATE_STYLES = ['modern', 'minimal', 'luxury', 'playful', 'corporate'] as const;

    const handleQuickGenerate = (value: string, themePreset: string | null) => {
        const style = themePreset && ALLOWED_GENERATE_STYLES.includes(themePreset as (typeof ALLOWED_GENERATE_STYLES)[number])
            ? themePreset
            : undefined;
        window.sessionStorage.setItem(CREATE_PENDING_REDIRECT_STORAGE_KEY, '1');

        router.post(route('projects.generate-website'), {
            prompt: value,
            style,
        });
    };

    return (
        <>
            <Head title={t("Create")} />
            <Toaster />
            <DemoResetNotice />

            <TooltipProvider>
                <SidebarProvider className="admin-ui-shell">
                    <AppSidebar user={user} />
                    <SidebarInset className="admin-layout__inset">
                        <div className="admin-layout__body create-page">

                            {/* Header with sidebar trigger and user profile */}
                            <header className="admin-layout__header">
                                <div className="flex items-center gap-2">
                                    <SidebarTrigger />
                                    {credits && <GlobalCredits {...credits} />}
                                </div>

                                <div className="flex items-center gap-2">
                                    <LanguageSelector />
                                    <NotificationBell
                                        notifications={notifications}
                                        unreadCount={unreadCount}
                                        onMarkAsRead={markAsRead}
                                        onMarkAllAsRead={markAllAsRead}
                                        isLoading={isLoadingNotifications}
                                    />
                                    <ThemeToggle />

                                    {/* User Profile */}
                                    <DropdownMenu>
                                    <DropdownMenuTrigger className="outline-none flex items-center gap-2 hover:bg-muted/50 rounded-lg px-2 py-1 transition-colors">
                                        <div className="text-end hidden sm:block">
                                            <p className="text-sm font-medium">{user.name}</p>
                                            <p className="text-xs text-muted-foreground">{user.email}</p>
                                        </div>
                                        <Avatar className="h-8 w-8 cursor-pointer">
                                            <AvatarImage src={user.avatar || undefined} />
                                            <AvatarFallback className="bg-primary text-primary-foreground text-sm">
                                                {user.name.charAt(0).toUpperCase()}
                                            </AvatarFallback>
                                        </Avatar>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="w-56">
                                        <div className="px-2 py-1.5">
                                            <p className="text-sm font-medium">{user.name}</p>
                                            <p className="text-xs text-muted-foreground">{user.email}</p>
                                        </div>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem asChild>
                                            <Link href="/logout" method="post" as="button" className="w-full">
                                                <LogOut className="h-4 w-4 me-2" />
                                                {t('Log Out')}
                                            </Link>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </header>

                            {/* Hero – ChatGPT-style: centered welcome + single input */}
                            <main className="admin-layout__main">
                                <div className="admin-layout__content-shell">
                                    <div className="admin-layout__content-surface admin-layout__content-surface--hero">
                                {/* Title – static, centered, smaller font */}
                                <h1 className="create-page-title mb-3 max-w-3xl text-center text-2xl font-medium tracking-tight text-foreground md:text-4xl">
                                    {t('What kind of website do you want to create?')}
                                </h1>
                                {!canCreateProject && (
                                    <Alert variant="destructive" className="mb-4 w-full max-w-3xl rounded-xl">
                                        <AlertCircle className="h-4 w-4" />
                                        <AlertDescription className="text-sm">
                                            {cannotCreateReason}
                                            {' '}
                                            <Link href="/billing/plans" className="underline font-semibold">
                                                {t('View Plans')}
                                            </Link>
                                        </AlertDescription>
                                    </Alert>
                                )}

                                {/* Single prompt input – ChatGPT-style */}
                                <div className="create-chat-shell mt-8 w-full max-w-[56rem]">
                                    <PromptInput
                                        onSubmit={handlePromptSubmit}
                                        onQuickGenerate={canCreateProject && webuV2Flags.codeFirstInitialGeneration ? handleQuickGenerate : undefined}
                                        disabled={!canCreateProject}
                                        suggestions={suggestions}
                                        typingPrompts={typingPrompts}
                                        isLoadingSuggestions={isLoadingAi}
                                        templates={templates ?? []}
                                    />
                                </div>
                                    </div>
                                </div>
                            </main>

                        </div>
                    </SidebarInset>
                </SidebarProvider>
            </TooltipProvider>

            {/* Page transition skeleton */}
            {isNavigating && destinationUrl?.startsWith('/project/') && (
                <div className="fixed inset-0 z-[100] bg-background">
                    <ChatPageSkeleton />
                </div>
            )}
        </>
    );
}
