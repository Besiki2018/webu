import type { CSSProperties, PropsWithChildren } from 'react';
import { Hero } from '@/components/sections/Hero';
import { Footer } from '@/components/layout/Footer';
import { WebuBanner } from '@/components/design-system/webu-banner';
import { WebuNewsletter } from '@/components/design-system/webu-newsletter';
import type { BannerVariant } from '@/components/design-system/webu-banner/types';
import type { NewsletterVariant } from '@/components/design-system/webu-newsletter/types';

function buildStyle(props: Record<string, unknown>): CSSProperties {
    const style: CSSProperties = {};

    if (typeof props.backgroundColor === 'string' && props.backgroundColor.trim() !== '') {
        style.backgroundColor = props.backgroundColor;
    }
    if (typeof props.textColor === 'string' && props.textColor.trim() !== '') {
        style.color = props.textColor;
    }
    if (typeof props.padding === 'string' && props.padding.trim() !== '') {
        style.padding = props.padding;
    }
    if (typeof props.borderRadius === 'string' && props.borderRadius.trim() !== '') {
        style.borderRadius = props.borderRadius;
    }
    if (typeof props.fontSize === 'string' && props.fontSize.trim() !== '') {
        style.fontSize = props.fontSize;
    }

    return style;
}

export function HeroRenderer(props: Record<string, unknown>) {
    return (
        <div style={buildStyle(props)}>
            <Hero {...props} />
        </div>
    );
}

export function BannerRenderer(props: Record<string, unknown>) {
    return (
        <div style={buildStyle(props)}>
            <WebuBanner
                variant={typeof props.variant === 'string' ? props.variant as BannerVariant : undefined}
                title={typeof props.title === 'string' ? props.title : 'Banner'}
                subtitle={typeof props.subtitle === 'string' ? props.subtitle : undefined}
                ctaLabel={typeof props.ctaLabel === 'string' ? props.ctaLabel : undefined}
                ctaUrl={typeof props.ctaUrl === 'string' ? props.ctaUrl : undefined}
                backgroundImage={typeof props.backgroundImage === 'string' ? props.backgroundImage : undefined}
            />
        </div>
    );
}

export function NewsletterRenderer(props: Record<string, unknown>) {
    return (
        <div style={buildStyle(props)}>
            <WebuNewsletter
                variant={typeof props.variant === 'string' ? props.variant as NewsletterVariant : undefined}
                title={typeof props.title === 'string' ? props.title : undefined}
                text={typeof props.text === 'string' ? props.text : undefined}
                placeholder={typeof props.placeholder === 'string' ? props.placeholder : undefined}
                buttonLabel={typeof props.buttonLabel === 'string' ? props.buttonLabel : undefined}
            />
        </div>
    );
}

export function FooterRenderer(props: Record<string, unknown>) {
    return (
        <div style={buildStyle(props)}>
            <Footer {...props} />
        </div>
    );
}

export function SectionRenderer({ children, ...props }: PropsWithChildren<Record<string, unknown>>) {
    return (
        <section
            style={buildStyle(props)}
            className="rounded-3xl border border-dashed border-slate-300 bg-white/70 p-8 shadow-sm"
        >
            {children}
        </section>
    );
}

export function TextRenderer(props: Record<string, unknown>) {
    return (
        <p style={buildStyle(props)} className="text-pretty text-base leading-7 text-slate-700">
            {typeof props.text === 'string' && props.text.trim() !== '' ? props.text : 'Text block'}
        </p>
    );
}

export function ImageRenderer(props: Record<string, unknown>) {
    const src = typeof props.src === 'string' && props.src.trim() !== ''
        ? props.src
        : 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1200&q=80';

    return (
        <img
            src={src}
            alt={typeof props.alt === 'string' ? props.alt : 'Builder image'}
            style={buildStyle(props)}
            className="block h-auto w-full max-w-full rounded-2xl object-cover"
        />
    );
}

export function ButtonRenderer(props: Record<string, unknown>) {
    return (
        <a
            href={typeof props.href === 'string' ? props.href : '#'}
            style={buildStyle(props)}
            className="inline-flex min-h-11 items-center justify-center rounded-full bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:opacity-90"
        >
            {typeof props.text === 'string' && props.text.trim() !== '' ? props.text : 'Button'}
        </a>
    );
}

export function LegacySectionRenderer(props: Record<string, unknown>) {
    const title = typeof props.title === 'string' && props.title.trim() !== ''
        ? props.title
        : (typeof props.headline === 'string' ? props.headline : 'Imported legacy section');

    return (
        <section
            style={buildStyle(props)}
            className="rounded-3xl border border-amber-300 bg-amber-50 p-8 shadow-sm"
        >
            <div className="mb-3 inline-flex rounded-full border border-amber-400 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-amber-700">
                {typeof props.legacyType === 'string' ? props.legacyType : 'legacy'}
            </div>
            <h3 className="text-xl font-semibold text-slate-900">{title}</h3>
            <p className="mt-2 text-sm leading-6 text-slate-600">
                {typeof props.description === 'string' && props.description.trim() !== ''
                    ? props.description
                    : 'This block was imported from the legacy CMS structure. It is editable in V2 and rendered entirely in the builder runtime.'}
            </p>
        </section>
    );
}
