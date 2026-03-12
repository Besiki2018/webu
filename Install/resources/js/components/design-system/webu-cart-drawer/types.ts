export type CartDrawerVariant = 'drawer-1' | 'drawer-2';

export interface CartLine {
  id: string;
  name: string;
  price: number;
  quantity: number;
  image?: string;
}

export interface WebuCartDrawerProps {
  variant?: CartDrawerVariant;
  open: boolean;
  onClose: () => void;
  lines: CartLine[];
  total: number;
  basePath?: string;
  checkoutUrl?: string;
  className?: string;
}
