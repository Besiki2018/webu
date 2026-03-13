import {
    type ComponentProps,
    type MutableRefObject,
    type ReactNode,
    type RefObject,
    Suspense,
    lazy,
    useCallback,
    useEffect,
    useMemo,
    useRef,
} from 'react';
import {
    DndContext,
    DragOverlay,
    useDndMonitor,
    useDraggable,
    useDroppable,
} from '@dnd-kit/core';
import { SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useVirtualizer } from '@tanstack/react-virtual';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    CheckCircle2,
    ChevronRight,
    Copy,
    ExternalLink,
    GripVertical,
    ImagePlus,
    Layers,
    Loader2,
    Monitor,
    Plus,
    RefreshCw,
    Redo2,
    Save,
    Smartphone,
    Sparkles,
    Tablet,
    Trash2,
    Undo2,
    Wand2,
    type LucideIcon,
} from 'lucide-react';

import type { AIWebsitePromptPayload } from '@/components/Project/AIWebsitePromptPanel';
import type { DesignImportPayload } from '@/components/Project/DesignImportPanel';
import type { AIImprovementItem } from '@/components/Project/AIImproveSitePanel';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import type { ComponentScoringReport } from '@/builder/ai/componentScoring';
import type { BuilderEditableTarget } from '@/builder/editingState';
import type { SectionDraft } from '@/builder/state/useBuilderCanvasState';
import {
    getValueAtPath,
    parseSectionProps as parseBuilderSectionProps,
} from '@/builder/state/sectionProps';
import { parseVisualDropId, type DropTarget, VISUAL_DROP_PREFIX } from '@/builder/visual/types';
import { resolveBuilderWidgetIcon as resolveBuilderWidgetIconKey } from '@/lib/resolveBuilderWidgetIcon';
import { cn } from '@/lib/utils';

const AIWebsitePromptPanel = lazy(async () => ({ default: (await import('@/components/Project/AIWebsitePromptPanel')).AIWebsitePromptPanel }));
const DesignImportPanel = lazy(async () => ({ default: (await import('@/components/Project/DesignImportPanel')).DesignImportPanel }));
const RefineLayoutPanel = lazy(async () => ({ default: (await import('@/components/Project/RefineLayoutPanel')).RefineLayoutPanel }));
const AIImproveSitePanel = lazy(async () => ({ default: (await import('@/components/Project/AIImproveSitePanel')).AIImproveSitePanel }));
const StructurePanel = lazy(async () => ({ default: (await import('@/builder/visual/StructurePanel')).StructurePanel }));
const BuilderCanvas = lazy(async () => ({ default: (await import('@/builder/visual/BuilderCanvas')).BuilderCanvas }));

const BUILDER_CANVAS_DROP_ID = 'cms-builder-canvas-drop-zone';
const BUILDER_LAYERS_VIRTUALIZE_THRESHOLD = 20;

type BuilderPreviewMode = 'desktop' | 'tablet' | 'mobile';
type BuilderSidebarMode = 'elements' | 'settings' | 'design-system';
type TranslationFn = (key: string, params?: Record<string, string | number>) => string;

interface SectionLibraryItemLike {
    id: number;
    key: string;
    category: string;
    label?: string;
    description?: string | null;
}

