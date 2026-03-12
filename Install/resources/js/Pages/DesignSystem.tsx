import { Head, Link } from '@inertiajs/react';
import { Header } from '@/ecommerce/components/Header';
import { HeroBanner } from '@/ecommerce/components/HeroBanner';
import { CategoryGrid } from '@/ecommerce/components/CategoryGrid';
import { ProductCard } from '@/ecommerce/components/ProductCard';
import { ProductGrid } from '@/ecommerce/components/ProductGrid';
import { Footer } from '@/ecommerce/components/Footer';
import { Cart } from '@/ecommerce/components/Cart';
import { Checkout } from '@/ecommerce/components/Checkout';
import { PlaceholderSection } from '@/ecommerce/components/PlaceholderSection';
import { useEffect } from 'react';
import { WebuHeader } from '@/components/design-system/webu-header';
import { WebuHero } from '@/components/design-system/webu-hero';
import { WebuFooter } from '@/components/design-system/webu-footer';
import { WebuProductGrid } from '@/components/design-system/webu-product-grid';
import { WebuBanner } from '@/components/design-system/webu-banner';
import { WebuNewsletter } from '@/components/design-system/webu-newsletter';
import { WebuCategoryCard } from '@/components/design-system/webu-category-card';
import { WebuCta } from '@/components/design-system/webu-cta';
import { WebuFeatures } from '@/components/design-system/webu-features';
import { WebuTestimonials } from '@/components/design-system/webu-testimonials';
import { WebuAnnouncement } from '@/components/design-system/webu-announcement';
import { WebuFaq } from '@/components/design-system/webu-faq';
import { WebuContact } from '@/components/design-system/webu-contact';
import { WebuStats } from '@/components/design-system/webu-stats';
import { WebuTeam } from '@/components/design-system/webu-team';
import { WebuBreadcrumb } from '@/components/design-system/webu-breadcrumb';
import { WebuPagination } from '@/components/design-system/webu-pagination';
import { WebuBlogGrid } from '@/components/design-system/webu-blog-grid';
import { WebuProductGallery } from '@/components/design-system/webu-product-gallery';
import { WebuProductDetails } from '@/components/design-system/webu-product-details';
import { WebuProductBuy } from '@/components/design-system/webu-product-buy';
import { WebuProductFilters } from '@/components/design-system/webu-product-filters';
import { WebuCheckoutForm } from '@/components/design-system/webu-checkout-form';
import { WebuOrderSummary } from '@/components/design-system/webu-order-summary';
import { WebuLogin } from '@/components/design-system/webu-login';
import { WebuRegister } from '@/components/design-system/webu-register';
import { WebuDashboard } from '@/components/design-system/webu-dashboard';
import { WebuOrders } from '@/components/design-system/webu-orders';
import { WebuAddresses } from '@/components/design-system/webu-addresses';
import { WebuWishlist } from '@/components/design-system/webu-wishlist';
import { WebuMap } from '@/components/design-system/webu-map';
import { LayoutRenderer } from '@/renderer/LayoutRenderer';
import { setCmsData, cmsResolver } from '@/services/cmsResolver';
import { DEFAULT_LAYOUT } from '@/types/layoutSchema';

const BASE = '';

interface CmsData {
  siteSettings?: { logo_text?: string; brand?: string; logo_url?: string | null; cta_label?: string | null; cta_url?: string | null };
  navigation?: { label: string; url: string; slug?: string }[];
  products?: { id?: string | number; name: string; slug: string; price: string; old_price?: string | null; image_url?: string | null; url: string }[];
  categories?: { name: string; slug: string }[];
  footer?: { menus?: Record<string, { label: string; url: string }[]>; contactAddress?: string };
  testimonials?: { user_name: string; avatar?: string; rating?: number; text: string }[];
  features?: { icon?: string; title: string; description?: string }[];
  faq?: { question: string; answer: string }[];
  blogPosts?: { id: string; title: string; excerpt?: string; image?: string; url?: string; date?: string; author?: string }[];
  announcement?: { text: string; linkUrl?: string; linkLabel?: string; countdownEnd?: string } | null;
  stats?: { label: string; value: string }[];
  team?: { name: string; role?: string; avatar?: string }[];
}

interface DesignSystemPageProps {
  demoMode?: boolean;
  cms?: CmsData;
}

