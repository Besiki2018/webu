import {
    Image as ImageIcon,
    Layers,
    LayoutGrid,
    Link2,
    MapPin,
    ShoppingCart,
    Type,
    Video,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export function resolveBuilderWidgetIcon(key: string, category: string): LucideIcon {
    const k = key.toLowerCase();
    const c = category.toLowerCase();

    if (k.includes('product') || k.includes('cart') || k.includes('checkout') || k.includes('shop') || c.includes('ecommerce')) {
        return ShoppingCart;
    }
    if (k.includes('video')) {
        return Video;
    }
    if (k.includes('spacer') || k.includes('divider')) {
        return LayoutGrid;
    }
    if (k.includes('map') || k.includes('location')) {
        return MapPin;
    }
    if (k.includes('link') || k.includes('cta') || k.includes('button')) {
        return Link2;
    }
    if (k.includes('image') || k.includes('gallery') || k.includes('logo')) {
        return ImageIcon;
    }
    if (k.includes('header') || k.includes('footer') || k.includes('grid') || k.includes('layout') || k.includes('menu') || c.includes('layout')) {
        return LayoutGrid;
    }
    if (k.includes('title') || k.includes('heading') || k.includes('hero') || k.includes('text')) {
        return Type;
    }

    return Layers;
}
