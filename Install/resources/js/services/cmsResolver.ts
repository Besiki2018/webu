/**
 * Webu CMS Resolver – frontend layer for dynamic content.
 * All components must receive content from Webu CMS via this resolver or passed props.
 * Data can be provided by: (1) Inertia page props, (2) API fetch, (3) React context.
 */

export interface SiteSettings {
  logo_url?: string | null;
  logo_text: string;
  brand: string;
  cta_label?: string | null;
  cta_url?: string | null;
  locale: string;
}

export interface NavItem {
  label: string;
  url: string;
  slug?: string;
}

export interface CmsProduct {
  id?: string | number;
  name: string;
  slug: string;
  price: string;
  old_price?: string | null;
  image_url?: string | null;
  url: string;
}

export interface CmsCategory {
  name: string;
  slug: string;
  count?: number;
  image_url?: string | null;
}

export interface CmsFooterData {
  menus: Record<string, { label: string; url: string }[]>;
  contactAddress?: string;
}

export interface CmsTestimonial {
  user_name: string;
  avatar?: string;
  rating?: number;
  text: string;
}

export interface CmsFeature {
  icon?: string;
  title: string;
  description?: string;
}

export interface CmsFaqItem {
  question: string;
  answer: string;
}

export interface CmsBlogPost {
  id: string;
  title: string;
  excerpt?: string;
  image?: string;
  url?: string;
  date?: string;
  author?: string;
}

export interface CmsData {
  siteSettings: SiteSettings;
  navigation: NavItem[];
  products: CmsProduct[];
  categories: CmsCategory[];
  footer: CmsFooterData;
  testimonials?: CmsTestimonial[];
  features?: CmsFeature[];
  faq?: CmsFaqItem[];
  blogPosts?: CmsBlogPost[];
  announcement?: { text: string; linkUrl?: string; linkLabel?: string; countdownEnd?: string };
  stats?: Array<{ label: string; value: string }>;
  team?: Array<{ name: string; role?: string; avatar?: string }>;
}

let cachedCms: CmsData | null = null;

/**
 * Set CMS data (e.g. from Inertia page props). Call from DesignSystem or layout page.
 */
export function setCmsData(data: CmsData | null): void {
  cachedCms = data;
}

/**
 * Get site settings. Uses cached CMS or returns defaults.
 */
export function getSiteSettings(): SiteSettings {
  if (cachedCms?.siteSettings) return cachedCms.siteSettings;
  return {
    logo_text: 'Store',
    brand: 'Store',
    locale: typeof document !== 'undefined' ? document.documentElement.lang || 'en' : 'en',
  };
}

/**
 * Get navigation menu for header. Uses cached CMS or defaults.
 */
export function getNavigation(): NavItem[] {
  if (cachedCms?.navigation?.length) return cachedCms.navigation;
  return [
    { label: 'Home', url: '/', slug: 'home' },
    { label: 'Shop', url: '/shop', slug: 'shop' },
    { label: 'Contact', url: '/contact', slug: 'contact' },
  ];
}

/**
 * Get products. Uses cached CMS or empty array.
 * For API-driven: call /api/webu/cms/products or pass data via setCmsData.
 */
export function getProducts(options?: { featured?: boolean; limit?: number }): CmsProduct[] {
  const list = cachedCms?.products ?? [];
  const limit = options?.limit ?? 12;
  return list.slice(0, limit);
}

/**
 * Get categories. Uses cached CMS or defaults.
 */
export function getCategories(): CmsCategory[] {
  if (cachedCms?.categories?.length) return cachedCms.categories;
  return [
    { name: 'New In', slug: 'new-in' },
    { name: 'Top Picks', slug: 'top-picks' },
    { name: 'Sale', slug: 'sale' },
    { name: 'Accessories', slug: 'accessories' },
  ];
}

/**
 * Get collections (alias for categories or future collection API).
 */
export function getCollections(): CmsCategory[] {
  return getCategories();
}

/**
 * Get footer data (menus + contact). Uses cached CMS or defaults.
 */
export function getFooterData(): CmsFooterData {
  if (cachedCms?.footer) return cachedCms.footer;
  return {
    menus: {
      footer: [
        { label: 'Shop', url: '/shop' },
        { label: 'About', url: '/about' },
        { label: 'Contact', url: '/contact' },
      ],
    },
  };
}

/**
 * Get banners (optional; extend backend when needed).
 */
export function getBanners(): Array<{ title: string; subtitle?: string; cta_label?: string; cta_url?: string; image_url?: string }> {
  return [];
}

export function getTestimonials(): CmsTestimonial[] {
  return cachedCms?.testimonials ?? [];
}

export function getFeatures(): CmsFeature[] {
  return cachedCms?.features ?? [];
}

export function getFaq(): CmsFaqItem[] {
  return cachedCms?.faq ?? [];
}

export function getBlogPosts(limit?: number): CmsBlogPost[] {
  const list = cachedCms?.blogPosts ?? [];
  return limit ? list.slice(0, limit) : list;
}

export function getAnnouncement(): CmsData['announcement'] {
  return cachedCms?.announcement ?? undefined;
}

export function getStats(): Array<{ label: string; value: string }> {
  return cachedCms?.stats ?? [];
}

export function getTeam(): Array<{ name: string; role?: string; avatar?: string }> {
  return cachedCms?.team ?? [];
}

export const cmsResolver = {
  getSiteSettings,
  getNavigation,
  getProducts,
  getCategories,
  getCollections,
  getFooterData,
  getBanners,
  getTestimonials,
  getFeatures,
  getFaq,
  getBlogPosts,
  getAnnouncement,
  getStats,
  getTeam,
  setCmsData,
};
