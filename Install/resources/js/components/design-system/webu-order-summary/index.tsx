import type { WebuOrderSummaryProps, OrderSummaryLine } from './types';
import { Summary1 } from './variants/summary-1';

const VARIANTS = ['summary-1', 'summary-2'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'summary-1';

export type { WebuOrderSummaryProps, OrderSummaryLine };

export function WebuOrderSummary({ variant, ...props }: WebuOrderSummaryProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Summary1 {...props} />;
}
