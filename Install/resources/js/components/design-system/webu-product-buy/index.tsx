import type { WebuProductBuyProps } from './types';
import { Buy1 } from './variants/buy-1';

const VARIANTS = ['buy-1', 'buy-2', 'buy-3'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'buy-1';

export type { WebuProductBuyProps };

export function WebuProductBuy({ variant, ...props }: WebuProductBuyProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Buy1 {...props} />;
}
