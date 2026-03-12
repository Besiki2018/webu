import type { WebuProductGalleryProps } from '../types';

/** Vertical thumbs – data from CMS */
export function Gallery1({ images, productName }: WebuProductGalleryProps) {
  if (!images?.length) return <div className="webu-product-gallery webu-product-gallery--gallery-1">No images</div>;
  const [main, ...thumbs] = images;
  return (
    <section className="webu-product-gallery webu-product-gallery--gallery-1">
      <div className="webu-product-gallery__inner">
        <div className="webu-product-gallery__main">
          <img src={main} alt={productName ?? 'Product'} className="webu-product-gallery__img" />
        </div>
        <div className="webu-product-gallery__thumbs">
          {thumbs.map((src, i) => (
            <button key={i} type="button" className="webu-product-gallery__thumb">
              <img src={src} alt="" />
            </button>
          ))}
        </div>
      </div>
    </section>
  );
}
