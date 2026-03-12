import type {
    CanonicalControlGroup,
    CanonicalPrimaryPanelTab,
    SchemaPrimitiveField,
} from './InspectorFieldResolver';

export interface CanonicalControlGroupAuditRow {
    group: CanonicalControlGroup;
    count: number;
    sample_labels: string[];
}

export interface CanonicalControlGroupFieldSet {
    group: CanonicalControlGroup;
    fields: SchemaPrimitiveField[];
}

export interface CanonicalPrimaryPanelTabFieldSetBucket {
    tab: CanonicalPrimaryPanelTab;
    fieldSets: CanonicalControlGroupFieldSet[];
}

export function buildCanonicalControlGroupAuditRows(fields: SchemaPrimitiveField[]): CanonicalControlGroupAuditRow[] {
    if (fields.length === 0) {
        return [];
    }

    const order: CanonicalControlGroup[] = [
        'content',
        'layout',
        'style',
        'advanced',
        'responsive',
        'states',
        'data',
        'bindings',
        'meta',
    ];
    const buckets = new Map<CanonicalControlGroup, CanonicalControlGroupAuditRow>();

    fields.forEach((field) => {
        const group = field.control_meta.group;
        const existing = buckets.get(group) ?? {
            group,
            count: 0,
            sample_labels: [],
        };

        existing.count += 1;
        if (existing.sample_labels.length < 3 && !existing.sample_labels.includes(field.label)) {
            existing.sample_labels.push(field.label);
        }

        buckets.set(group, existing);
    });

    return order
        .map((group) => buckets.get(group) ?? null)
        .filter((row): row is CanonicalControlGroupAuditRow => row !== null && row.count > 0);
}

export function buildCanonicalControlGroupFieldSets(fields: SchemaPrimitiveField[]): CanonicalControlGroupFieldSet[] {
    if (fields.length === 0) {
        return [];
    }

    const order: CanonicalControlGroup[] = [
        'content',
        'layout',
        'style',
        'advanced',
        'responsive',
        'states',
        'data',
        'bindings',
        'meta',
    ];
    const buckets = new Map<CanonicalControlGroup, SchemaPrimitiveField[]>();

    fields.forEach((field) => {
        const group = field.control_meta.group;
        const existing = buckets.get(group) ?? [];
        existing.push(field);
        buckets.set(group, existing);
    });

    return order
        .map((group) => {
            let groupFields = buckets.get(group) ?? [];
            if (groupFields.length === 0) {
                return null;
            }

            if (group === 'content' || group === 'style') {
                const variantOrder: Record<string, number> = { layout_variant: 0, style_variant: 1 };
                groupFields = [...groupFields].sort((left, right) => {
                    const leftLeaf = left.path[left.path.length - 1] as string;
                    const rightLeaf = right.path[right.path.length - 1] as string;
                    const leftOrder = variantOrder[leftLeaf] ?? 2;
                    const rightOrder = variantOrder[rightLeaf] ?? 2;
                    return leftOrder - rightOrder;
                });
            }

            return {
                group,
                fields: groupFields,
            };
        })
        .filter((fieldSet): fieldSet is CanonicalControlGroupFieldSet => fieldSet !== null);
}

export function mapCanonicalControlGroupToPrimaryPanelTab(group: CanonicalControlGroup): CanonicalPrimaryPanelTab {
    if (group === 'layout') {
        return 'layout';
    }
    if (group === 'style' || group === 'responsive' || group === 'states') {
        return 'style';
    }
    if (group === 'advanced') {
        return 'advanced';
    }
    return 'content';
}

export function buildCanonicalPrimaryPanelTabFieldSetBuckets(
    fieldSets: CanonicalControlGroupFieldSet[],
): CanonicalPrimaryPanelTabFieldSetBucket[] {
    if (fieldSets.length === 0) {
        return [];
    }

    const order: CanonicalPrimaryPanelTab[] = ['content', 'layout', 'style', 'advanced'];
    const buckets = new Map<CanonicalPrimaryPanelTab, CanonicalControlGroupFieldSet[]>();

    fieldSets.forEach((fieldSet) => {
        const tab = mapCanonicalControlGroupToPrimaryPanelTab(fieldSet.group);
        const existing = buckets.get(tab) ?? [];
        existing.push(fieldSet);
        buckets.set(tab, existing);
    });

    return order
        .map((tab) => {
            const tabFieldSets = buckets.get(tab) ?? [];
            if (tabFieldSets.length === 0) {
                return null;
            }

            return {
                tab,
                fieldSets: tabFieldSets,
            };
        })
        .filter((bucket): bucket is CanonicalPrimaryPanelTabFieldSetBucket => bucket !== null);
}
