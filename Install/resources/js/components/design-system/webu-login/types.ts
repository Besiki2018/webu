export type LoginVariant = 'login-1' | 'login-2';

export interface WebuLoginProps {
  variant?: LoginVariant;
  action?: string;
  redirectUrl?: string;
  registerUrl?: string;
  forgotUrl?: string;
  basePath?: string;
  /** From CMS */
  title?: string;
  labels?: Record<string, string>;
  className?: string;
}
