import type { CSSProperties, ComponentType, ReactNode } from 'react';
import { getCentralRegistryEntry } from '../centralComponentRegistry';
import { ensureFullComponentProps } from '../builderCompatibility';

type BuilderCanvasFieldGroup = 'content' | 'layout' | 'style' | 'advanced' | 'responsive' | 'state' | 'states' | 'data' | 'bindings' | 'meta';

export interface BuilderCanvasSchemaField {
    path: string;
    label: string;
    group: BuilderCanvasFieldGroup;
    type: string;
}

export interface BuilderCanvasSchemaSnapshot {
    componentKey: string;
    displayName: string;
    category: string;
    fields: BuilderCanvasSchemaField[];
}

export interface BuilderCanvasComponentProps {
    sectionKey: string;
    sectionLocalId: string;
    displayName: string;
    props: Record<string, unknown>;
    schema: BuilderCanvasSchemaSnapshot;
}

export type BuilderCanvasComponent = ComponentType<BuilderCanvasComponentProps>;

function isRecord(value: unknown): value is Record<string, unknown> {
    return !!value && typeof value === 'object' && !Array.isArray(value);
}

function getValueAtPath(source: Record<string, unknown>, path: string): unknown {
    return path.split('.').filter(Boolean).reduce<unknown>((cursor, segment) => {
        if (!isRecord(cursor)) {
            return undefined;
        }

        return cursor[segment];
    }, source);
}

function pickFirstValue(source: Record<string, unknown>, paths: string[]): unknown {
    for (const path of paths) {
        const value = getValueAtPath(source, path);
        if (value !== undefined && value !== null && value !== '') {
            return value;
        }
    }

    return undefined;
}

function asString(value: unknown): string {
    return typeof value === 'string' ? value.trim() : '';
}

function asNumber(value: unknown): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string' && value.trim() !== '') {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : null;
    }

    return null;
}

function coerceObjectArray(value: unknown): Array<Record<string, unknown>> {
    if (Array.isArray(value)) {
        return value.filter(isRecord);
    }

    if (typeof value === 'string' && value.trim() !== '') {
        try {
            const parsed: unknown = JSON.parse(value);
            return Array.isArray(parsed) ? parsed.filter(isRecord) : [];
        } catch {
            return [];
        }
    }

    return [];
}

function buildShellStyle(props: Record<string, unknown>): CSSProperties {
    const backgroundColor = asString(
        pickFirstValue(props, [
            'backgroundColor',
            'background_color',
            'style.background_color',
            'responsive.desktop.background_color',
        ])
    );
    const color = asString(
        pickFirstValue(props, [
            'textColor',
            'text_color',
            'style.text_color',
        ])
    );
    const borderRadius = asString(
        pickFirstValue(props, [
            'style.border_radius',
            'borderRadius',
            'border_radius',
        ])
    );

    return {
        background: backgroundColor || '#ffffff',
        color: color || '#111827',
        borderRadius: borderRadius || '18px',
    };
}

function resolveMenuItems(props: Record<string, unknown>, ...paths: string[]): Array<{ label: string; url: string; icon?: string }> {
    for (const path of paths) {
        const value = getValueAtPath(props, path);
        const items = coerceObjectArray(value)
            .map((item) => ({
                label: asString(item.label) || asString(item.title) || asString(item.text),
                url: asString(item.url) || asString(item.href) || '#',
                icon: asString(item.icon) || undefined,
            }))
            .filter((item) => item.label !== '');

        if (items.length > 0) {
            return items;
        }
    }

    return [];
}

