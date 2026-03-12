import { FormEvent, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import axios, { AxiosError } from 'axios';
import { toast } from 'sonner';
import {
    Avatar,
    AvatarFallback,
} from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { RichTextField } from '@/components/ui/rich-text-field';
import {
    Banknote,
    ArrowLeft,
    CheckCircle2,
    Copy,
    CreditCard,
    Eye,
    Mail,
    Gift,
    Gem,
    Home,
    Landmark,
    Loader2,
    Package,
    Palette,
    Pencil,
    MoreVertical,
    Phone,
    RefreshCw,
    Receipt,
    Search,
    Shirt,
    Sparkles,
    Tag,
    Trash2,
    Truck,
    Upload,
    Users,
    UserCheck,
    UserPlus,
    UserX,
    Wallet,
    Smartphone,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useTranslation } from '@/contexts/LanguageContext';
import { CmsEcommerceProductManager } from '@/components/Project/CmsEcommerceProductManager';
import { CmsEcommerceMetadataPanel } from '@/components/Project/CmsEcommerceMetadataPanel';

interface CmsEcommercePanelProps {
    siteId: string;
    activeSection?: EcommerceTab;
    productMode?: 'all' | 'create';
    hideTabs?: boolean;
}

interface ApiErrorPayload {
    message?: string;
    error?: string;
    errors?: Record<string, string[]>;
}

interface MediaUploadResponse {
    message?: string;
    media: {
        id: number;
        path: string;
        asset_url: string;
    };
}

type EcommerceTab =
    | 'customers'
    | 'categories'
    | 'discounts'
    | 'products'
    | 'attributes'
    | 'attributeValues'
    | 'variants'
    | 'inventory'
    | 'orders'
    | 'paymentProviders'
    | 'shippingProviders';
type CategoryStatus = 'active' | 'inactive';
type ProductStatus = 'draft' | 'active' | 'archived';
type OrderStatus = 'pending' | 'paid' | 'processing' | 'shipped' | 'completed' | 'cancelled' | 'failed' | 'refunded';
type PaymentStatus = 'unpaid' | 'paid' | 'failed' | 'refunded' | 'partially_refunded';
type FulfillmentStatus = 'unfulfilled' | 'partial' | 'fulfilled' | 'returned' | 'cancelled';
type ProviderAvailability = 'inherit' | 'enabled' | 'disabled';
type ProviderMode = 'sandbox' | 'live' | null;
type ProviderConfigFieldType = 'text' | 'password' | 'textarea' | 'toggle' | 'select';
type DiscountType = 'percent' | 'fixed';
type DiscountStatus = 'draft' | 'active' | 'inactive';
type DiscountScope = 'all_products' | 'specific_products' | 'categories';

interface EcommerceCategory {
    id: number;
    site_id: string;
    name: string;
    slug: string;
    description: string | null;
    status: CategoryStatus;
    sort_order: number;
    meta_json: Record<string, unknown>;
    created_at: string | null;
    updated_at: string | null;
}

interface EcommerceProduct {
    id: number;
    site_id: string;
    category_id: number | null;
    category_name: string | null;
    name: string;
    slug: string;
    sku: string | null;
    short_description: string | null;
    description: string | null;
    price: string;
    compare_at_price: string | null;
    currency: string;
    status: ProductStatus;
    stock_tracking: boolean;
    stock_quantity: number;
    allow_backorder: boolean;
    is_digital: boolean;
    weight_grams: number | null;
    attributes_json: Record<string, unknown>;
    seo_title: string | null;
    seo_description: string | null;
    published_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}

interface EcommerceOrderPaymentSummary {
    id: number;
    provider: string | null;
    status: string;
    amount: string;
    currency: string;
    is_installment: boolean;
    created_at: string | null;
}

interface EcommerceOrderSummary {
    id: number;
    site_id: string;
    order_number: string;
    status: OrderStatus;
    payment_status: PaymentStatus;
    fulfillment_status: FulfillmentStatus;
    currency: string;
    customer_name: string | null;
    customer_email: string | null;
    customer_phone: string | null;
    subtotal: string;
    tax_total: string;
    shipping_total: string;
    discount_total: string;
    grand_total: string;
    paid_total: string;
    outstanding_total: string;
    placed_at: string | null;
    paid_at: string | null;
    cancelled_at: string | null;
    items_count: number;
    shipments_count: number;
    payments: EcommerceOrderPaymentSummary[];
    created_at: string | null;
    updated_at: string | null;
}

interface EcommerceOrderItem {
    id: number;
    product_id: number | null;
    variant_id: number | null;
    name: string;
    sku: string | null;
    quantity: number;
    unit_price: string;
    tax_amount: string;
    discount_amount: string;
    line_total: string;
    options_json: Record<string, unknown>;
    meta_json: Record<string, unknown>;
}

interface EcommerceOrderPaymentDetail {
    id: number;
    provider: string | null;
    status: string;
    method: string | null;
    transaction_reference: string | null;
    amount: string;
    currency: string;
    is_installment: boolean;
    installment_plan_json: Record<string, unknown>;
    raw_payload_json: Record<string, unknown>;
    processed_at: string | null;
    created_at: string | null;
}

interface EcommerceShipmentEvent {
    id: number;
    event_type: string;
    status: string | null;
    message: string | null;
    payload_json: Record<string, unknown>;
    occurred_at: string | null;
    created_at: string | null;
}

interface EcommerceShipment {
    id: number;
    provider_slug: string;
    shipment_reference: string;
    tracking_number: string | null;
    tracking_url: string | null;
    status: string;
    shipped_at: string | null;
    delivered_at: string | null;
    cancelled_at: string | null;
    last_tracked_at: string | null;
    meta_json: Record<string, unknown>;
    events: EcommerceShipmentEvent[];
}

interface EcommerceOrderDetail extends EcommerceOrderSummary {
    billing_address_json: Record<string, unknown>;
    shipping_address_json: Record<string, unknown>;
    notes: string | null;
    meta_json: Record<string, unknown>;
    items: EcommerceOrderItem[];
    payments: EcommerceOrderPaymentDetail[];
    shipments: EcommerceShipment[];
}

interface EcommerceCategoryListResponse {
    site_id: string;
    categories: EcommerceCategory[];
}

interface EcommerceProductListResponse {
    site_id: string;
    products: EcommerceProduct[];
}

interface EcommerceOrderListResponse {
    site_id: string;
    orders: EcommerceOrderSummary[];
}

interface EcommerceOrderDetailResponse {
    site_id: string;
    order: EcommerceOrderDetail;
}

interface EcommerceDiscount {
    id: number;
    site_id: string;
    name: string;
    code: string | null;
    type: DiscountType;
    value: string;
    status: DiscountStatus;
    scope: DiscountScope;
    product_ids_json: number[];
    category_ids_json: number[];
    starts_at: string | null;
    ends_at: string | null;
    notes: string | null;
    meta_json: Record<string, unknown>;
    created_at: string | null;
    updated_at: string | null;
}

interface EcommerceDiscountListResponse {
    site_id: string;
    discounts: EcommerceDiscount[];
}

interface EcommerceDiscountMutationResponse {
    message: string;
    discount: EcommerceDiscount;
}

interface EcommerceDiscountBulkApplyResponse {
    message: string;
    affected_products: number;
}

interface EcommerceAccountingEntryLine {
    id: number;
    line_no: number;
    account_code: string;
    account_name: string;
    side: 'debit' | 'credit';
    amount: string;
    currency: string;
    description: string | null;
    meta_json: Record<string, unknown>;
}

interface EcommerceAccountingEntryItem {
    id: number;
    site_id: string;
    order_id: number | null;
    order_number: string | null;
    order_payment_id: number | null;
    payment_provider: string | null;
    event_type: string;
    event_key: string;
    currency: string;
    total_debit: string;
    total_credit: string;
    difference: string;
    description: string | null;
    meta_json: Record<string, unknown>;
    created_by: number | null;
    created_by_name: string | null;
    occurred_at: string | null;
    created_at: string | null;
    lines: EcommerceAccountingEntryLine[];
}

interface EcommerceAccountingEntriesResponse {
    site_id: string;
    filters: {
        order_id: number | null;
        event_type: string | null;
        limit: number;
    };
    summary: {
        entries_count: number;
        total_debit: string;
        total_credit: string;
        difference: string;
        is_balanced: boolean;
    };
    entries: EcommerceAccountingEntryItem[];
}

interface EcommerceAccountingReconciliationAccount {
    account_code: string;
    account_name: string;
    debit_total: string;
    credit_total: string;
    net: string;
}

interface EcommerceAccountingReconciliationResponse {
    site_id: string;
    filters: {
        order_id: number | null;
    };
    summary: {
        entries_count: number;
        total_debit: string;
        total_credit: string;
        difference: string;
        is_balanced: boolean;
        accounts_receivable_net: string;
        bookings_outstanding_total?: string;
        invoices_outstanding_total?: string;
        outstanding_gap?: string;
    };
    accounts: EcommerceAccountingReconciliationAccount[];
}

interface ProviderConfigField {
    name: string;
    label: string;
    type: ProviderConfigFieldType;
    required?: boolean;
    default?: string | boolean;
    placeholder?: string;
    help?: string;
    rows?: number;
    options?: { value: string; label: string }[];
}

interface EcommercePaymentProvider {
    slug: string;
    name: string;
    description: string;
    icon: string | null;
    availability: ProviderAvailability;
    is_active: boolean;
    is_enabled: boolean;
    is_configured: boolean;
    admin_default_configured: boolean;
    supports_installment: boolean;
    mode: ProviderMode;
    config_schema: ProviderConfigField[];
    site_config: Record<string, string | boolean>;
    callbacks: {
        webhook_url: string;
        callback_url: string;
    };
    updated_at: string | null;
    updated_by: number | null;
    site_id: string;
}

interface EcommercePaymentProviderListResponse {
    site_id: string;
    providers: EcommercePaymentProvider[];
}

interface EcommercePaymentProviderUpdateResponse {
    message: string;
    site_id: string;
    provider: EcommercePaymentProvider;
}

interface EcommerceShippingCourier {
    slug: string;
    name: string;
    description: string;
    icon: string | null;
    availability: ProviderAvailability;
    is_active: boolean;
    is_enabled: boolean;
    is_configured: boolean;
    admin_default_configured: boolean;
    supports_tracking: boolean;
    supported_countries: string[];
    mode: ProviderMode;
    config_schema: ProviderConfigField[];
    site_config: Record<string, string | boolean>;
    updated_at: string | null;
    updated_by: number | null;
    site_id: string;
}

interface EcommerceShippingCourierListResponse {
    site_id: string;
    couriers: EcommerceShippingCourier[];
}

interface EcommerceShippingCourierUpdateResponse {
    message: string;
    site_id: string;
    courier: EcommerceShippingCourier;
}

interface EcommerceInventorySummary {
    items_count: number;
    on_hand_total: number;
    reserved_total: number;
    available_total: number;
    low_stock_count: number;
}

type InventoryLocationStatus = 'active' | 'inactive';

interface EcommerceInventoryLocation {
    id: number;
    site_id: string;
    key: string;
    name: string;
    status: InventoryLocationStatus;
    is_default: boolean;
    notes: string | null;
    items_count: number;
    on_hand_total: number;
    reserved_total: number;
    available_total: number;
    created_at: string | null;
    updated_at: string | null;
}

interface EcommerceInventoryItem {
    id: number;
    site_id: string;
    product_id: number;
    product_name: string | null;
    product_slug: string | null;
    variant_id: number | null;
    variant_name: string | null;
    sku: string | null;
    location_id: number | null;
    location_key: string | null;
    location_name: string | null;
    location_status: string | null;
    quantity_on_hand: number;
    quantity_reserved: number;
    available_quantity: number;
    low_stock_threshold: number | null;
    is_low_stock: boolean;
    stock_tracking: boolean;
    allow_backorder: boolean;
    updated_at: string | null;
}

interface EcommerceInventoryMovement {
    id: number;
    inventory_item_id: number;
    product_id: number | null;
    product_name: string | null;
    variant_id: number | null;
    variant_name: string | null;
    sku: string | null;
    movement_type: string;
    reason: string | null;
    quantity_delta: number;
    reserved_delta: number;
    quantity_on_hand_before: number;
    quantity_on_hand_after: number;
    quantity_reserved_before: number;
    quantity_reserved_after: number;
    meta_json: Record<string, unknown>;
    created_by: number | null;
    created_by_name: string | null;
    created_at: string | null;
}

interface EcommerceInventoryDashboardResponse {
    site_id: string;
    summary: EcommerceInventorySummary;
    locations: EcommerceInventoryLocation[];
    inventory_items: EcommerceInventoryItem[];
    low_stock_items: EcommerceInventoryItem[];
    recent_movements: EcommerceInventoryMovement[];
}

interface EcommerceInventoryLocationMutationResponse {
    message: string;
    site_id: string;
    location: EcommerceInventoryLocation;
}

interface EcommerceInventoryItemMutationResponse {
    message: string;
    site_id: string;
    inventory_item: EcommerceInventoryItem;
}

interface CategoryFormState {
    name: string;
    slug: string;
    description: string;
    imageUrl: string;
    iconKey: string;
    status: CategoryStatus;
    sortOrder: string;
}

interface ProductFormState {
    categoryId: string;
    name: string;
    slug: string;
    sku: string;
    shortDescription: string;
    description: string;
    price: string;
    compareAtPrice: string;
    currency: string;
    status: ProductStatus;
    stockTracking: boolean;
    stockQuantity: string;
    allowBackorder: boolean;
    isDigital: boolean;
    weightGrams: string;
    attributesJson: string;
    seoTitle: string;
    seoDescription: string;
}

interface OrderUpdateFormState {
    status: OrderStatus;
    paymentStatus: PaymentStatus;
    fulfillmentStatus: FulfillmentStatus;
    notes: string;
}

interface OrderRowStatusFormState {
    status: OrderStatus;
    paymentStatus: PaymentStatus;
    fulfillmentStatus: FulfillmentStatus;
}

interface CreateShipmentFormState {
    providerSlug: string;
    shipmentReference: string;
    trackingNumber: string;
    trackingUrl: string;
}

interface ProviderFormState {
    availability: ProviderAvailability;
    config: Record<string, string | boolean>;
}

interface CourierFormState {
    availability: ProviderAvailability;
    config: Record<string, string | boolean>;
}

interface InventoryLocationFormState {
    key: string;
    name: string;
    status: InventoryLocationStatus;
    isDefault: boolean;
    notes: string;
}

interface InventoryItemFormState {
    locationId: string;
    lowStockThreshold: string;
    quantityDelta: string;
    adjustmentReason: string;
    countedQuantity: string;
}

interface DiscountFormState {
    name: string;
    code: string;
    type: DiscountType;
    value: string;
    status: DiscountStatus;
    scope: DiscountScope;
    productIds: number[];
    startsAt: string;
    endsAt: string;
    notes: string;
}

interface BulkDiscountFormState {
    type: DiscountType;
    value: string;
    createRecord: boolean;
    name: string;
    code: string;
    status: DiscountStatus;
}

const CATEGORY_STATUS_OPTIONS: CategoryStatus[] = ['active', 'inactive'];
const PRODUCT_STATUS_OPTIONS: ProductStatus[] = ['draft', 'active', 'archived'];
const DISCOUNT_TYPE_OPTIONS: DiscountType[] = ['percent', 'fixed'];
const DISCOUNT_STATUS_OPTIONS: DiscountStatus[] = ['draft', 'active', 'inactive'];
const DISCOUNT_SCOPE_OPTIONS: DiscountScope[] = ['all_products', 'specific_products', 'categories'];
const ORDER_STATUS_OPTIONS: OrderStatus[] = ['pending', 'processing', 'paid', 'shipped', 'completed', 'failed', 'cancelled', 'refunded'];
const PAYMENT_STATUS_OPTIONS: PaymentStatus[] = ['unpaid', 'paid', 'failed', 'partially_refunded', 'refunded'];
const FULFILLMENT_STATUS_OPTIONS: FulfillmentStatus[] = ['unfulfilled', 'partial', 'fulfilled', 'returned', 'cancelled'];
const PROVIDER_AVAILABILITY_OPTIONS: ProviderAvailability[] = ['inherit', 'enabled', 'disabled'];
const INVENTORY_LOCATION_STATUS_OPTIONS: InventoryLocationStatus[] = ['active', 'inactive'];

const PAYMENT_PROVIDER_ICON_MAP: Record<string, { icon: LucideIcon; accentClass: string }> = {
    'bank-of-georgia': { icon: Landmark, accentClass: 'bg-amber-100 text-amber-700 border-amber-200' },
    'bank-transfer': { icon: Landmark, accentClass: 'bg-slate-100 text-slate-700 border-slate-200' },
    fleet: { icon: Wallet, accentClass: 'bg-blue-100 text-blue-700 border-blue-200' },
    flitt: { icon: Wallet, accentClass: 'bg-blue-100 text-blue-700 border-blue-200' },
    paypal: { icon: CreditCard, accentClass: 'bg-sky-100 text-sky-700 border-sky-200' },
    stripe: { icon: CreditCard, accentClass: 'bg-violet-100 text-violet-700 border-violet-200' },
    tbc: { icon: Landmark, accentClass: 'bg-emerald-100 text-emerald-700 border-emerald-200' },
    bog: { icon: Landmark, accentClass: 'bg-amber-100 text-amber-700 border-amber-200' },
    manual: { icon: Receipt, accentClass: 'bg-zinc-100 text-zinc-700 border-zinc-200' },
    'cash-on-delivery': { icon: Banknote, accentClass: 'bg-lime-100 text-lime-700 border-lime-200' },
};
const SHIPPING_COURIER_ICON_MAP: Record<string, { icon: LucideIcon; accentClass: string }> = {
    onway: { icon: Truck, accentClass: 'bg-blue-100 text-blue-700 border-blue-200' },
    'manual-courier': { icon: Package, accentClass: 'bg-slate-100 text-slate-700 border-slate-200' },
    manual: { icon: Package, accentClass: 'bg-slate-100 text-slate-700 border-slate-200' },
    pickup: { icon: Home, accentClass: 'bg-emerald-100 text-emerald-700 border-emerald-200' },
};

function createShipmentFormState(defaultProviderSlug: string = ''): CreateShipmentFormState {
    return {
        providerSlug: defaultProviderSlug,
        shipmentReference: '',
        trackingNumber: '',
        trackingUrl: '',
    };
}

function createOrderRowStatusFormState(order: EcommerceOrderSummary): OrderRowStatusFormState {
    return {
        status: order.status,
        paymentStatus: order.payment_status,
        fulfillmentStatus: order.fulfillment_status,
    };
}

function createCategoryFormState(): CategoryFormState {
    return {
        name: '',
        slug: '',
        description: '',
        imageUrl: '',
        iconKey: '',
        status: 'active',
        sortOrder: '0',
    };
}

const CATEGORY_ICON_OPTIONS: Array<{ key: string; label: string; icon: LucideIcon }> = [
    { key: 'tag', label: 'Tag', icon: Tag },
    { key: 'package', label: 'Package', icon: Package },
    { key: 'shirt', label: 'Fashion', icon: Shirt },
    { key: 'gem', label: 'Jewelry', icon: Gem },
    { key: 'smartphone', label: 'Tech', icon: Smartphone },
    { key: 'home', label: 'Home', icon: Home },
    { key: 'gift', label: 'Gift', icon: Gift },
    { key: 'palette', label: 'Beauty', icon: Palette },
    { key: 'sparkles', label: 'New', icon: Sparkles },
];

const CATEGORY_ICON_MAP: Record<string, LucideIcon> = CATEGORY_ICON_OPTIONS.reduce((carry, item) => {
    carry[item.key] = item.icon;
    return carry;
}, {} as Record<string, LucideIcon>);

function createProductFormState(): ProductFormState {
    return {
        categoryId: '',
        name: '',
        slug: '',
        sku: '',
        shortDescription: '',
        description: '',
        price: '0',
        compareAtPrice: '',
        currency: 'GEL',
        status: 'draft',
        stockTracking: true,
        stockQuantity: '0',
        allowBackorder: false,
        isDigital: false,
        weightGrams: '',
        attributesJson: '{}',
        seoTitle: '',
        seoDescription: '',
    };
}

function createDiscountFormState(): DiscountFormState {
    return {
        name: '',
        code: '',
        type: 'percent',
        value: '10',
        status: 'draft',
        scope: 'specific_products',
        productIds: [],
        startsAt: '',
        endsAt: '',
        notes: '',
    };
}

function createBulkDiscountFormState(): BulkDiscountFormState {
    return {
        type: 'percent',
        value: '10',
        createRecord: false,
        name: '',
        code: '',
        status: 'active',
    };
}

function createProviderFormState(provider: EcommercePaymentProvider): ProviderFormState {
    return {
        availability: provider.availability,
        config: { ...(provider.site_config ?? {}) },
    };
}

function createCourierFormState(courier: EcommerceShippingCourier): CourierFormState {
    return {
        availability: courier.availability,
        config: { ...(courier.site_config ?? {}) },
    };
}

function createInventoryLocationFormState(): InventoryLocationFormState {
    return {
        key: '',
        name: '',
        status: 'active',
        isDefault: false,
        notes: '',
    };
}

function createInventoryItemFormState(item: EcommerceInventoryItem): InventoryItemFormState {
    return {
        locationId: item.location_id !== null ? String(item.location_id) : '',
        lowStockThreshold: item.low_stock_threshold !== null ? String(item.low_stock_threshold) : '',
        quantityDelta: '',
        adjustmentReason: '',
        countedQuantity: String(item.quantity_on_hand),
    };
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

const GEORGIAN_TO_LATIN_MAP: Record<string, string> = {
    ა: 'a', ბ: 'b', გ: 'g', დ: 'd', ე: 'e', ვ: 'v', ზ: 'z',
    თ: 'th', ი: 'i', კ: 'k', ლ: 'l', მ: 'm', ნ: 'n', ო: 'o',
    პ: 'p', ჟ: 'zh', რ: 'r', ს: 's', ტ: 't', უ: 'u', ფ: 'f',
    ქ: 'q', ღ: 'gh', ყ: 'y', შ: 'sh', ჩ: 'ch', ც: 'ts', ძ: 'dz',
    წ: 'ts', ჭ: 'tch', ხ: 'kh', ჯ: 'j', ჰ: 'h',
};

function slugify(value: string): string {
    const transliterated = value
        .toLowerCase()
        .split('')
        .map((char) => GEORGIAN_TO_LATIN_MAP[char] ?? char)
        .join('')
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '');

    return transliterated
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
}

function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString();
}

function toDateTimeInputValue(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function formatCurrencyAmount(value: string | number | null | undefined, currency: string = 'GEL'): string {
    const numeric = typeof value === 'number' ? value : Number.parseFloat(String(value ?? '0'));
    if (!Number.isFinite(numeric)) {
        return `${value ?? '0'} ${currency}`;
    }

    const normalizedCurrency = currency.trim().toUpperCase() || 'GEL';
    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: normalizedCurrency,
            maximumFractionDigits: 2,
        }).format(numeric);
    } catch {
        return `${numeric.toFixed(2)} ${normalizedCurrency}`;
    }
}

function parseOptionalNonNegativeInteger(input: string): number | null {
    const trimmed = input.trim();
    if (trimmed === '') {
        return null;
    }

    const parsed = Number.parseInt(trimmed, 10);
    if (!Number.isFinite(parsed) || parsed < 0) {
        return null;
    }

    return parsed;
}

function parseInteger(input: string): number | null {
    const trimmed = input.trim();
    if (trimmed === '') {
        return null;
    }

    const parsed = Number.parseInt(trimmed, 10);
    if (!Number.isFinite(parsed)) {
        return null;
    }

    return parsed;
}

function parseOptionalNonNegativeDecimal(input: string): number | null {
    const trimmed = input.trim();
    if (trimmed === '') {
        return null;
    }

    const parsed = Number.parseFloat(trimmed);
    if (!Number.isFinite(parsed) || parsed < 0) {
        return null;
    }

    return parsed;
}

function parseJsonRecord(input: string): { value: Record<string, unknown> | null; error: string | null } {
    const trimmed = input.trim();
    if (trimmed === '') {
        return { value: {}, error: null };
    }

    try {
        const parsed: unknown = JSON.parse(trimmed);
        if (!isRecord(parsed)) {
            return { value: null, error: 'Expected a JSON object.' };
        }

        return { value: parsed, error: null };
    } catch {
        return { value: null, error: 'Invalid JSON.' };
    }
}

function toPrettyJson(value: unknown): string {
    try {
        return JSON.stringify(value ?? {}, null, 2);
    } catch {
        return '{}';
    }
}

function getApiErrorMessage(error: unknown, fallback: string): string {
    const axiosError = error as AxiosError<ApiErrorPayload>;
    const payload = axiosError.response?.data;

    if (payload?.error) {
        return payload.error;
    }

    if (payload?.message) {
        return payload.message;
    }

    const firstValidationError = payload?.errors
        ? Object.values(payload.errors).flat()[0]
        : null;

    return firstValidationError ?? fallback;
}

function normalizeAddress(value: unknown): string {
    if (!isRecord(value)) {
        return '—';
    }

    const entries = Object.entries(value)
        .map(([key, fieldValue]) => `${key}: ${String(fieldValue ?? '')}`.trim())
        .filter((line) => line !== '' && !line.endsWith(':'));

    return entries.length > 0 ? entries.join(', ') : '—';
}

function statusBadgeVariant(status: string): 'default' | 'secondary' | 'outline' {
    switch (status) {
        case 'active':
        case 'paid':
        case 'completed':
        case 'fulfilled':
            return 'default';
        case 'failed':
        case 'cancelled':
        case 'archived':
            return 'secondary';
        default:
            return 'outline';
    }
}

function textInitials(value: string | null | undefined): string {
    const tokens = String(value ?? '').trim().split(/\s+/).filter(Boolean);
    if (tokens.length === 0) {
        return 'CU';
    }
    return tokens.slice(0, 2).map((token) => token[0]?.toUpperCase() ?? '').join('');
}

function orderStatusBadgeVariant(status: OrderStatus): 'success' | 'warning' | 'secondary' | 'outline' | 'default' {
    switch (status) {
        case 'completed':
        case 'paid':
            return 'success';
        case 'processing':
        case 'shipped':
            return 'default';
        case 'pending':
            return 'warning';
        case 'cancelled':
        case 'failed':
        case 'refunded':
            return 'secondary';
        default:
            return 'outline';
    }
}

