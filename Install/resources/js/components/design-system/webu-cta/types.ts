export type CtaVariant = 'cta-1' | 'cta-2' | 'cta-3' | 'cta-4';

export interface WebuCtaProps {
  variant?: CtaVariant;
  /** From CMS */
  title: string;
  subtitle?: string;
  buttonLabel?: string;
  buttonUrl?: string;
  backgroundImage?: string;
  basePath?: string;
  className?: string;
  backgroundColor?: string;
  textColor?: string;
  padding?: string;
  spacing?: string;
}
