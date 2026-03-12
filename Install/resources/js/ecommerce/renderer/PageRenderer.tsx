/**
 * Renders a storefront page: list of sections.
 * Layout: Section > .webu-container > Component (global layout system).
 */

import type { SitePlanPage } from '@/ecommerce/schema';
import { SectionRenderer } from './SectionRenderer';

export interface PageRendererProps {
  page: SitePlanPage;
  basePath?: string;
}

export function PageRenderer({ page, basePath }: PageRendererProps) {
  return (
    <main>
      {page.sections.map((section) => (
        <section key={section.id} className="webu-section">
          <div className="webu-container">
            <SectionRenderer
              section={section}
              basePath={basePath}
              pageRoute={page.route}
            />
          </div>
        </section>
      ))}
    </main>
  );
}
