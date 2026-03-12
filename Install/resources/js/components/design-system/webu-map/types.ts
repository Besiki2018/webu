export type MapVariant = 'map-1' | 'map-2';

export interface WebuMapProps {
  variant?: MapVariant;
  /** Embed URL or address for iframe (from CMS) */
  embedUrl?: string;
  address?: string;
  className?: string;
}