function resolveButtons(props: Record<string, unknown>): Array<{ label: string; url: string }> {
    const groupedButtons = coerceObjectArray(
        pickFirstValue(props, ['buttons', 'button_group', 'cta_buttons'])
    )
        .map((item) => ({
            label: asString(item.label) || asString(item.text),
            url: asString(item.url) || asString(item.href) || '#',
        }))
        .filter((item) => item.label !== '');

    if (groupedButtons.length > 0) {
        return groupedButtons;
    }

    const primaryLabel = asString(
        pickFirstValue(props, ['buttonText', 'ctaText', 'primary_cta.label', 'cta_label'])
    );
    const primaryUrl = asString(
        pickFirstValue(props, ['buttonLink', 'ctaLink', 'primary_cta.link', 'cta_url'])
    ) || '#';
    const secondaryLabel = asString(
        pickFirstValue(props, ['secondaryButtonText', 'secondary_cta.label', 'cta_secondary_label'])
    );
    const secondaryUrl = asString(
        pickFirstValue(props, ['secondaryButtonLink', 'secondary_cta.link', 'cta_secondary_url'])
    ) || '#';

    return [
        primaryLabel ? { label: primaryLabel, url: primaryUrl } : null,
        secondaryLabel ? { label: secondaryLabel, url: secondaryUrl } : null,
    ].filter((item): item is { label: string; url: string } => item !== null);
}

function resolveImage(props: Record<string, unknown>, ...paths: string[]): string {
    return asString(pickFirstValue(props, paths));
}

function FieldBadge({ children }: { children: string }) {
    return (
        <span
            className="inline-flex items-center rounded-full border border-slate-200 bg-white/90 px-2 py-0.5 text-[11px] font-medium text-slate-600"
            data-builder-chrome="true"
        >
            {children}
        </span>
    );
}

function Shell({
    displayName,
    sectionKey,
    props,
    children,
}: {
    displayName: string;
    sectionKey: string;
    props: Record<string, unknown>;
    children: ReactNode;
}) {
    return (
        <section
            className="relative overflow-hidden border border-slate-200/80 shadow-sm"
            style={buildShellStyle(props)}
            data-builder-component-key={sectionKey}
            data-builder-component-name={displayName}
        >
            <div className="pointer-events-none absolute right-3 top-3 z-[1]" data-builder-chrome="true">
                <FieldBadge>{displayName}</FieldBadge>
            </div>
            {children}
        </section>
    );
}

/** Renders the full Header builder component (no placeholder). */
export function BuilderHeaderCanvasSection({ displayName, props, sectionKey }: BuilderCanvasComponentProps) {
    const entry = getCentralRegistryEntry('webu_header_01');
    if (entry) {
        const mapped = entry.mapBuilderProps ? entry.mapBuilderProps(props) : props;
        const componentProps = ensureFullComponentProps(
            entry.defaults as Record<string, unknown>,
            mapped as Record<string, unknown>
        );
        const Component = entry.component;
        return <Component {...componentProps} />;
    }
    return (
        <Shell displayName={displayName} props={props} sectionKey={sectionKey}>
            <div className="px-4 py-3 text-sm text-muted-foreground">Header</div>
        </Shell>
    );
}

/** Renders the full Hero builder component (no placeholder). */
export function BuilderHeroCanvasSection({ displayName, props, sectionKey }: BuilderCanvasComponentProps) {
    const entry = getCentralRegistryEntry('webu_general_hero_01');
    if (entry) {
        const mapped = entry.mapBuilderProps ? entry.mapBuilderProps(props) : props;
        const componentProps = ensureFullComponentProps(
            entry.defaults as Record<string, unknown>,
            mapped as Record<string, unknown>
        );
        const Component = entry.component;
        return <Component {...componentProps} />;
    }
    return (
        <Shell displayName={displayName} props={props} sectionKey={sectionKey}>
            <div className="px-4 py-3 text-sm text-muted-foreground">Hero</div>
        </Shell>
    );
}

