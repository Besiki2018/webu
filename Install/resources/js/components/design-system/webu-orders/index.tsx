import type { WebuOrdersProps, OrderItem } from './types';
import { Orders1 } from './variants/orders-1';

const VARIANTS = ['orders-1', 'orders-2'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'orders-1';

export type { WebuOrdersProps, OrderItem };

export function WebuOrders({ variant, ...props }: WebuOrdersProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Orders1 {...props} />;
}
