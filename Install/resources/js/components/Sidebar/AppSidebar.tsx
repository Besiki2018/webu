import { useLayoutEffect, useMemo, useRef, useState, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarFooter,
} from '@/components/ui/sidebar';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    FolderOpen,
    Files,
    Database,
    LayoutTemplate,
    LayoutGrid,
    Layers,
    ChevronDown,
    LayoutDashboard,
    Users,
    CreditCard,
    Crown,
    Receipt,
    Package,
    Puzzle,
    Globe,
    Clock,
    Settings,
    Sparkles,
    Bot,
    Cpu,
    Paintbrush,
    Gift,
    Layout,
    Activity,
    FileText,
    Type,
    Plus,
    Image as ImageIcon,
    ShoppingCart,
    CalendarDays,
    Truck,
    Bug,
    Building2,
    UsersRound,
    Tag,
    SearchCheck,
    Link2,
    Eye,
    ArrowLeft,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import ApplicationLogo from '@/components/ApplicationLogo';
import { ShareDialog } from '@/components/Referral/ShareDialog';
import { PageProps } from '@/types';
import { useTranslation } from '@/contexts/LanguageContext';

interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    role?: 'admin' | 'user';
}

interface AppSidebarProps {
    user: User;
}

const SCROLL_POSITION_KEY = 'sidebar-scroll-position';
const RECENT_COLLAPSED_KEY = 'sidebar-recent-collapsed';
const DAY_IN_MS = 24 * 60 * 60 * 1000;

type RecentProject = NonNullable<PageProps['recentProjects']>[number];

const CHAT_HISTORY_BUCKETS = [
    { key: 'today', labelKey: 'Today' },
    { key: 'yesterday', labelKey: 'Yesterday' },
    { key: 'previous_7_days', labelKey: 'Previous 7 days' },
    { key: 'previous_30_days', labelKey: 'Previous 30 days' },
    { key: 'older', labelKey: 'Older' },
] as const;

type ChatHistoryBucketKey = (typeof CHAT_HISTORY_BUCKETS)[number]['key'];

const SIDEBAR_COMPACT_LABELS: Record<string, string> = {
    'All Projects': 'Projects',
    'File Manager': 'Files',
    'Project Workspace': 'Workspace',
    'Back to Projects': 'Back',
    'Previous 7 days': '7 days',
    'Previous 30 days': '30 days',
    'Shipping & Delivery': 'Shipping',
    'Attribute Values': 'Values',
    'Activity Log': 'Activity',
    'View Website': 'View Site',
    'AI Builders': 'Builders',
    'AI Providers': 'Providers',
    'CMS Sections': 'CMS',
    'Landing Page': 'Landing',
    'Operation Logs': 'Logs',
    'AI Bug Fixer': 'Bug Fixer',
};

const SIDEBAR_CHAT_TITLE_MAX_CHARS = 24;

