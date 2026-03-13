export function formatPreviewOverlayLabel(label: string | null): string | null {
    if (!label) {
        return null;
    }

    const normalized = label
        .split('.')
        .filter(Boolean)
        .map((segment) => segment.replace(/_/g, ' '))
        .join(' / ')
        .trim();

    if (!normalized) {
        return null;
    }

    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
}
