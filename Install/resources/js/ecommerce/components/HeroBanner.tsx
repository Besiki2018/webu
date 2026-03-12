import { Link } from '@inertiajs/react';
import type { SectionInjectedProps } from './types';
import { path } from './types';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export interface HeroBannerProps extends SectionInjectedProps {
  title?: string;
  subtitle?: string;
  ctaText?: string;
  ctaUrl?: string;
  backgroundImage?: string;
  className?: string;
}

export function HeroBanner(props: HeroBannerProps) {
  const { basePath, title = 'Welcome to Our Store', subtitle = 'Discover amazing products', ctaText = 'Shop Now', ctaUrl = '/shop', backgroundImage, className } = props;
  return (
    <section
      className={cn('webu-banner', backgroundImage && 'webu-banner--has-bg', className)}
      style={backgroundImage ? { ['--webu-banner-bg' as string]: `url(${backgroundImage})` } : undefined}
    >
      {backgroundImage && <div className="absolute inset-0 z-0 bg-black/40" aria-hidden />}
      <div className="webu-banner__inner">
        <h1 className="webu-hero__title">{title}</h1>
        {subtitle && <p className="webu-hero__subtitle">{subtitle}</p>}
        {ctaText && (
          <div className="mt-8">
            <Button asChild size="lg">
              <Link href={path(basePath, ctaUrl)}>{ctaText}</Link>
            </Button>
          </div>
        )}
      </div>
    </section>
  );
}
