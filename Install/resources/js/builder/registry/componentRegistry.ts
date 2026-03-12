/**
 * Builder component registry — central registry for all builder components.
 * Canvas must use this registry to resolve and render components.
 * Each entry: component, schema, defaults; optional mapBuilderProps for prop mapping.
 * Schema may include projectTypes for component library filtering by project type.
 */

import type { ComponentType } from 'react';
import type { ProjectType } from '../projectTypes';
import {
  Header,
  HeaderSchema,
  HEADER_DEFAULTS,
} from '@/components/layout/Header';
import {
  Footer,
  FooterSchema,
  FOOTER_DEFAULTS,
} from '@/components/layout/Footer';
import {
  Hero,
  HeroSchema,
  HERO_DEFAULTS,
} from '@/components/sections/Hero';
import {
  Features,
  FeaturesSchema,
  FEATURES_DEFAULTS,
} from '@/components/sections/Features';
import {
  CTA,
  CtaSchema,
  CTA_DEFAULTS,
} from '@/components/sections/CTA';
import {
  Navigation,
  NavigationSchema,
  NAVIGATION_DEFAULTS,
} from '@/components/layout/Navigation';
import {
  Cards,
  CardsSchema,
  CARDS_DEFAULTS,
} from '@/components/sections/Cards';
import {
  Grid,
  GridSchema,
  GRID_DEFAULTS,
} from '@/components/sections/Grid';
import { WebuTestimonials } from '@/components/design-system/webu-testimonials';
import { WebuFaq } from '@/components/design-system/webu-faq';
import { WebuBanner } from '@/components/design-system/webu-banner';
import { WebuOffcanvasMenu } from '@/components/design-system/webu-offcanvas-menu';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------
export interface ComponentRegistryEntry<P = Record<string, unknown>> {
  component: ComponentType<P>;
  schema: Record<string, unknown>;
  defaults: Record<string, unknown>;
  mapBuilderProps?: (builderProps: Record<string, unknown>) => P;
}

function asRegistryRecord<T extends object>(value: T): Record<string, unknown> {
  return value as unknown as Record<string, unknown>;
}

function asRegistryComponent<P>(component: ComponentType<P>): ComponentType<Record<string, unknown>> {
  return component as unknown as ComponentType<Record<string, unknown>>;
}

// ---------------------------------------------------------------------------
// Helpers (builder prop → component prop mapping)
// ---------------------------------------------------------------------------
function parseMenu(value: unknown): Array<{ label: string; url: string; slug?: string }> {
  if (Array.isArray(value)) {
    return value
      .filter((item): item is Record<string, unknown> => !!item && typeof item === 'object')
      .map((item) => ({
        label: String(item.label ?? item.title ?? item.text ?? ''),
        url: String(item.url ?? item.href ?? '#'),
        slug: item.slug != null ? String(item.slug) : undefined,
      }))
      .filter((item) => item.label.trim() !== '');
  }
  if (typeof value === 'string' && value.trim() !== '') {
    try {
      const parsed: unknown = JSON.parse(value);
      return parseMenu(parsed);
    } catch {
      return [];
    }
  }
  return [];
}

function asString(value: unknown): string {
  return typeof value === 'string' ? value.trim() : '';
}

function parseRepeaterItems<T extends Record<string, unknown>>(value: unknown): T[] {
  if (Array.isArray(value)) {
    return value.filter((item): item is T => !!item && typeof item === 'object') as T[];
  }
  if (typeof value === 'string' && value.trim() !== '') {
    try {
      return parseRepeaterItems(JSON.parse(value));
    } catch {
      return [];
    }
  }
  return [];
}

function parseFooterMenus(links: unknown, socialLinks: unknown): Record<string, { label: string; url: string }[]> {
  const main = parseMenu(links).map((item) => ({ label: item.label, url: item.url }));
  const social = parseMenu(socialLinks).map((item) => ({ label: item.label, url: item.url }));
  const result: Record<string, { label: string; url: string }[]> = {};
  if (main.length > 0) result.links = main;
  if (social.length > 0) result.social = social;
  return result;
}

