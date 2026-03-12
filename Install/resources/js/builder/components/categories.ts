export const builderComponentCategories = {
    layout: 'Layout',
    content: 'Content',
    marketing: 'Marketing',
    footer: 'Footer',
    fallback: 'Imported',
} as const;

export type BuilderComponentCategoryKey = keyof typeof builderComponentCategories;
