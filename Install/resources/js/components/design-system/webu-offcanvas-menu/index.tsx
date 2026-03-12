import { Drawer1 } from './variants/drawer-1';
import type { WebuOffcanvasMenuProps } from './types';

const VARIANTS = ['drawer-1'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'drawer-1';

export type { OffcanvasMenuItem, OffcanvasMenuSide, WebuOffcanvasMenuProps } from './types';

export function WebuOffcanvasMenu({ variant, ...props }: WebuOffcanvasMenuProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  if (v === 'drawer-1') {
    return <Drawer1 {...props} />;
  }
  return <Drawer1 {...props} />;
}
