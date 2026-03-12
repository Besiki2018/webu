import type { WebuProductBuyProps } from '../types';

/** Classic – data from CMS */
export function Buy1({ productId, price, addToCartUrl, buyNowUrl, quantity = 1 }: WebuProductBuyProps) {
  return (
    <div className="webu-product-buy webu-product-buy--buy-1">
      <div className="webu-product-buy__price">${price.toFixed(2)}</div>
      <div className="webu-product-buy__quantity">
        <label>Qty</label>
        <input type="number" min={1} defaultValue={quantity} aria-label="Quantity" />
      </div>
      {addToCartUrl && (
        <a href={addToCartUrl} className="webu-product-buy__add">Add to cart</a>
      )}
      {buyNowUrl && (
        <a href={buyNowUrl} className="webu-product-buy__buy">Buy now</a>
      )}
    </div>
  );
}
