/**
 * Footer component schema — name, category, editable fields, variants, defaults, responsive support, style groups.
 * Consumed by componentRegistry (webu_footer_01) and builder UI.
 */

import type { BuilderComponentSchema, BuilderFieldDefinition } from '../../../builder/componentRegistry';
import type { ComponentSchemaDef } from '../../../builder/componentSchemaFormat';
import { getResponsiveFieldSpecs } from '../../../builder/responsiveFieldDefinitions';
import { FOOTER_DEFAULTS } from './Footer.defaults';
import { FOOTER_VARIANTS } from './Footer.variants';

/** Canonical Footer schema: component name, category, props, variants, defaults, responsive support, style groups */
export const FooterSchema: ComponentSchemaDef = {
  name: 'Footer',
  category: 'layout',
  componentKey: 'webu_footer_01',
  icon: 'layout',

  props: {
    logoText: { type: 'text', label: 'Logo text', default: 'Footer', group: 'content' },
    logoUrl: { type: 'link', label: 'Logo link', default: '/', group: 'content' },
    logoFallback: { type: 'text', label: 'Logo fallback', default: 'Store', group: 'content' },
    description: { type: 'richtext', label: 'Description', default: '', group: 'content' },
    subtitle: { type: 'text', label: 'Subtitle', default: '', group: 'content' },
    links: { type: 'menu', label: 'Footer links', default: '[]', group: 'content' },
    socialLinks: { type: 'menu', label: 'Social links', default: '[]', group: 'content' },
    copyright: { type: 'text', label: 'Copyright', default: '© 2024', group: 'content' },
    contactAddress: { type: 'text', label: 'Contact address', default: '', group: 'content' },
    newsletterHeading: { type: 'text', label: 'Newsletter heading', default: '', group: 'content' },
    newsletterCopy: { type: 'richtext', label: 'Newsletter copy', default: '', group: 'content' },
    newsletterPlaceholder: { type: 'text', label: 'Newsletter placeholder', default: 'Your email', group: 'content' },
    newsletterButtonLabel: { type: 'text', label: 'Subscribe button', default: 'Subscribe', group: 'content' },
    paymentsLabel: { type: 'text', label: 'Payments label', default: '', group: 'content' },
    paymentsAriaLabel: { type: 'text', label: 'Payments aria-label', default: 'Payment methods', group: 'content' },
    footerNavAriaLabel: { type: 'text', label: 'Footer nav aria-label', default: 'Footer', group: 'content' },
    paymentMethods: { type: 'repeater', label: 'Payment methods', default: [], group: 'content' },
    variant: {
      type: 'select',
      label: 'Design variant',
      default: 'footer-1',
      options: FOOTER_VARIANTS.map((v) => ({ label: `Footer ${v.replace('footer-', '')}`, value: v })),
      group: 'layout',
    },
    backgroundColor: { type: 'color', label: 'Background', default: '#111111', group: 'style' },
    textColor: { type: 'color', label: 'Text color', default: '#ffffff', group: 'style' },
    padding: { type: 'spacing', label: 'Padding', default: '', group: 'advanced' },
    spacing: { type: 'spacing', label: 'Spacing', default: '', group: 'advanced' },
  },

  variants: {
    design: [...FOOTER_VARIANTS],
  },

  defaults: { ...FOOTER_DEFAULTS },

  editableFields: [
    'logoText',
    'logoFallback',
    'description',
    'subtitle',
    'copyright',
    'links',
    'socialLinks',
    'backgroundColor',
    'textColor',
    'newsletterHeading',
    'newsletterCopy',
    'newsletterPlaceholder',
    'newsletterButtonLabel',
    'paymentsAriaLabel',
    'footerNavAriaLabel',
    'contactAddress',
  ],

  responsiveSupport: {
    enabled: true,
    breakpoints: ['desktop', 'tablet', 'mobile'],
    supportsVisibility: true,
    supportsOverrides: true,
  },

  styleGroups: [
    { key: 'style', label: 'Style', description: 'Colors', fields: ['backgroundColor', 'textColor'] },
    { key: 'layout', label: 'Layout', description: 'Variant', fields: ['variant'] },
  ],

  contentGroups: [
    { key: 'content', label: 'Content', description: 'Logo, links, newsletter', fields: ['logoText', 'logoUrl', 'logoFallback', 'description', 'subtitle', 'links', 'socialLinks', 'copyright', 'contactAddress', 'newsletterHeading', 'newsletterCopy', 'newsletterPlaceholder', 'newsletterButtonLabel', 'paymentsLabel', 'paymentsAriaLabel', 'footerNavAriaLabel', 'paymentMethods'] },
  ],

  advancedGroups: [
    { key: 'advanced', label: 'Advanced', description: 'Spacing', fields: ['padding', 'spacing'] },
  ],

  projectTypes: ['business', 'ecommerce', 'saas', 'portfolio', 'restaurant', 'hotel', 'blog', 'landing', 'education'],
  capabilities: ['navigation', 'newsletter', 'links'],
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

export const FOOTER_SCHEMA: BuilderComponentSchema = {
  schemaVersion: 2,
  componentKey: 'webu_footer_01',
  displayName: 'Footer',
  category: 'footer',
  icon: 'layout',
  responsive: true,
  defaultProps: { ...FOOTER_DEFAULTS },
  chatTargets: [
    'logoText',
    'logoFallback',
    'description',
    'subtitle',
    'copyright',
    'links',
    'socialLinks',
    'backgroundColor',
    'textColor',
    'newsletterHeading',
    'newsletterCopy',
    'newsletterPlaceholder',
    'newsletterButtonLabel',
    'paymentsAriaLabel',
    'footerNavAriaLabel',
    'contactAddress',
  ],
  fields: [
    field('logoText', 'Logo text', 'text', 'content', { default: 'Footer' }),
    field('logoUrl', 'Logo link', 'link', 'content', { default: '/' }),
    field('logoFallback', 'Logo fallback', 'text', 'content', { default: 'Store' }),
    field('description', 'Description', 'richtext', 'content', { default: '' }),
    field('subtitle', 'Subtitle', 'text', 'content', { default: '' }),
    field('links', 'Footer links', 'menu', 'content', { default: '[]' }),
    field('socialLinks', 'Social links', 'menu', 'content', { default: '[]' }),
    field('copyright', 'Copyright', 'text', 'content', { default: '© 2024' }),
    field('contactAddress', 'Contact address', 'text', 'content', { default: '' }),
    field('newsletterHeading', 'Newsletter heading', 'text', 'content', { default: '' }),
    field('newsletterCopy', 'Newsletter copy', 'richtext', 'content', { default: '' }),
    field('newsletterPlaceholder', 'Newsletter placeholder', 'text', 'content', { default: 'Your email' }),
    field('newsletterButtonLabel', 'Subscribe button', 'text', 'content', { default: 'Subscribe' }),
    field('paymentsLabel', 'Payments label', 'text', 'content', { default: '' }),
    field('paymentsAriaLabel', 'Payments aria-label', 'text', 'content', { default: 'Payment methods' }),
    field('footerNavAriaLabel', 'Footer nav aria-label', 'text', 'content', { default: 'Footer' }),
    field('paymentMethods', 'Payment methods', 'repeater', 'content', { default: [] }),
    field('backgroundColor', 'Background', 'color', 'style', { default: '#111111' }),
    field('textColor', 'Text color', 'color', 'style', { default: '#ffffff' }),
    field('padding', 'Padding', 'spacing', 'advanced', { default: '' }),
    field('spacing', 'Spacing', 'spacing', 'advanced', { default: '' }),
    field('variant', 'Design variant', 'layout-variant', 'layout', {
      default: 'footer-1',
      options: FOOTER_VARIANTS.map((v) => ({
        label: `Footer ${v.replace('footer-', '')}`,
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
export const FooterEditableSchema = {
  component: 'footer',
  editableFields: [
    { key: 'logoText', type: 'text' },
    { key: 'logoUrl', type: 'link' },
    { key: 'logoFallback', type: 'text' },
    { key: 'links', type: 'menu' },
    { key: 'socialLinks', type: 'menu' },
    { key: 'copyright', type: 'text' },
    { key: 'contactAddress', type: 'textarea' },
    { key: 'newsletterHeading', type: 'text' },
    { key: 'newsletterCopy', type: 'textarea' },
    { key: 'newsletterPlaceholder', type: 'text' },
    { key: 'newsletterButtonLabel', type: 'text' },
    { key: 'paymentsLabel', type: 'text' },
    { key: 'paymentsAriaLabel', type: 'text' },
    { key: 'footerNavAriaLabel', type: 'text' },
    { key: 'paymentMethods', type: 'repeater' },
    { key: 'variant', type: 'select' },
    { key: 'backgroundColor', type: 'color' },
    { key: 'textColor', type: 'color' },
    { key: 'padding', type: 'spacing' },
    { key: 'spacing', type: 'spacing' },
  ] as const,
};
