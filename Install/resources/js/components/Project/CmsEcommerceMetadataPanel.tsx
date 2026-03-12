import { useCallback, useEffect, useMemo, useState } from 'react';
import axios, { AxiosError } from 'axios';
import { toast } from 'sonner';
import { Loader2, Pencil, Plus, RefreshCw, Save, Trash2, X } from 'lucide-react';
import { useTranslation } from '@/contexts/LanguageContext';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type MetadataMode = 'attributes' | 'attributeValues' | 'variants';

interface CmsEcommerceMetadataPanelProps {
    siteId: string;
    mode: MetadataMode;
}

interface ApiErrorPayload {
    message?: string;
    error?: string;
    errors?: Record<string, string[]>;
}

interface ProductListItem {
    id: number;
    name: string;
    slug: string;
}

interface ProductListResponse {
    site_id: string;
    products: ProductListItem[];
}

interface ProductDetailVariant {
    id: number;
    name: string;
    sku: string | null;
    options_json: Record<string, unknown>;
    price: string | null;
    stock_quantity: number;
}

interface ProductDetail {
    id: number;
    name: string;
    slug: string;
    price: string;
    currency: string;
    attributes_json: Record<string, unknown>;
    variants: ProductDetailVariant[];
}

interface ProductDetailResponse {
    site_id: string;
    product: ProductDetail;
}

interface AttributeListItem {
    id: number;
    site_id: string;
    name: string;
    slug: string;
    type: string;
    status: string;
    sort_order: number;
    values_count?: number;
}

interface AttributeListResponse {
    site_id: string;
    attributes: AttributeListItem[];
}

interface AttributeValueAttributeRef {
    id: number;
    name: string;
    slug: string;
    type: string;
}

interface AttributeValueListItem {
    id: number;
    site_id: string;
    ecommerce_attribute_id: number;
    label: string;
    slug: string;
    color_hex: string | null;
    sort_order: number;
    attribute?: AttributeValueAttributeRef | null;
}

interface AttributeValueListResponse {
    site_id: string;
    values: AttributeValueListItem[];
}

interface AttributeRow {
    key: string;
    name: string;
    type: string;
    productsCount: number;
    valuesCount: number;
}

interface AttributeValueRow {
    key: string;
    attributeName: string;
    value: string;
    colorHex: string | null;
    productsCount: number;
}

interface VariantRow {
    key: string;
    productId: number;
    productName: string;
    productSlug: string;
    variantName: string;
    price: string | null;
    currency: string;
    stockQuantity: number;
    sku: string | null;
}

interface AttributeFormState {
    name: string;
    slug: string;
    type: string;
    status: string;
}

interface AttributeValueFormState {
    ecommerce_attribute_id: string;
    label: string;
    slug: string;
    color_hex: string;
}

const KA_LATIN_MAP: Record<string, string> = {
    ა: 'a', ბ: 'b', გ: 'g', დ: 'd', ე: 'e', ვ: 'v', ზ: 'z', თ: 't', ი: 'i', კ: 'k',
    ლ: 'l', მ: 'm', ნ: 'n', ო: 'o', პ: 'p', ჟ: 'zh', რ: 'r', ს: 's', ტ: 't', უ: 'u',
    ფ: 'ph', ქ: 'q', ღ: 'gh', ყ: 'y', შ: 'sh', ჩ: 'ch', ც: 'ts', ძ: 'dz', წ: 'ts',
    ჭ: 'ch', ხ: 'kh', ჯ: 'j', ჰ: 'h',
};

function transliterateToLatin(input: string): string {
    return Array.from(input).map((char) => KA_LATIN_MAP[char] ?? KA_LATIN_MAP[char.toLowerCase()] ?? char).join('');
}

function slugify(value: string): string {
    return transliterateToLatin(value)
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .replace(/-{2,}/g, '-');
}

function getApiErrorMessage(error: unknown, fallback: string): string {
    const axiosError = error as AxiosError<ApiErrorPayload>;
    const payload = axiosError.response?.data;

    if (payload?.error) return payload.error;
    if (payload?.message) return payload.message;

    const firstValidationError = payload?.errors ? Object.values(payload.errors).flat()[0] : null;
    return firstValidationError ?? fallback;
}

