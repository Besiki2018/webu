import { Link } from '@inertiajs/react';
import type { SectionInjectedProps } from './types';
import { path } from './types';
import { cn } from '@/lib/utils';

export interface FooterProps extends SectionInjectedProps {
  logo?: string;
  links?: { label: string; url: string }[];
  socials?: { label: string; url: string }[];
  contact?: string;
  policies?: { label: string; url: string }[];
  copyright?: string;
  className?: string;
}

const defaultLinks = [
  { label: 'Shop', url: '/shop' },
  { label: 'About', url: '/about' },
  { label: 'Contact', url: '/contact' },
];

export function Footer(props: FooterProps) {
  const { basePath, logo = 'Store', links = defaultLinks, socials = [], contact, policies = [], copyright, className } = props;
  const year = new Date().getFullYear();
  return (
    <footer className={cn('webu-footer', className)}>
      <div className="webu-footer__inner">
        <div className="grid gap-8 md:grid-cols-2 lg:grid-cols-4">
          <div>
            <Link href={path(basePath, '/')} className="font-semibold">{logo}</Link>
            {contact && <p className="mt-2 text-sm text-muted-foreground">{contact}</p>}
          </div>
          <div>
            <h4 className="font-medium mb-2">Links</h4>
            <ul className="space-y-1 text-sm text-muted-foreground">
              {links.map((link) => (
                <li key={link.url}><Link href={path(basePath, link.url)} className="hover:text-foreground">{link.label}</Link></li>
              ))}
            </ul>
          </div>
          {policies.length > 0 && (
            <div>
              <h4 className="font-medium mb-2">Policies</h4>
              <ul className="space-y-1 text-sm text-muted-foreground">
                {policies.map((link) => (
                  <li key={link.url}><Link href={path(basePath, link.url)} className="hover:text-foreground">{link.label}</Link></li>
                ))}
              </ul>
            </div>
          )}
          {socials.length > 0 && (
            <div>
              <h4 className="font-medium mb-2">Follow</h4>
              <ul className="space-y-1 text-sm text-muted-foreground">
                {socials.map((s) => (
                  <li key={s.url}><a href={s.url} target="_blank" rel="noopener noreferrer" className="hover:text-foreground">{s.label}</a></li>
                ))}
              </ul>
            </div>
          )}
        </div>
        <div className="mt-8 pt-8 border-t text-center text-sm text-muted-foreground">
          {copyright ?? `${logo} © ${year}. All rights reserved.`}
        </div>
      </div>
    </footer>
  );
}