export function BuilderHeadingCanvasSection({ displayName, props, sectionKey }: BuilderCanvasComponentProps) {
    const eyebrow = asString(pickFirstValue(props, ['eyebrow', 'badgeText', 'kicker']));
    const title = asString(pickFirstValue(props, ['title', 'headline', 'heading'])) || displayName;
    const subtitle = asString(pickFirstValue(props, ['subtitle', 'description', 'body']));

    return (
        <Shell displayName={displayName} props={props} sectionKey={sectionKey}>
            <div className="space-y-3 px-6 py-8">
                {eyebrow ? (
                    <span className="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600" data-webu-field="eyebrow">
                        {eyebrow}
                    </span>
                ) : null}
                <h2 className="max-w-3xl text-3xl font-semibold tracking-tight text-balance" data-webu-field="headline">
                    {title}
                </h2>
                {subtitle ? (
                    <p className="max-w-2xl text-sm leading-6 text-slate-600" data-webu-field="subtitle">{subtitle}</p>
                ) : null}
            </div>
        </Shell>
    );
}

export function BuilderTextCanvasSection({ displayName, props, sectionKey }: BuilderCanvasComponentProps) {
    const body = asString(pickFirstValue(props, ['body', 'description', 'content', 'text', 'subtitle']))
        || 'Add supporting copy and refine it from Sidebar or Chat.';

    return (
        <Shell displayName={displayName} props={props} sectionKey={sectionKey}>
            <div className="px-6 py-7">
                <div className="max-w-3xl space-y-4">
                    <p className="text-base leading-7 text-slate-700" data-webu-field="body">{body}</p>
                </div>
            </div>
        </Shell>
    );
}

export function BuilderImageCanvasSection({ displayName, props, sectionKey }: BuilderCanvasComponentProps) {
    const imageUrl = resolveImage(props, 'image', 'image_url', 'src', 'url');
    const alt = asString(pickFirstValue(props, ['imageAlt', 'image_alt', 'alt'])) || displayName;
    const caption = asString(pickFirstValue(props, ['caption', 'subtitle', 'description']));
    const imageLink = asString(pickFirstValue(props, ['image_link', 'imageLink', 'link_url']));

    const imageEl = imageUrl ? (
        <img src={imageUrl} alt={alt} className="max-h-[420px] w-full rounded-2xl object-cover" data-webu-field="image_url" />
    ) : (
        <div className="grid min-h-[260px] place-items-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 text-sm text-slate-500">
            Select an image
        </div>
    );

    return (
        <Shell displayName={displayName} props={props} sectionKey={sectionKey}>
            <div className="space-y-3 px-6 py-6">
                {imageLink ? (
                    <a href={imageLink} className="block" data-webu-field="image_link" data-webu-field-url="image_link">
                        {imageEl}
                    </a>
                ) : (
                    imageEl
                )}
                {caption ? <p className="text-sm text-slate-500" data-webu-field="caption">{caption}</p> : null}
            </div>
        </Shell>
    );
}

export function BuilderButtonCanvasSection({ displayName, props, sectionKey }: BuilderCanvasComponentProps) {
    const label = asString(pickFirstValue(props, ['button', 'buttonText', 'ctaText', 'label'])) || 'Click here';
    const url = asString(pickFirstValue(props, ['button_url', 'buttonLink', 'ctaLink', 'href'])) || '#';

    return (
        <Shell displayName={displayName} props={props} sectionKey={sectionKey}>
            <div className="flex items-center justify-center px-6 py-8">
                <a href={url} className="rounded-full bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-sm" data-webu-field="button_url">
                    <span data-webu-field="button">{label}</span>
                </a>
            </div>
        </Shell>
    );
}

export function BuilderSpacerCanvasSection({ displayName, props, sectionKey }: BuilderCanvasComponentProps) {
    const height = Math.max(24, Math.min(asNumber(pickFirstValue(props, ['height', 'layout.height'])) ?? 56, 240));

    return (
        <Shell displayName={displayName} props={props} sectionKey={sectionKey}>
            <div className="grid place-items-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 text-xs font-medium text-slate-500" style={{ minHeight: `${height}px` }} data-webu-field="height">
                Spacer · {height}px
            </div>
        </Shell>
    );
}

