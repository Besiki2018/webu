export type HeaderVariant = 'header-1' | 'header-2' | 'header-3' | 'header-4' | 'header-5' | 'header-6';
export type HeaderMenuDrawerSide = 'left' | 'right';
export type HeaderSocialLink = { label: string; url: string; icon?: string };

export interface WebuHeaderProps {
  variant?: HeaderVariant;
  logo?: string;
  logoUrl?: string;
  /** Fallback when logo text is empty (editable, e.g. "Logo"). */
  logoFallback?: string;
  /** Site logo image URL (from site params / global_settings). When set, header shows image instead of text. */
  logoImageUrl?: string | null;
  /** From CMS: navigation */
  menu?: { label: string; url: string; slug?: string; description?: string }[];
  departmentMenu?: { label: string; url: string; slug?: string; description?: string }[];
  ctaLabel?: string | null;
  ctaUrl?: string | null;
  /** From CMS: show search, cart, account, wishlist */
  showSearch?: boolean;
  showCart?: boolean;
  showAccount?: boolean;
  showWishlist?: boolean;
  cartCount?: number;
  wishlistCount?: number;
  cartTotal?: string;
  basePath?: string;
  className?: string;
  /** header-6 (Clotya): announcement bar */
  announcementText?: string;
  announcementCtaLabel?: string;
  announcementCtaUrl?: string;
  /** header-3 (Finwave): top utility strip */
  topBarLoginLabel?: string;
  topBarLoginUrl?: string;
  topBarSocialLinks?: HeaderSocialLink[];
  topBarLocationText?: string;
  topBarLocationUrl?: string;
  topBarEmailText?: string;
  topBarEmailUrl?: string;
  hotlineEyebrow?: string;
  hotlineLabel?: string;
  hotlineUrl?: string;
  /** header-6: utility bar left */
  topBarLeftText?: string;
  topBarLeftCta?: string;
  topBarLeftCtaUrl?: string;
  socialFollowers?: string;
  socialUrl?: string;
  /** header-6: utility bar right */
  topBarRightTracking?: string;
  topBarRightTrackingUrl?: string;
  topBarRightLang?: string;
  topBarRightCurrency?: string;
  /** header-4: search + department + promo */
  accountUrl?: string;
  searchUrl?: string;
  wishlistUrl?: string;
  cartUrl?: string;
  searchPlaceholder?: string;
  searchCategoryLabel?: string;
  searchButtonLabel?: string;
  departmentLabel?: string;
  promoEyebrow?: string;
  promoLabel?: string;
  promoUrl?: string;
  accountEyebrow?: string;
  accountLabel?: string;
  cartLabel?: string;
  utilityLinks?: { label: string; url: string }[];
  menuDrawerSide?: HeaderMenuDrawerSide;
  menuDrawerTitle?: string;
  menuDrawerSubtitle?: string;
  /** Drawer footer link label (e.g. "Contact us") */
  menuDrawerFooterLabel?: string;
  /** Drawer footer link URL */
  menuDrawerFooterUrl?: string;
  /** Nav landmark aria-label (e.g. "Main navigation") */
  navAriaLabel?: string;
  /** Search button/link aria-label */
  searchAriaLabel?: string;
  /** Menu trigger button aria-label */
  menuTriggerAriaLabel?: string;
  /** Account link aria-label */
  accountAriaLabel?: string;
  /** Cart link aria-label */
  cartAriaLabel?: string;
  /** Wishlist link aria-label */
  wishlistAriaLabel?: string;
  /** Inline styles: background, text color, etc. */
  backgroundColor?: string;
  textColor?: string;
  /** Visibility (e.g. hide on mobile) — often applied by wrapper */
  visibility?: string;
  /** Padding / spacing — often applied by wrapper */
  padding?: string;
  spacing?: string;
  alignment?: 'left' | 'center' | 'right';
}