// ---------------------------------------------------------------------------
// Registry ID (section.type) → short key
// ---------------------------------------------------------------------------
export const REGISTRY_ID_TO_KEY: Record<string, string> = {
  webu_header_01: 'header',
  webu_footer_01: 'footer',
  webu_general_hero_01: 'hero',
  webu_general_features_01: 'features',
  webu_general_cta_01: 'cta',
  webu_general_navigation_01: 'navigation',
  webu_general_cards_01: 'cards',
  webu_general_grid_01: 'grid',
  webu_general_testimonials_01: 'testimonials',
  faq_accordion_plus: 'faq',
  webu_general_banner_01: 'banner',
  banner: 'banner',
  webu_general_offcanvas_menu_01: 'offcanvas',
};

/** Part 13 — Safety fallbacks: use only when a planned component is not in registry. */
export const DEFAULT_HERO_REGISTRY_ID = 'webu_general_hero_01';
export const DEFAULT_FEATURES_REGISTRY_ID = 'webu_general_features_01';
export const DEFAULT_FOOTER_REGISTRY_ID = 'webu_footer_01';

/** Part 11 — When a layout block cannot be mapped to a component, fall back to this generic section (e.g. GenericSection). Uses features component until a dedicated GenericSection is registered. */
export const DEFAULT_GENERIC_SECTION_REGISTRY_ID = 'webu_general_features_01';

