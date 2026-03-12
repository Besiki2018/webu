/**
 * Header component default props.
 * Each component must provide default props; used by builder, schema, and preview.
 */

import type { HeaderVariantId } from './Header.variants';

export interface HeaderDefaultProps {
  logo_url: string;
  logo_alt: string;
  logoText: string;
  logoFallback: string;
  menu_items: string;
  ctaText: string;
  ctaLink: string;
  sticky: boolean;
  backgroundColor: string;
  textColor: string;
  layoutVariant: string;
  variant?: HeaderVariantId;
  navAriaLabel: string;
  menuDrawerFooterLabel: string;
  menuDrawerFooterUrl: string;
  showSearch?: boolean;
  searchMode?: 'none' | 'generic' | 'product';
  showCartIcon?: boolean;
  showWishlistIcon?: boolean;
  padding?: string;
  spacing?: string;
  alignment?: string;
}

export const HEADER_DEFAULTS: HeaderDefaultProps = {
  logo_url: '',
  logo_alt: '',
  logoText: 'Webu',
  logoFallback: 'Logo',
  menu_items: '[]',
  ctaText: 'Get started',
  ctaLink: '#',
  sticky: false,
  backgroundColor: '#ffffff',
  textColor: '#111111',
  layoutVariant: 'default',
  variant: 'header-1',
  navAriaLabel: 'Main navigation',
  menuDrawerFooterLabel: '',
  menuDrawerFooterUrl: '/contact',
  showSearch: false,
  searchMode: 'generic',
  showCartIcon: false,
  showWishlistIcon: false,
  padding: '',
  spacing: '',
  alignment: 'left',
};

/** Default props for Header (alias for HEADER_DEFAULTS). */
export const HeaderDefaults = HEADER_DEFAULTS;