interface GroupedSectionLibrary {
    category: string;
    items: SectionLibraryItemLike[];
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function normalizeSectionTypeKey(value: unknown): string {
    return typeof value === 'string' ? value.trim().toLowerCase() : '';
}

interface NestedSectionDisplayItem {
    type: string;
    label: string;
    path: number[];
    children?: NestedSectionDisplayItem[];
}

interface SectionCardDisplayState {
    isLayoutPrimitive: boolean;
    label: string;
    nestedSections: NestedSectionDisplayItem[];
    previewText: string;
}

interface AddInsideOption {
    key: string;
    label: string;
}

interface CmsVisualBuilderShellProps {
    activeContentLocale: string;
    activeDragId: string | null;
    addInsideSectionOptions: AddInsideOption[];
    aiImproveApplyingIndex: number | null;
    aiImproveItems: AIImprovementItem[];
    aiImproveScoring: ComponentScoringReport | null;
    autoImproveEnabled: boolean;
    bindingValidationWarningsCount: number;
    bindingWarningsSummaryContent: ReactNode;
    builderCurrentDropTarget: DropTarget | null;
    builderHoveredElementId: string | null;
    builderPreviewMode: BuilderPreviewMode;
    builderSectionLibrary: SectionLibraryItemLike[];
    builderSidebarMode: BuilderSidebarMode;
    builderViewportRef: MutableRefObject<HTMLElement | null>;
    builderVisualPreviewUrl: string | null;
    canRedo: boolean;
    canUndo: boolean;
    collisionDetection: NonNullable<ComponentProps<typeof DndContext>['collisionDetection']>;
    designImportOpen: boolean;
    embeddedSidebarContent: ReactNode;
    expandedComponentCategories: Record<string, boolean>;
    getLibraryItemDisplayLabel: (item: SectionLibraryItemLike) => string;
    groupedSectionLibrary: GroupedSectionLibrary[];
    hoveredBuilderTarget: BuilderEditableTarget | null;
    isAIImproveSiteOpen: boolean;
    isAIWebsiteGenerating: boolean;
    isAIWebsitePromptOpen: boolean;
    isApplyingAllAIImprovements: boolean;
    isDesignImportGenerating: boolean;
    isEmbeddedMode: boolean;
    isEmbeddedPreviewMode: boolean;
    isEmbeddedSidebarMode: boolean;
    isHomePageSelected: boolean;
    isPublishingPage: boolean;
    isRefineLayoutOpen: boolean;
    isSavingRevision: boolean;
    isStructurePanelCollapsed: boolean;
    layoutPrimitiveSectionKeys: string[];
    onAddSectionByKey: (sectionKey: string) => void;
    onAddSectionInside: (parentLocalId: string, sectionKey: string) => void;
    onAddSectionInsideAtPath: (parentLocalId: string, path: number[], sectionKey: string) => void;
    onAiImproveSiteOpenChange: (open: boolean) => void;
    onAiWebsitePromptOpenChange: (open: boolean) => void;
    onAiWebsitePromptSubmit: (payload: AIWebsitePromptPayload) => void | Promise<void>;
    onApplyAIImprovement: (index: number) => void | Promise<void>;
    onApplyAllAIImprovements: () => void | Promise<void>;
    onBuilderCurrentDropTargetChange: (target: DropTarget | null) => void;
    onBuilderPreviewModeChange: (mode: BuilderPreviewMode) => void;
    onCanvasDeselect: () => void;
    onCanvasEditSection: (localId: string) => void;
    onCanvasHover: (localId: string | null) => void;
    onCanvasHoverTarget: (target: BuilderEditableTarget | null) => void;
    onCanvasSelect: (localId: string) => void;
    onCanvasSelectTarget: (target: BuilderEditableTarget) => void;
    onDesignImportOpenChange: (open: boolean) => void;
    onDesignImportSubmit: (payload: DesignImportPayload) => void | Promise<void>;
    onDragCancel: NonNullable<ComponentProps<typeof DndContext>['onDragCancel']>;
    onDragEnd: NonNullable<ComponentProps<typeof DndContext>['onDragEnd']>;
    onDragStart: NonNullable<ComponentProps<typeof DndContext>['onDragStart']>;
    onDuplicateSection: (localId: string) => void;
    onExitBuilder: () => void;
    onExpandedComponentCategoryChange: (category: string, open: boolean) => void;
    onFocusSection: (localId: string) => void;
    onMoveNestedSection: (parentLocalId: string, path: number[], direction: 'up' | 'down') => void;
    onMoveSection: (localId: string, direction: 'up' | 'down') => void;
    onOpenDesignSystemSidebar: () => void;
    onOpenDraftSyncedPreview: (url: string) => void | Promise<void>;
    onOpenElementsSidebar: () => void;
    onOpenRefineLayoutChange: (open: boolean) => void;
    onOpenSettingsSidebar: () => void;
    onOptimizeLayout: () => void | Promise<void>;
    onPasteSection: () => void;
    onPublishPage: () => void | Promise<void>;
    onRedo: () => void;
    onRefreshPreview: () => void;
    onRefineLayoutSubmit: (input: string) => void | Promise<void>;
    onRemoveNestedSection: (parentLocalId: string, path: number[]) => void;
    onRemoveSection: (localId: string) => void;
    onSaveDraftRevision: () => void | Promise<void>;
    onSectionSearchChange: (value: string) => void;
    onSelectNestedSection: (parentLocalId: string, path: number[]) => void;
    onSetAutoImproveEnabled: (enabled: boolean) => void;
    onSetStructurePanelCollapsed: (collapsed: boolean) => void;
    onSetStructurePanelPosition: (position: { x: number; y: number }) => void;
    onUndo: () => void;
    sectionDisplayLabelByKey: Map<string, string>;
    sectionSearch: string;
    sectionsDraft: SectionDraft[];
    selectedBuilderTarget: BuilderEditableTarget | null;
    selectedPageSlug: string | null;
    selectedPageTitle: string | null;
    selectedSectionLocalId: string | null;
    selectedNestedParentLocalId: string | null;
    selectedNestedPath: number[] | null;
    sensors: NonNullable<ComponentProps<typeof DndContext>['sensors']>;
    standaloneDesignSystemContent: ReactNode;
    standaloneSettingsContent: ReactNode;
    structurePanelPosition: { x: number; y: number };
    t: TranslationFn;
}

function getLibraryDragId(sectionKey: string): string {
    return `library:${sectionKey}`;
}

function extractLibrarySectionKey(dragId: string): string | null {
    return dragId.startsWith('library:') ? dragId.slice('library:'.length) : null;
}

function getShortLibraryLabel(sectionKey: string, displayLabel: string): string {
    if (displayLabel.length <= 12) {
        return displayLabel;
    }

    const normalized = sectionKey.trim();
    return normalized.length > 0 ? normalized.slice(0, 10).trim() + '…' : displayLabel.slice(0, 10).trim() + '…';
}

function resolveBuilderWidgetIcon(item: SectionLibraryItemLike): LucideIcon {
    return resolveBuilderWidgetIconKey(item.key, item.category);
}

interface DraggableLibraryIconTileProps {
    displayLabel: string;
    draggable?: boolean;
    item: SectionLibraryItemLike;
    onAdd: (sectionKey: string) => void;
}

function DraggableLibraryIconTile({
    displayLabel,
    draggable = true,
    item,
    onAdd,
}: DraggableLibraryIconTileProps) {
    const { attributes, isDragging, listeners, setNodeRef, transform } = useDraggable({
        id: getLibraryDragId(item.key),
        disabled: !draggable,
    });
    const suppressClickAfterDragRef = useRef(false);

    useEffect(() => {
        if (isDragging) {
            suppressClickAfterDragRef.current = true;
        }
    }, [isDragging]);

    const Icon = resolveBuilderWidgetIcon(item);
    const shortLabel = getShortLibraryLabel(item.key, displayLabel);

    return (
        <button
            ref={setNodeRef}
            type="button"
            style={{
                transform: CSS.Translate.toString(transform),
                opacity: isDragging ? 0.6 : 1,
            }}
            className={cn(
                'rounded-md border bg-background p-2 transition hover:border-primary/50 hover:bg-primary/5',
                'flex flex-col items-center justify-center gap-2 min-h-[72px] w-full min-w-0',
                draggable ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer',
            )}
            onClick={() => {
                if (suppressClickAfterDragRef.current) {
                    suppressClickAfterDragRef.current = false;
                    return;
                }

                onAdd(item.key);
            }}
            {...attributes}
            {...listeners}
            title={displayLabel}
        >
            <div className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground">
                <Icon className="h-4 w-4" />
            </div>
            <span className="text-[11px] leading-tight line-clamp-1 w-full text-center min-w-0 break-words">{shortLabel}</span>
        </button>
    );
}

interface SortableCanvasSectionCardProps {
    addInsideSectionOptions: AddInsideOption[];
    canMoveDown: boolean;
    canMoveUp: boolean;
    index: number;
    isSelected: boolean;
    label: string;
    layoutPrimitiveKeys: string[];
    nestedSections: NestedSectionDisplayItem[];
    onAddSectionInside: (localId: string, sectionKey: string) => void;
    onAddSectionInsideAtPath: (parentLocalId: string, path: number[], sectionKey: string) => void;
    onDuplicate: () => void;
    onMoveDown: () => void;
    onMoveNestedSection: (parentLocalId: string, path: number[], direction: 'up' | 'down') => void;
    onMoveUp: () => void;
    onRemove: () => void;
    onRemoveNestedSection: (parentLocalId: string, path: number[]) => void;
    onSelect: () => void;
    onSelectNestedSection: (parentLocalId: string, path: number[]) => void;
    previewText: string;
    section: SectionDraft;
    selectedNestedParentLocalId: string | null;
    selectedNestedPath: number[] | null;
    showAddInside: boolean;
    t: TranslationFn;
}

function SortableCanvasSectionCard({
    addInsideSectionOptions,
    canMoveDown,
    canMoveUp,
    index,
    isSelected,
    label,
    layoutPrimitiveKeys,
    nestedSections,
    onAddSectionInside,
    onAddSectionInsideAtPath,
    onDuplicate,
    onMoveDown,
    onMoveNestedSection,
    onMoveUp,
    onRemove,
    onRemoveNestedSection,
    onSelect,
    onSelectNestedSection,
    previewText,
    section,
    selectedNestedParentLocalId,
    selectedNestedPath,
    showAddInside,
    t,
}: SortableCanvasSectionCardProps) {
    const { attributes, isDragging, listeners, setNodeRef, transform, transition } = useSortable({
        id: section.localId,
    });

    const isNestedPathEqual = (a: number[] | null | undefined, b: number[]) =>
        a != null && a.length === b.length && a.every((value, indexOfValue) => value === b[indexOfValue]);

    return (
        <div
            ref={setNodeRef}
            style={{
                transform: CSS.Transform.toString(transform),
                transition,
                opacity: isDragging ? 0.5 : 1,
            }}
            role="button"
            tabIndex={0}
            onClick={onSelect}
            onKeyDown={(event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    onSelect();
                }
            }}
            className={`w-full text-start rounded-lg border p-3 transition-colors ${
                isSelected ? 'border-primary bg-primary/5' : 'hover:bg-muted/30'
            }`}
        >
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="text-xs text-muted-foreground">{t('Section')} #{index + 1}</p>
                    <p className="text-sm font-medium truncate">{label}</p>
                    <p className="text-xs text-muted-foreground truncate">{previewText}</p>
                </div>
                <div className="flex items-center gap-1">
                    <Button type="button" size="icon" variant="ghost" className="h-7 w-7 cursor-grab active:cursor-grabbing" {...attributes} {...listeners}>
                        <GripVertical className="h-3.5 w-3.5" />
                    </Button>
                    <Button type="button" size="icon" variant="ghost" className="h-7 w-7" disabled={!canMoveUp} onClick={(event) => {
                        event.stopPropagation();
                        onMoveUp();
                    }}>
                        <ArrowUp className="h-3.5 w-3.5" />
                    </Button>
                    <Button type="button" size="icon" variant="ghost" className="h-7 w-7" disabled={!canMoveDown} onClick={(event) => {
                        event.stopPropagation();
                        onMoveDown();
                    }}>
                        <ArrowDown className="h-3.5 w-3.5" />
                    </Button>
                    <Button type="button" size="icon" variant="ghost" className="h-7 w-7" onClick={(event) => {
                        event.stopPropagation();
                        onDuplicate();
                    }}>
                        <Copy className="h-3.5 w-3.5" />
                    </Button>
                    <Button type="button" size="icon" variant="ghost" className="h-7 w-7 text-destructive" onClick={(event) => {
                        event.stopPropagation();
                        onRemove();
                    }}>
                        <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                    {showAddInside ? (
                        addInsideSectionOptions.length > 0 ? (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        className="h-7 w-7"
                                        title={t('Add section inside')}
                                        aria-label={t('Add section inside')}
                                        onClick={(event) => event.stopPropagation()}
                                    >
                                        <Plus className="h-3.5 w-3.5" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="max-h-[280px] overflow-y-auto" onClick={(event) => event.stopPropagation()}>
                                    {addInsideSectionOptions.map((option) => (
                                        <DropdownMenuItem key={option.key} onSelect={() => onAddSectionInside(section.localId, option.key)}>
                                            {option.label}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        ) : (
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="h-7 w-7"
                                title={t('Add section inside')}
                                aria-label={t('Add section inside')}
                                onClick={(event) => {
                                    event.stopPropagation();
                                    onAddSectionInside(section.localId, 'webu_general_text_01');
                                }}
                            >
                                <Plus className="h-3.5 w-3.5" />
                            </Button>
                        )
                    ) : null}
                </div>
            </div>

            {showAddInside && nestedSections.length > 0 ? (
                <div className="mt-2 pl-3 border-l-2 border-muted space-y-1">
                    {(function renderNestedItems(items: NestedSectionDisplayItem[]): ReactNode {
                        return items.map((nestedItem, nestedIndex) => {
                            const canMoveNestedUp = nestedIndex > 0;
                            const canMoveNestedDown = nestedIndex < items.length - 1;
                            const isNestedSelected =
                                selectedNestedParentLocalId === section.localId
                                && isNestedPathEqual(selectedNestedPath, nestedItem.path);

                            return (
                                <div key={nestedItem.path.join('-')}>
                                    <div
                                        role="button"
                                        tabIndex={0}
                                        className={`flex items-center justify-between gap-1 py-1.5 pr-1 rounded text-xs cursor-pointer hover:bg-muted/50 ${isNestedSelected ? 'bg-primary/10' : ''}`}
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            onSelectNestedSection(section.localId, nestedItem.path);
                                        }}
                                        onKeyDown={(event) => {
                                            if (event.key === 'Enter' || event.key === ' ') {
                                                event.preventDefault();
                                                event.stopPropagation();
                                                onSelectNestedSection(section.localId, nestedItem.path);
                                            }
                                        }}
                                    >
                                        <span className="text-muted-foreground truncate min-w-0">{nestedItem.label}</span>
                                        <div className="flex items-center gap-0 shrink-0">
                                            {addInsideSectionOptions.length > 0 && layoutPrimitiveKeys.includes((nestedItem.type || '').toLowerCase()) ? (
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            className="h-6 w-6 text-muted-foreground"
                                                            title={t('Add section inside')}
                                                            aria-label={t('Add section inside')}
                                                            onClick={(event) => event.stopPropagation()}
                                                        >
                                                            <Plus className="h-3 w-3" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end" className="max-h-[280px] overflow-y-auto" onClick={(event) => event.stopPropagation()}>
                                                        {addInsideSectionOptions.map((option) => (
                                                            <DropdownMenuItem
                                                                key={option.key}
                                                                onSelect={() => onAddSectionInsideAtPath(section.localId, nestedItem.path, option.key)}
                                                            >
                                                                {option.label}
                                                            </DropdownMenuItem>
                                                        ))}
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            ) : null}
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                className="h-6 w-6 text-muted-foreground"
                                                disabled={!canMoveNestedUp}
                                                aria-label={t('Move up')}
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    onMoveNestedSection(section.localId, nestedItem.path, 'up');
                                                }}
                                            >
                                                <ArrowUp className="h-3 w-3" />
                                            </Button>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                className="h-6 w-6 text-muted-foreground"
                                                disabled={!canMoveNestedDown}
                                                aria-label={t('Move down')}
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    onMoveNestedSection(section.localId, nestedItem.path, 'down');
                                                }}
                                            >
                                                <ArrowDown className="h-3 w-3" />
                                            </Button>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                className="h-6 w-6 text-muted-foreground hover:text-destructive"
                                                aria-label={t('Remove nested section')}
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    onRemoveNestedSection(section.localId, nestedItem.path);
                                                }}
                                            >
                                                <Trash2 className="h-3 w-3" />
                                            </Button>
                                        </div>
                                    </div>
                                    {nestedItem.children && nestedItem.children.length > 0 ? (
                                        <div className="mt-1 pl-3 border-l-2 border-muted/70 space-y-1">
                                            {renderNestedItems(nestedItem.children)}
                                        </div>
                                    ) : null}
                                </div>
                            );
                        });
                    })(nestedSections)}
                </div>
            ) : null}
        </div>
    );
}

