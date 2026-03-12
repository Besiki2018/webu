import type React from 'react';
import { Link } from '@inertiajs/react';
import { ChevronDown, Mail, MapPin, PhoneCall, Search, User } from 'lucide-react';
import { WebuOffcanvasMenu } from '@/components/design-system/webu-offcanvas-menu';
import type { HeaderSocialLink, WebuHeaderProps } from '../types';

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

function SmartHref({
  href,
  basePath,
  className,
  ariaLabel,
  children,
}: {
  href: string;
  basePath?: string;
  className?: string;
  ariaLabel?: string;
  children: React.ReactNode;
}) {
  const resolved = path(basePath, href);
  if (resolved === '#' || /^(https?:|mailto:|tel:)/.test(resolved)) {
    return <a href={resolved} className={className} aria-label={ariaLabel}>{children}</a>;
  }
  return <Link href={resolved} className={className} aria-label={ariaLabel}>{children}</Link>;
}

function SocialIcon({ icon, className }: { icon?: string; className?: string }) {
  const normalized = (icon ?? '').trim().toLowerCase();

  if (normalized === 'facebook') {
    return (
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className={className} aria-hidden="true">
        <path d="M14 8h3V4h-3c-3 0-5 2-5 5v3H6v4h3v5h4v-5h3.2l.8-4H13V9c0-.8.2-1 1-1z" />
      </svg>
    );
  }

  if (normalized === 'x' || normalized === 'twitter' || normalized === 'twitter-x') {
    return (
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className={className} aria-hidden="true">
        <path d="M5 4l14 16" />
        <path d="M19 4L5 20" />
      </svg>
    );
  }

  if (normalized === 'pinterest') {
    return (
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className={className} aria-hidden="true">
        <path d="M12 3a7 7 0 0 0-2.55 13.52l1.05-3.98-.44-.92c-.2-.42-.31-.9-.31-1.4 0-1.46.85-2.56 1.9-2.56.9 0 1.34.67 1.34 1.48 0 .9-.58 2.25-.88 3.5-.25 1.05.53 1.9 1.56 1.9 1.87 0 3.3-1.96 3.3-4.79 0-2.5-1.8-4.25-4.37-4.25-2.98 0-4.72 2.23-4.72 4.54 0 .9.35 1.87.79 2.39a.32.32 0 0 1 .07.31l-.31 1.27c-.05.2-.16.24-.36.15-1.33-.62-2.16-2.57-2.16-4.14 0-3.37 2.45-6.46 7.07-6.46 3.71 0 6.59 2.64 6.59 6.16 0 3.67-2.31 6.63-5.52 6.63-1.08 0-2.1-.56-2.44-1.22l-.66 2.5c-.24.92-.9 2.07-1.34 2.77A7 7 0 1 0 12 3z" />
      </svg>
    );
  }

  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className={className} aria-hidden="true">
      <rect x="3" y="3" width="18" height="18" rx="5" />
      <circle cx="12" cy="12" r="4" />
      <circle cx="17.4" cy="6.6" r="1" />
    </svg>
  );
}

function Header3MenuIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className={className} aria-hidden="true">
      <path d="M3 6h18" />
      <path d="M3 12h18" />
      <path d="M3 18h18" />
    </svg>
  );
}

