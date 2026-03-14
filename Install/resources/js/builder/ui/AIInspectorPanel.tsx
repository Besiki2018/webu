import { Bot, MousePointerClick, Palette, Settings2, Sparkles, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useTranslation } from '@/contexts/LanguageContext';
import type { BuilderEditableTarget } from '@/builder/editingState';
import type { ElementMention } from '@/types/inspector';
import { buildAiNodeTag } from '@/builder/runtime/elementHover';
import { cn } from '@/lib/utils';

export interface AIInspectorPanelField {
    path: string;
    label: string;
    value: string;
}

interface AIInspectorPanelProps {
    primaryMention: ElementMention | null;
    primaryTarget: BuilderEditableTarget | null;
    selectedMentions: ElementMention[];
    textFields: AIInspectorPanelField[];
    styleFields: AIInspectorPanelField[];
    settingsFields: AIInspectorPanelField[];
    onEditWithAi: () => void;
    onEditManually: () => void;
    onInsertNodeTag: (nodeId: string) => void;
    onClose: () => void;
    manualEditDisabled?: boolean;
    manualEditDisabledReason?: string | null;
}

function trimValue(value: string | null | undefined, fallback = '—'): string {
    const normalized = typeof value === 'string' ? value.trim() : '';
    if (normalized === '') {
        return fallback;
    }

    return normalized.length > 120 ? `${normalized.slice(0, 117)}...` : normalized;
}

