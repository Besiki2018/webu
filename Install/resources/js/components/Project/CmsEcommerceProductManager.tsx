import { ChangeEvent, DragEvent, FormEvent, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import axios, { AxiosError } from 'axios';
import { toast } from 'sonner';
import {
    ArrowLeft,
    Boxes,
    CheckCircle2,
    CircleDollarSign,
    Copy,
    Download,
    Eye,
    GripVertical,
    LayoutGrid,
    Loader2,
    MoreVertical,
    Package,
    Pencil,
    Plus,
    RefreshCw,
    Search,
    Tag,
    Trash2,
    Upload,
    X,
} from 'lucide-react';
import { useTranslation } from '@/contexts/LanguageContext';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RichTextField } from '@/components/ui/rich-text-field';
import { Switch } from '@/components/ui/switch';

type ProductStatus = 'draft' | 'active' | 'archived';
type StockStatus = 'in_stock' | 'out_of_stock' | 'backorder';
type AttributeType = 'text' | 'color' | 'size';

interface CmsEcommerceProductManagerProps {
    siteId: string;
    mode?: 'all' | 'create';
}

interface ApiErrorPayload {
    message?: string;
    error?: string;
    errors?: Record<string, string[]>;
}

interface EcommerceCategory {
    id: number;
    name: string;
    slug: string;
    status: 'active' | 'inactive';
}

interface EcommerceProductListItem {
    id: number;
    category_id: number | null;
    category_name: string | null;
    name: string;
    slug: string;
    sku: string | null;
    price: string;
    compare_at_price: string | null;
    currency: string;
    status: ProductStatus;
    stock_quantity: number;
    primary_image_url?: string | null;
    primary_image_alt?: string | null;
    updated_at: string | null;
}

interface EcommerceProductImage {
    id: number;
    media_id: number | null;
    path: string;
    asset_url?: string | null;
    alt_text: string | null;
    sort_order: number;
    is_primary: boolean;
}

interface EcommerceProductVariant {
    id: number;
    name: string;
    sku: string | null;
    options_json: Record<string, unknown>;
    price: string | null;
    compare_at_price: string | null;
    stock_tracking: boolean;
    stock_quantity: number;
    allow_backorder: boolean;
    is_default: boolean;
    position: number;
}

interface EcommerceProductDetail extends EcommerceProductListItem {
    short_description: string | null;
    description: string | null;
    stock_tracking: boolean;
    allow_backorder: boolean;
    is_digital: boolean;
    weight_grams: number | null;
    attributes_json: Record<string, unknown>;
    seo_title: string | null;
    seo_description: string | null;
    images: EcommerceProductImage[];
    variants: EcommerceProductVariant[];
}

interface EcommerceCategoryListResponse {
    site_id: string;
    categories: EcommerceCategory[];
}

interface EcommerceProductListResponse {
    site_id: string;
    products: EcommerceProductListItem[];
}

interface EcommerceProductDetailResponse {
    site_id: string;
    product: EcommerceProductDetail;
}

interface ProductMutationResponse {
    message?: string;
    product?: EcommerceProductDetail | EcommerceProductListItem;
}

interface MediaUploadResponse {
    message?: string;
    media: {
        id: number;
        path: string;
        asset_url: string;
        meta_json?: Record<string, unknown>;
    };
}

interface ProductAttributeValueDraft {
    id: string;
    label: string;
    colorHex: string;
}

interface ProductAttributeDraft {
    id: string;
    name: string;
    type: AttributeType;
    values: ProductAttributeValueDraft[];
}

interface ProductVariantDraft {
    localId: string;
    id: number | null;
    name: string;
    sku: string;
    price: string;
    compareAtPrice: string;
    stockQuantity: string;
    stockTracking: boolean;
    allowBackorder: boolean;
    isDefault: boolean;
    position: number;
    optionValues: Record<string, string>;
    imagePath: string;
}

interface ProductImageDraft {
    localId: string;
    id: number | null;
    mediaId: number | null;
    path: string;
    assetUrl: string;
    altText: string;
    isPrimary: boolean;
    sortOrder: number;
}

interface ProductEditorFormState {
    categoryId: string;
    name: string;
    slug: string;
    shortDescription: string;
    description: string;
    status: ProductStatus;
    regularPrice: string;
    discountPrice: string;
    currency: string;
    sku: string;
    stockQuantity: string;
    stockStatus: StockStatus;
    stockTracking: boolean;
    allowBackorder: boolean;
    isDigital: boolean;
    weightGrams: string;
    seoTitle: string;
    seoDescription: string;
}

