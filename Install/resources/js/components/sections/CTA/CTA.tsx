/**
 * CTA — builder section with variants.
 * All content from props; no hardcoded values.
 */

import { WebuCta } from '@/components/design-system/webu-cta';
import type { WebuCtaProps } from '@/components/design-system/webu-cta/types';
import { CTA_DEFAULT_VARIANT, type CtaVariantId } from './CTA.variants';

/**
 * Props for the CTA section.
 * Main editable: title, subtitle, buttonLabel, buttonUrl, backgroundImage, backgroundColor, textColor.
 */
export interface CtaProps extends Omit<WebuCtaProps, 'title'> {
  title?: string;
  subtitle?: string;
  buttonLabel?: string;
  buttonUrl?: string;
  backgroundImage?: string;
  variant?: CtaVariantId;
  basePath?: string;
  className?: string;
  backgroundColor?: string;
  textColor?: string;
  padding?: string;
  spacing?: string;
}

export interface CtaSectionProps extends CtaProps {
  variant?: CtaVariantId;
}

export function CTA(props: CtaSectionProps) {
  const variant = (props.variant ?? CTA_DEFAULT_VARIANT) as CtaVariantId;
  return (
    <WebuCta
      variant={variant}
      title={props.title ?? ''}
      subtitle={props.subtitle}
      buttonLabel={props.buttonLabel}
      buttonUrl={props.buttonUrl}
      backgroundImage={props.backgroundImage}
      basePath={props.basePath}
      className={props.className}
      backgroundColor={props.backgroundColor}
      textColor={props.textColor}
      padding={props.padding}
      spacing={props.spacing}
    />
  );
}

export default CTA;
