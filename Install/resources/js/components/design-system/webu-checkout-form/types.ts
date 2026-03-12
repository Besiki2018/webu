export type CheckoutFormVariant = 'checkout-1' | 'checkout-2' | 'checkout-3';

export interface WebuCheckoutFormProps {
  variant?: CheckoutFormVariant;
  action?: string;
  method?: 'post' | 'get';
  basePath?: string;
  /** From CMS: labels, placeholders */
  labels?: Record<string, string>;
  className?: string;
}
