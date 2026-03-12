import type { WebuOrderSummaryProps } from '../types';

/** Data from CMS/cart */
export function Summary1({ lines, total }: WebuOrderSummaryProps) {
  return (
    <aside className="webu-order-summary webu-order-summary--summary-1">
      <div className="webu-order-summary__inner">
        <h3 className="webu-order-summary__title">Order summary</h3>
        <ul className="webu-order-summary__list">
          {lines.map((line, i) => (
            <li key={i} className="webu-order-summary__line">
              <span>{line.label}</span>
              <span>${line.amount.toFixed(2)}</span>
            </li>
          ))}
        </ul>
        <p className="webu-order-summary__total">Total: ${total.toFixed(2)}</p>
      </div>
    </aside>
  );
}
