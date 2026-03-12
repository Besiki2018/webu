import type { FeaturesVariantId } from './Features.variants';

export interface FeatureItemDefault {
  icon?: string;
  title: string;
  description?: string;
}

export interface FeaturesDefaultProps {
  title: string;
  items: FeatureItemDefault[];
  variant?: FeaturesVariantId;
  backgroundColor?: string;
  textColor?: string;
}

export const FEATURES_DEFAULTS: FeaturesDefaultProps = {
  title: 'Features',
  items: [
    { title: 'Feature 1', description: 'Description for feature one.' },
    { title: 'Feature 2', description: 'Description for feature two.' },
    { title: 'Feature 3', description: 'Description for feature three.' },
  ],
  variant: 'features-1',
  backgroundColor: '',
  textColor: '',
};

export const FeaturesDefaults = FEATURES_DEFAULTS;
