/**
 * Renders AI layout JSON using Webu components and design-system CSS.
 * Resolves bindings from CMS data; applies variant. No raw HTML.
 */

import React from 'react';
import type { AILayoutSchema, CMSData } from './types';
import { resolveBindings } from './resolveBindings';
import { getComponent, getNewsletterPlaceholderProps } from './componentRegistry';

export interface LayoutRendererProps {
  layout: AILayoutSchema;
  cmsData: CMSData;
  basePath?: string;
  className?: string;
}

export function LayoutRenderer({ layout, cmsData, basePath = '', className }: LayoutRendererProps) {
  const sections = layout.sections ?? [];
  if (sections.length === 0) {
    return null;
  }

  return (
    <div className={className ?? 'webu-template webu-ai-layout'}>
      {sections.map((section, index) => (
        <LayoutSectionRenderer
          key={`${section.component}-${index}`}
          section={section}
          cmsData={cmsData}
          basePath={basePath}
        />
      ))}
    </div>
  );
}

interface LayoutSectionRendererProps {
  section: AILayoutSchema['sections'][number];
  cmsData: CMSData;
  basePath: string;
}

function LayoutSectionRenderer({ section, cmsData, basePath }: LayoutSectionRendererProps) {
  const componentKey = (section.component ?? '').replace(/_/g, '-').toLowerCase().trim();
  const entry = getComponent(componentKey);
  const resolved = resolveBindings(cmsData, section.bindings);

  if (!entry) {
    return (
      <section className="webu-placeholder-section" data-ai-component={section.component}>
        <p className="webu-muted">Unknown section: {section.component}</p>
      </section>
    );
  }

  const { Component, variantProp } = entry;
  const variant = section.variant ?? 'default';

  const props: Record<string, unknown> = {
    basePath,
    ...resolved,
  };
  if (variantProp) {
    props[variantProp] = variant;
  }
  if ((componentKey === 'hero' || componentKey === 'banner' || componentKey === 'cta') && resolved.image != null) {
    props.backgroundImage = resolved.image;
  }
  if (componentKey === 'newsletter') {
    Object.assign(props, getNewsletterPlaceholderProps(section, resolved));
  }

  return <Component {...props} />;
}
