import type { WebuLoginProps } from './types';
import { Login1 } from './variants/login-1';

const VARIANTS = ['login-1', 'login-2'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'login-1';

export type { WebuLoginProps };

export function WebuLogin({ variant, ...props }: WebuLoginProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Login1 {...props} />;
}
