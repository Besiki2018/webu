import { Link } from '@inertiajs/react';
import {
  ChevronDown,
  User,
  Search,
  Heart,
  ShoppingBag,
  Instagram,
} from 'lucide-react';
import { WebuOffcanvasMenu } from '@/components/design-system/webu-offcanvas-menu';
import type { WebuHeaderProps } from '../types';

function path(basePath: string | undefined, p: string): string {
  const target = p.trim();
  if (!target || target === '#') {
    return '#';
  }
  if (/^(https?:|mailto:|tel:)/.test(target)) {
    return target;
  }
  const base = (basePath ?? '').replace(/\/$/, '');
  const pathname = target.startsWith('/') ? target : `/${target}`;
  return base ? `${base}${pathname}` : pathname;
}

/** Clotya-style header: 3 bars — announcement (teal), utility (white), main nav (white) with integrated icons */
export function Header6({
  logo = '',
  logoUrl = '/',
  logoImageUrl,
  logoFallback = 'Logo',
  menu = [],
  basePath,
  announcementText = '',
  announcementCtaLabel = '',
  announcementCtaUrl = '/shop',
  topBarLeftText = '',
  topBarLeftCta = '',
  topBarLeftCtaUrl = '/shop',
  socialFollowers = '',
  socialUrl = '#',
  topBarRightTracking = '',
  topBarRightTrackingUrl = '/account/orders',
  topBarRightLang = '',
  topBarRightCurrency = '',
  accountUrl = '/account',
  searchUrl = '/search',
  wishlistUrl = '/wishlist',
  cartUrl = '/cart',
  menuDrawerSide = 'left',
  menuDrawerTitle,
  menuDrawerSubtitle = '',
  cartCount = 0,
  wishlistCount = 0,
  cartTotal = '',
  navAriaLabel = 'Main navigation',
  searchAriaLabel = 'Search',
  accountAriaLabel = 'Account',
  cartAriaLabel = 'Cart',
  wishlistAriaLabel = 'Wishlist',
  menuTriggerAriaLabel = 'Open menu',
  backgroundColor,
  textColor,
}: WebuHeaderProps) {
  const defaultNav = menu;
  const withDropdown = ['HOME', 'SHOP'];
  const drawerItems = defaultNav.map((item) => ({
    label: item.label,
    url: item.url,
    description: withDropdown.includes(item.label.toUpperCase()) ? 'Highlighted navigation item' : 'Open page',
  }));
  const style = { backgroundColor: backgroundColor || undefined, color: textColor || undefined };

  return (
    <header className="webu-header webu-header--header-6 webu-header--clotya" style={style}>
      {/* 1. Announcement bar — dark teal, white text, centered, CTA link */}
      {(announcementText || announcementCtaLabel) ? (
      <div className="webu-header__announcement">
        <span className="webu-header__announcement-text">
          {announcementText}
          {announcementCtaLabel ? (
            <Link href={path(basePath, announcementCtaUrl)} className="webu-header__announcement-cta">
              {announcementCtaLabel}
            </Link>
          ) : null}
        </span>
      </div>
      ) : null}

      {/* 2. Utility bar */}
      <div className="webu-header__utility">
        <div className="webu-header__utility-left">
          {socialUrl ? (
            <Link href={path(basePath, socialUrl)} className="webu-header__utility-social" aria-label="Instagram">
              <Instagram className="webu-header__icon" size={18} strokeWidth={1.5} />
            </Link>
          ) : null}
          {socialFollowers ? (
            <span className="webu-header__utility-followers">
              {socialFollowers}
              <ChevronDown className="webu-header__icon webu-header__icon--chevron" size={14} />
            </span>
          ) : null}
          {topBarLeftText ? <span className="webu-header__utility-shipping">{topBarLeftText}</span> : null}
          {topBarLeftCta ? (
            <Link href={path(basePath, topBarLeftCtaUrl)} className="webu-header__utility-cta">
              {topBarLeftCta}
            </Link>
          ) : null}
        </div>
        <div className="webu-header__utility-right">
          {topBarRightTracking ? (
            <Link href={path(basePath, topBarRightTrackingUrl)} className="webu-header__utility-item">{topBarRightTracking}</Link>
          ) : null}
          {topBarRightLang ? (
          <span className="webu-header__utility-item webu-header__utility-item--dropdown">
            {topBarRightLang}
            <ChevronDown className="webu-header__icon webu-header__icon--chevron" size={14} />
          </span>
          ) : null}
          {topBarRightCurrency ? (
          <span className="webu-header__utility-item webu-header__utility-item--dropdown">
            {topBarRightCurrency}
            <ChevronDown className="webu-header__icon webu-header__icon--chevron" size={14} />
          </span>
          ) : null}
        </div>
      </div>

      {/* 3. Main nav — white, hamburger + logo + nav links, right: User, Search, Wishlist, Cart */}
      <div className="webu-header__inner webu-header__inner--header-6">
        <div className="webu-header__main-left">
          <WebuOffcanvasMenu
            side={menuDrawerSide}
            title={menuDrawerTitle ?? logo}
            subtitle={menuDrawerSubtitle}
            items={drawerItems}
            footerLabel={announcementCtaLabel}
            footerUrl={announcementCtaUrl}
            basePath={basePath}
            trigger={(
              <button type="button" className="webu-header__menu-btn" aria-label={menuTriggerAriaLabel}>
                <ClotyaMenuIcon className="webu-header__icon" />
              </button>
            )}
          />
          <Link href={path(basePath, logoUrl)} className="webu-header__logo webu-header__logo--header-6">
            {logoImageUrl ? (
              <img src={logoImageUrl} alt={logo || logoFallback} className="webu-header__logo-img" />
            ) : (
              logo || logoFallback
            )}
          </Link>
          <nav className="webu-nav webu-nav--header-6" aria-label={navAriaLabel}>
            {defaultNav.map((item) => (
              <span key={item.slug ?? item.url} className="webu-nav__item-wrap">
                <Link href={path(basePath, item.url)} className="webu-nav__link webu-nav__link--header-6">
                  {item.label}
                </Link>
                {withDropdown.includes(item.label.toUpperCase()) && (
                  <ChevronDown className="webu-header__icon webu-header__icon--nav" size={14} />
                )}
              </span>
            ))}
          </nav>
        </div>
        <div className="webu-header__actions webu-header__actions--header-6">
          <Link href={path(basePath, accountUrl)} className="webu-header__action-icon-btn" aria-label={accountAriaLabel || 'Account'}>
            <User className="webu-header__icon" size={20} strokeWidth={1.5} />
          </Link>
          <Link href={path(basePath, searchUrl)} className="webu-header__action-icon-btn" aria-label={searchAriaLabel || 'Search'}>
            <Search className="webu-header__icon" size={20} strokeWidth={1.5} />
          </Link>
          <Link href={path(basePath, wishlistUrl)} className="webu-header__action-icon-btn webu-header__action-icon-btn--badge" aria-label={wishlistAriaLabel || 'Wishlist'} data-badge={wishlistCount}>
            <Heart className="webu-header__icon" size={20} strokeWidth={1.5} />
          </Link>
          <Link href={path(basePath, cartUrl)} className="webu-header__action-cart" aria-label={cartAriaLabel || 'Cart'}>
            <span className="webu-header__cart-icon-wrap">
              <ShoppingBag className="webu-header__icon" size={20} strokeWidth={1.5} />
              <span className="webu-header__cart-badge" data-badge={cartCount} />
            </span>
            <span className="webu-header__cart-total">{cartTotal}</span>
          </Link>
        </div>
      </div>
    </header>
  );
}

function ClotyaMenuIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className={className} aria-hidden="true">
      <path d="M3 6h18" />
      <path d="M3 12h18" />
      <path d="M3 18h18" />
    </svg>
  );
}
