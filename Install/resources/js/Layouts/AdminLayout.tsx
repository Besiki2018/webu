import { PropsWithChildren, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { SidebarProvider, SidebarInset, SidebarTrigger } from '@/components/ui/sidebar';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { AppSidebar } from '@/components/Sidebar/AppSidebar';
import { ThemeToggle } from '@/components/ThemeToggle';
import { LanguageSelector } from '@/components/LanguageSelector';
import { NotificationBell } from '@/components/Notifications/NotificationBell';
import { GlobalCredits } from '@/components/Header/GlobalCredits';
import { useNotifications } from '@/hooks/useNotifications';
import { useUserChannel } from '@/hooks/useUserChannel';
import { cn } from '@/lib/utils';
import { User, PageProps } from '@/types';
import type { UserCredits, UserNotification } from '@/types/notifications';
import type { BroadcastConfig } from '@/hooks/useBuilderPusher';
import { LogOut } from 'lucide-react';

interface AdminLayoutProps extends PropsWithChildren {
    user: User | null;
    title: string;
    fullWidth?: boolean;
    variant?: 'default' | 'cms';
    hideChrome?: boolean;
}

export default function AdminLayout({
    user,
    title,
    fullWidth = false,
    variant = 'default',
    hideChrome = false,
    children,
}: AdminLayoutProps) {
    const { broadcastConfig, userCredits, unreadNotificationCount } = usePage<PageProps & {
        broadcastConfig: BroadcastConfig | null;
        userCredits: UserCredits | null;
        unreadNotificationCount: number;
    }>().props;
    const isCmsVariant = variant === 'cms';

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
        userId: user?.id ?? null,
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

    if (!user) return null;

    if (hideChrome) {
        return (
            <>
                <Head title={title} />

                <TooltipProvider>
                    <div className={cn('admin-ui-shell admin-ui-shell--embedded', isCmsVariant && 'cms-admin-shell cms-admin-shell--embedded')}>
                        <div className={cn('admin-layout__body', isCmsVariant && 'cms-admin-shell__body')}>
                            <main className={cn('min-h-screen min-w-0 overflow-x-hidden', isCmsVariant && 'cms-admin-shell__main cms-admin-shell__main--embedded')}>
                                <div
                                    className={cn(
                                        'admin-layout__content-shell min-h-screen',
                                        fullWidth && 'admin-layout__content-shell--full',
                                        isCmsVariant && 'cms-admin-shell__content cms-admin-shell__content--embedded'
                                    )}
                                >
                                    {children}
                                </div>
                            </main>
                        </div>
                    </div>
                </TooltipProvider>
                <Toaster />
            </>
        );
    }

    return (
        <>
            <Head title={title} />

            <TooltipProvider>
                <SidebarProvider
                    className={cn('admin-ui-shell', isCmsVariant && 'cms-admin-shell')}
                >
                    <AppSidebar user={user} />
                    <SidebarInset className={cn('admin-layout__inset', isCmsVariant ? 'cms-admin-shell__inset' : undefined)}>
                        <div className={cn('admin-layout__body', isCmsVariant && 'cms-admin-shell__body')}>
                            {/* Header */}
                            <header
                                className={cn(
                                    'admin-layout__header',
                                    isCmsVariant && 'cms-admin-shell__header px-3 md:px-4'
                                )}
                            >
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
                                        <div className="text-right hidden sm:block">
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
                                                <LogOut className="h-4 w-4 mr-2" />
                                                Log out
                                            </Link>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </header>

                            {/* Main Content – no horizontal scroll; content fits viewport */}
                            <main className={cn('admin-layout__main', isCmsVariant && 'cms-admin-shell__main')}>
                                <div
                                    className={cn(
                                        'admin-layout__content-shell',
                                        fullWidth && 'admin-layout__content-shell--full',
                                        !isCmsVariant && 'admin-layout__content-surface admin-layout__content-surface--page',
                                        isCmsVariant && 'cms-admin-shell__content'
                                    )}
                                >
                                    {children}
                                </div>
                            </main>
                        </div>
                    </SidebarInset>
                </SidebarProvider>
            </TooltipProvider>
            <Toaster />
        </>
    );
}
