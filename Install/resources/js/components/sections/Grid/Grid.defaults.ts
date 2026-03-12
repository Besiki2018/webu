import type { GridVariantId } from './Grid.variants';

export interface GridItemDefault {
  image?: string;
  imageAlt?: string;
  title: string;
  link?: string;
}

export interface GridDefaultProps {
  title: string;
  items: GridItemDefault[];
  columns: number;
  variant?: GridVariantId;
  backgroundColor?: string;
  textColor?: string;
}

export const GRID_DEFAULTS: GridDefaultProps = {
  title: 'Grid',
  items: [
    { title: 'Item 1', link: '#' },
    { title: 'Item 2', link: '#' },
    { title: 'Item 3', link: '#' },
    { title: 'Item 4', link: '#' },
    { title: 'Item 5', link: '#' },
    { title: 'Item 6', link: '#' },
  ],
  columns: 3,
  variant: 'grid-1',
  backgroundColor: '',
  textColor: '',
};

export const GridDefaults = GRID_DEFAULTS;
