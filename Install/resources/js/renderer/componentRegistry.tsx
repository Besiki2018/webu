/**
 * Component registry for LayoutRenderer.
 * Maps layout JSON "component" keys to React components.
 * All components receive variant and resolve data from CMS.
 */

import React from 'react';
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
import { WebuMap } from '@/components/design-system/webu-map';
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
import { WebuCartDrawer } from '@/components/design-system/webu-cart-drawer';
import { WebuOffcanvasMenu } from '@/components/design-system/webu-offcanvas-menu';
import { cmsResolver } from '@/services/cmsResolver';
import type { LayoutSection } from '@/types/layoutSchema';

const basePath = '';

export function resolveComponent(section: LayoutSection): React.ReactNode {
  const component = (section.component || '').toLowerCase().replace(/\s+/g, '-').replace(/^webu-/, '');
  const variant = section.variant || undefined;
  const bindings = section.bindings ?? {};

  const siteSettings = cmsResolver.getSiteSettings();
  const navigation = cmsResolver.getNavigation();
  const products = cmsResolver.getProducts({ limit: 8 });
  const categories = cmsResolver.getCategories();
  const footerData = cmsResolver.getFooterData();
  const testimonials = cmsResolver.getTestimonials();
  const features = cmsResolver.getFeatures();
  const faq = cmsResolver.getFaq();
  const blogPosts = cmsResolver.getBlogPosts(6);
  const announcement = cmsResolver.getAnnouncement();
  const stats = cmsResolver.getStats();
  const team = cmsResolver.getTeam();

  switch (component) {
    case 'header': {
      const headerBindings = (bindings && typeof bindings === 'object' ? bindings : {}) as Record<string, unknown>;
      const parseOptionalNumber = (value: unknown): number | undefined => {
        if (typeof value === 'number' && Number.isFinite(value)) {
          return value;
        }
        if (typeof value === 'string' && value.trim() !== '') {
          const parsed = Number(value);
          return Number.isFinite(parsed) ? parsed : undefined;
        }
        return undefined;
      };
      const bindingMenu = Array.isArray(headerBindings.menu_items)
        ? (headerBindings.menu_items as Array<Record<string, unknown>>)
            .map((item) => ({
              label: typeof item.label === 'string' ? item.label : '',
              url: typeof item.url === 'string' ? item.url : '/',
              slug: typeof item.slug === 'string' ? item.slug : undefined,
            }))
            .filter((item) => item.label !== '')
        : navigation;
      const departmentMenu = Array.isArray(headerBindings.department_menu_items)
        ? (headerBindings.department_menu_items as Array<Record<string, unknown>>)
            .map((item) => ({
              label: typeof item.label === 'string' ? item.label : '',
              url: typeof item.url === 'string' ? item.url : '/',
              slug: typeof item.slug === 'string' ? item.slug : undefined,
              description: typeof item.description === 'string' ? item.description : undefined,
            }))
            .filter((item) => item.label !== '')
        : undefined;
      const utilityLinks = Array.isArray(headerBindings.strip_right_links)
        ? (headerBindings.strip_right_links as Array<Record<string, unknown>>)
            .map((item) => ({
              label: typeof item.label === 'string' ? item.label : '',
              url: typeof item.url === 'string' ? item.url : '/',
            }))
            .filter((item) => item.label !== '')
        : undefined;
      const topBarSocialLinks = Array.isArray(headerBindings.top_bar_social_links)
        ? (headerBindings.top_bar_social_links as Array<Record<string, unknown>>)
            .map((item) => ({
              label: typeof item.label === 'string' ? item.label : '',
              url: typeof item.url === 'string' ? item.url : '/',
              icon: typeof item.icon === 'string' ? item.icon : undefined,
            }))
            .filter((item) => item.label !== '')
        : undefined;

      const logoImageUrl =
        (typeof headerBindings.logo_image_url === 'string' && headerBindings.logo_image_url.trim() !== ''
          ? headerBindings.logo_image_url.trim()
          : null) ?? (typeof siteSettings.logo_url === 'string' && siteSettings.logo_url.trim() !== '' ? siteSettings.logo_url.trim() : null);
      return (
        <WebuHeader
          variant={variant as 'header-1' | 'header-2' | 'header-3' | 'header-4' | 'header-5' | 'header-6'}
          logo={typeof headerBindings.logo_text === 'string' ? headerBindings.logo_text : siteSettings.logo_text}
          logoUrl={typeof headerBindings.logo_url === 'string' ? headerBindings.logo_url : '/'}
          logoImageUrl={logoImageUrl ?? undefined}
          menu={bindingMenu}
          ctaLabel={typeof headerBindings.cta_label === 'string' ? headerBindings.cta_label : siteSettings.cta_label ?? undefined}
          ctaUrl={typeof headerBindings.cta_url === 'string' ? headerBindings.cta_url : siteSettings.cta_url ?? undefined}
          announcementText={typeof headerBindings.announcement_text === 'string' ? headerBindings.announcement_text : undefined}
          announcementCtaLabel={typeof headerBindings.announcement_cta_label === 'string' ? headerBindings.announcement_cta_label : undefined}
          announcementCtaUrl={typeof headerBindings.announcement_cta_url === 'string' ? headerBindings.announcement_cta_url : undefined}
          topBarLoginLabel={typeof headerBindings.top_bar_login_label === 'string' ? headerBindings.top_bar_login_label : undefined}
          topBarLoginUrl={typeof headerBindings.top_bar_login_url === 'string' ? headerBindings.top_bar_login_url : undefined}
          topBarSocialLinks={topBarSocialLinks}
          topBarLocationText={typeof headerBindings.top_bar_location_text === 'string' ? headerBindings.top_bar_location_text : undefined}
          topBarLocationUrl={typeof headerBindings.top_bar_location_url === 'string' ? headerBindings.top_bar_location_url : undefined}
          topBarEmailText={typeof headerBindings.top_bar_email_text === 'string' ? headerBindings.top_bar_email_text : undefined}
          topBarEmailUrl={typeof headerBindings.top_bar_email_url === 'string' ? headerBindings.top_bar_email_url : undefined}
          hotlineEyebrow={typeof headerBindings.hotline_eyebrow === 'string' ? headerBindings.hotline_eyebrow : undefined}
          hotlineLabel={typeof headerBindings.hotline_label === 'string' ? headerBindings.hotline_label : undefined}
          hotlineUrl={typeof headerBindings.hotline_url === 'string' ? headerBindings.hotline_url : undefined}
          topBarLeftText={typeof headerBindings.top_bar_left_text === 'string' ? headerBindings.top_bar_left_text : undefined}
          topBarLeftCta={typeof headerBindings.top_bar_left_cta === 'string' ? headerBindings.top_bar_left_cta : undefined}
          topBarLeftCtaUrl={typeof headerBindings.top_bar_left_cta_url === 'string' ? headerBindings.top_bar_left_cta_url : undefined}
          socialFollowers={typeof headerBindings.social_followers === 'string' ? headerBindings.social_followers : undefined}
          socialUrl={typeof headerBindings.social_url === 'string' ? headerBindings.social_url : undefined}
          topBarRightTracking={typeof headerBindings.top_bar_right_tracking === 'string' ? headerBindings.top_bar_right_tracking : undefined}
          topBarRightTrackingUrl={typeof headerBindings.top_bar_right_tracking_url === 'string' ? headerBindings.top_bar_right_tracking_url : undefined}
          topBarRightLang={typeof headerBindings.top_bar_right_lang === 'string' ? headerBindings.top_bar_right_lang : undefined}
          topBarRightCurrency={typeof headerBindings.top_bar_right_currency === 'string' ? headerBindings.top_bar_right_currency : undefined}
          accountUrl={typeof headerBindings.account_url === 'string' ? headerBindings.account_url : undefined}
          searchUrl={typeof headerBindings.search_url === 'string' ? headerBindings.search_url : undefined}
          wishlistUrl={typeof headerBindings.wishlist_url === 'string' ? headerBindings.wishlist_url : undefined}
          cartUrl={typeof headerBindings.cart_url === 'string' ? headerBindings.cart_url : undefined}
          searchPlaceholder={typeof headerBindings.search_placeholder === 'string' ? headerBindings.search_placeholder : undefined}
          searchCategoryLabel={typeof headerBindings.search_category_label === 'string' ? headerBindings.search_category_label : undefined}
          searchButtonLabel={typeof headerBindings.search_button_label === 'string' ? headerBindings.search_button_label : undefined}
          departmentLabel={typeof headerBindings.department_label === 'string' ? headerBindings.department_label : undefined}
          departmentMenu={departmentMenu}
          promoEyebrow={typeof headerBindings.promo_eyebrow === 'string' ? headerBindings.promo_eyebrow : undefined}
          promoLabel={typeof headerBindings.promo_label === 'string' ? headerBindings.promo_label : undefined}
          promoUrl={typeof headerBindings.promo_url === 'string' ? headerBindings.promo_url : undefined}
          accountEyebrow={typeof headerBindings.account_eyebrow === 'string' ? headerBindings.account_eyebrow : undefined}
          accountLabel={typeof headerBindings.account_label === 'string' ? headerBindings.account_label : undefined}
          cartLabel={typeof headerBindings.cart_label === 'string' ? headerBindings.cart_label : undefined}
          utilityLinks={utilityLinks}
          wishlistCount={parseOptionalNumber(headerBindings.wishlist_count)}
          cartCount={parseOptionalNumber(headerBindings.cart_count)}
          cartTotal={typeof headerBindings.cart_total === 'string' ? headerBindings.cart_total : undefined}
          menuDrawerSide={headerBindings.menu_drawer_side === 'right' ? 'right' : headerBindings.menu_drawer_side === 'left' ? 'left' : undefined}
          menuDrawerTitle={typeof headerBindings.menu_drawer_title === 'string' ? headerBindings.menu_drawer_title : undefined}
          menuDrawerSubtitle={typeof headerBindings.menu_drawer_subtitle === 'string' ? headerBindings.menu_drawer_subtitle : undefined}
          basePath={basePath}
        />
      );
    }
    case 'hero': {
      const heroBindings = (bindings && typeof bindings === 'object' ? bindings : {}) as Record<string, unknown>;
      const heroStatAvatars = Array.isArray(heroBindings.hero_stat_avatars)
        ? (heroBindings.hero_stat_avatars as Array<Record<string, unknown>>)
            .map((item) => ({
              url: typeof item.url === 'string' ? item.url : '',
              alt: typeof item.alt === 'string' ? item.alt : undefined,
            }))
            .filter((item) => item.url !== '')
        : Array.isArray(heroBindings.stat_avatars)
          ? (heroBindings.stat_avatars as Array<Record<string, unknown>>)
              .map((item) => ({
                url: typeof item.url === 'string' ? item.url : '',
                alt: typeof item.alt === 'string' ? item.alt : undefined,
              }))
              .filter((item) => item.url !== '')
          : undefined;
      return (
        <WebuHero
          variant={variant as 'hero-1' | 'hero-2' | 'hero-3' | 'hero-4' | 'hero-5' | 'hero-6' | 'hero-7'}
          headline={typeof heroBindings.headline === 'string' ? heroBindings.headline : 'Welcome'}
          subheading={typeof heroBindings.subheading === 'string' ? heroBindings.subheading : 'Discover our collection.'}
          eyebrow={typeof heroBindings.eyebrow === 'string' ? heroBindings.eyebrow : undefined}
          badgeText={typeof heroBindings.badge_text === 'string' ? heroBindings.badge_text : undefined}
          ctaLabel={
            typeof heroBindings.hero_cta_label === 'string'
              ? heroBindings.hero_cta_label
              : typeof heroBindings.cta_label === 'string'
                ? heroBindings.cta_label
                : 'Shop now'
          }
          ctaUrl={
            typeof heroBindings.hero_cta_url === 'string'
              ? heroBindings.hero_cta_url
              : typeof heroBindings.cta_url === 'string'
                ? heroBindings.cta_url
                : '/shop'
          }
          ctaSecondaryLabel={
            typeof heroBindings.hero_cta_secondary_label === 'string'
              ? heroBindings.hero_cta_secondary_label
              : typeof heroBindings.cta_secondary_label === 'string'
                ? heroBindings.cta_secondary_label
                : undefined
          }
          ctaSecondaryUrl={
            typeof heroBindings.hero_cta_secondary_url === 'string'
              ? heroBindings.hero_cta_secondary_url
              : typeof heroBindings.cta_secondary_url === 'string'
                ? heroBindings.cta_secondary_url
                : undefined
          }
          imageUrl={
            typeof heroBindings.hero_image_url === 'string'
              ? heroBindings.hero_image_url
              : typeof heroBindings.image_url === 'string'
                ? heroBindings.image_url
                : typeof heroBindings.image === 'string'
                  ? heroBindings.image
                  : '/demo/hero/hero-1.svg'
          }
          imageAlt={
            typeof heroBindings.hero_image_alt === 'string'
              ? heroBindings.hero_image_alt
              : typeof heroBindings.image_alt === 'string'
                ? heroBindings.image_alt
                : undefined
          }
          overlayImageUrl={
            typeof heroBindings.hero_overlay_image_url === 'string'
              ? heroBindings.hero_overlay_image_url
              : typeof heroBindings.overlay_image_url === 'string'
                ? heroBindings.overlay_image_url
                : undefined
          }
          overlayImageAlt={
            typeof heroBindings.hero_overlay_image_alt === 'string'
              ? heroBindings.hero_overlay_image_alt
              : typeof heroBindings.overlay_image_alt === 'string'
                ? heroBindings.overlay_image_alt
                : undefined
          }
          statValue={
            typeof heroBindings.hero_stat_value === 'string'
              ? heroBindings.hero_stat_value
              : typeof heroBindings.stat_value === 'string'
                ? heroBindings.stat_value
                : undefined
          }
          statUnit={
            typeof heroBindings.hero_stat_unit === 'string'
              ? heroBindings.hero_stat_unit
              : typeof heroBindings.stat_unit === 'string'
                ? heroBindings.stat_unit
                : undefined
          }
          statLabel={
            typeof heroBindings.hero_stat_label === 'string'
              ? heroBindings.hero_stat_label
              : typeof heroBindings.stat_label === 'string'
                ? heroBindings.stat_label
                : undefined
          }
          statAvatars={heroStatAvatars}
          basePath={basePath}
        />
      );
    }
    case 'footer':
      return (
        <WebuFooter
          variant={variant as 'footer-1' | 'footer-2' | 'footer-3' | 'footer-4'}
          logo={siteSettings.logo_text}
          menus={footerData.menus}
          contactAddress={footerData.contactAddress}
          basePath={basePath}
        />
      );
    case 'product-grid':
      return (
        <WebuProductGrid
          title={(bindings.title as string) ?? 'Featured products'}
          products={products}
          variant={(variant as 'grid-1' | 'grid-2' | 'grid-3' | 'grid-4') ?? 'classic'}
          basePath={basePath}
        />
      );
    case 'banner':
      return (
        <WebuBanner
          variant={variant as 'banner-1' | 'banner-2' | 'banner-3' | 'banner-4'}
          title={(bindings.title as string) ?? 'Limited offer'}
          subtitle={bindings.subtitle as string | undefined}
          ctaLabel={(bindings.cta_label as string) ?? 'Shop now'}
          ctaUrl={(bindings.cta_url as string) ?? '/shop'}
          basePath={basePath}
        />
      );
    case 'cta':
      return (
        <WebuCta
          variant={variant as 'cta-1' | 'cta-2' | 'cta-3' | 'cta-4'}
          title={(bindings.title as string) ?? 'Get started'}
          subtitle={bindings.subtitle as string | undefined}
          buttonLabel={(bindings.cta_label as string) ?? 'Learn more'}
          buttonUrl={(bindings.cta_url as string) ?? '/'}
          basePath={basePath}
        />
      );
    case 'newsletter':
      return (
        <WebuNewsletter
          variant={variant as 'newsletter-1' | 'newsletter-2' | 'newsletter-3'}
          title={(bindings.title as string) ?? 'Stay updated'}
          text={(bindings.text as string) ?? 'Subscribe for offers and news.'}
          basePath={basePath}
        />
      );
    case 'category-grid':
      return (
        <section className="webu-category-grid">
          <h2 className="webu-category-grid__title">{(bindings.title as string) ?? 'Shop by category'}</h2>
          <div className="webu-category-grid__inner">
            {categories.map((cat) => (
              <WebuCategoryCard key={cat.slug} category={cat} basePath={basePath} />
            ))}
          </div>
        </section>
      );
    case 'features':
      return (
        <WebuFeatures
          variant={variant as 'features-1' | 'features-2' | 'features-3' | 'features-4'}
          title={(bindings.title as string) ?? 'Why choose us'}
          items={features.length ? features : [{ title: 'Quality', description: 'Premium products.' }, { title: 'Support', description: '24/7 help.' }]}
          basePath={basePath}
        />
      );
    case 'testimonials':
      return (
        <WebuTestimonials
          variant={variant as 'testimonials-1' | 'testimonials-2' | 'testimonials-3'}
          title={(bindings.title as string) ?? 'What people say'}
          items={testimonials.length ? testimonials : []}
          basePath={basePath}
        />
      );
    case 'announcement':
      return announcement ? (
        <WebuAnnouncement
          variant={variant as 'announcement-1' | 'announcement-2' | 'announcement-3'}
          text={announcement.text}
          linkUrl={announcement.linkUrl}
          linkLabel={announcement.linkLabel}
          countdownEnd={announcement.countdownEnd}
          basePath={basePath}
        />
      ) : (
        <WebuAnnouncement variant={variant as 'announcement-1'} text="Free shipping on orders over $50" basePath={basePath} />
      );
    case 'faq':
      return (
        <WebuFaq
          variant={variant as 'faq-1' | 'faq-2'}
          title={(bindings.title as string) ?? 'FAQ'}
          items={faq.length ? faq : [{ question: 'How do I return?', answer: 'Contact support for returns.' }]}
          basePath={basePath}
        />
      );
    case 'contact':
      return (
        <WebuContact
          variant={variant as 'contact-1' | 'contact-2' | 'contact-3'}
          title={(bindings.title as string) ?? 'Contact us'}
          subtitle={bindings.subtitle as string | undefined}
          email={bindings.email as string | undefined}
          phone={bindings.phone as string | undefined}
          address={bindings.address as string | undefined}
          basePath={basePath}
        />
      );
    case 'map':
      return (
        <WebuMap
          variant={variant as 'map-1' | 'map-2'}
          embedUrl={bindings.embedUrl as string | undefined}
          address={bindings.address as string | undefined}
        />
      );
    case 'stats':
      return (
        <WebuStats
          variant={variant as 'stats-1' | 'stats-2' | 'stats-3'}
          title={(bindings.title as string) ?? 'Our numbers'}
          items={stats.length ? stats : [{ label: 'Customers', value: '10k+' }, { label: 'Products', value: '500+' }]}
          basePath={basePath}
        />
      );
    case 'team':
      return (
        <WebuTeam
          variant={variant as 'team-1' | 'team-2'}
          title={(bindings.title as string) ?? 'Our team'}
          members={team.length ? team : []}
          basePath={basePath}
        />
      );
    case 'breadcrumb':
      return (
        <WebuBreadcrumb
          variant={variant as 'breadcrumb-1' | 'breadcrumb-2'}
          items={Array.isArray(bindings.items) ? bindings.items as { label: string; url?: string }[] : [{ label: 'Home', url: '/' }, { label: 'Current' }]}
          basePath={basePath}
        />
      );
    case 'pagination':
      return (
        <WebuPagination
          variant={variant as 'pagination-1' | 'pagination-2' | 'pagination-3'}
          currentPage={typeof bindings.currentPage === 'number' ? bindings.currentPage : 1}
          totalPages={typeof bindings.totalPages === 'number' ? bindings.totalPages : 1}
          basePath={basePath}
        />
      );
    case 'blog-grid':
      return (
        <WebuBlogGrid
          variant={variant as 'blog-grid-1' | 'blog-grid-2' | 'blog-grid-3'}
          title={(bindings.title as string) ?? 'Latest posts'}
          posts={blogPosts.length ? blogPosts.map((p) => ({ id: p.id, title: p.title, excerpt: p.excerpt, image: p.image, url: p.url, date: p.date, author: p.author })) : []}
          basePath={basePath}
        />
      );
    case 'product-gallery':
      return (
        <WebuProductGallery
          variant={variant as 'gallery-1' | 'gallery-2' | 'gallery-3'}
          images={Array.isArray(bindings.images) ? bindings.images as string[] : (bindings.image ? [bindings.image as string] : [])}
          productName={bindings.productName as string | undefined}
        />
      );
    case 'product-details':
      return (
        <WebuProductDetails
          variant={variant as 'details-1' | 'details-2' | 'details-3'}
          title={(bindings.title as string) ?? 'Product'}
          price={typeof bindings.price === 'number' ? bindings.price : 0}
          compareAtPrice={typeof bindings.compareAtPrice === 'number' ? bindings.compareAtPrice : undefined}
          description={bindings.description as string | undefined}
          variants={Array.isArray(bindings.variants) ? bindings.variants as { name: string; value: string }[] : undefined}
          stock={typeof bindings.stock === 'number' ? bindings.stock : undefined}
          sku={bindings.sku as string | undefined}
        />
      );
    case 'product-buy':
      return (
        <WebuProductBuy
          variant={variant as 'buy-1' | 'buy-2' | 'buy-3'}
          productId={(bindings.productId as string) ?? ''}
          price={typeof bindings.price === 'number' ? bindings.price : 0}
          addToCartUrl={bindings.addToCartUrl as string | undefined}
          buyNowUrl={bindings.buyNowUrl as string | undefined}
          basePath={basePath}
        />
      );
    case 'product-filters':
      return (
        <WebuProductFilters
          variant={variant as 'filters-1' | 'filters-2' | 'filters-3'}
          filters={Array.isArray(bindings.filters) ? bindings.filters as { key: string; label: string; options: { value: string; label: string; count?: number }[] }[] : []}
          basePath={basePath}
        />
      );
    case 'checkout-form':
      return (
        <WebuCheckoutForm
          variant={variant as 'checkout-1' | 'checkout-2' | 'checkout-3'}
          action={bindings.action as string | undefined}
          basePath={basePath}
        />
      );
    case 'order-summary':
      return (
        <WebuOrderSummary
          variant={variant as 'summary-1' | 'summary-2'}
          lines={Array.isArray(bindings.lines) ? bindings.lines as { label: string; amount: number }[] : []}
          total={typeof bindings.total === 'number' ? bindings.total : 0}
          basePath={basePath}
        />
      );
    case 'login':
      return (
        <WebuLogin
          variant={variant as 'login-1' | 'login-2'}
          action={bindings.action as string | undefined}
          registerUrl={bindings.registerUrl as string | undefined}
          forgotUrl={bindings.forgotUrl as string | undefined}
          basePath={basePath}
        />
      );
    case 'register':
      return (
        <WebuRegister
          variant={variant as 'register-1' | 'register-2'}
          action={bindings.action as string | undefined}
          loginUrl={bindings.loginUrl as string | undefined}
          basePath={basePath}
        />
      );
    case 'dashboard':
      return (
        <WebuDashboard
          variant={variant as 'dashboard-1' | 'dashboard-2'}
          userName={bindings.userName as string | undefined}
          menuItems={Array.isArray(bindings.menuItems) ? bindings.menuItems as { label: string; url: string }[] : undefined}
          basePath={basePath}
        />
      );
    case 'orders':
      return (
        <WebuOrders
          variant={variant as 'orders-1' | 'orders-2'}
          title={(bindings.title as string) ?? 'My orders'}
          orders={Array.isArray(bindings.orders) ? bindings.orders as { id: string; date: string; total: number; status: string; url?: string }[] : []}
          basePath={basePath}
        />
      );
    case 'addresses':
      return (
        <WebuAddresses
          variant={variant as 'addresses-1' | 'addresses-2'}
          title={(bindings.title as string) ?? 'Addresses'}
          addresses={Array.isArray(bindings.addresses) ? bindings.addresses as { id: string; line1: string; line2?: string; city?: string; country?: string; label?: string; isDefault?: boolean }[] : []}
          basePath={basePath}
        />
      );
    case 'wishlist':
      return (
        <WebuWishlist
          variant={variant as 'wishlist-1' | 'wishlist-2'}
          title={(bindings.title as string) ?? 'Wishlist'}
          products={Array.isArray(bindings.products) ? bindings.products as { id: string; name: string; price: number; image?: string; url?: string }[] : []}
          basePath={basePath}
        />
      );
    case 'cart-drawer':
      return (
        <WebuCartDrawer
          variant={variant as 'drawer-1' | 'drawer-2'}
          open={false}
          onClose={() => {}}
          lines={[]}
          total={0}
          basePath={basePath}
        />
      );
    case 'offcanvas-menu':
      return (
        <WebuOffcanvasMenu
          variant={variant as 'drawer-1'}
          title={(bindings.title as string) ?? 'Shop navigation'}
          subtitle={(bindings.subtitle as string) ?? 'Reusable drawer for desktop hamburger and mobile navigation.'}
          footerLabel={(bindings.footer_label as string) ?? 'Shop all'}
          footerUrl={(bindings.footer_url as string) ?? '/shop'}
          items={navigation.map((item) => ({
            label: item.label,
            url: item.url,
            description: 'Open page',
          }))}
          basePath={basePath}
          defaultOpen
        />
      );
    default:
      return (
        <div className="webu-unknown-section" data-component={section.component}>
          Unknown section: {section.component}
        </div>
      );
  }
}

