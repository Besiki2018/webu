/**
 * Footer — single builder layout component with variants.
 * Renders layout based on props.variant via explicit switch.
 *
 * Builder-compatible: receives all data via props from builder state; renders purely from props;
 * no hidden internal state for content. Example: <Footer {...componentProps} />.
 */

import { Footer1 } from '@/components/design-system/webu-footer/variants/footer-1';
import { Footer2 } from '@/components/design-system/webu-footer/variants/footer-2';
import { Footer3 } from '@/components/design-system/webu-footer/variants/footer-3';
import { Footer4 } from '@/components/design-system/webu-footer/variants/footer-4';
import type { WebuFooterProps } from '@/components/design-system/webu-footer/types';
import { FOOTER_DEFAULT_VARIANT, type FooterVariantId } from './Footer.variants';

/**
 * Props for the Footer component.
 * Main editable: logo, menus, copyright, newsletterHeading, newsletterPlaceholder, newsletterButtonLabel, backgroundColor, textColor.
 * All content and style are editable via builder/Chat.
 */
export interface FooterProps extends WebuFooterProps {
  logo?: string;
  logoFallback?: string;
  logoUrl?: string;
  menus?: WebuFooterProps['menus'];
  copyright?: string;
  contactAddress?: string;
  newsletterHeading?: string;
  newsletterPlaceholder?: string;
  newsletterButtonLabel?: string;
  paymentsAriaLabel?: string;
  footerNavAriaLabel?: string;
  variant?: FooterVariantId;
  backgroundColor?: string;
  textColor?: string;
}

export interface FooterComponentProps extends FooterProps {
  variant?: FooterVariantId;
}

/**
 * Renders the Footer. Layout is chosen by props.variant.
 */
export function Footer(props: FooterComponentProps) {
  const variant = (props.variant ?? FOOTER_DEFAULT_VARIANT) as FooterVariantId;

  switch (variant) {
    case 'footer-2':
      return <Footer2 {...props} />;
    case 'footer-3':
      return <Footer3 {...props} />;
    case 'footer-4':
      return <Footer4 {...props} />;
    case 'footer-1':
    default:
      return <Footer1 {...props} />;
  }
}

export default Footer;
