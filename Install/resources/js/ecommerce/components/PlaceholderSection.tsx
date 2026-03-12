import type { SectionInjectedProps } from './types';
import { cn } from '@/lib/utils';

interface PlaceholderSectionProps extends SectionInjectedProps {
  type?: string;
  title?: string;
  className?: string;
}

export function PlaceholderSection({ type = 'placeholder', title, className }: PlaceholderSectionProps) {
  return (
    <section className={cn('webu-placeholder-section', className)}>
      <p className="webu-muted">
        {title ?? `Section: ${type}`} (placeholder)
      </p>
    </section>
  );
}
