import type { WebuMapProps } from './types';
import { Map1 } from './variants/map-1';

const VARIANTS = ['map-1', 'map-2'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'map-1';

export type { WebuMapProps };

export function WebuMap({ variant, ...props }: WebuMapProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Map1 {...props} />;
}
