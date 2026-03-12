import { Link } from '@inertiajs/react';
import {
  BadgePercent,
  ChevronDown,
  Headphones,
  Heart,
  Menu,
  Search,
  ShoppingCart,
  Smartphone,
  User,
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

/** Machic-inspired header: utility row, search row and category rail */
export function Header4({
  logo = '',
  logoUrl = '/',
  logoImageUrl,
  logoFallback = 'Logo',
  menu = [],
  basePath,
  utilityLinks = [],
  departmentMenu = [],
  topBarRightTracking = '',
  topBarRightTrackingUrl = '/account/orders',
  topBarRightLang = '',
  topBarRightCurrency = '',
  accountUrl = '/account',
  searchUrl = '/search',
  wishlistUrl = '/wishlist',
  cartUrl = '/cart',
  searchPlaceholder = '',
  searchCategoryLabel = '',
  searchButtonLabel = '',
  departmentLabel = '',
  promoEyebrow = '',
  promoLabel = '',
  promoUrl = '/shop',
  accountEyebrow = '',
  accountLabel = '',
  cartLabel = '',
  wishlistCount = 0,
  cartCount = 0,
  cartTotal = '',
  menuDrawerSide = 'left',
  menuDrawerTitle,
  menuDrawerSubtitle = '',
  navAriaLabel = 'Main navigation',
  searchAriaLabel = 'Search',
  accountAriaLabel = 'Account',
  cartAriaLabel = 'Cart',
  wishlistAriaLabel = 'Wishlist',
  menuTriggerAriaLabel = 'Open departments menu',
  backgroundColor,
  textColor,
}: WebuHeaderProps) {
  const defaultNav = menu;
  const withDropdown = ['HOME', 'SHOP'];
  type DrawerItem = { label: string; url: string; slug?: string; description?: string };
  const drawerSource = departmentMenu.length > 0 ? departmentMenu : defaultNav;
  const drawerItems = (drawerSource as DrawerItem[]).map((item) => ({
    label: item.label,
    url: item.url,
    description: item.description ?? (withDropdown.includes(item.label.toUpperCase()) ? 'Highlighted navigation item' : 'Open page'),
  }));
  const style = { backgroundColor: backgroundColor || undefined, color: textColor || undefined };

  return (
    <header className="webu-header webu-header--header-4 webu-header--machic" style={style}>
      <div className="webu-header__utility webu-header__utility--header-4">
        <div className="webu-header__utility-left webu-header__utility-left--header-4">
          {(utilityLinks ?? []).map((item) => (
            <Link key={`${item.label}-${item.url}`} href={path(basePath, item.url)} className="webu-header__utility-link webu-header__utility-link--header-4">
              {item.label}
            </Link>
          ))}
        </div>
        <div className="webu-header__utility-right webu-header__utility-right--header-4">
          {topBarRightTracking ? (
            <Link href={path(basePath, topBarRightTrackingUrl)} className="webu-header__utility-item">{topBarRightTracking}</Link>
          ) : null}
          {topBarRightLang ? (
            <span className="webu-header__utility-item webu-header__utility-item--dropdown">
              {topBarRightLang}
              <ChevronDown className="webu-header__icon webu-header__icon--chevron" strokeWidth={1.7} />
            </span>
          ) : null}
          {topBarRightCurrency ? (
            <span className="webu-header__utility-item webu-header__utility-item--dropdown">
              {topBarRightCurrency}
              <ChevronDown className="webu-header__icon webu-header__icon--chevron" strokeWidth={1.7} />
            </span>
          ) : null}
        </div>
      </div>

      <div className="webu-header__search-row">
        <Link href={path(basePath, logoUrl)} className="webu-header__logo webu-header__logo--header-4" aria-label={logo || logoFallback}>
          {logoImageUrl ? (
            <img src={logoImageUrl} alt={logo || logoFallback} className="webu-header__logo-img" />
          ) : (
            <>
              <span className="webu-header__machic-mark" aria-hidden="true" />
              <span className="webu-header__machic-wordmark">{logo || logoFallback}</span>
            </>
          )}
        </Link>

        <form className="webu-header__search-shell" action={path(basePath, searchUrl)} role="search">
          <button type="button" className="webu-header__search-scope" aria-label={searchAriaLabel || 'Select category'}>
            <span>{searchCategoryLabel || 'All'}</span>
            <ChevronDown className="webu-header__icon webu-header__icon--chevron" strokeWidth={1.7} />
          </button>
          <span className="webu-header__search-icon-wrap" aria-hidden="true">
            <Search className="webu-header__icon" strokeWidth={1.7} />
          </span>
          <input type="search" name="q" className="webu-header__search-input" placeholder={searchPlaceholder || 'Search'} aria-label={searchPlaceholder || searchAriaLabel || 'Search'} />
          <button type="submit" className="webu-header__search-submit">{searchButtonLabel || 'Search'}</button>
        </form>

        <div className="webu-header__actions webu-header__actions--header-4">
          <Link href={path(basePath, accountUrl)} className="webu-header__account-link" aria-label={accountAriaLabel || accountLabel || 'Account'}>
            <span className="webu-header__account-icon-wrap">
              <User className="webu-header__icon" strokeWidth={1.7} />
            </span>
            <span className="webu-header__account-copy">
              {accountEyebrow ? <span className="webu-header__account-eyebrow">{accountEyebrow}</span> : null}
              <span className="webu-header__account-label">{accountLabel || 'Account'}</span>
            </span>
          </Link>

          <Link href={path(basePath, wishlistUrl)} className="webu-header__action-icon-btn webu-header__action-icon-btn--badge" aria-label={wishlistAriaLabel || 'Wishlist'} data-badge={wishlistCount}>
            <Heart className="webu-header__icon" strokeWidth={1.7} />
          </Link>

          <Link href={path(basePath, cartUrl)} className="webu-header__action-cart webu-header__action-cart--header-4" aria-label={cartAriaLabel || 'Cart'}>
            <span className="webu-header__cart-icon-wrap">
              <ShoppingCart className="webu-header__icon" strokeWidth={1.7} />
              <span className="webu-header__cart-badge" data-badge={cartCount} />
            </span>
            <span className="webu-header__cart-copy">
              <span className="webu-header__cart-label">{cartLabel || 'Cart'}</span>
              <span className="webu-header__cart-total">{cartTotal}</span>
            </span>
          </Link>
        </div>
      </div>

      <div className="webu-header__nav-row">
        <WebuOffcanvasMenu
          side={menuDrawerSide}
          title={menuDrawerTitle ?? departmentLabel}
          subtitle={menuDrawerSubtitle}
          items={drawerItems}
          footerLabel={promoLabel}
          footerUrl={promoUrl}
          basePath={basePath}
          trigger={(
            <button type="button" className="webu-header__department-trigger" aria-label={menuTriggerAriaLabel}>
              <span className="webu-header__department-trigger-icon">
                <Menu className="webu-header__icon" strokeWidth={1.8} />
              </span>
              <span className="webu-header__department-trigger-label">{departmentLabel || 'Menu'}</span>
              <ChevronDown className="webu-header__icon webu-header__icon--chevron" strokeWidth={1.7} />
            </button>
          )}
        />

        <nav className="webu-nav webu-nav--header-4" aria-label={navAriaLabel}>
          {defaultNav.map((item, index) => {
            const labelUpper = item.label.toUpperCase();
            const showDropdown = withDropdown.includes(labelUpper) || index < 2;
            const icon = labelUpper === 'CELL PHONES'
              ? <Smartphone className="webu-header__icon webu-header__icon--menu-feature" strokeWidth={1.7} />
              : labelUpper === 'HEADPHONES'
                ? <Headphones className="webu-header__icon webu-header__icon--menu-feature" strokeWidth={1.7} />
                : null;

            return (
              <span key={item.slug ?? item.url} className="webu-nav__item-wrap webu-nav__item-wrap--header-4">
                {icon}
                <Link href={path(basePath, item.url)} className={`webu-nav__link webu-nav__link--header-4${index === 0 ? ' is-active' : ''}`}>
                  {item.label}
                </Link>
                {showDropdown ? <ChevronDown className="webu-header__icon webu-header__icon--nav" strokeWidth={1.7} /> : null}
              </span>
            );
          })}
        </nav>

        {(promoEyebrow || promoLabel) ? (
        <Link href={path(basePath, promoUrl)} className="webu-header__promo-link">
          <span className="webu-header__promo-icon">
            <BadgePercent className="webu-header__icon" strokeWidth={1.9} />
          </span>
          <span className="webu-header__promo-copy">
            {promoEyebrow ? <span className="webu-header__promo-eyebrow">{promoEyebrow}</span> : null}
            {promoLabel ? <span className="webu-header__promo-label">{promoLabel}</span> : null}
          </span>
          <ChevronDown className="webu-header__icon webu-header__icon--chevron" strokeWidth={1.7} />
        </Link>
        ) : null}
      </div>
    </header>
  );
}
