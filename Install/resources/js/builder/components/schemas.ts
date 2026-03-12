import type { BuilderFieldSchema } from '@/builder/types/builderSchema';

export const sharedStyleFields: BuilderFieldSchema[] = [
    {
        key: 'backgroundColor',
        label: 'Background',
        control: 'color',
        target: 'styles',
    },
    {
        key: 'textColor',
        label: 'Text Color',
        control: 'color',
        target: 'styles',
    },
    {
        key: 'padding',
        label: 'Padding',
        control: 'spacing',
        target: 'styles',
        placeholder: '32px 24px',
    },
];

export const builderComponentSchemas = {
    hero: [
        { key: 'title', label: 'Title', control: 'text', target: 'props' },
        { key: 'subtitle', label: 'Subtitle', control: 'text', target: 'props' },
        { key: 'description', label: 'Description', control: 'textarea', target: 'props' },
        { key: 'buttonText', label: 'Primary Button', control: 'text', target: 'props' },
        { key: 'buttonLink', label: 'Primary Link', control: 'link', target: 'props' },
        { key: 'image', label: 'Image', control: 'image', target: 'props' },
        {
            key: 'variant',
            label: 'Variant',
            control: 'select',
            target: 'props',
            options: [
                { label: 'Hero 1', value: 'hero-1' },
                { label: 'Hero 2', value: 'hero-2' },
                { label: 'Hero 3', value: 'hero-3' },
            ],
        },
        ...sharedStyleFields,
    ] satisfies BuilderFieldSchema[],
    banner: [
        { key: 'title', label: 'Title', control: 'text', target: 'props' },
        { key: 'subtitle', label: 'Subtitle', control: 'textarea', target: 'props' },
        { key: 'ctaLabel', label: 'CTA Label', control: 'text', target: 'props' },
        { key: 'ctaUrl', label: 'CTA URL', control: 'link', target: 'props' },
        { key: 'backgroundImage', label: 'Background Image', control: 'image', target: 'props' },
        {
            key: 'variant',
            label: 'Variant',
            control: 'select',
            target: 'props',
            options: [
                { label: 'Banner 1', value: 'banner-1' },
                { label: 'Banner 2', value: 'banner-2' },
            ],
        },
        ...sharedStyleFields,
    ] satisfies BuilderFieldSchema[],
    newsletter: [
        { key: 'title', label: 'Title', control: 'text', target: 'props' },
        { key: 'text', label: 'Body', control: 'textarea', target: 'props' },
        { key: 'placeholder', label: 'Placeholder', control: 'text', target: 'props' },
        { key: 'buttonLabel', label: 'Button Label', control: 'text', target: 'props' },
        {
            key: 'variant',
            label: 'Variant',
            control: 'select',
            target: 'props',
            options: [
                { label: 'Newsletter 1', value: 'newsletter-1' },
                { label: 'Newsletter 2', value: 'newsletter-2' },
            ],
        },
        ...sharedStyleFields,
    ] satisfies BuilderFieldSchema[],
    footer: [
        { key: 'copyright', label: 'Copyright', control: 'text', target: 'props' },
        { key: 'description', label: 'Description', control: 'textarea', target: 'props' },
        { key: 'contactAddress', label: 'Address', control: 'text', target: 'props' },
        { key: 'newsletterHeading', label: 'Newsletter Heading', control: 'text', target: 'props' },
        { key: 'newsletterCopy', label: 'Newsletter Copy', control: 'textarea', target: 'props' },
        ...sharedStyleFields,
    ] satisfies BuilderFieldSchema[],
    section: [
        { key: 'title', label: 'Label', control: 'text', target: 'props' },
        ...sharedStyleFields,
    ] satisfies BuilderFieldSchema[],
    text: [
        { key: 'text', label: 'Text', control: 'textarea', target: 'props' },
        { key: 'fontSize', label: 'Font Size', control: 'text', target: 'styles' },
        { key: 'textColor', label: 'Text Color', control: 'color', target: 'styles' },
    ] satisfies BuilderFieldSchema[],
    image: [
        { key: 'src', label: 'Image', control: 'image', target: 'props' },
        { key: 'alt', label: 'Alt Text', control: 'text', target: 'props' },
        { key: 'borderRadius', label: 'Radius', control: 'spacing', target: 'styles' },
    ] satisfies BuilderFieldSchema[],
    button: [
        { key: 'text', label: 'Label', control: 'text', target: 'props' },
        { key: 'href', label: 'Link', control: 'link', target: 'props' },
        { key: 'backgroundColor', label: 'Background', control: 'color', target: 'styles' },
        { key: 'textColor', label: 'Text Color', control: 'color', target: 'styles' },
    ] satisfies BuilderFieldSchema[],
    'legacy-section': [
        { key: 'title', label: 'Title', control: 'text', target: 'props' },
        { key: 'description', label: 'Description', control: 'textarea', target: 'props' },
        { key: 'legacyType', label: 'Legacy Type', control: 'text', target: 'props' },
        ...sharedStyleFields,
    ] satisfies BuilderFieldSchema[],
} as const;
