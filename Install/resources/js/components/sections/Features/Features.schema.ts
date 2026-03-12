/**
 * Features section schema — builder fields and defaults.
 */

import { FEATURES_DEFAULTS } from './Features.defaults';
import { FEATURES_VARIANTS } from './Features.variants';

export const FeaturesSchema = {
  name: 'Features',
  category: 'sections',
  componentKey: 'webu_general_features_01',
  icon: 'grid',
  props: {
    title: { type: 'text', label: 'Section title', default: FEATURES_DEFAULTS.title, group: 'content' },
    items: {
      type: 'repeater',
      label: 'Feature items',
      default: FEATURES_DEFAULTS.items,
      group: 'content',
      itemFields: [
        { path: 'icon', type: 'text', label: 'Icon', default: '' },
        { path: 'title', type: 'text', label: 'Title', default: '' },
        { path: 'description', type: 'textarea', label: 'Description', default: '' },
      ],
    },
    variant: {
      type: 'select',
      label: 'Design variant',
      default: 'features-1',
      options: FEATURES_VARIANTS.map((v) => ({ label: v.replace('features-', 'Style '), value: v })),
      group: 'layout',
    },
    backgroundColor: { type: 'color', label: 'Background', default: '', group: 'style' },
    textColor: { type: 'color', label: 'Text color', default: '', group: 'style' },
    padding: { type: 'spacing', label: 'Padding', default: '', group: 'advanced' },
    spacing: { type: 'spacing', label: 'Spacing', default: '', group: 'advanced' },
  },
  defaults: { ...FEATURES_DEFAULTS },
  projectTypes: ['business', 'saas', 'landing', 'portfolio', 'education'],
  capabilities: ['content', 'features'],
};

/** Editable schema for chat/sidebar: describes which fields can be edited. */
export const FeaturesEditableSchema = {
  component: 'features',
  editableFields: [
    { key: 'title', type: 'text' },
    { key: 'items', type: 'repeater' },
    { key: 'variant', type: 'select' },
    { key: 'backgroundColor', type: 'color' },
    { key: 'textColor', type: 'color' },
    { key: 'padding', type: 'spacing' },
    { key: 'spacing', type: 'spacing' },
  ] as const,
};