export const componentRegistry: Record<string, React.ComponentType<unknown> | null> = {
  header: WebuHeader as React.ComponentType<unknown>,
  hero: WebuHero as React.ComponentType<unknown>,
  footer: WebuFooter as React.ComponentType<unknown>,
  'product-grid': WebuProductGrid as React.ComponentType<unknown>,
  banner: WebuBanner as React.ComponentType<unknown>,
  cta: WebuCta as React.ComponentType<unknown>,
  newsletter: WebuNewsletter as React.ComponentType<unknown>,
  features: WebuFeatures as React.ComponentType<unknown>,
  testimonials: WebuTestimonials as React.ComponentType<unknown>,
  announcement: WebuAnnouncement as React.ComponentType<unknown>,
  faq: WebuFaq as React.ComponentType<unknown>,
  contact: WebuContact as React.ComponentType<unknown>,
  map: WebuMap as React.ComponentType<unknown>,
  stats: WebuStats as React.ComponentType<unknown>,
  team: WebuTeam as React.ComponentType<unknown>,
  breadcrumb: WebuBreadcrumb as React.ComponentType<unknown>,
  pagination: WebuPagination as React.ComponentType<unknown>,
  'blog-grid': WebuBlogGrid as React.ComponentType<unknown>,
  'product-gallery': WebuProductGallery as React.ComponentType<unknown>,
  'product-details': WebuProductDetails as React.ComponentType<unknown>,
  'product-buy': WebuProductBuy as React.ComponentType<unknown>,
  'product-filters': WebuProductFilters as React.ComponentType<unknown>,
  'checkout-form': WebuCheckoutForm as React.ComponentType<unknown>,
  'order-summary': WebuOrderSummary as React.ComponentType<unknown>,
  login: WebuLogin as React.ComponentType<unknown>,
  register: WebuRegister as React.ComponentType<unknown>,
  dashboard: WebuDashboard as React.ComponentType<unknown>,
  orders: WebuOrders as React.ComponentType<unknown>,
  addresses: WebuAddresses as React.ComponentType<unknown>,
  wishlist: WebuWishlist as React.ComponentType<unknown>,
  'cart-drawer': WebuCartDrawer as React.ComponentType<unknown>,
  'offcanvas-menu': WebuOffcanvasMenu as React.ComponentType<unknown>,
  'category-grid': null,
};
