type ProjectEditRoutingOptions = {
    hasSelectedElement?: boolean;
    viewMode?: string | null;
};

const PROJECT_CODE_PATTERNS = [
    /codebase/i,
    /workspace/i,
    /source code/i,
    /repository/i,
    /\brepo\b/i,
    /\btsx\b/i,
    /\bjsx\b/i,
    /\bphp\b/i,
    /\bfile\b/i,
    /component file/i,
    /edit the code/i,
    /change the code/i,
    /source files/i,
    /მთელ კოდ/i,
    /კოდს/i,
    /კოდში/i,
    /ვორკსპეის/i,
    /რეპოზიტორი/i,
    /ფაილ/i,
    /სორს კოდ/i,
    /component.tsx/i,
    /page.tsx/i,
];

export function shouldPreferProjectEdit(
    message: string,
    options: ProjectEditRoutingOptions = {}
): boolean {
    const trimmed = message.trim();
    if (trimmed === '' || options.hasSelectedElement) {
        return false;
    }

    if (options.viewMode === 'code') {
        return true;
    }

    return PROJECT_CODE_PATTERNS.some((pattern) => pattern.test(trimmed));
}
