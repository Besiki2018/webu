import type { WebuRegisterProps } from './types';
import { Register1 } from './variants/register-1';

const VARIANTS = ['register-1', 'register-2'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'register-1';

export type { WebuRegisterProps };

export function WebuRegister({ variant, ...props }: WebuRegisterProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Register1 {...props} />;
}
