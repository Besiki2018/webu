/**
 * Header — single builder layout component with variants.
 * Renders layout based on props.variant via explicit switch.
 *
 * Builder-compatible: receives all data via props from builder state; renders purely from props;
 * no hidden internal state for content. Example: <Header {...componentProps} />.
 */

import { Header1 } from '@/components/design-system/webu-header/variants/header-1';
import { Header2 } from '@/components/design-system/webu-header/variants/header-2';
import { Header3 } from '@/components/design-system/webu-header/variants/header-3';
import { Header4 } from '@/components/design-system/webu-header/variants/header-4';
import { Header5 } from '@/components/design-system/webu-header/variants/header-5';
import { Header6 } from '@/components/design-system/webu-header/variants/header-6';
import type { WebuHeaderProps } from '@/components/design-system/webu-header/types';
import { HEADER_DEFAULT_VARIANT, type HeaderVariantId } from './Header.variants';

/**
 * Props for the Header component.
 * Main editable: logo, logoText, menu, ctaText, ctaLink, backgroundColor, textColor.
 * All content and style are editable via builder/Chat.
 */
export interface HeaderProps extends WebuHeaderProps {
  logo?: string;
  logoText?: string;
  logoFallback?: string;
  menu?: WebuHeaderProps['menu'];
  ctaText?: string;
  ctaLabel?: string;
  ctaLink?: string;
  ctaUrl?: string;
  variant?: HeaderVariantId;
  backgroundColor?: string;
  textColor?: string;
}

export interface HeaderComponentProps extends HeaderProps {
  variant?: HeaderVariantId;
}

/**
 * Renders the Header. Layout is chosen by props.variant (default | center | split | mega-style variants).
 */
export function Header(props: HeaderComponentProps) {
  const variant = (props.variant ?? HEADER_DEFAULT_VARIANT) as HeaderVariantId;

  switch (variant) {
    case 'header-2':
      return <Header2 {...props} />;
    case 'header-3':
      return <Header3 {...props} />;
    case 'header-4':
      return <Header4 {...props} />;
    case 'header-5':
      return <Header5 {...props} />;
    case 'header-6':
      return <Header6 {...props} />;
    case 'header-1':
    default:
      return <Header1 {...props} />;
  }
}

export default Header;
