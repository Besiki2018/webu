import type {
    Subscription,
    Transaction,
    Plan,
    Plugin,
    SubscriptionStats,
    TransactionStats,
    PlanStats,
    SubscriptionFilters,
    TransactionFilters,
    PaginatedResponse,
} from './billing';
import type { PageProps } from './index';

export interface AdminUser {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'user';
    status: 'active' | 'inactive';
    plan_id?: number | null;
    plan?: { id: number; name: string } | null;
    projects_count: number;
    created_at: string;
    // Extended fields for subscription management
    active_subscription?: Subscription | null;
}

export interface AdminProjectOwner {
    id: number;
    name: string;
    email: string;
}

export interface AdminProjectTemplate {
    id: number;
    name: string;
    slug: string | null;
    is_system?: boolean;
}

export interface AdminProject {
    id: string;
    name: string;
    description: string | null;
    owner: AdminProjectOwner | null;
    is_public: boolean;
    build_status: string | null;
    is_published: boolean;
    published_at: string | null;
    subdomain: string | null;
    custom_domain: string | null;
    theme_preset: string | null;
    template: AdminProjectTemplate | null;
    deleted_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface AdminCmsSection {
    id: number;
    key: string;
    category: string;
    enabled: boolean;
    schema_json: Record<string, unknown>;
    meta: {
        label: string;
        description: string | null;
        design_variant: string | null;
        backend_updatable: boolean;
        bindings: Record<string, string>;
    };
    updated_at: string | null;
    created_at: string | null;
}

export interface AdminBookingTimelineEvent {
    id: number;
    event_type: string;
    event_key: string;
    occurred_at: string | null;
    created_by: number | null;
    payload_json: Record<string, unknown>;
}

export interface AdminBooking {
    id: number;
    booking_number: string;
    status: 'pending' | 'confirmed' | 'in_progress' | 'completed' | 'cancelled' | 'no_show';
    source: string | null;
    customer_name: string | null;
    customer_email: string | null;
    customer_phone: string | null;
    starts_at: string | null;
    ends_at: string | null;
    service: {
        id: number | null;
        name: string | null;
    };
    staff_resource: {
        id: number | null;
        name: string | null;
        type: string | null;
    };
    site: {
        id: string | null;
        name: string | null;
        subdomain: string | null;
        primary_domain: string | null;
    };
    project: {
        id: string | null;
        name: string | null;
        owner: {
            id: number;
            name: string;
            email: string;
        } | null;
    };
    events_count: number;
    timeline: AdminBookingTimelineEvent[];
    created_at: string | null;
    updated_at: string | null;
}

export interface PaginationData {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

// Re-export billing types for backwards compatibility
export type { Subscription, Transaction, Plan, Plugin } from './billing';

// Admin Page Props
export interface AdminSubscriptionsPageProps extends PageProps {
    subscriptions: PaginatedResponse<Subscription>;
    stats: SubscriptionStats;
    plans: Plan[];
    filters: SubscriptionFilters;
}

export interface AdminSubscriptionDetailsPageProps extends PageProps {
    subscription: Subscription;
}

export interface AdminTransactionsPageProps extends PageProps {
    transactions: PaginatedResponse<Transaction>;
    stats: TransactionStats;
    filters: TransactionFilters;
}

export interface AdminPlansPageProps extends PageProps {
    plans: Plan[];
    stats: PlanStats;
    filters: {
        search?: string;
        status?: string;
    };
}

export interface AdminPluginsPageProps extends PageProps {
    plugins: Plugin[];
}

export interface AdminProjectsPageProps extends PageProps {
    user: import('./index').User;
    projects: {
        data: AdminProject[];
    };
    pagination: PaginationData;
    filters: {
        search: string;
        state: 'active' | 'trashed' | 'all';
        owner_user_id: string;
        build_status: string;
        publish_status: 'all' | 'published' | 'unpublished';
        sort: 'updated_desc' | 'created_desc' | 'name_asc' | 'name_desc';
        per_page: number;
    };
    owners: AdminProjectOwner[];
    templates: AdminProjectTemplate[];
    build_status_options: string[];
}

export interface AdminCmsSectionsPageProps extends PageProps {
    user: import('./index').User;
    sections: AdminCmsSection[];
    categories: string[];
    filters: {
        search: string;
        category: string;
        status: 'all' | 'enabled' | 'disabled';
    };
    stats: {
        total: number;
        enabled: number;
        categories: number;
        preset_count: number;
    };
}

export interface AdminBookingsPageProps extends PageProps {
    user: import('./index').User;
    bookings: {
        data: AdminBooking[];
    };
    pagination: PaginationData;
    filters: {
        search: string;
        status: string;
        source: string;
        project_id: string;
        site_id: string;
        date_from: string;
        date_to: string;
        sort: 'starts_desc' | 'starts_asc' | 'updated_desc';
        per_page: number;
    };
    status_options: string[];
    source_options: string[];
    projects: Array<{
        id: string;
        name: string;
        owner_name: string | null;
        owner_email: string | null;
    }>;
    sites: Array<{
        id: string;
        project_id: string;
        name: string;
        subdomain: string | null;
        primary_domain: string | null;
    }>;
}

export interface Language {
    id: number;
    code: string;
    country_code: string;
    name: string;
    native_name: string;
    is_rtl: boolean;
    is_active: boolean;
    is_default: boolean;
    sort_order: number;
    created_at: string;
    updated_at: string;
}

export interface AdminLanguagesPageProps extends PageProps {
    languages: Language[];
    availableLocales: string[];
}

export interface Cronjob {
    name: string;
    class: string;
    command: string;
    schedule: string;
    cron: string;
    description: string;
    last_run: string | null;
    last_status: 'success' | 'failed' | 'running' | 'pending';
    next_run: string;
}

export interface CronLog {
    id: number;
    job_name: string;
    job_class: string;
    status: 'success' | 'failed' | 'running';
    started_at: string;
    completed_at: string | null;
    duration: number | null;
    human_duration: string;
    triggered_by: string;
    trigger_display: string;
    message: string | null;
    exception: string | null;
    created_at: string;
}

export interface CronLogsResponse {
    data: CronLog[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface OperationLogRecord {
    id: number;
    project_id: string | null;
    user_id: number | null;
    channel: 'build' | 'publish' | 'payment' | 'subscription' | 'booking' | 'system';
    event: string;
    status: 'info' | 'success' | 'warning' | 'error';
    source: string | null;
    domain: string | null;
    identifier: string | null;
    message: string | null;
    context: Record<string, unknown>;
    occurred_at: string | null;
    created_at: string | null;
    user: {
        id: number;
        name: string;
        email: string;
    } | null;
    project: {
        id: string;
        name: string;
    } | null;
}

export interface OperationLogsResponse {
    data: OperationLogRecord[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface AdminStats {
    total_users: number;
    active_subscriptions: number;
    revenue_mtd: number;
    total_projects: number;
}

// Overview Dashboard Types
export interface OverviewStats {
    total_users: number;
    active_subscriptions: number;
    mrr: number;
    revenue_mtd: number;
    total_projects: number;
}

export interface ChangeMetric {
    value: number;
    trend: 'up' | 'down' | 'neutral';
}

export interface ChangeMetrics {
    users: ChangeMetric;
    subscriptions: ChangeMetric;
    revenue: ChangeMetric;
    projects: ChangeMetric;
}

export interface RecentUser {
    id: number;
    name: string;
    email: string;
    created_at: string;
}

export interface RecentTransaction {
    id: string;
    user: string;
    amount: number;
    status: 'completed' | 'pending' | 'failed' | 'refunded';
    created_at: string;
}

export interface SubscriptionDistributionItem {
    name: string;
    count: number;
    color: string;
}

export interface RevenueByMethod {
    method: string;
    amount: number;
}

export interface AiUsageStats {
    total_tokens: number;
    estimated_cost: number;
    request_count: number;
    unique_users: number;
    own_key_users: number;
    platform_users: number;
}

export interface AiUsageByProvider {
    provider: string;
    tokens: number;
    cost: number;
}

export interface AiUsageTrendItem {
    date: string;
    tokens: number;
    cost: number;
}

export interface AiTokenAvailability {
    platform_remaining_tokens: number;
    platform_overage_tokens: number;
    users_with_token_balance: number;
    users_with_unlimited_tokens: number;
    users_using_own_keys: number;
    active_providers: number;
    connected_providers: number;
}

export interface AiProviderLimit {
    provider_id: number;
    provider: string;
    status: 'active' | 'inactive';
    has_credentials: boolean;
    default_model: string;
    available_models_count: number;
    max_tokens_per_request: number;
    summarizer_max_tokens: number;
    input_cost_per_1m: number;
    output_cost_per_1m: number;
    month_tokens: number;
    month_cost: number;
    month_requests: number;
}

export interface AiSiteSpendItem {
    project_id: string | null;
    project_name: string;
    site_id: string | null;
    site_name: string | null;
    primary_domain: string | null;
    subdomain: string | null;
    owner_id: number | null;
    owner_name: string;
    owner_email: string | null;
    prompt_tokens: number;
    completion_tokens: number;
    total_tokens: number;
    total_cost: number;
    request_count: number;
    avg_tokens_per_request: number;
    avg_cost_per_request: number;
    last_used_at: string | null;
}

export interface AiRecentUsageItem {
    id: number;
    builder_event_id: string | null;
    action: string;
    model: string | null;
    prompt_tokens: number;
    completion_tokens: number;
    total_tokens: number;
    estimated_cost: number;
    used_own_api_key: boolean;
    created_at: string | null;
    user_id: number | null;
    user_name: string;
    user_email: string | null;
    project_id: string | null;
    project_name: string | null;
    site_id: string | null;
    site_name: string | null;
    primary_domain: string | null;
    subdomain: string | null;
    provider: string;
}

export interface AiSpendControlStats {
    availability: AiTokenAvailability;
    provider_limits: AiProviderLimit[];
    site_spend: AiSiteSpendItem[];
    recent_usage: AiRecentUsageItem[];
}

export interface ReferralStats {
    total: number;
    converted: number;
    credited: number;
    commission_paid: number;
    pending_earnings: number;
}

export interface TrendDataItem {
    date: string;
    value: number;
}

export interface TrendData {
    revenue: TrendDataItem[];
    users: TrendDataItem[];
    projects: TrendDataItem[];
}

export interface StorageByType {
    type: string;
    size_bytes: number;
    count: number;
}

export interface TopStorageUser {
    id: number;
    name: string;
    email: string;
    storage_bytes: number;
}

export interface StorageStats {
    total_storage_bytes: number;
    total_files: number;
    projects_with_files: number;
    top_users: TopStorageUser[];
    storage_by_type: StorageByType[];
}

export interface FirebaseConnectionStatus {
    connected: boolean;
    error: string | null;
}

export interface FirebaseStats {
    system_configured: boolean;
    system_status: FirebaseConnectionStatus;
    admin_sdk_configured: boolean;
    admin_sdk_status: FirebaseConnectionStatus;
    projects_using_firebase: number;
    projects_with_custom_firebase: number;
    projects_with_admin_sdk: number;
}

export interface CmsModuleStats {
    sites_total: number;
    pages_total: number;
    media_assets_total: number;
    media_assets_bytes: number;
    blog_posts_total: number;
    booking_services_total: number;
    bookings_total: number;
    ecommerce_products_total: number;
    ecommerce_orders_total: number;
}

export interface OverviewPageProps extends PageProps {
    stats: OverviewStats;
    changes: ChangeMetrics;
    recentUsers: RecentUser[];
    recentTransactions: RecentTransaction[];
    subscriptionDistribution: SubscriptionDistributionItem[];
    revenueByPaymentMethod: RevenueByMethod[];
    aiUsage: AiUsageStats;
    aiUsageByProvider: AiUsageByProvider[];
    aiUsageTrend: AiUsageTrendItem[];
    aiSpendControl: AiSpendControlStats;
    referralStats: ReferralStats;
    storageStats: StorageStats;
    cmsModuleStats: CmsModuleStats;
    firebaseStats: FirebaseStats;
    trends: TrendData;
}

// Builder types
export interface Builder {
    id: number;
    name: string;
    url: string;
    port: number;
    server_key: string;
    status: 'active' | 'inactive';
    max_iterations: number;
    last_triggered_at: string | null;
    created_at: string;
    updated_at: string;
    projects_count?: number;
}

export interface BuilderDetails {
    version: string;
    sessions: number;
    online: boolean;
}

// AI Provider types
export type AiProviderType = 'openai' | 'anthropic' | 'claude' | 'grok' | 'deepseek' | 'zhipu';

export interface AiProvider {
    id: number;
    name: string;
    type: AiProviderType;
    type_label: string;
    status: 'active' | 'inactive';
    has_credentials: boolean;
    available_models: string[];
    config: AiProviderConfig;
    plans_count?: number;
    total_requests: number;
    last_used_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface AiProviderConfig {
    base_url?: string;
    default_model?: string;
    max_tokens?: number;
    summarizer_max_tokens?: number;
    organization_id?: string;
    provider_type?: AiProviderType;
}

export interface AiProviderFormData {
    name: string;
    type: AiProviderType;
    api_key?: string;
    base_url?: string;
    default_model?: string;
    max_tokens?: number;
    summarizer_max_tokens?: number;
    available_models?: string[];
    provider_type?: AiProviderType;
}
