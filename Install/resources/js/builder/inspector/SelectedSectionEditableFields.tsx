import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

interface SelectedSectionDraftLike {
    type: string;
}

interface SelectedNestedSectionLike {
    parentLocalId: string;
    path: Array<string | number>;
}

export interface SelectedSectionEditableFieldsProps {
    compact: boolean;
    t: (key: string) => string;
    selectedSectionDraft: SelectedSectionDraftLike | null;
    effectiveProps: Record<string, unknown> | null;
    editableFieldCount: number;
    displayFieldCount: number;
    selectedSectionUsesEcommerceProductsBinding: boolean;
    selectedSectionUsesEcommerceProductDetailBinding: boolean;
    selectedNestedSection: SelectedNestedSectionLike | null;
    onBackToParent: () => void;
    inspectorTargetSummary: ReactNode;
    bindingWarningsContent: ReactNode;
    controlGroupAuditSummaryContent: ReactNode;
    fieldSetsContent: ReactNode;
}

export function SelectedSectionEditableFields({
    compact,
    t,
    selectedSectionDraft,
    effectiveProps,
    editableFieldCount,
    displayFieldCount,
    selectedSectionUsesEcommerceProductsBinding,
    selectedSectionUsesEcommerceProductDetailBinding,
    selectedNestedSection,
    onBackToParent,
    inspectorTargetSummary,
    bindingWarningsContent,
    controlGroupAuditSummaryContent,
    fieldSetsContent,
}: SelectedSectionEditableFieldsProps) {
    if (!selectedSectionDraft) {
        return null;
    }

    if (!effectiveProps) {
        return (
            <div className={compact ? 'grid gap-2' : 'grid gap-3'}>
                <p className="text-xs text-destructive">{t('Selected section data is invalid.')}</p>
            </div>
        );
    }

    if (editableFieldCount === 0) {
        if (selectedSectionUsesEcommerceProductsBinding || selectedSectionUsesEcommerceProductDetailBinding) {
            return (
                <div className={compact ? 'grid gap-2' : 'grid gap-3'}>
                    <p className="text-xs text-muted-foreground">
                        {t('Data comes from backend automatically.')}
                    </p>
                </div>
            );
        }

        return (
            <div className={compact ? 'grid gap-2' : 'grid gap-3'}>
                <p className="text-xs text-muted-foreground">{t('No editable fields')}</p>
            </div>
        );
    }

    if (displayFieldCount === 0) {
        return (
            <div className={compact ? 'grid gap-2' : 'grid gap-3'}>
                {inspectorTargetSummary}
                <p className="text-xs text-muted-foreground">{t('No controls available for the selected element.')}</p>
            </div>
        );
    }

    return (
        <div className={compact ? 'grid gap-2' : 'grid gap-3'}>
            {selectedNestedSection ? (
                <div className="flex items-center gap-2 rounded border bg-muted/30 px-2 py-1.5 text-xs">
                    <span className="text-muted-foreground">{t('Editing nested section')}</span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-6 text-xs"
                        onClick={onBackToParent}
                    >
                        {t('Back to parent')}
                    </Button>
                </div>
            ) : null}
            {inspectorTargetSummary}
            {bindingWarningsContent}
            {controlGroupAuditSummaryContent}
            {fieldSetsContent}
        </div>
    );
}
