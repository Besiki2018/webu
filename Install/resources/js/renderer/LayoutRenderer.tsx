/**
 * Layout Renderer – builds page from AI-generated layout JSON.
 * Reads layout JSON → resolves component → loads CMS data → renders component.
 * No raw HTML; all sections are React components with variants.
 */

import React from 'react';
import { resolveComponent } from './componentRegistry';
import type { LayoutPage, LayoutSection } from '@/types/layoutSchema';

export interface LayoutRendererProps {
  layout: LayoutPage;
  /** Optional: override base path for links */
  basePath?: string;
}

export function LayoutRenderer({ layout }: LayoutRendererProps): React.ReactElement {
  const sections = Array.isArray(layout.sections) ? layout.sections : [];

  return (
    <div className="webu-layout" data-page={layout.page}>
      {sections.map((section: LayoutSection, index: number) => (
        <React.Fragment key={section.id ?? `section-${index}`}>
          {resolveComponent(section)}
        </React.Fragment>
      ))}
    </div>
  );
}

export default LayoutRenderer;
