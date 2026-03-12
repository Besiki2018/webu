/**
 * Grid section schema — builder fields and defaults.
 */

import { GRID_DEFAULTS } from './Grid.defaults';
import { GRID_VARIANTS } from './Grid.variants';

export const GridSchema = {
  name: 'Grid',
  category: 'sections',
  componentKey: 'webu_general_grid_01',
  icon: 'grid',
  props: {
    title: { type: 'text', label: 'Section title', default: GRID_DEFAULTS.title, group: 'content' },
    items: {
      type: 'repeater',
      label: 'Grid items',
      default: GRID_DEFAULTS.items,
      group: 'content',
      itemFields: [
        { path: 'image', type: 'image', label: 'Image', default: '' },
        { path: 'imageAlt', type: 'text', label: 'Image alt', default: '' },
        { path: 'title', type: 'text', label: 'Title', default: '' },
        { path: 'link', type: 'link', label: 'Link', default: '#' },
      ],
    },
    columns: {
      type: 'number',
      label: 'Columns',
      default: 3,
      min: 1,
      max: 6,
      group: 'layout',
    },
    variant: {
      type: 'select',
      label: 'Design variant',
      default: 'grid-1',
      options: GRID_VARIANTS.map((v) => ({ label: v.replace('grid-', 'Style '), value: v })),
      group: 'layout',
    },
    backgroundColor: { type: 'color', label: 'Background', default: '', group: 'style' },
    textColor: { type: 'color', label: 'Text color', default: '', group: 'style' },
    padding: { type: 'spacing', label: 'Padding', default: '', group: 'advanced' },
    spacing: { type: 'spacing', label: 'Spacing', default: '', group: 'advanced' },
  },
  defaults: { ...GRID_DEFAULTS },
  projectTypes: ['business', 'ecommerce', 'saas', 'portfolio', 'landing', 'blog', 'education'],
  capabilities: ['product', 'content', 'links'],
};

/** Editable schema for chat/sidebar: describes which fields can be edited. */
export const GridEditableSchema = {
  component: 'grid',
  editableFields: [
    { key: 'title', type: 'text' },
    { key: 'items', type: 'repeater' },
    { key: 'columns', type: 'number' },
    { key: 'variant', type: 'select' },
    { key: 'backgroundColor', type: 'color' },
    { key: 'textColor', type: 'color' },
    { key: 'padding', type: 'spacing' },
    { key: 'spacing', type: 'spacing' },
  ] as const,
};
