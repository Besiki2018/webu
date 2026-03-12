import type { WebuHeroProps } from './types';
import { Hero1 } from './variants/hero-1';
import { Hero2 } from './variants/hero-2';
import { Hero3 } from './variants/hero-3';
import { Hero4 } from './variants/hero-4';
import { Hero5 } from './variants/hero-5';
import { Hero6 } from './variants/hero-6';
import { Hero7 } from './variants/hero-7';

const VARIANTS = ['hero-1', 'hero-2', 'hero-3', 'hero-4', 'hero-5', 'hero-6', 'hero-7'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'hero-1';

const variantMap = { Hero1, Hero2, Hero3, Hero4, Hero5, Hero6, Hero7 } as const;

export type { WebuHeroProps };

export function WebuHero({ variant, ...props }: WebuHeroProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  const key = `Hero${v.replace('hero-', '')}` as keyof typeof variantMap;
  const Component = variantMap[key] ?? Hero1;
  return <Component {...props} />;
}
