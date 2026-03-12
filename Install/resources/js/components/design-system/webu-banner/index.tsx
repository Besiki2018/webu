import type { WebuBannerProps } from './types';
import { Banner1 } from './variants/banner-1';
import { Banner2 } from './variants/banner-2';

const VARIANTS = ['banner-1', 'banner-2', 'banner-3', 'banner-4'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'banner-1';

export type { WebuBannerProps };

export function WebuBanner({ variant, ...props }: WebuBannerProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  if (v === 'banner-2' || v === 'banner-3' || v === 'banner-4') return <Banner2 {...props} />;
  return <Banner1 {...props} />;
}
