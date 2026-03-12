import type { WebuProductDetailsProps, ProductVariantOption } from './types';
import { Details1 } from './variants/details-1';

const VARIANTS = ['details-1', 'details-2', 'details-3'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'details-1';

export type { WebuProductDetailsProps, ProductVariantOption };

export function WebuProductDetails({ variant, ...props }: WebuProductDetailsProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Details1 {...props} />;
}
