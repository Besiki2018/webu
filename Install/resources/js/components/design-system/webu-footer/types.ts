export type FooterVariant = 'footer-1' | 'footer-2' | 'footer-3' | 'footer-4';

export interface FooterPaymentBadge {
  label: string;
  tone?: string;
}

export interface WebuFooterProps {
  variant?: FooterVariant;
  logo?: string;
  logoUrl?: string;
  /** Fallback when logo is empty (editable, e.g. "Store"). */
  logoFallback?: string;
  /** Footer menus keyed by column key */
  menus?: Record<string, { label: string; url: string }[]>;
  contactAddress?: string;
  copyright?: string;
  basePath?: string;
  className?: string;
  /** Newsletter block heading */
  newsletterHeading?: string;
  /** Newsletter block body copy */
  newsletterCopy?: string;
  /** Email input placeholder */
  newsletterPlaceholder?: string;
  /** Subscribe button label */
  newsletterButtonLabel?: string;
  /** Payment section label (e.g. "SECURE PAYMENT:") */
  paymentsLabel?: string;
  /** Payment method badges (e.g. AMEX, VISA) */
  paymentMethods?: FooterPaymentBadge[];
  /** Aria-label for payments section (editable, e.g. "Payment methods"). */
  paymentsAriaLabel?: string;
  /** Aria-label for footer nav when single nav (editable, e.g. "Footer"). */
  footerNavAriaLabel?: string;
  /** Inline styles */
  backgroundColor?: string;
  textColor?: string;
  padding?: string;
  spacing?: string;
}