function createLocalId(prefix: string): string {
    return `${prefix}_${Math.random().toString(36).slice(2, 10)}`;
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
        .replace(/[^a-z0-9\\s-]/g, '')
        .replace(/\\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
}

function stripHtmlToPlainText(value: string): string {
    return value
        .replace(/<[^>]+>/g, ' ')
        .replace(/&nbsp;/gi, ' ')
        .replace(/\s+/g, ' ')
        .trim();
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

function getApiErrorMessage(error: unknown, fallback: string): string {
    const axiosError = error as AxiosError<ApiErrorPayload>;
    const payload = axiosError.response?.data;

    if (payload?.error) {
        return payload.error;
    }

    if (payload?.message) {
        return payload.message;
    }

    const firstValidationError = payload?.errors ? Object.values(payload.errors).flat()[0] : null;

    return firstValidationError ?? fallback;
}

function csvEscapeCell(value: unknown): string {
    const stringValue = String(value ?? '');
    if (/[",\n\r]/.test(stringValue)) {
        return `"${stringValue.replace(/"/g, '""')}"`;
    }

    return stringValue;
}

function downloadTextFile(filename: string, content: string, mimeType: string): void {
    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(url);
}

function parseCsv(text: string): Array<Record<string, string>> {
    const source = text.replace(/^\uFEFF/, '');
    const rows: string[][] = [];
    let currentRow: string[] = [];
    let currentCell = '';
    let inQuotes = false;

    for (let index = 0; index < source.length; index += 1) {
        const char = source[index];

        if (inQuotes) {
            if (char === '"') {
                if (source[index + 1] === '"') {
                    currentCell += '"';
                    index += 1;
                } else {
                    inQuotes = false;
                }
            } else {
                currentCell += char;
            }
            continue;
        }

        if (char === '"') {
            inQuotes = true;
            continue;
        }

        if (char === ',') {
            currentRow.push(currentCell);
            currentCell = '';
            continue;
        }

        if (char === '\n') {
            currentRow.push(currentCell);
            rows.push(currentRow);
            currentRow = [];
            currentCell = '';
            continue;
        }

        if (char === '\r') {
            continue;
        }

        currentCell += char;
    }

    if (currentCell !== '' || currentRow.length > 0) {
        currentRow.push(currentCell);
        rows.push(currentRow);
    }

    if (rows.length === 0) {
        return [];
    }

    const [headerRow, ...dataRows] = rows;
    const headers = (headerRow ?? []).map((header) => header.trim().toLowerCase());

    return dataRows
        .filter((row) => row.some((cell) => cell.trim() !== ''))
        .map((row) => {
            const record: Record<string, string> = {};
            headers.forEach((header, columnIndex) => {
                if (!header) {
                    return;
                }

                record[header] = (row[columnIndex] ?? '').trim();
            });
            return record;
        });
}

function parseBooleanLike(value: string | undefined, fallback = false): boolean {
    const normalized = (value ?? '').trim().toLowerCase();
    if (normalized === '') {
        return fallback;
    }

    return ['1', 'true', 'yes', 'y', 'on'].includes(normalized);
}

function normalizeImportedStatus(value: string | undefined): ProductStatus {
    const normalized = (value ?? '').trim().toLowerCase();
    if (normalized === 'active' || normalized === 'published') {
        return 'active';
    }
    if (normalized === 'archived') {
        return 'archived';
    }
    return 'draft';
}

function statusBadgeVariant(status: string): 'default' | 'secondary' | 'outline' | 'success' | 'warning' {
    switch (status) {
        case 'active':
            return 'success';
        case 'archived':
            return 'secondary';
        default:
            return 'warning';
    }
}

function createEmptyProductEditorForm(): ProductEditorFormState {
    return {
        categoryId: '',
        name: '',
        slug: '',
        shortDescription: '',
        description: '',
        status: 'draft',
        regularPrice: '0',
        discountPrice: '',
        currency: 'GEL',
        sku: '',
        stockQuantity: '0',
        stockStatus: 'in_stock',
        stockTracking: true,
        allowBackorder: false,
        isDigital: false,
        weightGrams: '',
        seoTitle: '',
        seoDescription: '',
    };
}

function createEmptyAttributeValue(label = ''): ProductAttributeValueDraft {
    return {
        id: createLocalId('attr_value'),
        label,
        colorHex: '#111111',
    };
}

function createEmptyAttributeDraft(): ProductAttributeDraft {
    return {
        id: createLocalId('attr'),
        name: '',
        type: 'text',
        values: [createEmptyAttributeValue('')],
    };
}

function createEmptyVariantDraft(partial?: Partial<ProductVariantDraft>): ProductVariantDraft {
    return {
        localId: partial?.localId ?? createLocalId('variant'),
        id: partial?.id ?? null,
        name: partial?.name ?? '',
        sku: partial?.sku ?? '',
        price: partial?.price ?? '',
        compareAtPrice: partial?.compareAtPrice ?? '',
        stockQuantity: partial?.stockQuantity ?? '0',
        stockTracking: partial?.stockTracking ?? true,
        allowBackorder: partial?.allowBackorder ?? false,
        isDefault: partial?.isDefault ?? false,
        position: partial?.position ?? 0,
        optionValues: partial?.optionValues ?? {},
        imagePath: partial?.imagePath ?? '',
    };
}

function createEmptyImageDraft(): ProductImageDraft {
    return {
        localId: createLocalId('img'),
        id: null,
        mediaId: null,
        path: '',
        assetUrl: '',
        altText: '',
        isPrimary: false,
        sortOrder: 0,
    };
}

function deriveStockStatus(stockQuantity: number, allowBackorder: boolean): StockStatus {
    if (allowBackorder) {
        return 'backorder';
    }

    if (stockQuantity <= 0) {
        return 'out_of_stock';
    }

    return 'in_stock';
}

function productStatusLabel(status: ProductStatus, t: (value: string) => string): string {
    switch (status) {
        case 'active':
            return t('Published');
        case 'archived':
            return t('Archived');
        default:
            return t('Draft');
    }
}

function productInitials(name: string): string {
    const tokens = name.trim().split(/\s+/).filter(Boolean);
    if (tokens.length === 0) {
        return 'P';
    }
    return tokens.slice(0, 2).map((token) => token[0]?.toUpperCase() ?? '').join('');
}

function categoryColorClass(categoryName: string | null | undefined): string {
    const value = (categoryName ?? '').toLowerCase();
    if (value.includes('electr')) return 'bg-blue-100 text-blue-700';
    if (value.includes('home') || value.includes('decor')) return 'bg-cyan-100 text-cyan-700';
    if (value.includes('shoe') || value.includes('foot')) return 'bg-green-100 text-green-700';
    if (value.includes('office') || value.includes('business')) return 'bg-amber-100 text-amber-700';
    if (value.includes('game')) return 'bg-violet-100 text-violet-700';
    if (value.includes('access')) return 'bg-rose-100 text-rose-700';
    return 'bg-slate-100 text-slate-700';
}

function stockLabel(stockQuantity: number, t: (value: string) => string): string {
    if (stockQuantity <= 0) {
        return t('Out of Stock');
    }
    return t('In Stock');
}

function stockBadgeVariant(stockQuantity: number): 'secondary' | 'success' {
    return stockQuantity <= 0 ? 'secondary' : 'success';
}

function buildAttributeStorage(attributes: ProductAttributeDraft[]): Record<string, unknown> {
    const normalized = attributes
        .map((attribute) => ({
            name: attribute.name.trim(),
            type: attribute.type,
            values: attribute.values
                .map((value) => ({
                    label: value.label.trim(),
                    color_hex: attribute.type === 'color' ? value.colorHex : undefined,
                }))
                .filter((value) => value.label !== ''),
        }))
        .filter((attribute) => attribute.name !== '' && attribute.values.length > 0);

    if (normalized.length === 0) {
        return {};
    }

    return {
        attributes: normalized,
    };
}

function cartesianCombinations(attributes: ProductAttributeDraft[]): Array<Record<string, string>> {
    const normalized = attributes
        .map((attribute) => ({
            name: attribute.name.trim(),
            values: attribute.values.map((value) => value.label.trim()).filter((value) => value !== ''),
        }))
        .filter((attribute) => attribute.name !== '' && attribute.values.length > 0);

    if (normalized.length === 0) {
        return [];
    }

    return normalized.reduce<Array<Record<string, string>>>((acc, attribute) => {
        if (acc.length === 0) {
            return attribute.values.map((value) => ({ [attribute.name]: value }));
        }

        return acc.flatMap((combination) => (
            attribute.values.map((value) => ({
                ...combination,
                [attribute.name]: value,
            }))
        ));
    }, []);
}

function variantCombinationKey(optionValues: Record<string, string>): string {
    return Object.entries(optionValues)
        .filter(([key]) => !key.startsWith('__'))
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([key, value]) => `${key}:${value}`)
        .join('|');
}

function toVariantDisplayName(optionValues: Record<string, string>): string {
    const values = Object.entries(optionValues)
        .filter(([key]) => !key.startsWith('__'))
        .map(([, value]) => value)
        .filter((value) => value.trim() !== '');

    return values.length > 0 ? values.join(' / ') : '';
}

function extractVariantImagePath(options: Record<string, unknown>): string {
    const raw = options.__image_path;
    return typeof raw === 'string' ? raw : '';
}

function sanitizeVariantOptionsForSave(variant: ProductVariantDraft): Record<string, unknown> {
    const options: Record<string, unknown> = {};
    Object.entries(variant.optionValues).forEach(([key, value]) => {
        if (key.startsWith('__')) {
            return;
        }
        if (value.trim() !== '') {
            options[key] = value.trim();
        }
    });

    if (variant.imagePath.trim() !== '') {
        options.__image_path = variant.imagePath.trim();
    }

    return options;
}

export function CmsEcommerceProductManager({ siteId, mode = 'all' }: CmsEcommerceProductManagerProps) {
    const { t } = useTranslation();

    const [categories, setCategories] = useState<EcommerceCategory[]>([]);
    const [products, setProducts] = useState<EcommerceProductListItem[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [isLoadingEditor, setIsLoadingEditor] = useState(false);
    const [isDuplicating, setIsDuplicating] = useState(false);
    const [deletingProductId, setDeletingProductId] = useState<number | null>(null);
    const [view, setView] = useState<'catalog' | 'editor'>('catalog');
    const [editingProductId, setEditingProductId] = useState<number | null>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<'all' | ProductStatus>('all');
    const [categoryFilter, setCategoryFilter] = useState<string>('all');
    const [selectedProductIds, setSelectedProductIds] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [isSeedingDemo, setIsSeedingDemo] = useState(false);
    const [isImportingCsv, setIsImportingCsv] = useState(false);
    const [editorForm, setEditorForm] = useState<ProductEditorFormState>(createEmptyProductEditorForm());
    const [productImages, setProductImages] = useState<ProductImageDraft[]>([]);
    const [productAttributes, setProductAttributes] = useState<ProductAttributeDraft[]>([]);
    const [productVariants, setProductVariants] = useState<ProductVariantDraft[]>([]);
    const [imageDragLocalId, setImageDragLocalId] = useState<string | null>(null);
    const featuredUploadRef = useRef<HTMLInputElement | null>(null);
    const galleryUploadRef = useRef<HTMLInputElement | null>(null);
    const importCsvRef = useRef<HTMLInputElement | null>(null);
    const initialCatalogLoadedForSiteRef = useRef<string | null>(null);

    const loadCatalog = useCallback(async () => {
        setIsLoading(true);
        try {
            const [categoriesResponse, productsResponse] = await Promise.all([
                axios.get<EcommerceCategoryListResponse>(`/panel/sites/${siteId}/ecommerce/categories`),
                axios.get<EcommerceProductListResponse>(`/panel/sites/${siteId}/ecommerce/products`),
            ]);
            setCategories(categoriesResponse.data.categories ?? []);
            setProducts(productsResponse.data.products ?? []);
            setSelectedProductIds((prev) => {
                const available = new Set((productsResponse.data.products ?? []).map((item) => item.id));
                return prev.filter((id) => available.has(id));
            });
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to load product catalog')));
        } finally {
            setIsLoading(false);
        }
    }, [siteId, t]);

    useEffect(() => {
        if (initialCatalogLoadedForSiteRef.current === siteId) {
            return;
        }

        initialCatalogLoadedForSiteRef.current = siteId;
        void loadCatalog();
    }, [loadCatalog, siteId]);

    useEffect(() => {
        if (mode === 'create') {
            setEditingProductId(null);
            setEditorForm(createEmptyProductEditorForm());
            setProductImages([]);
            setProductAttributes([]);
            setProductVariants([]);
            setView('editor');
            return;
        }

        setView('catalog');
    }, [mode]);

    const resetEditor = useCallback(() => {
        setEditingProductId(null);
        setEditorForm(createEmptyProductEditorForm());
        setProductImages([]);
        setProductAttributes([]);
        setProductVariants([]);
        setView('catalog');
    }, []);

    const hydrateEditorFromProduct = useCallback((product: EcommerceProductDetail) => {
        const stockQuantity = Number(product.stock_quantity ?? 0);
        const allowBackorder = Boolean(product.allow_backorder);
        setEditingProductId(product.id);
        setEditorForm({
            categoryId: product.category_id !== null ? String(product.category_id) : '',
            name: product.name ?? '',
            slug: product.slug ?? '',
            shortDescription: product.short_description ?? '',
            description: product.description ?? '',
            status: product.status ?? 'draft',
            regularPrice: product.compare_at_price ?? product.price ?? '0',
            discountPrice: product.compare_at_price ? (product.price ?? '') : '',
            currency: product.currency ?? 'GEL',
            sku: product.sku ?? '',
            stockQuantity: String(stockQuantity),
            stockStatus: deriveStockStatus(stockQuantity, allowBackorder),
            stockTracking: Boolean(product.stock_tracking),
            allowBackorder,
            isDigital: Boolean(product.is_digital),
            weightGrams: product.weight_grams !== null && product.weight_grams !== undefined ? String(product.weight_grams) : '',
            seoTitle: product.seo_title ?? '',
            seoDescription: product.seo_description ?? '',
        });

        const nextImages = [...(product.images ?? [])]
            .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
            .map((image, index) => ({
                localId: createLocalId('img'),
                id: image.id ?? null,
                mediaId: image.media_id ?? null,
                path: image.path ?? '',
                assetUrl: image.asset_url ?? (image.path ? `/public/sites/${siteId}/assets/${image.path}` : ''),
                altText: image.alt_text ?? '',
                isPrimary: Boolean(image.is_primary),
                sortOrder: Number(image.sort_order ?? index),
            }));
        if (nextImages.length > 0 && !nextImages.some((image) => image.isPrimary)) {
            nextImages[0] = { ...nextImages[0], isPrimary: true };
        }
        setProductImages(nextImages);

        const rawAttributes = Array.isArray(product.attributes_json?.attributes)
            ? (product.attributes_json.attributes as Array<Record<string, unknown>>)
            : [];
        const nextAttributes = rawAttributes.map((attribute) => {
            const rawValues = Array.isArray(attribute.values) ? (attribute.values as Array<Record<string, unknown>>) : [];
            return {
                id: createLocalId('attr'),
                name: typeof attribute.name === 'string' ? attribute.name : '',
                type: (attribute.type === 'color' || attribute.type === 'size' ? attribute.type : 'text') as AttributeType,
                values: rawValues.map((value) => ({
                    id: createLocalId('attr_value'),
                    label: typeof value.label === 'string' ? value.label : '',
                    colorHex: typeof value.color_hex === 'string' ? value.color_hex : '#111111',
                })),
            } satisfies ProductAttributeDraft;
        }).filter((attribute) => attribute.name.trim() !== '');
        setProductAttributes(nextAttributes);

        const nextVariants = [...(product.variants ?? [])]
            .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
            .map((variant, index) => {
                const optionsSource = variant.options_json ?? {};
                const optionValues: Record<string, string> = {};
                Object.entries(optionsSource).forEach(([key, value]) => {
                    if (typeof value === 'string') {
                        optionValues[key] = value;
                    }
                });
                return createEmptyVariantDraft({
                    localId: createLocalId('variant'),
                    id: variant.id ?? null,
                    name: variant.name ?? '',
                    sku: variant.sku ?? '',
                    price: variant.price ?? product.price ?? '',
                    compareAtPrice: variant.compare_at_price ?? '',
                    stockQuantity: String(variant.stock_quantity ?? 0),
                    stockTracking: Boolean(variant.stock_tracking),
                    allowBackorder: Boolean(variant.allow_backorder),
                    isDefault: Boolean(variant.is_default),
                    position: Number(variant.position ?? index),
                    optionValues,
                    imagePath: extractVariantImagePath(optionsSource),
                });
            });
        setProductVariants(nextVariants);
        setView('editor');
    }, [siteId]);

    const loadProductDetail = useCallback(async (productId: number) => {
        setIsLoadingEditor(true);
        try {
            const response = await axios.get<EcommerceProductDetailResponse>(`/panel/sites/${siteId}/ecommerce/products/${productId}`);
            if (!response.data.product) {
                throw new Error('missing_product_payload');
            }
            hydrateEditorFromProduct(response.data.product);
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to load product details')));
        } finally {
            setIsLoadingEditor(false);
        }
    }, [hydrateEditorFromProduct, siteId, t]);

    const handleCreateNew = () => {
        setEditingProductId(null);
        setEditorForm(createEmptyProductEditorForm());
        setProductImages([]);
        setProductAttributes([]);
        setProductVariants([]);
        setView('editor');
    };

    const handleDeleteProduct = async (product: EcommerceProductListItem) => {
        if (!window.confirm(t('Delete this product?'))) {
            return;
        }

        setDeletingProductId(product.id);
        try {
            await axios.delete(`/panel/sites/${siteId}/ecommerce/products/${product.id}`);
            toast.success(t('Product deleted'));
            await loadCatalog();
            if (editingProductId === product.id) {
                resetEditor();
            }
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to delete product')));
        } finally {
            setDeletingProductId(null);
        }
    };

    const normalizeImagesForSave = useCallback(() => {
        const filtered = productImages.filter((image) => image.path.trim() !== '' || image.mediaId !== null);
        const withPrimary = filtered.map((image, index) => ({
            ...image,
            sortOrder: index,
            isPrimary: image.isPrimary,
        }));

        if (withPrimary.length > 0 && !withPrimary.some((image) => image.isPrimary)) {
            withPrimary[0] = { ...withPrimary[0], isPrimary: true };
        }

        return withPrimary;
    }, [productImages]);

    const applyStockStatusToForm = (status: StockStatus) => {
        setEditorForm((prev) => {
            const next = { ...prev, stockStatus: status };
            if (status === 'out_of_stock') {
                next.stockQuantity = '0';
                next.allowBackorder = false;
            }
            if (status === 'backorder') {
                next.allowBackorder = true;
            }
            if (status === 'in_stock' && prev.stockQuantity.trim() === '') {
                next.stockQuantity = '1';
            }

            return next;
        });
    };

    const generateVariantsFromAttributes = () => {
        const combinations = cartesianCombinations(productAttributes);
        if (combinations.length === 0) {
            setProductVariants([]);
            return;
        }

        const existingMap = new Map(
            productVariants.map((variant) => [variantCombinationKey(variant.optionValues), variant] as const)
        );

        const nextVariants = combinations.map((combination, index) => {
            const key = variantCombinationKey(combination);
            const existing = existingMap.get(key);

            return createEmptyVariantDraft({
                localId: existing?.localId ?? createLocalId('variant'),
                id: existing?.id ?? null,
                name: existing?.name || toVariantDisplayName(combination),
                sku: existing?.sku ?? '',
                price: existing?.price ?? editorForm.regularPrice,
                compareAtPrice: existing?.compareAtPrice ?? editorForm.discountPrice,
                stockQuantity: existing?.stockQuantity ?? editorForm.stockQuantity,
                stockTracking: existing?.stockTracking ?? editorForm.stockTracking,
                allowBackorder: existing?.allowBackorder ?? editorForm.allowBackorder,
                isDefault: existing?.isDefault ?? index === 0,
                position: index,
                optionValues: combination,
                imagePath: existing?.imagePath ?? '',
            });
        });

        if (nextVariants.length > 0 && !nextVariants.some((variant) => variant.isDefault)) {
            nextVariants[0] = { ...nextVariants[0], isDefault: true };
        }

        setProductVariants(nextVariants);
    };

    const buildSavePayload = (): Record<string, unknown> | null => {
        const name = editorForm.name.trim();
        const slug = slugify(editorForm.slug || editorForm.name);
        if (name === '' || slug === '') {
            toast.error(t('Product name and slug are required.'));
            return null;
        }

        const regularPrice = parseOptionalNonNegativeDecimal(editorForm.regularPrice);
        if (regularPrice === null) {
            toast.error(t('Regular price must be a non-negative number.'));
            return null;
        }

        const discountPrice = parseOptionalNonNegativeDecimal(editorForm.discountPrice);
        if (editorForm.discountPrice.trim() !== '' && discountPrice === null) {
            toast.error(t('Discount price must be a non-negative number.'));
            return null;
        }

        const stockQuantity = parseOptionalNonNegativeInteger(editorForm.stockQuantity);
        if (stockQuantity === null) {
            toast.error(t('Stock quantity must be a non-negative integer.'));
            return null;
        }

        const weightGrams = parseOptionalNonNegativeInteger(editorForm.weightGrams);
        if (editorForm.weightGrams.trim() !== '' && weightGrams === null) {
            toast.error(t('Weight must be a non-negative integer.'));
            return null;
        }

        const categoryId = parseOptionalNonNegativeInteger(editorForm.categoryId);
        if (editorForm.categoryId.trim() !== '' && categoryId === null) {
            toast.error(t('Selected category is invalid.'));
            return null;
        }

        const finalAllowBackorder = editorForm.stockStatus === 'backorder' ? true : editorForm.allowBackorder;
        const finalStockQuantity = editorForm.stockStatus === 'out_of_stock' ? 0 : stockQuantity;
        const finalPrice = discountPrice ?? regularPrice;
        const finalCompareAtPrice = discountPrice !== null ? regularPrice : null;

        const normalizedImages = normalizeImagesForSave().map((image, index) => ({
            id: image.id,
            media_id: image.mediaId,
            path: image.path.trim() || null,
            alt_text: image.altText.trim() || null,
            is_primary: image.isPrimary,
            sort_order: index,
        }));

        const normalizedVariants = productVariants
            .map((variant, index) => {
                const price = parseOptionalNonNegativeDecimal(variant.price);
                if (variant.price.trim() !== '' && price === null) {
                    throw new Error(t('Variant price must be a non-negative number.'));
                }
                const compareAt = parseOptionalNonNegativeDecimal(variant.compareAtPrice);
                if (variant.compareAtPrice.trim() !== '' && compareAt === null) {
                    throw new Error(t('Variant discount price must be a non-negative number.'));
                }
                const quantity = parseOptionalNonNegativeInteger(variant.stockQuantity);
                if (quantity === null) {
                    throw new Error(t('Variant stock quantity must be a non-negative integer.'));
                }

                const options = sanitizeVariantOptionsForSave(variant);
                const displayName = variant.name.trim() || toVariantDisplayName(variant.optionValues) || `${name} ${index + 1}`;

                return {
                    id: variant.id,
                    name: displayName,
                    sku: variant.sku.trim() || null,
                    options_json: options,
                    price,
                    compare_at_price: compareAt,
                    stock_tracking: variant.stockTracking,
                    stock_quantity: quantity,
                    allow_backorder: variant.allowBackorder,
                    is_default: variant.isDefault,
                    position: index,
                };
            });

        if (normalizedVariants.length > 0 && !normalizedVariants.some((variant) => variant.is_default)) {
            normalizedVariants[0] = { ...normalizedVariants[0], is_default: true };
        }

        return {
            category_id: categoryId,
            name,
            slug,
            short_description: editorForm.shortDescription.trim() || null,
            description: editorForm.description.trim() || null,
            status: editorForm.status,
            price: finalPrice,
            compare_at_price: finalCompareAtPrice,
            currency: editorForm.currency.trim().toUpperCase() || 'GEL',
            sku: editorForm.sku.trim() || null,
            stock_tracking: editorForm.stockTracking,
            stock_quantity: finalStockQuantity,
            allow_backorder: finalAllowBackorder,
            is_digital: editorForm.isDigital,
            weight_grams: weightGrams,
            seo_title: editorForm.seoTitle.trim() || null,
            seo_description: editorForm.seoDescription.trim() || null,
            attributes_json: buildAttributeStorage(productAttributes),
            images: normalizedImages,
            variants: normalizedVariants,
        };
    };

    const saveProduct = async (statusOverride?: ProductStatus) => {
        let payload: Record<string, unknown> | null = null;
        try {
            payload = buildSavePayload();
        } catch (error) {
            toast.error(error instanceof Error ? error.message : t('Failed to prepare product payload'));
            return;
        }

        if (!payload) {
            return;
        }

        if (statusOverride) {
            payload.status = statusOverride;
            setEditorForm((prev) => ({ ...prev, status: statusOverride }));
        }

        setIsSaving(true);
        try {
            if (editingProductId === null) {
                const response = await axios.post<ProductMutationResponse>(`/panel/sites/${siteId}/ecommerce/products`, payload);
                toast.success(response.data.message || t('Product created successfully'));
                await loadCatalog();
                const createdId = (response.data.product as { id?: number } | undefined)?.id;
                if (typeof createdId === 'number') {
                    await loadProductDetail(createdId);
                }
            } else {
                const response = await axios.put<ProductMutationResponse>(`/panel/sites/${siteId}/ecommerce/products/${editingProductId}`, payload);
                toast.success(response.data.message || t('Product updated successfully'));
                await loadCatalog();
                await loadProductDetail(editingProductId);
            }
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to save product')));
        } finally {
            setIsSaving(false);
        }
    };

    const handleProductSubmit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        await saveProduct();
    };

    const uploadMediaFiles = async (files: FileList | null, mode: 'featured' | 'gallery') => {
        if (!files || files.length === 0) {
            return;
        }

        setIsSaving(true);
        try {
            const uploaded: ProductImageDraft[] = [];
            for (const file of Array.from(files)) {
                const formData = new FormData();
                formData.append('file', file);
                const response = await axios.post<MediaUploadResponse>(`/panel/sites/${siteId}/media/upload`, formData);
                const media = response.data.media;
                uploaded.push({
                    localId: createLocalId('img'),
                    id: null,
                    mediaId: media.id,
                    path: media.path,
                    assetUrl: media.asset_url,
                    altText: '',
                    isPrimary: false,
                    sortOrder: 0,
                });
            }

            setProductImages((prev) => {
                let next = [...prev];
                if (mode === 'featured') {
                    const featured = uploaded[0];
                    if (!featured) {
                        return prev;
                    }
                    next = next.map((image) => ({ ...image, isPrimary: false }));
                    const existingPrimaryIndex = next.findIndex((image) => image.isPrimary);
                    if (existingPrimaryIndex >= 0) {
                        next[existingPrimaryIndex] = { ...featured, isPrimary: true };
                    } else {
                        next.unshift({ ...featured, isPrimary: true });
                    }
                } else {
                    next = [...next, ...uploaded];
                    if (!next.some((image) => image.isPrimary) && next[0]) {
                        next[0] = { ...next[0], isPrimary: true };
                    }
                }

                return next.map((image, index) => ({ ...image, sortOrder: index }));
            });

            toast.success(mode === 'featured' ? t('Featured image uploaded') : t('Gallery images uploaded'));
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to upload image')));
        } finally {
            setIsSaving(false);
            if (featuredUploadRef.current) {
                featuredUploadRef.current.value = '';
            }
            if (galleryUploadRef.current) {
                galleryUploadRef.current.value = '';
            }
        }
    };

    const moveImageToPrimary = (localId: string) => {
        setProductImages((prev) => prev.map((image) => ({
            ...image,
            isPrimary: image.localId === localId,
        })));
    };

    const removeImage = (localId: string) => {
        const target = productImages.find((image) => image.localId === localId) ?? null;
        setProductImages((prev) => {
            const next = prev.filter((image) => image.localId !== localId);
            if (next.length > 0 && !next.some((image) => image.isPrimary)) {
                next[0] = { ...next[0], isPrimary: true };
            }
            return next.map((image, index) => ({ ...image, sortOrder: index }));
        });
        setProductVariants((prev) => prev.map((variant) => (
            target && variant.imagePath === target.assetUrl ? { ...variant, imagePath: '' } : variant
        )));
    };

    const handleImageDragStart = (event: DragEvent<HTMLDivElement>, localId: string) => {
        setImageDragLocalId(localId);
        event.dataTransfer.effectAllowed = 'move';
    };

    const handleImageDrop = (targetLocalId: string) => {
        if (!imageDragLocalId || imageDragLocalId === targetLocalId) {
            setImageDragLocalId(null);
            return;
        }

        setProductImages((prev) => {
            const fromIndex = prev.findIndex((image) => image.localId === imageDragLocalId);
            const toIndex = prev.findIndex((image) => image.localId === targetLocalId);
            if (fromIndex === -1 || toIndex === -1) {
                return prev;
            }
            const next = [...prev];
            const [moved] = next.splice(fromIndex, 1);
            if (!moved) {
                return prev;
            }
            next.splice(toIndex, 0, moved);
            return next.map((image, index) => ({ ...image, sortOrder: index }));
        });

        setImageDragLocalId(null);
    };

    const featuredImage = useMemo(() => productImages.find((image) => image.isPrimary) ?? productImages[0] ?? null, [productImages]);

    const galleryImageOptions = useMemo(() => (
        productImages.map((image) => ({
            key: image.localId,
            label: image.altText.trim() || image.path.split('/').pop() || image.localId,
            preview: image.assetUrl,
        }))
    ), [productImages]);

    const duplicateCurrentProduct = async () => {
        let payload: Record<string, unknown> | null = null;
        try {
            payload = buildSavePayload();
        } catch (error) {
            toast.error(error instanceof Error ? error.message : t('Failed to prepare duplicate payload'));
            return;
        }
        if (!payload) {
            return;
        }

        const baseName = `${editorForm.name.trim() || t('Product')} Copy`;
        const baseSlug = slugify(`${editorForm.slug.trim() || editorForm.name.trim() || 'product'}-copy`);
        payload.name = baseName;
        payload.slug = baseSlug;
        payload.status = 'draft';
        payload.sku = editorForm.sku.trim() === '' ? null : `${editorForm.sku.trim()}-COPY`;
        payload.images = Array.isArray(payload.images)
            ? (payload.images as Array<Record<string, unknown>>).map((image) => {
                const next = { ...image };
                delete next.id;
                return next;
            })
            : [];
        payload.variants = Array.isArray(payload.variants)
            ? (payload.variants as Array<Record<string, unknown>>).map((variant, index) => {
                const next = { ...variant };
                delete next.id;
                next.is_default = index === 0;
                if (typeof next.sku === 'string' && next.sku.trim() !== '') {
                    next.sku = `${next.sku}-COPY`;
                }
                return next;
            })
            : [];

        setIsDuplicating(true);
        try {
            const response = await axios.post<ProductMutationResponse>(`/panel/sites/${siteId}/ecommerce/products`, payload);
            toast.success(response.data.message || t('Product duplicated'));
            await loadCatalog();
            const createdId = (response.data.product as { id?: number } | undefined)?.id;
            if (typeof createdId === 'number') {
                await loadProductDetail(createdId);
            }
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to duplicate product')));
        } finally {
            setIsDuplicating(false);
        }
    };

    const deleteCurrentProduct = async () => {
        if (editingProductId === null) {
            return;
        }
        const current = products.find((product) => product.id === editingProductId);
        if (!current) {
            return;
        }
        await handleDeleteProduct(current);
    };

    const previewUrl = editorForm.slug.trim() === ''
        ? null
        : `/public/sites/${siteId}/ecommerce/products/${slugify(editorForm.slug)}`;

    const filteredProducts = useMemo(() => {
        const normalizedQuery = searchQuery.trim().toLowerCase();

        return products.filter((product) => {
            if (statusFilter !== 'all' && product.status !== statusFilter) {
                return false;
            }

            if (categoryFilter !== 'all') {
                const categoryValue = product.category_id !== null ? String(product.category_id) : 'none';
                if (categoryValue !== categoryFilter) {
                    return false;
                }
            }

            if (normalizedQuery === '') {
                return true;
            }

            const haystack = [
                product.name,
                product.slug,
                product.sku ?? '',
                product.category_name ?? '',
            ].join(' ').toLowerCase();

            return haystack.includes(normalizedQuery);
        });
    }, [categoryFilter, products, searchQuery, statusFilter]);

    const filteredProductIds = useMemo(() => filteredProducts.map((product) => product.id), [filteredProducts]);
    const selectedProducts = useMemo(
        () => products.filter((product) => selectedProductIds.includes(product.id)),
        [products, selectedProductIds]
    );
    const selectedCount = selectedProductIds.length;
    const allFilteredSelected = filteredProductIds.length > 0 && filteredProductIds.every((id) => selectedProductIds.includes(id));
    const someFilteredSelected = filteredProductIds.some((id) => selectedProductIds.includes(id));

    const toggleProductSelection = (productId: number) => {
        setSelectedProductIds((prev) => (
            prev.includes(productId) ? prev.filter((id) => id !== productId) : [...prev, productId]
        ));
    };

    const toggleSelectAllFiltered = () => {
        setSelectedProductIds((prev) => {
            if (filteredProductIds.length === 0) {
                return prev;
            }

            if (allFilteredSelected) {
                return prev.filter((id) => !filteredProductIds.includes(id));
            }

            return Array.from(new Set([...prev, ...filteredProductIds]));
        });
    };

    const exportProductsCsv = (rows: EcommerceProductListItem[]) => {
        const header = [
            'name',
            'slug',
            'sku',
            'category_name',
            'category_slug',
            'status',
            'regular_price',
            'discount_price',
            'currency',
            'stock_quantity',
            'short_description',
            'description',
            'seo_title',
            'seo_description',
        ];

        const categoryMap = new Map(categories.map((category) => [category.id, category]));
        const csvRows = rows.map((product) => {
            const category = product.category_id !== null ? categoryMap.get(product.category_id) ?? null : null;
            const regularPrice = product.compare_at_price ?? product.price;
            const discountPrice = product.compare_at_price ? product.price : '';

            return [
                product.name,
                product.slug,
                product.sku ?? '',
                product.category_name ?? '',
                category?.slug ?? '',
                product.status,
                regularPrice ?? '',
                discountPrice,
                product.currency,
                product.stock_quantity,
                '',
                '',
                '',
                '',
            ];
        });

        const csvContent = [header, ...csvRows]
            .map((row) => row.map((cell) => csvEscapeCell(cell)).join(','))
            .join('\n');

        downloadTextFile(`products-export-${siteId}.csv`, csvContent, 'text/csv;charset=utf-8;');
    };

    const downloadSampleImportCsv = () => {
        const header = [
            'name',
            'slug',
            'sku',
            'category_slug',
            'status',
            'regular_price',
            'discount_price',
            'currency',
            'stock_quantity',
            'short_description',
            'description',
            'seo_title',
            'seo_description',
        ];
        const rows = [
            [
                'ქართული მაისური',
                'qartuli-maisuri',
                'SKU-TSHIRT-001',
                categories[0]?.slug ?? '',
                'active',
                '89.90',
                '69.90',
                'GEL',
                '12',
                'მოკლე აღწერა',
                '<p>სრული აღწერა <strong>HTML</strong>-ითაც შეიძლება.</p>',
                'ქართული მაისური',
                'სატესტო SEO აღწერა',
            ],
            [
                'სპორტული ფეხსაცმელი',
                'sportuli-fexsacmeli',
                'SKU-SHOE-001',
                categories[1]?.slug ?? '',
                'draft',
                '159.00',
                '',
                'GEL',
                '8',
                'სატესტო მოკლე აღწერა',
                '<p>სატესტო სრული აღწერა</p>',
                'სპორტული ფეხსაცმელი',
                'SEO აღწერა',
            ],
        ];

        const csvContent = [header, ...rows]
            .map((row) => row.map((cell) => csvEscapeCell(cell)).join(','))
            .join('\n');

        downloadTextFile('webu-products-import-sample.csv', csvContent, 'text/csv;charset=utf-8;');
    };

    const importProductsFromCsv = async (file: File) => {
        setIsImportingCsv(true);
        try {
            const text = await file.text();
            const rows = parseCsv(text);
            if (rows.length === 0) {
                toast.error(t('No data yet'));
                return;
            }

            const categoryBySlug = new Map(categories.map((category) => [category.slug.trim().toLowerCase(), category]));
            const categoryByName = new Map(categories.map((category) => [category.name.trim().toLowerCase(), category]));
            const productBySlug = new Map(products.map((product) => [product.slug.trim().toLowerCase(), product]));

            let created = 0;
            let updated = 0;
            let skipped = 0;
            const rowErrors: string[] = [];

            for (const [rowIndex, row] of rows.entries()) {
                const name = (row.name ?? '').trim();
                const slug = slugify(row.slug || name);
                if (!name || !slug) {
                    skipped += 1;
                    rowErrors.push(`${rowIndex + 2}: ${t('Title and slug are required.')}`);
                    continue;
                }

                const regularRaw = (row.regular_price ?? row.price ?? '').trim();
                const discountRaw = (row.discount_price ?? '').trim();
                const regularPrice = Number.parseFloat(regularRaw || '0');
                const discountPrice = discountRaw === '' ? null : Number.parseFloat(discountRaw);
                const stockQuantity = Number.parseInt((row.stock_quantity ?? '0').trim() || '0', 10);
                if (!Number.isFinite(regularPrice) || regularPrice < 0) {
                    skipped += 1;
                    rowErrors.push(`${rowIndex + 2}: ${t('Regular price must be a non-negative number.')}`);
                    continue;
                }
                if (discountRaw !== '' && (!Number.isFinite(discountPrice ?? NaN) || (discountPrice ?? 0) < 0)) {
                    skipped += 1;
                    rowErrors.push(`${rowIndex + 2}: ${t('Discount price must be a non-negative number.')}`);
                    continue;
                }
                if (!Number.isFinite(stockQuantity) || stockQuantity < 0) {
                    skipped += 1;
                    rowErrors.push(`${rowIndex + 2}: ${t('Stock quantity must be a non-negative integer.')}`);
                    continue;
                }

                const categorySlug = (row.category_slug ?? '').trim().toLowerCase();
                const categoryName = (row.category_name ?? '').trim().toLowerCase();
                const category = (categorySlug ? categoryBySlug.get(categorySlug) : undefined)
                    ?? (categoryName ? categoryByName.get(categoryName) : undefined)
                    ?? null;

                const status = normalizeImportedStatus(row.status);
                const allowBackorder = parseBooleanLike(row.allow_backorder, false);
                const stockTracking = parseBooleanLike(row.stock_tracking, true);
                const isDigital = parseBooleanLike(row.is_digital, false);

                const finalPrice = discountPrice ?? regularPrice;
                const compareAtPrice = discountPrice !== null ? regularPrice : null;

                const payload = {
                    category_id: category?.id ?? null,
                    name,
                    slug,
                    short_description: (row.short_description ?? '').trim() || null,
                    description: (row.description ?? '').trim() || null,
                    status,
                    price: finalPrice,
                    compare_at_price: compareAtPrice,
                    currency: (row.currency ?? 'GEL').trim().toUpperCase() || 'GEL',
                    sku: (row.sku ?? '').trim() || null,
                    stock_tracking: stockTracking,
                    stock_quantity: status === 'archived' ? stockQuantity : stockQuantity,
                    allow_backorder: allowBackorder,
                    is_digital: isDigital,
                    weight_grams: null,
                    seo_title: (row.seo_title ?? '').trim() || null,
                    seo_description: (row.seo_description ?? '').trim() || null,
                    attributes_json: {},
                    images: [],
                    variants: [],
                };

                const existing = productBySlug.get(slug.toLowerCase()) ?? null;
                try {
                    if (existing) {
                        await axios.put<ProductMutationResponse>(`/panel/sites/${siteId}/ecommerce/products/${existing.id}`, payload);
                        updated += 1;
                    } else {
                        const response = await axios.post<ProductMutationResponse>(`/panel/sites/${siteId}/ecommerce/products`, payload);
                        created += 1;
                        const createdProduct = response.data.product as EcommerceProductListItem | undefined;
                        if (createdProduct?.slug) {
                            productBySlug.set(createdProduct.slug.trim().toLowerCase(), createdProduct);
                        } else {
                            productBySlug.set(slug.toLowerCase(), { id: -1, ...payload, category_name: category?.name ?? null, category_id: category?.id ?? null, compare_at_price: compareAtPrice === null ? null : String(compareAtPrice), price: String(finalPrice), updated_at: null, currency: payload.currency } as unknown as EcommerceProductListItem);
                        }
                    }
                } catch (error) {
                    skipped += 1;
                    rowErrors.push(`${rowIndex + 2}: ${getApiErrorMessage(error, t('Failed to save product'))}`);
                }
            }

            await loadCatalog();
            setSelectedProductIds([]);

            if (created > 0 || updated > 0) {
                toast.success(`${t('Imported')}: ${created + updated} (${t('Create')}: ${created}, ${t('Update')}: ${updated})`);
            }
            if (rowErrors.length > 0) {
                toast.error(rowErrors.slice(0, 3).join(' | '));
            } else if (created === 0 && updated === 0) {
                toast.error(t('No data yet'));
            }
        } finally {
            setIsImportingCsv(false);
            if (importCsvRef.current) {
                importCsvRef.current.value = '';
            }
        }
    };

    const handleImportCsvChange = async (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) {
            return;
        }

        await importProductsFromCsv(file);
    };

    const handleBulkDeleteSelected = async () => {
        if (selectedProducts.length === 0) {
            return;
        }

        if (!window.confirm(t('Delete selected products?'))) {
            return;
        }

        setIsBulkDeleting(true);
        let deleted = 0;
        const errors: string[] = [];
        try {
            for (const product of selectedProducts) {
                setDeletingProductId(product.id);
                try {
                    await axios.delete(`/panel/sites/${siteId}/ecommerce/products/${product.id}`);
                    deleted += 1;
                } catch (error) {
                    errors.push(`${product.name}: ${getApiErrorMessage(error, t('Failed to delete product'))}`);
                }
            }
            await loadCatalog();
            setSelectedProductIds([]);
            if (deleted > 0) {
                toast.success(`${t('Delete Product')}: ${deleted}`);
            }
            if (errors.length > 0) {
                toast.error(errors.slice(0, 2).join(' | '));
            }
            if (editingProductId !== null && selectedProducts.some((product) => product.id === editingProductId)) {
                resetEditor();
            }
        } finally {
            setDeletingProductId(null);
            setIsBulkDeleting(false);
        }
    };

    return (
        <div className="w-full min-w-0 max-w-full space-y-4 overflow-x-hidden">
            {view === 'catalog' ? (
                <div className="w-full min-w-0 max-w-full space-y-4 overflow-x-hidden">
                    <Card className="min-w-0 gap-0 py-0">
                        <CardHeader className="min-w-0 border-b py-4">
                            <CardTitle className="text-base">{t('Filters')}</CardTitle>
                        </CardHeader>
                        <CardContent className="py-4">
                            <div className="min-w-0 grid gap-3 lg:grid-cols-[minmax(0,1.2fr)_220px_240px]">
                                <div className="space-y-2">
                                    <Label>{t('Search')}</Label>
                                    <div className="relative">
                                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            value={searchQuery}
                                            onChange={(event) => setSearchQuery(event.target.value)}
                                            placeholder={t('Search')}
                                            className="pl-9"
                                        />
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <Label>{t('Status')}</Label>
                                    <select
                                        value={statusFilter}
                                        onChange={(event) => setStatusFilter(event.target.value as 'all' | ProductStatus)}
                                        className="h-9 w-full rounded-md border bg-background px-3 text-sm shadow-xs"
                                    >
                                        <option value="all">{t('All statuses')}</option>
                                        <option value="draft">{t('Draft')}</option>
                                        <option value="active">{t('Published')}</option>
                                        <option value="archived">{t('Archived')}</option>
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label>{t('Category')}</Label>
                                    <select
                                        value={categoryFilter}
                                        onChange={(event) => setCategoryFilter(event.target.value)}
                                        className="h-9 w-full rounded-md border bg-background px-3 text-sm shadow-xs"
                                    >
                                        <option value="all">{t('All categories')}</option>
                                        <option value="none">{t('Uncategorized')}</option>
                                        {categories.map((category) => (
                                            <option key={category.id} value={String(category.id)}>
                                                {category.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="min-w-0 gap-0 py-0">
                        <CardHeader className="min-w-0 border-b py-4">
                            <div className="flex w-full min-w-0 flex-col gap-4">
                                <div className="flex min-w-0 flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <CardTitle>{t('Products')}</CardTitle>
                                    </div>
                                    <div className="flex min-w-0 flex-wrap items-center gap-2">
                                        <input
                                            ref={importCsvRef}
                                            type="file"
                                            accept=".csv,text/csv"
                                            className="hidden"
                                            onChange={(event) => void handleImportCsvChange(event)}
                                        />
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={downloadSampleImportCsv}
                                            disabled={isImportingCsv || isBulkDeleting}
                                        >
                                            <Download className="mr-2 h-4 w-4" />
                                            {t('Sample CSV')}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => importCsvRef.current?.click()}
                                            disabled={isImportingCsv || isBulkDeleting}
                                        >
                                            {isImportingCsv ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Upload className="mr-2 h-4 w-4" />}
                                            {t('Import CSV')}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => exportProductsCsv(filteredProducts)}
                                            disabled={filteredProducts.length === 0 || isImportingCsv || isBulkDeleting}
                                        >
                                            <Download className="mr-2 h-4 w-4" />
                                            {t('Export CSV')}
                                        </Button>
                                        <Button variant="outline" size="sm" onClick={() => void loadCatalog()} disabled={isLoading}>
                                            {isLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                                        </Button>
                                        <Button size="sm" onClick={handleCreateNew}>
                                            <Plus className="mr-2 h-4 w-4" />
                                            {t('Add Product')}
                                        </Button>
                                    </div>
                                </div>

                                <div className="min-w-0 grid gap-2 sm:grid-cols-3">
                                    <div className="rounded-lg border bg-muted/10 px-3 py-2">
                                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                            <Boxes className="h-3.5 w-3.5" />
                                            {t('Total Products')}
                                        </div>
                                        <p className="mt-1 text-lg font-semibold">{products.length}</p>
                                    </div>
                                    <div className="rounded-lg border bg-muted/10 px-3 py-2">
                                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                            <LayoutGrid className="h-3.5 w-3.5" />
                                            {t('Categories')}
                                        </div>
                                        <p className="mt-1 text-lg font-semibold">{categories.length}</p>
                                    </div>
                                    <div className="rounded-lg border bg-muted/10 px-3 py-2">
                                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                            <CircleDollarSign className="h-3.5 w-3.5" />
                                            {t('Published')}
                                        </div>
                                        <p className="mt-1 text-lg font-semibold">{products.filter((p) => p.status === 'active').length}</p>
                                    </div>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="min-w-0 py-4">
                            {selectedCount > 0 ? (
                                <div className="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-sm">
                                    <span>
                                        {t('Selected')}: <strong>{selectedCount}</strong>
                                    </span>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setSelectedProductIds([])}
                                            disabled={isBulkDeleting}
                                        >
                                            {t('Clear')}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            onClick={() => void handleBulkDeleteSelected()}
                                            disabled={isBulkDeleting}
                                        >
                                            {isBulkDeleting ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Trash2 className="mr-2 h-4 w-4" />}
                                            {t('Delete Selected')}
                                        </Button>
                                    </div>
                                </div>
                            ) : null}

                            {products.length === 0 ? (
                                <div className="py-12 text-center">
                                    <p className="text-sm text-muted-foreground mb-3">
                                        {isLoading ? t('Loading products...') : t('No products yet')}
                                    </p>
                                    {!isLoading && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={isSeedingDemo}
                                            onClick={async () => {
                                                setIsSeedingDemo(true);
                                                try {
                                                    await axios.post(`/panel/sites/${siteId}/ecommerce/seed-demo`);
                                                    toast.success(t('Demo products added. Your storefront preview will show sample products.'));
                                                    await loadCatalog();
                                                } catch (err) {
                                                    toast.error(getApiErrorMessage(err, t('Failed to load demo products')));
                                                } finally {
                                                    setIsSeedingDemo(false);
                                                }
                                            }}
                                        >
                                            {isSeedingDemo ? <><Loader2 className="mr-2 h-4 w-4 animate-spin" />{t('Loading…')}</> : t('Load demo products for preview')}
                                        </Button>
                                    )}
                                </div>
                            ) : filteredProducts.length === 0 ? (
                                <div className="py-12 text-center text-sm text-muted-foreground">
                                    {t('No products match your search/filter')}
                                </div>
                            ) : (
                                <div className="max-w-full overflow-hidden rounded-xl border bg-background">
                                    <div className="max-w-full overflow-x-auto">
                                        <table className="min-w-full text-sm">
                                            <thead className="bg-muted/30">
                                                <tr className="text-left">
                                                    <th className="w-10 px-3 py-2.5">
                                                        <input
                                                            type="checkbox"
                                                            checked={allFilteredSelected}
                                                            onChange={toggleSelectAllFiltered}
                                                            aria-label={t('Select All')}
                                                            data-indeterminate={(!allFilteredSelected && someFilteredSelected) ? 'true' : 'false'}
                                                        />
                                                    </th>
                                                    <th className="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t('Product')}</th>
                                                    <th className="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t('Category')}</th>
                                                    <th className="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t('Stock')}</th>
                                                    <th className="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t('SKU')}</th>
                                                    <th className="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t('Price')}</th>
                                                    <th className="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t('Status')}</th>
                                                    <th className="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t('Updated')}</th>
                                                    <th className="px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t('Actions')}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {filteredProducts.map((product) => (
                                                    <tr key={product.id} className="border-t align-top transition hover:bg-muted/20">
                                                        <td className="px-3 py-3">
                                                            <input
                                                                type="checkbox"
                                                                checked={selectedProductIds.includes(product.id)}
                                                                onChange={() => toggleProductSelection(product.id)}
                                                                aria-label={`${t('Select')} ${product.name}`}
                                                            />
                                                        </td>
                                                        <td className="px-3 py-3">
                                                            <div className="flex items-center gap-3">
                                                                <Avatar className="h-10 w-10 rounded-lg">
                                                                    <AvatarImage src={product.primary_image_url || undefined} alt={product.primary_image_alt || product.name} className="object-cover" />
                                                                    <AvatarFallback className="rounded-lg bg-primary/10 text-xs font-semibold text-primary">
                                                                        {productInitials(product.name)}
                                                                    </AvatarFallback>
                                                                </Avatar>
                                                                <div className="min-w-0">
                                                                    <p className="truncate font-medium text-foreground">{product.name}</p>
                                                                    <p className="truncate text-xs text-muted-foreground">/{product.slug}</p>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="px-3 py-3">
                                                            <div className="flex items-center gap-2">
                                                                <span className={`inline-flex h-8 w-8 items-center justify-center rounded-md ${categoryColorClass(product.category_name)}`}>
                                                                    <Tag className="h-4 w-4" />
                                                                </span>
                                                                <span className="text-sm">{product.category_name || t('Uncategorized')}</span>
                                                            </div>
                                                        </td>
                                                        <td className="px-3 py-3">
                                                            <div className="flex min-w-[130px] items-center gap-2">
                                                                <Switch checked={product.stock_quantity > 0} disabled aria-label={stockLabel(product.stock_quantity, t)} />
                                                                <div>
                                                                    <Badge variant={stockBadgeVariant(product.stock_quantity)} className="mb-1">
                                                                        {stockLabel(product.stock_quantity, t)}
                                                                    </Badge>
                                                                    <p className="text-xs text-muted-foreground">
                                                                        {t('QTY')}: {product.stock_quantity}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="px-3 py-3">
                                                            <span className="font-mono text-xs text-muted-foreground">{product.sku || '—'}</span>
                                                        </td>
                                                        <td className="px-3 py-3">
                                                            <div className="space-y-0.5">
                                                                <div className="font-medium">{formatCurrencyAmount(product.price, product.currency)}</div>
                                                                {product.compare_at_price ? (
                                                                    <div className="text-xs text-muted-foreground line-through">
                                                                        {formatCurrencyAmount(product.compare_at_price, product.currency)}
                                                                    </div>
                                                                ) : null}
                                                            </div>
                                                        </td>
                                                        <td className="px-3 py-3">
                                                            <Badge variant={statusBadgeVariant(product.status)}>
                                                                {productStatusLabel(product.status, t)}
                                                            </Badge>
                                                        </td>
                                                        <td className="px-3 py-3">
                                                            <span className="text-xs text-muted-foreground">{formatDate(product.updated_at)}</span>
                                                        </td>
                                                        <td className="px-3 py-3">
                                                            <div className="flex items-center justify-end gap-1">
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => void loadProductDetail(product.id)}
                                                                    title={t('Edit')}
                                                                    className="h-8 w-8 p-0"
                                                                >
                                                                    <Pencil className="h-4 w-4" />
                                                                </Button>
                                                                <DropdownMenu>
                                                                    <DropdownMenuTrigger asChild>
                                                                        <Button variant="ghost" size="sm" className="h-8 w-8 p-0" title={t('Actions')}>
                                                                            <MoreVertical className="h-4 w-4" />
                                                                        </Button>
                                                                    </DropdownMenuTrigger>
                                                                    <DropdownMenuContent align="end" className="w-44">
                                                                        <DropdownMenuItem onSelect={() => void loadProductDetail(product.id)}>
                                                                            <Pencil className="h-4 w-4" />
                                                                            {t('Edit')}
                                                                        </DropdownMenuItem>
                                                                        <DropdownMenuItem
                                                                            onSelect={() => {
                                                                                const url = `/public/sites/${siteId}/ecommerce/products/${slugify(product.slug)}`;
                                                                                window.open(url, '_blank', 'noopener,noreferrer');
                                                                            }}
                                                                        >
                                                                            <Eye className="h-4 w-4" />
                                                                            {t('Preview')}
                                                                        </DropdownMenuItem>
                                                                        <DropdownMenuSeparator />
                                                                        <DropdownMenuItem
                                                                            variant="destructive"
                                                                            disabled={deletingProductId === product.id}
                                                                            onSelect={() => void handleDeleteProduct(product)}
                                                                        >
                                                                            {deletingProductId === product.id ? (
                                                                                <Loader2 className="h-4 w-4 animate-spin" />
                                                                            ) : (
                                                                                <Trash2 className="h-4 w-4" />
                                                                            )}
                                                                            {t('Delete')}
                                                                        </DropdownMenuItem>
                                                                    </DropdownMenuContent>
                                                                </DropdownMenu>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    <div className="flex flex-wrap items-center justify-between gap-2 border-t bg-muted/10 px-4 py-3 text-xs text-muted-foreground">
                                        <div className="flex items-center gap-2">
                                            <Package className="h-3.5 w-3.5" />
                                            <span>{t('Showing')} {filteredProducts.length} / {products.length}</span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Badge variant="outline">{t('Filtered')}</Badge>
                                            <span>{filteredProducts.length}</span>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            ) : (
                <div className="min-w-0 max-w-full space-y-4 overflow-x-hidden">
                    <div className="rounded-xl border bg-card px-4 py-4 sm:px-5">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div className="flex items-start gap-2">
                                <Button variant="outline" size="sm" onClick={() => setView('catalog')}>
                                    <ArrowLeft className="mr-2 h-4 w-4" />
                                    {t('Products')}
                                </Button>
                                <div className="pt-0.5">
                                    <p className="text-base font-semibold">
                                        {editingProductId === null ? t('Add Product') : t('Edit Product')}
                                    </p>
                                </div>
                            </div>
                            <div className="ms-auto flex flex-wrap items-center justify-end gap-2">
                                <Button type="button" variant="outline" size="sm" onClick={() => setView('catalog')}>
                                    {t('Cancel')}
                                </Button>
                                <Button type="button" variant="outline" size="sm" onClick={() => void saveProduct('draft')} disabled={isSaving}>
                                    {isSaving ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <CheckCircle2 className="mr-2 h-4 w-4" />}
                                    {t('Draft')}
                                </Button>
                                <Button type="button" size="sm" onClick={() => void saveProduct('active')} disabled={isSaving}>
                                    {isSaving ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <CheckCircle2 className="mr-2 h-4 w-4" />}
                                    {t('Publish')}
                                </Button>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button type="button" variant="outline" size="sm" className="px-2.5">
                                            <MoreVertical className="h-4 w-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="w-44">
                                        <DropdownMenuItem
                                            onSelect={() => previewUrl && window.open(previewUrl, '_blank', 'noopener,noreferrer')}
                                            disabled={!previewUrl}
                                        >
                                            <Eye className="h-4 w-4" />
                                            {t('Preview')}
                                        </DropdownMenuItem>
                                        {editingProductId !== null ? (
                                            <DropdownMenuItem onSelect={() => void duplicateCurrentProduct()} disabled={isDuplicating}>
                                                {isDuplicating ? <Loader2 className="h-4 w-4 animate-spin" /> : <Copy className="h-4 w-4" />}
                                                {t('Duplicate')}
                                            </DropdownMenuItem>
                                        ) : null}
                                        {editingProductId !== null ? <DropdownMenuSeparator /> : null}
                                        {editingProductId !== null ? (
                                            <DropdownMenuItem
                                                variant="destructive"
                                                onSelect={() => void deleteCurrentProduct()}
                                                disabled={deletingProductId === editingProductId}
                                            >
                                                {deletingProductId === editingProductId ? <Loader2 className="h-4 w-4 animate-spin" /> : <Trash2 className="h-4 w-4" />}
                                                {t('Delete')}
                                            </DropdownMenuItem>
                                        ) : null}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </div>
                    {isLoadingEditor ? (
                        <Card>
                            <CardContent className="py-12 text-center">
                                <Loader2 className="mx-auto h-6 w-6 animate-spin text-muted-foreground" />
                                <p className="mt-3 text-sm text-muted-foreground">{t('Loading product editor...')}</p>
                            </CardContent>
                        </Card>
                    ) : (
                        <form className="space-y-4" onSubmit={handleProductSubmit}>
                            <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_380px]">
                                <div className="flex min-w-0 flex-col gap-4">
                                    <Card className="order-2 gap-0 py-0">
                                        <CardHeader className="border-b py-4">
                                            <CardTitle>{t('Product')}</CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-4 py-4">
                                            <div className="grid gap-3">
                                                <div className="space-y-2">
                                                    <Label>{t('Product Name')}</Label>
                                                    <Input
                                                        value={editorForm.name}
                                                        onChange={(event) => {
                                                            const value = event.target.value;
                                                            setEditorForm((prev) => ({
                                                                ...prev,
                                                                name: value,
                                                                slug: slugify(value),
                                                            }));
                                                        }}
                                                        required
                                                    />
                                                </div>
                                            </div>

                                            <div className="space-y-2">
                                                <Label>{t('Short Description')}</Label>
                                                <RichTextField
                                                    value={editorForm.shortDescription}
                                                    onChange={(nextValue) => setEditorForm((prev) => ({ ...prev, shortDescription: nextValue }))}
                                                    minHeightClassName="min-h-[140px]"
                                                    toolbarPreset="advanced"
                                                    showHtmlToggle
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>{t('Full Description')}</Label>
                                                <RichTextField
                                                    value={editorForm.description}
                                                    onChange={(nextValue) => setEditorForm((prev) => ({ ...prev, description: nextValue }))}
                                                    minHeightClassName="min-h-[320px]"
                                                    toolbarPreset="advanced"
                                                    showHtmlToggle
                                                />
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <Card className="order-1 gap-0 py-0">
                                        <CardHeader className="flex-row items-start justify-between gap-3 space-y-0">
                                            <div>
                                                <CardTitle>{t('Variants')}</CardTitle>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Button type="button" size="sm" variant="outline" onClick={() => setProductAttributes((prev) => [...prev, createEmptyAttributeDraft()])}>
                                                    <Plus className="mr-2 h-4 w-4" />
                                                    {t('Add Attribute')}
                                                </Button>
                                                <Button type="button" size="sm" onClick={generateVariantsFromAttributes}>
                                                    {t('Generate Variants')}
                                                </Button>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="space-y-4 py-4">
                                            {productAttributes.length === 0 ? (
                                                <div className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                                    {t('No attributes yet')}
                                                </div>
                                            ) : (
                                                <div className="space-y-4">
                                                    {productAttributes.map((attribute) => (
                                                        <div key={attribute.id} className="rounded-lg border p-3">
                                                            <div className="grid gap-3 md:grid-cols-[1fr_220px_auto]">
                                                                <div className="space-y-2">
                                                                    <Label>{t('Attribute Name')}</Label>
                                                                    <Input
                                                                        value={attribute.name}
                                                                        onChange={(event) => setProductAttributes((prev) => prev.map((entry) => (
                                                                            entry.id === attribute.id ? { ...entry, name: event.target.value } : entry
                                                                        )))}
                                                                    />
                                                                </div>
                                                                <div className="space-y-2">
                                                                    <Label>{t('Type')}</Label>
                                                                    <select
                                                                        value={attribute.type}
                                                                        onChange={(event) => setProductAttributes((prev) => prev.map((entry) => (
                                                                            entry.id === attribute.id ? { ...entry, type: event.target.value as AttributeType } : entry
                                                                        )))}
                                                                        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                                    >
                                                                        <option value="text">{t('Text')}</option>
                                                                        <option value="color">{t('Color')}</option>
                                                                        <option value="size">{t('Size')}</option>
                                                                    </select>
                                                                </div>
                                                                <div className="flex items-end">
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="text-destructive"
                                                                        onClick={() => setProductAttributes((prev) => prev.filter((entry) => entry.id !== attribute.id))}
                                                                    >
                                                                        <Trash2 className="h-4 w-4" />
                                                                    </Button>
                                                                </div>
                                                            </div>

                                                            <div className="mt-3 space-y-2">
                                                                <div className="flex items-center justify-between">
                                                                    <Label>{t('Values')}</Label>
                                                                    <Button
                                                                        type="button"
                                                                        variant="outline"
                                                                        size="sm"
                                                                        onClick={() => setProductAttributes((prev) => prev.map((entry) => (
                                                                            entry.id === attribute.id
                                                                                ? { ...entry, values: [...entry.values, createEmptyAttributeValue('')] }
                                                                                : entry
                                                                        )))}
                                                                    >
                                                                        <Plus className="mr-2 h-4 w-4" />
                                                                        {t('Add Value')}
                                                                    </Button>
                                                                </div>
                                                                <div className="space-y-2">
                                                                    {attribute.values.map((value) => (
                                                                        <div key={value.id} className="grid gap-2 md:grid-cols-[1fr_auto_auto] items-center">
                                                                            <Input
                                                                                value={value.label}
                                                                                onChange={(event) => setProductAttributes((prev) => prev.map((entry) => (
                                                                                    entry.id === attribute.id
                                                                                        ? {
                                                                                            ...entry,
                                                                                            values: entry.values.map((entryValue) => (
                                                                                                entryValue.id === value.id ? { ...entryValue, label: event.target.value } : entryValue
                                                                                            )),
                                                                                        }
                                                                                        : entry
                                                                                )))}
                                                                                placeholder={attribute.type === 'size' ? t('S / M / L or 40 / 41 / 42') : t('Value')}
                                                                            />
                                                                            {attribute.type === 'color' ? (
                                                                                <input
                                                                                    type="color"
                                                                                    className="h-9 w-12 rounded border bg-background p-1"
                                                                                    value={value.colorHex}
                                                                                    onChange={(event) => setProductAttributes((prev) => prev.map((entry) => (
                                                                                        entry.id === attribute.id
                                                                                            ? {
                                                                                                ...entry,
                                                                                                values: entry.values.map((entryValue) => (
                                                                                                    entryValue.id === value.id ? { ...entryValue, colorHex: event.target.value } : entryValue
                                                                                                )),
                                                                                            }
                                                                                            : entry
                                                                                    )))}
                                                                                />
                                                                            ) : (
                                                                                <div />
                                                                            )}
                                                                            <Button
                                                                                type="button"
                                                                                variant="ghost"
                                                                                size="sm"
                                                                                className="text-destructive"
                                                                                onClick={() => setProductAttributes((prev) => prev.map((entry) => (
                                                                                    entry.id === attribute.id
                                                                                        ? { ...entry, values: entry.values.filter((entryValue) => entryValue.id !== value.id) }
                                                                                        : entry
                                                                                )))}
                                                                            >
                                                                                <X className="h-4 w-4" />
                                                                            </Button>
                                                                        </div>
                                                                    ))}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}

                                            <div className="space-y-3">
                                                <div className="flex items-center justify-between">
                                                    <Label>{t('Generated Variants')}</Label>
                                                    {productVariants.length > 0 ? (
                                                        <p className="text-xs text-muted-foreground">{productVariants.length} {t('variants')}</p>
                                                    ) : null}
                                                </div>
                                                {productVariants.length === 0 ? (
                                                    <div className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                                        {t('No variants yet')}
                                                    </div>
                                                ) : (
                                                    <div className="space-y-3">
                                                        {productVariants.map((variant) => (
                                                            <div key={variant.localId} className="rounded-lg border p-3">
                                                                <div className="mb-3 flex items-center justify-between gap-2">
                                                                    <div>
                                                                        <p className="text-sm font-medium">
                                                                            {variant.name || toVariantDisplayName(variant.optionValues) || t('Variant')}
                                                                        </p>
                                                                        <p className="text-xs text-muted-foreground">
                                                                            {Object.entries(variant.optionValues)
                                                                                .filter(([key]) => !key.startsWith('__'))
                                                                                .map(([key, value]) => `${key}: ${value}`)
                                                                                .join(' · ') || t('No attribute values')}
                                                                        </p>
                                                                    </div>
                                                                    <label className="flex items-center gap-2 text-xs text-muted-foreground">
                                                                        <input
                                                                            type="radio"
                                                                            name="defaultVariant"
                                                                            checked={variant.isDefault}
                                                                            onChange={() => setProductVariants((prev) => prev.map((entry) => ({
                                                                                ...entry,
                                                                                isDefault: entry.localId === variant.localId,
                                                                            })))}
                                                                        />
                                                                        {t('Default')}
                                                                    </label>
                                                                </div>
                                                                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                                                    <div className="space-y-2">
                                                                        <Label>{t('Variant Name')}</Label>
                                                                        <Input
                                                                            value={variant.name}
                                                                            onChange={(event) => setProductVariants((prev) => prev.map((entry) => (
                                                                                entry.localId === variant.localId ? { ...entry, name: event.target.value } : entry
                                                                            )))}
                                                                        />
                                                                    </div>
                                                                    <div className="space-y-2">
                                                                        <Label>{t('SKU')}</Label>
                                                                        <Input
                                                                            value={variant.sku}
                                                                            onChange={(event) => setProductVariants((prev) => prev.map((entry) => (
                                                                                entry.localId === variant.localId ? { ...entry, sku: event.target.value } : entry
                                                                            )))}
                                                                        />
                                                                    </div>
                                                                    <div className="space-y-2">
                                                                        <Label>{t('Price')}</Label>
                                                                        <Input
                                                                            type="number"
                                                                            min={0}
                                                                            step="0.01"
                                                                            value={variant.price}
                                                                            onChange={(event) => setProductVariants((prev) => prev.map((entry) => (
                                                                                entry.localId === variant.localId ? { ...entry, price: event.target.value } : entry
                                                                            )))}
                                                                        />
                                                                    </div>
                                                                    <div className="space-y-2">
                                                                        <Label>{t('Discount Price')}</Label>
                                                                        <Input
                                                                            type="number"
                                                                            min={0}
                                                                            step="0.01"
                                                                            value={variant.compareAtPrice}
                                                                            onChange={(event) => setProductVariants((prev) => prev.map((entry) => (
                                                                                entry.localId === variant.localId ? { ...entry, compareAtPrice: event.target.value } : entry
                                                                            )))}
                                                                        />
                                                                    </div>
                                                                    <div className="space-y-2">
                                                                        <Label>{t('Stock Quantity')}</Label>
                                                                        <Input
                                                                            type="number"
                                                                            min={0}
                                                                            value={variant.stockQuantity}
                                                                            onChange={(event) => setProductVariants((prev) => prev.map((entry) => (
                                                                                entry.localId === variant.localId ? { ...entry, stockQuantity: event.target.value } : entry
                                                                            )))}
                                                                        />
                                                                    </div>
                                                                    <div className="space-y-2">
                                                                        <Label>{t('Variant Image')}</Label>
                                                                        <select
                                                                            value={variant.imagePath}
                                                                            onChange={(event) => setProductVariants((prev) => prev.map((entry) => (
                                                                                entry.localId === variant.localId ? { ...entry, imagePath: event.target.value } : entry
                                                                            )))}
                                                                            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                                        >
                                                                            <option value="">{t('No image')}</option>
                                                                            {galleryImageOptions.map((image) => (
                                                                                <option key={image.key} value={image.preview}>
                                                                                    {image.label}
                                                                                </option>
                                                                            ))}
                                                                        </select>
                                                                    </div>
                                                                    <label className="rounded-md border p-3 text-sm flex items-center gap-2">
                                                                        <input
                                                                            type="checkbox"
                                                                            checked={variant.stockTracking}
                                                                            onChange={(event) => setProductVariants((prev) => prev.map((entry) => (
                                                                                entry.localId === variant.localId ? { ...entry, stockTracking: event.target.checked } : entry
                                                                            )))}
                                                                        />
                                                                        {t('Track Stock')}
                                                                    </label>
                                                                    <label className="rounded-md border p-3 text-sm flex items-center gap-2">
                                                                        <input
                                                                            type="checkbox"
                                                                            checked={variant.allowBackorder}
                                                                            onChange={(event) => setProductVariants((prev) => prev.map((entry) => (
                                                                                entry.localId === variant.localId ? { ...entry, allowBackorder: event.target.checked } : entry
                                                                            )))}
                                                                        />
                                                                        {t('Allow Backorders')}
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>

                                <div className="space-y-4">
                                    <Card className="gap-0 py-0">
                                        <CardHeader className="border-b py-4">
                                            <CardTitle>{t('Price')}</CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-3 py-4">
                                            <div className="space-y-2">
                                                <Label>{t('Regular Price')}</Label>
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    step="0.01"
                                                    value={editorForm.regularPrice}
                                                    onChange={(event) => setEditorForm((prev) => ({ ...prev, regularPrice: event.target.value }))}
                                                    required
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>{t('Discount Price')}</Label>
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    step="0.01"
                                                    value={editorForm.discountPrice}
                                                    onChange={(event) => setEditorForm((prev) => ({ ...prev, discountPrice: event.target.value }))}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>{t('Currency')}</Label>
                                                <Input
                                                    maxLength={3}
                                                    value={editorForm.currency}
                                                    onChange={(event) => setEditorForm((prev) => ({ ...prev, currency: event.target.value.toUpperCase() }))}
                                                />
                                            </div>
                                            <div className="rounded-lg border bg-muted/10 px-3 py-2 text-xs text-muted-foreground">
                                                <div className="flex items-center justify-between gap-2">
                                                    <span>{t('Current Price')}</span>
                                                    <span className="font-medium text-foreground">
                                                        {formatCurrencyAmount(editorForm.discountPrice || editorForm.regularPrice || '0', editorForm.currency)}
                                                    </span>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <Card className="gap-0 py-0">
                                        <CardHeader className="border-b py-4">
                                            <CardTitle>{t('Details')}</CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-3 py-4">
                                            <div className="space-y-2">
                                                <Label>{t('Category')}</Label>
                                                <select
                                                    value={editorForm.categoryId}
                                                    onChange={(event) => setEditorForm((prev) => ({ ...prev, categoryId: event.target.value }))}
                                                    className="h-9 w-full rounded-md border bg-background px-3 text-sm"
                                                >
                                                    <option value="">{t('Uncategorized')}</option>
                                                    {categories.filter((category) => category.status === 'active').map((category) => (
                                                        <option key={category.id} value={String(category.id)}>
                                                            {category.name}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div className="space-y-2">
                                                <Label>{t('Status')}</Label>
                                                <select
                                                    value={editorForm.status}
                                                    onChange={(event) => setEditorForm((prev) => ({ ...prev, status: event.target.value as ProductStatus }))}
                                                    className="h-9 w-full rounded-md border bg-background px-3 text-sm"
                                                >
                                                    <option value="draft">{t('Draft')}</option>
                                                    <option value="active">{t('Published')}</option>
                                                    <option value="archived">{t('Archived')}</option>
                                                </select>
                                            </div>
                                            <div className="space-y-2">
                                                <Label>{t('SKU')}</Label>
                                                <Input
                                                    value={editorForm.sku}
                                                    onChange={(event) => setEditorForm((prev) => ({ ...prev, sku: event.target.value }))}
                                                    placeholder="SKU-001"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>{t('URL')}</Label>
                                                <Input
                                                    value={editorForm.slug}
                                                    onChange={(event) => setEditorForm((prev) => ({ ...prev, slug: slugify(event.target.value) }))}
                                                />
                                            </div>
                                            <div className="border-t pt-3 space-y-3">
                                                <div className="space-y-2">
                                                    <Label>{t('Meta Title')}</Label>
                                                    <Input
                                                        value={editorForm.seoTitle}
                                                        onChange={(event) => setEditorForm((prev) => ({ ...prev, seoTitle: event.target.value }))}
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>{t('Meta Description')}</Label>
                                                    <RichTextField
                                                        value={editorForm.seoDescription}
                                                        onChange={(nextValue) => setEditorForm((prev) => ({
                                                            ...prev,
                                                            seoDescription: stripHtmlToPlainText(nextValue),
                                                        }))}
                                                        minHeightClassName="min-h-[120px]"
                                                        toolbarPreset="basic"
                                                    />
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <Card className="gap-0 py-0">
                                        <CardHeader className="flex-row items-start justify-between gap-3 space-y-0">
                                            <div>
                                                <CardTitle>{t('Images')}</CardTitle>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <input
                                                    ref={featuredUploadRef}
                                                    type="file"
                                                    accept="image/*"
                                                    className="hidden"
                                                    onChange={(event) => void uploadMediaFiles(event.target.files, 'featured')}
                                                />
                                                <input
                                                    ref={galleryUploadRef}
                                                    type="file"
                                                    accept="image/*"
                                                    multiple
                                                    className="hidden"
                                                    onChange={(event) => void uploadMediaFiles(event.target.files, 'gallery')}
                                                />
                                                <Button type="button" size="sm" variant="outline" onClick={() => featuredUploadRef.current?.click()}>
                                                    <Upload className="mr-2 h-4 w-4" />
                                                    {t('Featured Image')}
                                                </Button>
                                                <Button type="button" size="sm" onClick={() => galleryUploadRef.current?.click()}>
                                                    <Plus className="mr-2 h-4 w-4" />
                                                    {t('Gallery')}
                                                </Button>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="space-y-4 py-4">
                                            <div className="space-y-2">
                                                <Label>{t('Featured Image')}</Label>
                                                {featuredImage ? (
                                                    <div className="relative overflow-hidden rounded-lg border bg-muted/20">
                                                        <img src={featuredImage.assetUrl} alt={featuredImage.altText || featuredImage.path} className="h-56 w-full object-cover" />
                                                        <div className="absolute inset-x-0 bottom-0 bg-black/60 p-2 text-xs text-white">
                                                            {featuredImage.path.split('/').pop()}
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                                        {t('No image')}
                                                    </div>
                                                )}
                                            </div>

                                            <div className="space-y-2">
                                                <Label>{t('Gallery Images')}</Label>
                                                {productImages.length === 0 ? (
                                                    <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                                        {t('No images')}
                                                    </div>
                                                ) : (
                                                    <div className="grid gap-3 sm:grid-cols-2">
                                                        {productImages.map((image) => (
                                                            <div
                                                                key={image.localId}
                                                                className="rounded-lg border bg-background"
                                                                draggable
                                                                onDragStart={(event) => handleImageDragStart(event, image.localId)}
                                                                onDragOver={(event) => event.preventDefault()}
                                                                onDrop={() => handleImageDrop(image.localId)}
                                                            >
                                                                <div className="relative">
                                                                    <img src={image.assetUrl} alt={image.altText || image.path} className="h-32 w-full rounded-t-lg object-cover" />
                                                                    <div className="absolute left-2 top-2 flex items-center gap-1">
                                                                        <span className="rounded bg-black/70 px-1.5 py-0.5 text-[11px] text-white">
                                                                            #{image.sortOrder + 1}
                                                                        </span>
                                                                        {image.isPrimary ? (
                                                                            <span className="rounded bg-primary px-1.5 py-0.5 text-[11px] text-primary-foreground">
                                                                                {t('Featured')}
                                                                            </span>
                                                                        ) : null}
                                                                    </div>
                                                                    <div className="absolute right-2 top-2 flex items-center gap-1">
                                                                        <button
                                                                            type="button"
                                                                            className="rounded bg-black/70 p-1 text-white"
                                                                            title={t('Drag to reorder')}
                                                                        >
                                                                            <GripVertical className="h-4 w-4" />
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            className="rounded bg-black/70 p-1 text-white"
                                                                            onClick={() => removeImage(image.localId)}
                                                                            title={t('Delete image')}
                                                                        >
                                                                            <Trash2 className="h-4 w-4" />
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                                <div className="space-y-2 p-3">
                                                                    <Button type="button" size="sm" variant="outline" className="w-full" onClick={() => moveImageToPrimary(image.localId)}>
                                                                        {t('Set as Featured')}
                                                                    </Button>
                                                                    <Input
                                                                        value={image.altText}
                                                                        onChange={(event) => setProductImages((prev) => prev.map((entry) => (
                                                                            entry.localId === image.localId ? { ...entry, altText: event.target.value } : entry
                                                                        )))}
                                                                    />
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <Card className="gap-0 py-0">
                                        <CardHeader className="border-b py-4">
                                            <CardTitle>{t('Stock')}</CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-3 py-4">
                                            <div className="space-y-2">
                                                <Label>{t('Stock Quantity')}</Label>
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    value={editorForm.stockQuantity}
                                                    onChange={(event) => setEditorForm((prev) => ({ ...prev, stockQuantity: event.target.value }))}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>{t('Stock Status')}</Label>
                                                <select
                                                    value={editorForm.stockStatus}
                                                    onChange={(event) => applyStockStatusToForm(event.target.value as StockStatus)}
                                                    className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                >
                                                    <option value="in_stock">{t('In Stock')}</option>
                                                    <option value="out_of_stock">{t('Out of Stock')}</option>
                                                    <option value="backorder">{t('Backorder')}</option>
                                                </select>
                                            </div>
                                            <div className="space-y-2">
                                                <Label>{t('Weight (grams)')}</Label>
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    value={editorForm.weightGrams}
                                                    onChange={(event) => setEditorForm((prev) => ({ ...prev, weightGrams: event.target.value }))}
                                                />
                                            </div>
                                            <label className="rounded-md border p-3 text-sm flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    checked={editorForm.stockTracking}
                                                    onChange={(event) => setEditorForm((prev) => ({ ...prev, stockTracking: event.target.checked }))}
                                                />
                                                {t('Track Stock')}
                                            </label>
                                            <label className="rounded-md border p-3 text-sm flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    checked={editorForm.allowBackorder}
                                                    onChange={(event) => {
                                                        const checked = event.target.checked;
                                                        setEditorForm((prev) => ({
                                                            ...prev,
                                                            allowBackorder: checked,
                                                            stockStatus: checked ? 'backorder' : deriveStockStatus(Number.parseInt(prev.stockQuantity || '0', 10) || 0, false),
                                                        }));
                                                    }}
                                                />
                                                {t('Allow Backorders')}
                                            </label>
                                            <label className="rounded-md border p-3 text-sm flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    checked={editorForm.isDigital}
                                                    onChange={(event) => setEditorForm((prev) => ({ ...prev, isDigital: event.target.checked }))}
                                                />
                                                {t('Digital Product')}
                                            </label>
                                        </CardContent>
                                    </Card>

                                </div>
                            </div>
                        </form>
                    )}
                </div>
            )}
        </div>
    );
}
