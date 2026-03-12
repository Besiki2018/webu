import type { WebuProductDetailsProps } from '../types';

/** Classic – data from CMS */
export function Details1({ title, price, compareAtPrice, description, variants, stock, sku }: WebuProductDetailsProps) {
  return (
    <div className="webu-product-details webu-product-details--details-1">
      <h1 className="webu-product-details__title">{title}</h1>
      <div className="webu-product-details__price-wrap">
        <span className="webu-product-details__price">${price.toFixed(2)}</span>
        {compareAtPrice != null && compareAtPrice > price && (
          <span className="webu-product-details__compare">${compareAtPrice.toFixed(2)}</span>
        )}
      </div>
      {description && <div className="webu-product-details__description" dangerouslySetInnerHTML={{ __html: description }} />}
      {variants?.length ? (
        <div className="webu-product-details__variants">
          {variants.map((v, i) => (
            <span key={i} className="webu-product-details__variant">{v.name}: {v.value}</span>
          ))}
        </div>
      ) : null}
      {stock != null && <p className="webu-product-details__stock">In stock: {stock}</p>}
      {sku && <p className="webu-product-details__sku">SKU: {sku}</p>}
    </div>
  );
}
