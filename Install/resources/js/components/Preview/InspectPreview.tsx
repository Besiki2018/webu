import React, { useRef, useEffect, useCallback, useState } from 'react';
import { PendingEditsPanel } from './PendingEditsPanel';
import type { PreviewViewport } from './PreviewViewportMenu';
import { usePreviewThemeSync } from '@/hooks/usePreviewThemeSync';
import { useTranslation } from '@/contexts/LanguageContext';
import { Loader2, LayoutGrid, Bot, Cog, Wrench } from 'lucide-react';
import type { ElementMention, PendingEdit } from '@/types/inspector';
import { usePreviewThemeInjection } from '@/hooks/usePreviewThemeInjection';
import { useThumbnailCapture } from '@/hooks/useThumbnailCapture';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';
import { observeDOMMapInvalidation } from '@/builder/domMapper';
import { applyPreviewAnnotationEngine } from '@/builder/preview/previewAnnotationEngine';
import {
    createPreviewPlaceholderSection,
    reconcilePreviewPlaceholderNodes,
    syncPreviewPlaceholderSection,
} from '@/builder/preview/previewPlaceholderReconciler';
import {
    type LivePreviewStructureItem,
    syncLivePreviewSection,
} from '@/builder/preview/previewRenderSync';
import { formatPreviewOverlayLabel } from '@/builder/preview/previewSelectionOverlay';
import { syncVisiblePreviewHeading } from '@/builder/preview/previewHeadingSync';
import { useInspectSelectionLifecycle, type DropPlacement } from './useInspectSelectionLifecycle';

type PreviewMode = 'preview' | 'inspect' | 'design';

const DEBUG_INSPECT = typeof window !== 'undefined' && window.location.search.includes('tab=inspect') && window.location.search.includes('debug=inspect');
function inspectLog(..._args: unknown[]) {
    if (DEBUG_INSPECT) {
        console.warn('[WebuInspect]', ..._args);
    }
}

function escapeCssContent(value: string): string {
    return value.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
}

/** Fixed device widths so canvas always shows real desktop/tablet/mobile layout */
const DEVICE_VIEWPORT = {
    desktop: { width: 1440, height: 960 },
    tablet: { width: 834, height: 1112 },
    mobile: { width: 390, height: 844 },
};
const DESKTOP_MIN_WIDTH = 1200;

type LiveStructureItem = LivePreviewStructureItem;

export interface InspectPreviewProps {
    previewUrl?: string | null;
    refreshTrigger?: number;
    isBuilding?: boolean;
    mode?: PreviewMode;
    viewport?: PreviewViewport;
    projectId?: string;  // For thumbnail capture
    captureThumbnailTrigger?: number;  // Change this value to trigger thumbnail capture
    // Inspect mode callbacks (optional when mode !== 'inspect')
    onElementSelect?: (element: ElementMention | null) => void;
    onElementEdit?: (edit: PendingEdit) => void;
    pendingEdits?: PendingEdit[];
    onSaveAllEdits?: () => Promise<void>;
    onDiscardAllEdits?: () => void;
    onRemoveEdit?: (id: string) => void;
    // Design mode props
    themeDesignerSlot?: React.ReactNode;
    onThemeSelect?: (presetId: string) => void;
    isSavingTheme?: boolean;
    currentTheme?: string | null;  // The saved/applied theme preset
    highlightSectionKey?: string | null;
    highlightSectionLocalId?: string | null;
    liveStructureItems?: LiveStructureItem[];
    selectedElementMention?: ElementMention | null;
    pendingLibraryItem?: { key: string; label: string } | null;
    onLibraryItemPlace?: (sectionKey: string, target: ElementMention | null) => void;
    onPreviewReadyChange?: (ready: boolean) => void;
}

/** Minimal loader during build (Lovable-style). */
function BuildingAnimation({
    t,
    label,
    note,
}: {
    t: (key: string) => string;
    label?: string;
    note?: string | null;
}) {
    return (
        <div className="flex flex-col items-center gap-3 text-center">
            <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
            <p className="text-sm font-medium text-foreground">
                {label ?? (t('Building your site...') === 'Building your site...' ? 'ვებსაიტი იქმნება...' : t('Building your site...'))}
            </p>
            {note ? (
                <p className="max-w-sm text-sm leading-6 text-muted-foreground">{note}</p>
            ) : null}
        </div>
    );
}

/**
 * Preview component with element inspection capabilities.
 * Allows users to click elements and mention them in chat or edit inline.
 */
