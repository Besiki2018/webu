/**
 * AI Layout — map component key to React component and default props.
 * Used by LayoutRenderer. Only Webu design-system components; no raw HTML.
 */

import type { ComponentType } from 'react';
import { Header } from '@/ecommerce/components/Header';
import { HeroBanner } from '@/ecommerce/components/HeroBanner';
import { CategoryGrid } from '@/ecommerce/components/CategoryGrid';
import { ProductGrid } from '@/ecommerce/components/ProductGrid';
import { Footer } from '@/ecommerce/components/Footer';
import { Cart } from '@/ecommerce/components/Cart';
import { PlaceholderSection } from '@/ecommerce/components/PlaceholderSection';
import type { SectionInjectedProps } from '@/ecommerce/components/types';
import type { LayoutSection } from './types';

export interface RegistryEntry {
  Component: ComponentType<Record<string, unknown> & SectionInjectedProps>;
  /** Prop name for variant (e.g. "variant") */
  variantProp?: string;
}

const BASE_PROPS: SectionInjectedProps = { basePath: '' };

const registry: Record<string, RegistryEntry> = {
  hero: {
    Component: HeroBanner as ComponentType<Record<string, unknown> & SectionInjectedProps>,
    variantProp: 'variant',
  },
  'hero-video': {
    Component: HeroBanner as ComponentType<Record<string, unknown> & SectionInjectedProps>,
    variantProp: 'variant',
  },
  'hero-split': {
    Component: HeroBanner as ComponentType<Record<string, unknown> & SectionInjectedProps>,
    variantProp: 'variant',
  },
  banner: {
    Component: HeroBanner as ComponentType<Record<string, unknown> & SectionInjectedProps>,
    variantProp: 'variant',
  },
  cta: {
    Component: HeroBanner as ComponentType<Record<string, unknown> & SectionInjectedProps>,
    variantProp: 'variant',
  },
  'product-grid': {
    Component: ProductGrid as ComponentType<Record<string, unknown> & SectionInjectedProps>,
    variantProp: 'variant',
  },
  'product-card': {
    Component: ProductGrid as ComponentType<Record<string, unknown> & SectionInjectedProps>,
    variantProp: 'variant',
  },
  'category-grid': {
    Component: CategoryGrid as ComponentType<Record<string, unknown> & SectionInjectedProps>,
    variantProp: 'variant',
  },
  'category-slider': {
    Component: CategoryGrid as ComponentType<Record<string, unknown> & SectionInjectedProps>,
    variantProp: 'variant',
  },
  newsletter: {
    Component: PlaceholderSection as ComponentType<Record<string, unknown> & SectionInjectedProps>,
    variantProp: undefined,
  },
  header: {
    Component: Header as ComponentType<Record<string, unknown> & SectionInjectedProps>,
    variantProp: 'variant',
  },
  footer: {
    Component: Footer as ComponentType<Record<string, unknown> & SectionInjectedProps>,
    variantProp: 'variant',
  },
  cart: {
    Component: Cart as ComponentType<Record<string, unknown> & SectionInjectedProps>,
    variantProp: undefined,
  },
};

/** Resolve component key (from layout JSON) to registry entry. */
export function getComponent(key: string): RegistryEntry | null {
  const k = key.replace(/_/g, '-').toLowerCase().trim();
  return registry[k] ?? null;
}

/** Props for newsletter placeholder when component is "newsletter". */
export function getNewsletterPlaceholderProps(section: LayoutSection, resolved: Record<string, unknown>): Record<string, unknown> {
  return {
    ...BASE_PROPS,
    type: 'custom',
    title: (resolved.title as string) ?? 'Newsletter',
    ...resolved,
  };
}

/** Props for Cart (not in default registry; can be added). */
export function getCartPlaceholderProps(): Record<string, unknown> {
  return { ...BASE_PROPS, title: 'Your Cart', emptyMessage: 'Your cart is empty.' };
}

export { registry };
