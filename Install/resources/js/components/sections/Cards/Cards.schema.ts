/**
 * Cards section schema — builder fields and defaults.
 */

import { CARDS_DEFAULTS } from './Cards.defaults';
import { CARDS_VARIANTS } from './Cards.variants';

export const CardsSchema = {
  name: 'Cards',
  category: 'sections',
  componentKey: 'webu_general_cards_01',
  icon: 'grid',
  props: {
    title: { type: 'text', label: 'Section title', default: CARDS_DEFAULTS.title, group: 'content' },
    items: {
      type: 'repeater',
      label: 'Cards',
      default: CARDS_DEFAULTS.items,
      group: 'content',
      itemFields: [
        { path: 'image', type: 'image', label: 'Image', default: '' },
        { path: 'imageAlt', type: 'text', label: 'Image alt', default: '' },
        { path: 'title', type: 'text', label: 'Title', default: '' },
        { path: 'description', type: 'textarea', label: 'Description', default: '' },
        { path: 'link', type: 'link', label: 'Link', default: '#' },
      ],
    },
    variant: {
      type: 'select',
      label: 'Design variant',
      default: 'cards-1',
      options: CARDS_VARIANTS.map((v) => ({ label: v.replace('cards-', 'Style '), value: v })),
      group: 'layout',
    },
    backgroundColor: { type: 'color', label: 'Background', default: '', group: 'style' },
    textColor: { type: 'color', label: 'Text color', default: '', group: 'style' },
    padding: { type: 'spacing', label: 'Padding', default: '', group: 'advanced' },
    spacing: { type: 'spacing', label: 'Spacing', default: '', group: 'advanced' },
  },
  defaults: { ...CARDS_DEFAULTS },
  projectTypes: ['business', 'ecommerce', 'saas', 'portfolio', 'landing', 'blog', 'education'],
  capabilities: ['product', 'cart', 'content', 'links'],
};

/** Editable schema for chat/sidebar: describes which fields can be edited. */
export const CardsEditableSchema = {
  component: 'cards',
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
