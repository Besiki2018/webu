import type { WebuContactProps } from './types';
import { Contact1 } from './variants/contact-1';

const VARIANTS = ['contact-1', 'contact-2', 'contact-3'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'contact-1';

export type { WebuContactProps };

export function WebuContact({ variant, ...props }: WebuContactProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Contact1 {...props} />;
}