export function BuilderCardCanvasSection({ displayName, props, sectionKey }: BuilderCanvasComponentProps) {
    const title = asString(pickFirstValue(props, ['title', 'headline', 'heading'])) || 'Card title';
    const body = asString(pickFirstValue(props, ['body', 'description', 'content'])) || 'Card content goes here.';
    const imageUrl = resolveImage(props, 'image', 'image_url');
    const imageAlt = asString(pickFirstValue(props, ['image_alt', 'imageAlt', 'alt'])) || '';
    const linkLabel = asString(pickFirstValue(props, ['linkLabel', 'buttonText', 'link_label'])) || 'Read more';
    const linkUrl = asString(pickFirstValue(props, ['link_url', 'buttonLink', 'href', 'linkUrl'])) || '#';

    return (
        <Shell displayName={displayName} props={props} sectionKey={sectionKey}>
            <article className="overflow-hidden rounded-[inherit]">
                {imageUrl ? <img src={imageUrl} alt={imageAlt} className="h-48 w-full object-cover" data-webu-field="image_url" /> : null}
                <div className="space-y-3 px-6 py-6">
                    <h3 className="text-xl font-semibold" data-webu-field="title">{title}</h3>
                    <p className="text-sm leading-6 text-slate-600" data-webu-field="body">{body}</p>
                    <a href={linkUrl} className="inline-flex rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700" data-webu-field="linkLabel" data-webu-field-url="link_url">
                        {linkLabel}
                    </a>
                </div>
            </article>
        </Shell>
    );
}

export function BuilderFormCanvasSection({ displayName, props, sectionKey }: BuilderCanvasComponentProps) {
    const title = asString(pickFirstValue(props, ['title', 'headline', 'heading'])) || 'Contact us';
    const subtitle = asString(pickFirstValue(props, ['subtitle', 'description', 'body']));
    const submitLabel = asString(pickFirstValue(props, ['submit_label', 'buttonText'])) || 'Submit';
    const namePlaceholder = asString(pickFirstValue(props, ['namePlaceholder', 'name_placeholder'])) || 'Your name';
    const emailPlaceholder = asString(pickFirstValue(props, ['emailPlaceholder', 'email_placeholder'])) || 'Email address';
    const messagePlaceholder = asString(pickFirstValue(props, ['messagePlaceholder', 'message_placeholder'])) || 'Project details';

    return (
        <Shell displayName={displayName} props={props} sectionKey={sectionKey}>
            <div className="grid gap-6 px-6 py-8 md:grid-cols-[0.9fr,1.1fr]">
                <div className="space-y-3">
                    <h3 className="text-2xl font-semibold" data-webu-field="title">{title}</h3>
                    {subtitle ? <p className="text-sm leading-6 text-slate-600" data-webu-field="subtitle">{subtitle}</p> : null}
                </div>
                <div className="grid gap-3 rounded-2xl border border-slate-200 bg-white/80 p-5">
                    <input className="rounded-xl border border-slate-200 px-4 py-3 text-sm" placeholder={namePlaceholder} readOnly data-webu-field="namePlaceholder" data-builder-chrome="true" />
                    <input className="rounded-xl border border-slate-200 px-4 py-3 text-sm" placeholder={emailPlaceholder} readOnly data-webu-field="emailPlaceholder" data-builder-chrome="true" />
                    <textarea className="min-h-[120px] rounded-xl border border-slate-200 px-4 py-3 text-sm" placeholder={messagePlaceholder} readOnly data-webu-field="messagePlaceholder" data-builder-chrome="true" />
                    <button className="rounded-full bg-slate-900 px-4 py-3 text-sm font-semibold text-white" data-webu-field="submit_label">
                        {submitLabel}
                    </button>
                </div>
            </div>
        </Shell>
    );
}