function renderFieldBucket(
    title: string,
    icon: typeof Sparkles,
    fields: AIInspectorPanelField[],
) {
    const Icon = icon;

    return (
        <section className="space-y-3 rounded-[22px] border border-[#e7dfd4] bg-white/88 p-4">
            <div className="flex items-center gap-2 text-[#1c1917]">
                <Icon className="h-4 w-4 text-[#4f46e5]" />
                <h3 className="text-sm font-semibold">{title}</h3>
            </div>

            {fields.length > 0 ? (
                <div className="space-y-2">
                    {fields.map((field) => (
                        <div key={`${title}-${field.path}`} className="rounded-2xl bg-[#f8f6f2] px-3 py-2">
                            <div className="flex items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="truncate text-xs font-semibold uppercase tracking-[0.16em] text-[#8a857d]">
                                        {field.label}
                                    </div>
                                    <div className="mt-1 break-words text-sm text-[#1c1917]">
                                        {field.value}
                                    </div>
                                </div>
                                <div className="shrink-0 rounded-full border border-[#ddd6fe] bg-[#eef2ff] px-2 py-0.5 text-[10px] font-medium text-[#4338ca]">
                                    {field.path}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <div className="rounded-2xl border border-dashed border-[#ddd6cf] px-3 py-4 text-sm text-[#78716c]">
                    No mapped fields in this group yet.
                </div>
            )}
        </section>
    );
}

export function AIInspectorPanel({
    primaryMention,
    primaryTarget,
    selectedMentions,
    textFields,
    styleFields,
    settingsFields,
    onEditWithAi,
    onEditManually,
    onInsertNodeTag,
    onClose,
    manualEditDisabled = false,
    manualEditDisabledReason = null,
}: AIInspectorPanelProps) {
    const { t } = useTranslation();

    if (!primaryMention && !primaryTarget) {
        return null;
    }

    const exactNodeId = primaryMention?.aiNodeId ?? null;
    const componentNodeId = primaryTarget
        ? (
            primaryTarget.sectionLocalId
            ?? primaryTarget.sectionKey
            ?? primaryTarget.componentType
            ?? null
        )
        : (primaryMention?.sectionLocalId ?? primaryMention?.sectionKey ?? null);
    const availableNodeIds = Array.from(new Set([
        exactNodeId,
        componentNodeId,
        ...selectedMentions.map((mention) => mention.aiNodeId ?? null),
    ].filter((value): value is string => typeof value === 'string' && value.trim() !== '')));
    const isProtectedStructure = /header|footer/i.test(
        primaryTarget?.sectionKey
        ?? primaryTarget?.componentType
        ?? primaryMention?.componentKey
        ?? ''
    );

    return (
        <div className="pointer-events-auto w-full max-w-[380px] rounded-[28px] border border-[#ded8cf] bg-[rgba(255,252,248,0.96)] p-4 text-left shadow-[0_28px_80px_rgba(15,23,42,0.18)] backdrop-blur">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[#8a857d]">
                        {t('AI Inspect')}
                    </div>
                    <h2 className="mt-1 truncate text-lg font-semibold text-[#1c1917]">
                        {primaryTarget?.componentName
                            ?? primaryTarget?.componentType
                            ?? primaryMention?.componentKey
                            ?? t('Selected element')}
                    </h2>
                    <p className="mt-1 text-sm text-[#625f57]">
                        {selectedMentions.length > 1
                            ? t(':count nodes selected for AI targeting', { count: selectedMentions.length })
                            : t('Inspect structure, content, styles, and AI-safe settings.')}
                    </p>
                </div>

                <button
                    type="button"
                    onClick={onClose}
                    className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-[#e7dfd4] bg-white text-[#625f57] transition hover:text-[#1c1917]"
                    aria-label={t('Close inspector')}
                    title={t('Close inspector')}
                >
                    <X className="h-4 w-4" />
                </button>
            </div>

            <div className="mt-4 grid grid-cols-1 gap-2 rounded-[24px] border border-[#ebe5da] bg-white/90 p-3 text-sm text-[#1c1917]">
                <div>
                    <div className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#8a857d]">{t('nodeId')}</div>
                    <div className="mt-1 break-all font-mono text-xs">{trimValue(exactNodeId)}</div>
                </div>
                <div>
                    <div className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#8a857d]">{t('componentKey')}</div>
                    <div className="mt-1 break-all font-mono text-xs">{trimValue(primaryTarget?.componentType ?? primaryMention?.componentKey)}</div>
                </div>
                <div>
                    <div className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#8a857d]">{t('propName')}</div>
                    <div className="mt-1 break-all font-mono text-xs">{trimValue(primaryMention?.propName ?? primaryMention?.parameterName ?? primaryTarget?.path)}</div>
                </div>
                <div>
                    <div className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#8a857d]">{t('currentValue')}</div>
                    <div className="mt-1 text-sm">{trimValue(primaryMention?.currentValue ?? primaryMention?.textPreview ?? primaryTarget?.textPreview)}</div>
                </div>
            </div>

            {availableNodeIds.length > 0 ? (
                <div className="mt-4 space-y-2">
                    <div className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#8a857d]">
                        {t('AI targets')}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {availableNodeIds.map((nodeId) => (
                            <button
                                key={nodeId}
                                type="button"
                                onClick={() => onInsertNodeTag(nodeId)}
                                className={cn(
                                    'rounded-full border px-3 py-1.5 text-left text-xs font-medium transition',
                                    nodeId === exactNodeId
                                        ? 'border-[#c7d2fe] bg-[#eef2ff] text-[#3730a3]'
                                        : 'border-[#e7dfd4] bg-white text-[#57534e] hover:border-[#c7d2fe] hover:text-[#3730a3]',
                                )}
                                title={buildAiNodeTag(nodeId)}
                            >
                                <span className="block font-mono">{buildAiNodeTag(nodeId)}</span>
                            </button>
                        ))}
                    </div>
                </div>
            ) : null}

            <div className="mt-4 flex flex-wrap gap-2">
                <Button
                    type="button"
                    onClick={onEditWithAi}
                    className="rounded-full"
                >
                    <Bot className="mr-2 h-4 w-4" />
                    {t('Edit with AI')}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    onClick={onEditManually}
                    disabled={manualEditDisabled}
                    title={manualEditDisabled ? (manualEditDisabledReason ?? t('Manual edit is unavailable for this selection.')) : t('Edit manually')}
                    className="rounded-full"
                >
                    <MousePointerClick className="mr-2 h-4 w-4" />
                    {t('Edit manually')}
                </Button>
            </div>

            {manualEditDisabledReason ? (
                <p className="mt-2 text-xs leading-5 text-[#8a857d]">
                    {manualEditDisabledReason}
                </p>
            ) : null}

            {isProtectedStructure ? (
                <div className="mt-4 rounded-[20px] border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-950">
                    {t('Structural protections are active for headers and footers. AI edits are limited to safe content, style, and variant changes.')}
                </div>
            ) : null}

            <div className="mt-4 space-y-3">
                {renderFieldBucket(t('Text'), Sparkles, textFields)}
                {renderFieldBucket(t('Styles'), Palette, styleFields)}
                {renderFieldBucket(t('Component settings'), Settings2, settingsFields)}
            </div>
        </div>
    );
}
