import { HERO_DEFAULTS } from '@/components/sections/Hero';
import { FOOTER_DEFAULTS } from '@/components/layout/Footer';

export const builderComponentDefaults = {
    hero: HERO_DEFAULTS,
    banner: {
        title: 'Make your next launch sharper',
        subtitle: 'Use the single-runtime builder to shape content, layout, and assets together.',
        ctaLabel: 'Start editing',
        ctaUrl: '#',
        backgroundImage: '',
        variant: 'banner-1',
    },
    newsletter: {
        title: 'Stay in the loop',
        text: 'Capture leads directly from the canvas.',
        placeholder: 'Enter your email',
        buttonLabel: 'Subscribe',
        variant: 'newsletter-1',
    },
    footer: FOOTER_DEFAULTS,
    section: {
        title: 'Section',
    },
    text: {
        text: 'Add your text here.',
    },
    image: {
        src: 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1200&q=80',
        alt: 'Builder placeholder',
    },
    button: {
        text: 'Click me',
        href: '#',
    },
    'legacy-section': {
        legacyType: 'legacy-section',
        title: 'Imported section',
        description: 'This block was mapped from the legacy CMS shape into the V2 document model.',
    },
} as const;
