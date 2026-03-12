import { Link } from '@inertiajs/react';
import { ArrowRight, Menu } from 'lucide-react';
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import type { WebuOffcanvasMenuProps } from '../types';

function path(basePath: string | undefined, p: string): string {
  if (!p) {
    return basePath ? `${basePath.replace(/\/$/, '')}/` : '/';
  }
  if (/^(https?:|mailto:|tel:)/.test(p)) {
    return p;
  }
  const base = (basePath ?? '').replace(/\/$/, '');
  const pathname = p.startsWith('/') ? p : `/${p}`;
  return base ? `${base}${pathname}` : pathname;
}

export function Drawer1({
  side = 'left',
  title = 'Shop navigation',
  subtitle = 'Reusable drawer for desktop hamburger and mobile navigation.',
  items = [],
  footerLabel,
  footerUrl = '/shop',
  basePath,
  open,
  defaultOpen,
  onOpenChange,
  trigger,
  triggerLabel = 'Open menu',
  triggerClassName,
  className,
}: WebuOffcanvasMenuProps) {
  const resolvedItems = items.length > 0 ? items : [
    { label: 'New arrivals', url: '/shop', description: 'Fresh seasonal edits' },
    { label: 'Outerwear', url: '/outerwear', description: 'Layering essentials' },
    { label: 'Contact', url: '/contact', description: 'Store support' },
  ];

  return (
    <Sheet open={open} defaultOpen={defaultOpen} onOpenChange={onOpenChange}>
      <SheetTrigger asChild>
        {trigger ?? (
          <button type="button" className={triggerClassName ?? 'webu-offcanvas-menu__trigger-button'} aria-label={triggerLabel} data-webu-field="trigger_label">
            <Menu className="webu-offcanvas-menu__trigger-icon" strokeWidth={1.8} />
            <span>{triggerLabel}</span>
          </button>
        )}
      </SheetTrigger>
      <SheetContent side={side} className={`webu-offcanvas-menu webu-offcanvas-menu--sheet ${className ?? ''}`.trim()}>
        <div className="webu-offcanvas-menu__header">
          <div className="webu-offcanvas-menu__heading">
            <span className="webu-offcanvas-menu__eyebrow">Navigation</span>
            <SheetTitle className="webu-offcanvas-menu__title" data-webu-field="title">{title}</SheetTitle>
            {subtitle ? <p className="webu-offcanvas-menu__subtitle" data-webu-field="subtitle">{subtitle}</p> : null}
          </div>
        </div>
        <div className="webu-offcanvas-menu__body">
          <nav className="webu-offcanvas-menu__nav" aria-label={title} data-webu-field="menu_items">
            {resolvedItems.map((item, i) => (
              <Link key={`${item.label}-${item.url}`} href={path(basePath, item.url)} className="webu-offcanvas-menu__link" data-webu-field-scope={`menu_items.${i}`} data-webu-field="label" data-webu-field-url="url">
                <span className="webu-offcanvas-menu__link-copy">
                  <span className="webu-offcanvas-menu__link-label">{item.label}</span>
                  {item.description ? <span className="webu-offcanvas-menu__link-description" data-webu-field="description">{item.description}</span> : null}
                </span>
                <ArrowRight className="webu-offcanvas-menu__link-icon" strokeWidth={1.7} />
              </Link>
            ))}
          </nav>
        </div>
        {footerLabel ? (
          <div className="webu-offcanvas-menu__footer">
            <Link href={path(basePath, footerUrl)} className="webu-offcanvas-menu__footer-link" data-webu-field="footerLabel" data-webu-field-url="footerUrl">{footerLabel}</Link>
          </div>
        ) : null}
      </SheetContent>
    </Sheet>
  );
}
