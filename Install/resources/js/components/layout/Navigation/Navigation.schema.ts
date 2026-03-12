/**
 * Navigation block schema — builder fields and defaults.
 */

import { NAVIGATION_DEFAULTS } from './Navigation.defaults';
import { NAVIGATION_VARIANTS } from './Navigation.variants';

export const NavigationSchema = {
  name: 'Navigation',
  category: 'layout',
  componentKey: 'webu_general_navigation_01',
  icon: 'menu',
  props: {
    links: { type: 'menu', label: 'Links', default: NAVIGATION_DEFAULTS.links, group: 'content' },
    ariaLabel: { type: 'text', label: 'Aria label', default: NAVIGATION_DEFAULTS.ariaLabel, group: 'content' },
    variant: {
      type: 'select',
      label: 'Design variant',
      default: 'navigation-1',
      options: NAVIGATION_VARIANTS.map((v) => ({ label: v.replace('navigation-', 'Style '), value: v })),
      group: 'layout',
    },
    alignment: {
      type: 'alignment',
      label: 'Alignment',
      default: 'left',
      options: [
        { label: 'Left', value: 'left' },
        { label: 'Center', value: 'center' },
        { label: 'Right', value: 'right' },
      ],
      group: 'layout',
    },
    backgroundColor: { type: 'color', label: 'Background', default: '', group: 'style' },
    textColor: { type: 'color', label: 'Text color', default: '', group: 'style' },
    padding: { type: 'spacing', label: 'Padding', default: '', group: 'advanced' },
    spacing: { type: 'spacing', label: 'Spacing', default: '', group: 'advanced' },
  },
  defaults: { ...NAVIGATION_DEFAULTS },
  projectTypes: ['business', 'ecommerce', 'saas', 'portfolio', 'restaurant', 'hotel', 'blog', 'landing', 'education'],
  capabilities: ['navigation'],
};

/** Editable schema for chat/sidebar: describes which fields can be edited. */
export const NavigationEditableSchema = {
  component: 'navigation',
  editableFields: [
    { key: 'links', type: 'menu' },
    { key: 'ariaLabel', type: 'text' },
    { key: 'variant', type: 'select' },
    { key: 'alignment', type: 'alignment' },
    { key: 'backgroundColor', type: 'color' },
    { key: 'textColor', type: 'color' },
    { key: 'padding', type: 'spacing' },
    { key: 'spacing', type: 'spacing' },
  ] as const,
};
