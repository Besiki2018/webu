const EXCLUDED_CREATE_PROMPT_PATTERNS = [
    /online store/i,
    /e-?commerce/i,
    /\bcart\b/i,
    /\bcheckout\b/i,
    /product pages?/i,
    /book appointments/i,
    /booking/i,
    /portfolio/i,
    /agency/i,
    /ონლაინ მაღაზი/i,
    /კალათ/i,
    /ჩექაუთ/i,
    /პროდუქტ/i,
    /ჯავშ/i,
    /სერვისი ჯავშ/i,
    /პორტფოლიო/i,
    /სააგენტო/i,
];

export function filterCreatePromptExamples(entries: string[]): string[] {
    const seen = new Set<string>();

    return entries
        .map((entry) => entry.trim())
        .filter((entry) => entry !== '')
        .filter((entry) => !EXCLUDED_CREATE_PROMPT_PATTERNS.some((pattern) => pattern.test(entry)))
        .filter((entry) => {
            const key = entry.toLocaleLowerCase();
            if (seen.has(key)) {
                return false;
            }
            seen.add(key);
            return true;
        });
}
