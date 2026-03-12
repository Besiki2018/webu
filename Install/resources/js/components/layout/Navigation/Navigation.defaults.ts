import type { NavigationVariantId } from './Navigation.variants';

export interface NavigationDefaultProps {
  links: string;
  ariaLabel: string;
  variant?: NavigationVariantId;
  alignment: string;
  backgroundColor?: string;
  textColor?: string;
}

export const NAVIGATION_DEFAULTS: NavigationDefaultProps = {
  links: '[]',
  ariaLabel: 'Navigation',
  variant: 'navigation-1',
  alignment: 'left',
  backgroundColor: '',
  textColor: '',
};

export const NavigationDefaults = NAVIGATION_DEFAULTS;
