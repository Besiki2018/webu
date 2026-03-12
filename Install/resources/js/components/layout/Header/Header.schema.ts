/**
 * Header component schema — name, category, editable fields, variants, defaults, responsive support, style groups.
 * Consumed by componentRegistry (webu_header_01) and builder UI.
 */

import type { BuilderComponentSchema, BuilderFieldDefinition } from '../../../builder/componentRegistry';
import type { ComponentSchemaDef } from '../../../builder/componentSchemaFormat';
import { getResponsiveFieldSpecs } from '../../../builder/responsiveFieldDefinitions';
import { HEADER_DEFAULTS } from './Header.defaults';
import { HEADER_VARIANTS } from './Header.variants';

/** Canonical Header schema: component name, category, props, variants, defaults, responsive support, style groups */
export const HeaderSchema: ComponentSchemaDef = {
  name: 'Header',
  category: 'layout',
  componentKey: 'webu_header_01',
  icon: 'layout',

  props: {
    logo_url: { type: 'image', label: 'Logo image', default: '', group: 'content' },
    logo_alt: { type: 'text', label: 'Logo alt', default: '', group: 'content' },
    logoText: { type: 'text', label: 'Logo text', default: 'Webu', group: 'content' },
    logoFallback: { type: 'text', label: 'Logo fallback', default: 'Logo', group: 'content' },
    menu_items: { type: 'menu', label: 'Menu items', default: '[]', group: 'content' },
    ctaText: { type: 'text', label: 'CTA text', default: 'Get started', group: 'content' },
    ctaLink: { type: 'link', label: 'CTA link', default: '#', group: 'content' },
    menuDrawerFooterLabel: { type: 'text', label: 'Drawer footer label', default: '', group: 'content' },
    menuDrawerFooterUrl: { type: 'link', label: 'Drawer footer link', default: '/contact', group: 'content' },
    navAriaLabel: { type: 'text', label: 'Nav aria-label', default: 'Main navigation', group: 'advanced' },
    variant: {
      type: 'select',
      label: 'Design variant',
      default: 'header-1',
      options: HEADER_VARIANTS.map((v) => ({ label: `Header ${v.replace('header-', '')}`, value: v })),
      group: 'layout',
    },
    layoutVariant: {
      type: 'select',
      label: 'Layout variant',
      default: 'default',
      options: [
        { label: 'Default', value: 'default' },
        { label: 'Centered', value: 'centered' },
        { label: 'Split', value: 'split' },
      ],
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
    backgroundColor: { type: 'color', label: 'Background', default: '#ffffff', group: 'style' },
    textColor: { type: 'color', label: 'Text color', default: '#111111', group: 'style' },
    sticky: { type: 'boolean', label: 'Sticky header', default: false, group: 'advanced' },
    showSearch: { type: 'boolean', label: 'Show search', default: false, group: 'content' },
    searchMode: {
      type: 'select',
      label: 'Search mode',
      default: 'generic',
      options: [
        { label: 'None', value: 'none' },
        { label: 'Generic search', value: 'generic' },
        { label: 'Product search', value: 'product' },
      ],
      group: 'content',
    },
    showCartIcon: { type: 'boolean', label: 'Show cart icon', default: false, group: 'content' },
    showWishlistIcon: { type: 'boolean', label: 'Show wishlist icon', default: false, group: 'content' },
    searchPlaceholder: { type: 'text', label: 'Search placeholder', default: 'Search...', group: 'content' },
    announcementText: { type: 'text', label: 'Announcement bar text (header-6)', default: '', group: 'content' },
    announcementCtaLabel: { type: 'text', label: 'Announcement CTA label', default: '', group: 'content' },
    announcementCtaUrl: { type: 'link', label: 'Announcement CTA URL', default: '', group: 'content' },
    padding: { type: 'spacing', label: 'Padding', default: '', group: 'advanced' },
    spacing: { type: 'spacing', label: 'Spacing', default: '', group: 'advanced' },
  },

  variants: {
    layout: ['default', 'centered', 'split'],
    style: ['light', 'dark'],
    design: [...HEADER_VARIANTS],
  },

  defaults: { ...HEADER_DEFAULTS },

  editableFields: [
    'logoText',
    'logoFallback',
    'menu_items',
    'ctaText',
    'ctaLink',
    'showSearch',
    'searchMode',
    'showCartIcon',
    'showWishlistIcon',
    'backgroundColor',
    'textColor',
    'navAriaLabel',
    'menuDrawerFooterLabel',
    'alignment',
  ],

  responsiveSupport: {
    enabled: true,
    breakpoints: ['desktop', 'tablet', 'mobile'],
    supportsVisibility: true,
    supportsOverrides: true,
  },

  styleGroups: [
    { key: 'style', label: 'Style', description: 'Colors and visual style', fields: ['backgroundColor', 'textColor'] },
    { key: 'layout', label: 'Layout', description: 'Variant and alignment', fields: ['variant', 'layoutVariant', 'alignment'] },
  ],

  contentGroups: [
    { key: 'content', label: 'Content', description: 'Logo, menu, CTA', fields: ['logo_url', 'logo_alt', 'logoText', 'logoFallback', 'menu_items', 'ctaText', 'ctaLink', 'menuDrawerFooterLabel', 'menuDrawerFooterUrl', 'showSearch', 'searchMode', 'showCartIcon', 'showWishlistIcon', 'searchPlaceholder', 'announcementText', 'announcementCtaLabel', 'announcementCtaUrl'] },
  ],

  advancedGroups: [
    { key: 'advanced', label: 'Advanced', description: 'Behavior and spacing', fields: ['sticky', 'navAriaLabel', 'padding', 'spacing'] },
  ],

  projectTypes: ['business', 'ecommerce', 'saas', 'portfolio', 'restaurant', 'hotel', 'blog', 'landing', 'education'],
  capabilities: ['navigation', 'search'],
};

function field(
  path: string,
  label: string,
  type: BuilderFieldDefinition['type'],
  group: BuilderFieldDefinition['group'],
  extra: Partial<BuilderFieldDefinition> = {}
): BuilderFieldDefinition {
  return {
    path,
    label,
    type,
    group,
    chatEditable: true,
    bindingCompatible: true,
    ...extra,
  };
}

export const HEADER_SCHEMA: BuilderComponentSchema = {
  schemaVersion: 2,
  componentKey: 'webu_header_01',
  displayName: 'Header',
  category: 'header',
  icon: 'layout',
  responsive: true,
  defaultProps: { ...HEADER_DEFAULTS },
  variants: {
    layout: ['default', 'centered', 'split'],
    style: ['light', 'dark'],
  },
  chatTargets: [
    'logoText',
    'logoFallback',
    'menu_items',
    'ctaText',
    'ctaLink',
    'backgroundColor',
    'textColor',
    'sticky',
    'navAriaLabel',
    'menuDrawerFooterLabel',
    'alignment',
  ],
  fields: [
    field('logo_url', 'Logo image', 'image', 'content', { default: '' }),
    field('logo_alt', 'Logo alt', 'text', 'content', { default: '' }),
    field('logoText', 'Logo text', 'text', 'content', { default: 'Webu' }),
    field('logoFallback', 'Logo fallback', 'text', 'content', { default: 'Logo' }),
    field('menu_items', 'Menu items', 'menu', 'content', { default: '[]' }),
    field('ctaText', 'CTA text', 'text', 'content', { default: 'Get started' }),
    field('ctaLink', 'CTA link', 'link', 'content', { default: '#' }),
    field('backgroundColor', 'Background', 'color', 'style', { default: '#ffffff' }),
    field('textColor', 'Text color', 'color', 'style', { default: '#111111' }),
    field('sticky', 'Sticky header', 'boolean', 'advanced', { default: false }),
    field('navAriaLabel', 'Nav aria-label', 'text', 'advanced', { default: 'Main navigation' }),
    field('menuDrawerFooterLabel', 'Drawer footer label', 'text', 'content', { default: '' }),
    field('menuDrawerFooterUrl', 'Drawer footer link', 'link', 'content', { default: '/contact' }),
    field('searchPlaceholder', 'Search placeholder', 'text', 'content', { default: 'Search...' }),
    field('announcementText', 'Announcement bar text (header-6)', 'text', 'content', { default: '' }),
    field('announcementCtaLabel', 'Announcement CTA label', 'text', 'content', { default: '' }),
    field('announcementCtaUrl', 'Announcement CTA URL', 'link', 'content', { default: '' }),
    field('padding', 'Padding', 'spacing', 'advanced', { default: '' }),
    field('spacing', 'Spacing', 'spacing', 'advanced', { default: '' }),
    field('alignment', 'Alignment', 'alignment', 'layout', {
      default: 'left',
      options: [
        { label: 'Left', value: 'left' },
        { label: 'Center', value: 'center' },
        { label: 'Right', value: 'right' },
      ],
    }),
    field('layoutVariant', 'Layout variant', 'select', 'layout', {
      default: 'default',
      options: [
        { label: 'Default', value: 'default' },
        { label: 'Centered', value: 'centered' },
        { label: 'Split', value: 'split' },
      ],
    }),
    field('variant', 'Design variant', 'layout-variant', 'layout', {
      default: 'header-1',
      options: HEADER_VARIANTS.map((v) => ({
        label: `Header ${v.replace('header-', '')}`,
        value: v,
      })),
    }),
    ...getResponsiveFieldSpecs().map((spec) =>
      field(spec.path, spec.label, spec.type as BuilderFieldDefinition['type'], 'responsive', {
        default: spec.default,
        responsive: true,
        chatEditable: false,
        bindingCompatible: false,
      })
    ),
  ],
  responsiveSupport: {
    enabled: true,
    breakpoints: ['desktop', 'tablet', 'mobile'],
    supportsVisibility: true,
    supportsResponsiveOverrides: true,
    interactionStates: ['normal', 'hover', 'focus', 'active'],
  },
};

/** Editable schema for chat/sidebar: describes which fields can be edited. */
export const HeaderEditableSchema = {
  component: 'header',
  editableFields: [
    { key: 'logo_url', type: 'image' },
    { key: 'logo_alt', type: 'text' },
    { key: 'logoText', type: 'text' },
    { key: 'logoFallback', type: 'text' },
    { key: 'menu_items', type: 'menu' },
    { key: 'ctaText', type: 'text' },
    { key: 'ctaLink', type: 'link' },
    { key: 'menuDrawerFooterLabel', type: 'text' },
    { key: 'menuDrawerFooterUrl', type: 'link' },
    { key: 'navAriaLabel', type: 'text' },
    { key: 'variant', type: 'select' },
    { key: 'layoutVariant', type: 'select' },
    { key: 'alignment', type: 'alignment' },
    { key: 'backgroundColor', type: 'color' },
    { key: 'textColor', type: 'color' },
    { key: 'sticky', type: 'boolean' },
    { key: 'padding', type: 'spacing' },
    { key: 'spacing', type: 'spacing' },
  ] as const,
};
