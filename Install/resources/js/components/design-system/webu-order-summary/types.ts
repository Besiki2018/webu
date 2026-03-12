export type OrderSummaryVariant = 'summary-1' | 'summary-2';

export interface OrderSummaryLine {
  label: string;
  amount: number;
}

export interface WebuOrderSummaryProps {
  variant?: OrderSummaryVariant;
  lines: OrderSummaryLine[];
  total: number;
  basePath?: string;
  className?: string;
}
