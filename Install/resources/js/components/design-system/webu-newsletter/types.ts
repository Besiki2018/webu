export type NewsletterVariant = 'newsletter-1' | 'newsletter-2' | 'newsletter-3';

export interface WebuNewsletterProps {
  variant?: NewsletterVariant;
  title?: string;
  text?: string;
  placeholder?: string;
  buttonLabel?: string;
  basePath?: string;
  className?: string;
}
