import { Link } from '@inertiajs/react';
import type { SectionInjectedProps } from './types';
import { path } from './types';
import { useCartStore } from '@/ecommerce/store/cartStore';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export interface CheckoutProps extends SectionInjectedProps {
  title?: string;
  className?: string;
}

export function Checkout(props: CheckoutProps) {
  const { basePath, title = 'Checkout', className } = props;
  const { items, subtotal } = useCartStore();

  return (
    <section className={cn('webu-checkout', className)}>
      <h2 className="webu-checkout__title">{title}</h2>
      <form className="space-y-6">
        <div>
          <Label htmlFor="email">Email</Label>
          <Input id="email" type="email" placeholder="you@example.com" className="mt-1" />
        </div>
        <div>
          <Label htmlFor="address">Address</Label>
          <Input id="address" placeholder="Street, City, Country" className="mt-1" />
        </div>
        <div>
          <Label>Shipping</Label>
          <p className="text-sm text-muted-foreground mt-1">Standard shipping (placeholder)</p>
        </div>
        <div>
          <Label>Payment</Label>
          <p className="text-sm text-muted-foreground mt-1">Payment placeholder – integrate gateway later.</p>
        </div>
      </form>
      <div className="mt-8 border-t pt-6">
        <p className="text-lg font-semibold">Order total: {subtotal().toFixed(2)}</p>
        <Button size="lg" className="mt-4" disabled>Place order (demo)</Button>
      </div>
      <Button variant="outline" asChild className="mt-4">
        <Link href={path(basePath, '/cart')}>Back to cart</Link>
      </Button>
    </section>
  );
}
