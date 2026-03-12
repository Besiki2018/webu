/**
 * Hero section schema — name, category, editable fields, variants, defaults, responsive support, style groups.
 * Consumed by componentRegistry (webu_general_hero_01) and builder UI.
 */

import type { BuilderComponentSchema, BuilderFieldDefinition } from '../../../builder/componentRegistry';
import type { ComponentSchemaDef } from '../../../builder/componentSchemaFormat';
import { getResponsiveFieldSpecs } from '../../../builder/responsiveFieldDefinitions';
import { HERO_DEFAULTS } from './Hero.defaults';
import { HERO_VARIANTS } from './Hero.variants';

/** Canonical Hero schema: component name, category, props, variants, defaults, responsive support, style groups. Phase 5: category + projectTypes + capabilities for library. */
export const HeroSchema: ComponentSchemaDef = {
  name: 'Hero Section',
  category: 'hero',
  componentKey: 'webu_general_hero_01',
  icon: 'hero',

  props: {
    eyebrow: { type: 'text', label: 'Eyebrow', default: HERO_DEFAULTS.eyebrow, group: 'content' },
    badgeText: { type: 'text', label: 'Badge text', default: HERO_DEFAULTS.badgeText, group: 'content' },
    title: { type: 'text', label: 'Title', default: HERO_DEFAULTS.title, group: 'content' },
    subtitle: { type: 'text', label: 'Subtitle', default: HERO_DEFAULTS.subtitle, group: 'content' },
    description: { type: 'richtext', label: 'Description', default: HERO_DEFAULTS.description, group: 'content' },
    buttonText: { type: 'text', label: 'Primary button text', default: HERO_DEFAULTS.buttonText, group: 'content' },
    buttonLink: { type: 'link', label: 'Primary button link', default: HERO_DEFAULTS.buttonLink, group: 'content' },
    secondaryButtonText: { type: 'text', label: 'Secondary button text', default: HERO_DEFAULTS.secondaryButtonText, group: 'content' },
    secondaryButtonLink: { type: 'link', label: 'Secondary button link', default: HERO_DEFAULTS.secondaryButtonLink, group: 'content' },
    image: { type: 'image', label: 'Image', default: HERO_DEFAULTS.image, group: 'content' },
    imageAlt: { type: 'text', label: 'Image alt', default: HERO_DEFAULTS.imageAlt, group: 'content' },
    imageAltFallback: { type: 'text', label: 'Image alt fallback', default: HERO_DEFAULTS.imageAltFallback, group: 'content' },
    overlayImageUrl: { type: 'image', label: 'Overlay image URL', default: HERO_DEFAULTS.overlayImageUrl, group: 'content' },
    overlayImageAlt: { type: 'text', label: 'Overlay image alt', default: HERO_DEFAULTS.overlayImageAlt, group: 'content' },
    statValue: { type: 'text', label: 'Stat value (hero-7)', default: HERO_DEFAULTS.statValue, group: 'content' },
    statUnit: { type: 'text', label: 'Stat unit (hero-7)', default: HERO_DEFAULTS.statUnit, group: 'content' },
    statLabel: { type: 'text', label: 'Stat label (hero-7)', default: HERO_DEFAULTS.statLabel, group: 'content' },
    statAvatars: { type: 'repeater', label: 'Stat avatars (hero-7)', default: HERO_DEFAULTS.statAvatars, group: 'content', itemFields: [{ path: 'url', type: 'image', label: 'Image URL' }, { path: 'alt', type: 'text', label: 'Alt text' }] },
    backgroundImage: { type: 'image', label: 'Background image', default: HERO_DEFAULTS.backgroundImage, group: 'style' },
    backgroundColor: { type: 'color', label: 'Background color', default: '', group: 'style' },
    textColor: { type: 'color', label: 'Text color', default: '', group: 'style' },
    variant: {
      type: 'select',
      label: 'Design variant',
      default: 'hero-1',
      options: HERO_VARIANTS.map((v) => ({ label: `Hero ${v.replace('hero-', '')}`, value: v })),
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
    padding: { type: 'spacing', label: 'Padding', default: '', group: 'advanced' },
    spacing: { type: 'spacing', label: 'Spacing', default: '', group: 'advanced' },
  },

  variants: {
    layout: ['split', 'centered', 'image-left'],
    style: ['default', 'soft', 'contrast'],
    design: [...HERO_VARIANTS],
  },

  defaults: { ...HERO_DEFAULTS },

  editableFields: [
    'eyebrow',
    'badgeText',
    'title',
    'subtitle',
    'description',
    'buttonText',
    'buttonLink',
    'secondaryButtonText',
    'secondaryButtonLink',
    'image',
    'imageAlt',
    'imageAltFallback',
    'overlayImageUrl',
    'overlayImageAlt',
    'statValue',
    'statUnit',
    'statLabel',
    'statAvatars',
    'backgroundImage',
    'backgroundColor',
    'textColor',
    'alignment',
    'padding',
    'spacing',
  ],

  responsiveSupport: {
    enabled: true,
    breakpoints: ['desktop', 'tablet', 'mobile'],
    supportsVisibility: true,
    supportsOverrides: true,
  },

  styleGroups: [
    { key: 'style', label: 'Style', description: 'Background and visuals', fields: ['backgroundImage', 'backgroundColor', 'textColor'] },
    { key: 'layout', label: 'Layout', description: 'Variant and alignment', fields: ['variant', 'alignment'] },
  ],

  contentGroups: [
    { key: 'content', label: 'Content', description: 'Headline, copy, buttons, image', fields: ['eyebrow', 'badgeText', 'title', 'subtitle', 'description', 'buttonText', 'buttonLink', 'secondaryButtonText', 'secondaryButtonLink', 'image', 'imageAlt', 'imageAltFallback', 'overlayImageUrl', 'overlayImageAlt', 'statValue', 'statUnit', 'statLabel', 'statAvatars'] },
  ],

  advancedGroups: [
    { key: 'advanced', label: 'Advanced', description: 'Spacing', fields: ['padding', 'spacing'] },
  ],

  projectTypes: ['business', 'saas', 'landing'],
  capabilities: ['headline', 'callToAction', 'cta', 'image'],
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

export const HERO_SCHEMA: BuilderComponentSchema = {
  schemaVersion: 2,
  componentKey: 'webu_general_hero_01',
  displayName: 'Hero Section',
  category: 'hero',
  icon: 'hero',
  responsive: true,
  defaultProps: { ...HERO_DEFAULTS },
  variants: {
    layout: ['split', 'centered', 'image-left'],
    style: ['default', 'soft', 'contrast'],
  },
  chatTargets: [
    'eyebrow',
    'badgeText',
    'title',
    'subtitle',
    'description',
    'buttonText',
    'buttonLink',
    'secondaryButtonText',
    'secondaryButtonLink',
    'image',
    'imageAlt',
    'imageAltFallback',
    'overlayImageUrl',
    'overlayImageAlt',
    'statValue',
    'statUnit',
    'statLabel',
    'statAvatars',
    'backgroundImage',
    'backgroundColor',
    'textColor',
    'alignment',
    'padding',
    'spacing',
  ],
  fields: [
    field('eyebrow', 'Eyebrow', 'text', 'content', { default: HERO_DEFAULTS.eyebrow }),
    field('title', 'Title', 'text', 'content', { default: HERO_DEFAULTS.title }),
    field('subtitle', 'Subtitle', 'text', 'content', { default: HERO_DEFAULTS.subtitle }),
    field('description', 'Description', 'richtext', 'content', { default: HERO_DEFAULTS.description }),
    field('buttonText', 'Primary button text', 'text', 'content', { default: HERO_DEFAULTS.buttonText }),
    field('buttonLink', 'Primary button link', 'link', 'content', { default: HERO_DEFAULTS.buttonLink }),
    field('secondaryButtonText', 'Secondary button text', 'text', 'content', { default: HERO_DEFAULTS.secondaryButtonText }),
    field('secondaryButtonLink', 'Secondary button link', 'link', 'content', { default: HERO_DEFAULTS.secondaryButtonLink }),
    field('image', 'Image', 'image', 'content', { default: HERO_DEFAULTS.image }),
    field('imageAlt', 'Image alt', 'text', 'content', { default: HERO_DEFAULTS.imageAlt }),
    field('imageAltFallback', 'Image alt fallback', 'text', 'content', { default: HERO_DEFAULTS.imageAltFallback }),
    field('overlayImageUrl', 'Overlay image URL', 'image', 'content', { default: HERO_DEFAULTS.overlayImageUrl }),
    field('overlayImageAlt', 'Overlay image alt', 'text', 'content', { default: HERO_DEFAULTS.overlayImageAlt }),
    field('statValue', 'Stat value (hero-7)', 'text', 'content', { default: HERO_DEFAULTS.statValue }),
    field('statUnit', 'Stat unit (hero-7)', 'text', 'content', { default: HERO_DEFAULTS.statUnit }),
    field('statLabel', 'Stat label (hero-7)', 'text', 'content', { default: HERO_DEFAULTS.statLabel }),
    field('statAvatars', 'Stat avatars (hero-7)', 'repeater', 'content', {
      default: HERO_DEFAULTS.statAvatars,
      itemFields: [
        field('url', 'Image URL', 'image', 'content', { default: '' }),
        field('alt', 'Alt text', 'text', 'content', { default: '' }),
      ],
    }),
    field('backgroundImage', 'Background image', 'image', 'style', { default: HERO_DEFAULTS.backgroundImage }),
    field('backgroundColor', 'Background color', 'color', 'style', { default: '' }),
    field('textColor', 'Text color', 'color', 'style', { default: '' }),
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
    field('variant', 'Design variant', 'layout-variant', 'layout', {
      default: 'hero-1',
      options: HERO_VARIANTS.map((v) => ({
        label: `Hero ${v.replace('hero-', '')}`,
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
export const HeroEditableSchema = {
  component: 'hero',
  editableFields: [
    { key: 'eyebrow', type: 'text' },
    { key: 'title', type: 'text' },
    { key: 'subtitle', type: 'textarea' },
    { key: 'description', type: 'textarea' },
    { key: 'buttonText', type: 'text' },
    { key: 'buttonLink', type: 'link' },
    { key: 'secondaryButtonText', type: 'text' },
    { key: 'secondaryButtonLink', type: 'link' },
    { key: 'image', type: 'image' },
    { key: 'imageAlt', type: 'text' },
    { key: 'imageAltFallback', type: 'text' },
    { key: 'overlayImageAlt', type: 'text' },
    { key: 'backgroundImage', type: 'image' },
    { key: 'alignment', type: 'alignment' },
    { key: 'variant', type: 'select' },
    { key: 'backgroundColor', type: 'color' },
    { key: 'textColor', type: 'color' },
    { key: 'padding', type: 'spacing' },
    { key: 'spacing', type: 'spacing' },
  ] as const,
};
