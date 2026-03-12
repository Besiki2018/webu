import { Link } from '@inertiajs/react';
import { Search, ShoppingCart, User } from 'lucide-react';
import type { SectionInjectedProps } from './types';
import { path } from './types';
import { useCartStore } from '@/ecommerce/store/cartStore';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type HeaderVariant = 'default' | 'minimal' | 'mega';

export interface HeaderProps extends SectionInjectedProps {
  logo?: string;
  logoUrl?: string;
  menuLinks?: { label: string; url: string }[];
  showSearch?: boolean;
  showCart?: boolean;
  showAccount?: boolean;
  variant?: HeaderVariant;
  className?: string;
}

const defaultMenuLinks = [
  { label: 'Home', url: '/' },
  { label: 'Shop', url: '/shop' },
  { label: 'Contact', url: '/contact' },
];

export function Header(props: HeaderProps) {
  const { basePath, logo = 'Store', logoUrl = '/', menuLinks = defaultMenuLinks, showSearch = true, showCart = true, showAccount = true, variant, className } = props;
  const totalItems = useCartStore((s) => s.totalItems());
  const variantClass = variant ? `webu-header--${variant}` : null;
  return (
    <header className={cn('webu-header', variantClass, className)}>
      <div className="webu-header__inner">
        <Link href={path(basePath, logoUrl)} className="text-lg font-semibold">{logo}</Link>
        <nav className="hidden md:flex items-center gap-6">
          {menuLinks.map((link: { label: string; url: string }) => (
            <Link key={link.url} href={path(basePath, link.url)} className="text-sm text-muted-foreground hover:text-foreground">{link.label}</Link>
          ))}
        </nav>
        <div className="flex items-center gap-2">
          {showSearch && <Button variant="ghost" size="icon"><Search className="h-4 w-4" /></Button>}
          {showCart && (
            <Link href={path(basePath, '/cart')}>
              <Button variant="ghost" size="icon" className="relative">
                <ShoppingCart className="h-4 w-4" />
                {totalItems > 0 && <span className="absolute -top-1 -right-1 h-4 w-4 rounded-full bg-primary text-primary-foreground text-xs flex items-center justify-center">{totalItems}</span>}
              </Button>
            </Link>
          )}
          {showAccount && <Button variant="ghost" size="icon"><User className="h-4 w-4" /></Button>}
        </div>
      </div>
    </header>
  );
}
