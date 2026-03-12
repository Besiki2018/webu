export interface AgentChangeSet {
    operations?: Array<Record<string, unknown>>;
    summary?: string[];
}

export interface PreviewLayoutOverrides {
    header_variant?: string;
    footer_variant?: string;
}

export interface ChangeSetScopeLabels {
    homePage: string;
    homePageOnly: string;
    page: (slug: string) => string;
    siteWide: string;
    siteWideHeader: string;
    siteWideFooter: string;
    siteWideTheme: string;
}

const BUILDER_SYNCABLE_OPS = new Set([
    'updateText',
    'setField',
    'replaceImage',
    'updateSection',
    'updateButton',
    'insertSection',
    'deleteSection',
    'reorderSection',
]);

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function normalizeOperationType(operation: Record<string, unknown>): string {
    return typeof operation.op === 'string' ? operation.op.trim() : '';
}

function normalizeGlobalComponent(operation: Record<string, unknown>): 'header' | 'footer' | null {
    const component = typeof operation.component === 'string' ? operation.component.trim().toLowerCase() : '';

    if (component === 'header' || component === 'footer') {
        return component;
    }

    return null;
}

function readPatchVariant(patch: Record<string, unknown>): string | null {
    const candidateKeys = ['layout_variant', 'layoutVariant', 'variant'];

    for (const key of candidateKeys) {
        const value = patch[key];
        if (typeof value === 'string' && value.trim() !== '') {
            return value.trim().toLowerCase();
        }
    }

    return null;
}

function getOperations(changeSet: AgentChangeSet | null | undefined): Array<Record<string, unknown>> {
    return Array.isArray(changeSet?.operations)
        ? changeSet.operations.filter(isRecord)
        : [];
}

export function getBuilderSyncableChangeSet(changeSet: AgentChangeSet | null | undefined): AgentChangeSet | null {
    const operations = getOperations(changeSet).filter((operation) => BUILDER_SYNCABLE_OPS.has(normalizeOperationType(operation)));

    if (operations.length === 0) {
        return null;
    }

    return {
        ...changeSet,
        operations,
    };
}

export function changeSetHasUnsyncedOperations(changeSet: AgentChangeSet | null | undefined): boolean {
    const operations = getOperations(changeSet);

    return operations.some((operation) => !BUILDER_SYNCABLE_OPS.has(normalizeOperationType(operation)));
}

export function extractPreviewLayoutOverrides(changeSet: AgentChangeSet | null | undefined): PreviewLayoutOverrides | null {
    const overrides: PreviewLayoutOverrides = {};

    getOperations(changeSet).forEach((operation) => {
        if (normalizeOperationType(operation) !== 'updateGlobalComponent') {
            return;
        }

        const component = normalizeGlobalComponent(operation);
        const patch = isRecord(operation.patch) ? operation.patch : null;
        if (!component || !patch) {
            return;
        }

        const nextVariant = readPatchVariant(patch);
        if (!nextVariant) {
            return;
        }

        if (component === 'header') {
            overrides.header_variant = nextVariant;
            return;
        }

        overrides.footer_variant = nextVariant;
    });

    return overrides.header_variant || overrides.footer_variant ? overrides : null;
}

export function resolveChangeSetScopeLabel(
    changeSet: AgentChangeSet | null | undefined,
    pageSlug: string | null | undefined,
    labels: ChangeSetScopeLabels,
): string | null {
    const operations = getOperations(changeSet);
    if (operations.length === 0) {
        return null;
    }

    const hasPageOps = operations.some((operation) => BUILDER_SYNCABLE_OPS.has(normalizeOperationType(operation)));
    const hasThemeOps = operations.some((operation) => normalizeOperationType(operation) === 'updateTheme');
    const globalTargets = new Set<'header' | 'footer'>();

    operations.forEach((operation) => {
        if (normalizeOperationType(operation) !== 'updateGlobalComponent') {
            return;
        }

        const component = normalizeGlobalComponent(operation);
        if (component) {
            globalTargets.add(component);
        }
    });

    const normalizedPageSlug = typeof pageSlug === 'string' ? pageSlug.trim().toLowerCase() : '';
    const pageOnlyLabel = normalizedPageSlug === '' || normalizedPageSlug === 'home'
        ? labels.homePageOnly
        : labels.page(normalizedPageSlug);
    const pageContextLabel = normalizedPageSlug === '' || normalizedPageSlug === 'home'
        ? labels.homePage
        : labels.page(normalizedPageSlug);

    let globalLabel: string | null = null;
    if (hasThemeOps || globalTargets.size > 1) {
        globalLabel = hasThemeOps ? labels.siteWideTheme : labels.siteWide;
    } else if (globalTargets.has('header')) {
        globalLabel = labels.siteWideHeader;
    } else if (globalTargets.has('footer')) {
        globalLabel = labels.siteWideFooter;
    }

    if (hasPageOps && globalLabel) {
        return `${pageContextLabel} + ${globalLabel}`;
    }

    if (globalLabel) {
        return globalLabel;
    }

    return hasPageOps ? pageOnlyLabel : null;
}