export function InspectPreview({
    previewUrl,
    refreshTrigger = 0,
    isBuilding = false,
    mode = 'inspect',
    viewport = 'desktop',
    projectId,
    captureThumbnailTrigger,
    onElementSelect,
    pendingEdits = [],
    onSaveAllEdits,
    onDiscardAllEdits,
    onRemoveEdit,
    themeDesignerSlot,
    onThemeSelect,
    isSavingTheme = false,
    currentTheme,
    highlightSectionKey = null,
    highlightSectionLocalId = null,
    liveStructureItems = [],
    selectedElementMention = null,
    pendingLibraryItem = null,
    onLibraryItemPlace,
    onPreviewReadyChange,
}: InspectPreviewProps) {
    const { t } = useTranslation();
    const tt = useCallback((key: string, fallback: string) => {
        const translated = t(key);
        return translated === key ? fallback : translated;
    }, [t]);
    const containerRef = useRef<HTMLDivElement>(null);
    const stageRef = useRef<HTMLDivElement>(null);
    const frameRef = useRef<HTMLDivElement>(null);
    const iframeRef = useRef<HTMLIFrameElement>(null);
    const hitLayerRef = useRef<HTMLDivElement | null>(null);
    const wasBuilding = useRef(false);
    const previousMode = useRef<PreviewMode>(mode);
    const previewScrollRestoreRef = useRef({ x: 0, y: 0 });
    const livePreviewHeadingSyncFrameRef = useRef<number | null>(null);
    const lastRefreshTriggerRef = useRef(refreshTrigger);
    const lastPreviewUrlRef = useRef(previewUrl);
    const [isSaving, setIsSaving] = useState(false);
    const [iframeReady, setIframeReady] = useState(false);
    const [isSoftRefreshing, setIsSoftRefreshing] = useState(false);
    const [stageSize, setStageSize] = useState({ w: 0, h: 0 });
    const [iframeNavigationToken, setIframeNavigationToken] = useState(refreshTrigger);
    const iframeSrc = previewUrl
        ? `${previewUrl}${previewUrl.includes('?') ? '&' : '?'}t=${iframeNavigationToken}`
        : null;
    const previewFreezeLabel = isBuilding
        ? tt('Building your site...', 'ვებსაიტი იქმნება...')
        : isSoftRefreshing
            ? tt('Applying changes...', 'ცვლილებები ინერგება...')
            : tt('Loading preview...', 'პრევიუ იტვირთება...');
    const previewFreezeNote = tt('Canvas stays in place while the updated result loads.', 'კანვასი ადგილზე რჩება, სანამ განახლებული შედეგი ჩაიტვირთება.');
    const shouldFreezePreview = Boolean(previewUrl) && (isBuilding || isSoftRefreshing || !iframeReady);

    /* Measure stage so we can scale preview to fit (desktop always visible, not by frame) */
    useEffect(() => {
        const el = stageRef.current;
        if (!el) return;
        const update = () => {
            const w = el.clientWidth || 0;
            const h = el.clientHeight || 0;
            setStageSize((prev) => (prev.w === w && prev.h === h ? prev : { w, h }));
        };
        update();
        const ro = new ResizeObserver(update);
        ro.observe(el);
        return () => ro.disconnect();
    }, []);

    const deviceWidth = DEVICE_VIEWPORT[viewport].width;
    const deviceHeight = DEVICE_VIEWPORT[viewport].height;
    const minimumCanvasWidth = viewport === 'desktop' ? DESKTOP_MIN_WIDTH : deviceWidth;
    const scale =
        stageSize.w > 0 && stageSize.h > 0
            ? Math.min(stageSize.w / deviceWidth, stageSize.h / deviceHeight, 1)
            : 1;
    const scaledWidth = Math.round(deviceWidth * scale);
    const scaledHeight = Math.round(deviceHeight * scale);
    const selectionEnabled = Boolean(onElementSelect) && (mode === 'inspect' || mode === 'preview');

    const selectionLifecycle = useInspectSelectionLifecycle({
        iframeRef,
        frameRef,
        scale,
        mode,
        selectionEnabled,
        isBuilding,
        iframeReady,
        highlightSectionKey: highlightSectionKey ?? null,
        highlightSectionLocalId: highlightSectionLocalId ?? null,
        selectedElementMention: selectedElementMention ?? null,
        liveStructureItems,
        pendingLibraryItem: pendingLibraryItem ?? null,
        onElementSelect,
        onLibraryItemPlace,
    });

    const {
        hoveredOverlay,
        selectedOverlay,
        setSelectedOverlay,
        clearHoveredSection,
        measureSectionOverlay,
        overlaysMatch,
        setHoveredOverlayFromSection,
        resolveSelectedPreviewTarget,
        handlePlacementPointerMove,
        handlePlacementLeave,
        handlePlacementClick,
        handlePlacementDragOver,
        handlePlacementDrop,
        handleInspectPointerMove,
        handleInspectPointerLeave,
        handleInspectClick,
        hoveredSectionRef,
    } = selectionLifecycle;

    const syncPlaceholderSection = useCallback((section: HTMLElement, item: LiveStructureItem) => {
        syncPreviewPlaceholderSection(section, item, tt);
    }, [tt]);

    const createPlaceholderSection = useCallback((item: LiveStructureItem, doc: Document) => {
        return createPreviewPlaceholderSection(item, doc, tt);
    }, [syncPlaceholderSection]);

    const reconcilePreviewPlaceholders = useCallback(() => {
        const iframeDoc = iframeRef.current?.contentDocument;
        if (!iframeReady || !iframeDoc) {
            return;
        }

        reconcilePreviewPlaceholderNodes({
            iframeDoc,
            liveStructureItems,
            syncPlaceholderSection,
            createPlaceholderSection,
        });
    }, [createPlaceholderSection, iframeReady, liveStructureItems, syncPlaceholderSection]);

    const reconcileLivePreviewSections = useCallback(() => {
        const iframeDoc = iframeRef.current?.contentDocument;
        if (!iframeReady || !iframeDoc) {
            return;
        }

        const pendingVisibleHeadings: Array<{ localId: string; text: string }> = [];

        liveStructureItems.forEach((item) => {
            const localId = item.localId.trim();
            if (localId === '') {
                return;
            }

            const container = iframeDoc.querySelector<HTMLElement>(
                `[data-webu-section-local-id="${localId.replace(/"/g, '\\"')}"]:not([data-webu-chat-placeholder="true"])`
            );
            if (!container) {
                return;
            }

            const preferredHeading = syncLivePreviewSection(container, item);
            if (preferredHeading) {
                pendingVisibleHeadings.push({
                    localId,
                    text: preferredHeading,
                });
            }
        });

        if (livePreviewHeadingSyncFrameRef.current !== null) {
            window.cancelAnimationFrame(livePreviewHeadingSyncFrameRef.current);
        }

        livePreviewHeadingSyncFrameRef.current = window.requestAnimationFrame(() => {
            pendingVisibleHeadings.forEach(({ localId, text }) => {
                const container = iframeDoc.querySelector<HTMLElement>(
                    `[data-webu-section-local-id="${localId.replace(/"/g, '\\"')}"]:not([data-webu-chat-placeholder="true"])`
                );
                if (container) {
                    syncVisiblePreviewHeading(container, text);
                }
            });
            livePreviewHeadingSyncFrameRef.current = null;
        });
    }, [iframeReady, liveStructureItems]);

    useEffect(() => {
        if (DEBUG_INSPECT && mode === 'inspect') {
            inspectLog('InspectPreview inspect mode', 'previewUrl=', !!previewUrl, 'iframeReady=', iframeReady, 'onElementSelect=', !!onElementSelect, 'onLibraryItemPlace=', !!onLibraryItemPlace);
        }
    }, [mode, previewUrl, iframeReady, pendingLibraryItem, onElementSelect, onLibraryItemPlace]);

    // Log hit layer size after layout (mount can report 0x0 before layout)
    useEffect(() => {
        if (!DEBUG_INSPECT || mode !== 'inspect') return;
        const el = hitLayerRef.current;
        if (!el) return;
        const logSize = () => {
            const rect = el.getBoundingClientRect();
            inspectLog('hit layer size', 'width=', rect.width, 'height=', rect.height);
        };
        logSize();
        const ro = new ResizeObserver(logSize);
        ro.observe(el);
        return () => ro.disconnect();
    }, [mode]);

    useEffect(() => {
        const previewWindow = iframeRef.current?.contentWindow;
        if (!previewWindow) {
            return;
        }

        previewScrollRestoreRef.current = {
            x: previewWindow.scrollX,
            y: previewWindow.scrollY,
        };
    }, [iframeNavigationToken]);

    useEffect(() => () => {
        if (livePreviewHeadingSyncFrameRef.current !== null) {
            window.cancelAnimationFrame(livePreviewHeadingSyncFrameRef.current);
            livePreviewHeadingSyncFrameRef.current = null;
        }
    }, []);

    // Track iframe load state independently of inspector mode
    useEffect(() => {
        setIframeReady(false);
        const iframe = iframeRef.current;
        if (!iframe) return;

        const handleLoad = () => setIframeReady(true);
        iframe.addEventListener('load', handleLoad);
        return () => iframe.removeEventListener('load', handleLoad);
    }, [iframeSrc]);

    useEffect(() => {
        onPreviewReadyChange?.(iframeReady);
    }, [iframeReady, onPreviewReadyChange]);

    // Invalidate DOM map cache when preview iframe DOM changes (e.g. after drop or edit)
    useEffect(() => {
        if (!iframeReady) return;
        const doc = iframeRef.current?.contentDocument;
        if (!doc) return;
        const disconnect = observeDOMMapInvalidation(doc);
        return () => disconnect();
    }, [iframeReady]);

    useEffect(() => {
        if (previewUrl !== lastPreviewUrlRef.current) {
            lastPreviewUrlRef.current = previewUrl;
            lastRefreshTriggerRef.current = refreshTrigger;
            setIframeReady(false);
            setIframeNavigationToken(refreshTrigger);
            return;
        }

        if (refreshTrigger === lastRefreshTriggerRef.current) {
            return;
        }

        lastRefreshTriggerRef.current = refreshTrigger;

        const refreshPreviewInPlace = async () => {
            const webuCms = (iframeRef.current?.contentWindow as (Window & {
                WebuCms?: { refresh?: () => Promise<unknown> };
            }) | null)?.WebuCms;

            if (!iframeReady || !webuCms?.refresh) {
                setIframeReady(false);
                setIframeNavigationToken(refreshTrigger);
                return;
            }

            setIsSoftRefreshing(true);
            try {
                await webuCms.refresh();
            } catch {
                setIframeReady(false);
                setIframeNavigationToken(refreshTrigger);
            } finally {
                setIsSoftRefreshing(false);
            }
        };

        void refreshPreviewInPlace();
    }, [iframeReady, previewUrl, refreshTrigger]);

    useEffect(() => {
        if (!iframeReady) {
            return;
        }

        const previewWindow = iframeRef.current?.contentWindow;
        if (!previewWindow) {
            return;
        }

        const { x, y } = previewScrollRestoreRef.current;
        previewWindow.scrollTo({
            left: x,
            top: y,
            behavior: 'auto',
        });
    }, [iframeNavigationToken, iframeReady]);

    // Sync light/dark theme with iframe (works in all modes)
    usePreviewThemeSync({ iframeRef, isReady: iframeReady });

    // Theme injection for design mode
    const { applyThemeToPreview: internalApplyTheme } = usePreviewThemeInjection(iframeRef);

    // Thumbnail capture hook
    const { captureAndUpload } = useThumbnailCapture(iframeRef, projectId);

    // Wrap parent's onThemeSelect to also apply theme to our iframe
    const wrappedOnThemeSelect = useCallback((presetId: string) => {
        internalApplyTheme(presetId);
        onThemeSelect?.(presetId);
    }, [internalApplyTheme, onThemeSelect]);

    // Revert theme preview when leaving design mode without applying
    useEffect(() => {
        if (previousMode.current === 'design' && mode !== 'design') {
            // Left design mode - revert to saved theme
            internalApplyTheme(currentTheme || 'default');
        }
        previousMode.current = mode;
    }, [mode, currentTheme, internalApplyTheme]);

    useEffect(() => {
        const iframeDoc = iframeRef.current?.contentDocument;
        if (!iframeDoc?.documentElement) {
            return;
        }

        let style = iframeDoc.getElementById('webu-chat-preview-highlight-style') as HTMLStyleElement | null;
        if (!style) {
            style = iframeDoc.createElement('style');
            style.id = 'webu-chat-preview-highlight-style';
            iframeDoc.head?.appendChild(style);
        }
        style.textContent = `
html,
body {
  width: 100%;
  min-width: 100%;
  max-width: 100%;
  min-height: 100%;
  overflow-x: hidden;
}
body {
  width: 100%;
  min-width: ${minimumCanvasWidth}px;
  max-width: none;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
body > main,
body > .main_content,
body > [data-webu-role="page-root"],
body > [data-webu-role="page-shell"] {
  flex: 1 0 auto;
  width: 100%;
  min-width: 100%;
  max-width: 100%;
}
.main_content,
main[data-webu-role="page-root"],
[data-webu-role="page-shell"] {
  display: flex;
  width: 100%;
  min-width: 100%;
  max-width: 100%;
  flex: 1 0 auto;
  flex-direction: column;
  min-height: 0;
}
body > footer,
body > .footer,
body > [data-webu-role="site-footer"],
body > [data-webu-section*="footer"],
.main_content > footer,
.main_content > .footer,
.main_content > [data-webu-role="site-footer"],
.main_content > [data-webu-section*="footer"] {
  margin-top: auto !important;
  flex-shrink: 0;
}
[data-webu-section] {
  box-sizing: border-box;
  display: block;
  width: 100%;
  max-width: 100%;
  flex: 0 0 auto;
  transition: outline 120ms ease;
}
[data-webu-section][data-webu-chat-hovered="true"] {
  outline: none !important;
  box-shadow: none !important;
  cursor: pointer !important;
}
html[data-webu-chat-placement="true"] [data-webu-section][data-webu-chat-hovered="true"] {
  position: relative;
  outline: none !important;
  box-shadow: none !important;
}
html[data-webu-chat-placement="true"] [data-webu-section][data-webu-chat-drop-position="before"]::before,
html[data-webu-chat-placement="true"] [data-webu-section][data-webu-chat-drop-position="after"]::before {
  content: "";
  position: absolute;
  left: 14px;
  right: 14px;
  height: 3px;
  border-radius: 9999px;
  background: #2563eb;
  box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.92);
  z-index: 2147483646;
}
html[data-webu-chat-placement="true"] [data-webu-section][data-webu-chat-drop-position="before"]::after,
html[data-webu-chat-placement="true"] [data-webu-section][data-webu-chat-drop-position="after"]::after {
  content: "${escapeCssContent(tt('Drop element here', 'ელემენტი აქ ჩასვით'))}";
  position: absolute;
  left: 14px;
  display: inline-flex;
  align-items: center;
  height: 24px;
  border-radius: 9999px;
  background: #2563eb;
  padding: 0 10px;
  font: 600 12px/1 system-ui, sans-serif;
  color: #fff;
  white-space: nowrap;
  box-shadow: 0 10px 20px rgba(37, 99, 235, 0.22);
  z-index: 2147483647;
}
html[data-webu-chat-placement="true"] [data-webu-section][data-webu-chat-drop-position="before"]::before {
  top: -2px;
}
html[data-webu-chat-placement="true"] [data-webu-section][data-webu-chat-drop-position="before"]::after {
  top: -30px;
}
html[data-webu-chat-placement="true"] [data-webu-section][data-webu-chat-drop-position="after"]::before {
  bottom: -2px;
}
html[data-webu-chat-placement="true"] [data-webu-section][data-webu-chat-drop-position="after"]::after {
  bottom: -30px;
}
html[data-webu-chat-placement="true"] [data-webu-section][data-webu-chat-drop-position="inside"] {
  outline: none !important;
  box-shadow: none !important;
}
[data-webu-section][data-webu-chat-selected="true"] {
  outline: none !important;
  box-shadow: none !important;
}
html[data-webu-chat-inspect="true"] a,
html[data-webu-chat-inspect="true"] button,
html[data-webu-chat-inspect="true"] input,
html[data-webu-chat-inspect="true"] textarea,
html[data-webu-chat-inspect="true"] select,
html[data-webu-chat-inspect="true"] label,
html[data-webu-chat-inspect="true"] [role="button"] {
  pointer-events: none !important;
}
`;

        const docEl = iframeDoc.documentElement;
        if (mode === 'inspect') {
            docEl.setAttribute('data-webu-chat-inspect', 'true');
        } else {
            docEl.removeAttribute('data-webu-chat-inspect');
        }

        if (pendingLibraryItem) {
            docEl.setAttribute('data-webu-chat-placement', 'true');
        } else {
            docEl.removeAttribute('data-webu-chat-placement');
        }

        iframeDoc
            .querySelectorAll<HTMLElement>('[data-webu-chat-hovered="true"]')
            .forEach((node) => node.removeAttribute('data-webu-chat-hovered'));

        iframeDoc
            .querySelectorAll<HTMLElement>('[data-webu-chat-selected="true"]')
            .forEach((node) => node.removeAttribute('data-webu-chat-selected'));

        if (!highlightSectionKey && !highlightSectionLocalId && !selectedElementMention) {
            return;
        }

        const selectedTarget = resolveSelectedPreviewTarget();
        if (selectedTarget) {
            selectedTarget.setAttribute('data-webu-chat-selected', 'true');
            const overlay = measureSectionOverlay(selectedTarget);
            setSelectedOverlay((current) => (overlaysMatch(current, overlay) ? current : overlay));
            // Scroll preview to the highlighted component (e.g. after AI apply)
            try {
                selectedTarget.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
            } catch {
                // ignore in unsupported environments
            }
            return;
        }
        setSelectedOverlay(null);
    }, [highlightSectionKey, highlightSectionLocalId, iframeReady, measureSectionOverlay, minimumCanvasWidth, mode, overlaysMatch, pendingLibraryItem, refreshTrigger, resolveSelectedPreviewTarget, selectedElementMention, tt]);

    useEffect(() => {
        reconcilePreviewPlaceholders();
    }, [reconcilePreviewPlaceholders]);

    useEffect(() => {
        reconcileLivePreviewSections();
    }, [reconcileLivePreviewSections]);

    useEffect(() => {
        const iframeDoc = iframeRef.current?.contentDocument;
        const root = iframeDoc?.body ?? iframeDoc?.documentElement;
        if (!iframeReady || !iframeDoc || !root) {
            return;
        }

        let rafId = 0;
        let reconnectRafId = 0;
        let isApplyingAnnotations = false;
        let observer: MutationObserver | null = null;
        const observeMutations = () => {
            observer?.observe(root, {
                childList: true,
                subtree: true,
                characterData: true,
            });
        };
        const applyAnnotations = () => {
            if (isApplyingAnnotations) {
                return;
            }
            isApplyingAnnotations = true;
            observer?.disconnect();
            applyPreviewAnnotationEngine({
                iframeDoc,
                liveStructureItems,
                selectionEnabled,
                onRenderSync: reconcileLivePreviewSections,
                onPlaceholderReconcile: reconcilePreviewPlaceholders,
            });
            reconnectRafId = window.requestAnimationFrame(() => {
                isApplyingAnnotations = false;
                observeMutations();
            });
        };

        observer = new MutationObserver(() => {
            if (isApplyingAnnotations) {
                return;
            }
            window.cancelAnimationFrame(rafId);
            rafId = window.requestAnimationFrame(() => {
                applyAnnotations();
            });
        });

        applyAnnotations();

        return () => {
            window.cancelAnimationFrame(rafId);
            window.cancelAnimationFrame(reconnectRafId);
            observer.disconnect();
        };
    }, [iframeReady, liveStructureItems, reconcileLivePreviewSections, reconcilePreviewPlaceholders, selectionEnabled]);

    useEffect(() => {
        if (!pendingLibraryItem) {
            clearHoveredSection();
        }
    }, [clearHoveredSection, pendingLibraryItem]);

    useEffect(() => {
        if (!selectionEnabled || !iframeReady) {
            clearHoveredSection();
            setSelectedOverlay(null);
            return;
        }

        const iframe = iframeRef.current;
        const frame = frameRef.current;
        const iframeWindow = iframe?.contentWindow;
        const iframeDoc = iframe?.contentDocument;
        if (!iframe || !frame || !iframeWindow || !iframeDoc) {
            return;
        }

        const syncOverlayFrames = () => {
            setHoveredOverlayFromSection(
                hoveredSectionRef.current,
                (hoveredSectionRef.current?.getAttribute('data-webu-chat-drop-position') as DropPlacement | null) ?? null
            );

            const selectedSectionNode = resolveSelectedPreviewTarget();
            const nextSelectedOverlay = selectedSectionNode ? measureSectionOverlay(selectedSectionNode) : null;
            setSelectedOverlay((current) => (overlaysMatch(current, nextSelectedOverlay) ? current : nextSelectedOverlay));
        };

        syncOverlayFrames();

        const resizeObserver = new ResizeObserver(syncOverlayFrames);
        resizeObserver.observe(frame);
        resizeObserver.observe(iframeDoc.documentElement);
        if (iframeDoc.body) {
            resizeObserver.observe(iframeDoc.body);
        }

        iframeWindow.addEventListener('scroll', syncOverlayFrames, { passive: true });
        window.addEventListener('resize', syncOverlayFrames);

        return () => {
            resizeObserver.disconnect();
            iframeWindow.removeEventListener('scroll', syncOverlayFrames);
            window.removeEventListener('resize', syncOverlayFrames);
        };
    }, [clearHoveredSection, iframeReady, measureSectionOverlay, overlaysMatch, resolveSelectedPreviewTarget, selectionEnabled, setHoveredOverlayFromSection]);

    const handleInspectWheel = useCallback((event: WheelEvent) => {
        const previewWindow = iframeRef.current?.contentWindow;
        if (!previewWindow) return;
        event.preventDefault();
        previewWindow.scrollBy({ top: event.deltaY, left: event.deltaX, behavior: 'auto' });
    }, []);

    // Wheel on hit layer must use { passive: false } so preventDefault works (no passive listener warning)
    useEffect(() => {
        if (!selectionEnabled) return;
        const el = hitLayerRef.current;
        if (!el) return;
        el.addEventListener('wheel', handleInspectWheel, { passive: false });
        return () => el.removeEventListener('wheel', handleInspectWheel);
    }, [handleInspectWheel, selectionEnabled]);

    useEffect(() => {
        if (!selectionEnabled) {
            return;
        }

        const hitLayer = hitLayerRef.current;
        if (!hitLayer) {
            return;
        }

        const forwardMouseEvent = (
            event: MouseEvent,
            handler: (event: React.MouseEvent<HTMLDivElement>) => void,
        ) => {
            handler(event as unknown as React.MouseEvent<HTMLDivElement>);
        };

        const forwardDragEvent = (
            event: DragEvent,
            handler: (event: React.DragEvent<HTMLDivElement>) => void,
        ) => {
            handler(event as unknown as React.DragEvent<HTMLDivElement>);
        };

        const handleMouseMove = (event: MouseEvent) => {
            if (pendingLibraryItem) {
                forwardMouseEvent(event, handlePlacementPointerMove);
                return;
            }

            forwardMouseEvent(event, handleInspectPointerMove);
        };

        const handleMouseLeave = () => {
            if (pendingLibraryItem) {
                handlePlacementLeave();
                return;
            }

            handleInspectPointerLeave();
        };

        const handleClick = (event: MouseEvent) => {
            if (pendingLibraryItem) {
                forwardMouseEvent(event, handlePlacementClick);
                return;
            }

            forwardMouseEvent(event, handleInspectClick);
        };

        const handleDragOver = (event: DragEvent) => {
            if (!pendingLibraryItem) {
                return;
            }

            forwardDragEvent(event, handlePlacementDragOver);
        };

        const handleDrop = (event: DragEvent) => {
            if (!pendingLibraryItem) {
                return;
            }

            forwardDragEvent(event, handlePlacementDrop);
        };

        hitLayer.addEventListener('mousemove', handleMouseMove);
        hitLayer.addEventListener('mouseleave', handleMouseLeave);
        hitLayer.addEventListener('click', handleClick);
        hitLayer.addEventListener('dragover', handleDragOver);
        hitLayer.addEventListener('drop', handleDrop);

        return () => {
            hitLayer.removeEventListener('mousemove', handleMouseMove);
            hitLayer.removeEventListener('mouseleave', handleMouseLeave);
            hitLayer.removeEventListener('click', handleClick);
            hitLayer.removeEventListener('dragover', handleDragOver);
            hitLayer.removeEventListener('drop', handleDrop);
        };
    }, [
        handleInspectClick,
        handleInspectPointerLeave,
        handleInspectPointerMove,
        handlePlacementClick,
        handlePlacementDragOver,
        handlePlacementDrop,
        handlePlacementLeave,
        handlePlacementPointerMove,
        pendingLibraryItem,
        selectionEnabled,
    ]);

    // Thumbnail capture when build completes (no confetti)
    useEffect(() => {
        if (wasBuilding.current && !isBuilding) {
            setTimeout(() => captureAndUpload(), 2000);
        }
        wasBuilding.current = isBuilding;
    }, [isBuilding, captureAndUpload]);

    // Capture thumbnail when trigger prop changes (e.g., after theme apply)
    const lastCaptureTrigger = useRef(0);
    useEffect(() => {
        if (captureThumbnailTrigger && captureThumbnailTrigger > 0 && captureThumbnailTrigger !== lastCaptureTrigger.current) {
            lastCaptureTrigger.current = captureThumbnailTrigger;
            // 2s delay to allow preview to update with new theme
            setTimeout(() => {
                captureAndUpload();
            }, 2000);
        }
    }, [captureThumbnailTrigger, captureAndUpload]);

    // Handle save all edits
    const handleSaveAll = useCallback(async () => {
        if (!onSaveAllEdits) return;
        setIsSaving(true);
        try {
            await onSaveAllEdits();
            toast.success(t('Changes sent to AI for processing'));
        } catch {
            toast.error(t('Failed to save changes'));
        } finally {
            setIsSaving(false);
        }
    }, [onSaveAllEdits, t]);

    // Handle discard all edits
    const handleDiscardAll = useCallback(() => {
        if (!onDiscardAllEdits) return;
        onDiscardAllEdits();
    }, [onDiscardAllEdits]);

    // Handle remove single edit
    const handleRemoveEdit = useCallback((id: string) => {
        onRemoveEdit?.(id);
    }, [onRemoveEdit]);

    if (previewUrl) {
        return (
            <div
                ref={containerRef}
                className="workspace-preview-root relative flex h-full w-full flex-col overflow-hidden"
                style={mode === 'inspect' ? { minHeight: 'min(360px, 50vh)' } : undefined}
            >
                <div
                    ref={stageRef}
                    className={`workspace-preview-stage workspace-preview-stage--${viewport}`}
                >
                    {mode === 'design' && themeDesignerSlot && (
                        <div className="workspace-preview-side-panel">
                            {React.isValidElement(themeDesignerSlot)
                                ? React.cloneElement(themeDesignerSlot as React.ReactElement<{ onThemeSelect?: (presetId: string) => void }>, {
                                    onThemeSelect: wrappedOnThemeSelect,
                            })
                            : themeDesignerSlot}
                        </div>
                    )}

                    {/* Scale-to-fit: preview always shows full desktop/tablet/mobile layout inside canvas */}
                    <div className="workspace-preview-scale-container">
                        <div
                            className={`workspace-preview-scale-shell workspace-preview-scale-shell--${viewport}`}
                            data-testid="preview-scale-shell"
                            style={{
                                width: scaledWidth,
                                height: scaledHeight,
                            }}
                        >
                            <div
                            data-testid="preview-scale-wrapper"
                            className="workspace-preview-scale-wrapper"
                                style={{
                                    width: deviceWidth,
                                    height: deviceHeight,
                                transform: `scale(${scale})`,
                                transformOrigin: 'top left',
                            }}
                        >
                            <div
                                className={`workspace-device-viewport workspace-device-viewport--${viewport}`}
                                role="presentation"
                                style={{
                                    width: deviceWidth,
                                    minWidth: viewport === 'desktop' ? DESKTOP_MIN_WIDTH : deviceWidth,
                                }}
                            >
                                <div
                                    ref={frameRef}
                                    className={`workspace-preview-frame workspace-preview-frame--${viewport}${mode === 'inspect' ? ' workspace-preview-frame--inspect' : ''}${pendingLibraryItem ? ' workspace-preview-frame--placing' : ''}`}
                                >
                        {/* Keep a deterministic hit layer whenever preview selection targeting is enabled so
                            scaled preview coordinates and chat/inspect targeting resolve through one path. */}
                        {selectionEnabled ? (
                            <div
                                ref={(el) => {
                                    (hitLayerRef as React.MutableRefObject<HTMLDivElement | null>).current = el;
                                    if (DEBUG_INSPECT && el) {
                                        const rect = el.getBoundingClientRect();
                                        inspectLog('hit layer mounted', 'width=', rect.width, 'height=', rect.height);
                                    }
                                }}
                                className="absolute inset-0 z-[50] min-h-full w-full cursor-default"
                                style={{ pointerEvents: 'auto' }}
                                aria-hidden
                            />
                        ) : null}
                        <iframe
                            ref={iframeRef}
                            src={iframeSrc ?? undefined}
                            className={`absolute inset-0 h-full w-full border-0 bg-white${mode === 'inspect' ? ' workspace-preview-iframe--inspect' : ''}${pendingLibraryItem ? ' workspace-preview-iframe--placement' : ''}`}
                            title={tt('Preview', 'პრევიუ')}
                            sandbox="allow-scripts allow-same-origin"
                        />

                        {pendingLibraryItem ? (
                            <div className="pointer-events-none absolute inset-x-0 top-6 z-20 flex justify-center">
                                <div className="workspace-preview-placement-chip">
                                    <LayoutGrid className="h-4 w-4" />
                                    <span>{pendingLibraryItem.label}</span>
                                </div>
                            </div>
                        ) : null}

                        {selectionEnabled && hoveredOverlay ? (
                            <div
                                className={cn(
                                    pendingLibraryItem ? 'workspace-preview-drop-target' : 'workspace-preview-hover-target',
                                    pendingLibraryItem && hoveredOverlay.placement === 'inside' && 'workspace-preview-drop-target--inside'
                                )}
                                style={{
                                    left: `${hoveredOverlay.left}px`,
                                    top: `${hoveredOverlay.top}px`,
                                    width: `${hoveredOverlay.width}px`,
                                    height: `${hoveredOverlay.height}px`,
                                    outline: pendingLibraryItem ? undefined : '2px dashed #6366f1',
                                    outlineOffset: pendingLibraryItem ? undefined : '-2px',
                                }}
                            >
                                {!pendingLibraryItem && formatPreviewOverlayLabel(hoveredOverlay.label) ? (
                                    <div className="pointer-events-none absolute -top-7 left-0 rounded bg-indigo-600 px-2 py-1 text-[11px] font-medium text-white shadow-sm">
                                        {formatPreviewOverlayLabel(hoveredOverlay.label)}
                                    </div>
                                ) : null}
                                {pendingLibraryItem && hoveredOverlay.placement === 'inside' ? (
                                    <div className="workspace-preview-drop-inside-badge">
                                        <span>{t('აქ ჩაჯდება')}</span>
                                    </div>
                                ) : null}
                                {pendingLibraryItem && hoveredOverlay.placement && hoveredOverlay.placement !== 'inside' ? (
                                    <div
                                        className={cn(
                                            'workspace-preview-drop-line',
                                            hoveredOverlay.placement === 'before'
                                                ? 'workspace-preview-drop-line--before'
                                                : 'workspace-preview-drop-line--after'
                                        )}
                                    >
                                        <span>{t('აქ ჩაჯდება')}</span>
                                    </div>
                                ) : null}
                            </div>
                        ) : null}

                        {selectionEnabled && selectedOverlay ? (
                            <div
                                className="workspace-preview-selected-target"
                                style={{
                                    left: `${selectedOverlay.left}px`,
                                    top: `${selectedOverlay.top}px`,
                                    width: `${selectedOverlay.width}px`,
                                    height: `${selectedOverlay.height}px`,
                                }}
                            >
                                {formatPreviewOverlayLabel(selectedOverlay.label) ? (
                                    <div className="pointer-events-none absolute -top-7 left-0 rounded bg-slate-900 px-2 py-1 text-[11px] font-medium text-white shadow-sm">
                                        {formatPreviewOverlayLabel(selectedOverlay.label)}
                                    </div>
                                ) : null}
                            </div>
                        ) : null}

                        {/* Freeze the visible canvas while preview updates load underneath it. */}
                        {shouldFreezePreview && !isSavingTheme && (
                            <div className="workspace-preview-freeze">
                                <div className="workspace-preview-freeze-card">
                                    <BuildingAnimation
                                        t={t}
                                        label={previewFreezeLabel}
                                        note={previewFreezeNote}
                                    />
                                </div>
                            </div>
                        )}

                        {/* Theme saving overlay - only in design mode */}
                        {mode === 'design' && isSavingTheme && (
                            <div className="absolute inset-0 z-20 flex items-center justify-center bg-[#232321]/16 backdrop-blur-sm">
                                <div className="workspace-preview-card">
                                    <div className="flex items-center gap-4">
                                        <div className="workspace-preview-bounce">
                                            <Bot className="h-8 w-8 text-primary" />
                                        </div>
                                        <div className="workspace-preview-spin">
                                            <Cog className="h-10 w-10 text-muted-foreground" />
                                        </div>
                                        <div className="workspace-preview-bounce workspace-preview-bounce--delayed">
                                            <Wrench className="h-8 w-8 text-primary" />
                                        </div>
                                    </div>
                                    <div className="text-center">
                                        <h3 className="font-medium text-lg">{t('Applying theme...')}</h3>
                                        <p className="text-sm text-muted-foreground mt-1">{t('This may take a moment')}</p>
                                    </div>
                                </div>
                            </div>
                        )}
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>

                {/* Pending edits panel - only in inspect mode */}
                {mode === 'inspect' && pendingEdits.length > 0 && (
                    <PendingEditsPanel
                        edits={pendingEdits}
                        onSaveAll={handleSaveAll}
                        onDiscardAll={handleDiscardAll}
                        onRemoveEdit={handleRemoveEdit}
                        isSaving={isSaving}
                    />
                )}

            </div>
        );
    }

    // Empty state
    return (
        <div ref={containerRef} className="workspace-preview-root relative flex h-full w-full items-center justify-center overflow-hidden">
            <div className="workspace-surface workspace-surface--compact relative z-10 flex max-w-sm flex-col items-center px-8 py-10 text-center">
                {isBuilding ? (
                    <BuildingAnimation t={t} />
                ) : (
                    <div className="prose prose-sm max-w-none">
                        <h3 className="mb-3 text-2xl font-semibold text-slate-900">
                            {t('Nothing built yet')}
                        </h3>
                        <p className="leading-relaxed text-slate-500">
                            {t('Start a conversation with the AI to build your website. Your project will appear here.')}
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
}

export default InspectPreview;
