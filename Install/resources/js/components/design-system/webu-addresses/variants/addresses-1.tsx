import type { WebuAddressesProps } from '../types';

/** Data from CMS */
export function Addresses1({ title, addresses }: WebuAddressesProps) {
  return (
    <section className="webu-addresses webu-addresses--addresses-1">
      <div className="webu-addresses__inner">
        {title && <h2 className="webu-addresses__title">{title}</h2>}
        <ul className="webu-addresses__list">
          {addresses.map((a) => (
            <li key={a.id} className="webu-addresses__item">
              {a.label && <strong>{a.label}</strong>}
              <p>{a.line1}{a.line2 ? `, ${a.line2}` : ''}{a.city ? `, ${a.city}` : ''}{a.country ? `, ${a.country}` : ''}</p>
              {a.isDefault && <span className="webu-addresses__default">Default</span>}
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
