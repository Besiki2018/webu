import type { BuilderComponentDefinition } from '@/builder/types/builderComponent';
import { builderComponentCategories } from './categories';
import { builderComponentDefaults } from './defaults';
import { builderComponentSchemas } from './schemas';
import {
    BannerRenderer,
    ButtonRenderer,
    FooterRenderer,
    HeroRenderer,
    ImageRenderer,
    LegacySectionRenderer,
    NewsletterRenderer,
    SectionRenderer,
    TextRenderer,
} from './renderers';

export const builderComponentRegistry: Record<string, BuilderComponentDefinition> = {
    hero: {
        key: 'hero',
        label: 'Hero',
        category: builderComponentCategories.marketing,
        defaultProps: { ...builderComponentDefaults.hero },
        schema: [...builderComponentSchemas.hero],
        renderer: HeroRenderer,
    },
    banner: {
        key: 'banner',
        label: 'Banner',
        category: builderComponentCategories.marketing,
        defaultProps: { ...builderComponentDefaults.banner },
        schema: [...builderComponentSchemas.banner],
        renderer: BannerRenderer,
    },
    newsletter: {
        key: 'newsletter',
        label: 'Newsletter',
        category: builderComponentCategories.marketing,
        defaultProps: { ...builderComponentDefaults.newsletter },
        schema: [...builderComponentSchemas.newsletter],
        renderer: NewsletterRenderer,
    },
    footer: {
        key: 'footer',
        label: 'Footer',
        category: builderComponentCategories.footer,
        defaultProps: { ...builderComponentDefaults.footer },
        schema: [...builderComponentSchemas.footer],
        renderer: FooterRenderer,
    },
    section: {
        key: 'section',
        label: 'Section',
        category: builderComponentCategories.layout,
        defaultProps: { ...builderComponentDefaults.section },
        schema: [...builderComponentSchemas.section],
        renderer: SectionRenderer,
        allowedChildren: ['component', 'text', 'image', 'button'],
    },
    text: {
        key: 'text',
        label: 'Text',
        category: builderComponentCategories.content,
        defaultProps: { ...builderComponentDefaults.text },
        schema: [...builderComponentSchemas.text],
        renderer: TextRenderer,
    },
    image: {
        key: 'image',
        label: 'Image',
        category: builderComponentCategories.content,
        defaultProps: { ...builderComponentDefaults.image },
        schema: [...builderComponentSchemas.image],
        renderer: ImageRenderer,
    },
    button: {
        key: 'button',
        label: 'Button',
        category: builderComponentCategories.content,
        defaultProps: { ...builderComponentDefaults.button },
        schema: [...builderComponentSchemas.button],
        renderer: ButtonRenderer,
    },
    'legacy-section': {
        key: 'legacy-section',
        label: 'Imported Section',
        category: builderComponentCategories.fallback,
        defaultProps: { ...builderComponentDefaults['legacy-section'] },
        schema: [...builderComponentSchemas['legacy-section']],
        renderer: LegacySectionRenderer,
    },
};

interface BuilderComponentLookupOptions {
    allowFallback?: boolean;
}

export function getBuilderComponentDefinition(
    componentKey: string | undefined | null,
    options: BuilderComponentLookupOptions = {},
) {
    if (! componentKey) {
        return options.allowFallback ? builderComponentRegistry['legacy-section'] : null;
    }

    return builderComponentRegistry[componentKey] ?? (options.allowFallback ? builderComponentRegistry['legacy-section'] : null);
}

export function listBuilderComponentDefinitions() {
    return Object.values(builderComponentRegistry);
}
