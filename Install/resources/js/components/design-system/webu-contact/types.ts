export type ContactVariant = 'contact-1' | 'contact-2' | 'contact-3';

export interface WebuContactProps {
  variant?: ContactVariant;
  title?: string;
  subtitle?: string;
  /** From CMS */
  email?: string;
  phone?: string;
  address?: string;
  basePath?: string;
  className?: string;
}
