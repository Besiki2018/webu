const GENERIC_EDIT_PATTERNS = [
    /\b(change|edit|update|modify|fix|adjust|improve|restyle)\b/i,
    /(შეცვალე|შეცვლა|განაახლე|გამისწორე|შეასწორე|გაასწორე|დაარედაქტირე|მინდა)/i,
];

const VALUE_PATTERNS = [
    /#[0-9a-f]{3,8}\b/i,
    /https?:\/\//i,
    /["“”''].*?["“”'']/,
    /\b\d+(px|rem|em|vh|vw|%)\b/i,
    /\b(sticky|transparent|center|left|right|smaller|bigger|black|white|blue|red|green|headline|centered|minimal)\b/i,
    /\b(გამჭვირვალე|ცენტრში|მარცხნივ|მარჯვნივ|პატარა|დიდი|შავი|თეთრი|ლურჯი|წითელი|მწვანე|მინიმალური)\b/i,
];

const SITE_BUILD_PATTERNS = [
    /create (?:a )?website/i,
    /build (?:a )?website/i,
    /make (?:a )?website/i,
    /redesign (?:the )?(?:site|website)/i,
    /full site/i,
    /whole site/i,
    /landing page/i,
    /online store/i,
    /e-?commerce/i,
    /website/i,
    /site/i,
    /project/i,
    /შექმენი საიტი/i,
    /ააგე საიტი/i,
    /საიტი/i,
    /ვებსაიტ/i,
    /მთელ საიტ/i,
    /ონლაინ მაღაზი/i,
    /ეკომერც/i,
    /პროექტ/i,
];

type ClarificationTarget = 'header' | 'footer' | 'hero' | 'section' | 'chat';

function detectTarget(normalized: string): ClarificationTarget | null {
    if (normalized.includes('მიმოწერ') || normalized.includes('ჩატ') || normalized.includes('conversation') || normalized.includes('chat ui') || normalized.includes('chat design') || normalized.includes('sidebar')) {
        return 'chat';
    }

    if (/(^|[\s-_])(header|navbar|nav)([\s-_]|$)/i.test(normalized) || normalized.includes('ჰედერ') || normalized.includes('მენიუ') || normalized.includes('ნავიგაცია')) {
        return 'header';
    }

    if (/(^|[\s-_])footer([\s-_]|$)/i.test(normalized) || normalized.includes('ფუტერ')) {
        return 'footer';
    }

    if (/(^|[\s-_])hero([\s-_]|$)/i.test(normalized) || normalized.includes('ჰირო') || normalized.includes('ჰერო')) {
        return 'hero';
    }

    if (normalized.includes('დიზაინ') || normalized.includes('design') || normalized.includes('layout') || normalized.includes('ინტერფეის')) {
        return 'section';
    }

    if (/\b(section|block|სექცია|ბლოკ)\b/i.test(normalized)) {
        return 'section';
    }

    return null;
}

function hasGenericEditIntent(normalized: string): boolean {
    return GENERIC_EDIT_PATTERNS.some((pattern) => pattern.test(normalized));
}

function hasExplicitValue(message: string): boolean {
    return VALUE_PATTERNS.some((pattern) => pattern.test(message));
}

function looksLikeBroadSiteBuildRequest(normalized: string): boolean {
    return SITE_BUILD_PATTERNS.some((pattern) => pattern.test(normalized));
}

export function getClarificationPrompt(message: string, locale: 'ka' | 'en'): string | null {
    const normalized = message.trim().toLowerCase();
    if (normalized === '') {
        return null;
    }

    if (looksLikeBroadSiteBuildRequest(normalized)) {
        return null;
    }

    const target = detectTarget(normalized);
    if (!target || !hasGenericEditIntent(normalized) || hasExplicitValue(message)) {
        return null;
    }

    const wordCount = normalized.split(/\s+/).filter(Boolean).length;
    if (wordCount > 18) {
        return null;
    }

    if (locale === 'ka') {
        switch (target) {
            case 'chat':
                return 'ეს აგენტი ვებსაიტის შიგთავსსა და სექციებს ცვლის, არა თვითონ ჩატის ინტერფეისს. თუ საიტის კონკრეტულ ნაწილს გულისხმობ, მომწერე რომელი სექცია ან ელემენტი უნდა შეიცვალოს.';
            case 'header':
                return 'ჰედერში კონკრეტულად რა შევცვალო: ლოგო, მენიუ, ფონი, ფერები, ზომა თუ spacing? ერთი წინადადებით დამიწერე ზუსტად როგორი უნდა იყოს.';
            case 'footer':
                return 'ფუტერში კონკრეტულად რა შევცვალო: ტექსტები, ლინკები, სვეტები, ფონი თუ spacing? ერთი წინადადებით დამიწერე ზუსტად როგორი უნდა იყოს.';
            case 'hero':
                return 'ჰერო სექციაში ზუსტად რა გინდა შევცვალო: სათაური, ტექსტი, ღილაკი, სურათი თუ layout? დამიწერე კონკრეტულად.';
            case 'section':
                return 'რომელ სექციას და რა ნაწილს გულისხმობ? დამიწერე სექციის სახელი და ზუსტად რა უნდა შეიცვალოს.';
            default:
                return null;
        }
    }

    switch (target) {
        case 'chat':
            return 'This agent edits the website itself, not the chat interface. If you mean a part of the site, tell me which section or element should change.';
        case 'header':
            return 'What exactly should change in the header: logo, menu, background, colors, size, or spacing? Describe the target result in one sentence.';
        case 'footer':
            return 'What exactly should change in the footer: text, links, columns, background, or spacing? Describe the target result in one sentence.';
        case 'hero':
            return 'What exactly should change in the hero section: headline, text, button, image, or layout? Describe the target result.';
        case 'section':
            return 'Which section do you mean, and what exactly should change? Name the section and the specific change.';
        default:
            return null;
    }
}
