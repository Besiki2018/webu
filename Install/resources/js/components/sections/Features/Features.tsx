/**
 * Features — builder section with variants.
 * All content from props; no hardcoded values.
 */

import { WebuFeatures } from '@/components/design-system/webu-features';
import type { FeatureItem, WebuFeaturesProps } from '@/components/design-system/webu-features/types';
import { FEATURES_DEFAULT_VARIANT, type FeaturesVariantId } from './Features.variants';

/**
 * Props for the Features section.
 * Main editable: title, items (icon, title, description per item), backgroundColor, textColor.
 */
export interface FeaturesProps extends WebuFeaturesProps {
  title?: string;
  items: FeatureItem[];
  variant?: FeaturesVariantId;
  basePath?: string;
  className?: string;
  backgroundColor?: string;
  textColor?: string;
  padding?: string;
  spacing?: string;
}

export interface FeaturesSectionProps extends FeaturesProps {
  variant?: FeaturesVariantId;
}

export function Features(props: FeaturesSectionProps) {
  const variant = (props.variant ?? FEATURES_DEFAULT_VARIANT) as FeaturesVariantId;
  const items = Array.isArray(props.items) ? props.items : [];
  return (
    <WebuFeatures
      variant={variant}
      title={props.title}
      items={items}
      basePath={props.basePath}
      className={props.className}
      backgroundColor={props.backgroundColor}
      textColor={props.textColor}
      padding={props.padding}
      spacing={props.spacing}
    />
  );
}

export default Features;
