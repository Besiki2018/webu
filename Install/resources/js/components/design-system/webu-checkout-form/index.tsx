import type { WebuCheckoutFormProps } from './types';
import { Checkout1 } from './variants/checkout-1';

const VARIANTS = ['checkout-1', 'checkout-2', 'checkout-3'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'checkout-1';

export type { WebuCheckoutFormProps };

export function WebuCheckoutForm({ variant, ...props }: WebuCheckoutFormProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Checkout1 {...props} />;
}
