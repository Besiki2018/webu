/**
 * CTA section schema — builder fields and defaults.
 */

import { CTA_DEFAULTS } from './CTA.defaults';
import { CTA_VARIANTS } from './CTA.variants';

export const CtaSchema = {
  name: 'CTA',
  category: 'sections',
  componentKey: 'webu_general_cta_01',
  icon: 'button',
  props: {
    title: { type: 'text', label: 'Title', default: CTA_DEFAULTS.title, group: 'content' },
    subtitle: { type: 'textarea', label: 'Subtitle', default: CTA_DEFAULTS.subtitle, group: 'content' },
    buttonLabel: { type: 'text', label: 'Button label', default: CTA_DEFAULTS.buttonLabel, group: 'content' },
    buttonUrl: { type: 'link', label: 'Button link', default: CTA_DEFAULTS.buttonUrl, group: 'content' },
    variant: {
      type: 'select',
      label: 'Design variant',
      default: 'cta-1',
      options: CTA_VARIANTS.map((v) => ({ label: v.replace('cta-', 'Style '), value: v })),
      group: 'layout',
    },
    backgroundImage: { type: 'image', label: 'Background image', default: '', group: 'style' },
    backgroundColor: { type: 'color', label: 'Background', default: '', group: 'style' },
    textColor: { type: 'color', label: 'Text color', default: '', group: 'style' },
    padding: { type: 'spacing', label: 'Padding', default: '', group: 'advanced' },
    spacing: { type: 'spacing', label: 'Spacing', default: '', group: 'advanced' },
  },
  defaults: { ...CTA_DEFAULTS },
  projectTypes: ['business', 'saas', 'landing', 'portfolio', 'blog', 'education'],
  capabilities: ['cta', 'login'],
};

/** Editable schema for chat/sidebar: describes which fields can be edited. */
export const CtaEditableSchema = {
  component: 'cta',
  editableFields: [
    { key: 'title', type: 'text' },
    { key: 'subtitle', type: 'textarea' },
    { key: 'buttonLabel', type: 'text' },
    { key: 'buttonUrl', type: 'link' },
    { key: 'backgroundImage', type: 'image' },
    { key: 'variant', type: 'select' },
    { key: 'backgroundColor', type: 'color' },
    { key: 'textColor', type: 'color' },
    { key: 'padding', type: 'spacing' },
    { key: 'spacing', type: 'spacing' },
  ] as const,
};