function BuilderCanvasDropZone({ children }: { children: ReactNode }) {
    const { isOver, setNodeRef } = useDroppable({
        id: BUILDER_CANVAS_DROP_ID,
    });

    return (
        <div ref={setNodeRef} className={`rounded-lg border ${isOver ? 'border-primary bg-primary/5' : 'border-dashed'}`}>
            {children}
        </div>
    );
}

function BuilderVisualDropMonitor({
    onChange,
    sectionsDraft,
}: {
    onChange: (target: DropTarget | null) => void;
    sectionsDraft: SectionDraft[];
}) {
    useDndMonitor({
        onDragOver(event) {
            const activeId = String(event.active?.id ?? '');
            const overId = event.over?.id != null ? String(event.over.id) : null;

            if (!extractLibrarySectionKey(activeId) || !overId || !overId.startsWith(VISUAL_DROP_PREFIX)) {
                onChange(null);
                return;
            }

            const parsed = parseVisualDropId(overId);
            if (!parsed) {
                onChange(null);
                return;
            }

            const sectionIndex = parsed.sectionLocalId === null
                ? (parsed.position === 'before' ? 0 : sectionsDraft.length)
                : sectionsDraft.findIndex((section) => section.localId === parsed.sectionLocalId);

            onChange({
                sectionLocalId: parsed.sectionLocalId,
                sectionIndex: sectionIndex >= 0 ? sectionIndex : 0,
                position: parsed.position,
            });
        },
    });

    return null;
}