function resolveProjectActivityDate(project: RecentProject): Date | null {
    const value = project.last_viewed_at ?? project.updated_at;
    if (!value) {
        return null;
    }

    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function getProjectHistoryBucket(project: RecentProject): ChatHistoryBucketKey {
    const activityDate = resolveProjectActivityDate(project);
    if (!activityDate) {
        return 'older';
    }

    const today = new Date();
    const startOfToday = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const activityDay = new Date(activityDate.getFullYear(), activityDate.getMonth(), activityDate.getDate());
    const diffDays = Math.floor((startOfToday.getTime() - activityDay.getTime()) / DAY_IN_MS);

    if (diffDays <= 0) {
        return 'today';
    }
    if (diffDays === 1) {
        return 'yesterday';
    }
    if (diffDays <= 7) {
        return 'previous_7_days';
    }
    if (diffDays <= 30) {
        return 'previous_30_days';
    }

    return 'older';
}

export function AppSidebar({ user }: AppSidebarProps) {
    const { url, props } = usePage<PageProps>();
    const { t } = useTranslation();
    const scrollAreaRef = useRef<HTMLDivElement>(null);
    const recentProjects = props.recentProjects;
    const hasUpgradablePlans = props.hasUpgradablePlans;
    const entitlementFeatures = props.entitlements?.features;

    // Share dialog state
    const [shareDialogOpen, setShareDialogOpen] = useState(false);

    // Recent collapsible state - persisted to localStorage
    const [recentOpen, setRecentOpen] = useState(() => {
        if (typeof window !== 'undefined') {
            const saved = localStorage.getItem(RECENT_COLLAPSED_KEY);
            return saved !== 'closed'; // Default to open
        }
        return true;
    });

    // Save recent collapsible state to localStorage
    useEffect(() => {
        localStorage.setItem(RECENT_COLLAPSED_KEY, recentOpen ? 'open' : 'closed');
    }, [recentOpen]);

    // Persist and restore scroll position across navigation
    // useLayoutEffect runs synchronously before paint to prevent visual flash
    useLayoutEffect(() => {
        const scrollArea = scrollAreaRef.current;
        if (!scrollArea) return;

        // Find the viewport element inside ScrollArea
        const viewport = scrollArea.querySelector('[data-slot="scroll-area-viewport"]') as HTMLElement;
        if (!viewport) return;

        // Restore scroll position
        const savedPosition = sessionStorage.getItem(SCROLL_POSITION_KEY);
        if (savedPosition) {
            viewport.scrollTop = parseInt(savedPosition, 10);
        }

        // Save scroll position on scroll
        const handleScroll = () => {
            sessionStorage.setItem(SCROLL_POSITION_KEY, viewport.scrollTop.toString());
        };

        viewport.addEventListener('scroll', handleScroll);
        return () => viewport.removeEventListener('scroll', handleScroll);
    }, []);

    const projectItems = [
        { titleKey: 'All Projects', href: '/projects', icon: FolderOpen, enabled: true },
        { titleKey: 'File Manager', href: '/file-manager', icon: Files, enabled: Boolean(entitlementFeatures?.file_storage) },
        { titleKey: 'Database', href: '/database', icon: Database, enabled: Boolean(entitlementFeatures?.firebase) },
        { titleKey: 'Billing', href: '/billing', icon: CreditCard, enabled: true },
        { titleKey: 'Settings', href: '/profile', icon: Settings, enabled: true },
    ].filter((item) => item.enabled);

    const adminItems: Array<{ type?: 'groupLabel'; titleKey: string; href?: string; icon?: LucideIcon }> = [
        { titleKey: 'Overview', href: '/admin/overview', icon: LayoutDashboard },
        { titleKey: 'Components', href: '/admin/component-library', icon: Layers },
        { titleKey: 'Projects', href: '/admin/projects', icon: FolderOpen },
        { titleKey: 'Bookings', href: '/admin/bookings', icon: CalendarDays },
        { titleKey: 'Users', href: '/admin/users', icon: Users },
        { titleKey: 'Tenants', href: '/admin/tenants', icon: Building2 },
        { titleKey: 'Subscriptions', href: '/admin/subscriptions', icon: Crown },
        { titleKey: 'Transactions', href: '/admin/transactions', icon: Receipt },
        { titleKey: 'Referrals', href: '/admin/referrals', icon: Gift },
        { titleKey: 'Plans', href: '/admin/plans', icon: Package },
        { titleKey: 'AI Builders', href: '/admin/ai-builders', icon: Bot },
        { titleKey: 'AI Providers', href: '/admin/ai-providers', icon: Cpu },
        { type: 'groupLabel', titleKey: 'Templates' },
        { titleKey: 'AI Templates', href: '/admin/templates', icon: LayoutTemplate },
        { titleKey: 'CMS Sections', href: '/admin/cms-sections', icon: LayoutTemplate },
        { titleKey: 'Websites', href: '/admin/websites', icon: Globe },
        { titleKey: 'Landing Page', href: '/admin/landing-builder', icon: Layout },
        { titleKey: 'Plugins', href: '/admin/plugins', icon: Puzzle },
        { titleKey: 'Languages', href: '/admin/languages', icon: Globe },
        { titleKey: 'Cronjobs', href: '/admin/cronjobs', icon: Clock },
        { titleKey: 'Operation Logs', href: '/admin/operation-logs', icon: Activity },
        { titleKey: 'AI Bug Fixer', href: '/admin/bugfixer', icon: Bug },
        { titleKey: 'Settings', href: '/admin/settings', icon: Settings },
    ];

    const currentPath = url.split('?')[0] ?? url;
    const currentQuery = url.includes('?') ? url.slice(url.indexOf('?') + 1) : '';
    const currentParams = new URLSearchParams(currentQuery);
    const currentCmsTab = currentParams.get('tab') ?? 'dashboard';
    const currentCmsAction = currentParams.get('action') ?? '';
    const projectMatch = currentPath.match(/^\/project\/([^/]+)/);
    const currentProjectId = projectMatch?.[1] ?? null;
    const isProjectWorkspaceContext = Boolean(
        currentProjectId
        && (
            currentPath === `/project/${currentProjectId}`
            || currentPath.startsWith(`/project/${currentProjectId}/cms`)
            || currentPath.startsWith(`/project/${currentProjectId}/settings`)
        )
    );

    const currentProjectName = (() => {
        const pageProject = (props as unknown as { project?: { id?: string; name?: string } }).project;
        if (pageProject?.name) {
            return pageProject.name;
        }

        if (!currentProjectId || !recentProjects) {
            return null;
        }

        return recentProjects.find((project) => String(project.id) === String(currentProjectId))?.name ?? null;
    })();

    const workspaceAvailability = (() => {
        const workspaceModules = props.projectWorkspaceModules;
        if (!workspaceModules || !currentProjectId) {
            return {} as Record<string, boolean>;
        }

        if (String(workspaceModules.project_id) !== String(currentProjectId)) {
            return {} as Record<string, boolean>;
        }

        return workspaceModules.available ?? {};
    })();

    const isAdminUser = user.role === 'admin';

    const isWorkspaceModuleAvailable = (key: string, fallback: boolean): boolean => {
        if (isAdminUser) {
            return true;
        }

        const value = workspaceAvailability[key];
        if (typeof value === 'boolean') {
            return value;
        }

        return fallback;
    };

    const projectWorkspaceItems = currentProjectId
        ? [
            {
                titleKey: 'Dashboard',
                href: `/project/${currentProjectId}/cms?tab=dashboard`,
                icon: LayoutDashboard,
                active: currentPath === `/project/${currentProjectId}/cms` && currentCmsTab === 'dashboard',
                enabled: true,
            },
            {
                titleKey: 'Pages',
                href: `/project/${currentProjectId}/cms?tab=pages`,
                icon: FileText,
                active: currentPath === `/project/${currentProjectId}/cms` && ['pages', 'pages-redirects'].includes(currentCmsTab),
                enabled: isWorkspaceModuleAvailable('cms_pages', true),
                children: [
                    { titleKey: 'All Pages', href: `/project/${currentProjectId}/cms?tab=pages`, active: currentCmsTab === 'pages' },
                    { titleKey: 'Redirects', href: `/project/${currentProjectId}/cms?tab=pages-redirects`, active: currentCmsTab === 'pages-redirects' },
                ],
            },
            {
                titleKey: 'Entries',
                href: `/project/${currentProjectId}/cms?tab=blog-posts`,
                icon: Type,
                active: currentPath === `/project/${currentProjectId}/cms` && ['cms-collections', 'cms-fields', 'cms-relationships', 'blog-posts'].includes(currentCmsTab),
                enabled: isWorkspaceModuleAvailable('cms_pages', true),
                children: [
                    {
                        titleKey: 'All Entries',
                        href: `/project/${currentProjectId}/cms?tab=blog-posts`,
                        active: currentCmsTab === 'blog-posts' && currentCmsAction !== 'create',
                    },
                    {
                        titleKey: 'Add New',
                        href: `/project/${currentProjectId}/cms?tab=blog-posts&action=create`,
                        active: currentCmsTab === 'blog-posts' && currentCmsAction === 'create',
                    },
                    {
                        titleKey: 'Categories',
                        href: `/project/${currentProjectId}/cms?tab=cms-collections`,
                        active: currentCmsTab === 'cms-collections',
                    },
                ],
            },
            {
                titleKey: 'Products',
                href: `/project/${currentProjectId}/cms?tab=ecommerce-products`,
                icon: ShoppingCart,
                active: currentPath === `/project/${currentProjectId}/cms` && [
                    'ecommerce-products',
                    'ecommerce-add-product',
                    'ecommerce-categories',
                    'ecommerce-attributes',
                    'ecommerce-attribute-values',
                    'ecommerce-variants',
                    'ecommerce-inventory',
                    'ecommerce-orders',
                ].includes(currentCmsTab),
                enabled: isWorkspaceModuleAvailable('ecommerce', true),
                children: [
                    { titleKey: 'All Products', href: `/project/${currentProjectId}/cms?tab=ecommerce-products`, active: currentCmsTab === 'ecommerce-products' },
                    { titleKey: 'Add Product', href: `/project/${currentProjectId}/cms?tab=ecommerce-add-product`, active: currentCmsTab === 'ecommerce-add-product' },
                    { titleKey: 'Categories', href: `/project/${currentProjectId}/cms?tab=ecommerce-categories`, active: currentCmsTab === 'ecommerce-categories' },
                    { titleKey: 'Attributes', href: `/project/${currentProjectId}/cms?tab=ecommerce-attributes`, active: currentCmsTab === 'ecommerce-attributes' },
                    { titleKey: 'Attribute Values', href: `/project/${currentProjectId}/cms?tab=ecommerce-attribute-values`, active: currentCmsTab === 'ecommerce-attribute-values' },
                    { titleKey: 'Variants', href: `/project/${currentProjectId}/cms?tab=ecommerce-variants`, active: currentCmsTab === 'ecommerce-variants' },
                    { titleKey: 'Inventory', href: `/project/${currentProjectId}/cms?tab=ecommerce-inventory`, active: currentCmsTab === 'ecommerce-inventory' },
                    { titleKey: 'Orders', href: `/project/${currentProjectId}/cms?tab=ecommerce-orders`, active: currentCmsTab === 'ecommerce-orders' },
                ],
            },
            {
                titleKey: 'Customers',
                href: `/project/${currentProjectId}/cms?tab=customers`,
                icon: UsersRound,
                active: currentPath === `/project/${currentProjectId}/cms` && currentCmsTab === 'customers',
                enabled: isWorkspaceModuleAvailable('ecommerce', true),
            },
            {
                titleKey: 'Discounts',
                href: `/project/${currentProjectId}/cms?tab=discounts`,
                icon: Tag,
                active: currentPath === `/project/${currentProjectId}/cms` && currentCmsTab === 'discounts',
                enabled: isWorkspaceModuleAvailable('ecommerce', true),
            },
            {
                titleKey: 'Shipping & Delivery',
                href: `/project/${currentProjectId}/cms?tab=ecommerce-shipping`,
                icon: Truck,
                active: currentPath === `/project/${currentProjectId}/cms` && currentCmsTab === 'ecommerce-shipping',
                enabled: isWorkspaceModuleAvailable('shipping', true),
            },
            {
                titleKey: 'Payments',
                href: `/project/${currentProjectId}/cms?tab=ecommerce-payments`,
                icon: CreditCard,
                active: currentPath === `/project/${currentProjectId}/cms` && currentCmsTab === 'ecommerce-payments',
                enabled: isWorkspaceModuleAvailable('payments', true),
            },
            {
                titleKey: 'Booking',
                href: `/project/${currentProjectId}/cms?tab=booking`,
                icon: CalendarDays,
                active: currentPath === `/project/${currentProjectId}/cms` && ['booking', 'booking-calendar', 'booking-services', 'booking-team', 'booking-finance'].includes(currentCmsTab),
                enabled: isWorkspaceModuleAvailable('booking', true),
                children: [
                    { titleKey: 'Inbox', href: `/project/${currentProjectId}/cms?tab=booking`, active: currentCmsTab === 'booking' },
                    { titleKey: 'Calendar', href: `/project/${currentProjectId}/cms?tab=booking-calendar`, active: currentCmsTab === 'booking-calendar' },
                    { titleKey: 'Services', href: `/project/${currentProjectId}/cms?tab=booking-services`, active: currentCmsTab === 'booking-services' },
                    { titleKey: 'Team', href: `/project/${currentProjectId}/cms?tab=booking-team`, active: currentCmsTab === 'booking-team' },
                    { titleKey: 'Finance', href: `/project/${currentProjectId}/cms?tab=booking-finance`, active: currentCmsTab === 'booking-finance' },
                ],
            },
            {
                titleKey: 'Media',
                href: `/project/${currentProjectId}/cms?tab=media`,
                icon: ImageIcon,
                active: currentPath === `/project/${currentProjectId}/cms` && currentCmsTab === 'media',
                enabled: isWorkspaceModuleAvailable('media_library', true),
            },
            {
                titleKey: 'Design',
                href: `/project/${currentProjectId}/cms?tab=design`,
                icon: Paintbrush,
                active: currentPath === `/project/${currentProjectId}/cms` && ['design', 'design-branding', 'design-layout', 'design-components', 'design-presets', 'design-menus', 'menus'].includes(currentCmsTab),
                enabled: isWorkspaceModuleAvailable('cms_settings', true),
                children: [
                    { titleKey: 'Branding', href: `/project/${currentProjectId}/cms?tab=design-branding`, active: currentCmsTab === 'design-branding' },
                    { titleKey: 'Layout', href: `/project/${currentProjectId}/cms?tab=design-layout`, active: currentCmsTab === 'design-layout' },
                    { titleKey: 'Menus', href: `/project/${currentProjectId}/cms?tab=design-menus`, active: ['design-menus', 'menus'].includes(currentCmsTab) },
                ],
            },
            {
                titleKey: 'SEO',
                href: `/project/${currentProjectId}/cms?tab=seo`,
                icon: SearchCheck,
                active: currentPath === `/project/${currentProjectId}/cms` && currentCmsTab === 'seo',
                enabled: isWorkspaceModuleAvailable('cms_settings', true),
            },
            {
                titleKey: 'Domain',
                href: `/project/${currentProjectId}/cms?tab=domain`,
                icon: Link2,
                active: currentPath === `/project/${currentProjectId}/cms` && currentCmsTab === 'domain',
                enabled: isWorkspaceModuleAvailable('domains', true),
            },
            {
                titleKey: 'Settings',
                href: `/project/${currentProjectId}/cms?tab=settings-general`,
                icon: Settings,
                active: currentPath === `/project/${currentProjectId}/cms` && ['settings-general', 'settings-team', 'settings-integrations', 'settings-webhooks'].includes(currentCmsTab),
                enabled: true,
                children: [
                    { titleKey: 'General', href: `/project/${currentProjectId}/cms?tab=settings-general`, active: currentCmsTab === 'settings-general' },
                ],
            },
            {
                titleKey: 'Activity Log',
                href: `/project/${currentProjectId}/cms?tab=activity`,
                icon: Activity,
                active: currentPath === `/project/${currentProjectId}/cms` && currentCmsTab === 'activity',
                enabled: true,
            },
            {
                titleKey: 'View Website',
                href: `/project/${currentProjectId}/cms?tab=view-website`,
                icon: Eye,
                active: currentPath === `/project/${currentProjectId}/cms` && currentCmsTab === 'view-website',
                enabled: true,
            },
        ].filter((item) => item.enabled)
        : [];

    const groupedRecentProjects = useMemo(() => {
        const buckets = new Map<ChatHistoryBucketKey, RecentProject[]>(
            CHAT_HISTORY_BUCKETS.map((bucket) => [bucket.key, [] as RecentProject[]]),
        );

        for (const project of recentProjects ?? []) {
            const bucket = getProjectHistoryBucket(project);
            buckets.get(bucket)?.push(project);
        }

        return CHAT_HISTORY_BUCKETS
            .map((bucket) => ({
                ...bucket,
                projects: buckets.get(bucket.key) ?? [],
            }))
            .filter((bucket) => bucket.projects.length > 0);
    }, [recentProjects]);

    const isActive = (href: string) => url.startsWith(href);
    const userDisplayName = user.name?.trim() || user.email;
    const userInitial = userDisplayName.charAt(0).toUpperCase();
    const sidebarLabel = (key: string) => t(SIDEBAR_COMPACT_LABELS[key] ?? key);

    return (
        <Sidebar className="app-sidebar--chatgpt border-r-0 group/sidebar bg-sidebar">
            <SidebarHeader className="app-sidebar__header">
                <div className="app-sidebar__brand-row">
                    <Link href="/create" className="app-sidebar__brand-link">
                        <ApplicationLogo showText size="lg" />
                    </Link>
                </div>
                {!isProjectWorkspaceContext ? (
                    <SidebarMenu className="app-sidebar__header-menu">
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild isActive={url === '/create'} className="app-sidebar__new-chat-button">
                                <Link href="/create">
                                    <Plus className="h-4 w-4 shrink-0" />
                                    <span>{t('New chat')}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                ) : null}
            </SidebarHeader>

            <SidebarContent className="app-sidebar__content">
                <div ref={scrollAreaRef} className="h-full">
                    <ScrollArea
                        className="app-sidebar__scroll-area h-full [&_[data-slot=scroll-area-scrollbar]]:opacity-0 [&_[data-slot=scroll-area-scrollbar]]:transition-opacity group-hover/sidebar:[&_[data-slot=scroll-area-scrollbar]]:opacity-100"
                        type="always"
                    >
                        {isProjectWorkspaceContext ? (
                            <>
                                <SidebarGroup className="app-sidebar__group app-sidebar__group--workspace">
                                    <SidebarGroupLabel className="app-sidebar__section-label">
                                        {currentProjectName ? `${t('Project')}: ${currentProjectName}` : sidebarLabel('Project Workspace')}
                                    </SidebarGroupLabel>
                                    <SidebarGroupContent>
                                        <SidebarMenu className="app-sidebar__menu">
                                            {projectWorkspaceItems.map((item) => (
                                                <SidebarMenuItem key={item.titleKey}>
                                                    <SidebarMenuButton asChild isActive={item.active} className="app-sidebar__menu-item">
                                                        <Link href={item.href}>
                                                            <item.icon className="h-4 w-4" />
                                                            <span>{sidebarLabel(item.titleKey)}</span>
                                                        </Link>
                                                    </SidebarMenuButton>
                                                    {item.active && Array.isArray((item as { children?: Array<{ titleKey: string; href: string; active: boolean }> }).children) && (item as { children?: Array<{ titleKey: string; href: string; active: boolean }> }).children!.length > 0 ? (
                                                        <div className="app-sidebar__sub-list">
                                                            {(item as { children?: Array<{ titleKey: string; href: string; active: boolean }> }).children!.map((child) => (
                                                                <Link
                                                                    key={`${item.titleKey}-${child.titleKey}`}
                                                                    href={child.href}
                                                                    className={`app-sidebar__sub-item ${child.active ? 'is-active' : ''}`}
                                                                >
                                                                    <span>{sidebarLabel(child.titleKey)}</span>
                                                                </Link>
                                                            ))}
                                                        </div>
                                                    ) : null}
                                                </SidebarMenuItem>
                                            ))}
                                            <SidebarMenuItem>
                                                <SidebarMenuButton asChild isActive={currentPath === '/projects'} className="app-sidebar__menu-item">
                                                    <Link href="/projects">
                                                        <ArrowLeft className="h-4 w-4 rtl:rotate-180" />
                                                        <span>{sidebarLabel('Back to Projects')}</span>
                                                    </Link>
                                                </SidebarMenuButton>
                                            </SidebarMenuItem>
                                        </SidebarMenu>
                                    </SidebarGroupContent>
                                </SidebarGroup>
                                {user?.role === 'admin' ? (
                                    <SidebarGroup className="app-sidebar__group app-sidebar__group--links">
                                        <SidebarGroupLabel className="app-sidebar__section-label">{t('Administration')}</SidebarGroupLabel>
                                        <SidebarGroupContent>
                                            <SidebarMenu className="app-sidebar__menu">
                                                {adminItems.map((item) =>
                                                    (item as { type?: string }).type === 'groupLabel' ? (
                                                        <SidebarMenuItem key={`label-${item.titleKey}`}>
                                                            <div className="px-2 py-1.5 text-xs font-semibold text-muted-foreground border-t border-sidebar-border mt-1 pt-2 first:mt-0 first:pt-0 first:border-t-0">
                                                                {sidebarLabel(item.titleKey)}
                                                            </div>
                                                        </SidebarMenuItem>
                                                    ) : (
                                                        <SidebarMenuItem key={item.titleKey}>
                                                            <SidebarMenuButton asChild isActive={isActive(item.href ?? '')} className="app-sidebar__menu-item">
                                                                <Link href={item.href ?? '#'}>
                                                                    {item.icon ? <item.icon className="h-4 w-4" /> : null}
                                                                    <span>{sidebarLabel(item.titleKey)}</span>
                                                                </Link>
                                                            </SidebarMenuButton>
                                                        </SidebarMenuItem>
                                                    )
                                                )}
                                            </SidebarMenu>
                                        </SidebarGroupContent>
                                    </SidebarGroup>
                                ) : null}
                            </>
                        ) : (
                            <>
                                <Collapsible open={recentOpen} onOpenChange={setRecentOpen} className="group/collapsible">
                                    <SidebarGroup className="app-sidebar__group app-sidebar__group--history">
                                        <CollapsibleTrigger asChild>
                                            <SidebarGroupLabel className="app-sidebar__collapsible-label">
                                                <span>{t('Chats')}</span>
                                                <ChevronDown className="h-4 w-4 transition-transform group-data-[state=closed]/collapsible:rotate-[-90deg]" />
                                            </SidebarGroupLabel>
                                        </CollapsibleTrigger>
                                        <CollapsibleContent>
                                            <SidebarGroupContent>
                                                {groupedRecentProjects.length > 0 ? (
                                                    <div className="app-sidebar__history">
                                                        {groupedRecentProjects.map((group) => (
                                                            <div key={group.key} className="app-sidebar__history-bucket">
                                                                <p className="app-sidebar__history-title">{sidebarLabel(group.labelKey)}</p>
                                                                <SidebarMenu className="app-sidebar__menu app-sidebar__chat-list">
                                                                    {group.projects.map((project) => {
                                                                        const href = `/project/${project.id}`;
                                                                        const isCurrent = currentPath === href || currentPath.startsWith(`${href}/`);
                                                                        const normalizedName = project.name.trim().replace(/\s+/g, ' ');
                                                                        const displayName = normalizedName.length > SIDEBAR_CHAT_TITLE_MAX_CHARS
                                                                            ? `${normalizedName.slice(0, SIDEBAR_CHAT_TITLE_MAX_CHARS)}…`
                                                                            : normalizedName;

                                                                        return (
                                                                            <SidebarMenuItem key={project.id}>
                                                                                <SidebarMenuButton asChild isActive={isCurrent} className="app-sidebar__chat-item">
                                                                                    <Link href={href} title={project.name}>
                                                                                        <span className="truncate">{displayName}</span>
                                                                                    </Link>
                                                                                </SidebarMenuButton>
                                                                            </SidebarMenuItem>
                                                                        );
                                                                    })}
                                                                </SidebarMenu>
                                                            </div>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <p className="app-sidebar__empty-history">{t('No chats yet')}</p>
                                                )}
                                            </SidebarGroupContent>
                                        </CollapsibleContent>
                                    </SidebarGroup>
                                </Collapsible>

                                <SidebarGroup className="app-sidebar__group app-sidebar__group--links">
                                    <SidebarGroupLabel className="app-sidebar__section-label">{t('Workspace')}</SidebarGroupLabel>
                                    <SidebarGroupContent>
                                        <SidebarMenu className="app-sidebar__menu">
                                            {projectItems.map((item) => (
                                                <SidebarMenuItem key={item.titleKey}>
                                                    <SidebarMenuButton asChild isActive={isActive(item.href)} className="app-sidebar__menu-item">
                                                        <Link href={item.href}>
                                                            <item.icon className="h-4 w-4 shrink-0" />
                                                            <span>{sidebarLabel(item.titleKey)}</span>
                                                        </Link>
                                                    </SidebarMenuButton>
                                                </SidebarMenuItem>
                                            ))}
                                        </SidebarMenu>
                                    </SidebarGroupContent>
                                </SidebarGroup>

                                {user?.role === 'admin' ? (
                                    <SidebarGroup className="app-sidebar__group app-sidebar__group--links">
                                        <SidebarGroupLabel className="app-sidebar__section-label">{t('Administration')}</SidebarGroupLabel>
                                        <SidebarGroupContent>
                                            <SidebarMenu className="app-sidebar__menu">
                                                {adminItems.map((item) =>
                                                    (item as { type?: string }).type === 'groupLabel' ? (
                                                        <SidebarMenuItem key={`label-${item.titleKey}`}>
                                                            <div className="px-2 py-1.5 text-xs font-semibold text-muted-foreground border-t border-sidebar-border mt-1 pt-2 first:mt-0 first:pt-0 first:border-t-0">
                                                                {sidebarLabel(item.titleKey)}
                                                            </div>
                                                        </SidebarMenuItem>
                                                    ) : (
                                                        <SidebarMenuItem key={item.titleKey}>
                                                            <SidebarMenuButton asChild isActive={isActive(item.href ?? '')} className="app-sidebar__menu-item">
                                                                <Link href={item.href ?? '#'}>
                                                                    {item.icon ? <item.icon className="h-4 w-4" /> : null}
                                                                    <span>{sidebarLabel(item.titleKey)}</span>
                                                                </Link>
                                                            </SidebarMenuButton>
                                                        </SidebarMenuItem>
                                                    )
                                                )}
                                            </SidebarMenu>
                                        </SidebarGroupContent>
                                    </SidebarGroup>
                                ) : null}
                            </>
                        )}
                    </ScrollArea>
                </div>
            </SidebarContent>

            {!isProjectWorkspaceContext ? (
                <SidebarFooter className="app-sidebar__footer">
                    <div className="app-sidebar__footer-actions">
                        <Button
                            variant="ghost"
                            className="app-sidebar__footer-action"
                            size="sm"
                            onClick={() => setShareDialogOpen(true)}
                        >
                            <Gift className="h-4 w-4 shrink-0" />
                            <span>{t('Invite Friends')}</span>
                        </Button>
                        {hasUpgradablePlans ? (
                            <Button asChild variant="ghost" className="app-sidebar__footer-action app-sidebar__footer-action--upgrade" size="sm">
                                <Link href="/billing/plans">
                                    <Sparkles className="h-4 w-4 shrink-0" />
                                    <span>{t('Upgrade your Plan')}</span>
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                    <ShareDialog open={shareDialogOpen} onOpenChange={setShareDialogOpen} />
                    <Link href="/profile" className="app-sidebar__profile">
                        <Avatar className="app-sidebar__avatar">
                            <AvatarImage src={user.avatar || undefined} alt={userDisplayName} />
                            <AvatarFallback>{userInitial}</AvatarFallback>
                        </Avatar>
                        <div className="app-sidebar__profile-meta">
                            <span className="app-sidebar__profile-name">{userDisplayName}</span>
                            <span className="app-sidebar__profile-email">{user.email}</span>
                        </div>
                    </Link>
                </SidebarFooter>
            ) : null}
        </Sidebar>
    );
}
