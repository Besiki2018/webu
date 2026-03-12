import type { CardsVariantId } from './Cards.variants';

export interface CardItemDefault {
  image?: string;
  imageAlt?: string;
  title: string;
  description?: string;
  link?: string;
}

export interface CardsDefaultProps {
  title: string;
  items: CardItemDefault[];
  variant?: CardsVariantId;
  backgroundColor?: string;
  textColor?: string;
}

export const CARDS_DEFAULTS: CardsDefaultProps = {
  title: 'Cards',
  items: [
    { title: 'Card 1', description: 'Description for card one.', link: '#' },
    { title: 'Card 2', description: 'Description for card two.', link: '#' },
    { title: 'Card 3', description: 'Description for card three.', link: '#' },
  ],
  variant: 'cards-1',
  backgroundColor: '',
  textColor: '',
};

export const CardsDefaults = CARDS_DEFAULTS;
