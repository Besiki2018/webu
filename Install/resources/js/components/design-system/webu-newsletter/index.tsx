import type { WebuNewsletterProps } from './types';
import { Newsletter1 } from './variants/newsletter-1';

const VARIANTS = ['newsletter-1', 'newsletter-2', 'newsletter-3'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'newsletter-1';

export type { WebuNewsletterProps };

export function WebuNewsletter({ variant, ...props }: WebuNewsletterProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  switch (v) {
    case 'newsletter-2':
      return <Newsletter1 {...props} />;
    default:
      return <Newsletter1 {...props} />;
  }
}
