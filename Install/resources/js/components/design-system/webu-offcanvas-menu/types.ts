import type React from 'react';

export type OffcanvasMenuVariant = 'drawer-1';
export type OffcanvasMenuSide = 'left' | 'right';

export interface OffcanvasMenuItem {
  label: string;
  url: string;
  description?: string;
}

export interface WebuOffcanvasMenuProps {
  variant?: OffcanvasMenuVariant;
  side?: OffcanvasMenuSide;
  title?: string;
  subtitle?: string;
  items?: OffcanvasMenuItem[];
  footerLabel?: string;
  footerUrl?: string;
  basePath?: string;
  open?: boolean;
  defaultOpen?: boolean;
  onOpenChange?: (open: boolean) => void;
  trigger?: React.ReactNode;
  triggerLabel?: string;
  triggerClassName?: string;
  className?: string;
}
