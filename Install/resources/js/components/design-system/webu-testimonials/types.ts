export type TestimonialsVariant = 'testimonials-1' | 'testimonials-2' | 'testimonials-3';

export interface TestimonialItem {
  user_name: string;
  avatar?: string;
  rating?: number;
  text: string;
}

export interface WebuTestimonialsProps {
  variant?: TestimonialsVariant;
  /** From CMS */
  title?: string;
  items: TestimonialItem[];
  basePath?: string;
  className?: string;
}
