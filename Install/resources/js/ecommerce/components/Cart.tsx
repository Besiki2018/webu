import { Link } from '@inertiajs/react';
import type { SectionInjectedProps } from './types';
import { path } from './types';
import { useCartStore, type CartItem } from '@/ecommerce/store/cartStore';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export interface CartProps extends SectionInjectedProps {
  title?: string;
  emptyMessage?: string;
  checkoutCta?: string;
  className?: string;
}

export function Cart(props: CartProps) {
  const { basePath, title = 'Your Cart', emptyMessage = 'Your cart is empty.', checkoutCta = 'Proceed to Checkout', className } = props;
  const { items, updateQty, remove, subtotal, clear } = useCartStore();

  if (items.length === 0) {
    return (
      <section className={cn('webu-cart', className)}>
        <h2 className="webu-cart__title">{title}</h2>
        <p className="webu-cart__empty text-muted-foreground">{emptyMessage}</p>
        <Button asChild className="mt-4">
          <Link href={path(basePath, '/shop')}>Continue Shopping</Link>
        </Button>
      </section>
    );
  }

  return (
    <section className={cn('webu-cart', className)}>
      <h2 className="webu-cart__title">{title}</h2>
      <div className="webu-cart__items">
        {items.map((item: CartItem) => (
          <div key={item.productId} className="webu-cart__row">
            <img src={item.image} alt={item.title} className="h-16 w-16 rounded object-cover" />
            <div className="flex-1 min-w-0">
              <p className="font-medium truncate">{item.title}</p>
              <p className="text-sm text-muted-foreground">{item.price.toFixed(2)} x {item.quantity}</p>
            </div>
            <div className="flex items-center gap-2">
              <Button variant="outline" size="sm" onClick={() => updateQty(item.productId, item.quantity - 1)}>-</Button>
              <span className="w-8 text-center text-sm">{item.quantity}</span>
              <Button variant="outline" size="sm" onClick={() => updateQty(item.productId, item.quantity + 1)}>+</Button>
              <Button variant="ghost" size="sm" onClick={() => remove(item.productId)}>Remove</Button>
            </div>
            <p className="font-medium">{(item.price * item.quantity).toFixed(2)}</p>
          </div>
        ))}
      </div>
      <div className="webu-cart__footer">
        <p className="webu-cart__subtotal">Subtotal: {subtotal().toFixed(2)}</p>
        <p className="text-sm text-muted-foreground">Shipping calculated at checkout.</p>
        <Button asChild size="lg">
          <Link href={path(basePath, '/checkout')}>{checkoutCta}</Link>
        </Button>
      </div>
    </section>
  );
}
