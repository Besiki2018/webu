import type { SitePlan } from '@/ecommerce/schema';

export const clothingStorePlan: SitePlan = {
  meta: { name: 'Modern Clothing Store', language: 'en', currency: 'GEL' },
  theme: {
    mode: 'dark',
    primaryColor: '#1a1a1a',
    secondaryColor: '#6366f1',
    fontFamily: 'system-ui, sans-serif',
    radius: 'md',
    buttonStyle: 'solid',
  },
  pages: [
    {
      id: 'home',
      route: '/',
      title: 'Home',
      sections: [
        { id: 'h1', type: 'Header', props: { logo: 'Clothing Co', logoUrl: '/', menuLinks: [{ label: 'Home', url: '/' }, { label: 'Shop', url: '/shop' }, { label: 'Contact', url: '/contact' }] } },
        { id: 'hero1', type: 'HeroBanner', props: { title: 'New Season Collection', subtitle: 'Discover the latest trends.', ctaText: 'Shop Now', ctaUrl: '/shop' } },
        { id: 'cat1', type: 'CategoryGrid', props: { title: 'Shop by Category', columns: 3 } },
        { id: 'feat1', type: 'ProductGrid', props: { title: 'Featured Products', limit: 4, columns: 4 } },
        { id: 'ft1', type: 'Footer', props: { logo: 'Clothing Co', copyright: 'Clothing Co. All rights reserved.' } },
      ],
    },
    {
      id: 'shop',
      route: '/shop',
      title: 'Shop',
      sections: [
        { id: 'h2', type: 'Header', props: { logo: 'Clothing Co', menuLinks: [{ label: 'Home', url: '/' }, { label: 'Shop', url: '/shop' }, { label: 'Contact', url: '/contact' }] } },
        { id: 'pg1', type: 'ProductGrid', props: { title: 'All Products', limit: 12, columns: 4 } },
        { id: 'ft2', type: 'Footer', props: { logo: 'Clothing Co' } },
      ],
    },
    {
      id: 'product',
      route: '/product/:id',
      title: 'Product',
      sections: [
        { id: 'h3', type: 'Header', props: { logo: 'Clothing Co', menuLinks: [{ label: 'Home', url: '/' }, { label: 'Shop', url: '/shop' }] } },
        { id: 'pd1', type: 'ProductDetails', props: {} },
        { id: 'ft3', type: 'Footer', props: { logo: 'Clothing Co' } },
      ],
    },
    {
      id: 'cart',
      route: '/cart',
      title: 'Cart',
      sections: [
        { id: 'h4', type: 'Header', props: { logo: 'Clothing Co' } },
        { id: 'cart1', type: 'Cart', props: { title: 'Your Cart', checkoutCta: 'Proceed to Checkout' } },
        { id: 'ft4', type: 'Footer', props: { logo: 'Clothing Co' } },
      ],
    },
    {
      id: 'checkout',
      route: '/checkout',
      title: 'Checkout',
      sections: [
        { id: 'h5', type: 'Header', props: { logo: 'Clothing Co' } },
        { id: 'co1', type: 'Checkout', props: { title: 'Checkout' } },
        { id: 'ft5', type: 'Footer', props: { logo: 'Clothing Co' } },
      ],
    },
    {
      id: 'contact',
      route: '/contact',
      title: 'Contact',
      sections: [
        { id: 'h6', type: 'Header', props: { logo: 'Clothing Co' } },
        { id: 'contact1', type: 'FAQ', props: { title: 'Contact us' } },
        { id: 'ft6', type: 'Footer', props: { logo: 'Clothing Co' } },
      ],
    },
  ],
};
