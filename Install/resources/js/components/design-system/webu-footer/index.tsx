import type { WebuFooterProps } from './types';
import { Footer1 } from './variants/footer-1';
import { Footer2 } from './variants/footer-2';
import { Footer3 } from './variants/footer-3';
import { Footer4 } from './variants/footer-4';

const VARIANTS = ['footer-1', 'footer-2', 'footer-3', 'footer-4'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'footer-1';

const variantMap = { Footer1, Footer2, Footer3, Footer4 } as const;

export type { WebuFooterProps };

export function WebuFooter({ variant, ...props }: WebuFooterProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  const key = `Footer${v.replace('footer-', '')}` as keyof typeof variantMap;
  const Component = variantMap[key] ?? Footer1;
  return <Component {...props} />;
}