export function Header3({
  logo = '',
  logoUrl = '/',
  logoImageUrl,
  logoFallback = 'Logo',
  menu = [],
  basePath,
  searchUrl = '/search',
  topBarLoginLabel = '',
  topBarLoginUrl = '/account/login',
  topBarSocialLinks = [],
  topBarLocationText = '',
  topBarLocationUrl = '/contact',
  topBarEmailText = '',
  topBarEmailUrl = 'mailto:',
  hotlineEyebrow = '',
  hotlineLabel = '',
  hotlineUrl = '',
  menuDrawerSide = 'right',
  menuDrawerTitle,
  menuDrawerSubtitle = '',
  menuDrawerFooterLabel = '',
  menuDrawerFooterUrl = '/contact',
  navAriaLabel = 'Main navigation',
  searchAriaLabel = 'Search',
  menuTriggerAriaLabel = 'Open menu',
  backgroundColor,
  textColor,
}: WebuHeaderProps) {
  const resolvedLogo = logo || logoFallback;
  const displayMenu = menu;
  const drawerItems = displayMenu.map((item, index) => ({
    label: item.label,
    url: item.url,
    description: item.description ?? (index < displayMenu.length - 1 ? 'Open subsection' : 'Open page'),
  }));
  const socialLinks = topBarSocialLinks ?? [];

  const style = { backgroundColor: backgroundColor || undefined, color: textColor || undefined };
  return (
    <header className="webu-header webu-header--header-3 webu-header--finwave" style={style}>
      <div className="webu-header__finwave-topbar">
        <div className="webu-header__finwave-topbar-inner">
          <div className="webu-header__finwave-topbar-left">
            {topBarLoginLabel ? (
              <SmartHref href={topBarLoginUrl} basePath={basePath} className="webu-header__finwave-login">
                <User className="webu-header__icon" strokeWidth={1.8} />
                <span>{topBarLoginLabel}</span>
              </SmartHref>
            ) : null}
            {socialLinks.map((item: HeaderSocialLink) => (
              <SmartHref
                key={`${item.label}-${item.url}`}
                href={item.url}
                basePath={basePath}
                className="webu-header__finwave-social-link"
                ariaLabel={item.label}
              >
                <SocialIcon icon={item.icon} className="webu-header__icon" />
              </SmartHref>
            ))}
          </div>
          <div className="webu-header__finwave-topbar-right">
            {topBarLocationText ? (
              <SmartHref href={topBarLocationUrl} basePath={basePath} className="webu-header__finwave-meta-link">
                <MapPin className="webu-header__icon" strokeWidth={1.8} />
                <span>{topBarLocationText}</span>
              </SmartHref>
            ) : null}
            {topBarLocationText && topBarEmailText ? <span className="webu-header__finwave-meta-separator" aria-hidden="true" /> : null}
            {topBarEmailText ? (
              <SmartHref href={topBarEmailUrl} basePath={basePath} className="webu-header__finwave-meta-link">
                <Mail className="webu-header__icon" strokeWidth={1.8} />
                <span>{topBarEmailText}</span>
              </SmartHref>
            ) : null}
          </div>
        </div>
      </div>

      <div className="webu-header__finwave-main">
        <div className="webu-header__finwave-main-inner">
          <SmartHref href={logoUrl} basePath={basePath} className="webu-header__logo webu-header__logo--header-3" ariaLabel={resolvedLogo}>
            {logoImageUrl ? (
              <img src={logoImageUrl} alt={resolvedLogo} className="webu-header__logo-img" />
            ) : (
              <>
                <span className="webu-header__finwave-mark" aria-hidden="true">
                  <span className="webu-header__finwave-mark-dot" />
                </span>
                <span className="webu-header__finwave-wordmark">{resolvedLogo}</span>
              </>
            )}
          </SmartHref>

          <nav className="webu-nav webu-nav--header-3-finwave" aria-label={navAriaLabel}>
            {displayMenu.map((item, index) => (
              <span key={item.slug ?? item.url} className="webu-nav__item-wrap webu-nav__item-wrap--header-3">
                <Link href={path(basePath, item.url)} className={`webu-nav__link webu-nav__link--header-3${index === 0 ? ' is-active' : ''}`}>
                  {item.label}
                </Link>
                {index < displayMenu.length - 1 ? <ChevronDown className="webu-header__icon webu-header__icon--nav" strokeWidth={1.7} /> : null}
              </span>
            ))}
          </nav>

          <div className="webu-header__finwave-actions">
            <SmartHref href={searchUrl} basePath={basePath} className="webu-header__finwave-search" ariaLabel={searchAriaLabel}>
              <Search className="webu-header__icon" strokeWidth={1.8} />
            </SmartHref>

            {(hotlineEyebrow || hotlineLabel) ? (
              <SmartHref href={hotlineUrl} basePath={basePath} className="webu-header__finwave-hotline">
                <span className="webu-header__finwave-hotline-icon">
                  <PhoneCall className="webu-header__icon" strokeWidth={1.8} />
                </span>
                <span className="webu-header__finwave-hotline-copy">
                  {hotlineEyebrow ? <span className="webu-header__finwave-hotline-eyebrow">{hotlineEyebrow}</span> : null}
                  {hotlineLabel ? <span className="webu-header__finwave-hotline-label">{hotlineLabel}</span> : null}
                </span>
              </SmartHref>
            ) : null}

            <WebuOffcanvasMenu
              side={menuDrawerSide}
              title={menuDrawerTitle ?? resolvedLogo}
              subtitle={menuDrawerSubtitle}
              items={drawerItems}
              footerLabel={menuDrawerFooterLabel}
              footerUrl={menuDrawerFooterUrl}
              basePath={basePath}
              trigger={(
                <button type="button" className="webu-header__finwave-menu-btn" aria-label={menuTriggerAriaLabel}>
                  <Header3MenuIcon className="webu-header__icon" />
                </button>
              )}
            />
          </div>
        </div>
      </div>
    </header>
  );
}
