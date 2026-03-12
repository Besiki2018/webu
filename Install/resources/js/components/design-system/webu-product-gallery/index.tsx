import type { WebuProductGalleryProps } from './types';
import { Gallery1 } from './variants/gallery-1';

const VARIANTS = ['gallery-1', 'gallery-2', 'gallery-3'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'gallery-1';

export type { WebuProductGalleryProps };

export function WebuProductGallery({ variant, ...props }: WebuProductGalleryProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Gallery1 {...props} />;
}
