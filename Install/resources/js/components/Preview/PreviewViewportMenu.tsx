import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { ChevronDown, Monitor, Smartphone, Tablet } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useTranslation } from '@/contexts/LanguageContext';

export type PreviewViewport = 'desktop' | 'tablet' | 'mobile';

interface PreviewViewportMenuProps {
    value: PreviewViewport;
    onChange: (value: PreviewViewport) => void;
}

const PRESET_META = {
    desktop: {
        label: 'დესკტოპი',
        hint: 'სრული სიგანე',
        icon: Monitor,
    },
    tablet: {
        label: 'აიპადი',
        hint: '834px',
        icon: Tablet,
    },
    mobile: {
        label: 'ტელეფონი',
        hint: '390px',
        icon: Smartphone,
    },
} satisfies Record<PreviewViewport, { label: string; hint: string; icon: typeof Monitor }>;

const MENU_GAP = 10;
const MENU_WIDTH = 208;
const VIEWPORT_MARGIN = 12;
const ESTIMATED_MENU_HEIGHT = 220;

interface MenuPosition {
    top: number;
    left: number;
    placement: 'top' | 'bottom';
}

function getMenuPosition(rect: DOMRect, menuWidth: number, menuHeight: number): MenuPosition {
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const maxLeft = Math.max(VIEWPORT_MARGIN, viewportWidth - menuWidth - VIEWPORT_MARGIN);
    const left = Math.min(Math.max(rect.right - menuWidth, VIEWPORT_MARGIN), maxLeft);
    const spaceAbove = rect.top - VIEWPORT_MARGIN - MENU_GAP;
    const spaceBelow = viewportHeight - rect.bottom - VIEWPORT_MARGIN - MENU_GAP;
    const shouldOpenAbove = spaceBelow < menuHeight && spaceAbove > spaceBelow;
    const top = shouldOpenAbove
        ? Math.max(VIEWPORT_MARGIN, rect.top - menuHeight - MENU_GAP)
        : Math.min(viewportHeight - menuHeight - VIEWPORT_MARGIN, rect.bottom + MENU_GAP);

    return {
        top,
        left,
        placement: shouldOpenAbove ? 'top' : 'bottom',
    };
}

export function PreviewViewportMenu({ value, onChange }: PreviewViewportMenuProps) {
    const { t } = useTranslation();
    const [isOpen, setIsOpen] = useState(false);
    const menuRef = useRef<HTMLDivElement | null>(null);
    const triggerRef = useRef<HTMLButtonElement | null>(null);
    const popoverRef = useRef<HTMLDivElement | null>(null);
    const [menuPosition, setMenuPosition] = useState<MenuPosition | null>(null);

    const currentPreset = useMemo(() => PRESET_META[value], [value]);
    const CurrentIcon = currentPreset.icon;
    const tt = useCallback((key: string, fallback: string) => {
        const translated = t(key);
        return translated === key ? fallback : translated;
    }, [t]);

    const handleSelect = useCallback((nextValue: PreviewViewport) => {
        onChange(nextValue);
        setIsOpen(false);
    }, [onChange]);

    const updateMenuPosition = useCallback((estimatedHeight = ESTIMATED_MENU_HEIGHT) => {
        const trigger = triggerRef.current;

        if (!trigger) {
            return;
        }

        const rect = trigger.getBoundingClientRect();
        const measuredWidth = popoverRef.current?.offsetWidth ?? MENU_WIDTH;
        const measuredHeight = popoverRef.current?.offsetHeight ?? estimatedHeight;
        setMenuPosition(getMenuPosition(rect, measuredWidth, measuredHeight));
    }, []);

    const handleToggle = useCallback(() => {
        setIsOpen((prev) => {
            const nextOpen = !prev;

            if (nextOpen) {
                updateMenuPosition();
            }

            return nextOpen;
        });
    }, [updateMenuPosition]);

    useLayoutEffect(() => {
        if (!isOpen) {
            return;
        }

        updateMenuPosition();
    }, [isOpen, value, updateMenuPosition]);

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        const handlePointerDown = (event: PointerEvent) => {
            const target = event.target as Node;

            if (!menuRef.current?.contains(target) && !popoverRef.current?.contains(target)) {
                setIsOpen(false);
            }
        };

        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setIsOpen(false);
            }
        };

        const handleViewportChange = () => {
            updateMenuPosition();
        };

        document.addEventListener('pointerdown', handlePointerDown);
        document.addEventListener('keydown', handleKeyDown);
        window.addEventListener('resize', handleViewportChange);
        window.addEventListener('scroll', handleViewportChange, true);

        return () => {
            document.removeEventListener('pointerdown', handlePointerDown);
            document.removeEventListener('keydown', handleKeyDown);
            window.removeEventListener('resize', handleViewportChange);
            window.removeEventListener('scroll', handleViewportChange, true);
        };
    }, [isOpen, updateMenuPosition]);

    return (
        <div
            ref={menuRef}
            className={cn('workspace-viewport-menu', isOpen && 'workspace-viewport-menu--open')}
        >
            <button
                ref={triggerRef}
                type="button"
                className="workspace-viewport-trigger"
                onClick={handleToggle}
                aria-expanded={isOpen}
                aria-haspopup="menu"
                title={tt('Responsive preview sizes', 'პრევიუს ზომები')}
            >
                <CurrentIcon className="h-4 w-4" />
                <ChevronDown className="h-3.5 w-3.5" />
            </button>

            {isOpen && menuPosition && createPortal(
                <div
                    ref={popoverRef}
                    className={cn(
                        'workspace-viewport-popover',
                        'workspace-viewport-popover--floating',
                        menuPosition.placement === 'top'
                            ? 'workspace-viewport-popover--top'
                            : 'workspace-viewport-popover--bottom'
                    )}
                    role="menu"
                    style={{ top: menuPosition.top, left: menuPosition.left }}
                >
                    {(Object.entries(PRESET_META) as Array<[PreviewViewport, (typeof PRESET_META)[PreviewViewport]]>).map(
                        ([preset, meta]) => {
                            const Icon = meta.icon;

                            return (
                                <button
                                    key={preset}
                                    type="button"
                                    role="menuitemradio"
                                    aria-checked={value === preset}
                                    className={cn(
                                        'workspace-viewport-option',
                                        value === preset && 'workspace-viewport-option--active'
                                    )}
                                    onClick={() => handleSelect(preset)}
                                >
                                    <span className="workspace-viewport-option-icon">
                                        <Icon className="h-4 w-4" />
                                    </span>
                                    <span className="workspace-viewport-option-copy">
                                        <span className="workspace-viewport-option-label">{meta.label}</span>
                                        <span className="workspace-viewport-option-hint">{meta.hint}</span>
                                    </span>
                                </button>
                            );
                        }
                    )}
                </div>,
                document.body
            )}
        </div>
    );
}

export default PreviewViewportMenu;
