/**
 * Matches current pathname to SitePlan page routes.
 * Supports static routes and /product/:id.
 */

import type { SitePlanPage } from '@/ecommerce/schema';

export function getPageForPath(pages: SitePlanPage[], pathname: string): SitePlanPage | undefined {
  const normalized = pathname.replace(/\/$/, '') || '/';
  for (const page of pages) {
    if (page.route === normalized) return page;
    if (page.route === '/product/:id') {
      const match = normalized.match(/^\/product\/([^/]+)$/);
      if (match) return page;
    }
  }
  return undefined;
}

export function getPageForPathStrict(pages: SitePlanPage[], pathname: string): SitePlanPage | undefined {
  const normalized = pathname.replace(/\/$/, '') || '/';
  for (const page of pages) {
    if (page.route === normalized) return page;
    if (page.route === '/product/:id' && /^\/product\/[^/]+$/.test(normalized)) return page;
  }
  return undefined;
}
