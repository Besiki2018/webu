/**
 * Webu Component Map – 36 components and variants.
 * All data from Webu CMS; no hardcoded content.
 */

export const WEBU_COMPONENT_MAP = [
  { component: 'webu-header', variants: ['header-1', 'header-2', 'header-3', 'header-4', 'header-5', 'header-6'], bindings: ['navigation', 'logo', 'search', 'cart', 'account', 'wishlist'] },
  { component: 'webu-hero', variants: ['hero-1', 'hero-2', 'hero-3', 'hero-4', 'hero-5', 'hero-6', 'hero-7'], bindings: ['title', 'subtitle', 'button', 'hero_image', 'overlay_image', 'stats'] },
  { component: 'webu-product-card', variants: ['product-card-1', 'product-card-2', 'product-card-3', 'product-card-4', 'product-card-5'], bindings: ['product.image', 'product.name', 'product.price', 'product.rating', 'product.discount'] },
  { component: 'webu-product-grid', variants: ['grid-1', 'grid-2', 'grid-3', 'grid-4'], bindings: ['pagination', 'filters', 'sorting'] },
  { component: 'webu-category-card', variants: ['category-1', 'category-2', 'category-3', 'category-4'], bindings: ['category.image', 'category.name', 'category.products_count'] },
  { component: 'webu-category-grid', variants: ['grid-1', 'grid-2', 'grid-3'], bindings: [] },
  { component: 'webu-banner', variants: ['banner-1', 'banner-2', 'banner-3', 'banner-4'], bindings: ['banner.image', 'banner.title', 'banner.cta'] },
  { component: 'webu-cta', variants: ['cta-1', 'cta-2', 'cta-3', 'cta-4'], bindings: [] },
  { component: 'webu-features', variants: ['features-1', 'features-2', 'features-3', 'features-4'], bindings: ['icon', 'title', 'description'] },
  { component: 'webu-testimonials', variants: ['testimonials-1', 'testimonials-2', 'testimonials-3'], bindings: ['user_name', 'avatar', 'rating', 'text'] },
  { component: 'webu-newsletter', variants: ['newsletter-1', 'newsletter-2', 'newsletter-3'], bindings: ['email', 'submit'] },
  { component: 'webu-blog-card', variants: ['blog-card-1', 'blog-card-2', 'blog-card-3'], bindings: [] },
  { component: 'webu-blog-grid', variants: ['blog-grid-1', 'blog-grid-2', 'blog-grid-3'], bindings: [] },
  { component: 'webu-product-gallery', variants: ['gallery-1', 'gallery-2', 'gallery-3'], bindings: ['product.images'] },
  { component: 'webu-product-details', variants: ['details-1', 'details-2', 'details-3'], bindings: ['title', 'price', 'description', 'variants', 'stock'] },
  { component: 'webu-product-buy', variants: ['buy-1', 'buy-2', 'buy-3'], bindings: [] },
  { component: 'webu-product-filters', variants: ['filters-1', 'filters-2', 'filters-3'], bindings: [] },
  { component: 'webu-breadcrumb', variants: ['breadcrumb-1', 'breadcrumb-2'], bindings: [] },
  { component: 'webu-pagination', variants: ['pagination-1', 'pagination-2', 'pagination-3'], bindings: [] },
  { component: 'webu-cart-drawer', variants: ['drawer-1', 'drawer-2'], bindings: [] },
  { component: 'webu-offcanvas-menu', variants: ['drawer-1'], bindings: ['navigation'] },
  { component: 'webu-checkout-form', variants: ['checkout-1', 'checkout-2', 'checkout-3'], bindings: [] },
  { component: 'webu-order-summary', variants: ['summary-1', 'summary-2'], bindings: [] },
  { component: 'webu-login', variants: ['login-1', 'login-2'], bindings: [] },
  { component: 'webu-register', variants: ['register-1', 'register-2'], bindings: [] },
  { component: 'webu-dashboard', variants: ['dashboard-1', 'dashboard-2'], bindings: [] },
  { component: 'webu-orders', variants: ['orders-1', 'orders-2'], bindings: [] },
  { component: 'webu-addresses', variants: ['addresses-1', 'addresses-2'], bindings: [] },
  { component: 'webu-wishlist', variants: ['wishlist-1', 'wishlist-2'], bindings: [] },
  { component: 'webu-faq', variants: ['faq-1', 'faq-2'], bindings: [] },
  { component: 'webu-contact', variants: ['contact-1', 'contact-2', 'contact-3'], bindings: [] },
  { component: 'webu-map', variants: ['map-1', 'map-2'], bindings: [] },
  { component: 'webu-stats', variants: ['stats-1', 'stats-2', 'stats-3'], bindings: [] },
  { component: 'webu-team', variants: ['team-1', 'team-2'], bindings: [] },
  { component: 'webu-footer', variants: ['footer-1', 'footer-2', 'footer-3', 'footer-4'], bindings: [] },
  { component: 'webu-announcement', variants: ['announcement-1', 'announcement-2', 'announcement-3'], bindings: [] },
] as const;

export type WebuComponentKey = (typeof WEBU_COMPONENT_MAP)[number]['component'];