export function BuilderNewsletterCanvasSection({ displayName, props, sectionKey }: BuilderCanvasComponentProps) {
    const title = asString(pickFirstValue(props, ['title', 'headline', 'heading'])) || 'Subscribe';
    const subtitle = asString(pickFirstValue(props, ['subtitle', 'description', 'body'])) || 'Share why joining the list is valuable.';
    const buttonText = asString(pickFirstValue(props, ['buttonText', 'submit_label'])) || 'Subscribe';
    const placeholder = asString(pickFirstValue(props, ['placeholder', 'emailPlaceholder'])) || 'Your email';

    return (
        <Shell displayName={displayName} props={props} sectionKey={sectionKey}>
            <div className="grid gap-5 px-6 py-7 md:grid-cols-[1fr,auto] md:items-center">
                <div className="space-y-2">
                    <h3 className="text-2xl font-semibold" data-webu-field="title">{title}</h3>
                    <p className="text-sm leading-6 text-slate-600" data-webu-field="subtitle">{subtitle}</p>
                </div>
                <div className="flex w-full max-w-md items-center gap-2 rounded-full border border-slate-200 bg-white p-2">
                    <span className="min-w-0 flex-1 truncate px-3 text-sm text-slate-400" data-webu-field="placeholder" data-builder-chrome="true">{placeholder}</span>
                    <button className="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white" data-webu-field="buttonText">
                        {buttonText}
                    </button>
                </div>
            </div>
        </Shell>
    );
}

export function BuilderSectionCanvasSection({ displayName, props, sectionKey }: BuilderCanvasComponentProps) {
    const title = asString(pickFirstValue(props, ['title', 'headline', 'heading'])) || displayName;
    const body = asString(pickFirstValue(props, ['body', 'description', 'subtitle']));

    return (
        <Shell displayName={displayName} props={props} sectionKey={sectionKey}>
            <div className="space-y-3 px-6 py-8">
                <h3 className="text-2xl font-semibold" data-webu-field="title">{title}</h3>
                {body ? <p className="max-w-2xl text-sm leading-6 text-slate-600" data-webu-field="body">{body}</p> : null}
            </div>
        </Shell>
    );
}

export function BuilderVideoCanvasSection({ displayName, props, sectionKey }: BuilderCanvasComponentProps) {
    const videoUrl = asString(pickFirstValue(props, ['video_url', 'videoUrl', 'url']));
    const caption = asString(pickFirstValue(props, ['caption', 'subtitle', 'description']));

    return (
        <Shell displayName={displayName} props={props} sectionKey={sectionKey}>
            <div className="space-y-3 px-6 py-6">
                <div className="grid aspect-video place-items-center rounded-2xl bg-slate-900 text-sm font-medium text-white" data-webu-field="video_url">
                    {videoUrl ? 'Video linked' : 'Add video URL'}
                </div>
                {caption ? <p className="text-sm text-slate-500" data-webu-field="caption">{caption}</p> : null}
            </div>
        </Shell>
    );
}

/** Renders the full Footer builder component (no placeholder). */
export function BuilderFooterCanvasSection({ displayName, props, sectionKey }: BuilderCanvasComponentProps) {
    const entry = getCentralRegistryEntry('webu_footer_01');
    if (entry) {
        const mapped = entry.mapBuilderProps ? entry.mapBuilderProps(props) : props;
        const componentProps = ensureFullComponentProps(
            entry.defaults as Record<string, unknown>,
            mapped as Record<string, unknown>
        );
        const Component = entry.component;
        return <Component {...componentProps} />;
    }
    return (
        <Shell displayName={displayName} props={props} sectionKey={sectionKey}>
            <div className="px-4 py-3 text-sm text-muted-foreground">Footer</div>
        </Shell>
    );
}

