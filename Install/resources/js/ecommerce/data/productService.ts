/**
 * Product and category data for the ecommerce storefront.
 * Stub implementation; replace with API or CMS data in production.
 */

export interface Product {
  id: string;
  slug: string;
  title: string;
  price: number;
  currency: string;
  image: string;
  badge?: string | null;
  rating?: number | null;
  description?: string | null;
}

export interface Category {
  id: string;
  title: string;
  image: string;
  link: string;
}

const stubProducts: Product[] = [
  { id: '1', slug: 'product-1', title: 'Product 1', price: 29.99, currency: 'GEL', image: '/placeholder.svg', badge: 'New', rating: 4.5, description: 'Description 1' },
  { id: '2', slug: 'product-2', title: 'Product 2', price: 49.99, currency: 'GEL', image: '/placeholder.svg', rating: 4, description: 'Description 2' },
  { id: '3', slug: 'product-3', title: 'Product 3', price: 19.99, currency: 'GEL', image: '/placeholder.svg', badge: 'Sale', description: 'Description 3' },
];

const stubCategories: Category[] = [
  { id: 'cat-1', title: 'Category 1', image: '/placeholder.svg', link: '/shop?cat=1' },
  { id: 'cat-2', title: 'Category 2', image: '/placeholder.svg', link: '/shop?cat=2' },
  { id: 'cat-3', title: 'Category 3', image: '/placeholder.svg', link: '/shop?cat=3' },
];

export function getProducts(options: { categoryId?: string; limit?: number } = {}): Product[] {
  const { categoryId, limit = 12 } = options;
  const list = categoryId ? stubProducts.filter((p) => p.id === categoryId || p.slug === categoryId) : [...stubProducts];
  return list.slice(0, limit);
}

export function getCategories(): Category[] {
  return [...stubCategories];
}

export function getProductBySlug(slug: string): Product | null {
  if (!slug) return null;
  return stubProducts.find((p) => p.slug === slug) ?? null;
}
