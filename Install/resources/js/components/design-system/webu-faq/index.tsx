import type { WebuFaqProps, FaqItem } from './types';
import { Faq1 } from './variants/faq-1';

const VARIANTS = ['faq-1', 'faq-2'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'faq-1';

export type { WebuFaqProps, FaqItem };

export function WebuFaq({ variant, ...props }: WebuFaqProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Faq1 {...props} />;
}
