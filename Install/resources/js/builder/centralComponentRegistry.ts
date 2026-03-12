/**
 * Central component registry — every builder component with component, schema, and defaults.
 * Canvas renderer uses this registry to resolve and render components.
 */

import type { ComponentType } from 'react';
import type { ComponentSchemaDef } from './componentSchemaFormat';

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

export interface ComponentRegistryEntry<P = Record<string, unknown>> {
  component: ComponentType<P>;
  schema: ComponentSchemaDef;
  defaults: Record<string, unknown>;
  /** Map builder props (from resolveComponentProps) to component props. Optional. */
  mapBuilderProps?: (builderProps: Record<string, unknown>) => P;
}

function asRegistryRecord<T extends object>(value: T): Record<string, unknown> {
  return value as unknown as Record<string, unknown>;
}

function asRegistryComponent<P>(component: ComponentType<P>): ComponentType<Record<string, unknown>> {
  return component as unknown as ComponentType<Record<string, unknown>>;
}

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

/** Registry ID (section.type) to short key */
export const REGISTRY_ID_TO_KEY: Record<string, string> = {
  webu_header_01: 'header',
  webu_footer_01: 'footer',
  webu_general_hero_01: 'hero',
};

/**
 * Central component registry.
 * Register every builder component: component, schema, defaults.
 */
export const componentRegistry: Record<string, ComponentRegistryEntry> = {
  header: {
    component: asRegistryComponent(Header),
    schema: HeaderSchema,
    defaults: asRegistryRecord(HEADER_DEFAULTS),
    mapBuilderProps: (p) => ({
      logo: asString(p.logoText) || 'Logo',
      logoUrl: asString(p.logo_url) || '/',
      logoImageUrl: asString(p.logo_url) || undefined,
      menu: parseMenu(p.menu_items),
      ctaLabel: asString(p.ctaText) || undefined,
      ctaUrl: asString(p.ctaLink) || undefined,
      variant: asString(p.variant) || 'header-1',
      backgroundColor: asString(p.backgroundColor),
      textColor: asString(p.textColor),
      navAriaLabel: asString(p.navAriaLabel),
      menuDrawerFooterLabel: asString(p.menuDrawerFooterLabel),
      menuDrawerFooterUrl: asString(p.menuDrawerFooterUrl),
      sticky: p.sticky === true,
      alignment: (asString(p.alignment) || 'left') as 'left' | 'center' | 'right',
      ...p,
    }),
  },

  footer: {
    component: asRegistryComponent(Footer),
    schema: FooterSchema,
    defaults: asRegistryRecord(FOOTER_DEFAULTS),
    mapBuilderProps: (p) => ({
      logo: asString(p.logoText) || 'Footer',
      logoUrl: asString(p.logoUrl) || '/',
      menus: parseFooterMenus(p.links, p.socialLinks),
      copyright: asString(p.copyright),
      contactAddress: asString(p.contactAddress),
      newsletterHeading: asString(p.newsletterHeading),
      newsletterCopy: asString(p.newsletterCopy),
      newsletterPlaceholder: asString(p.newsletterPlaceholder),
      newsletterButtonLabel: asString(p.newsletterButtonLabel),
      paymentsLabel: asString(p.paymentsLabel),
      paymentMethods: Array.isArray(p.paymentMethods) ? p.paymentMethods : [],
      variant: asString(p.variant) || 'footer-1',
      backgroundColor: asString(p.backgroundColor),
      textColor: asString(p.textColor),
      ...p,
    }),
  },

  hero: {
    component: asRegistryComponent(Hero),
    schema: HeroSchema,
    defaults: asRegistryRecord(HERO_DEFAULTS),
    mapBuilderProps: (p) => ({
      headline: asString(p.title) || asString(p.headline),
      title: asString(p.title) || asString(p.headline),
      subheading: asString(p.subtitle) || asString(p.subheading),
      subtitle: asString(p.subtitle) || asString(p.subheading),
      eyebrow: asString(p.eyebrow),
      description: asString(p.description),
      ctaLabel: asString(p.buttonText),
      ctaUrl: asString(p.buttonLink) || '#',
      ctaSecondaryLabel: asString(p.secondaryButtonText) || undefined,
      ctaSecondaryUrl: asString(p.secondaryButtonLink) || undefined,
      imageUrl: asString(p.image) || asString(p.backgroundImage),
      imageAlt: asString(p.imageAlt),
      backgroundImage: asString(p.backgroundImage),
      variant: asString(p.variant) || 'hero-1',
      alignment: (asString(p.alignment) || 'left') as 'left' | 'center' | 'right',
      backgroundColor: asString(p.backgroundColor),
      textColor: asString(p.textColor),
      ...p,
    }),
  },
};

function parseFooterMenus(links: unknown, socialLinks: unknown): Record<string, { label: string; url: string }[]> {
  const main = parseMenu(links).map((item) => ({ label: item.label, url: item.url }));
  const social = parseMenu(socialLinks).map((item) => ({ label: item.label, url: item.url }));
  const result: Record<string, { label: string; url: string }[]> = {};
  if (main.length > 0) result.links = main;
  if (social.length > 0) result.social = social;
  return result;
}

/**
 * Get registry key from component/registry ID (e.g. webu_header_01 -> 'header').
 */
export function getRegistryKeyByComponentId(registryId: string): string | null {
  const key = REGISTRY_ID_TO_KEY[registryId?.trim() ?? ''];
  return key ?? null;
}

/**
 * Get component registry entry by registry ID. Returns null if not in central registry.
 */
export function getCentralRegistryEntry(registryId: string): ComponentRegistryEntry | null {
  const key = getRegistryKeyByComponentId(registryId);
  if (!key) return null;
  return componentRegistry[key] ?? null;
}

/**
 * Check if a registry ID has an entry in the central component registry.
 */
export function isInCentralRegistry(registryId: string): boolean {
  return getRegistryKeyByComponentId(registryId) !== null;
}
