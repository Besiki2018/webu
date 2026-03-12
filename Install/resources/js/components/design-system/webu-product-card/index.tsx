import type { WebuProductCardProps, WebuProductCardProduct } from './types';
import { ProductCardClassic } from './variants/classic';
import { ProductCardMinimal } from './variants/minimal';

const VARIANTS = ['classic', 'minimal', 'modern', 'premium', 'compact', 'product-card-1', 'product-card-2', 'product-card-3', 'product-card-4', 'product-card-5'] as const;
const DEFAULT = 'classic';

const toInternal = (v: string) => {
  if (v?.startsWith('product-card-')) {
    const n = v.replace('product-card-', '');
    const map: Record<string, string> = { '1': 'classic', '2': 'modern', '3': 'premium', '4': 'classic', '5': 'classic' };
    return map[n] ?? 'classic';
  }
  return v ?? DEFAULT;
};

export type { WebuProductCardProps, WebuProductCardProduct };

export function WebuProductCard({ variant, ...props }: WebuProductCardProps) {
  const v = toInternal(variant ?? DEFAULT);
  if (v === 'minimal') return <ProductCardMinimal {...props} />;
  return <ProductCardClassic {...props} />;
}
