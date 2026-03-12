const GEORGIAN_SCRIPT_RE = /[\u10A0-\u10FF]/;
const LATIN_SCRIPT_RE = /[a-zA-Z\u00C0-\u024F]/g;

function normalizeLocaleHint(locale?: string | null): 'ka' | 'en' | null {
    const normalized = String(locale ?? '').trim().toLowerCase();
    if (normalized.startsWith('ka')) {
        return 'ka';
    }
    if (normalized.startsWith('en')) {
        return 'en';
    }

    return null;
}

function looksLikeRomanizedGeorgian(message: string): boolean {
    const normalized = message.trim().toLowerCase();
    if (!normalized) {
        return false;
    }

    return [
        'qartul',
        'qartulad',
        'qartuli',
        'kartul',
        'kartulad',
        'kartuli',
    ].some((needle) => normalized.includes(needle));
}

export function detectReplyLanguage(message: string, fallbackLocale?: string | null): 'ka' | 'en' {
    const trimmed = message.replace(/\s/g, '');
    const normalizedFallback = normalizeLocaleHint(fallbackLocale);

    if (!trimmed.length) {
        return normalizedFallback ?? 'ka';
    }

    if (GEORGIAN_SCRIPT_RE.test(trimmed) || looksLikeRomanizedGeorgian(trimmed)) {
        return 'ka';
    }

    const latin = (trimmed.match(LATIN_SCRIPT_RE) ?? []).length;
    if (latin > 0) {
        return 'en';
    }

    return normalizedFallback ?? 'ka';
}

export function resolveAiCommandLocale(message: string, fallbackLocale?: string | null): 'ka' | 'en' {
    return detectReplyLanguage(message, fallbackLocale);
}
