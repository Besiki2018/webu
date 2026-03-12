import type { WebuFeaturesProps, FeatureItem } from './types';
import { Features1 } from './variants/features-1';

const VARIANTS = ['features-1', 'features-2', 'features-3', 'features-4'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'features-1';

export type { WebuFeaturesProps, FeatureItem };

export function WebuFeatures({ variant, ...props }: WebuFeaturesProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Features1 {...props} />;
}
