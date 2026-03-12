import type { WebuDashboardProps } from './types';
import { Dashboard1 } from './variants/dashboard-1';

const VARIANTS = ['dashboard-1', 'dashboard-2'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'dashboard-1';

export type { WebuDashboardProps };

export function WebuDashboard({ variant, ...props }: WebuDashboardProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Dashboard1 {...props} />;
}