function formatCurrencyAmount(value: string | number | null | undefined, currency: string = 'GEL'): string {
    const numeric = typeof value === 'number' ? value : Number.parseFloat(String(value ?? '0'));
    if (!Number.isFinite(numeric)) {
        return `${value ?? '0'} ${currency}`;
    }
    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: (currency || 'GEL').toUpperCase(),
            maximumFractionDigits: 2,
        }).format(numeric);
    } catch {
        return `${numeric.toFixed(2)} ${currency}`;
    }
}

function emptyAttributeForm(): AttributeFormState {
    return {
        name: '',
        slug: '',
        type: 'text',
        status: 'active',
    };
}

function emptyAttributeValueForm(): AttributeValueFormState {
    return {
        ecommerce_attribute_id: '',
        label: '',
        slug: '',
        color_hex: '#000000',
    };
}

export function CmsEcommerceMetadataPanel({ siteId, mode }: CmsEcommerceMetadataPanelProps) {
    const { t } = useTranslation();
    const [isLoading, setIsLoading] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [details, setDetails] = useState<ProductDetail[]>([]);
    const [attributes, setAttributes] = useState<AttributeListItem[]>([]);
    const [attributeValues, setAttributeValues] = useState<AttributeValueListItem[]>([]);
    const [attributeForm, setAttributeForm] = useState<AttributeFormState>(emptyAttributeForm);
    const [attributeValueForm, setAttributeValueForm] = useState<AttributeValueFormState>(emptyAttributeValueForm);
    const [editingAttributeId, setEditingAttributeId] = useState<number | null>(null);
    const [editingAttributeValueId, setEditingAttributeValueId] = useState<number | null>(null);

    const loadData = useCallback(async () => {
        setIsLoading(true);
        try {
            const [attributesResponse, valuesResponse, productsResponse] = await Promise.all([
                axios.get<AttributeListResponse>(`/panel/sites/${siteId}/ecommerce/attributes`),
                axios.get<AttributeValueListResponse>(`/panel/sites/${siteId}/ecommerce/attribute-values`),
                axios.get<ProductListResponse>(`/panel/sites/${siteId}/ecommerce/products`),
            ]);

            setAttributes(attributesResponse.data.attributes ?? []);
            setAttributeValues(valuesResponse.data.values ?? []);

            const products = productsResponse.data.products ?? [];
            if (products.length === 0) {
                setDetails([]);
                return;
            }

            const detailResponses = await Promise.all(
                products.map((product) => axios.get<ProductDetailResponse>(`/panel/sites/${siteId}/ecommerce/products/${product.id}`))
            );
            setDetails(
                detailResponses
                    .map((response) => response.data.product)
                    .filter((product): product is ProductDetail => Boolean(product))
            );
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to load ecommerce metadata')));
        } finally {
            setIsLoading(false);
        }
    }, [siteId, t]);

    useEffect(() => {
        void loadData();
    }, [loadData]);

    const resetAttributeEditor = useCallback(() => {
        setEditingAttributeId(null);
        setAttributeForm(emptyAttributeForm());
    }, []);

    const resetAttributeValueEditor = useCallback(() => {
        setEditingAttributeValueId(null);
        setAttributeValueForm(emptyAttributeValueForm());
    }, []);

    const selectedAttributeForValue = useMemo(
        () => attributes.find((item) => String(item.id) === attributeValueForm.ecommerce_attribute_id) ?? null,
        [attributes, attributeValueForm.ecommerce_attribute_id]
    );

    const saveAttribute = useCallback(async () => {
        const payload = {
            name: attributeForm.name.trim(),
            slug: slugify(attributeForm.slug || attributeForm.name),
            type: attributeForm.type || 'text',
            status: attributeForm.status || 'active',
        };

        if (!payload.name || !payload.slug) {
            toast.error(t('Name and URL are required'));
            return;
        }

        setIsSaving(true);
        try {
            if (editingAttributeId) {
                await axios.put(`/panel/sites/${siteId}/ecommerce/attributes/${editingAttributeId}`, payload);
                toast.success(t('Attribute updated'));
            } else {
                await axios.post(`/panel/sites/${siteId}/ecommerce/attributes`, payload);
                toast.success(t('Attribute added'));
            }
            resetAttributeEditor();
            await loadData();
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to save attribute')));
        } finally {
            setIsSaving(false);
        }
    }, [attributeForm, editingAttributeId, loadData, resetAttributeEditor, siteId, t]);

    const saveAttributeValue = useCallback(async () => {
        const payload = {
            ecommerce_attribute_id: Number(attributeValueForm.ecommerce_attribute_id),
            label: attributeValueForm.label.trim(),
            slug: slugify(attributeValueForm.slug || attributeValueForm.label),
            color_hex: selectedAttributeForValue?.type === 'color' ? (attributeValueForm.color_hex || null) : null,
        };

        if (!payload.ecommerce_attribute_id || !payload.label || !payload.slug) {
            toast.error(t('Attribute, value and URL are required'));
            return;
        }

        setIsSaving(true);
        try {
            if (editingAttributeValueId) {
                await axios.put(`/panel/sites/${siteId}/ecommerce/attribute-values/${editingAttributeValueId}`, payload);
                toast.success(t('Attribute value updated'));
            } else {
                await axios.post(`/panel/sites/${siteId}/ecommerce/attribute-values`, payload);
                toast.success(t('Attribute value added'));
            }
            resetAttributeValueEditor();
            await loadData();
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to save attribute value')));
        } finally {
            setIsSaving(false);
        }
    }, [
        attributeValueForm,
        editingAttributeValueId,
        loadData,
        resetAttributeValueEditor,
        selectedAttributeForValue?.type,
        siteId,
        t,
    ]);

    const startAttributeEdit = (attribute: AttributeListItem) => {
        setEditingAttributeId(attribute.id);
        setAttributeForm({
            name: attribute.name,
            slug: attribute.slug,
            type: attribute.type || 'text',
            status: attribute.status || 'active',
        });
    };

    const startAttributeValueEdit = (value: AttributeValueListItem) => {
        setEditingAttributeValueId(value.id);
        setAttributeValueForm({
            ecommerce_attribute_id: String(value.ecommerce_attribute_id),
            label: value.label,
            slug: value.slug,
            color_hex: value.color_hex || '#000000',
        });
    };

    const deleteAttribute = async (attribute: AttributeListItem) => {
        if (!window.confirm(t('Delete attribute and its values?'))) {
            return;
        }
        try {
            await axios.delete(`/panel/sites/${siteId}/ecommerce/attributes/${attribute.id}`);
            if (editingAttributeId === attribute.id) {
                resetAttributeEditor();
            }
            if (attributeValueForm.ecommerce_attribute_id === String(attribute.id)) {
                resetAttributeValueEditor();
            }
            toast.success(t('Attribute deleted'));
            await loadData();
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to delete attribute')));
        }
    };

    const deleteAttributeValue = async (value: AttributeValueListItem) => {
        if (!window.confirm(t('Delete attribute value?'))) {
            return;
        }
        try {
            await axios.delete(`/panel/sites/${siteId}/ecommerce/attribute-values/${value.id}`);
            if (editingAttributeValueId === value.id) {
                resetAttributeValueEditor();
            }
            toast.success(t('Attribute value deleted'));
            await loadData();
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to delete attribute value')));
        }
    };

    const attributeRows = useMemo<AttributeRow[]>(() => {
        const index = new Map<string, AttributeRow>();

        details.forEach((product) => {
            const attrs = Array.isArray(product.attributes_json?.attributes)
                ? (product.attributes_json.attributes as Array<Record<string, unknown>>)
                : [];

            attrs.forEach((attribute) => {
                const name = typeof attribute.name === 'string' ? attribute.name.trim() : '';
                if (!name) return;

                const type = typeof attribute.type === 'string' ? attribute.type : 'text';
                const values = Array.isArray(attribute.values) ? attribute.values : [];
                const key = `${name.toLowerCase()}|${type}`;
                const existing = index.get(key);
                if (existing) {
                    existing.productsCount += 1;
                    existing.valuesCount = Math.max(existing.valuesCount, values.length);
                    return;
                }

                index.set(key, {
                    key,
                    name,
                    type,
                    productsCount: 1,
                    valuesCount: values.length,
                });
            });
        });

        return Array.from(index.values()).sort((a, b) => a.name.localeCompare(b.name));
    }, [details]);

    const attributeValueRows = useMemo<AttributeValueRow[]>(() => {
        const index = new Map<string, AttributeValueRow>();

        details.forEach((product) => {
            const attrs = Array.isArray(product.attributes_json?.attributes)
                ? (product.attributes_json.attributes as Array<Record<string, unknown>>)
                : [];

            attrs.forEach((attribute) => {
                const attributeName = typeof attribute.name === 'string' ? attribute.name.trim() : '';
                if (!attributeName) return;
                const values = Array.isArray(attribute.values) ? (attribute.values as Array<Record<string, unknown>>) : [];

                values.forEach((value) => {
                    const label = typeof value.label === 'string' ? value.label.trim() : '';
                    if (!label) return;
                    const colorHex = typeof value.color_hex === 'string' ? value.color_hex : null;
                    const key = `${attributeName.toLowerCase()}|${label.toLowerCase()}`;
                    const existing = index.get(key);
                    if (existing) {
                        existing.productsCount += 1;
                        if (!existing.colorHex && colorHex) existing.colorHex = colorHex;
                        return;
                    }
                    index.set(key, {
                        key,
                        attributeName,
                        value: label,
                        colorHex,
                        productsCount: 1,
                    });
                });
            });
        });

        return Array.from(index.values()).sort((a, b) => (
            a.attributeName === b.attributeName ? a.value.localeCompare(b.value) : a.attributeName.localeCompare(b.attributeName)
        ));
    }, [details]);

    const variantRows = useMemo<VariantRow[]>(() => {
        return details
            .flatMap((product) =>
                (product.variants ?? []).map((variant) => ({
                    key: `${product.id}-${variant.id}`,
                    productId: product.id,
                    productName: product.name,
                    productSlug: product.slug,
                    variantName:
                        variant.name ||
                        Object.values(variant.options_json ?? {})
                            .filter((v): v is string => typeof v === 'string')
                            .join(' / ') ||
                        'Variant',
                    price: variant.price ?? product.price,
                    currency: product.currency,
                    stockQuantity: Number(variant.stock_quantity ?? 0),
                    sku: variant.sku ?? null,
                }))
            )
            .sort((a, b) => a.productName.localeCompare(b.productName) || a.variantName.localeCompare(b.variantName));
    }, [details]);

    const attributeUsageByKey = useMemo(() => new Map(attributeRows.map((row) => [row.key, row])), [attributeRows]);
    const attributeValueUsageByKey = useMemo(() => new Map(attributeValueRows.map((row) => [row.key, row])), [attributeValueRows]);

    const mergedAttributeRows = useMemo(() => {
        if (attributes.length === 0) {
            return attributeRows.map((row) => ({
                id: null as number | null,
                name: row.name,
                slug: '',
                type: row.type,
                status: 'active',
                valuesCount: row.valuesCount,
                productsCount: row.productsCount,
            }));
        }

        return attributes.map((attribute) => {
            const usage = attributeUsageByKey.get(`${attribute.name.toLowerCase()}|${attribute.type}`);
            return {
                id: attribute.id,
                name: attribute.name,
                slug: attribute.slug,
                type: attribute.type,
                status: attribute.status,
                valuesCount: Math.max(Number(attribute.values_count ?? 0), usage?.valuesCount ?? 0),
                productsCount: usage?.productsCount ?? 0,
            };
        });
    }, [attributeRows, attributeUsageByKey, attributes]);

    const mergedAttributeValueRows = useMemo(() => {
        if (attributeValues.length === 0) {
            return attributeValueRows.map((row) => ({
                id: null as number | null,
                attributeId: null as number | null,
                attributeName: row.attributeName,
                attributeType: 'text',
                value: row.value,
                slug: '',
                colorHex: row.colorHex,
                productsCount: row.productsCount,
            }));
        }

        return attributeValues.map((value) => {
            const attributeName = value.attribute?.name ?? '';
            const usage = attributeValueUsageByKey.get(`${attributeName.toLowerCase()}|${value.label.toLowerCase()}`);

            return {
                id: value.id,
                attributeId: value.ecommerce_attribute_id,
                attributeName,
                attributeType: value.attribute?.type ?? 'text',
                value: value.label,
                slug: value.slug,
                colorHex: value.color_hex,
                productsCount: usage?.productsCount ?? 0,
            };
        });
    }, [attributeValueRows, attributeValueUsageByKey, attributeValues]);

    const title = mode === 'attributes' ? t('Attributes') : mode === 'attributeValues' ? t('Attribute Values') : t('Variants');
    const description = mode === 'attributes'
        ? t('Create and manage reusable product attributes')
        : mode === 'attributeValues'
            ? t('Create values for attributes like Color and Size')
            : t('Variant rows are loaded from product variants in database');

    const hasData = mode === 'attributes'
        ? mergedAttributeRows.length > 0
        : mode === 'attributeValues'
            ? mergedAttributeValueRows.length > 0
            : variantRows.length > 0;

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between gap-3 space-y-0">
                <div>
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </div>
                <Button type="button" variant="outline" size="sm" onClick={() => void loadData()} disabled={isLoading}>
                    {isLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                </Button>
            </CardHeader>
            <CardContent className="space-y-4">
                {mode === 'attributes' ? (
                    <div className="rounded-lg border p-4 space-y-3">
                        <div className="flex items-center justify-between gap-2">
                            <div>
                                <h4 className="text-sm font-semibold">{editingAttributeId ? t('Edit Attribute') : t('Add Attribute')}</h4>
                                <p className="text-xs text-muted-foreground">{t('Create reusable attributes for products and variants')}</p>
                            </div>
                            {editingAttributeId ? (
                                <Button type="button" variant="ghost" size="sm" onClick={resetAttributeEditor}>
                                    <X className="h-4 w-4 mr-1" />
                                    {t('Cancel')}
                                </Button>
                            ) : null}
                        </div>
                        <div className="grid gap-3 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label>{t('Name')}</Label>
                                <Input
                                    value={attributeForm.name}
                                    onChange={(event) => {
                                        const name = event.target.value;
                                        setAttributeForm((prev) => ({
                                            ...prev,
                                            name,
                                            slug: prev.slug === '' || prev.slug === slugify(prev.name) ? slugify(name) : prev.slug,
                                        }));
                                    }}
                                    placeholder={t('Color')}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>{t('URL')}</Label>
                                <Input
                                    value={attributeForm.slug}
                                    onChange={(event) => setAttributeForm((prev) => ({ ...prev, slug: slugify(event.target.value) }))}
                                    placeholder="color"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>{t('Type')}</Label>
                                <select
                                    value={attributeForm.type}
                                    onChange={(event) => setAttributeForm((prev) => ({ ...prev, type: event.target.value }))}
                                    className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                >
                                    <option value="text">{t('Text')}</option>
                                    <option value="color">{t('Color')}</option>
                                    <option value="size">{t('Size')}</option>
                                    <option value="number">{t('Number')}</option>
                                </select>
                            </div>
                            <div className="space-y-2">
                                <Label>{t('Status')}</Label>
                                <select
                                    value={attributeForm.status}
                                    onChange={(event) => setAttributeForm((prev) => ({ ...prev, status: event.target.value }))}
                                    className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                >
                                    <option value="active">{t('Active')}</option>
                                    <option value="inactive">{t('Inactive')}</option>
                                </select>
                            </div>
                        </div>
                        <div className="flex justify-end">
                            <Button type="button" onClick={() => void saveAttribute()} disabled={isSaving}>
                                {isSaving ? <Loader2 className="h-4 w-4 animate-spin mr-1.5" /> : editingAttributeId ? <Save className="h-4 w-4 mr-1.5" /> : <Plus className="h-4 w-4 mr-1.5" />}
                                {editingAttributeId ? t('Save') : t('Add')}
                            </Button>
                        </div>
                    </div>
                ) : null}

                {mode === 'attributeValues' ? (
                    <div className="rounded-lg border p-4 space-y-3">
                        <div className="flex items-center justify-between gap-2">
                            <div>
                                <h4 className="text-sm font-semibold">{editingAttributeValueId ? t('Edit Attribute Value') : t('Add Attribute Value')}</h4>
                                <p className="text-xs text-muted-foreground">{t('Select attribute first, then add value')}</p>
                            </div>
                            {editingAttributeValueId ? (
                                <Button type="button" variant="ghost" size="sm" onClick={resetAttributeValueEditor}>
                                    <X className="h-4 w-4 mr-1" />
                                    {t('Cancel')}
                                </Button>
                            ) : null}
                        </div>
                        <div className="grid gap-3 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label>{t('Attribute')}</Label>
                                <select
                                    value={attributeValueForm.ecommerce_attribute_id}
                                    onChange={(event) => setAttributeValueForm((prev) => ({ ...prev, ecommerce_attribute_id: event.target.value }))}
                                    className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">{t('Select Attribute')}</option>
                                    {attributes.map((attribute) => (
                                        <option key={attribute.id} value={attribute.id}>
                                            {attribute.name} ({attribute.type})
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="space-y-2">
                                <Label>{t('Value')}</Label>
                                <Input
                                    value={attributeValueForm.label}
                                    onChange={(event) => {
                                        const label = event.target.value;
                                        setAttributeValueForm((prev) => ({
                                            ...prev,
                                            label,
                                            slug: prev.slug === '' || prev.slug === slugify(prev.label) ? slugify(label) : prev.slug,
                                        }));
                                    }}
                                    placeholder={t('Red')}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>{t('URL')}</Label>
                                <Input
                                    value={attributeValueForm.slug}
                                    onChange={(event) => setAttributeValueForm((prev) => ({ ...prev, slug: slugify(event.target.value) }))}
                                    placeholder="red"
                                />
                            </div>
                            {selectedAttributeForValue?.type === 'color' ? (
                                <div className="space-y-2">
                                    <Label>{t('Color')}</Label>
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="color"
                                            value={attributeValueForm.color_hex || '#000000'}
                                            onChange={(event) => setAttributeValueForm((prev) => ({ ...prev, color_hex: event.target.value }))}
                                            className="h-10 w-14 rounded border bg-background p-1"
                                        />
                                        <Input
                                            value={attributeValueForm.color_hex}
                                            onChange={(event) => setAttributeValueForm((prev) => ({ ...prev, color_hex: event.target.value }))}
                                            placeholder="#ff0000"
                                        />
                                    </div>
                                </div>
                            ) : null}
                        </div>
                        <div className="flex justify-end">
                            <Button type="button" onClick={() => void saveAttributeValue()} disabled={isSaving || attributes.length === 0}>
                                {isSaving ? <Loader2 className="h-4 w-4 animate-spin mr-1.5" /> : editingAttributeValueId ? <Save className="h-4 w-4 mr-1.5" /> : <Plus className="h-4 w-4 mr-1.5" />}
                                {editingAttributeValueId ? t('Save') : t('Add')}
                            </Button>
                        </div>
                        {attributes.length === 0 ? (
                            <div className="rounded-md border bg-muted/20 px-3 py-2 text-xs text-muted-foreground">
                                {t('Add an attribute first from the Attributes tab')}
                            </div>
                        ) : null}
                    </div>
                ) : null}

                {isLoading ? (
                    <div className="py-10 text-center text-sm text-muted-foreground">{t('Loading...')}</div>
                ) : !hasData ? (
                    <div className="py-10 text-center text-sm text-muted-foreground">{t('No data yet')}</div>
                ) : mode === 'attributes' ? (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[820px] text-sm">
                            <thead className="bg-muted/40 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">{t('Attribute')}</th>
                                    <th className="px-4 py-3 font-medium">{t('URL')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Type')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Values')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Used in Products')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Status')}</th>
                                    <th className="px-4 py-3 font-medium text-right">{t('Actions')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {mergedAttributeRows.map((row, index) => (
                                    <tr key={`${row.id ?? 'summary'}-${row.name}-${index}`} className="border-t">
                                        <td className="px-4 py-3 font-medium">{row.name}</td>
                                        <td className="px-4 py-3 text-xs text-muted-foreground">{row.slug ? `/${row.slug}` : '—'}</td>
                                        <td className="px-4 py-3"><Badge variant="outline">{row.type}</Badge></td>
                                        <td className="px-4 py-3">{row.valuesCount}</td>
                                        <td className="px-4 py-3">{row.productsCount}</td>
                                        <td className="px-4 py-3">
                                            <Badge variant={row.status === 'active' ? 'default' : 'outline'}>
                                                {row.status === 'active' ? t('Active') : t('Inactive')}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                {row.id ? (
                                                    <>
                                                        <Button type="button" size="icon" variant="ghost" onClick={() => startAttributeEdit(attributes.find((item) => item.id === row.id)!)} title={t('Edit')}>
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button type="button" size="icon" variant="ghost" className="text-destructive" onClick={() => void deleteAttribute(attributes.find((item) => item.id === row.id)!)} title={t('Delete')}>
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">{t('From products')}</span>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : mode === 'attributeValues' ? (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[920px] text-sm">
                            <thead className="bg-muted/40 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">{t('Attribute')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Value')}</th>
                                    <th className="px-4 py-3 font-medium">{t('URL')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Color')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Used in Products')}</th>
                                    <th className="px-4 py-3 font-medium text-right">{t('Actions')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {mergedAttributeValueRows.map((row, index) => (
                                    <tr key={`${row.id ?? 'summary'}-${row.attributeName}-${row.value}-${index}`} className="border-t">
                                        <td className="px-4 py-3">
                                            <div className="font-medium">{row.attributeName || '—'}</div>
                                            {row.attributeType ? <div className="text-xs text-muted-foreground">{row.attributeType}</div> : null}
                                        </td>
                                        <td className="px-4 py-3 font-medium">{row.value}</td>
                                        <td className="px-4 py-3 text-xs text-muted-foreground">{row.slug ? `/${row.slug}` : '—'}</td>
                                        <td className="px-4 py-3">
                                            {row.colorHex ? (
                                                <span className="inline-flex items-center gap-2">
                                                    <span className="inline-block h-4 w-4 rounded border" style={{ backgroundColor: row.colorHex }} />
                                                    <span className="text-xs text-muted-foreground">{row.colorHex}</span>
                                                </span>
                                            ) : '—'}
                                        </td>
                                        <td className="px-4 py-3">{row.productsCount}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                {row.id ? (
                                                    <>
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            onClick={() => {
                                                                const item = attributeValues.find((v) => v.id === row.id);
                                                                if (item) startAttributeValueEdit(item);
                                                            }}
                                                            title={t('Edit')}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            className="text-destructive"
                                                            onClick={() => {
                                                                const item = attributeValues.find((v) => v.id === row.id);
                                                                if (item) void deleteAttributeValue(item);
                                                            }}
                                                            title={t('Delete')}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">{t('From products')}</span>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[900px] text-sm">
                            <thead className="bg-muted/40 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">{t('Product')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Variant')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Price')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Stock')}</th>
                                    <th className="px-4 py-3 font-medium">{t('SKU')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {variantRows.map((row) => (
                                    <tr key={row.key} className="border-t">
                                        <td className="px-4 py-3">
                                            <div className="font-medium">{row.productName}</div>
                                            <div className="text-xs text-muted-foreground">/{row.productSlug}</div>
                                        </td>
                                        <td className="px-4 py-3">{row.variantName}</td>
                                        <td className="px-4 py-3">{formatCurrencyAmount(row.price, row.currency)}</td>
                                        <td className="px-4 py-3">{row.stockQuantity}</td>
                                        <td className="px-4 py-3">{row.sku || '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

