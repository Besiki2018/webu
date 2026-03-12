import { Button } from '@/components/ui/button';
import { Save, Trash2, X, Loader2 } from 'lucide-react';
import { useTranslation } from '@/contexts/LanguageContext';
import type { PendingEdit } from '@/types/inspector';

interface PendingEditsPanelProps {
    edits: PendingEdit[];
    onSaveAll: () => void;
    onDiscardAll: () => void;
    onRemoveEdit?: (id: string) => void;
    isSaving?: boolean;
}

/**
 * Panel showing pending text/attribute edits with save/discard actions.
 * Appears at the bottom of the preview when edits are pending.
 */
export function PendingEditsPanel({
    edits,
    onSaveAll,
    onDiscardAll,
    onRemoveEdit,
    isSaving = false,
}: PendingEditsPanelProps) {
    const { t } = useTranslation();

    if (edits.length === 0) return null;

    return (
        <div className="workspace-pending-panel">
            {/* Header */}
            <div className="workspace-pending-panel-header">
                <div className="flex items-center gap-2">
                    <span className="workspace-pending-count">
                        {t(':count pending changes', { count: edits.length })}
                    </span>
                    <span className="text-sm text-slate-500">
                        {t('Review before sending to AI')}
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={onDiscardAll}
                        disabled={isSaving}
                        className="h-8 rounded-full px-3 text-xs text-[#6d6b63] hover:bg-red-50 hover:text-red-500"
                    >
                        <Trash2 className="h-3.5 w-3.5 mr-1" />
                        {t('Discard All')}
                    </Button>
                    <Button
                        variant="default"
                        size="sm"
                        onClick={onSaveAll}
                        disabled={isSaving}
                        className="h-8 rounded-full bg-[#5b6cff] px-4 text-xs text-white hover:bg-[#4f60f2]"
                    >
                        {isSaving ? (
                            <>
                                <Loader2 className="h-3.5 w-3.5 mr-1 animate-spin" />
                                {t('Saving...')}
                            </>
                        ) : (
                            <>
                                <Save className="h-3.5 w-3.5 mr-1" />
                                {t('Save All')}
                            </>
                        )}
                    </Button>
                </div>
            </div>

            {/* Edit list - native scrolling for simplicity */}
            <div className="workspace-pending-list space-y-2">
                {edits.map((edit) => (
                    <EditItem
                        key={edit.id}
                        edit={edit}
                        onRemove={onRemoveEdit ? () => onRemoveEdit(edit.id) : undefined}
                    />
                ))}
            </div>
        </div>
    );
}

interface EditItemProps {
    edit: PendingEdit;
    onRemove?: () => void;
}

function EditItem({ edit, onRemove }: EditItemProps) {
    const { element, field, originalValue, newValue } = edit;

    // Truncate values for display
    const truncate = (str: string, max: number) =>
        str.length > max ? str.substring(0, max) + '...' : str;

    const displayOriginal = truncate(originalValue, 30);
    const displayNew = truncate(newValue, 30);

    return (
        <div className="workspace-pending-item">
            {/* Element info */}
            <div className="flex-1 min-w-0">
                <div className="flex items-center gap-1.5">
                    <span className="font-mono text-[#4653d3]">
                        &lt;{element.tagName}{element.cssSelector.startsWith('#') ? '' : `.${element.classNames[0] || ''}`}&gt;
                    </span>
                    {field !== 'text' && (
                        <span className="text-slate-400">
                            [{field}]
                        </span>
                    )}
                </div>
                <div className="flex items-center gap-1.5 mt-0.5">
                    <span className="line-through text-red-400" title={originalValue}>
                        &quot;{displayOriginal}&quot;
                    </span>
                    <span className="text-slate-400">&rarr;</span>
                    <span className="font-medium text-emerald-600" title={newValue}>
                        &quot;{displayNew}&quot;
                    </span>
                </div>
            </div>

            {/* Remove button */}
            {onRemove && (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={onRemove}
                    className="workspace-pending-remove p-0 text-slate-400 hover:bg-red-50 hover:text-red-500"
                >
                    <X className="h-3.5 w-3.5" />
                </Button>
            )}
        </div>
    );
}

export default PendingEditsPanel;
