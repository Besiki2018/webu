import { Link } from '@inertiajs/react';
import type { WebuFooterProps } from '../types';

type FooterColumn = {
  title: string;
  links: { label: string; url: string }[];
};

function path(basePath: string | undefined, p: string): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const pathname = p.startsWith('/') ? p : `/${p}`;
  return base ? `${base}${pathname}` : pathname;
}

function isExternalUrl(url: string): boolean {
  return /^https?:\/\//i.test(url);
}

function titleizeMenuKey(key: string): string {
  return key
    .replace(/[-_]+/g, ' ')
    .trim()
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function buildColumns(menus: WebuFooterProps['menus']): (FooterColumn & { menuKey: string })[] {
  return Object.entries(menus ?? {})
    .filter(([, links]) => Array.isArray(links) && links.length > 0)
    .map(([key, links]) => ({
      title: titleizeMenuKey(key),
      links,
      menuKey: key === 'social' ? 'socialLinks' : key === 'links' ? 'links' : key,
    }))
    .slice(0, 10);
}

function FooterMenuLink({
  item,
  basePath,
  scopePath,
}: {
  item: { label: string; url: string };
  basePath?: string;
  scopePath: string;
}) {
  const linkProps = {
    className: 'webu-footer__link',
    'data-webu-field-scope': scopePath,
    'data-webu-field': 'label',
    'data-webu-field-url': 'url',
  };
  if (isExternalUrl(item.url)) {
    return (
      <a href={item.url} {...linkProps} target="_blank" rel="noreferrer">
        {item.label}
      </a>
    );
  }

  return (
    <Link href={path(basePath, item.url)} {...linkProps}>
      {item.label}
    </Link>
  );
}

export function Footer1({
  logo = '',
  logoUrl = '/',
  menus = {},
  contactAddress,
  copyright,
  basePath,
  newsletterHeading = '',
  newsletterCopy = '',
  newsletterPlaceholder = 'Your email',
  newsletterButtonLabel = 'Subscribe',
  paymentsLabel = '',
  paymentsAriaLabel = 'Payment methods',
  paymentMethods = [],
  backgroundColor,
  textColor,
}: WebuFooterProps) {
  const columns = buildColumns(menus);
  const resolvedCopyright = copyright?.trim() || (logo ? `© ${new Date().getFullYear()} ${logo}` : `© ${new Date().getFullYear()}`);
  const style = { backgroundColor: backgroundColor || undefined, color: textColor || undefined };

  return (
    <footer className="webu-footer webu-footer--footer-1" style={style}>
      <div className="webu-footer__inner">
        <div className="webu-footer__grid">
          <div className="webu-footer__newsletter-block">
            {newsletterHeading ? <p className="webu-footer__heading" data-webu-field="newsletterHeading">{newsletterHeading}</p> : null}
            {newsletterCopy ? <p className="webu-footer__newsletter-copy" data-webu-field="newsletterCopy">{newsletterCopy}</p> : null}
            {contactAddress ? <p className="webu-footer__support-text" data-webu-field="contactAddress">{contactAddress}</p> : null}
            <form className="webu-footer__newsletter-form" onSubmit={(event) => event.preventDefault()}>
              <input
                type="email"
                className="webu-footer__newsletter-input"
                placeholder={newsletterPlaceholder}
                aria-label={newsletterPlaceholder}
                data-webu-field="newsletterPlaceholder"
              />
              <button type="submit" className="webu-footer__newsletter-button" data-webu-field="newsletterButtonLabel">
                {newsletterButtonLabel}
              </button>
            </form>
            {(paymentsLabel || paymentMethods.length > 0) ? (
            <div className="webu-footer__payments" aria-label={paymentsAriaLabel}>
              {paymentsLabel ? <span className="webu-footer__payments-label">{paymentsLabel}</span> : null}
              <div className="webu-footer__payment-list">
                {paymentMethods.map((badge) => (
                  <span
                    key={badge.label}
                    className={`webu-footer__payment-chip webu-footer__payment-chip--${badge.tone ?? 'default'}`}
                  >
                    {badge.label}
                  </span>
                ))}
              </div>
            </div>
            ) : null}
          </div>
          {columns.map((column) => (
            <nav key={column.title} className="webu-footer__nav" aria-label={column.title} data-webu-field={column.menuKey}>
              <p className="webu-footer__heading">{column.title.toUpperCase()}</p>
              <div className="webu-footer__menu">
                {column.links.map((item, i) => (
                  <FooterMenuLink key={`${column.title}-${item.url}-${item.label}`} item={item} basePath={basePath} scopePath={`${column.menuKey}.${i}`} />
                ))}
              </div>
            </nav>
          ))}
        </div>
        <div className="webu-footer__bottom">
          <span className="webu-footer__copyright" data-webu-field="copyright">{resolvedCopyright}</span>
        </div>
      </div>
    </footer>
  );
}
