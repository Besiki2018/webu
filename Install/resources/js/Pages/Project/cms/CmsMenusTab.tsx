import { type ComponentProps, useState } from 'react';
import { closestCenter, DndContext, DragOverlay } from '@dnd-kit/core';
import { SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { ArrowDown, ArrowUp, GripVertical, Loader2, Plus, RefreshCw, Save, Trash2 } from 'lucide-react';

import { useTranslation } from '@/contexts/LanguageContext';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface MenuItem {
    id: string;
    label: string;
    url: string;
    slug: string;
    parent_id: string;
    source: 'custom' | 'page';
    page_id: string;
}

interface MenuResponse {
    id?: number;
    site_id: string;
    locale?: string;
    key: string;
    items_json: unknown;
    updated_at: string | null;
    is_system?: boolean;
}

interface PageRevisionMeta {
    id: number;
    version: number;
    created_at?: string | null;
    published_at?: string | null;
}

interface PageSummary {
    id: number;
    title: string;
    slug: string;
    status: string;
    seo_title: string | null;
    seo_description: string | null;
    latest_revision: PageRevisionMeta | null;
    published_revision: PageRevisionMeta | null;
    created_at: string | null;
    updated_at: string | null;
}

interface MenuLinkDraft {
    label: string;
    url: string;
}

type MenuSensors = NonNullable<ComponentProps<typeof DndContext>['sensors']>;
type MenuDragStartHandler = ComponentProps<typeof DndContext>['onDragStart'];
type MenuDragEndHandler = ComponentProps<typeof DndContext>['onDragEnd'];
type MenuDragCancelHandler = ComponentProps<typeof DndContext>['onDragCancel'];

interface CmsMenusTabProps {
    activeMenuDepthMap: Record<string, number>;
    activeMenuDragItem: MenuItem | null;
    activeMenuItemIds: string[];
    activeMenuItems: MenuItem[];
    activeMenuKey: string;
    activeMenuMeta: MenuResponse | null;
    deletingMenuKey: string | null;
    isCreatingMenu: boolean;
    isDesignMenusSection: boolean;
    isMenuListLoading: boolean;
    isSavingMenu: boolean;
    menuBuilderSensors: MenuSensors;
    menuCustomLinkDraft: MenuLinkDraft;
    menuDrafts: Record<string, MenuItem[]>;
    menuLoading: Record<string, boolean>;
    menuUpdatedAt: Record<string, string | null>;
    menus: MenuResponse[];
    newMenuName: string;
    onActiveMenuKeyChange: (key: string) => void;
    onAddCustomMenuLink: (menuKey: string) => void;
    onAddMenuItem: (menuKey: string) => void;
    onAddPageMenuItem: (menuKey: string, pageItem: PageSummary) => void;
    onAddSelectedPagesToMenu: (menuKey: string) => void;
    onCreateMenu: () => void | Promise<void>;
    onDeleteMenu: (menuKey: string) => void | Promise<void>;
    onMenuCustomLinkDraftChange: (field: keyof MenuLinkDraft, value: string) => void;
    onMenuDragCancel: MenuDragCancelHandler;
    onMenuDragEnd: MenuDragEndHandler;
    onMenuDragStart: MenuDragStartHandler;
    onMenuItemChange: (menuKey: string, itemId: string, field: 'label' | 'url' | 'slug', value: string) => void;
    onMenuItemIndent: (menuKey: string, itemId: string, direction: 'in' | 'out') => void;
    onMenuItemRemove: (menuKey: string, itemId: string) => void;
    onMenuNameChange: (value: string) => void;
    onReloadMenu: (menuKey: string) => void | Promise<void>;
    onReloadMenus: () => void | Promise<void>;
    onSaveMenu: () => void | Promise<void>;
    onSelectedMenuPageIdsChange: (next: number[]) => void;
    onToggleSelectedMenuPage: (pageId: number, checked: boolean) => void;
    pages: PageSummary[];
    selectedMenuPageIds: number[];
}

const SYSTEM_MENU_KEYS = ['header', 'footer'] as const;

function humanizeMenuKey(key: string): string {
    return key
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString();
}

interface SortableMenuItemRowProps {
    item: MenuItem;
    depth: number;
    onChange: (itemId: string, field: 'label' | 'url' | 'slug', value: string) => void;
    onIndent: (itemId: string) => void;
    onOutdent: (itemId: string) => void;
    onRemove: (itemId: string) => void;
    t: (key: string, params?: Record<string, string>) => string;
    variant?: 'default' | 'wordpress';
}

function SortableMenuItemRow({
    item,
    depth,
    onChange,
    onIndent,
    onOutdent,
    onRemove,
    t,
    variant = 'default',
}: SortableMenuItemRowProps) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: item.id,
    });
    const [isExpanded, setIsExpanded] = useState(item.label.trim() === '' || item.url.trim() === '');
    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.55 : 1,
        marginInlineStart: `${Math.max(0, Math.min(3, depth)) * 24}px`,
    };

    if (variant === 'wordpress') {
        const itemLabel = item.label.trim() !== '' ? item.label : t('Untitled item');
        const itemUrl = item.url.trim() !== '' ? item.url : t('No URL set');

        return (
            <div
                ref={setNodeRef}
                style={style}
                className={`rounded-md border bg-background shadow-sm overflow-hidden ${isDragging ? 'ring-2 ring-primary/20' : ''}`}
            >
                <div className="flex items-stretch">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-auto w-10 rounded-none border-e cursor-grab active:cursor-grabbing text-muted-foreground"
                        aria-label={t('Drag menu item')}
                        {...attributes}
                        {...listeners}
                    >
                        <GripVertical className="h-4 w-4" />
                    </Button>
                    <button
                        type="button"
                        className="flex-1 min-w-0 px-3 py-2.5 text-left hover:bg-muted/30 transition-colors"
                        onClick={() => setIsExpanded((prev) => !prev)}
                    >
                        <div className="flex flex-wrap items-center gap-1.5">
                            <span className="text-sm font-medium truncate max-w-full">{itemLabel}</span>
                            <Badge variant="outline" className="text-[10px]">
                                {item.source === 'page' ? t('Page') : t('Custom')}
                            </Badge>
                            {depth > 0 ? (
                                <Badge variant="secondary" className="text-[10px]">
                                    {t('Sub item')}
                                </Badge>
                            ) : null}
                        </div>
                        <p className="text-xs text-muted-foreground truncate mt-0.5">
                            {itemUrl}
                        </p>
                    </button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-auto w-10 rounded-none border-s"
                        onClick={() => setIsExpanded((prev) => !prev)}
                        aria-label={isExpanded ? t('Collapse') : t('Expand')}
                    >
                        {isExpanded ? <ArrowUp className="h-4 w-4" /> : <ArrowDown className="h-4 w-4" />}
                    </Button>
                </div>

                {isExpanded ? (
                    <div className="border-t bg-muted/20 p-3 space-y-3">
                        <div className="grid gap-3 md:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label className="text-xs">{t('Navigation Label')}</Label>
                                <Input
                                    value={item.label}
                                    onChange={(event) => onChange(item.id, 'label', event.target.value)}
                                    placeholder={t('Home')}
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-xs">{t('URL')}</Label>
                                <Input
                                    value={item.url}
                                    onChange={(event) => onChange(item.id, 'url', event.target.value)}
                                    placeholder="/contact"
                                />
                            </div>
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">{t('Slug (optional)')}</Label>
                            <Input
                                value={item.slug}
                                onChange={(event) => onChange(item.id, 'slug', event.target.value)}
                                placeholder="contact"
                            />
                        </div>
                        <div className="flex flex-wrap items-center gap-2 pt-1">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-8"
                                onClick={() => onOutdent(item.id)}
                            >
                                {t('Move left')}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-8"
                                onClick={() => onIndent(item.id)}
                            >
                                {t('Move right')}
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-8 text-destructive ms-auto"
                                onClick={() => onRemove(item.id)}
                            >
                                {t('Remove')}
                            </Button>
                        </div>
                    </div>
                ) : null}
            </div>
        );
    }

    return (
        <div
            ref={setNodeRef}
            style={style}
            className="rounded-lg border bg-background p-3 grid gap-2 md:grid-cols-[auto_1fr_1fr_1fr_auto]"
        >
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="h-9 w-9 cursor-grab active:cursor-grabbing mt-0.5"
                {...attributes}
                {...listeners}
            >
                <GripVertical className="h-4 w-4" />
            </Button>
            <div className="space-y-1">
                <Input
                    value={item.label}
                    onChange={(event) => onChange(item.id, 'label', event.target.value)}
                    placeholder={t('Label')}
                />
                <div className="flex items-center gap-1">
                    <Badge variant="outline" className="text-[10px]">
                        {item.source === 'page' ? t('Page') : t('Custom')}
                    </Badge>
                    {item.source === 'page' && item.slug.trim() !== '' ? (
                        <span className="text-[10px] text-muted-foreground truncate">
                            /{item.slug}
                        </span>
                    ) : null}
                </div>
            </div>
            <Input
                value={item.url}
                onChange={(event) => onChange(item.id, 'url', event.target.value)}
                placeholder="/contact"
            />
            <Input
                value={item.slug}
                onChange={(event) => onChange(item.id, 'slug', event.target.value)}
                placeholder="contact"
            />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="h-9 w-9 text-destructive"
                onClick={() => onRemove(item.id)}
            >
                <Trash2 className="h-4 w-4" />
            </Button>
            <div className="md:col-span-5 -mt-1 flex items-center justify-end gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-7 px-2 text-xs"
                    onClick={() => onOutdent(item.id)}
                >
                    {t('Outdent')}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-7 px-2 text-xs"
                    onClick={() => onIndent(item.id)}
                >
                    {t('Indent')}
                </Button>
            </div>
        </div>
    );
}

