/**
 * Footer component default props.
 * Each component must provide default props; used by builder, schema, and preview.
 */

import type { FooterVariantId } from './Footer.variants';

export interface FooterPaymentBadge {
  label: string;
  tone?: string;
}

export interface FooterDefaultProps {
  logoText: string;
  logoUrl: string;
  logoFallback: string;
  paymentsAriaLabel: string;
  footerNavAriaLabel: string;
  description: string;
  subtitle: string;
  copyright: string;
  links: string;
  socialLinks: string;
  backgroundColor: string;
  textColor: string;
  variant?: FooterVariantId;
  newsletterHeading: string;
  newsletterCopy: string;
  newsletterPlaceholder: string;
  newsletterButtonLabel: string;
  paymentsLabel: string;
  paymentMethods: FooterPaymentBadge[];
  contactAddress: string;
  padding?: string;
  spacing?: string;
}

export const FOOTER_DEFAULTS: FooterDefaultProps = {
  logoText: 'Footer',
  logoUrl: '/',
  logoFallback: 'Store',
  paymentsAriaLabel: 'Payment methods',
  footerNavAriaLabel: 'Footer',
  description: '',
  subtitle: '',
  copyright: '© 2024',
  links: '[]',
  socialLinks: '[]',
  backgroundColor: '#111111',
  textColor: '#ffffff',
  variant: 'footer-1',
  newsletterHeading: '',
  newsletterCopy: '',
  newsletterPlaceholder: 'Your email',
  newsletterButtonLabel: 'Subscribe',
  paymentsLabel: '',
  paymentMethods: [],
  contactAddress: '',
  padding: '',
  spacing: '',
};

/** Default props for Footer (alias for FOOTER_DEFAULTS). */
export const FooterDefaults = FOOTER_DEFAULTS;
