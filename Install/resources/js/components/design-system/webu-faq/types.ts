export type FaqVariant = 'faq-1' | 'faq-2';

export interface FaqItem {
  question: string;
  answer: string;
}

export interface WebuFaqProps {
  variant?: FaqVariant;
  title?: string;
  items: FaqItem[];
  basePath?: string;
  className?: string;
}
