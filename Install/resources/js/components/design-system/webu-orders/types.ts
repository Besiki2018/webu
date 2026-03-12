export type OrdersVariant = 'orders-1' | 'orders-2';

export interface OrderItem {
  id: string;
  date: string;
  total: number;
  status: string;
  url?: string;
}

export interface WebuOrdersProps {
  variant?: OrdersVariant;
  title?: string;
  orders: OrderItem[];
  basePath?: string;
  className?: string;
}
