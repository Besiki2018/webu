export type BannerVariant = 'banner-1' | 'banner-2' | 'banner-3' | 'banner-4';

export interface WebuBannerProps {
  variant?: BannerVariant;
  title: string;
  subtitle?: string;
  ctaLabel?: string;
  ctaUrl?: string;
  backgroundImage?: string;
  basePath?: string;
  className?: string;
}
