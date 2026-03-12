/**
 * Applies ecommerce storefront theme (colors, mode) to the subtree.
 */

import type { CSSProperties, ReactNode } from 'react';
import type { SitePlanTheme } from '@/ecommerce/schema';

interface EcommerceThemeProviderProps {
  theme: SitePlanTheme;
  children: ReactNode;
}

export function EcommerceThemeProvider({ theme, children }: EcommerceThemeProviderProps) {
  const mode = theme.mode ?? 'light';
  const style: Record<string, string> = {};
  if (theme.primaryColor) style['--primary'] = theme.primaryColor;
  if (theme.secondaryColor) style['--secondary'] = theme.secondaryColor;
  if (theme.fontFamily) style.fontFamily = theme.fontFamily;

  return (
    <div data-theme={mode} style={style}>
      {children}
    </div>
  );
}
