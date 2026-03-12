import { Link } from '@inertiajs/react';
import type { WebuCartDrawerProps } from '../types';

/** Slide – data from CMS/cart */
export function Drawer1({ open, onClose, lines, total, basePath, checkoutUrl }: WebuCartDrawerProps) {
  const checkout = checkoutUrl ?? (basePath ? `${basePath.replace(/\/$/, '')}/checkout` : '/checkout');
  if (!open) return null;
  return (
    <>
      <div className="webu-cart-drawer__backdrop" onClick={onClose} aria-hidden />
      <aside className="webu-cart-drawer webu-cart-drawer--drawer-1" role="dialog" aria-label="Cart">
        <div className="webu-cart-drawer__header">
          <h2 className="webu-cart-drawer__title">Cart</h2>
          <button type="button" className="webu-cart-drawer__close" onClick={onClose} aria-label="Close">×</button>
        </div>
        <div className="webu-cart-drawer__body">
          {lines.length === 0 ? (
            <p className="webu-cart-drawer__empty">Your cart is empty.</p>
          ) : (
            <ul className="webu-cart-drawer__list">
              {lines.map((line) => (
                <li key={line.id} className="webu-cart-drawer__line">
                  {line.image && <img src={line.image} alt={line.name} className="webu-cart-drawer__line-img" />}
                  <span className="webu-cart-drawer__line-name">{line.name}</span>
                  <span className="webu-cart-drawer__line-qty">×{line.quantity}</span>
                  <span className="webu-cart-drawer__line-price">${(line.price * line.quantity).toFixed(2)}</span>
                </li>
              ))}
            </ul>
          )}
        </div>
        <div className="webu-cart-drawer__footer">
          <p className="webu-cart-drawer__total">Total: ${total.toFixed(2)}</p>
          <Link href={checkout} className="webu-cart-drawer__checkout">Checkout</Link>
        </div>
      </aside>
    </>
  );
}
