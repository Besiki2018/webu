/**
 * Template matrix: (websiteType + category + style) -> templateId.
 * Used by Ultra Cheap Mode to select template without AI.
 * Backend resolves via config/ultra_cheap.php; this is the frontend mirror for display/fallbacks.
 */

export type WebsiteType = 'business' | 'ecommerce' | 'portfolio' | 'booking';
export type Style = 'modern' | 'minimal' | 'luxury' | 'playful' | 'corporate';

const FALLBACKS: Record<WebsiteType, string> = {
  business: 'business_default_modern_01',
  ecommerce: 'ecommerce_default_modern_01',
  portfolio: 'portfolio_default_modern_01',
  booking: 'booking_default_modern_01',
};

/**
 * Resolve template id client-side (for display or when backend is not used).
 */
export function resolveTemplateId(
  websiteType: WebsiteType,
  category: string,
  style: Style
): string {
  const matrix: Record<string, Record<string, Record<string, string>>> = {
    business: {
      beauty_salon: { modern: 'biz_salon_modern_01', minimal: 'biz_salon_minimal_01', luxury: 'biz_salon_luxury_01', playful: 'biz_salon_playful_01', corporate: 'biz_salon_corporate_01' },
      restaurant: { modern: 'biz_restaurant_modern_01', minimal: 'biz_restaurant_minimal_01', luxury: 'biz_restaurant_luxury_01', playful: 'biz_restaurant_playful_01', corporate: 'biz_restaurant_corporate_01' },
      general: { modern: 'business_default_modern_01', minimal: 'business_default_minimal_01', luxury: 'business_default_luxury_01', playful: 'business_default_playful_01', corporate: 'business_default_corporate_01' },
    },
    ecommerce: {
      electronics: { modern: 'shop_electronics_modern_01', minimal: 'shop_electronics_minimal_01', luxury: 'shop_electronics_luxury_01', playful: 'shop_electronics_playful_01', corporate: 'shop_electronics_corporate_01' },
      fashion: { modern: 'shop_fashion_modern_01', minimal: 'shop_fashion_minimal_01', luxury: 'shop_fashion_luxury_01', playful: 'shop_fashion_playful_01', corporate: 'shop_fashion_corporate_01' },
      general: { modern: 'ecommerce_default_modern_01', minimal: 'ecommerce_default_minimal_01', luxury: 'ecommerce_default_luxury_01', playful: 'ecommerce_default_playful_01', corporate: 'ecommerce_default_corporate_01' },
    },
    portfolio: {
      general: { modern: 'portfolio_default_modern_01', minimal: 'portfolio_default_minimal_01', luxury: 'portfolio_default_luxury_01', playful: 'portfolio_default_playful_01', corporate: 'portfolio_default_corporate_01' },
    },
    booking: {
      general: { modern: 'booking_default_modern_01', minimal: 'booking_default_minimal_01', luxury: 'booking_default_luxury_01', playful: 'booking_default_playful_01', corporate: 'booking_default_corporate_01' },
    },
  };

  const byType = matrix[websiteType];
  if (!byType) return FALLBACKS[websiteType];
  const byCategory = byType[category] ?? byType.general;
  if (!byCategory) return FALLBACKS[websiteType];
  return byCategory[style] ?? byCategory.modern ?? FALLBACKS[websiteType];
}
