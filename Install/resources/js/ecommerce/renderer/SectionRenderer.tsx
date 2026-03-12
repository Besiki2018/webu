/**
 * Renders a single storefront section by type.
 */

import type { SitePlanSection } from '@/ecommerce/schema';
import { Header } from '@/ecommerce/components/Header';
import { Footer } from '@/ecommerce/components/Footer';
import { HeroBanner } from '@/ecommerce/components/HeroBanner';
import { CategoryGrid } from '@/ecommerce/components/CategoryGrid';
import { ProductGrid } from '@/ecommerce/components/ProductGrid';
import { ProductDetails } from '@/ecommerce/components/ProductDetails';
import { Cart } from '@/ecommerce/components/Cart';
import { Checkout } from '@/ecommerce/components/Checkout';
import { PlaceholderSection } from '@/ecommerce/components/PlaceholderSection';

export interface SectionRendererProps {
  section: SitePlanSection;
  basePath?: string;
  pageRoute?: string;
}

const PLACEHOLDER_TYPES = new Set(['FAQ']);

export function SectionRenderer({ section, basePath, pageRoute }: SectionRendererProps) {
  const props = {
    ...(section.props as Record<string, unknown>),
    basePath,
    pageRoute,
  };

  if (PLACEHOLDER_TYPES.has(section.type)) {
    return <PlaceholderSection type={section.type} basePath={basePath} pageRoute={pageRoute} title={section.type} />;
  }

  switch (section.type) {
    case 'Header':
      return <Header {...props} />;
    case 'Footer':
      return <Footer {...props} />;
    case 'HeroBanner':
      return <HeroBanner {...props} />;
    case 'CategoryGrid':
      return <CategoryGrid {...props} />;
    case 'ProductGrid':
      return <ProductGrid {...props} />;
    case 'ProductDetails':
      return <ProductDetails {...props} />;
    case 'Cart':
      return <Cart {...props} />;
    case 'Checkout':
      return <Checkout {...props} />;
    default:
      return <PlaceholderSection type={section.type} basePath={basePath} pageRoute={pageRoute} title={section.type} />;
  }
}