function MenuDragItemOverlay({ itemLabel, subtitle }: { itemLabel: string; subtitle: string }) {
    return (
        <div className="rounded-lg border bg-background/95 px-3 py-2 shadow-xl backdrop-blur w-[280px]">
            <p className="text-xs text-muted-foreground">{subtitle}</p>
            <p className="text-sm font-medium truncate">{itemLabel}</p>
        </div>
    );
}

export function CmsMenusTab({
    activeMenuDepthMap,
    activeMenuDragItem,
    activeMenuItemIds,
    activeMenuItems,
    activeMenuKey,
    activeMenuMeta,
    deletingMenuKey,
    isCreatingMenu,
    isDesignMenusSection,
    isMenuListLoading,
    isSavingMenu,
    menuBuilderSensors,
    menuCustomLinkDraft,
    menuDrafts,
    menuLoading,
    menuUpdatedAt,
    menus,
    newMenuName,
    onActiveMenuKeyChange,
    onAddCustomMenuLink,
    onAddMenuItem,
    onAddPageMenuItem,
    onAddSelectedPagesToMenu,
    onCreateMenu,
    onDeleteMenu,
    onMenuCustomLinkDraftChange,
    onMenuDragCancel,
    onMenuDragEnd,
    onMenuDragStart,
    onMenuItemChange,
    onMenuItemIndent,
    onMenuItemRemove,
    onMenuNameChange,
    onReloadMenu,
    onReloadMenus,
    onSaveMenu,
    onSelectedMenuPageIdsChange,
    onToggleSelectedMenuPage,
    pages,
    selectedMenuPageIds,
}: CmsMenusTabProps) {
    const { t } = useTranslation();

    if (isDesignMenusSection) {
        return (
            <div className="grid gap-4 xl:grid-cols-[340px_minmax(0,1fr)] items-start">
                <div className="space-y-4 xl:sticky xl:top-20">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle>{t('Menus')}</CardTitle>
                            <CardDescription>{t('Select a menu and build its structure like WordPress menu editor.')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="space-y-2">
                                <Label className="text-xs">{t('Create New Menu')}</Label>
                                <div className="flex items-center gap-2">
                                    <Input
                                        value={newMenuName}
                                        onChange={(event) => onMenuNameChange(event.target.value)}
                                        placeholder={t('Main Menu')}
                                    />
                                    <Button type="button" size="sm" onClick={() => void onCreateMenu()} disabled={isCreatingMenu}>
                                        {isCreatingMenu ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
                                    </Button>
                                </div>
                            </div>
                            <div className="flex items-center justify-between">
                                <Label className="text-xs">{t('Available Menus')}</Label>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 px-2"
                                    onClick={() => void onReloadMenus()}
                                    disabled={isMenuListLoading}
                                >
                                    {isMenuListLoading ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <RefreshCw className="h-3.5 w-3.5" />}
                                </Button>
                            </div>
                            <div className="space-y-1.5 max-h-56 overflow-y-auto pe-1">
                                {menus.length === 0 ? (
                                    <p className="text-xs text-muted-foreground">{t('No menus yet')}</p>
                                ) : (
                                    menus.map((menu) => {
                                        const isActive = activeMenuKey === menu.key;
                                        const isSystem = Boolean(menu.is_system) || SYSTEM_MENU_KEYS.includes(menu.key as typeof SYSTEM_MENU_KEYS[number]);
                                        const itemCount = (menuDrafts[menu.key] ?? []).length;

                                        return (
                                            <div
                                                key={`menu-list-${menu.key}`}
                                                className={`rounded-md border px-2.5 py-2 flex items-center gap-2 ${isActive ? 'border-primary bg-primary/5' : 'bg-background'}`}
                                            >
                                                <button
                                                    type="button"
                                                    onClick={() => onActiveMenuKeyChange(menu.key)}
                                                    className="flex-1 min-w-0 text-left"
                                                >
                                                    <p className="text-sm font-medium truncate">{humanizeMenuKey(menu.key)}</p>
                                                    <p className="text-[11px] text-muted-foreground truncate">
                                                        {itemCount} {t('items')}
                                                    </p>
                                                </button>
                                                {isSystem ? <Badge variant="outline">{t('System')}</Badge> : null}
                                                {!isSystem ? (
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        className="h-7 w-7 text-destructive"
                                                        disabled={deletingMenuKey === menu.key}
                                                        onClick={() => void onDeleteMenu(menu.key)}
                                                    >
                                                        {deletingMenuKey === menu.key ? (
                                                            <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                                        ) : (
                                                            <Trash2 className="h-3.5 w-3.5" />
                                                        )}
                                                    </Button>
                                                ) : null}
                                            </div>
                                        );
                                    })
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">{t('Custom Links')}</CardTitle>
                            <CardDescription>{t('Add custom URLs to the selected menu')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="space-y-1.5">
                                <Label className="text-xs">{t('URL')}</Label>
                                <Input
                                    value={menuCustomLinkDraft.url}
                                    onChange={(event) => onMenuCustomLinkDraftChange('url', event.target.value)}
                                    placeholder="https://example.com or /contact"
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-xs">{t('Link Text')}</Label>
                                <Input
                                    value={menuCustomLinkDraft.label}
                                    onChange={(event) => onMenuCustomLinkDraftChange('label', event.target.value)}
                                    placeholder={t('Contact')}
                                />
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="w-full"
                                disabled={!activeMenuMeta}
                                onClick={() => onAddCustomMenuLink(activeMenuKey)}
                            >
                                <Plus className="h-4 w-4 mr-2" />
                                {t('Add to Menu')}
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">{t('Pages')}</CardTitle>
                            <CardDescription>{t('Select pages and add them to the current menu')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex items-center justify-between text-xs text-muted-foreground">
                                <span>{t('Selected')}: {selectedMenuPageIds.length}</span>
                                <div className="flex items-center gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 px-2"
                                        onClick={() => onSelectedMenuPageIdsChange(pages.map((pageItem) => pageItem.id))}
                                        disabled={pages.length === 0}
                                    >
                                        {t('Select All')}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 px-2"
                                        onClick={() => onSelectedMenuPageIdsChange([])}
                                        disabled={selectedMenuPageIds.length === 0}
                                    >
                                        {t('Clear')}
                                    </Button>
                                </div>
                            </div>
                            <div className="max-h-72 overflow-y-auto space-y-1.5 pe-1">
                                {pages.length === 0 ? (
                                    <p className="text-xs text-muted-foreground">{t('No pages found')}</p>
                                ) : (
                                    pages.map((pageItem) => {
                                        const pageCheckboxId = `design-menu-page-${pageItem.id}`;
                                        const isChecked = selectedMenuPageIds.includes(pageItem.id);

                                        return (
                                            <label
                                                key={`menu-page-checkbox-${pageItem.id}`}
                                                htmlFor={pageCheckboxId}
                                                className="flex items-start gap-2 rounded-md border bg-background px-2.5 py-2 cursor-pointer hover:bg-muted/30"
                                            >
                                                <Checkbox
                                                    id={pageCheckboxId}
                                                    checked={isChecked}
                                                    onCheckedChange={(checked) => onToggleSelectedMenuPage(pageItem.id, checked === true)}
                                                    className="mt-0.5"
                                                />
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-xs font-medium truncate">{pageItem.title}</p>
                                                    <p className="text-[11px] text-muted-foreground truncate">/{pageItem.slug}</p>
                                                </div>
                                            </label>
                                        );
                                    })
                                )}
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                className="w-full"
                                disabled={!activeMenuMeta || selectedMenuPageIds.length === 0}
                                onClick={() => onAddSelectedPagesToMenu(activeMenuKey)}
                            >
                                <Plus className="h-4 w-4 mr-2" />
                                {t('Add to Menu')}
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <Card className="min-w-0">
                    <CardHeader>
                        <CardTitle>
                            {activeMenuMeta
                                ? `${t('Menu Structure')}: ${humanizeMenuKey(activeMenuMeta.key)}`
                                : t('Select a menu')}
                        </CardTitle>
                        <CardDescription>
                            {activeMenuMeta
                                ? t('Drag items to reorder. Drag right for submenu, drag left to move up a level.')
                                : t('Choose a menu from the left panel')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {!activeMenuMeta ? (
                            <div className="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">
                                {t('No menu selected')}
                            </div>
                        ) : (
                            <>
                                <div className="flex flex-wrap items-center gap-2 rounded-md border bg-muted/20 p-2">
                                    <Button type="button" variant="outline" size="sm" onClick={() => onAddMenuItem(activeMenuMeta.key)}>
                                        <Plus className="h-4 w-4 mr-2" />
                                        {t('Add Empty Item')}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => void onReloadMenu(activeMenuMeta.key)}
                                        disabled={menuLoading[activeMenuMeta.key]}
                                    >
                                        {menuLoading[activeMenuMeta.key] ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <RefreshCw className="h-4 w-4 mr-2" />}
                                        {t('Reload')}
                                    </Button>
                                    <Button type="button" size="sm" onClick={() => void onSaveMenu()} disabled={isSavingMenu}>
                                        {isSavingMenu ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <Save className="h-4 w-4 mr-2" />}
                                        {t('Save Menu')}
                                    </Button>
                                    <p className="text-xs text-muted-foreground ms-auto">
                                        {t('Last update')}: {formatDate(menuUpdatedAt[activeMenuMeta.key] ?? null)}
                                    </p>
                                </div>

                                {activeMenuItems.length === 0 ? (
                                    <div className="rounded-md border border-dashed p-10 text-center text-sm text-muted-foreground">
                                        {t('Menu is empty. Add items from the left panel.')}
                                    </div>
                                ) : (
                                    <DndContext
                                        sensors={menuBuilderSensors}
                                        collisionDetection={closestCenter}
                                        onDragStart={onMenuDragStart}
                                        onDragEnd={onMenuDragEnd}
                                        onDragCancel={onMenuDragCancel}
                                    >
                                        <SortableContext items={activeMenuItemIds} strategy={verticalListSortingStrategy}>
                                            <div className="space-y-2">
                                                {activeMenuItems.map((item) => (
                                                    <SortableMenuItemRow
                                                        key={item.id}
                                                        item={item}
                                                        depth={activeMenuDepthMap[item.id] ?? 0}
                                                        onChange={(itemId, field, value) => onMenuItemChange(activeMenuMeta.key, itemId, field, value)}
                                                        onIndent={(itemId) => onMenuItemIndent(activeMenuMeta.key, itemId, 'in')}
                                                        onOutdent={(itemId) => onMenuItemIndent(activeMenuMeta.key, itemId, 'out')}
                                                        onRemove={(itemId) => onMenuItemRemove(activeMenuMeta.key, itemId)}
                                                        t={t}
                                                        variant="wordpress"
                                                    />
                                                ))}
                                            </div>
                                        </SortableContext>
                                        <DragOverlay>
                                            {activeMenuDragItem ? (
                                                <MenuDragItemOverlay
                                                    subtitle={t('Dragging menu item')}
                                                    itemLabel={activeMenuDragItem.label.trim() !== '' ? activeMenuDragItem.label : t('Untitled item')}
                                                />
                                            ) : null}
                                        </DragOverlay>
                                    </DndContext>
                                )}
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        );
    }

    return (
        <div className="grid gap-4 xl:grid-cols-[320px_minmax(0,1fr)]">
            <Card>
                <CardHeader>
                    <CardTitle>{t('Menu Constructor')}</CardTitle>
                    <CardDescription>{t('Create menus separately, then select them in Header/Footer settings.')}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    <div className="flex items-center gap-2">
                        <Input
                            value={newMenuName}
                            onChange={(event) => onMenuNameChange(event.target.value)}
                            placeholder={t('Menu name (e.g. Main Navigation)')}
                        />
                        <Button type="button" size="sm" onClick={() => void onCreateMenu()} disabled={isCreatingMenu}>
                            {isCreatingMenu ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
                        </Button>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="w-full"
                        onClick={() => void onReloadMenus()}
                        disabled={isMenuListLoading}
                    >
                        {isMenuListLoading ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <RefreshCw className="h-4 w-4 mr-2" />}
                        {t('Refresh Menus')}
                    </Button>
                    <div className="space-y-1.5">
                        {menus.length === 0 ? (
                            <p className="text-xs text-muted-foreground">{t('No menus yet')}</p>
                        ) : (
                            menus.map((menu) => {
                                const isActive = activeMenuKey === menu.key;
                                const isSystem = Boolean(menu.is_system) || SYSTEM_MENU_KEYS.includes(menu.key as typeof SYSTEM_MENU_KEYS[number]);
                                const itemCount = (menuDrafts[menu.key] ?? []).length;

                                return (
                                    <div
                                        key={`menu-list-${menu.key}`}
                                        className={`rounded-md border p-2 flex items-center gap-2 ${isActive ? 'border-primary bg-primary/5' : 'bg-background'}`}
                                    >
                                        <button
                                            type="button"
                                            onClick={() => onActiveMenuKeyChange(menu.key)}
                                            className="flex-1 min-w-0 text-left"
                                        >
                                            <p className="text-sm font-medium truncate">{humanizeMenuKey(menu.key)}</p>
                                            <p className="text-[11px] text-muted-foreground truncate">
                                                {menu.key} · {itemCount} {t('items')}
                                            </p>
                                        </button>
                                        {isSystem ? <Badge variant="outline">{t('System')}</Badge> : null}
                                        {!isSystem ? (
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                className="h-7 w-7 text-destructive"
                                                disabled={deletingMenuKey === menu.key}
                                                onClick={() => void onDeleteMenu(menu.key)}
                                            >
                                                {deletingMenuKey === menu.key ? (
                                                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                                ) : (
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                )}
                                            </Button>
                                        ) : null}
                                    </div>
                                );
                            })
                        )}
                    </div>
                    <div className="border-t pt-3 space-y-2">
                        <p className="text-xs font-medium text-muted-foreground">{t('Pages')}</p>
                        <p className="text-[11px] text-muted-foreground">{t('Click + to add a page to selected menu')}</p>
                        <div className="max-h-64 overflow-y-auto space-y-1.5 pe-1">
                            {pages.length === 0 ? (
                                <p className="text-xs text-muted-foreground">{t('No pages found')}</p>
                            ) : (
                                pages.map((pageItem) => (
                                    <div
                                        key={`menu-page-add-${pageItem.id}`}
                                        className="rounded-md border bg-background px-2 py-1.5 flex items-center gap-2"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="text-xs font-medium truncate">{pageItem.title}</p>
                                            <p className="text-[11px] text-muted-foreground truncate">/{pageItem.slug}</p>
                                        </div>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            className="h-7 w-7"
                                            disabled={!activeMenuMeta}
                                            onClick={() => onAddPageMenuItem(activeMenuKey, pageItem)}
                                        >
                                            <Plus className="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>
                        {activeMenuMeta ? `${t('Edit Menu')}: ${humanizeMenuKey(activeMenuMeta.key)}` : t('Select a menu')}
                    </CardTitle>
                    <CardDescription>
                        {activeMenuMeta ? `${t('Last update')}: ${formatDate(menuUpdatedAt[activeMenuMeta.key] ?? null)}` : t('Choose a menu from the left panel')}
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    {!activeMenuMeta ? (
                        <p className="text-sm text-muted-foreground">{t('No menu selected')}</p>
                    ) : (
                        <>
                            <div className="flex flex-wrap items-center gap-2">
                                <Button type="button" variant="outline" size="sm" onClick={() => onAddMenuItem(activeMenuMeta.key)}>
                                    <Plus className="h-4 w-4 mr-2" />
                                    {t('Add Item')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => void onReloadMenu(activeMenuMeta.key)}
                                    disabled={menuLoading[activeMenuMeta.key]}
                                >
                                    {menuLoading[activeMenuMeta.key] ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <RefreshCw className="h-4 w-4 mr-2" />}
                                    {t('Reload')}
                                </Button>
                                <Button type="button" size="sm" onClick={() => void onSaveMenu()} disabled={isSavingMenu}>
                                    {isSavingMenu ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <Save className="h-4 w-4 mr-2" />}
                                    {t('Save Menu')}
                                </Button>
                                <p className="text-xs text-muted-foreground ms-auto">
                                    {t('Drag right = submenu, drag left = move level up')}
                                </p>
                            </div>

                            {activeMenuItems.length === 0 ? (
                                <div className="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">
                                    {t('No menu items yet. Add your first link.')}
                                </div>
                            ) : (
                                <DndContext
                                    sensors={menuBuilderSensors}
                                    collisionDetection={closestCenter}
                                    onDragStart={onMenuDragStart}
                                    onDragEnd={onMenuDragEnd}
                                    onDragCancel={onMenuDragCancel}
                                >
                                    <SortableContext items={activeMenuItemIds} strategy={verticalListSortingStrategy}>
                                        <div className="space-y-2">
                                            {activeMenuItems.map((item) => (
                                                <SortableMenuItemRow
                                                    key={item.id}
                                                    item={item}
                                                    depth={activeMenuDepthMap[item.id] ?? 0}
                                                    onChange={(itemId, field, value) => onMenuItemChange(activeMenuMeta.key, itemId, field, value)}
                                                    onIndent={(itemId) => onMenuItemIndent(activeMenuMeta.key, itemId, 'in')}
                                                    onOutdent={(itemId) => onMenuItemIndent(activeMenuMeta.key, itemId, 'out')}
                                                    onRemove={(itemId) => onMenuItemRemove(activeMenuMeta.key, itemId)}
                                                    t={t}
                                                />
                                            ))}
                                        </div>
                                    </SortableContext>
                                    <DragOverlay>
                                        {activeMenuDragItem ? (
                                            <MenuDragItemOverlay
                                                subtitle={t('Dragging menu item')}
                                                itemLabel={activeMenuDragItem.label.trim() !== '' ? activeMenuDragItem.label : t('Untitled item')}
                                            />
                                        ) : null}
                                    </DragOverlay>
                                </DndContext>
                            )}
                        </>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