function paymentStatusBadgeVariant(status: PaymentStatus): 'success' | 'warning' | 'secondary' | 'outline' {
    switch (status) {
        case 'paid':
            return 'success';
        case 'unpaid':
            return 'warning';
        case 'failed':
        case 'refunded':
            return 'secondary';
        default:
            return 'outline';
    }
}

function fulfillmentStatusBadgeVariant(status: FulfillmentStatus): 'success' | 'warning' | 'secondary' | 'outline' | 'info' {
    switch (status) {
        case 'fulfilled':
            return 'success';
        case 'partial':
            return 'info';
        case 'unfulfilled':
            return 'warning';
        case 'returned':
        case 'cancelled':
            return 'secondary';
        default:
            return 'outline';
    }
}

export function CmsEcommercePanel({ siteId, activeSection, productMode = 'all', hideTabs = false }: CmsEcommercePanelProps) {
    const { t } = useTranslation();
    const initialCoreLoadedForSiteRef = useRef<string | null>(null);
    const categoryImageUploadRef = useRef<HTMLInputElement | null>(null);

    const [activeTab, setActiveTab] = useState<EcommerceTab>(activeSection ?? 'products');

    const [categories, setCategories] = useState<EcommerceCategory[]>([]);
    const [isCategoriesLoading, setIsCategoriesLoading] = useState(false);
    const [isSavingCategory, setIsSavingCategory] = useState(false);
    const [isUploadingCategoryImage, setIsUploadingCategoryImage] = useState(false);
    const [editingCategoryId, setEditingCategoryId] = useState<number | null>(null);
    const [deletingCategoryId, setDeletingCategoryId] = useState<number | null>(null);
    const [categoryForm, setCategoryForm] = useState<CategoryFormState>(createCategoryFormState());

    const [products, setProducts] = useState<EcommerceProduct[]>([]);
    const [isProductsLoading, setIsProductsLoading] = useState(false);
    const [isSavingProduct, setIsSavingProduct] = useState(false);
    const [editingProductId, setEditingProductId] = useState<number | null>(null);
    const [deletingProductId, setDeletingProductId] = useState<number | null>(null);
    const [productForm, setProductForm] = useState<ProductFormState>(createProductFormState());
    const [discounts, setDiscounts] = useState<EcommerceDiscount[]>([]);
    const [isDiscountsLoading, setIsDiscountsLoading] = useState(false);
    const [isSavingDiscount, setIsSavingDiscount] = useState(false);
    const [editingDiscountId, setEditingDiscountId] = useState<number | null>(null);
    const [deletingDiscountId, setDeletingDiscountId] = useState<number | null>(null);
    const [discountForm, setDiscountForm] = useState<DiscountFormState>(createDiscountFormState());
    const [bulkDiscountForm, setBulkDiscountForm] = useState<BulkDiscountFormState>(createBulkDiscountFormState());
    const [bulkSelectedProductIds, setBulkSelectedProductIds] = useState<number[]>([]);
    const [isApplyingBulkDiscount, setIsApplyingBulkDiscount] = useState(false);
    const [inventorySummary, setInventorySummary] = useState<EcommerceInventorySummary | null>(null);
    const [inventoryLocations, setInventoryLocations] = useState<EcommerceInventoryLocation[]>([]);
    const [inventoryItems, setInventoryItems] = useState<EcommerceInventoryItem[]>([]);
    const [lowStockItems, setLowStockItems] = useState<EcommerceInventoryItem[]>([]);
    const [inventoryMovements, setInventoryMovements] = useState<EcommerceInventoryMovement[]>([]);
    const [inventoryItemForms, setInventoryItemForms] = useState<Record<number, InventoryItemFormState>>({});
    const [isInventoryLoading, setIsInventoryLoading] = useState(false);
    const [isSavingInventoryLocation, setIsSavingInventoryLocation] = useState(false);
    const [editingInventoryLocationId, setEditingInventoryLocationId] = useState<number | null>(null);
    const [inventoryLocationForm, setInventoryLocationForm] = useState<InventoryLocationFormState>(createInventoryLocationFormState());
    const [savingInventoryItemSettingsId, setSavingInventoryItemSettingsId] = useState<number | null>(null);
    const [adjustingInventoryItemId, setAdjustingInventoryItemId] = useState<number | null>(null);
    const [stocktakingInventoryItemId, setStocktakingInventoryItemId] = useState<number | null>(null);

    const [orders, setOrders] = useState<EcommerceOrderSummary[]>([]);
    const [isOrdersLoading, setIsOrdersLoading] = useState(false);
    const [orderRowForms, setOrderRowForms] = useState<Record<number, OrderRowStatusFormState>>({});
    const [savingOrderRowId, setSavingOrderRowId] = useState<number | null>(null);
    const [deletingOrderId, setDeletingOrderId] = useState<number | null>(null);
    const [selectedOrderId, setSelectedOrderId] = useState<number | null>(null);
    const [ordersView, setOrdersView] = useState<'list' | 'details'>(() => {
        if (typeof window === 'undefined') {
            return 'list';
        }
        const orderId = new URLSearchParams(window.location.search).get('order_id');
        return orderId ? 'details' : 'list';
    });
    const [orderSearchQuery, setOrderSearchQuery] = useState('');
    const [orderStatusFilter, setOrderStatusFilter] = useState<'all' | OrderStatus>('all');
    const [orderPaymentFilter, setOrderPaymentFilter] = useState<'all' | PaymentStatus>('all');
    const [customerSearchQuery, setCustomerSearchQuery] = useState('');
    const [customerSegmentFilter, setCustomerSegmentFilter] = useState<'all' | 'vip' | 'active' | 'new' | 'at_risk'>('all');
    const [customerOrderActivityFilter, setCustomerOrderActivityFilter] = useState<'all' | 'single' | 'repeat'>('all');
    const [selectedOrder, setSelectedOrder] = useState<EcommerceOrderDetail | null>(null);
    const [isOrderDetailsLoading, setIsOrderDetailsLoading] = useState(false);
    const [isSavingOrderUpdate, setIsSavingOrderUpdate] = useState(false);
    const [accountingEntries, setAccountingEntries] = useState<EcommerceAccountingEntryItem[]>([]);
    const [accountingSummary, setAccountingSummary] = useState<EcommerceAccountingEntriesResponse['summary'] | null>(null);
    const [reconciliationSummary, setReconciliationSummary] = useState<EcommerceAccountingReconciliationResponse['summary'] | null>(null);
    const [reconciliationAccounts, setReconciliationAccounts] = useState<EcommerceAccountingReconciliationAccount[]>([]);
    const [isAccountingLoading, setIsAccountingLoading] = useState(false);
    const [orderUpdateForm, setOrderUpdateForm] = useState<OrderUpdateFormState>({
        status: 'pending',
        paymentStatus: 'unpaid',
        fulfillmentStatus: 'unfulfilled',
        notes: '',
    });
    const [paymentProviders, setPaymentProviders] = useState<EcommercePaymentProvider[]>([]);
    const [providerForms, setProviderForms] = useState<Record<string, ProviderFormState>>({});
    const [isPaymentProvidersLoading, setIsPaymentProvidersLoading] = useState(false);
    const [savingProviderSlug, setSavingProviderSlug] = useState<string | null>(null);
    const [selectedPaymentProviderSlug, setSelectedPaymentProviderSlug] = useState<string | null>(null);
    const [paymentProvidersView, setPaymentProvidersView] = useState<'list' | 'config'>('list');
    const [shippingCouriers, setShippingCouriers] = useState<EcommerceShippingCourier[]>([]);
    const [selectedShippingCourierSlug, setSelectedShippingCourierSlug] = useState<string | null>(null);
    const [courierForms, setCourierForms] = useState<Record<string, CourierFormState>>({});
    const [isShippingCouriersLoading, setIsShippingCouriersLoading] = useState(false);
    const [savingCourierSlug, setSavingCourierSlug] = useState<string | null>(null);
    const [shipmentForm, setShipmentForm] = useState<CreateShipmentFormState>(createShipmentFormState());
    const [isCreatingShipment, setIsCreatingShipment] = useState(false);
    const [shipmentActionId, setShipmentActionId] = useState<number | null>(null);

    const loadCategories = useCallback(async () => {
        setIsCategoriesLoading(true);

        try {
            const response = await axios.get<EcommerceCategoryListResponse>(`/panel/sites/${siteId}/ecommerce/categories`);
            setCategories(response.data.categories ?? []);
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to load ecommerce categories')));
        } finally {
            setIsCategoriesLoading(false);
        }
    }, [siteId, t]);

    const loadProducts = useCallback(async () => {
        setIsProductsLoading(true);

        try {
            const response = await axios.get<EcommerceProductListResponse>(`/panel/sites/${siteId}/ecommerce/products`);
            setProducts(response.data.products ?? []);
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to load ecommerce products')));
        } finally {
            setIsProductsLoading(false);
        }
    }, [siteId, t]);

    const loadDiscounts = useCallback(async () => {
        setIsDiscountsLoading(true);

        try {
            const response = await axios.get<EcommerceDiscountListResponse>(`/panel/sites/${siteId}/ecommerce/discounts`);
            setDiscounts(response.data.discounts ?? []);
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to load discounts')));
        } finally {
            setIsDiscountsLoading(false);
        }
    }, [siteId, t]);

    const loadInventory = useCallback(async () => {
        setIsInventoryLoading(true);

        try {
            const response = await axios.get<EcommerceInventoryDashboardResponse>(`/panel/sites/${siteId}/ecommerce/inventory`);
            const nextItems = response.data.inventory_items ?? [];
            setInventorySummary(response.data.summary ?? null);
            setInventoryLocations(response.data.locations ?? []);
            setInventoryItems(nextItems);
            setLowStockItems(response.data.low_stock_items ?? []);
            setInventoryMovements(response.data.recent_movements ?? []);

            const nextForms: Record<number, InventoryItemFormState> = {};
            nextItems.forEach((item) => {
                nextForms[item.id] = createInventoryItemFormState(item);
            });
            setInventoryItemForms(nextForms);
        } catch (error) {
            const axiosError = error as AxiosError<ApiErrorPayload>;
            const status = axiosError.response?.status;
            if (status === 403 || status === 404) {
                setInventorySummary(null);
                setInventoryLocations([]);
                setInventoryItems([]);
                setLowStockItems([]);
                setInventoryMovements([]);
                setInventoryItemForms({});
                return;
            }
            toast.error(getApiErrorMessage(error, t('Failed to load inventory dashboard')));
        } finally {
            setIsInventoryLoading(false);
        }
    }, [siteId, t]);

    const loadOrders = useCallback(async () => {
        setIsOrdersLoading(true);

        try {
            const response = await axios.get<EcommerceOrderListResponse>(`/panel/sites/${siteId}/ecommerce/orders`);
            const nextOrders = response.data.orders ?? [];
            setOrders(nextOrders);

            setSelectedOrderId((current) => {
                if (current !== null && nextOrders.some((order) => order.id === current)) {
                    return current;
                }

                return nextOrders[0]?.id ?? null;
            });
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to load ecommerce orders')));
        } finally {
            setIsOrdersLoading(false);
        }
    }, [siteId, t]);

    const loadPaymentProviders = useCallback(async () => {
        setIsPaymentProvidersLoading(true);

        try {
            const response = await axios.get<EcommercePaymentProviderListResponse>(`/panel/sites/${siteId}/ecommerce/payment-gateways`);
            const providers = response.data.providers ?? [];
            setPaymentProviders(providers);

            const nextForms: Record<string, ProviderFormState> = {};
            providers.forEach((provider) => {
                nextForms[provider.slug] = createProviderFormState(provider);
            });
            setProviderForms(nextForms);
        } catch (error) {
            const axiosError = error as AxiosError<ApiErrorPayload>;
            const status = axiosError.response?.status;
            if (status === 403 || status === 404) {
                setPaymentProviders([]);
                setProviderForms({});
                return;
            }
            toast.error(getApiErrorMessage(error, t('Failed to load payment providers')));
        } finally {
            setIsPaymentProvidersLoading(false);
        }
    }, [siteId, t]);

    const loadShippingCouriers = useCallback(async () => {
        setIsShippingCouriersLoading(true);

        try {
            const response = await axios.get<EcommerceShippingCourierListResponse>(`/panel/sites/${siteId}/ecommerce/shipping/couriers`);
            const couriers = response.data.couriers ?? [];
            setShippingCouriers(couriers);

            const nextForms: Record<string, CourierFormState> = {};
            couriers.forEach((courier) => {
                nextForms[courier.slug] = createCourierFormState(courier);
            });
            setCourierForms(nextForms);
        } catch (error) {
            const axiosError = error as AxiosError<ApiErrorPayload>;
            const status = axiosError.response?.status;
            if (status === 403 || status === 404) {
                setShippingCouriers([]);
                setCourierForms({});
                return;
            }
            toast.error(getApiErrorMessage(error, t('Failed to load shipping couriers')));
        } finally {
            setIsShippingCouriersLoading(false);
        }
    }, [siteId, t]);

    const loadOrderDetails = useCallback(async (orderId: number) => {
        setIsOrderDetailsLoading(true);

        try {
            const response = await axios.get<EcommerceOrderDetailResponse>(`/panel/sites/${siteId}/ecommerce/orders/${orderId}`);
            setSelectedOrder(response.data.order);
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to load order details')));
        } finally {
            setIsOrderDetailsLoading(false);
        }
    }, [siteId, t]);

    const loadOrderAccounting = useCallback(async (orderId: number) => {
        setIsAccountingLoading(true);

        try {
            const [entriesResponse, reconciliationResponse] = await Promise.all([
                axios.get<EcommerceAccountingEntriesResponse>(`/panel/sites/${siteId}/ecommerce/accounting/entries`, {
                    params: {
                        order_id: orderId,
                        limit: 30,
                    },
                }),
                axios.get<EcommerceAccountingReconciliationResponse>(`/panel/sites/${siteId}/ecommerce/accounting/reconciliation`, {
                    params: {
                        order_id: orderId,
                    },
                }),
            ]);

            setAccountingEntries(entriesResponse.data.entries ?? []);
            setAccountingSummary(entriesResponse.data.summary ?? null);
            setReconciliationSummary(reconciliationResponse.data.summary ?? null);
            setReconciliationAccounts(reconciliationResponse.data.accounts ?? []);
        } catch (error) {
            const axiosError = error as AxiosError<ApiErrorPayload>;
            const status = axiosError.response?.status;
            if (status === 403 || status === 404) {
                setAccountingEntries([]);
                setAccountingSummary(null);
                setReconciliationSummary(null);
                setReconciliationAccounts([]);
                return;
            }
            toast.error(getApiErrorMessage(error, t('Failed to load accounting reconciliation')));
        } finally {
            setIsAccountingLoading(false);
        }
    }, [siteId, t]);

    useEffect(() => {
        if (initialCoreLoadedForSiteRef.current === siteId) {
            return;
        }

        initialCoreLoadedForSiteRef.current = siteId;
        void Promise.all([
            loadCategories(),
            loadProducts(),
        ]);
    }, [loadCategories, loadProducts, siteId]);

    useEffect(() => {
        switch (activeTab) {
            case 'discounts':
                void loadDiscounts();
                break;
            case 'inventory':
                void loadInventory();
                break;
            case 'orders':
            case 'customers':
                void loadOrders();
                break;
            case 'paymentProviders':
                void loadPaymentProviders();
                break;
            case 'shippingProviders':
                void loadShippingCouriers();
                break;
            default:
                break;
        }
    }, [activeTab, loadDiscounts, loadInventory, loadOrders, loadPaymentProviders, loadShippingCouriers]);

    useEffect(() => {
        if (paymentProviders.length === 0) {
            setSelectedPaymentProviderSlug(null);
            return;
        }

        setSelectedPaymentProviderSlug((current) => (
            current && paymentProviders.some((provider) => provider.slug === current)
                ? current
                : paymentProviders[0]?.slug ?? null
        ));
    }, [paymentProviders]);

    useEffect(() => {
        if (activeTab !== 'paymentProviders' || typeof window === 'undefined') {
            return;
        }

        const slugFromUrl = new URLSearchParams(window.location.search).get('payment_provider');
        if (!slugFromUrl) {
            setPaymentProvidersView('list');
            return;
        }

        if (paymentProviders.some((provider) => provider.slug === slugFromUrl)) {
            setSelectedPaymentProviderSlug(slugFromUrl);
            setPaymentProvidersView('config');
            return;
        }

        if (paymentProviders.length > 0) {
            setPaymentProvidersView('list');
        }
    }, [activeTab, paymentProviders]);

    useEffect(() => {
        if (shippingCouriers.length === 0) {
            setSelectedShippingCourierSlug(null);
            return;
        }

        setSelectedShippingCourierSlug((current) => (
            current && shippingCouriers.some((courier) => courier.slug === current)
                ? current
                : shippingCouriers.find((courier) => courier.is_enabled)?.slug
                    ?? shippingCouriers[0]?.slug
                    ?? null
        ));
    }, [shippingCouriers]);

    useEffect(() => {
        if (activeTab !== 'orders') {
            return;
        }

        if (selectedOrderId === null) {
            setSelectedOrder(null);
            setAccountingEntries([]);
            setAccountingSummary(null);
            setReconciliationSummary(null);
            setReconciliationAccounts([]);
            return;
        }

        void Promise.all([
            loadOrderDetails(selectedOrderId),
            loadOrderAccounting(selectedOrderId),
        ]);
    }, [activeTab, loadOrderAccounting, loadOrderDetails, selectedOrderId]);

    useEffect(() => {
        if (activeTab !== 'orders' || typeof window === 'undefined') {
            return;
        }

        const params = new URLSearchParams(window.location.search);
        const orderIdFromUrl = params.get('order_id');
        if (!orderIdFromUrl) {
            if (ordersView !== 'list') {
                setOrdersView('list');
            }
            return;
        }

        const parsed = Number(orderIdFromUrl);
        if (!Number.isInteger(parsed) || parsed <= 0) {
            return;
        }

        if (selectedOrderId !== parsed) {
            setSelectedOrderId(parsed);
        }

        if (ordersView !== 'details') {
            setOrdersView('details');
        }
    }, [activeTab, ordersView, selectedOrderId]);

    useEffect(() => {
        if (!selectedOrder) {
            return;
        }

        setOrderUpdateForm({
            status: selectedOrder.status,
            paymentStatus: selectedOrder.payment_status,
            fulfillmentStatus: selectedOrder.fulfillment_status,
            notes: selectedOrder.notes ?? '',
        });
    }, [selectedOrder]);

    useEffect(() => {
        setOrderRowForms((current) => {
            const next: Record<number, OrderRowStatusFormState> = {};

            orders.forEach((order) => {
                const existing = current[order.id];
                next[order.id] = existing ?? createOrderRowStatusFormState(order);
            });

            return next;
        });
    }, [orders]);

    useEffect(() => {
        if (!activeSection || activeSection === activeTab) {
            return;
        }

        setActiveTab(activeSection);
    }, [activeSection, activeTab]);

    useEffect(() => {
        if (shipmentForm.providerSlug.trim() !== '') {
            return;
        }

        const preferredProvider = shippingCouriers.find((courier) => courier.is_enabled)?.slug
            ?? shippingCouriers[0]?.slug
            ?? '';

        if (preferredProvider === '') {
            return;
        }

        setShipmentForm((prev) => ({
            ...prev,
            providerSlug: preferredProvider,
        }));
    }, [shipmentForm.providerSlug, shippingCouriers]);

    const resetCategoryForm = () => {
        setCategoryForm(createCategoryFormState());
        setEditingCategoryId(null);
    };

    const resetProductForm = () => {
        setProductForm(createProductFormState());
        setEditingProductId(null);
    };

    const resetInventoryLocationForm = () => {
        setInventoryLocationForm(createInventoryLocationFormState());
        setEditingInventoryLocationId(null);
    };

    const handleInventoryLocationEdit = (location: EcommerceInventoryLocation) => {
        setEditingInventoryLocationId(location.id);
        setInventoryLocationForm({
            key: location.key,
            name: location.name,
            status: location.status,
            isDefault: location.is_default,
            notes: location.notes ?? '',
        });
    };

    const handleInventoryLocationSubmit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const name = inventoryLocationForm.name.trim();
        const key = slugify(inventoryLocationForm.key.trim() || name);
        if (name === '' || key === '') {
            toast.error(t('Location name and key are required.'));
            return;
        }

        setIsSavingInventoryLocation(true);
        try {
            const payload = {
                name,
                key,
                status: inventoryLocationForm.status,
                is_default: inventoryLocationForm.isDefault,
                notes: inventoryLocationForm.notes.trim() || null,
            };

            if (editingInventoryLocationId === null) {
                await axios.post<EcommerceInventoryLocationMutationResponse>(`/panel/sites/${siteId}/ecommerce/inventory/locations`, payload);
                toast.success(t('Inventory location created'));
            } else {
                await axios.put<EcommerceInventoryLocationMutationResponse>(
                    `/panel/sites/${siteId}/ecommerce/inventory/locations/${editingInventoryLocationId}`,
                    payload
                );
                toast.success(t('Inventory location updated'));
            }

            await loadInventory();
            resetInventoryLocationForm();
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to save inventory location')));
        } finally {
            setIsSavingInventoryLocation(false);
        }
    };

    const updateInventoryItemForm = useCallback((itemId: number, updater: (current: InventoryItemFormState) => InventoryItemFormState) => {
        setInventoryItemForms((prev) => {
            const item = inventoryItems.find((entry) => entry.id === itemId);
            const current = prev[itemId] ?? (item ? createInventoryItemFormState(item) : {
                locationId: '',
                lowStockThreshold: '',
                quantityDelta: '',
                adjustmentReason: '',
                countedQuantity: '0',
            });

            return {
                ...prev,
                [itemId]: updater(current),
            };
        });
    }, [inventoryItems]);

    const handleInventorySettingsSave = async (itemId: number) => {
        const form = inventoryItemForms[itemId];
        if (!form) {
            toast.error(t('Inventory item form state is missing.'));
            return;
        }

        const parsedLocationId = parseOptionalNonNegativeInteger(form.locationId);
        if (form.locationId.trim() !== '' && parsedLocationId === null) {
            toast.error(t('Selected location is invalid.'));
            return;
        }

        const parsedLowStockThreshold = parseOptionalNonNegativeInteger(form.lowStockThreshold);
        if (form.lowStockThreshold.trim() !== '' && parsedLowStockThreshold === null) {
            toast.error(t('Low stock threshold must be a non-negative integer.'));
            return;
        }

        setSavingInventoryItemSettingsId(itemId);
        try {
            await axios.put<EcommerceInventoryItemMutationResponse>(`/panel/sites/${siteId}/ecommerce/inventory/items/${itemId}`, {
                location_id: parsedLocationId,
                low_stock_threshold: parsedLowStockThreshold,
            });

            toast.success(t('Inventory item settings updated'));
            await loadInventory();
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to update inventory item settings')));
        } finally {
            setSavingInventoryItemSettingsId(null);
        }
    };

    const handleInventoryAdjustment = async (itemId: number) => {
        const form = inventoryItemForms[itemId];
        if (!form) {
            toast.error(t('Inventory item form state is missing.'));
            return;
        }

        const quantityDelta = parseInteger(form.quantityDelta);
        if (quantityDelta === null || quantityDelta === 0) {
            toast.error(t('Quantity delta must be a non-zero integer.'));
            return;
        }

        setAdjustingInventoryItemId(itemId);
        try {
            await axios.post<EcommerceInventoryItemMutationResponse>(`/panel/sites/${siteId}/ecommerce/inventory/items/${itemId}/adjust`, {
                quantity_delta: quantityDelta,
                reason: form.adjustmentReason.trim() || null,
            });

            toast.success(t('Inventory adjustment applied'));
            await Promise.all([
                loadInventory(),
                loadProducts(),
            ]);
            updateInventoryItemForm(itemId, (current) => ({
                ...current,
                quantityDelta: '',
                adjustmentReason: '',
            }));
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to apply inventory adjustment')));
        } finally {
            setAdjustingInventoryItemId(null);
        }
    };

    const handleInventoryStocktake = async (itemId: number) => {
        const form = inventoryItemForms[itemId];
        if (!form) {
            toast.error(t('Inventory item form state is missing.'));
            return;
        }

        const countedQuantity = parseOptionalNonNegativeInteger(form.countedQuantity);
        if (countedQuantity === null) {
            toast.error(t('Counted quantity must be a non-negative integer.'));
            return;
        }

        setStocktakingInventoryItemId(itemId);
        try {
            await axios.post<EcommerceInventoryItemMutationResponse>(`/panel/sites/${siteId}/ecommerce/inventory/items/${itemId}/stocktake`, {
                counted_quantity: countedQuantity,
            });

            toast.success(t('Inventory stocktake applied'));
            await Promise.all([
                loadInventory(),
                loadProducts(),
            ]);
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to apply inventory stocktake')));
        } finally {
            setStocktakingInventoryItemId(null);
        }
    };

    const handleCategoryImageUpload = async (files: FileList | null) => {
        if (!files || files.length === 0) {
            return;
        }

        const [file] = Array.from(files);
        if (!file) {
            return;
        }

        setIsUploadingCategoryImage(true);
        try {
            const formData = new FormData();
            formData.append('file', file);

            const response = await axios.post<MediaUploadResponse>(`/panel/sites/${siteId}/media/upload`, formData);
            const media = response.data.media;

            setCategoryForm((prev) => ({
                ...prev,
                imageUrl: media.asset_url || media.path || prev.imageUrl,
            }));

            toast.success(t('Image uploaded'));
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to upload image')));
        } finally {
            setIsUploadingCategoryImage(false);
            if (categoryImageUploadRef.current) {
                categoryImageUploadRef.current.value = '';
            }
        }
    };

    const handleCategorySubmit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const normalizedName = categoryForm.name.trim();
        const normalizedSlug = slugify(categoryForm.slug);
        if (normalizedName === '' || normalizedSlug === '') {
            toast.error(t('Category name and slug are required.'));
            return;
        }

        const parsedSortOrder = parseOptionalNonNegativeInteger(categoryForm.sortOrder);
        if (parsedSortOrder === null) {
            toast.error(t('Sort order must be a non-negative integer.'));
            return;
        }

        setIsSavingCategory(true);
        try {
            const payload = {
                name: normalizedName,
                slug: normalizedSlug,
                description: categoryForm.description.trim() || null,
                status: categoryForm.status,
                sort_order: parsedSortOrder,
                meta_json: {
                    image_url: categoryForm.imageUrl.trim() || null,
                    icon_key: categoryForm.iconKey.trim() || null,
                },
            };

            if (editingCategoryId === null) {
                await axios.post(`/panel/sites/${siteId}/ecommerce/categories`, payload);
                toast.success(t('Category created successfully'));
            } else {
                await axios.put(`/panel/sites/${siteId}/ecommerce/categories/${editingCategoryId}`, payload);
                toast.success(t('Category updated successfully'));
            }

            await loadCategories();
            resetCategoryForm();
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to save category')));
        } finally {
            setIsSavingCategory(false);
        }
    };

    const handleCategoryEdit = (category: EcommerceCategory) => {
        setEditingCategoryId(category.id);
        setCategoryForm({
            name: category.name,
            slug: category.slug,
            description: category.description ?? '',
            imageUrl: typeof category.meta_json?.image_url === 'string' ? category.meta_json.image_url : '',
            iconKey: typeof category.meta_json?.icon_key === 'string' ? category.meta_json.icon_key : '',
            status: category.status,
            sortOrder: String(category.sort_order),
        });
    };

    const handleCategoryDelete = async (category: EcommerceCategory) => {
        if (!window.confirm(t('Delete this category?'))) {
            return;
        }

        setDeletingCategoryId(category.id);
        try {
            await axios.delete(`/panel/sites/${siteId}/ecommerce/categories/${category.id}`);
            toast.success(t('Category deleted'));

            if (editingCategoryId === category.id) {
                resetCategoryForm();
            }

            await loadCategories();
            await loadProducts();
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to delete category')));
        } finally {
            setDeletingCategoryId(null);
        }
    };

    const resetDiscountForm = () => {
        setEditingDiscountId(null);
        setDiscountForm(createDiscountFormState());
    };

    const handleDiscountEdit = (discount: EcommerceDiscount) => {
        setEditingDiscountId(discount.id);
        setDiscountForm({
            name: discount.name,
            code: discount.code ?? '',
            type: discount.type,
            value: String(discount.value),
            status: discount.status,
            scope: discount.scope,
            productIds: Array.isArray(discount.product_ids_json) ? discount.product_ids_json.map((id) => Number(id)).filter(Number.isFinite) : [],
            startsAt: toDateTimeInputValue(discount.starts_at),
            endsAt: toDateTimeInputValue(discount.ends_at),
            notes: discount.notes ?? '',
        });
    };

    const toggleDiscountFormProduct = (productId: number) => {
        setDiscountForm((prev) => ({
            ...prev,
            productIds: prev.productIds.includes(productId)
                ? prev.productIds.filter((id) => id !== productId)
                : [...prev.productIds, productId],
        }));
    };

    const handleDiscountSubmit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const normalizedName = discountForm.name.trim();
        if (normalizedName === '') {
            toast.error(t('Discount name is required.'));
            return;
        }

        const parsedValue = parseOptionalNonNegativeDecimal(discountForm.value);
        if (parsedValue === null || parsedValue <= 0) {
            toast.error(t('Discount value must be greater than zero.'));
            return;
        }

        setIsSavingDiscount(true);
        try {
            const payload = {
                name: normalizedName,
                code: discountForm.code.trim() || null,
                type: discountForm.type,
                value: parsedValue,
                status: discountForm.status,
                scope: discountForm.scope,
                product_ids_json: discountForm.scope === 'specific_products' ? discountForm.productIds : [],
                category_ids_json: [],
                starts_at: discountForm.startsAt.trim() || null,
                ends_at: discountForm.endsAt.trim() || null,
                notes: discountForm.notes.trim() || null,
                meta_json: {},
            };

            if (editingDiscountId === null) {
                const response = await axios.post<EcommerceDiscountMutationResponse>(`/panel/sites/${siteId}/ecommerce/discounts`, payload);
                toast.success(response.data.message || t('Discount created'));
            } else {
                const response = await axios.put<EcommerceDiscountMutationResponse>(`/panel/sites/${siteId}/ecommerce/discounts/${editingDiscountId}`, payload);
                toast.success(response.data.message || t('Discount updated'));
            }

            await loadDiscounts();
            resetDiscountForm();
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to save discount')));
        } finally {
            setIsSavingDiscount(false);
        }
    };

    const handleDiscountDelete = async (discount: EcommerceDiscount) => {
        if (!window.confirm(t('Delete this discount?'))) {
            return;
        }

        setDeletingDiscountId(discount.id);
        try {
            await axios.delete(`/panel/sites/${siteId}/ecommerce/discounts/${discount.id}`);
            toast.success(t('Discount deleted'));

            if (editingDiscountId === discount.id) {
                resetDiscountForm();
            }

            await loadDiscounts();
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to delete discount')));
        } finally {
            setDeletingDiscountId(null);
        }
    };

    const toggleBulkSelectedProduct = (productId: number) => {
        setBulkSelectedProductIds((prev) => (
            prev.includes(productId)
                ? prev.filter((id) => id !== productId)
                : [...prev, productId]
        ));
    };

    const handleBulkApplyDiscount = async () => {
        if (bulkSelectedProductIds.length === 0) {
            toast.error(t('Select products first'));
            return;
        }

        const parsedValue = parseOptionalNonNegativeDecimal(bulkDiscountForm.value);
        if (parsedValue === null || parsedValue <= 0) {
            toast.error(t('Discount value must be greater than zero.'));
            return;
        }

        setIsApplyingBulkDiscount(true);
        try {
            const response = await axios.post<EcommerceDiscountBulkApplyResponse>(`/panel/sites/${siteId}/ecommerce/discounts/bulk-apply`, {
                product_ids: bulkSelectedProductIds,
                type: bulkDiscountForm.type,
                value: parsedValue,
                create_discount_record: bulkDiscountForm.createRecord,
                name: bulkDiscountForm.name.trim() || null,
                code: bulkDiscountForm.code.trim() || null,
                status: bulkDiscountForm.status,
            });

            toast.success(response.data.message || t('Bulk discount applied'));

            await Promise.all([
                loadProducts(),
                loadDiscounts(),
            ]);
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to apply bulk discount')));
        } finally {
            setIsApplyingBulkDiscount(false);
        }
    };

    const buildProductPayload = (): Record<string, unknown> | null => {
        const normalizedName = productForm.name.trim();
        const normalizedSlug = slugify(productForm.slug);
        if (normalizedName === '' || normalizedSlug === '') {
            toast.error(t('Product name and slug are required.'));
            return null;
        }

        const parsedPrice = parseOptionalNonNegativeDecimal(productForm.price);
        if (parsedPrice === null) {
            toast.error(t('Price must be a non-negative number.'));
            return null;
        }

        const parsedCompareAt = parseOptionalNonNegativeDecimal(productForm.compareAtPrice);
        if (productForm.compareAtPrice.trim() !== '' && parsedCompareAt === null) {
            toast.error(t('Compare-at price must be a non-negative number.'));
            return null;
        }

        const parsedStockQuantity = parseOptionalNonNegativeInteger(productForm.stockQuantity);
        if (parsedStockQuantity === null) {
            toast.error(t('Stock quantity must be a non-negative integer.'));
            return null;
        }

        const parsedWeight = parseOptionalNonNegativeInteger(productForm.weightGrams);
        if (productForm.weightGrams.trim() !== '' && parsedWeight === null) {
            toast.error(t('Weight must be a non-negative integer.'));
            return null;
        }

        const parsedCategoryId = parseOptionalNonNegativeInteger(productForm.categoryId);
        if (productForm.categoryId.trim() !== '' && parsedCategoryId === null) {
            toast.error(t('Selected category is invalid.'));
            return null;
        }

        const parsedAttributes = parseJsonRecord(productForm.attributesJson);
        if (!parsedAttributes.value) {
            toast.error(parsedAttributes.error ?? t('Invalid attributes JSON'));
            return null;
        }

        return {
            category_id: parsedCategoryId,
            name: normalizedName,
            slug: normalizedSlug,
            sku: productForm.sku.trim() || null,
            short_description: productForm.shortDescription.trim() || null,
            description: productForm.description.trim() || null,
            price: parsedPrice,
            compare_at_price: parsedCompareAt,
            currency: productForm.currency.trim().toUpperCase() || 'GEL',
            status: productForm.status,
            stock_tracking: productForm.stockTracking,
            stock_quantity: parsedStockQuantity,
            allow_backorder: productForm.allowBackorder,
            is_digital: productForm.isDigital,
            weight_grams: parsedWeight,
            attributes_json: parsedAttributes.value,
            seo_title: productForm.seoTitle.trim() || null,
            seo_description: productForm.seoDescription.trim() || null,
        };
    };

    const handleProductSubmit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const payload = buildProductPayload();
        if (!payload) {
            return;
        }

        setIsSavingProduct(true);
        try {
            if (editingProductId === null) {
                await axios.post(`/panel/sites/${siteId}/ecommerce/products`, payload);
                toast.success(t('Product created successfully'));
            } else {
                await axios.put(`/panel/sites/${siteId}/ecommerce/products/${editingProductId}`, payload);
                toast.success(t('Product updated successfully'));
            }

            await Promise.all([
                loadProducts(),
                loadInventory(),
            ]);
            resetProductForm();
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to save product')));
        } finally {
            setIsSavingProduct(false);
        }
    };

    const handleProductEdit = (product: EcommerceProduct) => {
        setEditingProductId(product.id);
        setProductForm({
            categoryId: product.category_id !== null ? String(product.category_id) : '',
            name: product.name,
            slug: product.slug,
            sku: product.sku ?? '',
            shortDescription: product.short_description ?? '',
            description: product.description ?? '',
            price: String(product.price),
            compareAtPrice: product.compare_at_price ?? '',
            currency: product.currency,
            status: product.status,
            stockTracking: product.stock_tracking,
            stockQuantity: String(product.stock_quantity),
            allowBackorder: product.allow_backorder,
            isDigital: product.is_digital,
            weightGrams: product.weight_grams !== null ? String(product.weight_grams) : '',
            attributesJson: toPrettyJson(product.attributes_json ?? {}),
            seoTitle: product.seo_title ?? '',
            seoDescription: product.seo_description ?? '',
        });
    };

    const handleProductDelete = async (product: EcommerceProduct) => {
        if (!window.confirm(t('Delete this product?'))) {
            return;
        }

        setDeletingProductId(product.id);
        try {
            await axios.delete(`/panel/sites/${siteId}/ecommerce/products/${product.id}`);
            toast.success(t('Product deleted'));

            if (editingProductId === product.id) {
                resetProductForm();
            }

            await Promise.all([
                loadProducts(),
                loadInventory(),
            ]);
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to delete product')));
        } finally {
            setDeletingProductId(null);
        }
    };

    const handleOrderUpdate = async () => {
        if (!selectedOrderId) {
            toast.error(t('Select an order first'));
            return;
        }

        setIsSavingOrderUpdate(true);
        try {
            await axios.put(`/panel/sites/${siteId}/ecommerce/orders/${selectedOrderId}`, {
                status: orderUpdateForm.status,
                payment_status: orderUpdateForm.paymentStatus,
                fulfillment_status: orderUpdateForm.fulfillmentStatus,
                notes: orderUpdateForm.notes.trim() || null,
            });

            toast.success(t('Order updated successfully'));
            await Promise.all([
                loadOrders(),
                loadOrderDetails(selectedOrderId),
                loadOrderAccounting(selectedOrderId),
            ]);
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to update order')));
        } finally {
            setIsSavingOrderUpdate(false);
        }
    };

    const handleOrderRowStatusChange = <K extends keyof OrderRowStatusFormState>(
        orderId: number,
        key: K,
        value: OrderRowStatusFormState[K],
    ) => {
        setOrderRowForms((current) => ({
            ...current,
            [orderId]: {
                ...(current[orderId] ?? {
                    status: 'pending',
                    paymentStatus: 'unpaid',
                    fulfillmentStatus: 'unfulfilled',
                }),
                [key]: value,
            },
        }));
    };

    const handleSaveOrderRow = async (order: EcommerceOrderSummary) => {
        const rowForm = orderRowForms[order.id] ?? createOrderRowStatusFormState(order);
        const isUnchanged =
            rowForm.status === order.status
            && rowForm.paymentStatus === order.payment_status
            && rowForm.fulfillmentStatus === order.fulfillment_status;

        if (isUnchanged) {
            if (selectedOrderId !== order.id) {
                setSelectedOrderId(order.id);
            }

            return;
        }

        setSavingOrderRowId(order.id);
        try {
            await axios.put(`/panel/sites/${siteId}/ecommerce/orders/${order.id}`, {
                status: rowForm.status,
                payment_status: rowForm.paymentStatus,
                fulfillment_status: rowForm.fulfillmentStatus,
            });

            toast.success(t('Order updated successfully'));

            await loadOrders();
            if (selectedOrderId === order.id) {
                await Promise.all([
                    loadOrderDetails(order.id),
                    loadOrderAccounting(order.id),
                ]);
            }
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to update order')));
        } finally {
            setSavingOrderRowId(null);
        }
    };

    const updateOrdersUrlState = useCallback((nextOrderId: number | null) => {
        if (typeof window === 'undefined') {
            return;
        }

        const url = new URL(window.location.href);
        if (nextOrderId && nextOrderId > 0) {
            url.searchParams.set('order_id', String(nextOrderId));
        } else {
            url.searchParams.delete('order_id');
        }
        window.history.replaceState({}, '', url.toString());
    }, []);

    const openOrderDetailsView = useCallback((orderId: number) => {
        setSelectedOrderId(orderId);
        setOrdersView('details');
        updateOrdersUrlState(orderId);
    }, [updateOrdersUrlState]);

    const closeOrderDetailsView = useCallback(() => {
        setOrdersView('list');
        updateOrdersUrlState(null);
    }, [updateOrdersUrlState]);

    const handleDeleteOrder = useCallback(async (order: EcommerceOrderSummary) => {
        if (!window.confirm(t('Delete this order?'))) {
            return;
        }

        setDeletingOrderId(order.id);
        try {
            await axios.delete(`/panel/sites/${siteId}/ecommerce/orders/${order.id}`);
            toast.success(t('Order deleted'));

            if (selectedOrderId === order.id) {
                setSelectedOrderId(null);
                setSelectedOrder(null);
                setOrdersView('list');
                updateOrdersUrlState(null);
            }

            setOrderRowForms((current) => {
                const next = { ...current };
                delete next[order.id];

                return next;
            });

            await loadOrders();
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to delete order')));
        } finally {
            setDeletingOrderId(null);
        }
    }, [loadOrders, selectedOrderId, siteId, t, updateOrdersUrlState]);

    const resetShipmentForm = () => {
        const fallbackProvider = shippingCouriers.find((courier) => courier.is_enabled)?.slug
            ?? shippingCouriers[0]?.slug
            ?? shipmentForm.providerSlug;

        setShipmentForm(createShipmentFormState(fallbackProvider));
    };

    const handleCreateShipment = async () => {
        if (!selectedOrderId) {
            toast.error(t('Select an order first'));
            return;
        }

        const providerSlug = shipmentForm.providerSlug.trim();
        if (providerSlug === '') {
            toast.error(t('Select a shipping provider'));
            return;
        }

        setIsCreatingShipment(true);
        try {
            await axios.post(`/panel/sites/${siteId}/ecommerce/orders/${selectedOrderId}/shipments`, {
                provider_slug: providerSlug,
                shipment_reference: shipmentForm.shipmentReference.trim() || null,
                tracking_number: shipmentForm.trackingNumber.trim() || null,
                tracking_url: shipmentForm.trackingUrl.trim() || null,
            });

            toast.success(t('Shipment created successfully'));
            resetShipmentForm();
            await Promise.all([
                loadOrders(),
                loadOrderDetails(selectedOrderId),
                loadOrderAccounting(selectedOrderId),
            ]);
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to create shipment')));
        } finally {
            setIsCreatingShipment(false);
        }
    };

    const handleRefreshShipment = async (shipmentId: number, statusOverride?: string) => {
        if (!selectedOrderId) {
            toast.error(t('Select an order first'));
            return;
        }

        setShipmentActionId(shipmentId);
        try {
            await axios.post(`/panel/sites/${siteId}/ecommerce/orders/${selectedOrderId}/shipments/${shipmentId}/refresh-tracking`, {
                status_override: statusOverride ?? null,
            });

            toast.success(statusOverride ? t('Shipment status updated') : t('Shipment tracking refreshed'));
            await Promise.all([
                loadOrders(),
                loadOrderDetails(selectedOrderId),
                loadOrderAccounting(selectedOrderId),
            ]);
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to refresh shipment tracking')));
        } finally {
            setShipmentActionId(null);
        }
    };

    const handleCancelShipment = async (shipmentId: number) => {
        if (!selectedOrderId) {
            toast.error(t('Select an order first'));
            return;
        }

        if (!window.confirm(t('Cancel this shipment?'))) {
            return;
        }

        setShipmentActionId(shipmentId);
        try {
            await axios.post(`/panel/sites/${siteId}/ecommerce/orders/${selectedOrderId}/shipments/${shipmentId}/cancel`);
            toast.success(t('Shipment cancelled'));
            await Promise.all([
                loadOrders(),
                loadOrderDetails(selectedOrderId),
                loadOrderAccounting(selectedOrderId),
            ]);
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to cancel shipment')));
        } finally {
            setShipmentActionId(null);
        }
    };

    const updateProviderForm = useCallback((providerSlug: string, updater: (current: ProviderFormState) => ProviderFormState) => {
        setProviderForms((prev) => {
            const current = prev[providerSlug] ?? {
                availability: 'inherit',
                config: {},
            };

            return {
                ...prev,
                [providerSlug]: updater(current),
            };
        });
    }, []);

    const handleProviderAvailabilityChange = (providerSlug: string, availability: ProviderAvailability) => {
        updateProviderForm(providerSlug, (current) => ({
            ...current,
            availability,
        }));
    };

    const handleProviderFieldChange = (
        providerSlug: string,
        field: ProviderConfigField,
        rawValue: string | boolean | null
    ) => {
        updateProviderForm(providerSlug, (current) => {
            const nextConfig = { ...current.config };
            const fieldType = field.type;

            if (fieldType === 'toggle') {
                if (rawValue === null) {
                    delete nextConfig[field.name];
                } else {
                    nextConfig[field.name] = Boolean(rawValue);
                }
            } else {
                const normalized = String(rawValue);
                if (normalized.trim() === '') {
                    delete nextConfig[field.name];
                } else {
                    nextConfig[field.name] = normalized;
                }
            }

            return {
                ...current,
                config: nextConfig,
            };
        });
    };

    const handleProviderSave = async (provider: EcommercePaymentProvider) => {
        const formState = providerForms[provider.slug] ?? createProviderFormState(provider);

        setSavingProviderSlug(provider.slug);
        try {
            const response = await axios.put<EcommercePaymentProviderUpdateResponse>(
                `/panel/sites/${siteId}/ecommerce/payment-gateways/${provider.slug}`,
                {
                    availability: formState.availability,
                    config: formState.config,
                }
            );

            toast.success(response.data.message || t('Payment provider settings updated'));
            await loadPaymentProviders();
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to update payment provider settings')));
        } finally {
            setSavingProviderSlug(null);
        }
    };

    const updateCourierForm = useCallback((courierSlug: string, updater: (current: CourierFormState) => CourierFormState) => {
        setCourierForms((prev) => {
            const current = prev[courierSlug] ?? {
                availability: 'inherit',
                config: {},
            };

            return {
                ...prev,
                [courierSlug]: updater(current),
            };
        });
    }, []);

    const handleCourierAvailabilityChange = (courierSlug: string, availability: ProviderAvailability) => {
        updateCourierForm(courierSlug, (current) => ({
            ...current,
            availability,
        }));
    };

    const handleCourierFieldChange = (
        courierSlug: string,
        field: ProviderConfigField,
        rawValue: string | boolean | null
    ) => {
        updateCourierForm(courierSlug, (current) => {
            const nextConfig = { ...current.config };
            const fieldType = field.type;

            if (fieldType === 'toggle') {
                if (rawValue === null) {
                    delete nextConfig[field.name];
                } else {
                    nextConfig[field.name] = Boolean(rawValue);
                }
            } else {
                const normalized = String(rawValue);
                if (normalized.trim() === '') {
                    delete nextConfig[field.name];
                } else {
                    nextConfig[field.name] = normalized;
                }
            }

            return {
                ...current,
                config: nextConfig,
            };
        });
    };

    const handleCourierSave = async (courier: EcommerceShippingCourier) => {
        const formState = courierForms[courier.slug] ?? createCourierFormState(courier);

        setSavingCourierSlug(courier.slug);
        try {
            const response = await axios.put<EcommerceShippingCourierUpdateResponse>(
                `/panel/sites/${siteId}/ecommerce/shipping/couriers/${courier.slug}`,
                {
                    availability: formState.availability,
                    config: formState.config,
                }
            );

            toast.success(response.data.message || t('Shipping courier settings updated'));
            await loadShippingCouriers();
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to update shipping courier settings')));
        } finally {
            setSavingCourierSlug(null);
        }
    };

    const handleCopyValue = async (value: string, successMessage: string) => {
        try {
            await navigator.clipboard.writeText(value);
            toast.success(successMessage);
        } catch {
            toast.error(t('Failed to copy to clipboard'));
        }
    };

    const ordersByStatus = useMemo(() => {
        const grouped = new Map<OrderStatus, EcommerceOrderSummary[]>();
        ORDER_STATUS_OPTIONS.forEach((status) => grouped.set(status, []));

        orders.forEach((order) => {
            const current = grouped.get(order.status);
            if (!current) {
                grouped.set(order.status, [order]);
                return;
            }

            current.push(order);
        });

        return grouped;
    }, [orders]);

    const filteredOrders = useMemo(() => {
        const query = orderSearchQuery.trim().toLowerCase();

        return orders.filter((order) => {
            if (orderStatusFilter !== 'all' && order.status !== orderStatusFilter) {
                return false;
            }

            if (orderPaymentFilter !== 'all' && order.payment_status !== orderPaymentFilter) {
                return false;
            }

            if (query === '') {
                return true;
            }

            const searchable = [
                order.order_number,
                order.customer_name,
                order.customer_email,
                order.customer_phone,
                order.payments[0]?.provider ?? null,
            ]
                .map((value) => String(value ?? '').toLowerCase())
                .join(' ');

            return searchable.includes(query);
        });
    }, [orderPaymentFilter, orderSearchQuery, orderStatusFilter, orders]);

    const filteredOrdersByStatus = useMemo(() => {
        const grouped = new Map<OrderStatus, EcommerceOrderSummary[]>();
        ORDER_STATUS_OPTIONS.forEach((status) => grouped.set(status, []));

        filteredOrders.forEach((order) => {
            const current = grouped.get(order.status);
            if (!current) {
                grouped.set(order.status, [order]);
                return;
            }

            current.push(order);
        });

        return grouped;
    }, [filteredOrders]);

    const filteredOrdersSummary = useMemo(() => {
        let gross = 0;
        let paid = 0;
        let outstanding = 0;

        filteredOrders.forEach((order) => {
            gross += Number.parseFloat(String(order.grand_total ?? 0)) || 0;
            paid += Number.parseFloat(String(order.paid_total ?? 0)) || 0;
            outstanding += Number.parseFloat(String(order.outstanding_total ?? 0)) || 0;
        });

        const count = filteredOrders.length;
        const currency = filteredOrders[0]?.currency ?? orders[0]?.currency ?? 'GEL';

        return {
            count,
            currency,
            gross,
            paid,
            outstanding,
            average: count > 0 ? gross / count : 0,
            pending: (filteredOrdersByStatus.get('pending') ?? []).length,
            processing: (filteredOrdersByStatus.get('processing') ?? []).length,
            completed: (filteredOrdersByStatus.get('completed') ?? []).length,
            cancelled: (filteredOrdersByStatus.get('cancelled') ?? []).length,
            refunded: (filteredOrdersByStatus.get('refunded') ?? []).length,
        };
    }, [filteredOrders, filteredOrdersByStatus, orders]);
    const hasOrderFilters = orderSearchQuery.trim() !== '' || orderStatusFilter !== 'all' || orderPaymentFilter !== 'all';

    const customerRows = useMemo(() => {
        type CustomerRow = {
            key: string;
            name: string;
            email: string | null;
            phone: string | null;
            ordersCount: number;
            totalSpent: number;
            paidSpent: number;
            outstanding: number;
            currency: string;
            firstOrderAt: string | null;
            lastOrderAt: string | null;
            latestOrderId: number;
            latestOrderNumber: string;
            latestOrderStatus: OrderStatus;
            latestPaymentStatus: PaymentStatus;
            latestFulfillmentStatus: FulfillmentStatus;
            status: 'vip' | 'active' | 'new' | 'at_risk';
        };

        const map = new Map<string, CustomerRow>();
        const now = Date.now();

        const daysSince = (iso: string | null): number => {
            if (!iso) {
                return Number.POSITIVE_INFINITY;
            }
            const timestamp = new Date(iso).getTime();
            if (Number.isNaN(timestamp)) {
                return Number.POSITIVE_INFINITY;
            }
            return (now - timestamp) / (1000 * 60 * 60 * 24);
        };

        orders.forEach((order) => {
            const email = order.customer_email?.trim() || null;
            const phone = order.customer_phone?.trim() || null;
            const name = order.customer_name?.trim() || email || phone || t('Unknown customer');
            const key = email || `phone:${phone || 'unknown'}:${order.customer_name || ''}` || `order:${order.id}`;
            const grandTotal = Number.parseFloat(String(order.grand_total ?? 0)) || 0;
            const paidTotal = Number.parseFloat(String(order.paid_total ?? 0)) || 0;
            const outstandingTotal = Number.parseFloat(String(order.outstanding_total ?? 0)) || 0;

            const existing = map.get(key);
            if (!existing) {
                map.set(key, {
                    key,
                    name,
                    email,
                    phone,
                    ordersCount: 1,
                    totalSpent: grandTotal,
                    paidSpent: paidTotal,
                    outstanding: outstandingTotal,
                    currency: order.currency || 'GEL',
                    firstOrderAt: order.placed_at,
                    lastOrderAt: order.placed_at ?? order.updated_at,
                    latestOrderId: order.id,
                    latestOrderNumber: order.order_number,
                    latestOrderStatus: order.status,
                    latestPaymentStatus: order.payment_status,
                    latestFulfillmentStatus: order.fulfillment_status,
                    status: 'new',
                });
                return;
            }

            existing.ordersCount += 1;
            existing.totalSpent += grandTotal;
            existing.paidSpent += paidTotal;
            existing.outstanding += outstandingTotal;

            const placedAt = order.placed_at ?? order.updated_at;
            const existingLast = existing.lastOrderAt ? new Date(existing.lastOrderAt).getTime() : 0;
            const nextLast = placedAt ? new Date(placedAt).getTime() : 0;
            if (nextLast >= existingLast) {
                existing.lastOrderAt = placedAt;
                existing.latestOrderId = order.id;
                existing.latestOrderNumber = order.order_number;
                existing.latestOrderStatus = order.status;
                existing.latestPaymentStatus = order.payment_status;
                existing.latestFulfillmentStatus = order.fulfillment_status;
            }

            const existingFirst = existing.firstOrderAt ? new Date(existing.firstOrderAt).getTime() : Number.POSITIVE_INFINITY;
            const nextFirst = placedAt ? new Date(placedAt).getTime() : Number.POSITIVE_INFINITY;
            if (nextFirst < existingFirst) {
                existing.firstOrderAt = placedAt;
            }
        });

        const rows = Array.from(map.values()).map((customer) => {
            const lastOrderDays = daysSince(customer.lastOrderAt);

            let status: CustomerRow['status'] = 'at_risk';
            if (customer.ordersCount === 1) {
                status = 'new';
            }
            if (lastOrderDays <= 90 || customer.ordersCount >= 2) {
                status = 'active';
            }
            if (customer.ordersCount >= 5 || customer.totalSpent >= 1000) {
                status = 'vip';
            }
            if (lastOrderDays > 180 && customer.ordersCount > 1 && status !== 'vip') {
                status = 'at_risk';
            }

            return {
                ...customer,
                status,
            };
        });

        rows.sort((a, b) => {
            const aTime = a.lastOrderAt ? new Date(a.lastOrderAt).getTime() : 0;
            const bTime = b.lastOrderAt ? new Date(b.lastOrderAt).getTime() : 0;
            return bTime - aTime;
        });

        return rows;
    }, [orders, t]);

    const filteredCustomerRows = useMemo(() => {
        const query = customerSearchQuery.trim().toLowerCase();

        return customerRows.filter((customer) => {
            if (customerSegmentFilter !== 'all' && customer.status !== customerSegmentFilter) {
                return false;
            }

            if (customerOrderActivityFilter === 'single' && customer.ordersCount !== 1) {
                return false;
            }

            if (customerOrderActivityFilter === 'repeat' && customer.ordersCount < 2) {
                return false;
            }

            if (query === '') {
                return true;
            }

            const haystack = [
                customer.name,
                customer.email,
                customer.phone,
                customer.latestOrderNumber,
            ].map((value) => String(value ?? '').toLowerCase()).join(' ');

            return haystack.includes(query);
        });
    }, [customerOrderActivityFilter, customerRows, customerSearchQuery, customerSegmentFilter]);

    const customerSummary = useMemo(() => {
        const total = filteredCustomerRows.length;
        const vip = filteredCustomerRows.filter((customer) => customer.status === 'vip').length;
        const active = filteredCustomerRows.filter((customer) => customer.status === 'active').length;
        const newlyAdded = filteredCustomerRows.filter((customer) => customer.status === 'new').length;
        const repeat = filteredCustomerRows.filter((customer) => customer.ordersCount >= 2).length;
        const revenue = filteredCustomerRows.reduce((sum, customer) => sum + customer.totalSpent, 0);
        const currency = filteredCustomerRows[0]?.currency ?? customerRows[0]?.currency ?? orders[0]?.currency ?? 'GEL';

        return { total, vip, active, newlyAdded, repeat, revenue, currency };
    }, [customerRows, filteredCustomerRows, orders]);
    const hasCustomerFilters = customerSearchQuery.trim() !== '' || customerSegmentFilter !== 'all' || customerOrderActivityFilter !== 'all';

    const customerStatusBadgeVariant = useCallback((status: 'vip' | 'active' | 'new' | 'at_risk'): 'default' | 'secondary' | 'outline' => {
        switch (status) {
            case 'vip':
                return 'default';
            case 'active':
                return 'outline';
            case 'new':
                return 'secondary';
            case 'at_risk':
                return 'secondary';
            default:
                return 'outline';
        }
    }, []);

    const selectedPaymentProvider = useMemo(() => (
        paymentProviders.find((provider) => provider.slug === selectedPaymentProviderSlug) ?? null
    ), [paymentProviders, selectedPaymentProviderSlug]);
    const selectedShippingCourier = useMemo(() => (
        shippingCouriers.find((courier) => courier.slug === selectedShippingCourierSlug) ?? null
    ), [selectedShippingCourierSlug, shippingCouriers]);

    const getPaymentProviderCardMeta = (provider: EcommercePaymentProvider) => {
        const exact = PAYMENT_PROVIDER_ICON_MAP[provider.slug];
        if (exact) {
            return exact;
        }

        const normalizedName = provider.name.toLowerCase();
        if (normalizedName.includes('bank')) {
            return PAYMENT_PROVIDER_ICON_MAP['bank-transfer'];
        }
        if (normalizedName.includes('pay') || normalizedName.includes('card')) {
            return PAYMENT_PROVIDER_ICON_MAP.paypal;
        }

        return { icon: CreditCard, accentClass: 'bg-muted text-foreground border-border' };
    };

    const syncPaymentProviderQuery = useCallback((providerSlug: string | null) => {
        if (typeof window === 'undefined') {
            return;
        }

        const url = new URL(window.location.href);
        if (providerSlug && providerSlug.trim() !== '') {
            url.searchParams.set('payment_provider', providerSlug.trim());
        } else {
            url.searchParams.delete('payment_provider');
        }

        const nextUrl = `${url.pathname}${url.search}${url.hash}`;
        const currentUrl = `${window.location.pathname}${window.location.search}${window.location.hash}`;
        if (nextUrl !== currentUrl) {
            window.history.replaceState({}, '', nextUrl);
        }
    }, []);

    const openPaymentProviderConfigView = useCallback((providerSlug: string) => {
        setSelectedPaymentProviderSlug(providerSlug);
        setPaymentProvidersView('config');
        syncPaymentProviderQuery(providerSlug);
    }, [syncPaymentProviderQuery]);

    const closePaymentProviderConfigView = useCallback(() => {
        setPaymentProvidersView('list');
        syncPaymentProviderQuery(null);
    }, [syncPaymentProviderQuery]);

    const getShippingCourierCardMeta = (courier: EcommerceShippingCourier) => {
        const exact = SHIPPING_COURIER_ICON_MAP[courier.slug];
        if (exact) {
            return exact;
        }

        const normalizedName = courier.name.toLowerCase();
        if (normalizedName.includes('courier') || normalizedName.includes('delivery') || normalizedName.includes('shipping')) {
            return { icon: Truck, accentClass: 'bg-sky-100 text-sky-700 border-sky-200' };
        }

        if (normalizedName.includes('pickup')) {
            return { icon: Home, accentClass: 'bg-emerald-100 text-emerald-700 border-emerald-200' };
        }

        return { icon: Package, accentClass: 'bg-muted text-foreground border-border' };
    };

    return (
        <div className="min-w-0 max-w-full space-y-4 overflow-x-hidden">
            <Tabs
                value={activeTab}
                onValueChange={(value) => setActiveTab(value as EcommerceTab)}
                className="min-w-0 max-w-full overflow-x-hidden"
            >
                {!hideTabs ? (
                    <div className="min-w-0 max-w-full overflow-x-hidden">
                        <TabsList className="grid h-auto w-full max-w-7xl grid-cols-2 items-stretch md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 2xl:grid-cols-11">
                            <TabsTrigger value="categories" className="min-w-0 whitespace-normal text-center leading-tight">{t('Categories')}</TabsTrigger>
                            <TabsTrigger value="products" className="min-w-0 whitespace-normal text-center leading-tight">{t('Products')}</TabsTrigger>
                            <TabsTrigger value="discounts" className="min-w-0 whitespace-normal text-center leading-tight">{t('Discounts')}</TabsTrigger>
                            <TabsTrigger value="attributes" className="min-w-0 whitespace-normal text-center leading-tight">{t('Attributes')}</TabsTrigger>
                            <TabsTrigger value="attributeValues" className="min-w-0 whitespace-normal text-center leading-tight">{t('Attribute Values')}</TabsTrigger>
                            <TabsTrigger value="variants" className="min-w-0 whitespace-normal text-center leading-tight">{t('Variants')}</TabsTrigger>
                            <TabsTrigger value="inventory" className="min-w-0 whitespace-normal text-center leading-tight">{t('Inventory')}</TabsTrigger>
                            <TabsTrigger value="orders" className="min-w-0 whitespace-normal text-center leading-tight">{t('Orders')}</TabsTrigger>
                            <TabsTrigger value="customers" className="min-w-0 whitespace-normal text-center leading-tight">{t('Customers')}</TabsTrigger>
                            <TabsTrigger value="paymentProviders" className="min-w-0 whitespace-normal text-center leading-tight">{t('Payment Providers')}</TabsTrigger>
                            <TabsTrigger value="shippingProviders" className="min-w-0 whitespace-normal text-center leading-tight">{t('Shipping Providers')}</TabsTrigger>
                        </TabsList>
                    </div>
                ) : null}

                <TabsContent value="categories" className="min-w-0 max-w-full space-y-4 pt-4 overflow-x-hidden">
                    <div className="grid gap-4 xl:grid-cols-[420px_1fr]">
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {editingCategoryId === null ? t('Create Category') : t('Edit Category')}
                                </CardTitle>
                                <CardDescription>{t('Manage catalog grouping for products')}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form className="space-y-3" onSubmit={handleCategorySubmit}>
                                    <div className="space-y-2">
                                        <Label>{t('Name')}</Label>
                                        <Input
                                            value={categoryForm.name}
                                            onChange={(event) => {
                                                const value = event.target.value;
                                                setCategoryForm((prev) => ({
                                                    ...prev,
                                                    name: value,
                                                    slug: slugify(value),
                                                }));
                                            }}
                                            placeholder={t('Category name')}
                                            required
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label>{t('Slug')}</Label>
                                        <Input
                                            value={categoryForm.slug}
                                            onChange={(event) => setCategoryForm((prev) => ({
                                                ...prev,
                                                slug: slugify(event.target.value),
                                            }))}
                                            placeholder="category-slug"
                                            required
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label>{t('Status')}</Label>
                                        <select
                                            value={categoryForm.status}
                                            onChange={(event) => setCategoryForm((prev) => ({
                                                ...prev,
                                                status: event.target.value as CategoryStatus,
                                            }))}
                                            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                        >
                                            {CATEGORY_STATUS_OPTIONS.map((status) => (
                                                <option key={status} value={status}>
                                                    {status}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="space-y-2">
                                        <Label>{t('Sort Order')}</Label>
                                        <Input
                                            type="number"
                                            min={0}
                                            value={categoryForm.sortOrder}
                                            onChange={(event) => setCategoryForm((prev) => ({
                                                ...prev,
                                                sortOrder: event.target.value,
                                            }))}
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label>{t('Description')}</Label>
                                        <RichTextField
                                            value={categoryForm.description}
                                            onChange={(nextValue) => setCategoryForm((prev) => ({
                                                ...prev,
                                                description: nextValue,
                                            }))}
                                            minHeightClassName="min-h-[140px]"
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label>{t('Category Image')}</Label>
                                        <input
                                            ref={categoryImageUploadRef}
                                            type="file"
                                            accept="image/*"
                                            className="hidden"
                                            onChange={(event) => void handleCategoryImageUpload(event.target.files)}
                                        />
                                        <div className="rounded-lg border p-3 space-y-3">
                                            {categoryForm.imageUrl.trim() !== '' ? (
                                                <div className="relative overflow-hidden rounded-md border bg-muted/20">
                                                    <img
                                                        src={categoryForm.imageUrl}
                                                        alt={categoryForm.name || 'Category'}
                                                        className="h-36 w-full object-cover"
                                                    />
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="secondary"
                                                        className="absolute right-2 top-2 h-7 w-7"
                                                        onClick={() => setCategoryForm((prev) => ({ ...prev, imageUrl: '' }))}
                                                        title={t('Remove image')}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            ) : (
                                                <div className="rounded-md border border-dashed p-5 text-center text-sm text-muted-foreground">
                                                    {t('No image selected')}
                                                </div>
                                            )}

                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="w-full"
                                                onClick={() => categoryImageUploadRef.current?.click()}
                                                disabled={isUploadingCategoryImage}
                                            >
                                                {isUploadingCategoryImage ? (
                                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                ) : (
                                                    <Upload className="mr-2 h-4 w-4" />
                                                )}
                                                {t('Upload Image')}
                                            </Button>
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label>{t('Icon')}</Label>
                                        <div className="grid grid-cols-3 gap-2">
                                            <Button
                                                type="button"
                                                variant={categoryForm.iconKey === '' ? 'default' : 'outline'}
                                                className="h-auto justify-start gap-2 px-2 py-2"
                                                onClick={() => setCategoryForm((prev) => ({ ...prev, iconKey: '' }))}
                                            >
                                                <div className="h-7 w-7 rounded-md border bg-muted/40" />
                                                <span className="text-xs">{t('None')}</span>
                                            </Button>
                                            {CATEGORY_ICON_OPTIONS.map((iconOption) => {
                                                const IconComponent = iconOption.icon;

                                                return (
                                                    <Button
                                                        key={`category-icon-${iconOption.key}`}
                                                        type="button"
                                                        variant={categoryForm.iconKey === iconOption.key ? 'default' : 'outline'}
                                                        className="h-auto justify-start gap-2 px-2 py-2"
                                                        onClick={() => setCategoryForm((prev) => ({ ...prev, iconKey: iconOption.key }))}
                                                        title={iconOption.label}
                                                    >
                                                        <span className="flex h-7 w-7 items-center justify-center rounded-md border">
                                                            <IconComponent className="h-4 w-4" />
                                                        </span>
                                                        <span className="truncate text-xs">{iconOption.label}</span>
                                                    </Button>
                                                );
                                            })}
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        <Button type="submit" disabled={isSavingCategory}>
                                            {isSavingCategory ? (
                                                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                            ) : (
                                                <CheckCircle2 className="h-4 w-4 mr-2" />
                                            )}
                                            {editingCategoryId === null ? t('Create Category') : t('Update Category')}
                                        </Button>
                                        <Button type="button" variant="outline" onClick={resetCategoryForm}>
                                            {t('Clear')}
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex-row items-center justify-between space-y-0">
                                <div>
                                    <CardTitle>{t('Categories')}</CardTitle>
                                    <CardDescription>{t('All ecommerce categories for this site')}</CardDescription>
                                </div>
                                <Button variant="outline" size="sm" onClick={() => void loadCategories()} disabled={isCategoriesLoading}>
                                    {isCategoriesLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                                </Button>
                            </CardHeader>
                            <CardContent>
                                {categories.length === 0 ? (
                                    <div className="py-10 text-sm text-muted-foreground text-center">
                                        {isCategoriesLoading ? t('Loading categories...') : t('No categories yet')}
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        {categories.map((category) => (
                                            <div key={category.id} className="rounded-lg border p-3 flex flex-wrap gap-3 items-start justify-between">
                                                {(() => {
                                                    const imageUrl = typeof category.meta_json?.image_url === 'string'
                                                        ? category.meta_json.image_url
                                                        : '';
                                                    const iconKey = typeof category.meta_json?.icon_key === 'string'
                                                        ? category.meta_json.icon_key
                                                        : '';
                                                    const CategoryIcon = iconKey ? CATEGORY_ICON_MAP[iconKey] : undefined;

                                                    return (
                                                <div className="min-w-0 flex items-start gap-3">
                                                    {imageUrl.trim() !== '' ? (
                                                        <img
                                                            src={imageUrl}
                                                            alt={category.name}
                                                            className="h-12 w-12 rounded-md border object-cover"
                                                        />
                                                    ) : CategoryIcon ? (
                                                        <div className="h-12 w-12 rounded-md border bg-muted/30 flex items-center justify-center">
                                                            <CategoryIcon className="h-5 w-5 text-muted-foreground" />
                                                        </div>
                                                    ) : (
                                                        <div className="h-12 w-12 rounded-md border bg-muted/30" />
                                                    )}
                                                    <div className="min-w-0">
                                                    <p className="text-sm font-medium">{category.name}</p>
                                                    <p className="text-xs text-muted-foreground">/{category.slug}</p>
                                                    <p className="text-xs text-muted-foreground mt-1">
                                                        {t('Sort')}: {category.sort_order} · {t('Updated')}: {formatDate(category.updated_at)}
                                                    </p>
                                                    {category.description ? (
                                                        <p className="text-xs text-muted-foreground mt-1 line-clamp-2">{category.description}</p>
                                                    ) : null}
                                                    </div>
                                                </div>
                                                    );
                                                })()}
                                                <div className="flex items-center gap-2">
                                                    <Badge variant={statusBadgeVariant(category.status)}>{category.status}</Badge>
                                                    <Button variant="outline" size="sm" onClick={() => handleCategoryEdit(category)}>
                                                        <Pencil className="h-4 w-4 mr-1.5" />
                                                        {t('Edit')}
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-destructive"
                                                        onClick={() => void handleCategoryDelete(category)}
                                                        disabled={deletingCategoryId === category.id}
                                                    >
                                                        {deletingCategoryId === category.id ? (
                                                            <Loader2 className="h-4 w-4 animate-spin" />
                                                        ) : (
                                                            <Trash2 className="h-4 w-4" />
                                                        )}
                                                    </Button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <TabsContent value="products" className="min-w-0 max-w-full space-y-4 pt-4 overflow-x-hidden">
                    <CmsEcommerceProductManager siteId={siteId} mode={productMode} />
                </TabsContent>

                <TabsContent value="discounts" className="min-w-0 max-w-full space-y-4 pt-4 overflow-x-hidden">
                    <div className="grid gap-4 xl:grid-cols-[440px_1fr]">
                        <div className="space-y-4">
                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        {editingDiscountId === null ? t('Add Discount') : t('Edit Discount')}
                                    </CardTitle>
                                    <CardDescription>{t('Create discount rules for products')}</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form className="space-y-3" onSubmit={handleDiscountSubmit}>
                                        <div className="space-y-2">
                                            <Label>{t('Name')}</Label>
                                            <Input
                                                value={discountForm.name}
                                                onChange={(event) => setDiscountForm((prev) => ({
                                                    ...prev,
                                                    name: event.target.value,
                                                }))}
                                                placeholder={t('Discount name')}
                                                required
                                            />
                                        </div>

                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label>{t('Code')}</Label>
                                                <Input
                                                    value={discountForm.code}
                                                    onChange={(event) => setDiscountForm((prev) => ({
                                                        ...prev,
                                                        code: event.target.value.toUpperCase().replace(/\s+/g, '-'),
                                                    }))}
                                                    placeholder="SALE10"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>{t('Status')}</Label>
                                                <select
                                                    value={discountForm.status}
                                                    onChange={(event) => setDiscountForm((prev) => ({
                                                        ...prev,
                                                        status: event.target.value as DiscountStatus,
                                                    }))}
                                                    className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                >
                                                    {DISCOUNT_STATUS_OPTIONS.map((status) => (
                                                        <option key={`discount-status-${status}`} value={status}>
                                                            {t(status)}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                        </div>

                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label>{t('Type')}</Label>
                                                <select
                                                    value={discountForm.type}
                                                    onChange={(event) => setDiscountForm((prev) => ({
                                                        ...prev,
                                                        type: event.target.value as DiscountType,
                                                    }))}
                                                    className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                >
                                                    {DISCOUNT_TYPE_OPTIONS.map((type) => (
                                                        <option key={`discount-type-${type}`} value={type}>
                                                            {type === 'percent' ? t('Percent') : t('Fixed Amount')}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div className="space-y-2">
                                                <Label>
                                                    {discountForm.type === 'percent' ? t('Percent') : t('Amount')}
                                                </Label>
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    value={discountForm.value}
                                                    onChange={(event) => setDiscountForm((prev) => ({
                                                        ...prev,
                                                        value: event.target.value,
                                                    }))}
                                                    placeholder={discountForm.type === 'percent' ? '10' : '25'}
                                                    required
                                                />
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <Label>{t('Apply To')}</Label>
                                            <select
                                                value={discountForm.scope}
                                                onChange={(event) => setDiscountForm((prev) => ({
                                                    ...prev,
                                                    scope: event.target.value as DiscountScope,
                                                }))}
                                                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                            >
                                                <option value="all_products">{t('All Products')}</option>
                                                <option value="specific_products">{t('Selected Products')}</option>
                                            </select>
                                        </div>

                                        {discountForm.scope === 'specific_products' ? (
                                            <div className="space-y-2">
                                                <div className="flex items-center justify-between gap-2">
                                                    <Label>{t('Products')}</Label>
                                                    <div className="flex items-center gap-2 text-xs">
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-7 px-2"
                                                            onClick={() => setDiscountForm((prev) => ({
                                                                ...prev,
                                                                productIds: products.map((product) => product.id),
                                                            }))}
                                                        >
                                                            {t('Select All')}
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-7 px-2"
                                                            onClick={() => setDiscountForm((prev) => ({ ...prev, productIds: [] }))}
                                                        >
                                                            {t('Clear')}
                                                        </Button>
                                                    </div>
                                                </div>
                                                <div className="max-h-48 space-y-2 overflow-auto rounded-md border p-2">
                                                    {products.length === 0 ? (
                                                        <p className="text-xs text-muted-foreground">
                                                            {t('No products found')}
                                                        </p>
                                                    ) : (
                                                        products.map((product) => (
                                                            <label
                                                                key={`discount-form-product-${product.id}`}
                                                                className="flex cursor-pointer items-start gap-2 rounded-md border p-2 text-sm"
                                                            >
                                                                <input
                                                                    type="checkbox"
                                                                    className="mt-0.5 h-4 w-4"
                                                                    checked={discountForm.productIds.includes(product.id)}
                                                                    onChange={() => toggleDiscountFormProduct(product.id)}
                                                                />
                                                                <span className="min-w-0">
                                                                    <span className="block truncate font-medium">
                                                                        {product.name}
                                                                    </span>
                                                                    <span className="block truncate text-xs text-muted-foreground">
                                                                        /{product.slug} · {formatCurrencyAmount(product.price, product.currency)}
                                                                    </span>
                                                                </span>
                                                            </label>
                                                        ))
                                                    )}
                                                </div>
                                            </div>
                                        ) : null}

                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label>{t('Start')}</Label>
                                                <Input
                                                    type="datetime-local"
                                                    value={discountForm.startsAt}
                                                    onChange={(event) => setDiscountForm((prev) => ({
                                                        ...prev,
                                                        startsAt: event.target.value,
                                                    }))}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>{t('End')}</Label>
                                                <Input
                                                    type="datetime-local"
                                                    value={discountForm.endsAt}
                                                    onChange={(event) => setDiscountForm((prev) => ({
                                                        ...prev,
                                                        endsAt: event.target.value,
                                                    }))}
                                                />
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <Label>{t('Notes')}</Label>
                                            <Textarea
                                                value={discountForm.notes}
                                                onChange={(event) => setDiscountForm((prev) => ({
                                                    ...prev,
                                                    notes: event.target.value,
                                                }))}
                                                rows={3}
                                                placeholder={t('Optional note')}
                                            />
                                        </div>

                                        <div className="flex flex-wrap gap-2">
                                            <Button type="submit" disabled={isSavingDiscount}>
                                                {isSavingDiscount ? (
                                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                ) : (
                                                    <CheckCircle2 className="mr-2 h-4 w-4" />
                                                )}
                                                {editingDiscountId === null ? t('Save') : t('Update')}
                                            </Button>
                                            <Button type="button" variant="outline" onClick={resetDiscountForm}>
                                                {t('Clear')}
                                            </Button>
                                        </div>
                                    </form>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('Bulk Discount')}</CardTitle>
                                    <CardDescription>{t('Apply discount to multiple products at once')}</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label>{t('Type')}</Label>
                                            <select
                                                value={bulkDiscountForm.type}
                                                onChange={(event) => setBulkDiscountForm((prev) => ({
                                                    ...prev,
                                                    type: event.target.value as DiscountType,
                                                }))}
                                                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                            >
                                                <option value="percent">{t('Percent')}</option>
                                                <option value="fixed">{t('Fixed Amount')}</option>
                                            </select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label>{t('Value')}</Label>
                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={bulkDiscountForm.value}
                                                onChange={(event) => setBulkDiscountForm((prev) => ({
                                                    ...prev,
                                                    value: event.target.value,
                                                }))}
                                                placeholder={bulkDiscountForm.type === 'percent' ? '10' : '25'}
                                            />
                                        </div>
                                    </div>

                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            className="h-4 w-4"
                                            checked={bulkDiscountForm.createRecord}
                                            onChange={(event) => setBulkDiscountForm((prev) => ({
                                                ...prev,
                                                createRecord: event.target.checked,
                                            }))}
                                        />
                                        <span>{t('Save as discount rule')}</span>
                                    </label>

                                    {bulkDiscountForm.createRecord ? (
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div className="space-y-2 sm:col-span-2">
                                                <Label>{t('Name')}</Label>
                                                <Input
                                                    value={bulkDiscountForm.name}
                                                    onChange={(event) => setBulkDiscountForm((prev) => ({
                                                        ...prev,
                                                        name: event.target.value,
                                                    }))}
                                                    placeholder={t('Bulk discount name')}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>{t('Code')}</Label>
                                                <Input
                                                    value={bulkDiscountForm.code}
                                                    onChange={(event) => setBulkDiscountForm((prev) => ({
                                                        ...prev,
                                                        code: event.target.value.toUpperCase().replace(/\s+/g, '-'),
                                                    }))}
                                                    placeholder="BULK10"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>{t('Status')}</Label>
                                                <select
                                                    value={bulkDiscountForm.status}
                                                    onChange={(event) => setBulkDiscountForm((prev) => ({
                                                        ...prev,
                                                        status: event.target.value as DiscountStatus,
                                                    }))}
                                                    className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                >
                                                    {DISCOUNT_STATUS_OPTIONS.map((status) => (
                                                        <option key={`bulk-discount-status-${status}`} value={status}>
                                                            {t(status)}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                        </div>
                                    ) : null}

                                    <div className="flex items-center justify-between gap-2">
                                        <Label>{t('Selected Products')}</Label>
                                        <span className="text-xs text-muted-foreground">
                                            {bulkSelectedProductIds.length} / {products.length}
                                        </span>
                                    </div>
                                    <div className="max-h-56 space-y-2 overflow-auto rounded-md border p-2">
                                        {products.length === 0 ? (
                                            <p className="text-xs text-muted-foreground">{t('No products found')}</p>
                                        ) : (
                                            products.map((product) => {
                                                const checked = bulkSelectedProductIds.includes(product.id);
                                                return (
                                                    <label
                                                        key={`bulk-discount-product-${product.id}`}
                                                        className="flex cursor-pointer items-start gap-2 rounded-md border p-2 text-sm"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            className="mt-0.5 h-4 w-4"
                                                            checked={checked}
                                                            onChange={() => toggleBulkSelectedProduct(product.id)}
                                                        />
                                                        <span className="min-w-0">
                                                            <span className="block truncate font-medium">{product.name}</span>
                                                            <span className="block truncate text-xs text-muted-foreground">
                                                                {product.category_name || t('No Category')} · {formatCurrencyAmount(product.price, product.currency)}
                                                            </span>
                                                        </span>
                                                    </label>
                                                );
                                            })
                                        )}
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setBulkSelectedProductIds(products.map((product) => product.id))}
                                        >
                                            {t('Select All')}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setBulkSelectedProductIds([])}
                                        >
                                            {t('Clear')}
                                        </Button>
                                        <Button
                                            type="button"
                                            onClick={() => void handleBulkApplyDiscount()}
                                            disabled={isApplyingBulkDiscount}
                                        >
                                            {isApplyingBulkDiscount ? (
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            ) : (
                                                <Tag className="mr-2 h-4 w-4" />
                                            )}
                                            {t('Apply')}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <Card>
                            <CardHeader className="flex-row items-center justify-between space-y-0">
                                <div>
                                    <CardTitle>{t('Discounts')}</CardTitle>
                                    <CardDescription>{t('Manage active and scheduled discounts')}</CardDescription>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => void loadDiscounts()}
                                    disabled={isDiscountsLoading}
                                >
                                    {isDiscountsLoading ? (
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                    ) : (
                                        <RefreshCw className="h-4 w-4" />
                                    )}
                                </Button>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <div className="rounded-lg border p-3">
                                        <p className="text-xs text-muted-foreground">{t('Total')}</p>
                                        <p className="text-xl font-semibold">{discounts.length}</p>
                                    </div>
                                    <div className="rounded-lg border p-3">
                                        <p className="text-xs text-muted-foreground">{t('Active')}</p>
                                        <p className="text-xl font-semibold">
                                            {discounts.filter((discount) => discount.status === 'active').length}
                                        </p>
                                    </div>
                                    <div className="rounded-lg border p-3">
                                        <p className="text-xs text-muted-foreground">{t('Draft')}</p>
                                        <p className="text-xl font-semibold">
                                            {discounts.filter((discount) => discount.status === 'draft').length}
                                        </p>
                                    </div>
                                </div>

                                {discounts.length === 0 ? (
                                    <div className="py-12 text-center text-sm text-muted-foreground">
                                        {isDiscountsLoading ? t('Loading discounts...') : t('No discounts yet')}
                                    </div>
                                ) : (
                                    <div className="overflow-x-auto rounded-lg border">
                                        <table className="w-full min-w-[980px] text-sm">
                                            <thead className="bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                                <tr>
                                                    <th className="px-3 py-2 text-left">{t('Name')}</th>
                                                    <th className="px-3 py-2 text-left">{t('Code')}</th>
                                                    <th className="px-3 py-2 text-left">{t('Type')}</th>
                                                    <th className="px-3 py-2 text-left">{t('Value')}</th>
                                                    <th className="px-3 py-2 text-left">{t('Apply To')}</th>
                                                    <th className="px-3 py-2 text-left">{t('Status')}</th>
                                                    <th className="px-3 py-2 text-left">{t('Period')}</th>
                                                    <th className="px-3 py-2 text-left">{t('Updated')}</th>
                                                    <th className="px-3 py-2 text-right">{t('Actions')}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {discounts.map((discount) => (
                                                    <tr key={`discount-row-${discount.id}`} className="border-t align-top">
                                                        <td className="px-3 py-2">
                                                            <div className="font-medium">{discount.name}</div>
                                                            {discount.notes ? (
                                                                <div className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                                                                    {discount.notes}
                                                                </div>
                                                            ) : null}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {discount.code ? (
                                                                <Badge variant="outline">{discount.code}</Badge>
                                                            ) : (
                                                                <span className="text-muted-foreground">—</span>
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <Badge variant="outline">
                                                                {discount.type === 'percent' ? t('Percent') : t('Fixed Amount')}
                                                            </Badge>
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {discount.type === 'percent'
                                                                ? `${discount.value}%`
                                                                : formatCurrencyAmount(discount.value, 'GEL')}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {discount.scope === 'all_products' ? (
                                                                <span>{t('All Products')}</span>
                                                            ) : (
                                                                <span>
                                                                    {t('Selected Products')}: {Array.isArray(discount.product_ids_json) ? discount.product_ids_json.length : 0}
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <Badge variant={statusBadgeVariant(discount.status)}>{t(discount.status)}</Badge>
                                                        </td>
                                                        <td className="px-3 py-2 text-xs text-muted-foreground">
                                                            <div>{discount.starts_at ? formatDate(discount.starts_at) : t('No start')}</div>
                                                            <div>{discount.ends_at ? formatDate(discount.ends_at) : t('No end')}</div>
                                                        </td>
                                                        <td className="px-3 py-2 text-xs text-muted-foreground">
                                                            {formatDate(discount.updated_at)}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <div className="flex justify-end gap-2">
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    size="sm"
                                                                    onClick={() => handleDiscountEdit(discount)}
                                                                >
                                                                    <Pencil className="mr-1.5 h-4 w-4" />
                                                                    {t('Edit')}
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="text-destructive"
                                                                    onClick={() => void handleDiscountDelete(discount)}
                                                                    disabled={deletingDiscountId === discount.id}
                                                                >
                                                                    {deletingDiscountId === discount.id ? (
                                                                        <Loader2 className="h-4 w-4 animate-spin" />
                                                                    ) : (
                                                                        <Trash2 className="h-4 w-4" />
                                                                    )}
                                                                </Button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <TabsContent value="attributes" className="min-w-0 max-w-full space-y-4 pt-4 overflow-x-hidden">
                    <CmsEcommerceMetadataPanel siteId={siteId} mode="attributes" />
                </TabsContent>

                <TabsContent value="attributeValues" className="min-w-0 max-w-full space-y-4 pt-4 overflow-x-hidden">
                    <CmsEcommerceMetadataPanel siteId={siteId} mode="attributeValues" />
                </TabsContent>

                <TabsContent value="variants" className="min-w-0 max-w-full space-y-4 pt-4 overflow-x-hidden">
                    <CmsEcommerceMetadataPanel siteId={siteId} mode="variants" />
                </TabsContent>

                <TabsContent value="inventory" className="min-w-0 max-w-full space-y-4 pt-4 overflow-x-hidden">
                    <div className="flex justify-end">
                        <Button variant="outline" size="sm" onClick={() => void loadInventory()} disabled={isInventoryLoading}>
                            {isInventoryLoading ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <RefreshCw className="h-4 w-4 mr-2" />}
                            {t('Refresh Inventory')}
                        </Button>
                    </div>

                    <div className="grid gap-3 md:grid-cols-5">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm">{t('Items')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-2xl font-semibold">{inventorySummary?.items_count ?? 0}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm">{t('On Hand')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-2xl font-semibold">{inventorySummary?.on_hand_total ?? 0}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm">{t('Reserved')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-2xl font-semibold">{inventorySummary?.reserved_total ?? 0}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm">{t('Available')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-2xl font-semibold">{inventorySummary?.available_total ?? 0}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm">{t('Low Stock')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-2xl font-semibold">{inventorySummary?.low_stock_count ?? 0}</p>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="grid gap-4 xl:grid-cols-[420px_1fr]">
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {editingInventoryLocationId === null ? t('Create Location') : t('Edit Location')}
                                </CardTitle>
                                <CardDescription>{t('Manage inventory warehouses and branches')}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form className="space-y-3" onSubmit={handleInventoryLocationSubmit}>
                                    <div className="grid gap-3 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label>{t('Name')}</Label>
                                            <Input
                                                value={inventoryLocationForm.name}
                                                onChange={(event) => {
                                                    const value = event.target.value;
                                                    setInventoryLocationForm((prev) => ({
                                                        ...prev,
                                                        name: value,
                                                        key: prev.key === '' ? slugify(value) : prev.key,
                                                    }));
                                                }}
                                                placeholder={t('Main warehouse')}
                                                required
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>{t('Key')}</Label>
                                            <Input
                                                value={inventoryLocationForm.key}
                                                onChange={(event) => setInventoryLocationForm((prev) => ({
                                                    ...prev,
                                                    key: slugify(event.target.value),
                                                }))}
                                                placeholder="main"
                                                required
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label>{t('Status')}</Label>
                                        <select
                                            value={inventoryLocationForm.status}
                                            onChange={(event) => setInventoryLocationForm((prev) => ({
                                                ...prev,
                                                status: event.target.value as InventoryLocationStatus,
                                            }))}
                                            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                        >
                                            {INVENTORY_LOCATION_STATUS_OPTIONS.map((status) => (
                                                <option key={status} value={status}>
                                                    {status}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <label className="rounded-md border p-3 flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            className="h-4 w-4"
                                            checked={inventoryLocationForm.isDefault}
                                            onChange={(event) => setInventoryLocationForm((prev) => ({
                                                ...prev,
                                                isDefault: event.target.checked,
                                            }))}
                                        />
                                        {t('Set as default location')}
                                    </label>

                                    <div className="space-y-2">
                                        <Label>{t('Notes')}</Label>
                                        <Textarea
                                            value={inventoryLocationForm.notes}
                                            onChange={(event) => setInventoryLocationForm((prev) => ({
                                                ...prev,
                                                notes: event.target.value,
                                            }))}
                                            rows={3}
                                            placeholder={t('Optional notes for fulfillment team')}
                                        />
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        <Button type="submit" disabled={isSavingInventoryLocation}>
                                            {isSavingInventoryLocation ? (
                                                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                            ) : (
                                                <CheckCircle2 className="h-4 w-4 mr-2" />
                                            )}
                                            {editingInventoryLocationId === null ? t('Create Location') : t('Update Location')}
                                        </Button>
                                        <Button type="button" variant="outline" onClick={resetInventoryLocationForm}>
                                            {t('Clear')}
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        <div className="space-y-4">
                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('Locations')}</CardTitle>
                                    <CardDescription>{t('Stock distribution by location')}</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {inventoryLocations.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            {isInventoryLoading ? t('Loading locations...') : t('No locations yet')}
                                        </p>
                                    ) : (
                                        <div className="space-y-2">
                                            {inventoryLocations.map((location) => (
                                                <div key={location.id} className="rounded-lg border p-3 flex flex-wrap items-start justify-between gap-2">
                                                    <div>
                                                        <p className="text-sm font-medium">{location.name}</p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {location.key}
                                                            {' · '}
                                                            {t('Items')}: {location.items_count}
                                                            {' · '}
                                                            {t('Available')}: {location.available_total}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {t('On Hand')}: {location.on_hand_total}
                                                            {' · '}
                                                            {t('Reserved')}: {location.reserved_total}
                                                        </p>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        {location.is_default ? (
                                                            <Badge variant="default">{t('Default')}</Badge>
                                                        ) : (
                                                            <Badge variant="outline">{location.status}</Badge>
                                                        )}
                                                        <Button variant="outline" size="sm" onClick={() => handleInventoryLocationEdit(location)}>
                                                            <Pencil className="h-4 w-4 mr-1.5" />
                                                            {t('Edit')}
                                                        </Button>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('Low Stock Alerts')}</CardTitle>
                                    <CardDescription>{t('Items at or below threshold')}</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {lowStockItems.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">{t('No low stock alerts')}</p>
                                    ) : (
                                        <div className="space-y-2">
                                            {lowStockItems.map((item) => (
                                                <div key={item.id} className="rounded-md border p-2 text-sm">
                                                    <p className="font-medium">{item.product_name ?? t('Unknown product')}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {t('Available')}: {item.available_quantity}
                                                        {' · '}
                                                        {t('Threshold')}: {item.low_stock_threshold ?? 0}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {t('Location')}: {item.location_name ?? t('Unassigned')}
                                                    </p>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    <div className="grid gap-4 xl:grid-cols-[2fr_1fr]">
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('Inventory Items')}</CardTitle>
                                <CardDescription>{t('Adjust stock, stocktake and low-stock thresholds')}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {inventoryItems.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {isInventoryLoading ? t('Loading inventory items...') : t('No inventory items yet')}
                                    </p>
                                ) : (
                                    <div className="space-y-3">
                                        {inventoryItems.map((item) => {
                                            const form = inventoryItemForms[item.id] ?? createInventoryItemFormState(item);
                                            const isSavingSettings = savingInventoryItemSettingsId === item.id;
                                            const isAdjusting = adjustingInventoryItemId === item.id;
                                            const isStocktaking = stocktakingInventoryItemId === item.id;

                                            return (
                                                <div key={item.id} className="rounded-lg border p-3 space-y-3">
                                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                                        <div>
                                                            <p className="text-sm font-medium">
                                                                {item.product_name ?? t('Unknown product')}
                                                                {item.variant_name ? ` / ${item.variant_name}` : ''}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {t('SKU')}: {item.sku ?? '—'}
                                                                {' · '}
                                                                {t('Location')}: {item.location_name ?? t('Unassigned')}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {t('On Hand')}: {item.quantity_on_hand}
                                                                {' · '}
                                                                {t('Reserved')}: {item.quantity_reserved}
                                                                {' · '}
                                                                {t('Available')}: {item.available_quantity}
                                                            </p>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            {item.stock_tracking ? (
                                                                <Badge variant="outline">{t('Tracked')}</Badge>
                                                            ) : (
                                                                <Badge variant="secondary">{t('Tracking Disabled')}</Badge>
                                                            )}
                                                            {item.is_low_stock ? (
                                                                <Badge variant="secondary">{t('Low Stock')}</Badge>
                                                            ) : null}
                                                        </div>
                                                    </div>

                                                    <div className="grid gap-3 md:grid-cols-[1.2fr_1fr_auto]">
                                                        <div className="space-y-2">
                                                            <Label>{t('Location')}</Label>
                                                            <select
                                                                value={form.locationId}
                                                                onChange={(event) => updateInventoryItemForm(item.id, (current) => ({
                                                                    ...current,
                                                                    locationId: event.target.value,
                                                                }))}
                                                                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                            >
                                                                <option value="">{t('Default location')}</option>
                                                                {inventoryLocations.map((location) => (
                                                                    <option key={location.id} value={String(location.id)}>
                                                                        {location.name} ({location.key})
                                                                    </option>
                                                                ))}
                                                            </select>
                                                        </div>
                                                        <div className="space-y-2">
                                                            <Label>{t('Low Stock Threshold')}</Label>
                                                            <Input
                                                                type="number"
                                                                min={0}
                                                                value={form.lowStockThreshold}
                                                                onChange={(event) => updateInventoryItemForm(item.id, (current) => ({
                                                                    ...current,
                                                                    lowStockThreshold: event.target.value,
                                                                }))}
                                                                placeholder="0"
                                                            />
                                                        </div>
                                                        <div className="flex items-end">
                                                            <Button
                                                                type="button"
                                                                onClick={() => void handleInventorySettingsSave(item.id)}
                                                                disabled={isSavingSettings}
                                                            >
                                                                {isSavingSettings ? <Loader2 className="h-4 w-4 animate-spin" /> : t('Save')}
                                                            </Button>
                                                        </div>
                                                    </div>

                                                    <div className="grid gap-3 md:grid-cols-[1fr_1fr_auto]">
                                                        <div className="space-y-2">
                                                            <Label>{t('Adjust Quantity Delta')}</Label>
                                                            <Input
                                                                value={form.quantityDelta}
                                                                onChange={(event) => updateInventoryItemForm(item.id, (current) => ({
                                                                    ...current,
                                                                    quantityDelta: event.target.value,
                                                                }))}
                                                                placeholder="-5 / +8"
                                                            />
                                                        </div>
                                                        <div className="space-y-2">
                                                            <Label>{t('Adjustment Reason')}</Label>
                                                            <Input
                                                                value={form.adjustmentReason}
                                                                onChange={(event) => updateInventoryItemForm(item.id, (current) => ({
                                                                    ...current,
                                                                    adjustmentReason: event.target.value,
                                                                }))}
                                                                placeholder={t('damaged, returned, correction')}
                                                            />
                                                        </div>
                                                        <div className="flex items-end">
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                onClick={() => void handleInventoryAdjustment(item.id)}
                                                                disabled={isAdjusting}
                                                            >
                                                                {isAdjusting ? <Loader2 className="h-4 w-4 animate-spin" /> : t('Apply Adjustment')}
                                                            </Button>
                                                        </div>
                                                    </div>

                                                    <div className="grid gap-3 md:grid-cols-[1fr_auto]">
                                                        <div className="space-y-2">
                                                            <Label>{t('Stocktake Counted Quantity')}</Label>
                                                            <Input
                                                                type="number"
                                                                min={0}
                                                                value={form.countedQuantity}
                                                                onChange={(event) => updateInventoryItemForm(item.id, (current) => ({
                                                                    ...current,
                                                                    countedQuantity: event.target.value,
                                                                }))}
                                                            />
                                                        </div>
                                                        <div className="flex items-end">
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                onClick={() => void handleInventoryStocktake(item.id)}
                                                                disabled={isStocktaking}
                                                            >
                                                                {isStocktaking ? <Loader2 className="h-4 w-4 animate-spin" /> : t('Apply Stocktake')}
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>{t('Recent Stock Movements')}</CardTitle>
                                <CardDescription>{t('Latest inventory ledger activity')}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {inventoryMovements.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">{t('No inventory movements yet')}</p>
                                ) : (
                                    <div className="space-y-2">
                                        {inventoryMovements.map((movement) => (
                                            <div key={movement.id} className="rounded-md border p-2 text-xs">
                                                <p className="font-medium text-sm">
                                                    {movement.product_name ?? t('Unknown product')}
                                                    {movement.variant_name ? ` / ${movement.variant_name}` : ''}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {movement.movement_type}
                                                    {' · '}
                                                    {movement.reason ?? t('n/a')}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    Δ {movement.quantity_delta}
                                                    {' · '}
                                                    {t('Reserved Δ')}: {movement.reserved_delta}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {t('After')}: {movement.quantity_on_hand_after}
                                                    {' / '}
                                                    {movement.quantity_reserved_after}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {movement.created_by_name ?? t('System')}
                                                    {' · '}
                                                    {formatDate(movement.created_at)}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <TabsContent value="customers" className="min-w-0 max-w-full space-y-4 pt-4 overflow-x-hidden">
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <Card className="border-sky-200/70 bg-sky-50/60 shadow-none">
                            <CardContent className="p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="text-xs text-muted-foreground">{t('Total Customers')}</p>
                                        <p className="text-2xl font-semibold">{customerSummary.total}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {hasCustomerFilters ? `${t('Filtered')} / ${customerRows.length}` : t('All customers')}
                                        </p>
                                    </div>
                                    <div className="rounded-xl border border-sky-200 bg-white p-2 text-sky-600">
                                        <Users className="h-5 w-5" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-emerald-200/70 bg-emerald-50/60 shadow-none">
                            <CardContent className="p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="text-xs text-muted-foreground">{t('Active Customers')}</p>
                                        <p className="text-2xl font-semibold">{customerSummary.active}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {t('Repeat')}: {customerSummary.repeat}
                                        </p>
                                    </div>
                                    <div className="rounded-xl border border-emerald-200 bg-white p-2 text-emerald-600">
                                        <UserCheck className="h-5 w-5" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-violet-200/70 bg-violet-50/60 shadow-none">
                            <CardContent className="p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="text-xs text-muted-foreground">{t('VIP Customers')}</p>
                                        <p className="text-2xl font-semibold">{customerSummary.vip}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {t('New')}: {customerSummary.newlyAdded}
                                        </p>
                                    </div>
                                    <div className="rounded-xl border border-violet-200 bg-white p-2 text-violet-600">
                                        <UserPlus className="h-5 w-5" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-amber-200/70 bg-amber-50/60 shadow-none">
                            <CardContent className="p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="text-xs text-muted-foreground">{t('Customer Revenue')}</p>
                                        <p className="text-lg font-semibold">
                                            {formatCurrencyAmount(customerSummary.revenue, customerSummary.currency)}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {t('At Risk')}: {filteredCustomerRows.filter((row) => row.status === 'at_risk').length}
                                        </p>
                                    </div>
                                    <div className="rounded-xl border border-amber-200 bg-white p-2 text-amber-600">
                                        <Banknote className="h-5 w-5" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <Card className="overflow-hidden border-muted/70 shadow-sm">
                        <CardHeader className="space-y-4 border-b bg-muted/20">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <CardTitle>{t('Customers')}</CardTitle>
                                    <CardDescription>{t('Customer list, activity and latest orders')}</CardDescription>
                                </div>
                                <Button variant="outline" size="sm" onClick={() => void loadOrders()} disabled={isOrdersLoading}>
                                    {isOrdersLoading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <RefreshCw className="mr-2 h-4 w-4" />}
                                    {t('Refresh')}
                                </Button>
                            </div>

                            <div className="grid gap-2 lg:grid-cols-[minmax(220px,1fr)_180px_180px]">
                                <div className="relative">
                                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        value={customerSearchQuery}
                                        onChange={(event) => setCustomerSearchQuery(event.target.value)}
                                        placeholder={t('Search user, email or order')}
                                        className="pl-9"
                                    />
                                </div>
                                <select
                                    value={customerSegmentFilter}
                                    onChange={(event) => setCustomerSegmentFilter(event.target.value as 'all' | 'vip' | 'active' | 'new' | 'at_risk')}
                                    className="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="all">{t('All Segments')}</option>
                                    <option value="vip">{t('VIP')}</option>
                                    <option value="active">{t('Active')}</option>
                                    <option value="new">{t('New')}</option>
                                    <option value="at_risk">{t('At Risk')}</option>
                                </select>
                                <select
                                    value={customerOrderActivityFilter}
                                    onChange={(event) => setCustomerOrderActivityFilter(event.target.value as 'all' | 'single' | 'repeat')}
                                    className="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="all">{t('All Activity')}</option>
                                    <option value="single">{t('Single Order')}</option>
                                    <option value="repeat">{t('Repeat Orders')}</option>
                                </select>
                            </div>
                        </CardHeader>

                        <CardContent className="p-0">
                            {customerRows.length === 0 ? (
                                <div className="py-10 text-center text-sm text-muted-foreground">
                                    {isOrdersLoading ? t('Loading customers...') : t('No customers yet')}
                                </div>
                            ) : filteredCustomerRows.length === 0 ? (
                                <div className="py-10 text-center text-sm text-muted-foreground">
                                    {t('No customers match the current filters')}
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-[1100px] w-full text-sm">
                                        <thead className="bg-background">
                                            <tr className="border-b text-left text-xs uppercase tracking-wide text-muted-foreground">
                                                <th className="px-4 py-3 font-medium">{t('User')}</th>
                                                <th className="px-4 py-3 font-medium">{t('Contact')}</th>
                                                <th className="px-4 py-3 font-medium">{t('Orders')}</th>
                                                <th className="px-4 py-3 font-medium">{t('Spending')}</th>
                                                <th className="px-4 py-3 font-medium">{t('Status')}</th>
                                                <th className="px-4 py-3 font-medium">{t('Latest Order')}</th>
                                                <th className="px-4 py-3 text-right font-medium">{t('Actions')}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {filteredCustomerRows.map((customer) => (
                                                <tr key={customer.key} className="border-b align-top transition hover:bg-muted/20">
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-start gap-3">
                                                            <Avatar className="h-10 w-10 border">
                                                                <AvatarFallback className="text-xs">
                                                                    {textInitials(customer.name || customer.email)}
                                                                </AvatarFallback>
                                                            </Avatar>
                                                            <div className="min-w-0">
                                                                <div className="truncate font-semibold">{customer.name}</div>
                                                                <div className="text-xs text-muted-foreground">
                                                                    {t('Customer')}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td className="px-4 py-3">
                                                        <div className="space-y-1 text-xs">
                                                            <div className="flex items-center gap-2 text-muted-foreground">
                                                                <Mail className="h-3.5 w-3.5" />
                                                                <span className="truncate">{customer.email || '—'}</span>
                                                            </div>
                                                            <div className="flex items-center gap-2 text-muted-foreground">
                                                                <Phone className="h-3.5 w-3.5" />
                                                                <span>{customer.phone || '—'}</span>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td className="px-4 py-3">
                                                        <div className="space-y-1">
                                                            <div className="font-semibold">{customer.ordersCount}</div>
                                                            <div className="text-xs text-muted-foreground">
                                                                {customer.ordersCount >= 2 ? t('Repeat Orders') : t('Single Order')}
                                                            </div>
                                                            <div className="text-xs text-muted-foreground">
                                                                {t('First')}: {formatDate(customer.firstOrderAt)}
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td className="px-4 py-3">
                                                        <div className="space-y-1">
                                                            <div className="font-semibold">
                                                                {formatCurrencyAmount(customer.totalSpent, customer.currency)}
                                                            </div>
                                                            <div className="text-xs text-emerald-600">
                                                                {t('Paid')}: {formatCurrencyAmount(customer.paidSpent, customer.currency)}
                                                            </div>
                                                            <div className="text-xs text-amber-600">
                                                                {t('Outstanding')}: {formatCurrencyAmount(customer.outstanding, customer.currency)}
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td className="px-4 py-3">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <Badge variant={customerStatusBadgeVariant(customer.status)} className="capitalize">
                                                                {t(customer.status)}
                                                            </Badge>
                                                            <Badge variant={orderStatusBadgeVariant(customer.latestOrderStatus)} className="capitalize">
                                                                {t(customer.latestOrderStatus)}
                                                            </Badge>
                                                            <Badge variant={paymentStatusBadgeVariant(customer.latestPaymentStatus)} className="capitalize">
                                                                {t(customer.latestPaymentStatus)}
                                                            </Badge>
                                                        </div>
                                                    </td>

                                                    <td className="px-4 py-3">
                                                        <div className="space-y-1">
                                                            <div className="font-medium">{customer.latestOrderNumber}</div>
                                                            <div className="text-xs text-muted-foreground">
                                                                {formatDate(customer.lastOrderAt)}
                                                            </div>
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                <Badge variant={fulfillmentStatusBadgeVariant(customer.latestFulfillmentStatus)} className="capitalize">
                                                                    {t(customer.latestFulfillmentStatus)}
                                                                </Badge>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center justify-end gap-1">
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                title={t('Open latest order')}
                                                                onClick={() => {
                                                                    openOrderDetailsView(customer.latestOrderId);
                                                                    setActiveTab('orders');
                                                                }}
                                                            >
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                            <DropdownMenu>
                                                                <DropdownMenuTrigger asChild>
                                                                    <Button type="button" variant="ghost" size="icon" title={t('More actions')}>
                                                                        <MoreVertical className="h-4 w-4" />
                                                                    </Button>
                                                                </DropdownMenuTrigger>
                                                                <DropdownMenuContent align="end">
                                                                    <DropdownMenuItem
                                                                        onClick={() => {
                                                                            openOrderDetailsView(customer.latestOrderId);
                                                                            setActiveTab('orders');
                                                                        }}
                                                                    >
                                                                        <Eye className="mr-2 h-4 w-4" />
                                                                        {t('Open latest order')}
                                                                    </DropdownMenuItem>
                                                                    {customer.email ? (
                                                                        <DropdownMenuItem
                                                                            onClick={() => void handleCopyValue(customer.email as string, t('Email copied'))}
                                                                        >
                                                                            <Copy className="mr-2 h-4 w-4" />
                                                                            {t('Copy Email')}
                                                                        </DropdownMenuItem>
                                                                    ) : null}
                                                                    {customer.phone ? (
                                                                        <DropdownMenuItem
                                                                            onClick={() => void handleCopyValue(customer.phone as string, t('Phone copied'))}
                                                                        >
                                                                            <Copy className="mr-2 h-4 w-4" />
                                                                            {t('Copy Phone')}
                                                                        </DropdownMenuItem>
                                                                    ) : null}
                                                                </DropdownMenuContent>
                                                            </DropdownMenu>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="orders" className="min-w-0 max-w-full space-y-4 pt-4 overflow-x-hidden">
                    {ordersView === 'details' ? (
                        <div className="space-y-4">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="flex items-center gap-2">
                                    <Button type="button" variant="outline" size="sm" onClick={closeOrderDetailsView}>
                                        <ArrowLeft className="mr-1.5 h-4 w-4" />
                                        {t('Orders')}
                                    </Button>
                                    <div>
                                        <p className="text-xs uppercase tracking-wide text-muted-foreground">{t('Order Details')}</p>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="text-lg font-semibold">
                                                {selectedOrder?.order_number ?? (selectedOrderId ? `#${selectedOrderId}` : t('Select an order'))}
                                            </h3>
                                            {selectedOrder ? (
                                                <>
                                                    <Badge variant={orderStatusBadgeVariant(selectedOrder.status)} className="capitalize">{t(selectedOrder.status)}</Badge>
                                                    <Badge variant={paymentStatusBadgeVariant(selectedOrder.payment_status)} className="capitalize">{t(selectedOrder.payment_status)}</Badge>
                                                    <Badge variant={fulfillmentStatusBadgeVariant(selectedOrder.fulfillment_status)} className="capitalize">{t(selectedOrder.fulfillment_status)}</Badge>
                                                </>
                                            ) : null}
                                        </div>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            if (selectedOrderId !== null) {
                                                void Promise.all([
                                                    loadOrderDetails(selectedOrderId),
                                                    loadOrderAccounting(selectedOrderId),
                                                ]);
                                            }
                                        }}
                                        disabled={selectedOrderId === null || isOrderDetailsLoading || isAccountingLoading}
                                    >
                                        {(isOrderDetailsLoading || isAccountingLoading) ? (
                                            <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                                        ) : (
                                            <RefreshCw className="mr-1.5 h-4 w-4" />
                                        )}
                                        {t('Refresh')}
                                    </Button>
                                    {selectedOrder ? (
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            onClick={() => void handleDeleteOrder(selectedOrder)}
                                            disabled={deletingOrderId === selectedOrder.id}
                                        >
                                            {deletingOrderId === selectedOrder.id ? (
                                                <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                                            ) : (
                                                <Trash2 className="mr-1.5 h-4 w-4" />
                                            )}
                                            {t('Delete')}
                                        </Button>
                                    ) : null}
                                </div>
                            </div>

                            {isOrderDetailsLoading && !selectedOrder ? (
                                <Card>
                                    <CardContent className="py-12">
                                        <div className="flex items-center justify-center gap-2 text-sm text-muted-foreground">
                                            <Loader2 className="h-4 w-4 animate-spin" />
                                            {t('Loading order...')}
                                        </div>
                                    </CardContent>
                                </Card>
                            ) : !selectedOrder ? (
                                <Card>
                                    <CardContent className="py-12 text-center text-sm text-muted-foreground">
                                        {t('Select an order to open details')}
                                    </CardContent>
                                </Card>
                            ) : (
                                <>
                                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                        <Card className="border-violet-200/70 bg-violet-50/60 shadow-none">
                                            <CardContent className="p-4">
                                                <p className="text-xs text-muted-foreground">{t('Grand Total')}</p>
                                                <p className="mt-1 text-xl font-semibold">{formatCurrencyAmount(selectedOrder.grand_total, selectedOrder.currency)}</p>
                                                <p className="mt-1 text-xs text-muted-foreground">{t('Placed')}: {formatDate(selectedOrder.placed_at)}</p>
                                            </CardContent>
                                        </Card>
                                        <Card className="border-emerald-200/70 bg-emerald-50/60 shadow-none">
                                            <CardContent className="p-4">
                                                <p className="text-xs text-muted-foreground">{t('Paid')}</p>
                                                <p className="mt-1 text-xl font-semibold">{formatCurrencyAmount(selectedOrder.paid_total, selectedOrder.currency)}</p>
                                                <p className="mt-1 text-xs text-muted-foreground">{t('Payment Status')}: {t(selectedOrder.payment_status)}</p>
                                            </CardContent>
                                        </Card>
                                        <Card className="border-amber-200/70 bg-amber-50/60 shadow-none">
                                            <CardContent className="p-4">
                                                <p className="text-xs text-muted-foreground">{t('Outstanding')}</p>
                                                <p className="mt-1 text-xl font-semibold">{formatCurrencyAmount(selectedOrder.outstanding_total, selectedOrder.currency)}</p>
                                                <p className="mt-1 text-xs text-muted-foreground">{t('Fulfillment')}: {t(selectedOrder.fulfillment_status)}</p>
                                            </CardContent>
                                        </Card>
                                        <Card className="border-slate-200/80 bg-slate-50/70 shadow-none">
                                            <CardContent className="p-4">
                                                <p className="text-xs text-muted-foreground">{t('Items / Shipments')}</p>
                                                <p className="mt-1 text-xl font-semibold">{selectedOrder.items.length} / {selectedOrder.shipments.length}</p>
                                                <p className="mt-1 text-xs text-muted-foreground">{t('Customer')}: {selectedOrder.customer_name ?? t('Unknown customer')}</p>
                                            </CardContent>
                                        </Card>
                                    </div>

                                    <div className="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_380px]">
                                        <div className="space-y-4">
                                            <Card>
                                                <CardHeader>
                                                    <CardTitle>{t('Customer & Addresses')}</CardTitle>
                                                    <CardDescription>{t('Billing, shipping and contact details')}</CardDescription>
                                                </CardHeader>
                                                <CardContent className="grid gap-4 md:grid-cols-2">
                                                    <div className="rounded-lg border p-3 text-sm space-y-1">
                                                        <p><span className="text-muted-foreground">{t('Name')}:</span> {selectedOrder.customer_name ?? '—'}</p>
                                                        <p><span className="text-muted-foreground">{t('Email')}:</span> {selectedOrder.customer_email ?? '—'}</p>
                                                        <p><span className="text-muted-foreground">{t('Phone')}:</span> {selectedOrder.customer_phone ?? '—'}</p>
                                                    </div>
                                                    <div className="rounded-lg border p-3 text-sm space-y-2">
                                                        <div>
                                                            <p className="text-xs text-muted-foreground">{t('Billing')}</p>
                                                            <p>{normalizeAddress(selectedOrder.billing_address_json)}</p>
                                                        </div>
                                                        <div>
                                                            <p className="text-xs text-muted-foreground">{t('Shipping')}</p>
                                                            <p>{normalizeAddress(selectedOrder.shipping_address_json)}</p>
                                                        </div>
                                                    </div>
                                                </CardContent>
                                            </Card>

                                            <Card>
                                                <CardHeader>
                                                    <CardTitle>{t('Order Items')}</CardTitle>
                                                    <CardDescription>{t('Products included in this order')}</CardDescription>
                                                </CardHeader>
                                                <CardContent>
                                                    {selectedOrder.items.length === 0 ? (
                                                        <p className="text-sm text-muted-foreground">{t('No items')}</p>
                                                    ) : (
                                                        <div className="space-y-2">
                                                            {selectedOrder.items.map((item) => (
                                                                <div key={item.id} className="rounded-lg border p-3 text-sm">
                                                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                                                        <p className="font-medium">{item.name}</p>
                                                                        <Badge variant="outline">{t('Qty')}: {item.quantity}</Badge>
                                                                    </div>
                                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                                        {t('Unit')}: {formatCurrencyAmount(item.unit_price, selectedOrder.currency)}
                                                                        {' · '}
                                                                        {t('Line Total')}: {formatCurrencyAmount(item.line_total, selectedOrder.currency)}
                                                                    </p>
                                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                                        {t('Tax')}: {formatCurrencyAmount(item.tax_amount, selectedOrder.currency)}
                                                                        {' · '}
                                                                        {t('Discount')}: {formatCurrencyAmount(item.discount_amount, selectedOrder.currency)}
                                                                    </p>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    )}
                                                </CardContent>
                                            </Card>

                                            <div className="grid gap-4 lg:grid-cols-2">
                                                <Card>
                                                    <CardHeader>
                                                        <CardTitle>{t('Payments')}</CardTitle>
                                                        <CardDescription>{t('Transactions and payment providers')}</CardDescription>
                                                    </CardHeader>
                                                    <CardContent>
                                                        {selectedOrder.payments.length === 0 ? (
                                                            <p className="text-sm text-muted-foreground">{t('No payments')}</p>
                                                        ) : (
                                                            <div className="space-y-2">
                                                                {selectedOrder.payments.map((payment) => (
                                                                    <div key={payment.id} className="rounded-lg border p-3 text-sm">
                                                                        <div className="flex items-center justify-between gap-2">
                                                                            <p className="font-medium">{payment.provider ?? t('Unknown provider')}</p>
                                                                            <Badge variant={statusBadgeVariant(payment.status)}>{payment.status}</Badge>
                                                                        </div>
                                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                                            {formatCurrencyAmount(payment.amount, payment.currency)}
                                                                            {' · '}
                                                                            {payment.method ?? t('N/A')}
                                                                        </p>
                                                                        <p className="mt-1 text-xs text-muted-foreground">{payment.transaction_reference ?? t('No transaction reference')}</p>
                                                                        <p className="mt-1 text-xs text-muted-foreground">{t('Processed')}: {formatDate(payment.processed_at)}</p>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        )}
                                                    </CardContent>
                                                </Card>

                                                <Card>
                                                    <CardHeader>
                                                        <CardTitle>{t('Shipment Lifecycle')}</CardTitle>
                                                        <CardDescription>{t('Create and manage deliveries')}</CardDescription>
                                                    </CardHeader>
                                                    <CardContent className="space-y-4">
                                                        <div className="rounded-lg border p-3 space-y-3">
                                                            <p className="text-sm font-medium">{t('Create Shipment')}</p>
                                                            <div className="space-y-2">
                                                                <Label>{t('Provider')}</Label>
                                                                <select
                                                                    value={shipmentForm.providerSlug}
                                                                    onChange={(event) => setShipmentForm((prev) => ({ ...prev, providerSlug: event.target.value }))}
                                                                    className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                                >
                                                                    <option value="">{t('Select provider')}</option>
                                                                    {shippingCouriers.map((courier) => (
                                                                        <option key={courier.slug} value={courier.slug}>
                                                                            {courier.name} ({courier.slug})
                                                                        </option>
                                                                    ))}
                                                                </select>
                                                            </div>
                                                            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                                <Input
                                                                    value={shipmentForm.shipmentReference}
                                                                    onChange={(event) => setShipmentForm((prev) => ({ ...prev, shipmentReference: event.target.value }))}
                                                                    placeholder={t('Shipment Reference')}
                                                                />
                                                                <Input
                                                                    value={shipmentForm.trackingNumber}
                                                                    onChange={(event) => setShipmentForm((prev) => ({ ...prev, trackingNumber: event.target.value }))}
                                                                    placeholder={t('Tracking Number')}
                                                                />
                                                            </div>
                                                            <Input
                                                                value={shipmentForm.trackingUrl}
                                                                onChange={(event) => setShipmentForm((prev) => ({ ...prev, trackingUrl: event.target.value }))}
                                                                placeholder={t('Tracking URL')}
                                                            />
                                                            <Button type="button" onClick={() => void handleCreateShipment()} disabled={isCreatingShipment} className="w-full">
                                                                {isCreatingShipment ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <CheckCircle2 className="mr-2 h-4 w-4" />}
                                                                {t('Create Shipment')}
                                                            </Button>
                                                        </div>

                                                        {selectedOrder.shipments.length === 0 ? (
                                                            <p className="text-sm text-muted-foreground">{t('No shipments')}</p>
                                                        ) : (
                                                            <div className="space-y-3">
                                                                {selectedOrder.shipments.map((shipment) => {
                                                                    const isActionLoading = shipmentActionId === shipment.id;
                                                                    return (
                                                                        <div key={shipment.id} className="rounded-lg border p-3 text-sm space-y-2">
                                                                            <div className="flex items-center justify-between gap-2">
                                                                                <p className="font-medium">{shipment.provider_slug}</p>
                                                                                <Badge variant={statusBadgeVariant(shipment.status)}>{shipment.status}</Badge>
                                                                            </div>
                                                                            <p className="text-xs text-muted-foreground">{t('Tracking')}: {shipment.tracking_number ?? '—'}</p>
                                                                            <p className="text-xs text-muted-foreground">{t('Reference')}: {shipment.shipment_reference}</p>
                                                                            <div className="flex flex-wrap gap-2 pt-1">
                                                                                <Button type="button" variant="outline" size="sm" onClick={() => void handleRefreshShipment(shipment.id)} disabled={isActionLoading}>
                                                                                    {isActionLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : t('Refresh')}
                                                                                </Button>
                                                                                <Button type="button" variant="outline" size="sm" onClick={() => void handleRefreshShipment(shipment.id, 'delivered')} disabled={isActionLoading}>
                                                                                    {t('Mark Delivered')}
                                                                                </Button>
                                                                                <Button type="button" variant="ghost" size="sm" className="text-destructive" onClick={() => void handleCancelShipment(shipment.id)} disabled={isActionLoading}>
                                                                                    {t('Cancel')}
                                                                                </Button>
                                                                            </div>
                                                                        </div>
                                                                    );
                                                                })}
                                                            </div>
                                                        )}
                                                    </CardContent>
                                                </Card>
                                            </div>
                                        </div>

                                        <div className="space-y-4">
                                            <Card>
                                                <CardHeader>
                                                    <CardTitle>{t('Status & Notes')}</CardTitle>
                                                    <CardDescription>{t('Update order workflow statuses')}</CardDescription>
                                                </CardHeader>
                                                <CardContent className="space-y-3">
                                                    <div className="space-y-2">
                                                        <Label>{t('Order Status')}</Label>
                                                        <select
                                                            value={orderUpdateForm.status}
                                                            onChange={(event) => setOrderUpdateForm((prev) => ({ ...prev, status: event.target.value as OrderStatus }))}
                                                            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                        >
                                                            {ORDER_STATUS_OPTIONS.map((status) => (
                                                                <option key={`detail-order-status-${status}`} value={status}>{t(status)}</option>
                                                            ))}
                                                        </select>
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label>{t('Payment Status')}</Label>
                                                        <select
                                                            value={orderUpdateForm.paymentStatus}
                                                            onChange={(event) => setOrderUpdateForm((prev) => ({ ...prev, paymentStatus: event.target.value as PaymentStatus }))}
                                                            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                        >
                                                            {PAYMENT_STATUS_OPTIONS.map((status) => (
                                                                <option key={`detail-payment-status-${status}`} value={status}>{t(status)}</option>
                                                            ))}
                                                        </select>
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label>{t('Fulfillment Status')}</Label>
                                                        <select
                                                            value={orderUpdateForm.fulfillmentStatus}
                                                            onChange={(event) => setOrderUpdateForm((prev) => ({ ...prev, fulfillmentStatus: event.target.value as FulfillmentStatus }))}
                                                            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                        >
                                                            {FULFILLMENT_STATUS_OPTIONS.map((status) => (
                                                                <option key={`detail-fulfillment-status-${status}`} value={status}>{t(status)}</option>
                                                            ))}
                                                        </select>
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label>{t('Internal Notes')}</Label>
                                                        <Textarea
                                                            value={orderUpdateForm.notes}
                                                            onChange={(event) => setOrderUpdateForm((prev) => ({ ...prev, notes: event.target.value }))}
                                                            rows={4}
                                                        />
                                                    </div>
                                                    <Button onClick={() => void handleOrderUpdate()} disabled={isSavingOrderUpdate} className="w-full">
                                                        {isSavingOrderUpdate ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <CheckCircle2 className="mr-2 h-4 w-4" />}
                                                        {t('Update Order')}
                                                    </Button>
                                                </CardContent>
                                            </Card>

                                            <Card>
                                                <CardHeader>
                                                    <CardTitle>{t('Financial Breakdown')}</CardTitle>
                                                    <CardDescription>{t('Order accounting summary')}</CardDescription>
                                                </CardHeader>
                                                <CardContent className="space-y-2 text-sm">
                                                    <div className="flex items-center justify-between"><span>{t('Subtotal')}</span><span>{formatCurrencyAmount(selectedOrder.subtotal, selectedOrder.currency)}</span></div>
                                                    <div className="flex items-center justify-between"><span>{t('Tax')}</span><span>{formatCurrencyAmount(selectedOrder.tax_total, selectedOrder.currency)}</span></div>
                                                    <div className="flex items-center justify-between"><span>{t('Shipping')}</span><span>{formatCurrencyAmount(selectedOrder.shipping_total, selectedOrder.currency)}</span></div>
                                                    <div className="flex items-center justify-between"><span>{t('Discount')}</span><span>{formatCurrencyAmount(selectedOrder.discount_total, selectedOrder.currency)}</span></div>
                                                    <div className="flex items-center justify-between font-semibold"><span>{t('Grand Total')}</span><span>{formatCurrencyAmount(selectedOrder.grand_total, selectedOrder.currency)}</span></div>
                                                    <div className="flex items-center justify-between text-emerald-600"><span>{t('Paid')}</span><span>{formatCurrencyAmount(selectedOrder.paid_total, selectedOrder.currency)}</span></div>
                                                    <div className="flex items-center justify-between text-amber-600"><span>{t('Outstanding')}</span><span>{formatCurrencyAmount(selectedOrder.outstanding_total, selectedOrder.currency)}</span></div>
                                                </CardContent>
                                            </Card>

                                            <Card>
                                                <CardHeader className="flex-row items-center justify-between space-y-0">
                                                    <div>
                                                        <CardTitle>{t('Accounting')}</CardTitle>
                                                        <CardDescription>{t('Ledger and reconciliation')}</CardDescription>
                                                    </div>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => selectedOrderId !== null && void loadOrderAccounting(selectedOrderId)}
                                                        disabled={isAccountingLoading || selectedOrderId === null}
                                                    >
                                                        {isAccountingLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                                                    </Button>
                                                </CardHeader>
                                                <CardContent className="space-y-3">
                                                    {isAccountingLoading ? (
                                                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                            <Loader2 className="h-4 w-4 animate-spin" />
                                                            {t('Loading accounting ledger...')}
                                                        </div>
                                                    ) : (
                                                        <>
                                                            <div className="rounded-md border p-3 text-sm space-y-1">
                                                                <div className="flex items-center justify-between"><span>{t('Entries')}</span><span>{accountingSummary?.entries_count ?? 0}</span></div>
                                                                <div className="flex items-center justify-between"><span>{t('Debit')}</span><span>{accountingSummary?.total_debit ?? '0.00'}</span></div>
                                                                <div className="flex items-center justify-between"><span>{t('Credit')}</span><span>{accountingSummary?.total_credit ?? '0.00'}</span></div>
                                                                <div className="flex items-center justify-between font-medium"><span>{t('Difference')}</span><span>{accountingSummary?.difference ?? '0.00'}</span></div>
                                                                <div className="pt-1">
                                                                    <Badge variant={accountingSummary?.is_balanced ? 'default' : 'secondary'}>
                                                                        {accountingSummary?.is_balanced ? t('Balanced') : t('Unbalanced')}
                                                                    </Badge>
                                                                </div>
                                                            </div>
                                                            <div className="rounded-md border p-3 text-xs text-muted-foreground space-y-1">
                                                                <p>{t('AR Net')}: {reconciliationSummary?.accounts_receivable_net ?? '0.00'}</p>
                                                                <p>{t('Bookings Outstanding')}: {reconciliationSummary?.bookings_outstanding_total ?? '0.00'}</p>
                                                                <p>{t('Outstanding Gap')}: {reconciliationSummary?.outstanding_gap ?? '0.00'}</p>
                                                            </div>
                                                        </>
                                                    )}
                                                </CardContent>
                                            </Card>
                                        </div>
                                    </div>
                                </>
                            )}
                        </div>
                    ) : (
                    <>
                    <div className="flex justify-end">
                        <Button variant="outline" size="sm" onClick={() => void loadOrders()} disabled={isOrdersLoading}>
                            {isOrdersLoading ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <RefreshCw className="h-4 w-4 mr-2" />}
                            {t('Refresh Orders')}
                        </Button>
                    </div>

                    <div className="grid gap-4 xl:grid-cols-[1.7fr_1fr]">
                        <div className="space-y-4">
                            <div className="grid gap-3 sm:grid-cols-2 2xl:grid-cols-4">
                                <Card className="border-violet-200/70 bg-violet-50/60 shadow-none">
                                    <CardContent className="p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-xs text-muted-foreground">{t('Total Orders')}</p>
                                                <p className="text-2xl font-semibold">{filteredOrdersSummary.count}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {hasOrderFilters ? `${t('Filtered')} / ${orders.length}` : t('All orders')}
                                                </p>
                                            </div>
                                            <div className="rounded-xl border border-violet-200 bg-white p-2 text-violet-600">
                                                <Package className="h-5 w-5" />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card className="border-emerald-200/70 bg-emerald-50/60 shadow-none">
                                    <CardContent className="p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-xs text-muted-foreground">{t('Paid Revenue')}</p>
                                                <p className="text-lg font-semibold">
                                                    {formatCurrencyAmount(filteredOrdersSummary.paid, filteredOrdersSummary.currency)}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {t('Avg')}: {formatCurrencyAmount(filteredOrdersSummary.average, filteredOrdersSummary.currency)}
                                                </p>
                                            </div>
                                            <div className="rounded-xl border border-emerald-200 bg-white p-2 text-emerald-600">
                                                <Banknote className="h-5 w-5" />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card className="border-amber-200/70 bg-amber-50/60 shadow-none">
                                    <CardContent className="p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-xs text-muted-foreground">{t('Pending & Processing')}</p>
                                                <p className="text-2xl font-semibold">
                                                    {filteredOrdersSummary.pending + filteredOrdersSummary.processing}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {t('Outstanding')}: {formatCurrencyAmount(filteredOrdersSummary.outstanding, filteredOrdersSummary.currency)}
                                                </p>
                                            </div>
                                            <div className="rounded-xl border border-amber-200 bg-white p-2 text-amber-600">
                                                <Truck className="h-5 w-5" />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card className="border-slate-200/80 bg-slate-50/70 shadow-none">
                                    <CardContent className="p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-xs text-muted-foreground">{t('Completed / Refunded')}</p>
                                                <p className="text-2xl font-semibold">
                                                    {filteredOrdersSummary.completed + filteredOrdersSummary.refunded}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {t('Cancelled')}: {filteredOrdersSummary.cancelled}
                                                </p>
                                            </div>
                                            <div className="rounded-xl border border-slate-200 bg-white p-2 text-slate-700">
                                                <CheckCircle2 className="h-5 w-5" />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            <Card className="overflow-hidden border-muted/70 shadow-sm">
                                <CardHeader className="space-y-4 border-b bg-muted/20">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <CardTitle>{t('Orders')}</CardTitle>
                                            <CardDescription>{t('Manage orders, payment and delivery statuses')}</CardDescription>
                                        </div>
                                        <Button variant="outline" size="sm" onClick={() => void loadOrders()} disabled={isOrdersLoading}>
                                            {isOrdersLoading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <RefreshCw className="mr-2 h-4 w-4" />}
                                            {t('Refresh Orders')}
                                        </Button>
                                    </div>

                                    <div className="grid gap-2 lg:grid-cols-[minmax(220px,1fr)_180px_180px]">
                                        <div className="relative">
                                            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                            <Input
                                                value={orderSearchQuery}
                                                onChange={(event) => setOrderSearchQuery(event.target.value)}
                                                placeholder={t('Search order or customer')}
                                                className="pl-9"
                                            />
                                        </div>

                                        <select
                                            value={orderStatusFilter}
                                            onChange={(event) => setOrderStatusFilter(event.target.value as 'all' | OrderStatus)}
                                            className="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                        >
                                            <option value="all">{t('All Order Statuses')}</option>
                                            {ORDER_STATUS_OPTIONS.map((status) => (
                                                <option key={`order-filter-status-${status}`} value={status}>
                                                    {t(status)}
                                                </option>
                                            ))}
                                        </select>

                                        <select
                                            value={orderPaymentFilter}
                                            onChange={(event) => setOrderPaymentFilter(event.target.value as 'all' | PaymentStatus)}
                                            className="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                        >
                                            <option value="all">{t('All Payment Statuses')}</option>
                                            {PAYMENT_STATUS_OPTIONS.map((status) => (
                                                <option key={`order-filter-payment-${status}`} value={status}>
                                                    {t(status)}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-2 text-xs">
                                        <Badge variant="outline">
                                            {t('Gross')}: {formatCurrencyAmount(filteredOrdersSummary.gross, filteredOrdersSummary.currency)}
                                        </Badge>
                                        {ORDER_STATUS_OPTIONS.map((status) => {
                                            const filteredCount = (filteredOrdersByStatus.get(status) ?? []).length;
                                            const totalCount = (ordersByStatus.get(status) ?? []).length;

                                            if (totalCount === 0 && filteredCount === 0) {
                                                return null;
                                            }

                                            return (
                                                <Badge
                                                    key={`order-status-badge-${status}`}
                                                    variant={orderStatusBadgeVariant(status)}
                                                    className="capitalize"
                                                >
                                                    {t(status)}: {filteredCount}
                                                    {hasOrderFilters ? ` / ${totalCount}` : ''}
                                                </Badge>
                                            );
                                        })}
                                    </div>
                                </CardHeader>

                                <CardContent className="p-0">
                                    {orders.length === 0 ? (
                                        <div className="py-10 text-center text-sm text-muted-foreground">
                                            {isOrdersLoading ? t('Loading orders...') : t('No orders')}
                                        </div>
                                    ) : filteredOrders.length === 0 ? (
                                        <div className="py-10 text-center text-sm text-muted-foreground">
                                            {t('No orders match the current filters')}
                                        </div>
                                    ) : (
                                        <div className="overflow-x-auto">
                                            <table className="min-w-[1180px] w-full text-sm">
                                                <thead className="bg-background">
                                                    <tr className="border-b text-left text-xs uppercase tracking-wide text-muted-foreground">
                                                        <th className="px-4 py-3 font-medium">{t('Order')}</th>
                                                        <th className="px-4 py-3 font-medium">{t('Customer')}</th>
                                                        <th className="px-4 py-3 font-medium">{t('Payment')}</th>
                                                        <th className="px-4 py-3 font-medium">{t('Delivery')}</th>
                                                        <th className="px-4 py-3 font-medium">{t('Order Status')}</th>
                                                        <th className="px-4 py-3 font-medium">{t('Updated')}</th>
                                                        <th className="px-4 py-3 text-right font-medium">{t('Actions')}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {filteredOrders.map((order) => {
                                                        const rowForm = orderRowForms[order.id] ?? createOrderRowStatusFormState(order);
                                                        const isRowDirty =
                                                            rowForm.status !== order.status
                                                            || rowForm.paymentStatus !== order.payment_status
                                                            || rowForm.fulfillmentStatus !== order.fulfillment_status;
                                                        const isRowSaving = savingOrderRowId === order.id;
                                                        const isRowDeleting = deletingOrderId === order.id;
                                                        const primaryPayment = order.payments[0] ?? null;

                                                        return (
                                                            <tr
                                                                key={order.id}
                                                                className={`cursor-pointer border-b align-top transition ${
                                                                    selectedOrderId === order.id ? 'bg-primary/5' : 'hover:bg-muted/20'
                                                                }`}
                                                                onClick={() => openOrderDetailsView(order.id)}
                                                            >
                                                                <td className="px-4 py-3">
                                                                    <div className="space-y-1">
                                                                        <div className="font-semibold">{order.order_number}</div>
                                                                        <div className="text-xs text-muted-foreground">
                                                                            {formatDate(order.placed_at)}
                                                                        </div>
                                                                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                                            <span>{t('Items')}: {order.items_count}</span>
                                                                            <span>•</span>
                                                                            <span>{t('Shipments')}: {order.shipments_count}</span>
                                                                        </div>
                                                                    </div>
                                                                </td>

                                                                <td className="px-4 py-3">
                                                                    <div className="flex items-start gap-3">
                                                                        <Avatar className="h-9 w-9 border">
                                                                            <AvatarFallback className="text-xs">
                                                                                {textInitials(order.customer_name || order.customer_email)}
                                                                            </AvatarFallback>
                                                                        </Avatar>
                                                                        <div className="min-w-0 space-y-1">
                                                                            <div className="truncate font-medium">
                                                                                {order.customer_name || t('Unknown customer')}
                                                                            </div>
                                                                            <div className="truncate text-xs text-muted-foreground">
                                                                                {order.customer_email || '—'}
                                                                            </div>
                                                                            <div className="text-xs text-muted-foreground">
                                                                                {order.customer_phone || '—'}
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </td>

                                                                <td className="px-4 py-3">
                                                                    <div className="space-y-2">
                                                                        <div>
                                                                            <div className="font-semibold">
                                                                                {formatCurrencyAmount(order.grand_total, order.currency)}
                                                                            </div>
                                                                            <div className="text-xs text-emerald-600">
                                                                                {t('Paid')}: {formatCurrencyAmount(order.paid_total, order.currency)}
                                                                            </div>
                                                                            <div className="text-xs text-amber-600">
                                                                                {t('Outstanding')}: {formatCurrencyAmount(order.outstanding_total, order.currency)}
                                                                            </div>
                                                                        </div>
                                                                        <div className="flex flex-wrap items-center gap-2">
                                                                            <Badge variant={paymentStatusBadgeVariant(order.payment_status)} className="capitalize">
                                                                                {t(order.payment_status)}
                                                                            </Badge>
                                                                            {primaryPayment?.provider ? (
                                                                                <Badge variant="outline" className="capitalize">
                                                                                    {primaryPayment.provider}
                                                                                </Badge>
                                                                            ) : null}
                                                                            {primaryPayment?.is_installment ? (
                                                                                <Badge variant="outline">{t('Installment')}</Badge>
                                                                            ) : null}
                                                                        </div>
                                                                        <select
                                                                            value={rowForm.paymentStatus}
                                                                            onClick={(event) => event.stopPropagation()}
                                                                            onChange={(event) => handleOrderRowStatusChange(order.id, 'paymentStatus', event.target.value as PaymentStatus)}
                                                                            className="h-8 w-full min-w-[170px] rounded-md border bg-background px-2 text-xs capitalize"
                                                                        >
                                                                            {PAYMENT_STATUS_OPTIONS.map((status) => (
                                                                                <option key={`row-payment-status-${order.id}-${status}`} value={status}>
                                                                                    {t(status)}
                                                                                </option>
                                                                            ))}
                                                                        </select>
                                                                    </div>
                                                                </td>

                                                                <td className="px-4 py-3">
                                                                    <div className="space-y-2">
                                                                        <div className="flex flex-wrap items-center gap-2">
                                                                            <Badge variant={fulfillmentStatusBadgeVariant(order.fulfillment_status)} className="capitalize">
                                                                                {t(order.fulfillment_status)}
                                                                            </Badge>
                                                                            <Badge variant="outline">
                                                                                {t('Shipments')}: {order.shipments_count}
                                                                            </Badge>
                                                                        </div>
                                                                        <select
                                                                            value={rowForm.fulfillmentStatus}
                                                                            onClick={(event) => event.stopPropagation()}
                                                                            onChange={(event) => handleOrderRowStatusChange(order.id, 'fulfillmentStatus', event.target.value as FulfillmentStatus)}
                                                                            className="h-8 w-full min-w-[180px] rounded-md border bg-background px-2 text-xs capitalize"
                                                                        >
                                                                            {FULFILLMENT_STATUS_OPTIONS.map((status) => (
                                                                                <option key={`row-fulfillment-status-${order.id}-${status}`} value={status}>
                                                                                    {t(status)}
                                                                                </option>
                                                                            ))}
                                                                        </select>
                                                                    </div>
                                                                </td>

                                                                <td className="px-4 py-3">
                                                                    <div className="space-y-2">
                                                                        <Badge variant={orderStatusBadgeVariant(order.status)} className="capitalize">
                                                                            {t(order.status)}
                                                                        </Badge>
                                                                        <select
                                                                            value={rowForm.status}
                                                                            onClick={(event) => event.stopPropagation()}
                                                                            onChange={(event) => handleOrderRowStatusChange(order.id, 'status', event.target.value as OrderStatus)}
                                                                            className="h-8 w-full min-w-[170px] rounded-md border bg-background px-2 text-xs capitalize"
                                                                        >
                                                                            {ORDER_STATUS_OPTIONS.map((status) => (
                                                                                <option key={`row-order-status-${order.id}-${status}`} value={status}>
                                                                                    {t(status)}
                                                                                </option>
                                                                            ))}
                                                                        </select>
                                                                    </div>
                                                                </td>

                                                                <td className="px-4 py-3">
                                                                    <div className="space-y-1 text-xs text-muted-foreground">
                                                                        <div>{formatDate(order.updated_at)}</div>
                                                                        {order.paid_at ? <div>{t('Paid')}: {formatDate(order.paid_at)}</div> : null}
                                                                    </div>
                                                                </td>

                                                                <td className="px-4 py-3">
                                                                    <div className="flex items-center justify-end gap-1">
                                                                        <Button
                                                                            type="button"
                                                                            variant={selectedOrderId === order.id ? 'secondary' : 'ghost'}
                                                                            size="icon"
                                                                            title={t('Quick View')}
                                                                            onClick={(event) => {
                                                                                event.stopPropagation();
                                                                                openOrderDetailsView(order.id);
                                                                            }}
                                                                        >
                                                                            <Eye className="h-4 w-4" />
                                                                        </Button>

                                                                        <Button
                                                                            type="button"
                                                                            variant={isRowDirty ? 'default' : 'ghost'}
                                                                            size="icon"
                                                                            title={t('Save Statuses')}
                                                                            onClick={(event) => {
                                                                                event.stopPropagation();
                                                                                void handleSaveOrderRow(order);
                                                                            }}
                                                                            disabled={isRowSaving || isRowDeleting || !isRowDirty}
                                                                        >
                                                                            {isRowSaving ? (
                                                                                <Loader2 className="h-4 w-4 animate-spin" />
                                                                            ) : (
                                                                                <CheckCircle2 className="h-4 w-4" />
                                                                            )}
                                                                        </Button>

                                                                        <DropdownMenu>
                                                                            <DropdownMenuTrigger asChild>
                                                                                <Button
                                                                                    type="button"
                                                                                    variant="ghost"
                                                                                    size="icon"
                                                                                    title={t('More actions')}
                                                                                    onClick={(event) => event.stopPropagation()}
                                                                                >
                                                                                    <MoreVertical className="h-4 w-4" />
                                                                                </Button>
                                                                            </DropdownMenuTrigger>
                                                                            <DropdownMenuContent align="end">
                                                                                <DropdownMenuItem
                                                                                    onClick={() => openOrderDetailsView(order.id)}
                                                                                >
                                                                                    <Eye className="mr-2 h-4 w-4" />
                                                                                    {t('Order Details')}
                                                                                </DropdownMenuItem>
                                                                                <DropdownMenuItem
                                                                                    onClick={() => openOrderDetailsView(order.id)}
                                                                                >
                                                                                    <Pencil className="mr-2 h-4 w-4" />
                                                                                    {t('Edit Order')}
                                                                                </DropdownMenuItem>
                                                                                <DropdownMenuSeparator />
                                                                                <DropdownMenuItem
                                                                                    onClick={() => void handleDeleteOrder(order)}
                                                                                    disabled={isRowSaving || isRowDeleting}
                                                                                    className="text-destructive focus:text-destructive"
                                                                                >
                                                                                    {isRowDeleting ? (
                                                                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                                                    ) : (
                                                                                        <Trash2 className="mr-2 h-4 w-4" />
                                                                                    )}
                                                                                    {t('Delete Order')}
                                                                                </DropdownMenuItem>
                                                                            </DropdownMenuContent>
                                                                        </DropdownMenu>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        );
                                                    })}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        <div className="space-y-4">
                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('Order Details')}</CardTitle>
                                    <CardDescription>
                                        {selectedOrder ? selectedOrder.order_number : t('Select an order')}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {isOrderDetailsLoading ? (
                                        <div className="text-sm text-muted-foreground flex items-center gap-2">
                                            <Loader2 className="h-4 w-4 animate-spin" />
                                            {t('Loading order...')}
                                        </div>
                                    ) : !selectedOrder ? (
                                        <div className="text-sm text-muted-foreground">
                                            {t('Select an order card to manage statuses and financials.')}
                                        </div>
                                    ) : (
                                        <>
                                            <div className="space-y-2">
                                                <Label>{t('Order Status')}</Label>
                                                <select
                                                    value={orderUpdateForm.status}
                                                    onChange={(event) => setOrderUpdateForm((prev) => ({
                                                        ...prev,
                                                        status: event.target.value as OrderStatus,
                                                    }))}
                                                    className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                >
                                                    {ORDER_STATUS_OPTIONS.map((status) => (
                                                        <option key={status} value={status}>
                                                            {status}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>

                                            <div className="space-y-2">
                                                <Label>{t('Payment Status')}</Label>
                                                <select
                                                    value={orderUpdateForm.paymentStatus}
                                                    onChange={(event) => setOrderUpdateForm((prev) => ({
                                                        ...prev,
                                                        paymentStatus: event.target.value as PaymentStatus,
                                                    }))}
                                                    className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                >
                                                    {PAYMENT_STATUS_OPTIONS.map((status) => (
                                                        <option key={status} value={status}>
                                                            {status}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>

                                            <div className="space-y-2">
                                                <Label>{t('Fulfillment Status')}</Label>
                                                <select
                                                    value={orderUpdateForm.fulfillmentStatus}
                                                    onChange={(event) => setOrderUpdateForm((prev) => ({
                                                        ...prev,
                                                        fulfillmentStatus: event.target.value as FulfillmentStatus,
                                                    }))}
                                                    className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                >
                                                    {FULFILLMENT_STATUS_OPTIONS.map((status) => (
                                                        <option key={status} value={status}>
                                                            {status}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>

                                            <div className="space-y-2">
                                                <Label>{t('Internal Notes')}</Label>
                                                <Textarea
                                                    value={orderUpdateForm.notes}
                                                    onChange={(event) => setOrderUpdateForm((prev) => ({
                                                        ...prev,
                                                        notes: event.target.value,
                                                    }))}
                                                    rows={3}
                                                />
                                            </div>

                                            <Button onClick={() => void handleOrderUpdate()} disabled={isSavingOrderUpdate}>
                                                {isSavingOrderUpdate ? (
                                                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                                ) : (
                                                    <CheckCircle2 className="h-4 w-4 mr-2" />
                                                )}
                                                {t('Update Order')}
                                            </Button>
                                        </>
                                    )}
                                </CardContent>
                            </Card>

                            {selectedOrder ? (
                                <>
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>{t('Financial Breakdown')}</CardTitle>
                                            <CardDescription>{t('Order accounting summary')}</CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-2 text-sm">
                                            <div className="flex items-center justify-between"><span>{t('Subtotal')}</span><span>{formatCurrencyAmount(selectedOrder.subtotal, selectedOrder.currency)}</span></div>
                                            <div className="flex items-center justify-between"><span>{t('Tax')}</span><span>{formatCurrencyAmount(selectedOrder.tax_total, selectedOrder.currency)}</span></div>
                                            <div className="flex items-center justify-between"><span>{t('Shipping')}</span><span>{formatCurrencyAmount(selectedOrder.shipping_total, selectedOrder.currency)}</span></div>
                                            <div className="flex items-center justify-between"><span>{t('Discount')}</span><span>{formatCurrencyAmount(selectedOrder.discount_total, selectedOrder.currency)}</span></div>
                                            <div className="flex items-center justify-between font-semibold"><span>{t('Grand Total')}</span><span>{formatCurrencyAmount(selectedOrder.grand_total, selectedOrder.currency)}</span></div>
                                            <div className="flex items-center justify-between text-emerald-600"><span>{t('Paid')}</span><span>{formatCurrencyAmount(selectedOrder.paid_total, selectedOrder.currency)}</span></div>
                                            <div className="flex items-center justify-between text-amber-600"><span>{t('Outstanding')}</span><span>{formatCurrencyAmount(selectedOrder.outstanding_total, selectedOrder.currency)}</span></div>
                                            <p className="text-xs text-muted-foreground pt-1">
                                                {t('Placed')}: {formatDate(selectedOrder.placed_at)}
                                            </p>
                                        </CardContent>
                                    </Card>

                                    <Card>
                                        <CardHeader>
                                            <CardTitle>{t('Customer')}</CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-2 text-sm">
                                            <p><span className="text-muted-foreground">{t('Name')}:</span> {selectedOrder.customer_name ?? '—'}</p>
                                            <p><span className="text-muted-foreground">{t('Email')}:</span> {selectedOrder.customer_email ?? '—'}</p>
                                            <p><span className="text-muted-foreground">{t('Phone')}:</span> {selectedOrder.customer_phone ?? '—'}</p>
                                            <p><span className="text-muted-foreground">{t('Billing')}:</span> {normalizeAddress(selectedOrder.billing_address_json)}</p>
                                            <p><span className="text-muted-foreground">{t('Shipping')}:</span> {normalizeAddress(selectedOrder.shipping_address_json)}</p>
                                        </CardContent>
                                    </Card>

                                    <Card>
                                        <CardHeader className="flex-row items-center justify-between space-y-0">
                                            <div>
                                                <CardTitle>{t('Accounting Ledger')}</CardTitle>
                                                <CardDescription>{t('Balanced entries and reconciliation for this order')}</CardDescription>
                                            </div>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => selectedOrderId !== null && void loadOrderAccounting(selectedOrderId)}
                                                disabled={isAccountingLoading || selectedOrderId === null}
                                            >
                                                {isAccountingLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                                            </Button>
                                        </CardHeader>
                                        <CardContent className="space-y-3">
                                            {isAccountingLoading ? (
                                                <div className="text-sm text-muted-foreground flex items-center gap-2">
                                                    <Loader2 className="h-4 w-4 animate-spin" />
                                                    {t('Loading accounting ledger...')}
                                                </div>
                                            ) : (
                                                <>
                                                    <div className="rounded-md border p-3 text-sm space-y-1">
                                                        <div className="flex items-center justify-between">
                                                            <span>{t('Entries')}</span>
                                                            <span>{accountingSummary?.entries_count ?? 0}</span>
                                                        </div>
                                                        <div className="flex items-center justify-between">
                                                            <span>{t('Debit')}</span>
                                                            <span>{accountingSummary?.total_debit ?? '0.00'}</span>
                                                        </div>
                                                        <div className="flex items-center justify-between">
                                                            <span>{t('Credit')}</span>
                                                            <span>{accountingSummary?.total_credit ?? '0.00'}</span>
                                                        </div>
                                                        <div className="flex items-center justify-between font-medium">
                                                            <span>{t('Difference')}</span>
                                                            <span>{accountingSummary?.difference ?? '0.00'}</span>
                                                        </div>
                                                        <div className="pt-1">
                                                            <Badge variant={accountingSummary?.is_balanced ? 'default' : 'secondary'}>
                                                                {accountingSummary?.is_balanced ? t('Balanced') : t('Unbalanced')}
                                                            </Badge>
                                                        </div>
                                                        <div className="text-xs text-muted-foreground pt-1">
                                                            {t('AR Net')}: {reconciliationSummary?.accounts_receivable_net ?? '0.00'}
                                                        </div>
                                                    </div>

                                                    {accountingEntries.length === 0 ? (
                                                        <p className="text-xs text-muted-foreground">{t('No accounting entries for this order')}</p>
                                                    ) : (
                                                        <div className="space-y-2">
                                                            {accountingEntries.slice(0, 6).map((entry) => (
                                                                <div key={entry.id} className="rounded-md border p-2 text-xs">
                                                                    <div className="flex items-center justify-between gap-2">
                                                                        <span className="font-medium">{entry.event_type}</span>
                                                                        <span>{formatDate(entry.occurred_at)}</span>
                                                                    </div>
                                                                    <div className="text-muted-foreground mt-1">
                                                                        {t('Debit')}: {entry.total_debit}
                                                                        {' · '}
                                                                        {t('Credit')}: {entry.total_credit}
                                                                    </div>
                                                                    {entry.lines.length > 0 ? (
                                                                        <div className="mt-1 text-muted-foreground">
                                                                            {entry.lines.map((line) => `${line.account_code} ${line.side} ${line.amount}`).join(' | ')}
                                                                        </div>
                                                                    ) : null}
                                                                </div>
                                                            ))}
                                                        </div>
                                                    )}

                                                    {reconciliationAccounts.length > 0 ? (
                                                        <div className="rounded-md border p-2 text-xs space-y-1">
                                                            <p className="font-medium">{t('Account Balances')}</p>
                                                            {reconciliationAccounts.slice(0, 6).map((account) => (
                                                                <div key={account.account_code} className="flex items-center justify-between gap-2">
                                                                    <span className="truncate">{account.account_code}</span>
                                                                    <span>{account.net}</span>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    ) : null}
                                                </>
                                            )}
                                        </CardContent>
                                    </Card>
                                </>
                            ) : null}
                        </div>
                    </div>

                    {selectedOrder ? (
                        <div className="grid gap-4 lg:grid-cols-3">
                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('Order Items')}</CardTitle>
                                    <CardDescription>{t('Products included in this order')}</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {selectedOrder.items.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">{t('No items')}</p>
                                    ) : (
                                        <div className="space-y-2">
                                            {selectedOrder.items.map((item) => (
                                                <div key={item.id} className="rounded-lg border p-3 text-sm">
                                                    <p className="font-medium">{item.name}</p>
                                                    <p className="text-xs text-muted-foreground mt-1">
                                                        {t('Qty')}: {item.quantity} · {t('Unit')}: {formatCurrencyAmount(item.unit_price, selectedOrder.currency)}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground mt-1">
                                                        {t('Tax')}: {formatCurrencyAmount(item.tax_amount, selectedOrder.currency)}
                                                        {' · '}
                                                        {t('Discount')}: {formatCurrencyAmount(item.discount_amount, selectedOrder.currency)}
                                                    </p>
                                                    <p className="text-xs font-medium mt-1">
                                                        {t('Line Total')}: {formatCurrencyAmount(item.line_total, selectedOrder.currency)}
                                                    </p>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('Payments')}</CardTitle>
                                    <CardDescription>{t('Payment transactions and installment flags')}</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {selectedOrder.payments.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">{t('No payments')}</p>
                                    ) : (
                                        <div className="space-y-2">
                                            {selectedOrder.payments.map((payment) => (
                                                <div key={payment.id} className="rounded-lg border p-3 text-sm">
                                                    <div className="flex items-center justify-between gap-2">
                                                        <p className="font-medium">
                                                            {payment.provider ?? t('Unknown provider')}
                                                        </p>
                                                        <Badge variant={statusBadgeVariant(payment.status)}>{payment.status}</Badge>
                                                    </div>
                                                    <p className="text-xs text-muted-foreground mt-1">
                                                        {formatCurrencyAmount(payment.amount, payment.currency)}
                                                        {' · '}
                                                        {payment.method ?? t('N/A')}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground mt-1">
                                                        {payment.transaction_reference ?? t('No transaction reference')}
                                                    </p>
                                                    {payment.is_installment ? (
                                                        <p className="text-xs text-amber-600 mt-1">{t('Installment payment')}</p>
                                                    ) : null}
                                                    <p className="text-xs text-muted-foreground mt-1">
                                                        {t('Processed')}: {formatDate(payment.processed_at)}
                                                    </p>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('Shipments')}</CardTitle>
                                    <CardDescription>{t('Shipment lifecycle and tracking')}</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="rounded-lg border p-3 space-y-3">
                                        <p className="text-sm font-medium">{t('Create Shipment')}</p>
                                        <div className="space-y-2">
                                            <Label>{t('Provider')}</Label>
                                            <select
                                                value={shipmentForm.providerSlug}
                                                onChange={(event) => setShipmentForm((prev) => ({
                                                    ...prev,
                                                    providerSlug: event.target.value,
                                                }))}
                                                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                            >
                                                <option value="">{t('Select provider')}</option>
                                                {shippingCouriers.map((courier) => (
                                                    <option key={courier.slug} value={courier.slug}>
                                                        {courier.name} ({courier.slug})
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label>{t('Shipment Reference')}</Label>
                                            <Input
                                                value={shipmentForm.shipmentReference}
                                                onChange={(event) => setShipmentForm((prev) => ({
                                                    ...prev,
                                                    shipmentReference: event.target.value,
                                                }))}
                                                placeholder={t('Optional')}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>{t('Tracking Number')}</Label>
                                            <Input
                                                value={shipmentForm.trackingNumber}
                                                onChange={(event) => setShipmentForm((prev) => ({
                                                    ...prev,
                                                    trackingNumber: event.target.value,
                                                }))}
                                                placeholder={t('Optional')}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>{t('Tracking URL')}</Label>
                                            <Input
                                                value={shipmentForm.trackingUrl}
                                                onChange={(event) => setShipmentForm((prev) => ({
                                                    ...prev,
                                                    trackingUrl: event.target.value,
                                                }))}
                                                placeholder="https://..."
                                            />
                                        </div>
                                        <Button
                                            type="button"
                                            onClick={() => void handleCreateShipment()}
                                            disabled={isCreatingShipment}
                                            className="w-full"
                                        >
                                            {isCreatingShipment ? (
                                                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                            ) : (
                                                <CheckCircle2 className="h-4 w-4 mr-2" />
                                            )}
                                            {t('Create Shipment')}
                                        </Button>
                                    </div>

                                    {selectedOrder.shipments.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">{t('No shipments')}</p>
                                    ) : (
                                        <div className="space-y-3">
                                            {selectedOrder.shipments.map((shipment) => {
                                                const isActionLoading = shipmentActionId === shipment.id;

                                                return (
                                                    <div key={shipment.id} className="rounded-lg border p-3 text-sm space-y-2">
                                                        <div className="flex items-center justify-between gap-2">
                                                            <p className="font-medium">{shipment.provider_slug}</p>
                                                            <Badge variant={statusBadgeVariant(shipment.status)}>{shipment.status}</Badge>
                                                        </div>
                                                        <p className="text-xs text-muted-foreground">
                                                            {t('Reference')}: {shipment.shipment_reference}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {t('Tracking')}: {shipment.tracking_number ?? '—'}
                                                        </p>
                                                        {shipment.tracking_url ? (
                                                            <a
                                                                href={shipment.tracking_url}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="text-xs text-primary underline"
                                                            >
                                                                {t('Open tracking link')}
                                                            </a>
                                                        ) : null}
                                                        <p className="text-xs text-muted-foreground">
                                                            {t('Last tracked')}: {formatDate(shipment.last_tracked_at)}
                                                        </p>

                                                        <div className="flex flex-wrap gap-2 pt-1">
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() => void handleRefreshShipment(shipment.id)}
                                                                disabled={isActionLoading}
                                                            >
                                                                {isActionLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : t('Refresh')}
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() => void handleRefreshShipment(shipment.id, 'delivered')}
                                                                disabled={isActionLoading}
                                                            >
                                                                {t('Mark Delivered')}
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                                className="text-destructive"
                                                                onClick={() => void handleCancelShipment(shipment.id)}
                                                                disabled={isActionLoading}
                                                            >
                                                                {t('Cancel')}
                                                            </Button>
                                                        </div>

                                                        {shipment.events.length > 0 ? (
                                                            <div className="rounded-md bg-muted/40 p-2 space-y-1">
                                                                {shipment.events.slice(0, 5).map((event) => (
                                                                    <div key={event.id} className="text-xs text-muted-foreground">
                                                                        <span className="font-medium text-foreground">{event.event_type}</span>
                                                                        {' · '}
                                                                        {event.status ?? t('N/A')}
                                                                        {' · '}
                                                                        {formatDate(event.occurred_at)}
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        ) : null}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    ) : null}
                    </>
                    )}
                </TabsContent>

                <TabsContent value="paymentProviders" className="min-w-0 max-w-full space-y-4 pt-4 overflow-x-hidden">
                    <Card>
                        <CardHeader className="flex-row items-center justify-between space-y-0">
                            <div>
                                <CardTitle>{t('Payments')}</CardTitle>
                                <CardDescription>{t('Enable and configure payment methods')}</CardDescription>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => void loadPaymentProviders()}
                                disabled={isPaymentProvidersLoading}
                            >
                                {isPaymentProvidersLoading ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <RefreshCw className="h-4 w-4" />
                                )}
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {paymentProviders.length === 0 ? (
                                <div className="py-10 text-sm text-muted-foreground text-center">
                                    {isPaymentProvidersLoading ? t('Loading payment providers...') : t('No payment providers available')}
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {paymentProvidersView === 'list' ? (
                                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                            {paymentProviders.map((provider) => {
                                                const { icon: FallbackIcon, accentClass } = getPaymentProviderCardMeta(provider);
                                                const isSelected = selectedPaymentProviderSlug === provider.slug;
                                                const hasImageIcon = typeof provider.icon === 'string'
                                                    && provider.icon.trim() !== ''
                                                    && /^(https?:\/\/|\/|data:image)/i.test(provider.icon.trim());

                                                return (
                                                    <button
                                                        key={provider.slug}
                                                        type="button"
                                                        onClick={() => openPaymentProviderConfigView(provider.slug)}
                                                        className={[
                                                            'text-left rounded-xl border p-4 transition-colors',
                                                            'hover:border-primary/40 hover:bg-muted/20',
                                                            isSelected ? 'border-primary ring-1 ring-primary/30 bg-primary/5' : '',
                                                            !provider.is_enabled ? 'opacity-90' : '',
                                                        ].join(' ')}
                                                    >
                                                        <div className="flex items-start justify-between gap-3">
                                                            <div className="flex items-center gap-3 min-w-0">
                                                                <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border ${accentClass}`}>
                                                                    {hasImageIcon ? (
                                                                        <img
                                                                            src={provider.icon as string}
                                                                            alt={provider.name}
                                                                            className="h-8 w-8 object-contain"
                                                                        />
                                                                    ) : (
                                                                        <FallbackIcon className="h-5 w-5" />
                                                                    )}
                                                                </div>
                                                                <div className="min-w-0">
                                                                    <p className="truncate text-sm font-semibold">{provider.name}</p>
                                                                    <p className="truncate text-xs text-muted-foreground">{provider.slug}</p>
                                                                </div>
                                                            </div>
                                                            <Badge variant={statusBadgeVariant(provider.is_enabled ? 'active' : 'inactive')}>
                                                                {provider.is_enabled ? t('Active') : t('Disabled')}
                                                            </Badge>
                                                        </div>

                                                        <p className="mt-3 line-clamp-2 text-xs text-muted-foreground">
                                                            {provider.description || t('Payment provider')}
                                                        </p>

                                                        <div className="mt-3 flex flex-wrap gap-2">
                                                            <Badge variant="outline">
                                                                {t('Availability')}: {t(provider.availability)}
                                                            </Badge>
                                                            {provider.mode ? (
                                                                <Badge variant="outline">
                                                                    {t('Mode')}: {t(provider.mode)}
                                                                </Badge>
                                                            ) : null}
                                                            {provider.supports_installment ? (
                                                                <Badge variant="outline">{t('Installments')}</Badge>
                                                            ) : null}
                                                            <Badge variant={provider.is_configured ? 'default' : 'secondary'}>
                                                                {provider.is_configured ? t('Configured') : t('Needs Setup')}
                                                            </Badge>
                                                        </div>

                                                        <div className="mt-4 flex items-center justify-between gap-2">
                                                            <span className="text-xs text-muted-foreground">
                                                                {provider.admin_default_configured ? t('Ready') : t('Requires credentials')}
                                                            </span>
                                                            <span className="text-xs font-medium text-primary">
                                                                {t('Configure')}
                                                            </span>
                                                        </div>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    ) : null}

                                    {paymentProvidersView === 'config' && selectedPaymentProvider ? (
                                        (() => {
                                            const provider = selectedPaymentProvider;
                                            const formState = providerForms[provider.slug] ?? createProviderFormState(provider);
                                            const isSavingThisProvider = savingProviderSlug === provider.slug;
                                            const { icon: FallbackIcon, accentClass } = getPaymentProviderCardMeta(provider);
                                            const hasImageIcon = typeof provider.icon === 'string'
                                                && provider.icon.trim() !== ''
                                                && /^(https?:\/\/|\/|data:image)/i.test(provider.icon.trim());

                                            return (
                                                <div className="space-y-3">
                                                    <div className="flex items-center justify-between gap-2">
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={closePaymentProviderConfigView}
                                                        >
                                                            {t('Back to Payment Methods')}
                                                        </Button>
                                                        <Badge variant="outline">{provider.slug}</Badge>
                                                    </div>

                                                    <Card className="border-primary/30">
                                                    <CardHeader className="space-y-3">
                                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                                            <div className="flex items-center gap-3 min-w-0">
                                                                <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border ${accentClass}`}>
                                                                    {hasImageIcon ? (
                                                                        <img
                                                                            src={provider.icon as string}
                                                                            alt={provider.name}
                                                                            className="h-8 w-8 object-contain"
                                                                        />
                                                                    ) : (
                                                                        <FallbackIcon className="h-5 w-5" />
                                                                    )}
                                                                </div>
                                                                <div className="min-w-0">
                                                                    <CardTitle className="text-base">{provider.name}</CardTitle>
                                                                    <CardDescription className="truncate">{provider.description}</CardDescription>
                                                                </div>
                                                            </div>
                                                            <div className="flex flex-wrap gap-2">
                                                                <Badge variant={statusBadgeVariant(provider.is_enabled ? 'active' : 'inactive')}>
                                                                    {provider.is_enabled ? t('Active') : t('Disabled')}
                                                                </Badge>
                                                                <Badge variant="outline">
                                                                    {t('Availability')}: {t(provider.availability)}
                                                                </Badge>
                                                                {provider.mode ? (
                                                                    <Badge variant="outline">
                                                                        {t('Mode')}: {t(provider.mode)}
                                                                    </Badge>
                                                                ) : null}
                                                            </div>
                                                        </div>
                                                        <p className="text-xs text-muted-foreground">
                                                            {provider.admin_default_configured
                                                                ? t('Default provider credentials are configured')
                                                                : t('Default credentials are missing. Add site credentials below.')}
                                                        </p>
                                                    </CardHeader>

                                                    <CardContent className="space-y-4">
                                                        <div className="grid gap-3 md:grid-cols-2">
                                                            <div className="space-y-2">
                                                                <Label>{t('Availability')}</Label>
                                                                <select
                                                                    value={formState.availability}
                                                                    onChange={(event) => handleProviderAvailabilityChange(provider.slug, event.target.value as ProviderAvailability)}
                                                                    className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                                >
                                                                    {PROVIDER_AVAILABILITY_OPTIONS.map((availability) => (
                                                                        <option key={`${provider.slug}-${availability}`} value={availability}>
                                                                            {t(availability)}
                                                                        </option>
                                                                    ))}
                                                                </select>
                                                            </div>
                                                            <div className="space-y-2">
                                                                <Label>{t('Provider Key')}</Label>
                                                                <Input value={provider.slug} readOnly />
                                                            </div>
                                                        </div>

                                                        <div className="grid gap-3 md:grid-cols-2">
                                                            <div className="space-y-2">
                                                                <Label>{t('Webhook URL')}</Label>
                                                                <div className="flex items-center gap-2">
                                                                    <Input value={provider.callbacks.webhook_url} readOnly />
                                                                    <Button
                                                                        type="button"
                                                                        variant="outline"
                                                                        size="sm"
                                                                        onClick={() => void handleCopyValue(provider.callbacks.webhook_url, t('Webhook URL copied'))}
                                                                    >
                                                                        <Copy className="h-4 w-4" />
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                            <div className="space-y-2">
                                                                <Label>{t('Return Callback URL')}</Label>
                                                                <div className="flex items-center gap-2">
                                                                    <Input value={provider.callbacks.callback_url} readOnly />
                                                                    <Button
                                                                        type="button"
                                                                        variant="outline"
                                                                        size="sm"
                                                                        onClick={() => void handleCopyValue(provider.callbacks.callback_url, t('Callback URL copied'))}
                                                                    >
                                                                        <Copy className="h-4 w-4" />
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div className="space-y-3">
                                                            <p className="text-sm font-medium">{t('Configuration')}</p>
                                                            {provider.config_schema.length === 0 ? (
                                                                <p className="text-sm text-muted-foreground">{t('This provider has no configurable fields')}</p>
                                                            ) : (
                                                                <div className="grid gap-3 md:grid-cols-2">
                                                                    {provider.config_schema.map((field) => {
                                                                        const hasOwnOverride = Object.prototype.hasOwnProperty.call(formState.config, field.name);
                                                                        const rawValue = formState.config[field.name];

                                                                        if (field.type === 'toggle') {
                                                                            const selectValue = hasOwnOverride
                                                                                ? (rawValue ? 'true' : 'false')
                                                                                : 'inherit';

                                                                            return (
                                                                                <div key={`${provider.slug}-${field.name}`} className="space-y-2">
                                                                                    <Label>{t(field.label)}</Label>
                                                                                    <select
                                                                                        value={selectValue}
                                                                                        onChange={(event) => {
                                                                                            const nextValue = event.target.value;
                                                                                            if (nextValue === 'inherit') {
                                                                                                handleProviderFieldChange(provider.slug, field, null);
                                                                                                return;
                                                                                            }

                                                                                            handleProviderFieldChange(provider.slug, field, nextValue === 'true');
                                                                                        }}
                                                                                        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                                                    >
                                                                                        <option value="inherit">{t('Inherit default')}</option>
                                                                                        <option value="true">{t('Enabled')}</option>
                                                                                        <option value="false">{t('Disabled')}</option>
                                                                                    </select>
                                                                                    {field.help ? (
                                                                                        <p className="text-xs text-muted-foreground">{t(field.help)}</p>
                                                                                    ) : null}
                                                                                </div>
                                                                            );
                                                                        }

                                                                        if (field.type === 'textarea') {
                                                                            return (
                                                                                <div key={`${provider.slug}-${field.name}`} className="space-y-2 md:col-span-2">
                                                                                    <Label>
                                                                                        {t(field.label)}
                                                                                        {field.required ? <span className="text-destructive ms-1">*</span> : null}
                                                                                    </Label>
                                                                                    <Textarea
                                                                                        value={hasOwnOverride ? String(rawValue ?? '') : ''}
                                                                                        onChange={(event) => handleProviderFieldChange(provider.slug, field, event.target.value)}
                                                                                        placeholder={field.placeholder ? t(field.placeholder) : undefined}
                                                                                        rows={field.rows || 4}
                                                                                    />
                                                                                    {field.help ? (
                                                                                        <p className="text-xs text-muted-foreground">{t(field.help)}</p>
                                                                                    ) : null}
                                                                                </div>
                                                                            );
                                                                        }

                                                                        if (field.type === 'select') {
                                                                            return (
                                                                                <div key={`${provider.slug}-${field.name}`} className="space-y-2">
                                                                                    <Label>
                                                                                        {t(field.label)}
                                                                                        {field.required ? <span className="text-destructive ms-1">*</span> : null}
                                                                                    </Label>
                                                                                    <select
                                                                                        value={hasOwnOverride ? String(rawValue ?? '') : ''}
                                                                                        onChange={(event) => handleProviderFieldChange(provider.slug, field, event.target.value)}
                                                                                        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                                                    >
                                                                                        <option value="">{t('Inherit default')}</option>
                                                                                        {(field.options ?? []).map((option) => (
                                                                                            <option key={`${provider.slug}-${field.name}-${option.value}`} value={option.value}>
                                                                                                {t(option.label)}
                                                                                            </option>
                                                                                        ))}
                                                                                    </select>
                                                                                    {field.help ? (
                                                                                        <p className="text-xs text-muted-foreground">{t(field.help)}</p>
                                                                                    ) : null}
                                                                                </div>
                                                                            );
                                                                        }

                                                                        return (
                                                                            <div key={`${provider.slug}-${field.name}`} className="space-y-2">
                                                                                <Label>
                                                                                    {t(field.label)}
                                                                                    {field.required ? <span className="text-destructive ms-1">*</span> : null}
                                                                                </Label>
                                                                                <Input
                                                                                    type={field.type === 'password' ? 'password' : 'text'}
                                                                                    value={hasOwnOverride ? String(rawValue ?? '') : ''}
                                                                                    placeholder={field.placeholder ? t(field.placeholder) : undefined}
                                                                                    onChange={(event) => handleProviderFieldChange(provider.slug, field, event.target.value)}
                                                                                />
                                                                                {field.help ? (
                                                                                    <p className="text-xs text-muted-foreground">{t(field.help)}</p>
                                                                                ) : null}
                                                                            </div>
                                                                        );
                                                                    })}
                                                                </div>
                                                            )}
                                                        </div>

                                                        <div className="flex flex-wrap justify-end gap-2">
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                onClick={() => {
                                                                    setProviderForms((prev) => ({
                                                                        ...prev,
                                                                        [provider.slug]: createProviderFormState(provider),
                                                                    }));
                                                                }}
                                                            >
                                                                {t('Reset')}
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                onClick={() => void handleProviderSave(provider)}
                                                                disabled={isSavingThisProvider}
                                                            >
                                                                {isSavingThisProvider ? (
                                                                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                                                ) : (
                                                                    <CheckCircle2 className="h-4 w-4 mr-2" />
                                                                )}
                                                                {t('Save')}
                                                            </Button>
                                                        </div>
                                                    </CardContent>
                                                    </Card>
                                                </div>
                                            );
                                        })()
                                    ) : null}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="shippingProviders" className="min-w-0 max-w-full space-y-4 pt-4 overflow-x-hidden">
                    <Card>
                        <CardHeader className="flex-row items-center justify-between space-y-0">
                            <div>
                                <CardTitle>{t('Shipping')}</CardTitle>
                                <CardDescription>{t('Enable and configure shipping methods')}</CardDescription>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => void loadShippingCouriers()}
                                disabled={isShippingCouriersLoading}
                            >
                                {isShippingCouriersLoading ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <RefreshCw className="h-4 w-4" />
                                )}
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {shippingCouriers.length === 0 ? (
                                <div className="py-10 text-sm text-muted-foreground text-center">
                                    {isShippingCouriersLoading ? t('Loading shipping providers...') : t('No shipping providers available')}
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                        {shippingCouriers.map((courier) => {
                                            const { icon: FallbackIcon, accentClass } = getShippingCourierCardMeta(courier);
                                            const isSelected = selectedShippingCourierSlug === courier.slug;
                                            const hasImageIcon = typeof courier.icon === 'string'
                                                && courier.icon.trim() !== ''
                                                && /^(https?:\/\/|\/|data:image)/i.test(courier.icon.trim());

                                            return (
                                                <button
                                                    key={courier.slug}
                                                    type="button"
                                                    onClick={() => setSelectedShippingCourierSlug(courier.slug)}
                                                    className={[
                                                        'text-left rounded-xl border p-4 transition-colors',
                                                        'hover:border-primary/40 hover:bg-muted/20',
                                                        isSelected ? 'border-primary ring-1 ring-primary/30 bg-primary/5' : '',
                                                        !courier.is_enabled ? 'opacity-90' : '',
                                                    ].join(' ')}
                                                >
                                                    <div className="flex items-start justify-between gap-3">
                                                        <div className="flex min-w-0 items-center gap-3">
                                                            <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border ${accentClass}`}>
                                                                {hasImageIcon ? (
                                                                    <img
                                                                        src={courier.icon as string}
                                                                        alt={courier.name}
                                                                        className="h-8 w-8 object-contain"
                                                                    />
                                                                ) : (
                                                                    <FallbackIcon className="h-5 w-5" />
                                                                )}
                                                            </div>
                                                            <div className="min-w-0">
                                                                <p className="truncate text-sm font-semibold">{courier.name}</p>
                                                                <p className="truncate text-xs text-muted-foreground">{courier.slug}</p>
                                                            </div>
                                                        </div>
                                                        <Badge variant={statusBadgeVariant(courier.is_enabled ? 'active' : 'inactive')}>
                                                            {courier.is_enabled ? t('Active') : t('Disabled')}
                                                        </Badge>
                                                    </div>

                                                    <p className="mt-3 line-clamp-2 text-xs text-muted-foreground">
                                                        {courier.description || t('Shipping provider')}
                                                    </p>

                                                    <div className="mt-3 flex flex-wrap gap-2">
                                                        <Badge variant="outline">
                                                            {t('Availability')}: {t(courier.availability)}
                                                        </Badge>
                                                        {courier.mode ? (
                                                            <Badge variant="outline">
                                                                {t('Mode')}: {t(courier.mode)}
                                                            </Badge>
                                                        ) : null}
                                                        {courier.supports_tracking ? (
                                                            <Badge variant="outline">{t('Tracking')}</Badge>
                                                        ) : null}
                                                        <Badge variant={courier.is_configured ? 'default' : 'secondary'}>
                                                            {courier.is_configured ? t('Configured') : t('Needs Setup')}
                                                        </Badge>
                                                    </div>

                                                    <div className="mt-4 flex items-center justify-between gap-2">
                                                        <span className="text-xs text-muted-foreground">
                                                            {courier.admin_default_configured ? t('Ready') : t('Requires credentials')}
                                                        </span>
                                                        <span className="text-xs font-medium text-primary">
                                                            {t('Configure')}
                                                        </span>
                                                    </div>
                                                </button>
                                            );
                                        })}
                                    </div>

                                    {selectedShippingCourier ? (
                                        (() => {
                                            const courier = selectedShippingCourier;
                                            const formState = courierForms[courier.slug] ?? createCourierFormState(courier);
                                            const isSavingThisCourier = savingCourierSlug === courier.slug;
                                            const { icon: FallbackIcon, accentClass } = getShippingCourierCardMeta(courier);
                                            const hasImageIcon = typeof courier.icon === 'string'
                                                && courier.icon.trim() !== ''
                                                && /^(https?:\/\/|\/|data:image)/i.test(courier.icon.trim());

                                            return (
                                                <Card className="border-primary/30">
                                                    <CardHeader className="space-y-3">
                                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                                            <div className="flex min-w-0 items-center gap-3">
                                                                <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border ${accentClass}`}>
                                                                    {hasImageIcon ? (
                                                                        <img
                                                                            src={courier.icon as string}
                                                                            alt={courier.name}
                                                                            className="h-8 w-8 object-contain"
                                                                        />
                                                                    ) : (
                                                                        <FallbackIcon className="h-5 w-5" />
                                                                    )}
                                                                </div>
                                                                <div className="min-w-0">
                                                                    <CardTitle className="text-base">{courier.name}</CardTitle>
                                                                    <CardDescription className="truncate">{courier.description}</CardDescription>
                                                                </div>
                                                            </div>
                                                            <div className="flex flex-wrap gap-2">
                                                                <Badge variant={statusBadgeVariant(courier.is_enabled ? 'active' : 'inactive')}>
                                                                    {courier.is_enabled ? t('Active') : t('Disabled')}
                                                                </Badge>
                                                                <Badge variant="outline">
                                                                    {t('Availability')}: {t(courier.availability)}
                                                                </Badge>
                                                                {courier.mode ? (
                                                                    <Badge variant="outline">
                                                                        {t('Mode')}: {t(courier.mode)}
                                                                    </Badge>
                                                                ) : null}
                                                            </div>
                                                        </div>
                                                        <p className="text-xs text-muted-foreground">
                                                            {courier.admin_default_configured
                                                                ? t('Admin default credentials are configured for this shipping provider')
                                                                : t('Admin default credentials are missing; enable this shipping provider with site credentials')}
                                                        </p>
                                                    </CardHeader>

                                                    <CardContent className="space-y-4">
                                                        <div className="grid gap-3 md:grid-cols-3">
                                                            <div className="space-y-2">
                                                                <Label>{t('Availability')}</Label>
                                                                <select
                                                                    value={formState.availability}
                                                                    onChange={(event) => handleCourierAvailabilityChange(courier.slug, event.target.value as ProviderAvailability)}
                                                                    className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                                >
                                                                    {PROVIDER_AVAILABILITY_OPTIONS.map((availability) => (
                                                                        <option key={`${courier.slug}-${availability}`} value={availability}>
                                                                            {t(availability)}
                                                                        </option>
                                                                    ))}
                                                                </select>
                                                            </div>
                                                            <div className="space-y-2">
                                                                <Label>{t('Provider Key')}</Label>
                                                                <Input value={courier.slug} readOnly />
                                                            </div>
                                                            <div className="space-y-2">
                                                                <Label>{t('Supported Countries')}</Label>
                                                                <Input value={courier.supported_countries.length > 0 ? courier.supported_countries.join(', ') : t('All')} readOnly />
                                                            </div>
                                                        </div>

                                                        <div className="space-y-3">
                                                            <p className="text-sm font-medium">{t('Site Config / Overrides')}</p>
                                                            {courier.config_schema.length === 0 ? (
                                                                <p className="text-sm text-muted-foreground">{t('This shipping provider has no configurable fields')}</p>
                                                            ) : (
                                                                <div className="grid gap-3 md:grid-cols-2">
                                                                    {courier.config_schema.map((field) => {
                                                                        const hasOwnOverride = Object.prototype.hasOwnProperty.call(formState.config, field.name);
                                                                        const rawValue = formState.config[field.name];

                                                                        if (field.type === 'toggle') {
                                                                            const selectValue = hasOwnOverride
                                                                                ? (rawValue ? 'true' : 'false')
                                                                                : 'inherit';

                                                                            return (
                                                                                <div key={field.name} className="space-y-2">
                                                                                    <Label>{t(field.label)}</Label>
                                                                                    <select
                                                                                        value={selectValue}
                                                                                        onChange={(event) => {
                                                                                            const nextValue = event.target.value;
                                                                                            if (nextValue === 'inherit') {
                                                                                                handleCourierFieldChange(courier.slug, field, null);
                                                                                                return;
                                                                                            }

                                                                                            handleCourierFieldChange(courier.slug, field, nextValue === 'true');
                                                                                        }}
                                                                                        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                                                    >
                                                                                        <option value="inherit">{t('Inherit default')}</option>
                                                                                        <option value="true">{t('Enabled')}</option>
                                                                                        <option value="false">{t('Disabled')}</option>
                                                                                    </select>
                                                                                    {field.help ? (
                                                                                        <p className="text-xs text-muted-foreground">{t(field.help)}</p>
                                                                                    ) : null}
                                                                                </div>
                                                                            );
                                                                        }

                                                                        if (field.type === 'textarea') {
                                                                            return (
                                                                                <div key={field.name} className="space-y-2 md:col-span-2">
                                                                                    <Label>
                                                                                        {t(field.label)}
                                                                                        {field.required ? <span className="text-destructive ms-1">*</span> : null}
                                                                                    </Label>
                                                                                    <Textarea
                                                                                        value={hasOwnOverride ? String(rawValue ?? '') : ''}
                                                                                        onChange={(event) => handleCourierFieldChange(courier.slug, field, event.target.value)}
                                                                                        placeholder={field.placeholder ? t(field.placeholder) : undefined}
                                                                                        rows={field.rows || 4}
                                                                                    />
                                                                                    {field.help ? (
                                                                                        <p className="text-xs text-muted-foreground">{t(field.help)}</p>
                                                                                    ) : null}
                                                                                </div>
                                                                            );
                                                                        }

                                                                        if (field.type === 'select') {
                                                                            return (
                                                                                <div key={field.name} className="space-y-2">
                                                                                    <Label>
                                                                                        {t(field.label)}
                                                                                        {field.required ? <span className="text-destructive ms-1">*</span> : null}
                                                                                    </Label>
                                                                                    <select
                                                                                        value={hasOwnOverride ? String(rawValue ?? '') : ''}
                                                                                        onChange={(event) => handleCourierFieldChange(courier.slug, field, event.target.value)}
                                                                                        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                                                    >
                                                                                        <option value="">{t('Inherit default')}</option>
                                                                                        {(field.options ?? []).map((option) => (
                                                                                            <option key={option.value} value={option.value}>
                                                                                                {t(option.label)}
                                                                                            </option>
                                                                                        ))}
                                                                                    </select>
                                                                                    {field.help ? (
                                                                                        <p className="text-xs text-muted-foreground">{t(field.help)}</p>
                                                                                    ) : null}
                                                                                </div>
                                                                            );
                                                                        }

                                                                        return (
                                                                            <div key={field.name} className="space-y-2">
                                                                                <Label>
                                                                                    {t(field.label)}
                                                                                    {field.required ? <span className="text-destructive ms-1">*</span> : null}
                                                                                </Label>
                                                                                <Input
                                                                                    type={field.type === 'password' ? 'password' : 'text'}
                                                                                    value={hasOwnOverride ? String(rawValue ?? '') : ''}
                                                                                    placeholder={field.placeholder ? t(field.placeholder) : undefined}
                                                                                    onChange={(event) => handleCourierFieldChange(courier.slug, field, event.target.value)}
                                                                                />
                                                                                {field.help ? (
                                                                                    <p className="text-xs text-muted-foreground">{t(field.help)}</p>
                                                                                ) : null}
                                                                            </div>
                                                                        );
                                                                    })}
                                                                </div>
                                                            )}
                                                        </div>

                                                        <div className="flex flex-wrap justify-end gap-2">
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                onClick={() => {
                                                                    setCourierForms((prev) => ({
                                                                        ...prev,
                                                                        [courier.slug]: createCourierFormState(courier),
                                                                    }));
                                                                }}
                                                            >
                                                                {t('Reset')}
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                onClick={() => void handleCourierSave(courier)}
                                                                disabled={isSavingThisCourier}
                                                            >
                                                                {isSavingThisCourier ? (
                                                                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                                                ) : (
                                                                    <CheckCircle2 className="h-4 w-4 mr-2" />
                                                                )}
                                                                {t('Save Shipping Settings')}
                                                            </Button>
                                                        </div>
                                                    </CardContent>
                                                </Card>
                                            );
                                        })()
                                    ) : null}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    );
}