// ---------------------------------------------------------------------------
// Central component registry — all components registered here
// ---------------------------------------------------------------------------
export const componentRegistry: Record<string, ComponentRegistryEntry> = {
  header: {
    component: asRegistryComponent(Header),
    schema: asRegistryRecord(HeaderSchema),
    defaults: asRegistryRecord(HEADER_DEFAULTS),
    mapBuilderProps: (p) => {
      const d = asRegistryRecord(HEADER_DEFAULTS);
      return {
        logo: asString(p.logoText) || asString(d.logoText) || 'Logo',
        logoFallback: asString(p.logoFallback) || asString(d.logoFallback) || 'Logo',
        logoUrl: asString(p.logo_url) || asString(d.logoUrl) || '/',
        logoImageUrl: asString(p.logo_url) || undefined,
        menu: parseMenu(p.menu_items),
        ctaLabel: asString(p.ctaText) || asString(d.ctaText) || undefined,
        ctaUrl: asString(p.ctaLink) || asString(d.ctaLink) || undefined,
        variant: asString(p.variant) || asString(d.variant) || 'header-1',
        backgroundColor: asString(p.backgroundColor) || asString(d.backgroundColor),
        textColor: asString(p.textColor) || asString(d.textColor),
        navAriaLabel: asString(p.navAriaLabel) || asString(d.navAriaLabel),
        menuDrawerFooterLabel: asString(p.menuDrawerFooterLabel) || asString(d.menuDrawerFooterLabel),
        menuDrawerFooterUrl: asString(p.menuDrawerFooterUrl) || asString(d.menuDrawerFooterUrl),
        sticky: p.sticky === true,
        alignment: (asString(p.alignment) || asString(d.alignment) || 'left') as 'left' | 'center' | 'right',
        showSearch: p.showSearch === true,
        searchMode: asString(p.searchMode) || asString(d.searchMode) || 'generic',
        showCartIcon: p.showCartIcon === true,
        showWishlistIcon: p.showWishlistIcon === true,
        ...p,
      };
    },
  },

  footer: {
    component: asRegistryComponent(Footer),
    schema: asRegistryRecord(FooterSchema),
    defaults: asRegistryRecord(FOOTER_DEFAULTS),
    mapBuilderProps: (p) => {
      const d = asRegistryRecord(FOOTER_DEFAULTS);
      return {
        logo: asString(p.logoText) || asString(d.logoText) || 'Footer',
        logoFallback: asString(p.logoFallback) || asString(d.logoFallback) || 'Store',
        logoUrl: asString(p.logoUrl) || asString(d.logoUrl) || '/',
        menus: parseFooterMenus(p.links, p.socialLinks),
        copyright: asString(p.copyright) || asString(d.copyright),
        contactAddress: asString(p.contactAddress) || asString(d.contactAddress),
        newsletterHeading: asString(p.newsletterHeading) || asString(d.newsletterHeading),
        newsletterCopy: asString(p.newsletterCopy) || asString(d.newsletterCopy),
        newsletterPlaceholder: asString(p.newsletterPlaceholder) || asString(d.newsletterPlaceholder),
        newsletterButtonLabel: asString(p.newsletterButtonLabel) || asString(d.newsletterButtonLabel),
        paymentsLabel: asString(p.paymentsLabel) || asString(d.paymentsLabel),
        paymentsAriaLabel: asString(p.paymentsAriaLabel) || asString(d.paymentsAriaLabel) || 'Payment methods',
        footerNavAriaLabel: asString(p.footerNavAriaLabel) || asString(d.footerNavAriaLabel) || 'Footer',
        paymentMethods: Array.isArray(p.paymentMethods) ? p.paymentMethods : (d.paymentMethods as unknown[]),
        variant: asString(p.variant) || asString(d.variant) || 'footer-1',
        backgroundColor: asString(p.backgroundColor) || asString(d.backgroundColor),
        textColor: asString(p.textColor) || asString(d.textColor),
        ...p,
      };
    },
  },

  hero: {
    component: asRegistryComponent(Hero),
    schema: asRegistryRecord(HeroSchema),
    defaults: asRegistryRecord(HERO_DEFAULTS),
    mapBuilderProps: (p) => {
      const d = asRegistryRecord(HERO_DEFAULTS);
      const statAvatars = Array.isArray(p.statAvatars)
        ? p.statAvatars
        : (typeof p.stat_avatars === 'string' && p.stat_avatars.trim() !== ''
          ? (() => { try { return JSON.parse(p.stat_avatars); } catch { return []; } })()
          : d.statAvatars);
      return {
        headline: asString(p.title) || asString(p.headline) || asString(d.title),
        title: asString(p.title) || asString(p.headline) || asString(d.title),
        subheading: asString(p.subtitle) || asString(p.subheading) || asString(d.subtitle),
        subtitle: asString(p.subtitle) || asString(p.subheading) || asString(d.subtitle),
        eyebrow: asString(p.eyebrow) || asString(d.eyebrow),
        badgeText: asString(p.badgeText) || asString(p.badge_text) || asString(d.badgeText),
        description: asString(p.description) || asString(d.description),
        ctaLabel: asString(p.buttonText) || asString(d.buttonText),
        ctaUrl: asString(p.buttonLink) || asString(d.buttonLink) || '#',
        ctaSecondaryLabel: asString(p.secondaryButtonText) || asString(d.secondaryButtonText) || undefined,
        ctaSecondaryUrl: asString(p.secondaryButtonLink) || asString(d.secondaryButtonLink) || undefined,
        imageUrl: asString(p.image) || asString(p.backgroundImage) || asString(d.image),
        imageAlt: asString(p.imageAlt) || asString(d.imageAlt),
        imageAltFallback: asString(p.imageAltFallback) || asString(d.imageAltFallback) || 'Hero',
        overlayImageUrl: asString(p.overlayImageUrl) || asString(p.overlay_image_url) || asString(d.overlayImageUrl),
        overlayImageAlt: asString(p.overlayImageAlt) || asString(p.overlay_image_alt) || asString(d.overlayImageAlt) || 'Overlay',
        statValue: asString(p.statValue) || asString(p.stat_value) || asString(d.statValue),
        statUnit: asString(p.statUnit) || asString(p.stat_unit) || asString(d.statUnit),
        statLabel: asString(p.statLabel) || asString(p.stat_label) || asString(d.statLabel),
        statAvatars: Array.isArray(statAvatars) ? statAvatars : d.statAvatars,
        backgroundImage: asString(p.backgroundImage) || asString(d.backgroundImage),
        variant: asString(p.variant) || asString(d.variant) || 'hero-1',
        alignment: (asString(p.alignment) || asString(d.alignment) || 'left') as 'left' | 'center' | 'right',
        backgroundColor: asString(p.backgroundColor) || asString(d.backgroundColor),
        textColor: asString(p.textColor) || asString(d.textColor),
        padding: asString(p.padding) || asString(d.padding),
        spacing: asString(p.spacing) || asString(d.spacing),
        ...p,
      };
    },
  },

  features: {
    component: asRegistryComponent(Features),
    schema: asRegistryRecord(FeaturesSchema),
    defaults: asRegistryRecord(FEATURES_DEFAULTS),
    mapBuilderProps: (p) => {
      const d = asRegistryRecord(FEATURES_DEFAULTS);
      const items = parseRepeaterItems<{ icon?: string; title: string; description?: string }>(p.items);
      return {
        title: asString(p.title) || asString(d.title) || 'Features',
        items: (Array.isArray(items) && items.length > 0 ? items : (d.items as { icon?: string; title: string; description?: string }[])) ?? [],
        variant: asString(p.variant) || asString(d.variant) || 'features-1',
        backgroundColor: asString(p.backgroundColor) || asString(d.backgroundColor),
        textColor: asString(p.textColor) || asString(d.textColor),
        ...p,
      };
    },
  },

  cta: {
    component: asRegistryComponent(CTA),
    schema: asRegistryRecord(CtaSchema),
    defaults: asRegistryRecord(CTA_DEFAULTS),
    mapBuilderProps: (p) => {
      const d = asRegistryRecord(CTA_DEFAULTS);
      return {
        title: asString(p.title) || asString(d.title) || 'Ready to get started?',
        subtitle: asString(p.subtitle) || asString(d.subtitle) || '',
        buttonLabel: asString(p.buttonLabel) || asString(p.buttonText) || asString(d.buttonLabel) || 'Get started',
        buttonUrl: asString(p.buttonUrl) || asString(p.buttonLink) || asString(d.buttonUrl) || '#',
        variant: asString(p.variant) || asString(d.variant) || 'cta-1',
        backgroundImage: asString(p.backgroundImage) || asString(d.backgroundImage),
        backgroundColor: asString(p.backgroundColor) || asString(d.backgroundColor),
        textColor: asString(p.textColor) || asString(d.textColor),
        padding: asString(p.padding) || asString(d.padding),
        spacing: asString(p.spacing) || asString(d.spacing),
        ...p,
      };
    },
  },

  navigation: {
    component: asRegistryComponent(Navigation),
    schema: asRegistryRecord(NavigationSchema),
    defaults: asRegistryRecord(NAVIGATION_DEFAULTS),
    mapBuilderProps: (p) => {
      const d = asRegistryRecord(NAVIGATION_DEFAULTS);
      const parsedLinks = parseMenu(p.links);
      return {
        links: parsedLinks.length > 0 ? parsedLinks : parseMenu(d.links),
        ariaLabel: asString(p.ariaLabel) || asString(d.ariaLabel) || 'Navigation',
        variant: asString(p.variant) || asString(d.variant) || 'navigation-1',
        alignment: (asString(p.alignment) || asString(d.alignment) || 'left') as 'left' | 'center' | 'right',
        backgroundColor: asString(p.backgroundColor) || asString(d.backgroundColor),
        textColor: asString(p.textColor) || asString(d.textColor),
        padding: asString(p.padding) || asString(d.padding),
        spacing: asString(p.spacing) || asString(d.spacing),
        ...p,
      };
    },
  },

  cards: {
    component: asRegistryComponent(Cards),
    schema: asRegistryRecord(CardsSchema),
    defaults: asRegistryRecord(CARDS_DEFAULTS),
    mapBuilderProps: (p) => {
      const d = asRegistryRecord(CARDS_DEFAULTS);
      const items = parseRepeaterItems<{ image?: string; imageAlt?: string; title: string; description?: string; link?: string }>(p.items);
      return {
        title: asString(p.title) || asString(d.title) || 'Cards',
        items: (Array.isArray(items) && items.length > 0 ? items : (d.items as { image?: string; imageAlt?: string; title: string; description?: string; link?: string }[])) ?? [],
        variant: asString(p.variant) || asString(d.variant) || 'cards-1',
        backgroundColor: asString(p.backgroundColor) || asString(d.backgroundColor),
        textColor: asString(p.textColor) || asString(d.textColor),
        padding: asString(p.padding) || asString(d.padding),
        spacing: asString(p.spacing) || asString(d.spacing),
        ...p,
      };
    },
  },

  testimonials: {
    component: asRegistryComponent(WebuTestimonials),
    schema: asRegistryRecord({
      name: 'Testimonials',
      category: 'sections',
      componentKey: 'webu_general_testimonials_01',
      props: {
        title: { type: 'text', label: 'Section title', default: 'Testimonials', group: 'content' },
        items: {
          type: 'repeater',
          label: 'Testimonials',
          default: [
            { user_name: 'Jane D.', avatar: '', text: 'Great product and support.', rating: 5 },
            { user_name: 'John S.', avatar: '', text: 'Exactly what we needed.', rating: 5 },
          ],
          group: 'content',
          itemFields: [
            { path: 'text', type: 'textarea', label: 'Quote' },
            { path: 'user_name', type: 'text', label: 'Author name' },
            { path: 'avatar', type: 'image', label: 'Avatar URL' },
            { path: 'rating', type: 'number', label: 'Rating' },
          ],
        },
      },
    }),
    defaults: asRegistryRecord({
      title: 'Testimonials',
      items: [
        { user_name: 'Jane D.', avatar: '', text: 'Great product and support.', rating: 5 },
        { user_name: 'John S.', avatar: '', text: 'Exactly what we needed.', rating: 5 },
      ],
      variant: 'testimonials-1',
    }),
    mapBuilderProps: (p) => {
      const itemsRaw = parseRepeaterItems<Record<string, unknown>>(p.items ?? p.testimonials);
      const items = itemsRaw.map((item) => ({
        user_name: asString(item.author ?? item.name ?? item.user_name) || 'Author',
        avatar: asString(item.image_url ?? item.avatar_url ?? item.avatar) || undefined,
        text: asString(item.quote ?? item.text ?? item.body) || '',
        rating: typeof item.rating === 'number' ? item.rating : undefined,
      }));
      return {
        title: asString(p.title) || 'Testimonials',
        items: items.length > 0 ? items : [{ user_name: 'Author', text: 'Quote text.', rating: 5 }],
        variant: asString(p.variant) || 'testimonials-1',
        ...p,
      };
    },
  },

  faq: {
    component: asRegistryComponent(WebuFaq),
    schema: asRegistryRecord({
      name: 'FAQ',
      category: 'sections',
      componentKey: 'faq_accordion_plus',
      props: {
        title: { type: 'text', label: 'Section title', default: 'FAQ', group: 'content' },
        items: {
          type: 'repeater',
          label: 'FAQ items',
          default: [
            { question: 'How do I get started?', answer: 'Follow the setup guide.' },
            { question: 'What are the pricing options?', answer: 'We offer flexible plans.' },
          ],
          group: 'content',
          itemFields: [
            { path: 'question', type: 'text', label: 'Question' },
            { path: 'answer', type: 'textarea', label: 'Answer' },
          ],
        },
      },
    }),
    defaults: asRegistryRecord({
      title: 'FAQ',
      items: [
        { question: 'How do I get started?', answer: 'Follow the setup guide.' },
        { question: 'What are the pricing options?', answer: 'We offer flexible plans.' },
      ],
      variant: 'faq-1',
    }),
    mapBuilderProps: (p) => {
      const itemsRaw = parseRepeaterItems<Record<string, unknown>>(p.items);
      const items = itemsRaw.map((item) => ({
        question: asString(item.q ?? item.question) || 'Question?',
        answer: asString(item.a ?? item.answer) || 'Answer.',
      }));
      return {
        title: asString(p.title) || 'FAQ',
        items: items.length > 0 ? items : [{ question: 'Question?', answer: 'Answer.' }],
        variant: asString(p.variant) || 'faq-1',
        ...p,
      };
    },
  },

  banner: {
    component: asRegistryComponent(WebuBanner),
    schema: asRegistryRecord({
      name: 'Banner',
      category: 'marketing',
      componentKey: 'webu_general_banner_01',
      props: {
        title: { type: 'text', label: 'Title', default: 'Banner title', group: 'content' },
        subtitle: { type: 'textarea', label: 'Subtitle', default: '', group: 'content' },
        ctaLabel: { type: 'text', label: 'Button label', default: 'Learn more', group: 'content' },
        ctaUrl: { type: 'link', label: 'Button URL', default: '#', group: 'content' },
      },
    }),
    defaults: asRegistryRecord({
      title: 'Banner title',
      subtitle: '',
      ctaLabel: 'Learn more',
      ctaUrl: '#',
      variant: 'banner-1',
    }),
    mapBuilderProps: (p) => {
      const d = { title: 'Banner title', subtitle: '', ctaLabel: 'Learn more', ctaUrl: '#' };
      return {
        title: asString(p.title ?? p.headline) || asString(d.title),
        subtitle: asString(p.subtitle ?? p.subheading) || asString(d.subtitle),
        ctaLabel: asString(p.cta_label ?? p.ctaLabel) || asString(d.ctaLabel),
        ctaUrl: asString(p.cta_url ?? p.ctaUrl) || asString(d.ctaUrl),
        variant: asString(p.variant) || 'banner-1',
        ...p,
      };
    },
  },

  offcanvas: {
    component: asRegistryComponent(WebuOffcanvasMenu),
    schema: asRegistryRecord({
      name: 'Offcanvas Menu',
      category: 'layout',
      componentKey: 'webu_general_offcanvas_menu_01',
      props: {
        trigger_label: { type: 'text', label: 'Trigger label', default: 'Open menu', group: 'content' },
        title: { type: 'text', label: 'Panel title', default: 'Shop navigation', group: 'content' },
        subtitle: { type: 'textarea', label: 'Panel subtitle', default: '', group: 'content' },
        menu_items: {
          type: 'menu',
          label: 'Menu items',
          default: [],
          group: 'content',
          itemFields: [
            { path: 'label', type: 'text', label: 'Label' },
            { path: 'url', type: 'link', label: 'URL' },
            { path: 'description', type: 'text', label: 'Description' },
          ],
        },
        footer_label: { type: 'text', label: 'Footer CTA label', default: 'Shop all', group: 'content' },
        footer_url: { type: 'link', label: 'Footer CTA URL', default: '/shop', group: 'content' },
      },
    }),
    defaults: asRegistryRecord({
      triggerLabel: 'Open menu',
      title: 'Shop navigation',
      subtitle: 'Reusable drawer for desktop hamburger and mobile navigation.',
      items: [
        { label: 'New arrivals', url: '/shop', description: 'Fresh seasonal edits' },
        { label: 'Outerwear', url: '/outerwear', description: 'Layering essentials' },
        { label: 'Contact', url: '/contact', description: 'Store support' },
      ],
      footerLabel: 'Shop all',
      footerUrl: '/shop',
      variant: 'drawer-1',
    }),
    mapBuilderProps: (p) => {
      const menuItems = parseRepeaterItems<{ label?: string; url?: string; description?: string }>(p.menu_items);
      const items = menuItems.length > 0
        ? menuItems.map((i) => ({
            label: asString(i.label) || 'Link',
            url: asString(i.url) || '#',
            description: asString(i.description) || undefined,
          }))
        : [
            { label: 'New arrivals', url: '/shop', description: 'Fresh seasonal edits' },
            { label: 'Outerwear', url: '/outerwear', description: 'Layering essentials' },
            { label: 'Contact', url: '/contact', description: 'Store support' },
          ];
      const d = {
        triggerLabel: 'Open menu',
        title: 'Shop navigation',
        subtitle: 'Reusable drawer for desktop hamburger and mobile navigation.',
        footerLabel: 'Shop all',
        footerUrl: '/shop',
      };
      return {
        triggerLabel: asString(p.trigger_label ?? p.triggerLabel) || asString(d.triggerLabel),
        title: asString(p.title) || asString(d.title),
        subtitle: asString(p.subtitle) || asString(d.subtitle),
        items,
        footerLabel: asString(p.footer_label ?? p.footerLabel) || asString(d.footerLabel),
        footerUrl: asString(p.footer_url ?? p.footerUrl) || asString(d.footerUrl),
        variant: asString(p.variant) || 'drawer-1',
        ...p,
      };
    },
  },

  grid: {
    component: asRegistryComponent(Grid),
    schema: asRegistryRecord(GridSchema),
    defaults: asRegistryRecord(GRID_DEFAULTS),
    mapBuilderProps: (p) => {
      const d = asRegistryRecord(GRID_DEFAULTS);
      const items = parseRepeaterItems<{ image?: string; imageAlt?: string; title: string; link?: string }>(p.items);
      const defaultColumns = typeof d.columns === 'number' ? d.columns : 3;
      return {
        title: asString(p.title) || asString(d.title) || 'Grid',
        items: (Array.isArray(items) && items.length > 0 ? items : (d.items as { image?: string; imageAlt?: string; title: string; link?: string }[])) ?? [],
        columns: typeof p.columns === 'number' ? p.columns : parseInt(String(p.columns), 10) || defaultColumns,
        variant: asString(p.variant) || asString(d.variant) || 'grid-1',
        backgroundColor: asString(p.backgroundColor) || asString(d.backgroundColor),
        textColor: asString(p.textColor) || asString(d.textColor),
        padding: asString(p.padding) || asString(d.padding),
        spacing: asString(p.spacing) || asString(d.spacing),
        ...p,
      };
    },
  },
};

