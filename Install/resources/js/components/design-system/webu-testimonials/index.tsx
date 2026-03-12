import type { WebuTestimonialsProps, TestimonialItem } from './types';
import { Testimonials1 } from './variants/testimonials-1';

const VARIANTS = ['testimonials-1', 'testimonials-2', 'testimonials-3'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'testimonials-1';

export type { WebuTestimonialsProps, TestimonialItem };

export function WebuTestimonials({ variant, ...props }: WebuTestimonialsProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Testimonials1 {...props} />;
}
