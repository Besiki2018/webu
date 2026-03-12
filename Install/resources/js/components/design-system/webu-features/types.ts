export type FeaturesVariant = 'features-1' | 'features-2' | 'features-3' | 'features-4';

export interface FeatureItem {
  icon?: string;
  title: string;
  description?: string;
}

export interface WebuFeaturesProps {
  variant?: FeaturesVariant;
  /** From CMS */
  title?: string;
  items: FeatureItem[];
  basePath?: string;
  className?: string;
  backgroundColor?: string;
  textColor?: string;
  padding?: string;
  spacing?: string;
}
