import { Link } from '@inertiajs/react';
import type { WebuOrdersProps } from '../types';

function path(basePath: string | undefined, url: string): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const p = url.startsWith('/') ? url : `/${url}`;
  return base ? `${base}${p}` : p;
}

/** Table – data from CMS */
export function Orders1({ title, orders, basePath }: WebuOrdersProps) {
  return (
    <section className="webu-orders webu-orders--orders-1">
      <div className="webu-orders__inner">
        {title && <h2 className="webu-orders__title">{title}</h2>}
        <div className="webu-orders__table-wrap">
          <table className="webu-orders__table">
            <thead>
              <tr>
                <th>Order</th>
                <th>Date</th>
                <th>Total</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {orders.map((o) => (
                <tr key={o.id}>
                  <td>
                    {o.url ? <Link href={path(basePath, o.url)}>{o.id}</Link> : o.id}
                  </td>
                  <td>{o.date}</td>
                  <td>${o.total.toFixed(2)}</td>
                  <td>{o.status}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </section>
  );
}
