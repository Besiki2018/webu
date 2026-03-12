/**
 * Maps Webu section library keys to JSX component tag names for generated code.
 * Add new components here when extending the builder.
 */
import {
  getCodegenTagMapSnapshot,
  getComponentCodegenMetadata,
  getComponentRegistryIdByCodegenTagName,
} from '@/builder/componentRegistry';

const LEGACY_SECTION_TAG_MAP: Record<string, string> = {
  header: 'Header',
  footer: 'Footer',
  hero: 'Hero',
  features: 'Features',
  products: 'Products',
  testimonials: 'Testimonials',
  cta: 'CTA',
  container: 'Container',
  grid: 'Grid',
  section: 'Section',
  // General
  'webu_general_header_01': 'Header',
  'webu_general_footer_01': 'Footer',
  'webu_general_hero_01': 'Hero',
  'webu_general_features_01': 'Features',
  'webu_general_cta_01': 'CTA',
  'webu_general_text_01': 'Text',
  'webu_general_heading_01': 'Heading',
  'webu_general_button_01': 'Button',
  'webu_general_spacer_01': 'Spacer',
  'webu_general_image_01': 'Image',
  'webu_general_card_01': 'Card',
  'webu_general_form_wrapper_01': 'FormWrapper',
  'webu_general_input_01': 'Input',
  'webu_general_newsletter_01': 'Newsletter',
  // Ecommerce
  'webu_ecom_product_grid_01': 'ProductGrid',
  'webu_ecom_featured_categories_01': 'FeaturedCategories',
  'webu_ecom_category_list_01': 'CategoryList',
  'webu_ecom_product_search_01': 'ProductSearch',
  'webu_ecom_product_gallery_01': 'ProductGallery',
  'webu_ecom_product_detail_01': 'ProductDetail',
  'webu_ecom_add_to_cart_button_01': 'AddToCartButton',
  'webu_ecom_product_tabs_01': 'ProductTabs',
  'webu_ecom_cart_icon_01': 'CartIcon',
  'webu_ecom_cart_page_01': 'CartPage',
  'webu_ecom_coupon_ui_01': 'CouponUI',
  'webu_ecom_order_summary_01': 'OrderSummary',
};

const SECTION_TAG_MAP: Record<string, string> = {
  ...LEGACY_SECTION_TAG_MAP,
  ...getCodegenTagMapSnapshot(),
};

/**
 * Get JSX tag name for a section key. Falls back to PascalCase of the key.
 */
export function getSectionTagName(sectionKey: string): string {
  const normalized = sectionKey.trim().toLowerCase();
  const registryCodegen = getComponentCodegenMetadata(sectionKey);
  if (registryCodegen?.tagName) {
    return registryCodegen.tagName;
  }
  if (SECTION_TAG_MAP[normalized]) {
    return SECTION_TAG_MAP[normalized];
  }
  return sectionKeyToPascalCase(sectionKey);
}

/**
 * Get section key from a tag name (reverse lookup for code → builder sync).
 */
export function getSectionKeyFromTagName(tagName: string): string | null {
  const normalized = tagName.trim();
  const registryId = getComponentRegistryIdByCodegenTagName(normalized);
  if (registryId) {
    return registryId;
  }
  const entry = Object.entries(SECTION_TAG_MAP).find(
    ([, tag]) => tag === normalized
  );
  if (entry) return entry[0];
  return tagNameToSectionKey(normalized);
}

function sectionKeyToPascalCase(key: string): string {
  return key
    .split(/[-_\s]+/)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase())
    .join('');
}

function tagNameToSectionKey(tag: string): string {
  const withSpaces = tag.replace(/([A-Z])/g, ' $1').trim().toLowerCase();
  return withSpaces.replace(/\s+/g, '_');
}

export { SECTION_TAG_MAP };
