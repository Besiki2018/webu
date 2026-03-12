import type { CtaVariantId } from './CTA.variants';

export interface CtaDefaultProps {
  title: string;
  subtitle: string;
  buttonLabel: string;
  buttonUrl: string;
  variant?: CtaVariantId;
  backgroundImage?: string;
  backgroundColor?: string;
  textColor?: string;
}

export const CTA_DEFAULTS: CtaDefaultProps = {
  title: 'Ready to get started?',
  subtitle: 'Join us today and see the difference.',
  buttonLabel: 'Get started',
  buttonUrl: '#',
  variant: 'cta-1',
  backgroundImage: '',
  backgroundColor: '',
  textColor: '',
};

export const CtaDefaults = CTA_DEFAULTS;
