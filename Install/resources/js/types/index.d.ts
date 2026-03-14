export type UserRole = 'admin' | 'user';

export interface User {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    role: UserRole;
    email_verified_at?: string;
}

export interface Project {
    id: string;
    name: string;
    description: string | null;
    thumbnail: string | null;
    preview_url?: string | null;
    is_public: boolean;
    is_starred: boolean;
    status: 'draft' | 'published' | 'archived';
    build_status?: 'idle' | 'building' | 'completed' | 'failed';
    last_viewed_at: string | null;
    updated_at: string;
    deleted_at?: string | null;
    subdomain?: string | null;
    custom_domain?: string | null;
    user?: User;
    pivot?: {
        permission: 'view' | 'edit' | 'admin';
    };
}

export type ProjectTab = 'all' | 'favorites' | 'trash';
export type ProjectSort = 'last-edited' | 'name' | 'created';
export type ProjectVisibility = 'public' | 'private';

export interface ProjectFilters {
    search?: string | null;
    sort: ProjectSort;
    visibility?: ProjectVisibility | null;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface ProjectCounts {
    all: number;
    favorites: number;
    trash: number;
}

export interface ProjectsPageProps extends PageProps {
    projects: PaginatedData<Project>;
    counts: ProjectCounts;
    activeTab: ProjectTab;
    filters: ProjectFilters;
    baseDomain?: string;
}

export interface Template {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    thumbnail: string | null;
    is_system: boolean;
    plans?: Plan[];
    plan_ids?: number[];
}

export type ColorTheme = 'neutral' | 'blue' | 'green' | 'orange' | 'red' | 'rose' | 'violet' | 'yellow';

export type RealtimeProvider = 'sse' | 'pusher';

export interface AppSettings {
    site_name: string;
    site_tagline: string;
    site_description: string;
    site_logo: string | null;
    site_logo_dark: string | null;
    site_favicon: string | null;
    default_theme: 'light' | 'dark' | 'system';
    color_theme: ColorTheme;
    default_locale: string;
    timezone: string;
    date_format: string;
    landing_page_enabled: boolean;
    cookie_consent_enabled: boolean;
    enable_registration: boolean;
    google_login_enabled: boolean;
    facebook_login_enabled: boolean;
    github_login_enabled: boolean;
    recaptcha_enabled: boolean;
    recaptcha_site_key: string;
    realtime_provider: RealtimeProvider;
    pusher_key: string;
    pusher_cluster: string;
}

export interface EntitlementFeatures {
    subdomains: boolean;
    custom_domains: boolean;
    private_visibility: boolean;
    firebase: boolean;
    firebase_custom_config: boolean;
    file_storage: boolean;
    own_ai_api_key: boolean;
    booking_prepayment: boolean;
    ecommerce: boolean;
    booking: boolean;
    shipping: boolean;
    ecommerce_inventory: boolean;
    ecommerce_accounting: boolean;
    ecommerce_rs: boolean;
    booking_team_scheduling: boolean;
    booking_finance: boolean;
    booking_advanced_calendar: boolean;
}

export interface Entitlements {
    features: EntitlementFeatures;
}

export interface ProjectWorkspaceModules {
    project_id: string;
    site_id: string;
    available: Record<string, boolean>;
}

export interface WebuV2FeatureFlags {
    codeFirstInitialGeneration: boolean;
    workspaceBackedVisualBuilder: boolean;
    imageToSiteImport: boolean;
    advancedAiWorkspaceEdits: boolean;
}

export interface SharedFeatureFlags {
    webuV2: WebuV2FeatureFlags;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User | null;
    };
    flash: {
        message: string | null;
        error: string | null;
    };
    appSettings: AppSettings;
    entitlements: Entitlements;
    projectWorkspaceModules: ProjectWorkspaceModules | null;
    featureFlags: SharedFeatureFlags;
    recentProjects: Project[] | null;
    hasUpgradablePlans: boolean;
    isDemo?: boolean;
    userCredits: {
        remaining: number;
        monthlyLimit: number;
        isUnlimited: boolean;
        usingOwnKey: boolean;
    } | null;
    unreadNotificationCount: number;
};

export interface ReadyTemplateItem {
    slug: string;
    name: string;
    path: string;
    category?: string;
}

export interface CreateProps extends PageProps {
    user: User;
    recentProjects: Project[];
    starredProjects: Project[];
    sharedProjects: Project[];
    templates: Template[];
    readyTemplates?: ReadyTemplateItem[];
    isPusherConfigured: boolean;
    broadcastErrorMessage?: string | null;
    canCreateProject: boolean;
    cannotCreateReason: string | null;
    suggestions: string[];
    typingPrompts: string[];
    greeting: string;
    pendingRedirectUrl?: string | null;
}