// ---------------------------------------------------------------------------
// Lookup helpers — for canvas and inspector
// ---------------------------------------------------------------------------
/** Get registry key from component/registry ID (e.g. webu_header_01 -> 'header'). */
export function getRegistryKeyByComponentId(registryId: string): string | null {
  const key = REGISTRY_ID_TO_KEY[registryId?.trim() ?? ''];
  return key ?? null;
}

/** Get component registry entry by registry ID. Returns null if not in registry. */
export function getEntry(registryId: string): ComponentRegistryEntry | null {
  const key = getRegistryKeyByComponentId(registryId);
  if (!key) return null;
  return componentRegistry[key] ?? null;
}

/** Alias for getEntry (canvas may expect getCentralRegistryEntry). */
export function getCentralRegistryEntry(registryId: string): ComponentRegistryEntry | null {
  return getEntry(registryId);
}

/** Check if a registry ID has an entry in the component registry. */
export function hasEntry(registryId: string): boolean {
  return getRegistryKeyByComponentId(registryId) !== null;
}

/** Registry IDs (componentKey) that are compatible with the given project type. Used to filter the component library. */
export function getRegistryIdsForProjectType(projectType: ProjectType): string[] {
  const ids: string[] = [];
  for (const [registryId, key] of Object.entries(REGISTRY_ID_TO_KEY)) {
    const entry = componentRegistry[key];
    if (!entry?.schema || typeof entry.schema !== 'object') continue;
    const schema = entry.schema as { projectTypes?: ProjectType[] };
    const types = schema.projectTypes;
    if (!types || types.length === 0 || types.includes(projectType)) {
      ids.push(registryId);
    }
  }
  return ids;
}

/** True if this central-registry component is allowed for the given project type (for library filtering). */
export function isComponentAllowedForProjectType(registryId: string, projectType: ProjectType): boolean {
  const entry = getEntry(registryId);
  if (!entry?.schema || typeof entry.schema !== 'object') return true;
  const schema = entry.schema as { projectTypes?: ProjectType[] };
  const types = schema.projectTypes;
  if (!types || types.length === 0) return true;
  return types.includes(projectType);
}

// ---------------------------------------------------------------------------
// Part 7 — Runtime registration for generated components
// ---------------------------------------------------------------------------

/**
 * Registers a generated component at runtime so it appears in the builder library immediately.
 * Call after dynamically importing the generated section (e.g. from components/sections/Pricing).
 *
 * @param registryId — e.g. 'webu_general_pricing_01'
 * @param key — short key, e.g. 'pricing'
 * @param entry — { component, schema, defaults }
 */
export function registerGeneratedComponent(
  registryId: string,
  key: string,
  entry: ComponentRegistryEntry
): void {
  (REGISTRY_ID_TO_KEY as Record<string, string>)[registryId] = key;
  (componentRegistry as Record<string, ComponentRegistryEntry>)[key] = entry;
}
