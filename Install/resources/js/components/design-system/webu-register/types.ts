export type RegisterVariant = 'register-1' | 'register-2';

export interface WebuRegisterProps {
  variant?: RegisterVariant;
  action?: string;
  loginUrl?: string;
  basePath?: string;
  /** From CMS */
  title?: string;
  labels?: Record<string, string>;
  className?: string;
}
