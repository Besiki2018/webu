/**
 * Navigation — builder layout block (nav with links).
 * All content from props; no hardcoded values.
 */

import { Link } from '@inertiajs/react';
import { NAVIGATION_DEFAULT_VARIANT, type NavigationVariantId } from './Navigation.variants';

export interface NavLink {
  label: string;
  url: string;
  slug?: string;
}

/**
 * Props for the Navigation component.
 * Main editable: links (label, url), ariaLabel, variant, alignment, backgroundColor, textColor.
 */
export interface NavigationProps {
  links?: NavLink[] | string;
  ariaLabel?: string;
  variant?: NavigationVariantId;
  alignment?: 'left' | 'center' | 'right';
  backgroundColor?: string;
  textColor?: string;
  padding?: string;
  spacing?: string;
  className?: string;
}

function parseLinks(value: unknown): NavLink[] {
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
      return parseLinks(JSON.parse(value));
    } catch {
      return [];
    }
  }
  return [];
}

export function Navigation(props: NavigationProps) {
  const links = Array.isArray(props.links) ? props.links : parseLinks(props.links);
  const variant = (props.variant ?? NAVIGATION_DEFAULT_VARIANT) as NavigationVariantId;
  const alignment = props.alignment ?? 'left';
  const style: React.CSSProperties = {};
  if (props.backgroundColor) style.backgroundColor = props.backgroundColor;
  if (props.textColor) style.color = props.textColor;
  if (props.padding) style.padding = props.padding;
  if (props.spacing) style.margin = props.spacing;

  return (
    <nav
      className={`navigation navigation--${variant} flex gap-4 ${alignment === 'center' ? 'justify-center' : alignment === 'right' ? 'justify-end' : 'justify-start'}`}
      aria-label={props.ariaLabel ?? 'Navigation'}
      style={Object.keys(style).length ? style : undefined}
    >
      {links.map((link, i) => (
        <Link key={i} href={link.url} className="navigation__link text-inherit hover:underline" data-webu-field-scope={`links.${i}`} data-webu-field="label" data-webu-field-url="url">
          {link.label}
        </Link>
      ))}
    </nav>
  );
}

export default Navigation;
