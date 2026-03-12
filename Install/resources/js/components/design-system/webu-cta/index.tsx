import type { WebuCtaProps } from './types';
import { Cta1 } from './variants/cta-1';

const VARIANTS = ['cta-1', 'cta-2', 'cta-3', 'cta-4'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'cta-1';

export type { WebuCtaProps };

export function WebuCta({ variant, ...props }: WebuCtaProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Cta1 {...props} />;
}