export function BuilderCollectionCanvasSection({ displayName, props, sectionKey }: BuilderCanvasComponentProps) {
    const title = asString(pickFirstValue(props, ['title', 'headline', 'heading'])) || displayName;
    const subtitle = asString(pickFirstValue(props, ['subtitle', 'description', 'body']));
    const columns = Math.max(2, Math.min(asNumber(pickFirstValue(props, ['columns_desktop', 'productCount'])) ?? 4, 4));
    const ctaLabel = asString(pickFirstValue(props, ['cta_label', 'buttonText'])) || 'View item';

    return (
        <Shell displayName={displayName} props={props} sectionKey={sectionKey}>
            <div className="space-y-5 px-6 py-6">
                <div className="space-y-2">
                    <h3 className="text-xl font-semibold" data-webu-field="title">{title}</h3>
                    {subtitle ? <p className="text-sm text-slate-500" data-webu-field="subtitle">{subtitle}</p> : null}
                </div>
                <div
                    className="grid gap-4 md:grid-cols-2"
                    style={{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }}
                >
                    {Array.from({ length: columns }).map((_, index) => (
                        <article key={index} className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div className="aspect-[4/3] bg-gradient-to-br from-slate-100 to-slate-200" data-webu-field="image" />
                            <div className="space-y-2 p-4">
                                <p className="text-sm font-medium text-slate-900">Product {index + 1}</p>
                                <p className="text-xs text-slate-500">Grid cards inherit defaults from the central builder registry.</p>
                                <span className="inline-flex rounded-full bg-slate-900 px-3 py-1 text-[11px] font-semibold text-white" data-webu-field="cta_label">
                                    {ctaLabel}
                                </span>
                            </div>
                        </article>
                    ))}
                </div>
            </div>
        </Shell>
    );
}

export function BuilderGenericCanvasSection({ displayName, props, schema, sectionKey }: BuilderCanvasComponentProps) {
    const title = asString(pickFirstValue(props, ['title', 'headline', 'heading'])) || displayName;
    const subtitle = asString(pickFirstValue(props, ['subtitle', 'description', 'body']));
    const imageUrl = resolveImage(props, 'image', 'image_url', 'backgroundImage', 'background_image');
    const previewFields = schema.fields
        .filter((field) => field.group === 'content' || field.group === 'layout' || field.group === 'style')
        .slice(0, 5);

    return (
        <Shell displayName={displayName} props={props} sectionKey={sectionKey}>
            <div className="grid gap-5 px-6 py-6 md:grid-cols-[1.2fr,0.8fr]">
                <div className="space-y-3">
                    <h3 className="text-xl font-semibold" data-webu-field="title">{title}</h3>
                    {subtitle ? (
                        <p className="max-w-xl text-sm leading-6 text-slate-600" data-webu-field="subtitle">
                            {subtitle}
                        </p>
                    ) : (
                        <p className="max-w-xl text-sm leading-6 text-slate-500">
                            This section is rendered from the centralized builder registry and reads the same schema as Sidebar and Chat.
                        </p>
                    )}
                    <div className="flex flex-wrap gap-2">
                        {previewFields.map((field) => (
                            <FieldBadge key={field.path}>{field.label}</FieldBadge>
                        ))}
                    </div>
                </div>

                <div className="space-y-3">
                    {imageUrl ? (
                        <img src={imageUrl} alt="" className="h-40 w-full rounded-2xl object-cover" data-webu-field="image" />
                    ) : (
                        <div className="grid h-40 place-items-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 text-xs text-slate-500">
                            {schema.category.toUpperCase()} COMPONENT
                        </div>
                    )}
                    <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600">
                        <p className="font-semibold text-slate-900">Schema-backed fields</p>
                        <p className="mt-1 leading-5">
                            {schema.fields.length} editable fields, {schema.category} category, {schema.componentKey}
                        </p>
                    </div>
                </div>
            </div>
        </Shell>
    );
}
