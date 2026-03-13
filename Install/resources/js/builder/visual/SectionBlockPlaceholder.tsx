import type { BuilderSection } from './treeUtils';

function getProps(section: BuilderSection): Record<string, unknown> | null {
    if (section.props && typeof section.props === 'object' && !Array.isArray(section.props)) {
        return section.props;
    }

    try {
        const p: unknown = JSON.parse(section.propsText || '{}');
        return p && typeof p === 'object' && !Array.isArray(p) ? (p as Record<string, unknown>) : null;
    } catch {
        return null;
    }
}

function getStr(props: Record<string, unknown> | null, ...keys: string[]): string {
    if (!props) return '';
    for (const k of keys) {
        const v = props[k];
        if (typeof v === 'string' && v.trim() !== '') return v.trim().slice(0, 120);
    }
    return '';
}

/**
 * Placeholder for a section in the visual canvas.
 * Shows multiple editable fields so live updates from the sidebar are clearly visible.
 */
export function SectionBlockPlaceholder({ section }: { section: BuilderSection }) {
    const props = getProps(section);
    const title = getStr(props, 'title', 'headline', 'heading');
    const subtitle = getStr(props, 'subtitle', 'description', 'body');
    const label = getStr(props, 'label', 'button_label', 'cta_label');
    const imageUrl = getStr(props, 'image_url', 'src', 'url');
    const headingText = title || 'Unknown component';
    const detailText = title ? subtitle : section.type;

    return (
        <div className="px-3 py-2 text-left space-y-1">
            <p className="text-[10px] text-muted-foreground/80 uppercase tracking-wide truncate">{section.type}</p>
            <p className="text-sm font-medium text-foreground truncate">{headingText}</p>
            {detailText ? <p className="text-xs text-muted-foreground truncate">{detailText}</p> : null}
            {label ? <span className="inline-block text-xs px-2 py-0.5 rounded bg-muted">{label}</span> : null}
            {imageUrl ? (
                <div className="mt-1 rounded overflow-hidden bg-muted/50 max-h-20 flex items-center justify-center">
                    <img src={imageUrl} alt="" className="max-h-20 w-auto object-contain" onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }} />
                </div>
            ) : null}
            {!title && !subtitle && !label && !imageUrl && (
                <p className="text-xs text-muted-foreground/70 italic">No preview content</p>
            )}
        </div>
    );
}