export function CmsVisualBuilderShell({
    activeContentLocale,
    activeDragId,
    addInsideSectionOptions,
    aiImproveApplyingIndex,
    aiImproveItems,
    aiImproveScoring,
    autoImproveEnabled,
    bindingValidationWarningsCount,
    bindingWarningsSummaryContent,
    builderCurrentDropTarget,
    builderHoveredElementId,
    builderPreviewMode,
    builderSectionLibrary,
    builderSidebarMode,
    builderViewportRef,
    builderVisualPreviewUrl,
    canRedo,
    canUndo,
    collisionDetection,
    designImportOpen,
    embeddedSidebarContent,
    expandedComponentCategories,
    getLibraryItemDisplayLabel,
    groupedSectionLibrary,
    hoveredBuilderTarget,
    isAIImproveSiteOpen,
    isAIWebsiteGenerating,
    isAIWebsitePromptOpen,
    isApplyingAllAIImprovements,
    isDesignImportGenerating,
    isEmbeddedMode,
    isEmbeddedPreviewMode,
    isEmbeddedSidebarMode,
    isHomePageSelected,
    isPublishingPage,
    isRefineLayoutOpen,
    isSavingRevision,
    isStructurePanelCollapsed,
    layoutPrimitiveSectionKeys,
    onAddSectionByKey,
    onAddSectionInside,
    onAddSectionInsideAtPath,
    onAiImproveSiteOpenChange,
    onAiWebsitePromptOpenChange,
    onAiWebsitePromptSubmit,
    onApplyAIImprovement,
    onApplyAllAIImprovements,
    onBuilderCurrentDropTargetChange,
    onBuilderPreviewModeChange,
    onCanvasDeselect,
    onCanvasEditSection,
    onCanvasHover,
    onCanvasHoverTarget,
    onCanvasSelect,
    onCanvasSelectTarget,
    onDesignImportOpenChange,
    onDesignImportSubmit,
    onDragCancel,
    onDragEnd,
    onDragStart,
    onDuplicateSection,
    onExitBuilder,
    onExpandedComponentCategoryChange,
    onFocusSection,
    onMoveNestedSection,
    onMoveSection,
    onOpenDesignSystemSidebar,
    onOpenDraftSyncedPreview,
    onOpenElementsSidebar,
    onOpenRefineLayoutChange,
    onOpenSettingsSidebar,
    onOptimizeLayout,
    onPasteSection,
    onPublishPage,
    onRedo,
    onRefreshPreview,
    onRefineLayoutSubmit,
    onRemoveNestedSection,
    onRemoveSection,
    onSaveDraftRevision,
    onSectionSearchChange,
    onSelectNestedSection,
    onSetAutoImproveEnabled,
    onSetStructurePanelCollapsed,
    onSetStructurePanelPosition,
    onUndo,
    sectionDisplayLabelByKey,
    sectionSearch,
    sectionsDraft,
    selectedBuilderTarget,
    selectedPageSlug,
    selectedPageTitle,
    selectedSectionLocalId,
    selectedNestedParentLocalId,
    selectedNestedPath,
    sensors,
    standaloneDesignSystemContent,
    standaloneSettingsContent,
    structurePanelPosition,
    t,
}: CmsVisualBuilderShellProps) {
    const builderLayersScrollRef = useRef<HTMLDivElement>(null);
    const layersVirtualizer = useVirtualizer({
        count: sectionsDraft.length,
        getScrollElement: () => builderLayersScrollRef.current,
        estimateSize: () => 52,
        overscan: 5,
    });
    const parseSectionProps = useCallback((raw: string): Record<string, unknown> | null => {
        return parseBuilderSectionProps(raw);
    }, []);

    const lazyPanelFallback = (
        <div className="flex min-h-[160px] items-center justify-center p-4 text-sm text-muted-foreground">
            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
            {t('Loading panel...')}
        </div>
    );

    const builderCanvasViewportClass = useMemo(() => {
        if (builderPreviewMode === 'tablet') {
            return 'mx-auto w-full max-w-[840px]';
        }

        if (builderPreviewMode === 'mobile') {
            return 'mx-auto w-full max-w-[430px]';
        }

        return 'w-full';
    }, [builderPreviewMode]);
    const buildNestedSectionDisplayItems = useCallback(function buildNestedSectionDisplayItems(
        sections: unknown[],
        parentPath: number[] = [],
    ): NestedSectionDisplayItem[] {
        return sections.map((item: unknown, index) => {
            const record = isRecord(item) ? item : {};
            const nestedType = typeof record.type === 'string' ? record.type : (typeof record.key === 'string' ? record.key : '');
            const path = [...parentPath, index];
            const normalizedType = normalizeSectionTypeKey(nestedType);
            const subSections = isRecord(record.props) && Array.isArray(record.props.sections)
                ? record.props.sections
                : null;
            const children = layoutPrimitiveSectionKeys.includes(normalizedType) && Array.isArray(subSections) && subSections.length > 0
                ? buildNestedSectionDisplayItems(subSections, path)
                : undefined;

            return {
                type: nestedType || 'section',
                label: (sectionDisplayLabelByKey.get(normalizedType) ?? sectionDisplayLabelByKey.get(nestedType) ?? nestedType) || t('Section'),
                path,
                children: children && children.length > 0 ? children : undefined,
            };
        });
    }, [layoutPrimitiveSectionKeys, sectionDisplayLabelByKey, t]);

    const resolveSectionCardDisplayState = useCallback((section: SectionDraft): SectionCardDisplayState => {
        const parsedProps = parseSectionProps(section.propsText);
        const normalizedType = normalizeSectionTypeKey(section.type);
        const label = sectionDisplayLabelByKey.get(normalizedType) ?? sectionDisplayLabelByKey.get(section.type) ?? section.type;
        const previewTextCandidates = [
            parsedProps ? getValueAtPath(parsedProps, ['headline']) : null,
            parsedProps ? getValueAtPath(parsedProps, ['title']) : null,
            parsedProps ? getValueAtPath(parsedProps, ['subtitle']) : null,
            parsedProps ? getValueAtPath(parsedProps, ['label']) : null,
        ];
        const previewTextRaw = previewTextCandidates.find((value) => typeof value === 'string' && String(value).trim() !== '');
        const isLayoutPrimitive = layoutPrimitiveSectionKeys.includes(normalizedType);
        const rawNested = isLayoutPrimitive && parsedProps && Array.isArray(parsedProps.sections) ? parsedProps.sections : [];

        return {
            isLayoutPrimitive,
            label,
            nestedSections: buildNestedSectionDisplayItems(rawNested),
            previewText: typeof previewTextRaw === 'string' ? previewTextRaw : t('No preview text'),
        };
    }, [buildNestedSectionDisplayItems, layoutPrimitiveSectionKeys, parseSectionProps, sectionDisplayLabelByKey, t]);

    const activeDragSectionKey = useMemo(() => {
        if (!activeDragId) {
            return null;
        }

        const draggedFromLibrary = extractLibrarySectionKey(activeDragId);
        if (draggedFromLibrary) {
            return draggedFromLibrary;
        }

        return sectionsDraft.find((section) => section.localId === activeDragId)?.type ?? null;
    }, [activeDragId, sectionsDraft]);

    const activeDragLibraryItem = useMemo(() => {
        if (!activeDragSectionKey) {
            return null;
        }

        return builderSectionLibrary.find((item) => item.key === activeDragSectionKey) ?? null;
    }, [activeDragSectionKey, builderSectionLibrary]);

    const activeDragLabel = useMemo(() => {
        if (!activeDragId) {
            return null;
        }

        const draggedFromLibrary = extractLibrarySectionKey(activeDragId);
        if (draggedFromLibrary) {
            return builderSectionLibrary.find((item) => item.key === draggedFromLibrary)?.label || draggedFromLibrary;
        }

        const draggedSection = sectionsDraft.find((section) => section.localId === activeDragId);
        if (!draggedSection) {
            return null;
        }

        return builderSectionLibrary.find((item) => item.key === draggedSection.type)?.label || draggedSection.type;
    }, [activeDragId, builderSectionLibrary, sectionsDraft]);

    const ActiveDragIcon = useMemo<LucideIcon>(() => {
        if (!activeDragLibraryItem) {
            return Layers;
        }

        return resolveBuilderWidgetIcon(activeDragLibraryItem);
    }, [activeDragLibraryItem]);

    return (
        <div className={cn('cms-visual-builder', isEmbeddedMode ? 'cms-visual-builder--embedded' : 'cms-visual-builder--standalone')}>
            <Suspense fallback={null}>
                <AIWebsitePromptPanel
                    open={isAIWebsitePromptOpen}
                    onOpenChange={onAiWebsitePromptOpenChange}
                    onSubmit={onAiWebsitePromptSubmit}
                    isGenerating={isAIWebsiteGenerating}
                    t={t}
                />
                <DesignImportPanel
                    open={designImportOpen}
                    onOpenChange={onDesignImportOpenChange}
                    onSubmit={onDesignImportSubmit}
                    isProcessing={isDesignImportGenerating}
                    t={t}
                />
                <RefineLayoutPanel
                    open={isRefineLayoutOpen}
                    onOpenChange={onOpenRefineLayoutChange}
                    onSubmit={onRefineLayoutSubmit}
                    hasSections={sectionsDraft.length > 0}
                    t={t}
                />
                <AIImproveSitePanel
                    open={isAIImproveSiteOpen}
                    onOpenChange={onAiImproveSiteOpenChange}
                    improvements={aiImproveItems}
                    onApplyImprovement={onApplyAIImprovement}
                    onApplyAllImprovements={onApplyAllAIImprovements}
                    applyingIndex={aiImproveApplyingIndex}
                    isApplyingAll={isApplyingAllAIImprovements}
                    hasSections={sectionsDraft.length > 0}
                    scoring={aiImproveScoring}
                    isAutoImproveMode={autoImproveEnabled}
                    t={t}
                />
            </Suspense>

            <DndContext
                sensors={sensors}
                collisionDetection={collisionDetection}
                onDragStart={onDragStart}
                onDragEnd={onDragEnd}
                onDragCancel={onDragCancel}
            >
                <BuilderVisualDropMonitor sectionsDraft={sectionsDraft} onChange={onBuilderCurrentDropTargetChange} />

                <div className="h-full flex">
                    {!isEmbeddedPreviewMode ? (
                        <aside className={isEmbeddedSidebarMode ? 'w-full min-h-full overflow-y-auto bg-card' : 'w-[320px] border-e bg-card flex flex-col'}>
                            {!isEmbeddedMode ? (
                                <div className="h-14 px-3 border-b flex items-center justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="text-[11px] uppercase tracking-wide text-muted-foreground">{t('Visual Builder')}</p>
                                        <p className="text-sm font-semibold truncate">{selectedPageTitle ?? t('Page')}</p>
                                    </div>
                                    <Button type="button" size="icon" variant="ghost" className="h-8 w-8 shrink-0" onClick={onExitBuilder}>
                                        <ArrowLeft className="h-4 w-4" />
                                    </Button>
                                </div>
                            ) : null}

                            <div className={isEmbeddedSidebarMode ? 'min-h-full p-3 space-y-3' : 'flex-1 overflow-y-auto p-3 space-y-3'}>
                                {isEmbeddedSidebarMode ? (
                                    builderSidebarMode === 'settings' ? (
                                        embeddedSidebarContent
                                    ) : (
                                        <div className="space-y-4">
                                            {builderSectionLibrary.length === 0 ? (
                                                <p className="text-xs text-muted-foreground">{t('Nothing found')}</p>
                                            ) : (
                                                <div className="grid grid-cols-2 gap-2">
                                                    {builderSectionLibrary.map((item) => (
                                                        <DraggableLibraryIconTile
                                                            key={item.id}
                                                            item={item}
                                                            displayLabel={getLibraryItemDisplayLabel(item)}
                                                            onAdd={onAddSectionByKey}
                                                            draggable
                                                        />
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    )
                                ) : (
                                    <>
                                        <Button type="button" variant="secondary" size="sm" className="w-full gap-2" onClick={() => onAiWebsitePromptOpenChange(true)}>
                                            <Sparkles className="h-3.5 w-3.5 shrink-0" />
                                            {t('Generate Website With AI')}
                                        </Button>
                                        <Button type="button" variant="secondary" size="sm" className="w-full gap-2" onClick={() => onDesignImportOpenChange(true)}>
                                            <ImagePlus className="h-3.5 w-3.5 shrink-0" />
                                            {t('Import Design')}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            className="w-full gap-2"
                                            onClick={() => onOpenRefineLayoutChange(true)}
                                            disabled={sectionsDraft.length === 0}
                                        >
                                            <Wand2 className="h-3.5 w-3.5 shrink-0" />
                                            {t('Refine layout')}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            className="w-full gap-2"
                                            onClick={() => onAiImproveSiteOpenChange(true)}
                                            disabled={sectionsDraft.length === 0}
                                        >
                                            <Sparkles className="h-3.5 w-3.5 shrink-0" />
                                            {t('AI Improve Site')}
                                        </Button>
                                        <div className="flex items-center justify-between gap-2 rounded-lg border px-3 py-2">
                                            <Label htmlFor="auto-improve-website" className="cursor-pointer text-xs font-medium">
                                                {t('Auto Improve Website')}
                                            </Label>
                                            <Switch id="auto-improve-website" checked={autoImproveEnabled} onCheckedChange={onSetAutoImproveEnabled} />
                                        </div>
                                        <p className="text-[11px] text-muted-foreground">
                                            {t('Continuously analyzes layout and suggests improvements.')}
                                        </p>
                                        <div className="grid grid-cols-3 gap-1.5">
                                            <Button type="button" size="sm" variant={builderSidebarMode === 'elements' ? 'default' : 'outline'} onClick={onOpenElementsSidebar} title={t('Elements')}>
                                                {t('Elements')}
                                            </Button>
                                            <Button type="button" size="sm" variant={builderSidebarMode === 'settings' ? 'default' : 'outline'} onClick={onOpenSettingsSidebar} title={t('Settings')}>
                                                {t('Settings')}
                                            </Button>
                                            <Button type="button" size="sm" variant={builderSidebarMode === 'design-system' ? 'default' : 'outline'} onClick={onOpenDesignSystemSidebar} title={t('Design System')}>
                                                {t('Design')}
                                            </Button>
                                        </div>
                                        {bindingWarningsSummaryContent}

                                        {builderSidebarMode === 'elements' ? (
                                            <div className="h-full min-h-0 flex flex-col gap-3">
                                                <div className="space-y-2">
                                                    <Input
                                                        value={sectionSearch}
                                                        onChange={(event) => onSectionSearchChange(event.target.value)}
                                                        placeholder={t('Search Widget...')}
                                                        className="text-xs"
                                                    />
                                                </div>

                                                <div className="rounded-lg border p-2 min-h-0 flex-1 flex flex-col gap-1">
                                                    <div className="min-h-0 flex-1 overflow-auto space-y-1 pr-1">
                                                        {groupedSectionLibrary.length === 0 ? (
                                                            <p className="text-xs text-muted-foreground">{t('Nothing found')}</p>
                                                        ) : (
                                                            groupedSectionLibrary.map((group, groupIndex) => (
                                                                <Collapsible
                                                                    key={group.category}
                                                                    open={expandedComponentCategories[group.category] ?? groupIndex === 0}
                                                                    onOpenChange={(open) => onExpandedComponentCategoryChange(group.category, open)}
                                                                    className="rounded border border-transparent"
                                                                >
                                                                    <CollapsibleTrigger className="flex w-full items-center gap-1.5 py-1.5 text-left hover:opacity-80">
                                                                        <ChevronRight className="h-3.5 w-3.5 shrink-0 text-muted-foreground data-[state=open]:rotate-90 transition-transform" />
                                                                        <span className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{group.category}</span>
                                                                    </CollapsibleTrigger>
                                                                    <CollapsibleContent>
                                                                        <div className="grid grid-cols-2 gap-2 pt-1 pb-2 pl-4">
                                                                            {group.items.map((item) => (
                                                                                <DraggableLibraryIconTile
                                                                                    key={item.id}
                                                                                    item={item}
                                                                                    displayLabel={getLibraryItemDisplayLabel(item)}
                                                                                    onAdd={onAddSectionByKey}
                                                                                />
                                                                            ))}
                                                                        </div>
                                                                    </CollapsibleContent>
                                                                </Collapsible>
                                                            ))
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        ) : null}

                                        {builderSidebarMode === 'settings' ? standaloneSettingsContent : null}
                                        {builderSidebarMode === 'design-system' ? standaloneDesignSystemContent : null}
                                    </>
                                )}
                            </div>
                        </aside>
                    ) : null}

                    {!isEmbeddedSidebarMode ? (
                        <section ref={builderViewportRef} className="relative flex-1 min-w-0 flex flex-col">
                            {!isEmbeddedMode ? (
                                <div className="h-14 border-b bg-background px-3 flex items-center justify-between gap-2">
                                    <div className="flex items-center gap-2 min-w-0">
                                        <Badge variant={isHomePageSelected ? 'default' : 'secondary'}>
                                            {isHomePageSelected ? t('Main Page') : t('Page')}
                                        </Badge>
                                        <p className="text-sm text-muted-foreground truncate">/{selectedPageSlug ?? 'home'}</p>
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant={builderPreviewMode === 'desktop' ? 'default' : 'outline'}
                                            className="h-8 w-8"
                                            onClick={() => onBuilderPreviewModeChange('desktop')}
                                            aria-label={t('Desktop')}
                                        >
                                            <Monitor className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant={builderPreviewMode === 'tablet' ? 'default' : 'outline'}
                                            className="h-8 w-8"
                                            onClick={() => onBuilderPreviewModeChange('tablet')}
                                            aria-label={t('Tablet')}
                                        >
                                            <Tablet className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant={builderPreviewMode === 'mobile' ? 'default' : 'outline'}
                                            className="h-8 w-8"
                                            onClick={() => onBuilderPreviewModeChange('mobile')}
                                            aria-label={t('Mobile')}
                                        >
                                            <Smartphone className="h-4 w-4" />
                                        </Button>
                                        <Button type="button" size="icon" variant="outline" className="h-8 w-8" onClick={onRefreshPreview} aria-label={t('Refresh Preview')}>
                                            <RefreshCw className="h-4 w-4" />
                                        </Button>
                                        {isStructurePanelCollapsed ? (
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                className="h-8 w-8"
                                                onClick={() => onSetStructurePanelCollapsed(false)}
                                                aria-label={t('Open Structure')}
                                            >
                                                <Layers className="h-4 w-4" />
                                            </Button>
                                        ) : null}
                                        <Badge variant="outline">{t('Language')}: {activeContentLocale.toUpperCase()}</Badge>
                                        {bindingValidationWarningsCount > 0 ? (
                                            <Badge variant="outline" className="border-amber-500 text-amber-700">
                                                {t('Binding Warnings')}: {bindingValidationWarningsCount}
                                            </Badge>
                                        ) : null}
                                        {builderVisualPreviewUrl ? (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                type="button"
                                                onClick={() => void onOpenDraftSyncedPreview(builderVisualPreviewUrl)}
                                                disabled={isSavingRevision}
                                            >
                                                <ExternalLink className="h-4 w-4 mr-1.5" />
                                                {t('Open')}
                                            </Button>
                                        ) : null}
                                        <Button type="button" variant="outline" size="icon" className="h-8 w-8" onClick={onUndo} disabled={!canUndo} aria-label={t('Undo')} title={t('Undo')}>
                                            <Undo2 className="h-4 w-4" />
                                        </Button>
                                        <Button type="button" variant="outline" size="icon" className="h-8 w-8" onClick={onRedo} disabled={!canRedo} aria-label={t('Redo')} title={t('Redo')}>
                                            <Redo2 className="h-4 w-4" />
                                        </Button>
                                        <Button variant="outline" onClick={() => void onSaveDraftRevision()} disabled={isSavingRevision}>
                                            {isSavingRevision ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <Save className="h-4 w-4 mr-2" />}
                                            {t('Save')}
                                        </Button>
                                        <Button onClick={() => void onPublishPage()} disabled={isPublishingPage}>
                                            {isPublishingPage ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <CheckCircle2 className="h-4 w-4 mr-2" />}
                                            {t('Publish')}
                                        </Button>
                                    </div>
                                </div>
                            ) : null}

                            {!isEmbeddedMode ? (
                                <Suspense fallback={lazyPanelFallback}>
                                    <StructurePanel
                                        collapsed={isStructurePanelCollapsed}
                                        onCollapse={() => onSetStructurePanelCollapsed(true)}
                                        position={structurePanelPosition}
                                        onPositionChange={onSetStructurePanelPosition}
                                        viewportRef={builderViewportRef}
                                        scrollRef={builderLayersScrollRef}
                                        sectionCount={sectionsDraft.length}
                                        onPaste={onPasteSection}
                                        onOptimizeLayout={onOptimizeLayout}
                                        t={t}
                                    >
                                        <BuilderCanvasDropZone>
                                            {sectionsDraft.length === 0 ? (
                                                <div className="rounded-md border border-dashed p-4 text-center text-xs text-muted-foreground">
                                                    {t('Drop widgets here')}
                                                </div>
                                            ) : sectionsDraft.length > BUILDER_LAYERS_VIRTUALIZE_THRESHOLD ? (
                                                <SortableContext items={sectionsDraft.map((section) => section.localId)} strategy={verticalListSortingStrategy}>
                                                    <div className="relative w-full" style={{ height: `${layersVirtualizer.getTotalSize()}px` }}>
                                                        {layersVirtualizer.getVirtualItems().map((virtualRow) => {
                                                            const section = sectionsDraft[virtualRow.index];
                                                            const index = virtualRow.index;
                                                            const { isLayoutPrimitive, label, nestedSections, previewText } = resolveSectionCardDisplayState(section);

                                                            return (
                                                                <div
                                                                    key={virtualRow.key}
                                                                    className="absolute left-0 top-0 w-full px-0.5"
                                                                    style={{
                                                                        height: `${virtualRow.size}px`,
                                                                        transform: `translateY(${virtualRow.start}px)`,
                                                                    }}
                                                                >
                                                                    <SortableCanvasSectionCard
                                                                        section={section}
                                                                        index={index}
                                                                        isSelected={selectedSectionLocalId === section.localId}
                                                                        label={label}
                                                                        previewText={previewText}
                                                                        canMoveUp={index > 0}
                                                                        canMoveDown={index < sectionsDraft.length - 1}
                                                                        onSelect={() => onFocusSection(section.localId)}
                                                                        onMoveUp={() => onMoveSection(section.localId, 'up')}
                                                                        onMoveDown={() => onMoveSection(section.localId, 'down')}
                                                                        onDuplicate={() => onDuplicateSection(section.localId)}
                                                                        onRemove={() => onRemoveSection(section.localId)}
                                                                        onAddSectionInside={onAddSectionInside}
                                                                        onAddSectionInsideAtPath={onAddSectionInsideAtPath}
                                                                        addInsideSectionOptions={addInsideSectionOptions}
                                                                        layoutPrimitiveKeys={layoutPrimitiveSectionKeys}
                                                                        showAddInside={isLayoutPrimitive}
                                                                        nestedSections={nestedSections}
                                                                        onRemoveNestedSection={onRemoveNestedSection}
                                                                        onMoveNestedSection={onMoveNestedSection}
                                                                        onSelectNestedSection={onSelectNestedSection}
                                                                        selectedNestedPath={selectedNestedPath}
                                                                        selectedNestedParentLocalId={selectedNestedParentLocalId}
                                                                        t={t}
                                                                    />
                                                                </div>
                                                            );
                                                        })}
                                                    </div>
                                                </SortableContext>
                                            ) : (
                                                <SortableContext items={sectionsDraft.map((section) => section.localId)} strategy={verticalListSortingStrategy}>
                                                    <div className="space-y-2">
                                                        {sectionsDraft.map((section, index) => {
                                                            const { isLayoutPrimitive, label, nestedSections, previewText } = resolveSectionCardDisplayState(section);

                                                            return (
                                                                <SortableCanvasSectionCard
                                                                    key={section.localId}
                                                                    section={section}
                                                                    index={index}
                                                                    isSelected={selectedSectionLocalId === section.localId}
                                                                    label={label}
                                                                    previewText={previewText}
                                                                    canMoveUp={index > 0}
                                                                    canMoveDown={index < sectionsDraft.length - 1}
                                                                    onSelect={() => onFocusSection(section.localId)}
                                                                    onMoveUp={() => onMoveSection(section.localId, 'up')}
                                                                    onMoveDown={() => onMoveSection(section.localId, 'down')}
                                                                    onDuplicate={() => onDuplicateSection(section.localId)}
                                                                    onRemove={() => onRemoveSection(section.localId)}
                                                                    onAddSectionInside={onAddSectionInside}
                                                                    onAddSectionInsideAtPath={onAddSectionInsideAtPath}
                                                                    addInsideSectionOptions={addInsideSectionOptions}
                                                                    layoutPrimitiveKeys={layoutPrimitiveSectionKeys}
                                                                    showAddInside={isLayoutPrimitive}
                                                                    nestedSections={nestedSections}
                                                                    onRemoveNestedSection={onRemoveNestedSection}
                                                                    onMoveNestedSection={onMoveNestedSection}
                                                                    onSelectNestedSection={onSelectNestedSection}
                                                                    selectedNestedPath={selectedNestedPath}
                                                                    selectedNestedParentLocalId={selectedNestedParentLocalId}
                                                                    t={t}
                                                                />
                                                            );
                                                        })}
                                                    </div>
                                                </SortableContext>
                                            )}
                                        </BuilderCanvasDropZone>
                                    </StructurePanel>
                                </Suspense>
                            ) : null}

                            <div className="flex-1 overflow-auto bg-muted/30 p-3">
                                <div className={cn(builderCanvasViewportClass, 'min-h-[calc(100vh-92px)] rounded-lg border bg-white overflow-hidden shadow-sm')}>
                                    <Suspense fallback={lazyPanelFallback}>
                                        <BuilderCanvas
                                            sections={sectionsDraft}
                                            selectedElementId={selectedSectionLocalId}
                                            hoveredElementId={hoveredBuilderTarget?.sectionLocalId ?? builderHoveredElementId}
                                            selectedTarget={selectedBuilderTarget}
                                            hoveredTarget={hoveredBuilderTarget}
                                            draggingComponentType={extractLibrarySectionKey(activeDragId ?? '')}
                                            currentDropTarget={builderCurrentDropTarget}
                                            sectionDisplayLabelByKey={sectionDisplayLabelByKey}
                                            onSelect={onCanvasSelect}
                                            onHover={onCanvasHover}
                                            onSelectTarget={onCanvasSelectTarget}
                                            onHoverTarget={onCanvasHoverTarget}
                                            onDeselect={onCanvasDeselect}
                                            onEditSection={onCanvasEditSection}
                                            onDeleteSection={onRemoveSection}
                                            t={(key) => t(key)}
                                        />
                                    </Suspense>
                                </div>
                            </div>
                        </section>
                    ) : null}
                </div>

                <DragOverlay dropAnimation={null}>
                    {activeDragLabel ? (
                        <div className="w-[240px] rounded-xl border bg-background/95 px-3 py-2 shadow-xl backdrop-blur pointer-events-none">
                            <div className="flex items-center gap-2">
                                <div className="inline-flex h-8 w-8 items-center justify-center rounded-md border bg-muted/20">
                                    <ActiveDragIcon className="h-4 w-4" />
                                </div>
                                <div className="min-w-0">
                                    <p className="text-[10px] uppercase tracking-wide text-muted-foreground">{t('Dragging')}</p>
                                    <p className="text-sm font-medium truncate">{activeDragLabel}</p>
                                </div>
                            </div>
                            {activeDragLibraryItem?.description ? (
                                <p className="mt-1.5 text-xs text-muted-foreground line-clamp-2">
                                    {activeDragLibraryItem.description}
                                </p>
                            ) : null}
                        </div>
                    ) : null}
                </DragOverlay>
            </DndContext>
        </div>
    );
}
