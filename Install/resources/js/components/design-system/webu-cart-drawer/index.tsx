import type { WebuCartDrawerProps, CartLine } from './types';
import { Drawer1 } from './variants/drawer-1';

const VARIANTS = ['drawer-1', 'drawer-2'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'drawer-1';

export type { WebuCartDrawerProps, CartLine };

export function WebuCartDrawer({ variant, ...props }: WebuCartDrawerProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Drawer1 {...props} />;
}