const DEMO_PRODUCTS = [
  { id: 'demo-1', slug: 'demo-product-1', title: 'Demo Product One', price: 49.99, currency: 'GEL', image: 'https://via.placeholder.com/400', badge: 'New', rating: 4.5 },
  { id: 'demo-2', slug: 'demo-product-2', title: 'Demo Product Two', price: 29.99, currency: 'GEL', image: 'https://via.placeholder.com/400', badge: 'Sale', rating: 4 },
  { id: 'demo-3', slug: 'demo-product-3', title: 'Demo Product Three', price: 79.99, currency: 'GEL', image: 'https://via.placeholder.com/400', rating: 5 },
  { id: 'demo-4', slug: 'demo-product-4', title: 'Demo Product Four', price: 39.99, currency: 'GEL', image: 'https://via.placeholder.com/400', badge: 'Best', rating: 4.8 },
  { id: 'demo-5', slug: 'demo-product-5', title: 'Demo Product Five', price: 59.99, currency: 'GEL', image: 'https://via.placeholder.com/400', rating: 4.2 },
  { id: 'demo-6', slug: 'demo-product-6', title: 'Demo Product Six', price: 19.99, currency: 'GEL', image: 'https://via.placeholder.com/400', badge: 'Sale', rating: 5 },
];

const DEMO_CATEGORIES = [
  { id: 'cat-1', title: 'Electronics', image: 'https://via.placeholder.com/400x300', link: '/shop?cat=electronics' },
  { id: 'cat-2', title: 'Fashion', image: 'https://via.placeholder.com/400x300', link: '/shop?cat=fashion' },
  { id: 'cat-3', title: 'Home', image: 'https://via.placeholder.com/400x300', link: '/shop?cat=home' },
];

const defaultProducts = [
  { id: '1', name: 'Demo Product 1', slug: 'demo-1', price: '49 GEL', old_price: '59 GEL', image_url: '/demo/hero/hero-1.svg', url: '/shop/demo-1' },
  { id: '2', name: 'Demo Product 2', slug: 'demo-2', price: '29 GEL', image_url: '/demo/hero/hero-2.svg', url: '/shop/demo-2' },
  { id: '3', name: 'Demo Product 3', slug: 'demo-3', price: '79 GEL', image_url: '/demo/hero/hero-3.svg', url: '/shop/demo-3' },
];
const defaultCategories = [
  { name: 'New In', slug: 'new-in', image_url: '/demo/hero/hero-1.svg' },
  { name: 'Top Picks', slug: 'top-picks', image_url: '/demo/hero/hero-2.svg' },
  { name: 'Sale', slug: 'sale', image_url: '/demo/hero/hero-3.svg' },
];

const fallbackFeatures = [
  { title: 'Free shipping', description: 'On orders over $50' },
  { title: 'Secure payment', description: '100% protected' },
  { title: 'Easy returns', description: '30-day policy' },
];
const fallbackTestimonials = [
  { user_name: 'Alex', rating: 5, text: 'Great quality and fast delivery.' },
  { user_name: 'Jordan', rating: 5, text: 'Best experience. Will order again.' },
];
const fallbackFaq = [
  { question: 'How do I return?', answer: 'Contact support with your order number.' },
  { question: 'What payment methods?', answer: 'We accept major cards and PayPal.' },
];
const fallbackStats = [
  { label: 'Happy customers', value: '10k+' },
  { label: 'Products', value: '500+' },
  { label: 'Years', value: '5+' },
];
const fallbackTeam = [
  { name: 'Jane Doe', role: 'Founder' },
  { name: 'John Smith', role: 'Lead Developer' },
];
const fallbackBlogPosts = [
  { id: '1', title: 'Welcome to our blog', excerpt: 'First post about quality.', url: '/blog/1', date: '2024-01-15', author: 'Admin' },
  { id: '2', title: 'New collection tips', excerpt: 'How to choose the right pieces.', url: '/blog/2', date: '2024-01-10', author: 'Admin' },
];

