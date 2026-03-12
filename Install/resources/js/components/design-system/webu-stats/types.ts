export type StatsVariant = 'stats-1' | 'stats-2' | 'stats-3';

export interface StatItem {
  label: string;
  value: string;
}

export interface WebuStatsProps {
  variant?: StatsVariant;
  title?: string;
  items: StatItem[];
  basePath?: string;
  className?: string;
}
