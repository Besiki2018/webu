import type { WebuHeaderProps } from './types';
import { Header1 } from './variants/header-1';
import { Header2 } from './variants/header-2';
import { Header3 } from './variants/header-3';
import { Header4 } from './variants/header-4';
import { Header5 } from './variants/header-5';
import { Header6 } from './variants/header-6';

const VARIANTS = ['header-1', 'header-2', 'header-3', 'header-4', 'header-5', 'header-6'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'header-1';

export type { WebuHeaderProps };

const variantMap = { Header1, Header2, Header3, Header4, Header5, Header6 } as const;

export function WebuHeader({ variant, ...props }: WebuHeaderProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  const num = v.replace('header-', '');
  const key = `Header${num}` as keyof typeof variantMap;
  const Component = variantMap[key] ?? Header1;
  return <Component {...props} />;
}