export default function DesignSystemPage({ demoMode = true, cms }: DesignSystemPageProps) {
  const siteSettings = cms?.siteSettings ?? { logo_text: 'Webu Store', brand: 'Webu Store', logo_url: null, cta_label: null, cta_url: null };
  const nav = cms?.navigation ?? [{ label: 'Home', url: '/', slug: 'home' }, { label: 'Shop', url: '/shop', slug: 'shop' }, { label: 'Contact', url: '/contact', slug: 'contact' }];
  const products = cms?.products ?? defaultProducts;
  const categories = cms?.categories ?? defaultCategories;
  const footerMenus = cms?.footer?.menus ?? { footer: [{ label: 'Shop', url: '/shop' }, { label: 'About', url: '/about' }, { label: 'Contact', url: '/contact' }] };
  const contactAddress = cms?.footer?.contactAddress ?? '';

  useEffect(() => {
    setCmsData({
      siteSettings: {
        logo_text: siteSettings.logo_text ?? '',
        brand: siteSettings.brand ?? '',
        logo_url: siteSettings.logo_url ?? null,
        cta_label: siteSettings.cta_label ?? null,
        cta_url: siteSettings.cta_url ?? null,
        locale: 'en',
      },
      navigation: nav,
      products,
      categories,
      footer: { menus: footerMenus, contactAddress },
      testimonials: cms?.testimonials,
      features: cms?.features,
      faq: cms?.faq,
      blogPosts: cms?.blogPosts,
      announcement: cms?.announcement ?? undefined,
      stats: cms?.stats,
      team: cms?.team,
    });
    return () => setCmsData(null);
  }, []);

  return (
    <div className="webu-template design-playground">
      <Head title="Webu Component Playground" />
      <h1 className="design-playground__title">Webu Component Playground</h1>
      <p className="design-playground__subtitle">
        Inspect and refine component styling. Edit <code>resources/css/design-system/components.css</code> and <code>tokens.css</code> to see changes here.{' '}
        <Link href="/ai-layout-playground" className="text-primary underline">Build with AI</Link>
        {cms && ' · Using CMS/demo data from backend.'}
      </p>

      <nav className="design-playground__nav" aria-label="Jump to component">
        <span className="design-playground__nav-label">ნავიგაცია — გადადი კომპონენტზე:</span>
        <div className="design-playground__nav-links">
          <a href="#webu-announcement">Announcement</a>
          <a href="#webu-headers">Headers</a>
          <a href="#webu-heroes">Heroes</a>
          <a href="#webu-product-grid">Product grid</a>
          <a href="#webu-cta">CTA</a>
          <a href="#webu-features">Features</a>
          <a href="#webu-testimonials">Testimonials</a>
          <a href="#webu-banners">Banners</a>
          <a href="#webu-faq">FAQ</a>
          <a href="#webu-newsletter">Newsletter</a>
          <a href="#webu-category-cards">Category cards</a>
          <a href="#webu-layout-renderer">Layout renderer</a>
          <a href="#webu-footers">Footers</a>
          <a href="#webu-contact-stats-team">Contact, Stats, Team</a>
          <a href="#webu-breadcrumb-pagination">Breadcrumb, Pagination</a>
          <a href="#webu-blog-grid">Blog grid</a>
          <a href="#webu-product-gallery">Product gallery / Details / Buy / Filters</a>
          <a href="#webu-login-register">Login, Register, Dashboard</a>
          <a href="#webu-orders-addresses">Orders, Addresses, Wishlist</a>
          <a href="#webu-map-checkout">Map, Checkout, Order summary</a>
        </div>
      </nav>

      <section className="design-playground__block">
        <h2 className="design-playground__block-title">Design system — variant-based (webu-* + CMS data)</h2>
        <div id="webu-announcement" className="design-playground__section">
          <h3 className="design-playground__heading">Announcement</h3>
          <WebuAnnouncement text="Free shipping on orders over $50" linkUrl="/shop" linkLabel="Shop now" basePath={BASE} />
        </div>
        <div id="webu-headers" className="design-playground__section">
          <h3 className="design-playground__heading">Headers (all variants)</h3>
          <WebuHeader variant="header-1" logo={siteSettings.logo_text} menu={nav} ctaLabel={siteSettings.cta_label ?? undefined} ctaUrl={siteSettings.cta_url ?? undefined} basePath={BASE} />
          <WebuHeader variant="header-2" logo={siteSettings.logo_text} menu={nav} basePath={BASE} />
          <WebuHeader
            variant="header-3"
            logo="Finwave"
            menu={[
              { label: 'Home', url: '/' },
              { label: 'Service', url: '/service' },
              { label: 'Pages', url: '/pages' },
              { label: 'Elements', url: '/elements' },
              { label: 'Blog', url: '/blog' },
              { label: 'Contact', url: '/contact' },
            ]}
            topBarLoginLabel="Log In"
            topBarLoginUrl="/account/login"
            topBarSocialLinks={[
              { label: 'Facebook', url: 'https://facebook.com', icon: 'facebook' },
              { label: 'X', url: 'https://x.com', icon: 'x' },
              { label: 'Instagram', url: 'https://instagram.com', icon: 'instagram' },
              { label: 'Pinterest', url: 'https://pinterest.com', icon: 'pinterest' },
            ]}
            topBarLocationText="Location: 57 Park Ave, New York"
            topBarLocationUrl="/contact"
            topBarEmailText="Mail: info@gmail.com"
            topBarEmailUrl="mailto:info@gmail.com"
            hotlineEyebrow="Hotline"
            hotlineLabel="+123-7767-8989"
            hotlineUrl="tel:+12377678989"
            searchUrl="/search"
            menuDrawerSide="right"
            basePath={BASE}
          />
          <WebuHeader
            variant="header-4"
            logo="machic®"
            menu={[
              { label: 'Home', url: '/' },
              { label: 'Shop', url: '/shop' },
              { label: 'Cell Phones', url: '/cell-phones' },
              { label: 'Headphones', url: '/headphones' },
              { label: 'Blog', url: '/blog' },
              { label: 'Contact', url: '/contact' },
            ]}
            utilityLinks={[
              { label: 'About Us', url: '/about' },
              { label: 'My account', url: '/account' },
              { label: 'Featured Products', url: '/shop' },
              { label: 'Wishlist', url: '/wishlist' },
            ]}
            topBarRightTracking="Order Tracking"
            topBarRightLang="English"
            topBarRightCurrency="USD"
            searchPlaceholder="Search your favorite product..."
            searchCategoryLabel="All"
            searchButtonLabel="Search"
            departmentLabel="All Departments"
            promoEyebrow="Only this weekend"
            promoLabel="Super Discount"
            wishlistCount={16}
            cartCount={0}
            cartTotal="$0.00"
            basePath={BASE}
          />
          <WebuHeader variant="header-5" logo={siteSettings.logo_text} menu={nav} basePath={BASE} />
          <WebuHeader variant="header-6" logo="Clotya®" menu={nav} basePath={BASE} />
        </div>
        <div id="webu-heroes" className="design-playground__section">
          <h3 className="design-playground__heading">Heroes (all variants)</h3>
          <WebuHero variant="hero-1" headline="Welcome to the store" subheading="Discover quality products." ctaLabel="Shop now" ctaUrl="/shop" imageUrl="/demo/hero/hero-1.svg" basePath={BASE} />
          <WebuHero variant="hero-2" headline="Centered hero" subheading="Minimal layout." ctaLabel="Learn more" ctaUrl="/about" imageUrl="/demo/hero/hero-2.svg" basePath={BASE} />
          <WebuHero variant="hero-3" headline="Split hero" subheading="Content and image side by side." ctaLabel="Get started" ctaUrl="/shop" imageUrl="/demo/hero/hero-3.svg" basePath={BASE} />
          <WebuHero variant="hero-4" headline="Video hero" subheading="Background video." ctaLabel="Watch" ctaUrl="/about" imageUrl="/demo/hero/hero-1.svg" basePath={BASE} />
          <WebuHero variant="hero-5" headline="Slider hero" subheading="Slide content." ctaLabel="Shop" ctaUrl="/shop" imageUrl="/demo/hero/hero-2.svg" basePath={BASE} />
          <WebuHero variant="hero-6" headline="Background image hero" subheading="Full bleed." ctaLabel="Explore" ctaUrl="/shop" imageUrl="/demo/hero/hero-3.svg" basePath={BASE} />
          <WebuHero
            variant="hero-7"
            eyebrow="Exclusive Finance Apps"
            headline={'Financial Consult\nThat Leads you\nto Your Goals'}
            subheading="Finance can deliver newsitescover a moving experience like no other at Outgrid beyond merely business investmenr transporting items of manual tracking spreadWego shee."
            ctaLabel="Take Our Services"
            ctaUrl="/contact"
            imageUrl="https://www.radiustheme.com/demo/wordpress/themes/finwave/wp-content/uploads/2025/07/home11-herobanner.webp"
            overlayImageUrl="https://www.radiustheme.com/demo/wordpress/themes/finwave/wp-content/uploads/2025/07/total-revenue-last-manth.webp"
            statValue="15"
            statUnit="k+"
            statLabel="Active Users"
            statAvatars={[
              { url: '/demo/people/person-1.svg', alt: 'User 1' },
              { url: '/demo/people/person-2.svg', alt: 'User 2' },
              { url: '/demo/people/person-3.svg', alt: 'User 3' },
            ]}
            basePath={BASE}
          />
        </div>
        <div id="webu-product-grid" className="design-playground__section">
          <h3 className="design-playground__heading">Product grid (CMS/demo products)</h3>
          <WebuProductGrid title="Featured products" products={products} variant="classic" basePath={BASE} />
        </div>
        <div className="design-playground__section">
          <h3 className="design-playground__heading">CTA (webu-cta)</h3>
          <WebuCta variant="cta-1" title="Get started today" subtitle="Join thousands of happy customers." buttonLabel="Learn more" buttonUrl="/about" basePath={BASE} />
        </div>
        <div id="webu-features" className="design-playground__section">
          <h3 className="design-playground__heading">Features (CMS)</h3>
          <WebuFeatures variant="features-1" title="Why choose us" items={cmsResolver.getFeatures().length ? cmsResolver.getFeatures() : fallbackFeatures} basePath={BASE} />
        </div>
        <div id="webu-testimonials" className="design-playground__section">
          <h3 className="design-playground__heading">Testimonials (CMS)</h3>
          <WebuTestimonials variant="testimonials-1" title="What people say" items={cmsResolver.getTestimonials().length ? cmsResolver.getTestimonials() : fallbackTestimonials} basePath={BASE} />
        </div>
        <div id="webu-banners" className="design-playground__section">
          <h3 className="design-playground__heading">Banners / CTA (all variants)</h3>
          <WebuBanner variant="banner-1" title="Limited offer" subtitle="Free shipping on orders over 100 GEL." ctaLabel="Shop now" ctaUrl="/shop" basePath={BASE} />
          <WebuBanner variant="banner-2" title="New collection" subtitle="Discover the latest trends." ctaLabel="View collection" ctaUrl="/shop" backgroundImage="/demo/hero/hero-1.svg" basePath={BASE} />
        </div>
        <div id="webu-faq" className="design-playground__section">
          <h3 className="design-playground__heading">FAQ (CMS)</h3>
          <WebuFaq variant="faq-1" title="FAQ" items={cmsResolver.getFaq()} basePath={BASE} />
        </div>
        <div id="webu-newsletter" className="design-playground__section">
          <h3 className="design-playground__heading">Newsletter</h3>
          <WebuNewsletter variant="newsletter-1" title="Stay updated" text="Subscribe for offers and news." basePath={BASE} />
        </div>
        <div id="webu-category-cards" className="design-playground__section">
          <h3 className="design-playground__heading">Category cards</h3>
          <div className="webu-category-grid__inner">
            {categories.map((cat) => (
              <WebuCategoryCard key={cat.slug} category={cat} basePath={BASE} />
            ))}
          </div>
        </div>
        <div id="webu-layout-renderer" className="design-playground__section">
          <h3 className="design-playground__heading">Layout renderer (AI layout JSON)</h3>
          <LayoutRenderer layout={DEFAULT_LAYOUT} />
        </div>
        <div id="webu-footers" className="design-playground__section">
          <h3 className="design-playground__heading">Footers (all variants)</h3>
          <WebuFooter variant="footer-1" logo={siteSettings.logo_text} menus={footerMenus} contactAddress={contactAddress} basePath={BASE} />
          <WebuFooter variant="footer-2" logo={siteSettings.logo_text} menus={footerMenus} basePath={BASE} />
          <WebuFooter variant="footer-3" logo={siteSettings.logo_text} menus={footerMenus} contactAddress={contactAddress} basePath={BASE} />
          <WebuFooter variant="footer-4" logo={siteSettings.logo_text} menus={footerMenus} contactAddress={contactAddress} basePath={BASE} />
        </div>
        <div id="webu-contact-stats-team" className="design-playground__section">
          <h3 className="design-playground__heading">Contact, Stats, Team (CMS)</h3>
          <WebuContact variant="contact-1" title="Contact us" subtitle="We’re here to help." email="hello@example.com" phone="+1 234 567 890" address="123 Main St" basePath={BASE} />
          <WebuStats variant="stats-1" title="Our numbers" items={cmsResolver.getStats()} basePath={BASE} />
          <WebuTeam variant="team-1" title="Our team" members={cmsResolver.getTeam()} basePath={BASE} />
        </div>
        <div id="webu-breadcrumb-pagination" className="design-playground__section">
          <h3 className="design-playground__heading">Breadcrumb, Pagination</h3>
          <WebuBreadcrumb variant="breadcrumb-1" items={[{ label: 'Home', url: '/' }, { label: 'Shop', url: '/shop' }, { label: 'Current' }]} basePath={BASE} />
          <WebuPagination variant="pagination-1" currentPage={2} totalPages={5} basePath={BASE} />
        </div>
        <div id="webu-blog-grid" className="design-playground__section">
          <h3 className="design-playground__heading">Blog grid (CMS)</h3>
          <WebuBlogGrid variant="blog-grid-1" title="Latest posts" posts={cmsResolver.getBlogPosts(6).length ? cmsResolver.getBlogPosts(6) : fallbackBlogPosts} basePath={BASE} />
        </div>
        <div id="webu-product-gallery" className="design-playground__section">
          <h3 className="design-playground__heading">Product gallery, Details, Buy, Filters</h3>
          <WebuProductGallery variant="gallery-1" images={['/demo/hero/hero-1.svg', '/demo/hero/hero-2.svg']} productName="Demo product" />
          <WebuProductDetails variant="details-1" title="Demo product" price={49.99} compareAtPrice={59.99} description="<p>Quality product.</p>" stock={10} />
          <WebuProductBuy variant="buy-1" productId="1" price={49.99} addToCartUrl="/cart/add/1" buyNowUrl="/checkout" basePath={BASE} />
          <WebuProductFilters variant="filters-1" filters={[{ key: 'color', label: 'Color', options: [{ value: 'red', label: 'Red', count: 3 }, { value: 'blue', label: 'Blue', count: 2 }] }]} basePath={BASE} />
        </div>
        <div id="webu-login-register" className="design-playground__section">
          <h3 className="design-playground__heading">Login, Register, Dashboard</h3>
          <WebuLogin variant="login-1" registerUrl="/register" forgotUrl="/forgot" basePath={BASE} />
          <WebuRegister variant="register-1" loginUrl="/login" basePath={BASE} />
          <WebuDashboard variant="dashboard-1" userName="Demo User" menuItems={[{ label: 'Orders', url: '/account/orders' }, { label: 'Addresses', url: '/account/addresses' }]} basePath={BASE} />
        </div>
        <div className="design-playground__section">
          <h3 className="design-playground__heading">Orders, Addresses, Wishlist</h3>
          <WebuOrders variant="orders-1" title="My orders" orders={[{ id: 'ORD-001', date: '2024-01-15', total: 99.99, status: 'Delivered', url: '/account/orders/1' }]} basePath={BASE} />
          <WebuAddresses variant="addresses-1" title="Addresses" addresses={[{ id: '1', line1: '123 Main St', city: 'Tbilisi', country: 'Georgia', isDefault: true }]} basePath={BASE} />
          <WebuWishlist variant="wishlist-1" title="Wishlist" products={[{ id: '1', name: 'Demo product', price: 49.99, url: '/shop/1' }]} basePath={BASE} />
        </div>
        <div id="webu-map-checkout" className="design-playground__section">
          <h3 className="design-playground__heading">Map, Checkout form, Order summary</h3>
          <WebuMap variant="map-1" address="123 Main St, Tbilisi" />
          <WebuCheckoutForm variant="checkout-1" basePath={BASE} />
          <WebuOrderSummary variant="summary-1" lines={[{ label: 'Subtotal', amount: 89.99 }, { label: 'Shipping', amount: 5.99 }]} total={95.98} basePath={BASE} />
        </div>
      </section>

      <section className="design-playground__section">
        <h2 className="design-playground__heading">Header (default)</h2>
        <Header basePath={BASE} logo="Webu Store" />
      </section>
      <section className="design-playground__section">
        <h2 className="design-playground__heading">Header — minimal</h2>
        <Header basePath={BASE} logo="Webu" variant="minimal" />
      </section>
      <section className="design-playground__section">
        <h2 className="design-playground__heading">Header — mega</h2>
        <Header basePath={BASE} logo="Webu Store" variant="mega" />
      </section>

      <section className="design-playground__section">
        <h2 className="design-playground__heading">Hero / Banner</h2>
        <HeroBanner
          basePath={BASE}
          title="Welcome to Our Store"
          subtitle="Discover amazing products for every need."
          ctaText="Shop Now"
          ctaUrl="/shop"
        />
      </section>

      <section className="design-playground__section">
        <h2 className="design-playground__heading">Hero with background image</h2>
        <HeroBanner
          basePath={BASE}
          title="Summer Collection"
          subtitle="New arrivals with dynamic background."
          ctaText="View collection"
          ctaUrl="/shop"
          backgroundImage="https://via.placeholder.com/1200x600/5B5BD6/ffffff?text=Hero+Background"
        />
      </section>

      <section className="design-playground__section">
        <h2 className="design-playground__heading">Category cards</h2>
        <CategoryGrid basePath={BASE} title="Shop by Category" categories={DEMO_CATEGORIES} columns={3} />
      </section>

      <section className="design-playground__section">
        <h2 className="design-playground__heading">Product cards (single row)</h2>
        <div className="demo-grid">
          {DEMO_PRODUCTS.map((p) => (
            <ProductCard
              key={p.id}
              basePath={BASE}
              id={p.id}
              slug={p.slug}
              title={p.title}
              price={p.price}
              currency={p.currency}
              image={p.image}
              badge={p.badge}
              rating={p.rating}
            />
          ))}
        </div>
      </section>

      <section className="design-playground__section">
        <h2 className="design-playground__heading">Product cards — variants</h2>
        <div className="demo-grid demo-grid--variants">
          <ProductCard basePath={BASE} variant="classic" id="v1" slug="v1" title="Classic" price={59} currency="GEL" image="https://via.placeholder.com/400" badge="Classic" rating={4.8} />
          <ProductCard basePath={BASE} variant="minimal" id="v2" slug="v2" title="Minimal" price={39} currency="GEL" image="https://via.placeholder.com/400" />
          <ProductCard basePath={BASE} variant="modern" id="v3" slug="v3" title="Modern" price={45} currency="GEL" image="https://via.placeholder.com/400" badge="New" rating={4.5} />
          <ProductCard basePath={BASE} variant="premium" id="v4" slug="v4" title="Premium" price={99} currency="GEL" image="https://via.placeholder.com/400" badge="Premium" rating={5} />
          <ProductCard basePath={BASE} variant="compact" id="v5" slug="v5" title="Compact" price={29} currency="GEL" image="https://via.placeholder.com/400" />
        </div>
      </section>

      <section className="design-playground__section">
        <h2 className="design-playground__heading">Product grid</h2>
        <ProductGrid basePath={BASE} title="Featured products" products={DEMO_PRODUCTS} columns={3} />
      </section>

      <section className="design-playground__section">
        <h2 className="design-playground__heading">Banner (CTA)</h2>
        <HeroBanner
          basePath={BASE}
          title="Limited offer"
          subtitle="Free shipping on orders over 100 GEL."
          ctaText="Shop now"
          ctaUrl="/shop"
        />
      </section>

      <section className="design-playground__section">
        <h2 className="design-playground__heading">Newsletter (placeholder)</h2>
        <div className="webu-newsletter design-playground__newsletter">
          <h3 className="webu-newsletter__title">Stay updated</h3>
          <p className="webu-newsletter__text">Subscribe for offers and news. (Newsletter component placeholder.)</p>
        </div>
      </section>

      <section className="design-playground__section">
        <h2 className="design-playground__heading">Cart (empty state)</h2>
        <Cart basePath={BASE} title="Your Cart" emptyMessage="Your cart is empty." />
      </section>

      <section className="design-playground__section">
        <h2 className="design-playground__heading">Checkout (placeholder)</h2>
        <Checkout basePath={BASE} title="Checkout" />
      </section>

      <section className="design-playground__section">
        <h2 className="design-playground__heading">Placeholder section</h2>
        <PlaceholderSection basePath={BASE} type="custom" title="Custom section (placeholder)" />
      </section>

      <section className="design-playground__section">
        <h2 className="design-playground__heading">Footer</h2>
        <Footer
          basePath={BASE}
          logo="Webu Store"
          links={[{ label: 'Shop', url: '/shop' }, { label: 'About', url: '/about' }, { label: 'Contact', url: '/contact' }]}
          contact="contact@example.com"
          copyright="© Webu Component Playground. Demo only."
        />
      </section>
    </div>
  );
}
