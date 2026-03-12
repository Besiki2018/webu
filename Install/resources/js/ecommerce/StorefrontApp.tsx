/**
 * Renders a SitePlan: theme + current page sections based on pathname.
 * Use inside an Inertia page; pathname is taken from window (or basePath-relative).
 */

import { useEffect, useState } from 'react';
import type { SitePlan } from '@/ecommerce/schema';
import { EcommerceThemeProvider } from '@/ecommerce/renderer/ThemeProvider';
import { PageRenderer } from '@/ecommerce/renderer/PageRenderer';
import { getPageForPathStrict } from '@/ecommerce/renderer/routeMatcher';

interface StorefrontAppProps {
  plan: SitePlan;
  basePath?: string;
  initialPath?: string;
}

function getCurrentPath(basePath: string): string {
  if (typeof window === 'undefined') return '/';
  const pathname = window.location.pathname;
  const base = basePath.replace(/\/$/, '');
  if (!base) return pathname || '/';
  if (pathname === base) return '/';
  if (pathname.startsWith(base + '/')) return pathname.slice(base.length) || '/';
  return pathname || '/';
}

export function StorefrontApp({ plan, basePath = '', initialPath }: StorefrontAppProps) {
  const [path, setPath] = useState(initialPath ?? getCurrentPath(basePath));

  useEffect(() => {
    setPath(getCurrentPath(basePath));
    const handlePopState = () => setPath(getCurrentPath(basePath));
    window.addEventListener('popstate', handlePopState);
    return () => window.removeEventListener('popstate', handlePopState);
  }, [basePath]);

  const page = getPageForPathStrict(plan.pages, path);

  if (!page) {
    return (
      <EcommerceThemeProvider theme={plan.theme}>
        <div className="webu-storefront-layout">
          <main className="flex flex-1 items-center justify-center">
            <div className="webu-container py-12 text-center">
              <p className="text-muted-foreground">Page not found.</p>
              <a href={basePath || '/'} className="text-primary underline mt-4 inline-block">Go home</a>
            </div>
          </main>
        </div>
      </EcommerceThemeProvider>
    );
  }

  return (
    <EcommerceThemeProvider theme={plan.theme}>
      <div className="webu-storefront-layout">
        <PageRenderer page={page} basePath={basePath} />
      </div>
    </EcommerceThemeProvider>
  );
}
