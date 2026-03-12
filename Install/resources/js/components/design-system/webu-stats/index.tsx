import type { WebuStatsProps, StatItem } from './types';
import { Stats1 } from './variants/stats-1';

const VARIANTS = ['stats-1', 'stats-2', 'stats-3'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'stats-1';

export type { WebuStatsProps, StatItem };

export function WebuStats({ variant, ...props }: WebuStatsProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Stats1 {...props} />;
}
