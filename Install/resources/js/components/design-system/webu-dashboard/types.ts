export type DashboardVariant = 'dashboard-1' | 'dashboard-2';

export interface WebuDashboardProps {
  variant?: DashboardVariant;
  userName?: string;
  /** From CMS: menu items, orders count, etc. */
  menuItems?: { label: string; url: string }[];
  basePath?: string;
  className?: string;
}
