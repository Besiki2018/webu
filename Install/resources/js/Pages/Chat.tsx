import { Suspense, lazy, useState, useRef, useEffect, useCallback, useMemo, type DragEvent as ReactDragEvent } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import { MessageBubble } from '@/components/Chat/MessageBubble';
import { PendingAssistantBubble } from '@/components/Chat/MessageBubble';
import { AgentProgressInline } from '@/components/Chat/AgentProgressInline';
import type { CodeEditorHandle } from '@/components/Code/CodeEditor';
import type { CodeFileSelectionMeta } from '@/components/Code/FileTree';
import { ChatPageSkeleton, MessageListSkeleton } from '@/components/Skeleton';
import type { SoundSettings } from '@/hooks/useChatSounds';
import { useBuilderChat, BroadcastConfig } from '@/hooks/useBuilderChat';
import { useAiSiteEditor, type ChangeSet } from '@/hooks/useAiSiteEditor';
import { useNotifications } from '@/hooks/useNotifications';
import { useUserChannel } from '@/hooks/useUserChannel';
import { useTranslation } from '@/contexts/LanguageContext';
import { PageProps, User } from '@/types';
import type { ChatMessage } from '@/types/chat';
import type { UserNotification } from '@/types/notifications';
import { Code, Loader2, Globe, MousePointerClick, Palette, Sparkles, ChevronDown, History, Columns2, Cloud, BarChart3, Ellipsis, ArrowLeft, Share2, ExternalLink, CreditCard, RefreshCw, Layers, MessageSquare, GripVertical, ArrowUp, Trash2, Save, PanelLeft } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { toast } from 'sonner';
import { Toaster } from '@/components/ui/sonner';
import axios from 'axios';
import { PreviewViewportMenu } from '@/components/Preview/PreviewViewportMenu';
import type { PreviewViewport } from '@/components/Preview/PreviewViewportMenu';
import { ChatInputWithMentions } from '@/components/Chat/ChatInputWithMentions';
import { useBuildCredits, BuildCreditsInfo } from '@/hooks/useBuildCredits';
import type { ElementMention, PendingEdit } from '@/types/inspector';
import { cn } from '@/lib/utils';
import { getAgentErrorMessage } from '@/lib/agentErrorMessages';
import { shouldPreferProjectEdit } from '@/lib/chatAgentRouting';
import { detectReplyLanguage, resolveAiCommandLocale } from '@/lib/chatLocale';
import {
    changeSetHasUnsyncedOperations,
    extractPreviewLayoutOverrides,
    getBuilderSyncableChangeSet,
    resolveChangeSetScopeLabel,
} from '@/lib/agentChangeSet';
import { resolveBuilderWidgetIcon } from '@/lib/resolveBuilderWidgetIcon';
import { getShortDisplayName } from '@/builder/componentRegistry';
import { getClarificationPrompt } from '@/lib/chatClarification';
import {
    buildSectionPreviewText,
    parseSectionProps as parseBuilderSectionProps,
} from '@/builder/state/sectionProps';
import {
    type BuilderEditableTarget,
    editableTargetToMention,
} from '@/builder/editingState';
import {
    isImprovementCommand,
    getDesignUpgradeSectionKind,
    runChatImprovement,
    applyOptimizationStepsToSections,
    formatImprovementSummary,
} from '@/builder/ai/chatImprovementCommands';
import { runDesignUpgrade, type ContentSectionType } from '@/builder/ai/designUpgrade';
import {
    buildDetailedAssistantMessage,
    getInitialViewMode,
    isConversationalMessage,
    reorderStructureCollection,
    type ChatViewMode,
} from '@/builder/chat/chatPageUtils';
import {
    buildStructureItemSelection,
    resolveMentionBuilderTarget,
} from '@/builder/chat/chatBuilderSelection';
import {
    buildSelectedTargetContext,
    selectedTargetIsMappable,
    type SelectedTargetContext,
} from '@/builder/selectedTargetContext';
import {
    builderBridgePagesMatch,
    createBuilderBridgeRequestId,
} from '@/builder/cms/embeddedBuilderBridgeContract';
import {
    getWorkspaceBuilderPageIdentity,
    type WorkspaceBuilderCodePage as BuilderCodePage,
    type WorkspaceBuilderStructureItem as BuilderStructureItem,
} from '@/builder/cms/workspaceBuilderSync';
import { useGeneratedCodePreview } from '@/builder/chat/useGeneratedCodePreview';
import {
    buildOptimisticInsertedStructureItems,
    buildOptimisticRemovedStructureItems,
    createPendingBuilderSelectionSnapshot,
    type PendingBuilderStructureMutation,
} from '@/builder/cms/chatBuilderStructureMutations';
import { buildBuilderPreviewUrl, useBuilderWorkspace, type BuilderLibraryItem } from '@/builder/chat/useBuilderWorkspace';
import { buildCanonicalBridgeSelectedTargetPayload } from '@/builder/cms/canonicalSelectionPayload';
import { BuilderWorkspaceShell } from '@/builder/workspace/BuilderWorkspaceShell';
import { BuilderPreviewSurface } from '@/builder/workspace/BuilderPreviewSurface';

const FileTree = lazy(async () => ({ default: (await import('@/components/Code/FileTree')).FileTree }));
const CodeEditor = lazy(async () => ({ default: (await import('@/components/Code/CodeEditor')).CodeEditor }));
const PublishModal = lazy(() => import('@/components/Project/PublishModal'));
const ProjectSettingsPanel = lazy(async () => ({ default: (await import('@/components/Project/ProjectSettingsPanel')).ProjectSettingsPanel }));
const InspectPreview = lazy(async () => ({ default: (await import('@/components/Preview/InspectPreview')).InspectPreview }));
const ThemeDesigner = lazy(async () => ({ default: (await import('@/components/Design/ThemeDesigner')).ThemeDesigner }));

const RESPONSE_STRINGS: Record<'en' | 'ka', {
    madeFollowingChanges: string;
    doneApplied: string;
    updatedProjectSummary: string;
    changes: string;
    noChangesApplied: string;
    couldNotUnderstand: string;
    greetingReply: string;
    hereReply: string;
    requestCouldNotBeApplied: string;
    requestCouldNotBeCompleted: string;
    projectEditRequestFailed: string;
}> = {
    en: {
        madeFollowingChanges: "I've made the following changes:",
        doneApplied: "Done. I've applied your request.",
        updatedProjectSummary: "I've updated the project. Summary:",
        changes: 'Changes:',
        noChangesApplied: 'No changes applied.',
        couldNotUnderstand: 'Could not understand the request. Try rephrasing.',
        greetingReply: "Hi! I'm here to help you edit your site. What would you like to change?",
        hereReply: "Yes, I'm here. What would you like to do?",
        requestCouldNotBeApplied: 'The request could not be applied.',
        requestCouldNotBeCompleted: 'Request could not be completed.',
        projectEditRequestFailed: 'Project edit request failed. Check that the workspace is initialized and AI is configured.',
    },
    ka: {
        madeFollowingChanges: 'შევასრულე შემდეგი ცვლილებები:',
        doneApplied: 'მზადაა. შენი მოთხოვნა შესრულებულია.',
        updatedProjectSummary: 'პროექტი განახლდა. შეჯამება:',
        changes: 'ცვლილებები:',
        noChangesApplied: 'ცვლილებები არ გაკეთებულა.',
        couldNotUnderstand: 'მოთხოვნა ვერ გავიგე. სცადე სხვა ფორმულირება.',
        greetingReply: 'გამარჯობა! აქ ვარ, რომ საიტის რედაქტირებაში დაგეხმარო. რა შევცვალო?',
        hereReply: 'დიახ, აქ ვარ. რისი გაკეთება გსურს?',
        requestCouldNotBeApplied: 'მოთხოვნა ვერ შესრულდა.',
        requestCouldNotBeCompleted: 'მოთხოვნის შესრულება ვერ მოხდა.',
        projectEditRequestFailed: 'პროექტის რედაქტირება ვერ მოხდა. შეამოწმე, რომ სამუშაო სივრცე ინიციალიზებულია და AI კონფიგურირებულია.',
    },
};

interface Project {
    id: string;
    name: string;
    initial_prompt: string | null;
    has_history: boolean;
    conversation_history: Array<{
        role: 'user' | 'assistant' | 'action';
        content: string;
        timestamp: string;
        category?: string;
        thinking_duration?: number;
    }>;
    preview_url: string | null;
    cms_preview_url?: string | null;
    has_active_session: boolean;
    build_session_id: string | null;
    // Reconnection-related fields
    build_status?: string;
    can_reconnect?: boolean;
    build_started_at?: string | null;
    // Publishing fields
    subdomain: string | null;
    published_title: string | null;
    published_description: string | null;
    published_visibility: string;
    published_at: string | null;
    // Settings fields
    custom_instructions: string | null;
    theme_preset: string | null;
    share_image: string | null;
    api_token?: string | null;
}

interface FirebaseSettings {
    enabled: boolean;
    canUseOwnConfig: boolean;
    usesSystemFirebase: boolean;
    customConfig: {
        apiKey: string;
        authDomain: string;
        projectId: string;
        storageBucket: string;
        messagingSenderId: string;
        appId: string;
    } | null;
    systemConfigured: boolean;
    collectionPrefix: string;
    adminSdkConfigured: boolean;
    adminSdkStatus: {
        configured: boolean;
        is_system: boolean;
        project_id: string | null;
        client_email: string | null;
    };
}

interface StorageSettings {
    enabled: boolean;
    usedBytes: number;
    limitMb: number | null;
    unlimited: boolean;
}

interface ModuleRegistryItem {
    key: string;
    label: string;
    group: string;
    implemented: boolean;
    requested: boolean;
    globally_enabled: boolean;
    entitled: boolean;
    enabled: boolean;
    available: boolean;
    reason: string | null;
}

interface ModuleRegistryPayload {
    site_id: string;
    project_id: string;
    modules: ModuleRegistryItem[];
    summary: {
        total: number;
        available: number;
        disabled: number;
        not_entitled: number;
    };
}

interface ChatPageProps extends PageProps {
    project: Project;
    user: User;
    builderLibraryItems?: BuilderLibraryItem[];
    generatedPage?: GeneratedPagePayload | null;
    generatedPages?: GeneratedPagePayload[];
    pusherConfig: BroadcastConfig;
    soundSettings: SoundSettings;
    // Publishing props
    baseDomain: string;
    canUseSubdomains: boolean;
    canCreateMoreSubdomains: boolean;
    canUsePrivateVisibility: boolean;
    suggestedSubdomain: string;
    subdomainUsage: {
        used: number;
        limit: number | null;
        unlimited: boolean;
        remaining: number;
    };
    // Storage & Database props
    firebase?: FirebaseSettings;
    storage?: StorageSettings;
    moduleRegistry?: ModuleRegistryPayload;
    // Build credits
    buildCredits: BuildCreditsInfo;
}

const FALLBACK_BUILD_CREDITS: BuildCreditsInfo = {
    remaining: 0,
    monthlyLimit: 0,
    isUnlimited: false,
    usingOwnKey: false,
};

const FALLBACK_APP_SETTINGS = {
    site_favicon: null as string | null,
    site_logo: null as string | null,
};

type ViewMode = ChatViewMode;

type StructureDropIndicator = {
    localId: string;
    position: 'before' | 'after';
};

interface GeneratedPageSection {
    type: string;
    props: Record<string, unknown>;
}

interface GeneratedPagePayload {
    page_id: number | null;
    revision_id: number | null;
    slug: string | null;
    title: string | null;
    revision_source: string | null;
    sections: GeneratedPageSection[];
}

const DEBUG_INSPECT = typeof window !== 'undefined' && window.location.search.includes('tab=inspect') && window.location.search.includes('debug=inspect');
function inspectLog(..._args: unknown[]) {
    if (DEBUG_INSPECT) {
        console.warn('[WebuInspect:Chat]', ..._args);
    }
}

export default function Chat({
    project,
    user: _user,
    builderLibraryItems: seededBuilderLibraryItems = [],
    generatedPage,
    generatedPages = [],
    pusherConfig,
    soundSettings,
    baseDomain,
    canUseSubdomains,
    canCreateMoreSubdomains,
    canUsePrivateVisibility,
    suggestedSubdomain,
    subdomainUsage,
    firebase,
    storage,
    moduleRegistry,
    buildCredits,
}: ChatPageProps) {
    const { t, locale: appLocale } = useTranslation();

    // Get unread notification count from shared props
    const pageProps = usePage<PageProps & { unreadNotificationCount: number }>().props;
    const unreadNotificationCount = pageProps.unreadNotificationCount ?? 0;
    const appSettings = pageProps.appSettings ?? FALLBACK_APP_SETTINGS;

    // Notification state
    const { addNotification } = useNotifications(unreadNotificationCount);

    // Subscribe to user channel for real-time notification updates
    useUserChannel({
        userId: _user.id,
        broadcastConfig: pusherConfig,
        enabled: !!pusherConfig?.key,
        onNotification: (notification: UserNotification) => {
            addNotification(notification);
            // Show toast for important notifications (but not build_complete/failed since we're on chat page)
            if (notification.type === 'credits_low') {
                toast(notification.title, {
                    description: notification.message,
                });
            }
        },
    });
    const [viewMode, setViewMode] = useState<ViewMode>(getInitialViewMode);

    // Sync viewMode to URL
    useEffect(() => {
        const url = new URL(window.location.href);
        if (viewMode === 'preview') {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', viewMode);
        }
        window.history.replaceState({}, '', url.toString());
    }, [viewMode]);

    const [prompt, setPrompt] = useState('');
    const [selectedFile, setSelectedFile] = useState<string | null>(null);
    const [selectedFileMeta, setSelectedFileMeta] = useState<CodeFileSelectionMeta | null>(null);

    const [fileRefreshTrigger, setFileRefreshTrigger] = useState(0);
    const [isRegeneratingCode, setIsRegeneratingCode] = useState(false);
    const codeEditorRef = useRef<CodeEditorHandle>(null);
    const [previewRefreshTrigger, setPreviewRefreshTrigger] = useState(() => Date.now());
    const [isProjectMenuOpen, setIsProjectMenuOpen] = useState(false);
    const scrollEndRef = useRef<HTMLDivElement>(null);
    const initialSent = useRef(false);
    const [typingAssistantMessageIds, setTypingAssistantMessageIds] = useState<string[]>([]);
    const typingBaselineCapturedRef = useRef(false);
    const knownMessageIdsRef = useRef<Set<string>>(new Set());
    const [failedMessages, setFailedMessages] = useState<Array<{message: string; timestamp: number}>>([]);
    const [initialLoading, setInitialLoading] = useState(true);
    const [publishModalOpen, setPublishModalOpen] = useState(false);

    const {
        generatedVirtualFiles,
        generatedVirtualFilePaths,
        builderCodePages,
        setBuilderCodePages,
        activeBuilderCodePage,
        builderStructureItems,
        setBuilderStructureItems,
        structureSnapshotPageRef,
        GENERATED_PAGE_PATH_PREFIX,
    } = useGeneratedCodePreview({ generatedPages, generatedPage }, viewMode);

    useEffect(() => {
        if (viewMode !== 'code') {
            return;
        }

        if (generatedVirtualFiles.length === 0) {
            if (selectedFile?.startsWith(GENERATED_PAGE_PATH_PREFIX)) {
                setSelectedFile(null);
                setSelectedFileMeta(null);
            }
            return;
        }

        if (selectedFile?.startsWith(GENERATED_PAGE_PATH_PREFIX) && !generatedVirtualFilePaths.has(selectedFile)) {
            setSelectedFile(null);
            setSelectedFileMeta(null);
        }
    }, [generatedVirtualFilePaths, generatedVirtualFiles, selectedFile, viewMode]);

    const structureDropdownRef = useRef<HTMLDivElement | null>(null);
    const structurePanelCloseTimeoutRef = useRef<number | null>(null);
    const [expandedStructureItemIds, setExpandedStructureItemIds] = useState<string[]>([]);
    const [draggingStructureItemId, setDraggingStructureItemId] = useState<string | null>(null);
    const [structureDropIndicator, setStructureDropIndicator] = useState<StructureDropIndicator | null>(null);

    const [pendingEdits, setPendingEdits] = useState<PendingEdit[]>([]);

    // Theme designer state
    const [isSavingTheme, setIsSavingTheme] = useState(false);
    const [appliedTheme, setAppliedTheme] = useState(project.theme_preset);
    const [captureThumbnailTrigger, setCaptureThumbnailTrigger] = useState(0);
    const visualPreviewUrl = project.cms_preview_url ?? null;

    const handleCodeFileSelect = useCallback((path: string, meta: CodeFileSelectionMeta) => {
        setSelectedFile(path);
        setSelectedFileMeta(meta);
    }, []);

    const isGlobalThemeElementMention = useCallback((element: ElementMention | PendingEdit['element']) => {
        const selector = String('selector' in element ? element.selector : element.cssSelector || '').toLowerCase();
        const tagName = String(element.tagName || '').toLowerCase();
        const elementId = String('elementId' in element ? (element.elementId ?? '') : '').toLowerCase();
        const classNames = Array.isArray(('classNames' in element ? element.classNames : []))
            ? ('classNames' in element ? element.classNames : []).map((value) => String(value).toLowerCase())
            : [];
        const parentTag = String('parentTagName' in element ? (element.parentTagName ?? '') : '').toLowerCase();

        const selectorSignals = [
            'webu_header_',
            'webu_footer_',
            '[data-webu-menu',
            'header',
            'footer',
            'nav',
            '.navbar',
            '.header',
            '.footer',
            '#header',
            '#footer',
        ];

        if (selectorSignals.some((signal) => selector.includes(signal))) {
            return true;
        }

        if (['header', 'footer', 'nav'].includes(tagName) || ['header', 'footer', 'nav'].includes(parentTag)) {
            return true;
        }

        if (elementId.includes('header') || elementId.includes('footer') || elementId.includes('nav')) {
            return true;
        }

        if (classNames.some((name) => /(^|[-_])(header|footer|navbar|menu)([-_]|$)/.test(name))) {
            return true;
        }

        return false;
    }, []);

    // Theme preview callback - passed to InspectPreview which has the iframe ref
    const applyThemeToPreview = useCallback((_presetId: string) => {
        // Theme application is handled internally by InspectPreview
        // This callback is kept for the ThemeDesigner onThemeSelect prop
    }, []);

    // Build credits tracking with refresh capability
    const { credits, refresh: refreshCredits } = useBuildCredits(buildCredits ?? FALLBACK_BUILD_CREDITS);

    const {
        messages,
        progress,
        isLoading,
        sendMessage,
        addMessage,
        cancelBuild,
        triggerBuild,
        retryStart,
        isBuildingPreview,
        startError,
    } = useBuilderChat(project.id, {
        pusherConfig,
        initialHistory: project.conversation_history,
        initialPreviewUrl: project.cms_preview_url ?? project.preview_url,
        // Pass initial reconnection state from server
        initialSessionId: project.build_session_id,
        initialCanReconnect: project.can_reconnect ?? false,
        onComplete: (event) => {
            refreshCredits(); // Refresh credits after build completes
            if (event.files_changed) {
                toast.success(t('Build complete! Files have been updated.'));
            } else {
                toast.success(t('Build complete!'));
            }
        },
        onError: (error) => {
            toast.error(error);
        },
        onBuildComplete: () => {
            setPreviewRefreshTrigger(Date.now());
        },
    });

    const handleRegenerateCodeFromSite = useCallback(async () => {
        setIsRegeneratingCode(true);
        try {
            const res = await axios.post<{ success?: boolean; error?: string }>(
                `/panel/projects/${project.id}/workspace/regenerate`
            );
            if (res.data?.success) {
                setFileRefreshTrigger((c) => c + 1);
                toast.success(t('Code regenerated from site. All pages and sections are in sync.'));
            } else {
                toast.error(res.data?.error ?? t('Failed to regenerate code'));
            }
        } catch (err: unknown) {
            const message = axios.isAxiosError(err) && err.response?.data?.error
                ? String(err.response.data.error)
                : t('Failed to regenerate code');
            toast.error(message);
        } finally {
            setIsRegeneratingCode(false);
        }
    }, [project.id, t]);

    const refreshWorkspaceAfterChange = useCallback(async () => {
        setFileRefreshTrigger((current) => current + 1);
        await triggerBuild();
    }, [triggerBuild]);

    const {
        analyze: analyzePageStructure,
        interpret,
        execute: executeChangeSet,
        runUnifiedEdit,
        isBusy: isAiSiteEditorBusy,
        isAnalyzing: isAgentAnalyzing,
        isInterpreting: isAgentInterpreting,
        isExecuting: isAgentExecuting,
        clearError: clearAiSiteEditorError,
    } = useAiSiteEditor(project.id);

    /** When set, user has seen "Planned changes" and can Confirm or Cancel before execute. */
    const [pendingAgentPlan, setPendingAgentPlan] = useState<{
        changeSet: ChangeSet;
        summary: string[];
        instruction: string;
        pageSlug: string;
        pageId?: number;
    } | null>(null);

    /** Last applied changes summary (session memory); sent to interpret so "make it shorter" etc. resolve. */
    const [lastAgentRecentEdits, setLastAgentRecentEdits] = useState<string>('');

    /** Brief "Updating preview..." then "Completed" after successful apply (spec: execution states) */
    const [agentPhaseUpdatingPreview, setAgentPhaseUpdatingPreview] = useState(false);
    const [agentPhaseCompleted, setAgentPhaseCompleted] = useState(false);
    const agentPhaseCompletedTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    useEffect(() => () => {
        if (agentPhaseCompletedTimeoutRef.current) clearTimeout(agentPhaseCompletedTimeoutRef.current);
    }, []);
    /** First 400ms of execute = "Preparing change...", then "Applying change..." */
    const [agentPhasePreparing, setAgentPhasePreparing] = useState(false);
    const agentExecuteStartRef = useRef<number | null>(null);
    useEffect(() => {
        if (isAgentExecuting) {
            if (agentExecuteStartRef.current === null) agentExecuteStartRef.current = Date.now();
            setAgentPhasePreparing(true);
        } else {
            agentExecuteStartRef.current = null;
            setAgentPhasePreparing(false);
        }
    }, [isAgentExecuting]);
    useEffect(() => {
        if (!isAgentExecuting || !agentPhasePreparing) return;
        const t = setTimeout(() => setAgentPhasePreparing(false), 400);
        return () => clearTimeout(t);
    }, [isAgentExecuting, agentPhasePreparing]);
    const agentPhaseLabel = agentPhaseCompleted
        ? t('Completed')
        : agentPhaseUpdatingPreview
            ? t('Updating preview...')
            : isAgentAnalyzing
                ? t('Analyzing...')
                : isAgentInterpreting
                    ? t('Understanding request...')
                    : isAgentExecuting
                        ? (agentPhasePreparing ? t('Planning changes...') : t('Applying changes...'))
                        : null;

    const [agentHighlightLocalId, setAgentHighlightLocalId] = useState<string | null>(null);
    const agentHighlightTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    /** Show pipeline in "Failed" state briefly after interpret/execute error (no silent disappear). */
    const [agentRunFailedAt, setAgentRunFailedAt] = useState<number | null>(null);
    const agentRunFailedTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    useEffect(() => {
        if (agentRunFailedAt == null) return;
        if (agentRunFailedTimeoutRef.current) clearTimeout(agentRunFailedTimeoutRef.current);
        agentRunFailedTimeoutRef.current = setTimeout(() => {
            setAgentRunFailedAt(null);
            agentRunFailedTimeoutRef.current = null;
        }, 2500);
        return () => { if (agentRunFailedTimeoutRef.current) clearTimeout(agentRunFailedTimeoutRef.current); };
    }, [agentRunFailedAt]);

    useEffect(() => {
        if (!agentHighlightLocalId) return;
        if (agentHighlightTimeoutRef.current) clearTimeout(agentHighlightTimeoutRef.current);
        agentHighlightTimeoutRef.current = setTimeout(() => {
            setAgentHighlightLocalId(null);
            agentHighlightTimeoutRef.current = null;
        }, 3000);
        return () => {
            if (agentHighlightTimeoutRef.current) clearTimeout(agentHighlightTimeoutRef.current);
        };
    }, [agentHighlightLocalId]);

    const effectivePreviewUrl = visualPreviewUrl || progress.previewUrl || null;
    // Chat owns the inspect workspace shell and coordinates the embedded sidebar/preview runtime.
    const {
        previewViewport,
        setPreviewViewport,
        previewInteractionState,
        setPreviewInteractionState,
        selectedBuilderTarget,
        selectBuilderTarget,
        clearBuilderSelection,
        isBuilderSidebarReady,
        markBuilderSidebarReady,
        isBuilderPreviewReady,
        markBuilderPreviewReady,
        builderPaneMode,
        setBuilderPaneMode,
        isSidebarVisible,
        isVisualBuilderOpen,
        isBuilderStructureOpen,
        pendingBuilderStructureMutation,
        setPendingBuilderStructureMutation,
        builderLibraryItems,
        groupedBuilderLibraryItems,
        activeLibraryItem,
        setActiveLibraryItem,
        isSavingBuilderDraft,
        setIsSavingBuilderDraft,
        selectedElementMention,
        activeBuilderPageIdentity,
        visibleBuilderStructureItems,
        effectiveSelectedBuilderSectionLocalId,
        effectiveSelectedPreviewSectionKey,
        applyPreviewLayoutOverrides,
        effectivePreviewUrlWithOverrides,
        visualBuilderSidebarUrl,
        builderSidebarFrameRef,
        postBuilderCommand,
        syncBuilderChangeSet,
        setStructurePanelOpen,
        handleBuilderSidebarFrameLoad,
        openVisualBuilder,
        handleVisualBuilderToggle,
        handleWorkspaceModeChange,
        handleSidebarToggle,
        preferPersistedStructureStateRef,
        justPlacedSectionRef,
    } = useBuilderWorkspace({
        projectId: project.id,
        viewMode,
        setViewMode,
        seededBuilderLibraryItems,
        activeBuilderCodePage,
        builderStructureItems,
        setBuilderStructureItems,
        setBuilderCodePages,
        structureSnapshotPageRef,
        effectivePreviewUrl,
        setPreviewRefreshTrigger,
        t,
    });

    useEffect(() => {
        setExpandedStructureItemIds((current) => current.filter((localId) => (
            builderStructureItems.some((item) => item.localId === localId)
        )));
    }, [builderStructureItems]);

    useEffect(() => {
        if (!effectiveSelectedBuilderSectionLocalId) {
            return;
        }

        setExpandedStructureItemIds((current) => (
            current.includes(effectiveSelectedBuilderSectionLocalId)
                ? current
                : [...current, effectiveSelectedBuilderSectionLocalId]
        ));
    }, [effectiveSelectedBuilderSectionLocalId]);

    const toggleStructureItemExpanded = useCallback((localId: string) => {
        setExpandedStructureItemIds((current) => (
            current.includes(localId)
                ? current.filter((itemId) => itemId !== localId)
                : [...current, localId]
        ));
    }, []);

    const visualBuilderLabel = t('Visual');
    const previewStatusLabel = isBuildingPreview
        ? t('Loading Live Preview...')
        : effectivePreviewUrl
            ? t('Live preview ready')
            : t('Waiting for your first build');
    const builderPreviewUrl = buildBuilderPreviewUrl(
        viewMode as 'preview' | 'inspect' | 'design',
        effectivePreviewUrl,
        effectivePreviewUrlWithOverrides,
    );
    const creditLabel = credits.isUnlimited
        ? t('Unlimited builds')
        : t(':count builds left', { count: credits.remaining });

    // Track if initial scroll has been done
    const initialScrollDone = useRef(false);
    const shouldStickChatToBottomRef = useRef(true);
    const lastAutoScrollStateRef = useRef({
        messageCount: 0,
        failedCount: 0,
        loading: false,
    });
    const getChatScrollViewport = useCallback((): HTMLElement | null => {
        return scrollEndRef.current?.closest('[data-slot="scroll-area-viewport"]') as HTMLElement | null;
    }, []);
    const scrollChatToBottom = useCallback((behavior: ScrollBehavior = 'auto') => {
        const viewport = getChatScrollViewport();
        if (viewport) {
            viewport.scrollTo({
                top: viewport.scrollHeight,
                behavior,
            });
            return;
        }

        scrollEndRef.current?.scrollIntoView({ behavior, block: 'end' });
    }, [getChatScrollViewport]);
    const scheduleScrollChatToBottom = useCallback((behavior: ScrollBehavior = 'auto', attempts = 6) => {
        if (typeof window === 'undefined') {
            scrollChatToBottom(behavior);
            return () => undefined;
        }

        let frameId = 0;
        let timeoutId: number | null = null;
        let cancelled = false;

        const runAttempt = (remaining: number) => {
            if (cancelled) {
                return;
            }

            scrollChatToBottom(behavior);

            if (remaining <= 1) {
                return;
            }

            frameId = window.requestAnimationFrame(() => {
                timeoutId = window.setTimeout(() => runAttempt(remaining - 1), 40);
            });
        };

        timeoutId = window.setTimeout(() => runAttempt(attempts), 0);

        return () => {
            cancelled = true;
            if (frameId) {
                window.cancelAnimationFrame(frameId);
            }
            if (timeoutId !== null) {
                window.clearTimeout(timeoutId);
            }
        };
    }, [scrollChatToBottom]);
    const syncChatStickiness = useCallback(() => {
        const viewport = getChatScrollViewport();
        if (!viewport) {
            shouldStickChatToBottomRef.current = true;
            return;
        }

        const distanceFromBottom = viewport.scrollHeight - viewport.clientHeight - viewport.scrollTop;
        shouldStickChatToBottomRef.current = distanceFromBottom <= 48;
    }, [getChatScrollViewport]);

    // Clear initial loading state after first render
    useEffect(() => {
        const timer = setTimeout(() => setInitialLoading(false), 100);
        return () => clearTimeout(timer);
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const viewport = getChatScrollViewport();
        if (!viewport) {
            return;
        }

        const handleScroll = () => {
            syncChatStickiness();
        };

        syncChatStickiness();
        viewport.addEventListener('scroll', handleScroll, { passive: true });

        return () => {
            viewport.removeEventListener('scroll', handleScroll);
        };
    }, [getChatScrollViewport, syncChatStickiness, viewMode, messages.length]);

    // Auto-scroll to bottom
    useEffect(() => {
        const nextState = {
            messageCount: messages.length,
            failedCount: failedMessages.length,
            loading: isLoading,
        };

        if (!initialScrollDone.current && (nextState.messageCount > 0 || nextState.failedCount > 0 || nextState.loading)) {
            const cancel = scheduleScrollChatToBottom('auto', 8);
            initialScrollDone.current = true;
            lastAutoScrollStateRef.current = nextState;
            shouldStickChatToBottomRef.current = true;
            return cancel;
        }

        const previousState = lastAutoScrollStateRef.current;
        lastAutoScrollStateRef.current = nextState;

        if (!initialScrollDone.current) {
            return;
        }

        const shouldScroll = nextState.messageCount > previousState.messageCount
            || nextState.failedCount > previousState.failedCount
            || (nextState.loading && !previousState.loading);

        if (shouldScroll && shouldStickChatToBottomRef.current) {
            return scheduleScrollChatToBottom('smooth', 4);
        }
    }, [failedMessages.length, isLoading, messages.length, scheduleScrollChatToBottom]);

    // Send initial message from project prompt (only for new projects with no history)
    useEffect(() => {
        if (project.initial_prompt && !initialSent.current && !project.has_history) {
            initialSent.current = true;
            sendMessage(project.initial_prompt);
        }
    }, [project.initial_prompt, project.has_history, sendMessage]);

    // Auto-rebuild preview for projects with history but no preview
    const autoRebuildTriggered = useRef(false);
    useEffect(() => {
        if (project.has_history && !project.preview_url && project.build_status !== 'building' && !autoRebuildTriggered.current) {
            autoRebuildTriggered.current = true;
            triggerBuild();
        }
    }, [project.has_history, project.preview_url, project.build_status, triggerBuild]);

    const handleSubmit = async (e?: React.FormEvent) => {
        e?.preventDefault();
        const derivedSelectedTarget = selectedBuilderTarget ?? (
            selectedElementMention
                ? resolveMentionBuilderTarget({
                    element: selectedElementMention,
                    builderStructureItems,
                    currentBreakpoint: previewViewport,
                    currentInteractionState: previewInteractionState,
                }).target
                : null
        );
        const pageAwareSelectedTarget = derivedSelectedTarget
            ? {
                ...derivedSelectedTarget,
                pageId: activeBuilderPageIdentity.pageId,
                pageSlug: activeBuilderPageIdentity.pageSlug,
                pageTitle: activeBuilderPageIdentity.pageTitle,
            }
            : null;
        const activeSelectedElement = editableTargetToMention(pageAwareSelectedTarget) ?? selectedElementMention;
        if ((!prompt.trim() && !activeSelectedElement) || isLoading) return;

        if (activeSelectedElement && !selectedTargetIsMappable(pageAwareSelectedTarget)) {
            const replyLang = detectReplyLanguage(prompt.trim(), appLocale);
            const content = replyLang === 'ka'
                ? 'არჩეული ელემენტი ჯერ builder parameter-ზე არ არის მიბმული, ამიტომ ჩატი ამ ელემენტს ზუსტად ვერ შეცვლის.'
                : 'The selected element is not mapped to a builder parameter yet, so chat cannot target it safely.';
            addMessage?.({
                id: `agent-unmapped-target-${Date.now()}`,
                type: 'assistant',
                content,
                timestamp: new Date(),
            });
            return;
        }

        const msg = prompt.trim();
        const replyLang = detectReplyLanguage(msg, appLocale);
        const commandLocale = resolveAiCommandLocale(msg, appLocale);
        const elementContext = activeSelectedElement ? {
            tagName: activeSelectedElement.tagName,
            selector: activeSelectedElement.selector,
            textPreview: activeSelectedElement.textPreview,
            sectionLocalId: activeSelectedElement.sectionLocalId ?? undefined,
            sectionKey: activeSelectedElement.sectionKey ?? undefined,
            componentType: pageAwareSelectedTarget?.componentType ?? undefined,
            componentPath: pageAwareSelectedTarget?.componentPath ?? pageAwareSelectedTarget?.path ?? undefined,
            parameterName: activeSelectedElement.parameterName ?? undefined,
            elementId: activeSelectedElement.elementId ?? undefined,
            editableFields: pageAwareSelectedTarget?.editableFields ?? undefined,
            variants: pageAwareSelectedTarget?.variants ?? undefined,
            allowedUpdates: pageAwareSelectedTarget?.allowedUpdates ?? undefined,
            currentBreakpoint: previewViewport,
            currentInteractionState: previewInteractionState,
            responsiveContext: pageAwareSelectedTarget?.responsiveContext ?? undefined,
        } : undefined;
        const selectedTargetContext = buildSelectedTargetContext(pageAwareSelectedTarget, {
            currentBreakpoint: previewViewport,
            currentInteractionState: previewInteractionState,
        });
        const hasScopedSelectedTarget = Boolean(
            selectedTargetContext
            && (
                selectedTargetContext.section_id
                || selectedTargetContext.element_id
                || selectedTargetContext.component_path
            )
        );
        const selectedElementPayload =
            pageAwareSelectedTarget?.sectionLocalId && pageAwareSelectedTarget.path && pageAwareSelectedTarget.elementId
                ? {
                    section_id: pageAwareSelectedTarget.sectionLocalId,
                    parameter_path: pageAwareSelectedTarget.path,
                    element_id: pageAwareSelectedTarget.elementId,
                    page_id: activeBuilderPageIdentity.pageId,
                    page_slug: activeBuilderPageIdentity.pageSlug,
                    component_path: pageAwareSelectedTarget.componentPath ?? pageAwareSelectedTarget.path ?? null,
                    component_type: pageAwareSelectedTarget.componentType ?? null,
                    component_name: pageAwareSelectedTarget.componentName ?? null,
                    editable_fields: pageAwareSelectedTarget.editableFields ?? [],
                    variants: pageAwareSelectedTarget.variants ?? null,
                    allowed_updates: pageAwareSelectedTarget.allowedUpdates ?? null,
                    current_breakpoint: previewViewport,
                    current_interaction_state: previewInteractionState,
                    responsive_context: pageAwareSelectedTarget.responsiveContext ?? null,
                }
                : null;
        const scopedMsg = elementContext
            ? `[PAGE_CONTENT_SCOPE] Update only page content elements for the current page. Do not modify global theme, header, footer, menus, or layout structure.\n${msg}`
            : msg;

        setPrompt('');

        // Part 10 — Design upgrade: "Improve hero" → replace variant + regenerate content + adjust spacing.
        const designUpgradeKind = getDesignUpgradeSectionKind(msg);
        if (designUpgradeKind) {
            addMessage?.({ id: `user-${Date.now()}`, type: 'user', content: msg, timestamp: new Date() });
            const currentPage = activeBuilderCodePage;
            if (!currentPage || !currentPage.sections.length) {
                const replyLang = detectReplyLanguage(msg, appLocale);
                const noSections = replyLang === 'ka'
                    ? 'ამ გვერდზე სექციები არ არის. დაამატე სექციები.'
                    : 'This page has no sections. Add sections first.';
                addMessage?.({
                    id: `agent-design-upgrade-empty-${Date.now()}`,
                    type: 'assistant',
                    content: noSections,
                    timestamp: new Date(),
                });
                axios.post(`/panel/projects/${project.id}/chat-append`, {
                    entries: [{ role: 'user', content: msg }, { role: 'assistant', content: noSections }],
                }).catch(() => {});
                return;
            }
            const fetchContent = async (sectionType: ContentSectionType): Promise<Record<string, unknown>> => {
                const res = await axios.post<{ success: boolean; content?: Record<string, unknown>; error?: string }>(
                    `/panel/projects/${project.id}/generate-section-content`,
                    { section_type: sectionType, project_type: 'landing', language: commandLocale }
                );
                if (res.data?.success && res.data?.content) return res.data.content;
                throw new Error(res.data?.error ?? 'Failed to generate content');
            };
            runDesignUpgrade(currentPage.sections, designUpgradeKind, fetchContent, { regenerateContent: true })
                .then((result) => {
                    setBuilderCodePages((pages) => {
                        const next = [...pages];
                        const targetIndex = next.findIndex((page) => (
                            builderBridgePagesMatch(getWorkspaceBuilderPageIdentity(page), activeBuilderPageIdentity)
                        ));
                        if (targetIndex >= 0) {
                            next[targetIndex] = { ...next[targetIndex], sections: result.sections };
                        }
                        return next;
                    });
                    const replyLang = detectReplyLanguage(msg, appLocale);
                    const intro = replyLang === 'ka'
                        ? 'ჰეროს გავაუმჯობესე: ვარიანტი, კონტენტი და ინტერვალები. '
                        : 'I improved the section: replaced variant, regenerated content, and adjusted spacing. ';
                    const summaryLine = result.summary.length ? result.summary.join('; ') : 'Done.';
                    const assistantContent = intro + summaryLine;
                    addMessage?.({
                        id: `agent-design-upgrade-${Date.now()}`,
                        type: 'assistant',
                        content: assistantContent,
                        timestamp: new Date(),
                    });
                    setPreviewRefreshTrigger((prev) => (prev ?? 0) + 1);
                    axios.post(`/panel/projects/${project.id}/chat-append`, {
                        entries: [{ role: 'user', content: msg }, { role: 'assistant', content: assistantContent }],
                    }).catch(() => {});
                })
                .catch(() => {
                    const replyLang = detectReplyLanguage(msg, appLocale);
                    const errMsg = replyLang === 'ka'
                        ? 'კონტენტის გენერაცია ვერ მოხდა. სცადე თავიდან.'
                        : 'Content generation failed. Try again.';
                    addMessage?.({
                        id: `agent-design-upgrade-err-${Date.now()}`,
                        type: 'assistant',
                        content: errMsg,
                        timestamp: new Date(),
                    });
                    axios.post(`/panel/projects/${project.id}/chat-append`, {
                        entries: [{ role: 'user', content: msg }, { role: 'assistant', content: errMsg }],
                    }).catch(() => {});
                });
            return;
        }

        // Part 9 — Chat AI improvements: "Improve this page", "Optimize layout", etc. Run analyzer + optimizer locally.
        if (isImprovementCommand(msg)) {
            addMessage?.({ id: `user-${Date.now()}`, type: 'user', content: msg, timestamp: new Date() });
            const currentPage = activeBuilderCodePage;
            if (!currentPage || !currentPage.sections.length) {
                const replyLang = detectReplyLanguage(msg, appLocale);
                const noSections = replyLang === 'ka'
                    ? 'ამ გვერდზე სექციები არ არის. დაამატე სექციები, რომ AI-მ შეგვიძლის ოპტიმიზაცია.'
                    : 'This page has no sections. Add sections to get AI improvement suggestions.';
                addMessage?.({
                    id: `agent-improve-empty-${Date.now()}`,
                    type: 'assistant',
                    content: noSections,
                    timestamp: new Date(),
                });
                axios.post(`/panel/projects/${project.id}/chat-append`, {
                    entries: [{ role: 'user', content: msg }, { role: 'assistant', content: noSections }],
                }).catch(() => {});
                return;
            }
            const report = runChatImprovement(
                currentPage.sections.map((s) => ({ localId: s.localId, type: s.type, props: s.props }))
            );
            const newSections = applyOptimizationStepsToSections(currentPage.sections, report.transformations);
            setBuilderCodePages((pages) => {
                const next = [...pages];
                const targetIndex = next.findIndex((page) => (
                    builderBridgePagesMatch(getWorkspaceBuilderPageIdentity(page), activeBuilderPageIdentity)
                ));
                if (targetIndex >= 0) {
                    next[targetIndex] = { ...next[targetIndex], sections: newSections };
                }
                return next;
            });
            const appliedCount = report.transformations.length;
            const summary = formatImprovementSummary(report.transformations, appliedCount);
            const replyLang = detectReplyLanguage(msg, appLocale);
            const intro = replyLang === 'ka' ? 'შევაფასე გვერდი და ოპტიმიზატორი გავუშვი. ' : 'I ran the analyzer and optimizer. ';
            const assistantContent = intro + summary;
            addMessage?.({
                id: `agent-improve-${Date.now()}`,
                type: 'assistant',
                content: assistantContent,
                timestamp: new Date(),
            });
            setPreviewRefreshTrigger((prev) => (prev ?? 0) + 1);
            axios.post(`/panel/projects/${project.id}/chat-append`, {
                entries: [{ role: 'user', content: msg }, { role: 'assistant', content: assistantContent }],
            }).catch(() => {});
            return;
        }

        // Normal chat = real AI execution on the site (analyze → interpret → execute), then show real result. No fake "I've applied your change".
        if (addMessage) {
            clearAiSiteEditorError();
            setAgentRunFailedAt(null);

            const tryAiProjectEdit = async (options: { silentFailure?: boolean } = {}): Promise<boolean> => {
                const { silentFailure = false } = options;
                try {
                    const res = await axios.post<{
                        success: boolean;
                        summary: string;
                        changes: Array<{ path: string; op: string }>;
                        diagnostic_log?: string[];
                        error?: string;
                        no_change_reason?: string;
                        files_changed?: boolean;
                    }>(`/panel/projects/${project.id}/ai-project-edit`, {
                        message: msg,
                        ...(selectedElementPayload ? { selected_element: selectedElementPayload } : {}),
                    });
                    const data = res.data;
                    const hasFileChanges = Array.isArray(data.changes) && data.changes.length > 0;
                    const replyLang = detectReplyLanguage(msg, appLocale);
                    const strings = RESPONSE_STRINGS[replyLang];
                    if (!data.success) {
                        if (silentFailure) {
                            return false;
                        }
                        const errorText = replyLang === 'ka'
                            ? strings.requestCouldNotBeApplied
                            : (data.error || data.no_change_reason || strings.requestCouldNotBeApplied);
                        addMessage({
                            id: `agent-project-edit-fail-${Date.now()}`,
                            type: 'assistant',
                            content: errorText,
                            timestamp: new Date(),
                            diagnosticLog: data.diagnostic_log?.length ? data.diagnostic_log : undefined,
                        });
                        axios.post(`/panel/projects/${project.id}/chat-append`, {
                            entries: [{ role: 'user', content: msg }, { role: 'assistant', content: errorText }],
                        }).catch(() => {});
                        return true;
                    }
                    if (silentFailure && !hasFileChanges) {
                        return false;
                    }
                    const actionLog = hasFileChanges
                        ? data.changes.map((c) => `${c.op}: ${c.path}`)
                        : undefined;
                    const summaryNote = hasFileChanges
                        ? (data.summary || strings.updatedProjectSummary)
                        : (data.summary || (data.no_change_reason ? `${strings.noChangesApplied} ${data.no_change_reason}` : strings.noChangesApplied));
                    const content = buildDetailedAssistantMessage(
                        hasFileChanges ? strings.doneApplied : strings.noChangesApplied,
                        strings.changes,
                        actionLog,
                        summaryNote,
                    );
                    addMessage({
                        id: `agent-project-edit-${Date.now()}`,
                        type: 'assistant',
                        content,
                        timestamp: new Date(),
                        actionLog,
                        diagnosticLog: data.diagnostic_log?.length ? data.diagnostic_log : undefined,
                    });
                    if (data.files_changed) {
                        await refreshWorkspaceAfterChange();
                    }
                    axios.post(`/panel/projects/${project.id}/chat-append`, {
                        entries: [{ role: 'user', content: msg }, { role: 'assistant', content }],
                    }).catch(() => {});
                    return true;
                } catch (err: unknown) {
                    if (silentFailure) {
                        return false;
                    }
                    const replyLang = detectReplyLanguage(msg, appLocale);
                    const strings = RESPONSE_STRINGS[replyLang];
                    const projectEditErrorData = axios.isAxiosError(err) ? err.response?.data as { error?: string; diagnostic_log?: string[] } | undefined : undefined;
                    const errMsg = replyLang === 'ka'
                        ? (axios.isAxiosError(err) && err.response?.status === 422
                            ? strings.requestCouldNotBeCompleted
                            : strings.projectEditRequestFailed)
                        : (projectEditErrorData?.error
                            ? String(projectEditErrorData.error)
                            : axios.isAxiosError(err) && err.response?.status === 422
                                ? projectEditErrorData?.error || strings.requestCouldNotBeCompleted
                                : strings.projectEditRequestFailed);
                    addMessage({
                        id: `agent-project-edit-error-${Date.now()}`,
                        type: 'assistant',
                        content: errMsg,
                        timestamp: new Date(),
                        diagnosticLog: Array.isArray(projectEditErrorData?.diagnostic_log) ? projectEditErrorData?.diagnostic_log : undefined,
                    });
                    axios.post(`/panel/projects/${project.id}/chat-append`, {
                        entries: [{ role: 'user', content: msg }, { role: 'assistant', content: errMsg }],
                    }).catch(() => {});
                    return true;
                }
            };

            addMessage({ id: `user-${Date.now()}`, type: 'user', content: msg, timestamp: new Date() });
            const replyLang = detectReplyLanguage(msg, appLocale);
            if (isConversationalMessage(msg)) {
                const strings = RESPONSE_STRINGS[replyLang];
                const isHere = /აქ\s+ხარ|are you there|anyone there/i.test(msg.trim());
                const content = isHere ? strings.hereReply : strings.greetingReply;
                addMessage({
                    id: `agent-conversational-${Date.now()}`,
                    type: 'assistant',
                    content,
                    timestamp: new Date(),
                });
                axios.post(`/panel/projects/${project.id}/chat-append`, {
                    entries: [{ role: 'user', content: msg }, { role: 'assistant', content }],
                }).catch(() => {});
                return;
            }
            const clarificationPrompt = getClarificationPrompt(msg, replyLang);
            if (clarificationPrompt) {
                addMessage({
                    id: `agent-clarify-${Date.now()}`,
                    type: 'assistant',
                    content: clarificationPrompt,
                    timestamp: new Date(),
                });
                axios.post(`/panel/projects/${project.id}/chat-append`, {
                    entries: [{ role: 'user', content: msg }, { role: 'assistant', content: clarificationPrompt }],
                }).catch(() => {});
                return;
            }
            const preferProjectEdit = shouldPreferProjectEdit(msg, {
                hasSelectedElement: selectedElementPayload !== null,
                viewMode,
            });
            if (preferProjectEdit) {
                const handledByProjectEdit = await tryAiProjectEdit({ silentFailure: true });
                if (handledByProjectEdit) {
                    return;
                }
            }
            const pageSlug = activeBuilderPageIdentity.pageSlug ?? 'home';
            const pageId = activeBuilderPageIdentity.pageId ?? undefined;
            const result = await runUnifiedEdit(msg, {
                page_slug: pageSlug,
                page_id: pageId,
                locale: commandLocale,
                selected_target: selectedTargetContext,
                recent_edits: lastAgentRecentEdits,
            });
            if (result.success) {
                const actionLog = result.action_log ?? result.summary ?? [];
                const replyStr = RESPONSE_STRINGS[replyLang];
                const changeSetForScope = result.change_set ?? { operations: [], summary: result.summary };
                const scopeLabel = resolveChangeSetScopeLabel(changeSetForScope, pageSlug, {
                    homePage: replyLang === 'ka' ? 'მთავარი გვერდი' : 'Home page',
                    homePageOnly: replyLang === 'ka' ? 'მხოლოდ მთავარი გვერდი' : t('Home page only'),
                    page: (slug) => replyLang === 'ka' ? `გვერდი: ${slug}` : t('Page: :page', { page: slug }),
                    siteWide: replyLang === 'ka' ? 'ცვლილება მთელ საიტზე' : 'Site-wide changes',
                    siteWideHeader: replyLang === 'ka' ? 'ჰედერი მთელ საიტზე' : 'Site-wide header',
                    siteWideFooter: replyLang === 'ka' ? 'ფუტერი მთელ საიტზე' : 'Site-wide footer',
                    siteWideTheme: replyLang === 'ka' ? 'თემა მთელ საიტზე' : 'Site-wide theme',
                });
                const builderSyncableChangeSet = getBuilderSyncableChangeSet(changeSetForScope);
                const hasUnsyncedOps = changeSetHasUnsyncedOperations(changeSetForScope);
                const previewOverrides = extractPreviewLayoutOverrides(changeSetForScope);
                const syncedBuilderChangeSet = builderSyncableChangeSet
                    ? syncBuilderChangeSet(builderSyncableChangeSet as ChangeSet)
                    : false;
                applyPreviewLayoutOverrides(previewOverrides);
                const assistantContent = buildDetailedAssistantMessage(
                    replyStr.doneApplied,
                    replyStr.changes,
                    actionLog,
                    scopeLabel,
                );
                addMessage({
                    id: `agent-${Date.now()}`,
                    type: 'assistant',
                    content: assistantContent,
                    timestamp: new Date(),
                    actionLog: actionLog.length ? actionLog : undefined,
                    scope: scopeLabel ?? undefined,
                    appliedChanges: result.applied_changes,
                    diagnosticLog: result.diagnostic_log?.length ? result.diagnostic_log : undefined,
                });
                setLastAgentRecentEdits((result.action_log?.join('; ') ?? result.summary?.join('; ') ?? '').slice(0, 500));
                if (!syncedBuilderChangeSet || hasUnsyncedOps) {
                    setPreviewRefreshTrigger(Date.now());
                }
                if (result.highlight_section_ids?.length) setAgentHighlightLocalId(result.highlight_section_ids[0] ?? null);
                axios.post(`/panel/projects/${project.id}/chat-append`, {
                    entries: [{ role: 'user', content: msg }, { role: 'assistant', content: assistantContent }],
                }).catch(() => {});
                return;
            }
            if (preferProjectEdit) {
                const handled = await tryAiProjectEdit();
                if (handled) return;
            }
            const errorContent = getAgentErrorMessage(result.error, result.error_code, detectReplyLanguage(msg));
            addMessage({
                id: `agent-fail-${Date.now()}`,
                type: 'assistant',
                content: errorContent,
                timestamp: new Date(),
                diagnosticLog: result.diagnostic_log?.length ? result.diagnostic_log : undefined,
            });
            setAgentRunFailedAt(Date.now());
            axios.post(`/panel/projects/${project.id}/chat-append`, {
                entries: [{ role: 'user', content: msg }, { role: 'assistant', content: errorContent }],
            }).catch(() => {});
            return;
        }

        if (hasScopedSelectedTarget) {
            const errorContent = getAgentErrorMessage(undefined, 'selected_target_scope_violation', detectReplyLanguage(msg));
            const appendFallbackMessage = addMessage as ((message: ChatMessage) => void) | undefined;
            appendFallbackMessage?.({
                    id: `agent-target-fallback-blocked-${Date.now()}`,
                    type: 'assistant',
                    content: errorContent,
                    timestamp: new Date(),
                });
            setAgentRunFailedAt(Date.now());
            return;
        }

        // Builder path (element context or when AI site editor / patch did not apply)
        try {
            const healthResponse = await axios.get(`/builder/projects/${project.id}/health`);
            if (!healthResponse.data.online) {
                setFailedMessages(prev => [...prev, { message: scopedMsg, timestamp: Date.now() }]);
                toast.error(t('Builder is offline. Start it with "composer dev" or run "bash scripts/start-local-builder.sh".'));
                return;
            }
        } catch {
            setFailedMessages(prev => [...prev, { message: scopedMsg, timestamp: Date.now() }]);
            toast.error(t('Builder is offline. Start it with "composer dev" or run "bash scripts/start-local-builder.sh".'));
            return;
        }

        await sendMessage(scopedMsg, { elementContext });
    };

    const visibleMessages = messages.filter((msg) => msg.type === 'user' || msg.type === 'assistant');
    useEffect(() => {
        if (!typingBaselineCapturedRef.current) {
            if (initialLoading) {
                return;
            }

            knownMessageIdsRef.current = new Set(messages.map((message) => message.id));
            typingBaselineCapturedRef.current = true;
            return;
        }

        const nextTypingIds: string[] = [];
        messages.forEach((message) => {
            if (knownMessageIdsRef.current.has(message.id)) {
                return;
            }

            knownMessageIdsRef.current.add(message.id);
            if (message.type === 'assistant' && message.content.trim() !== '') {
                nextTypingIds.push(message.id);
            }
        });

        if (nextTypingIds.length === 0) {
            return;
        }

        setTypingAssistantMessageIds((current) => {
            const merged = new Set(current);
            nextTypingIds.forEach((id) => merged.add(id));
            return Array.from(merged);
        });
    }, [initialLoading, messages]);

    const handleAssistantTypingComplete = useCallback((messageId: string) => {
        setTypingAssistantMessageIds((current) => current.filter((id) => id !== messageId));
    }, []);

    const previewTarget = project.subdomain ? `https://${project.subdomain}.${baseDomain}` : effectivePreviewUrl;
    const toolbarTabs: Array<{ key: ViewMode; label: string; icon: LucideIcon }> = [
        { key: 'preview', label: t('Preview'), icon: Globe },
        { key: 'inspect', label: visualBuilderLabel, icon: Cloud },
        { key: 'design', label: t('Theme'), icon: Palette },
        { key: 'code', label: t('Code'), icon: Code },
        { key: 'settings', label: t('Settings'), icon: BarChart3 },
    ];
    const brandImageUrl = appSettings.site_favicon
        ? `/storage/${appSettings.site_favicon}`
        : appSettings.site_logo
            ? `/storage/${appSettings.site_logo}`
            : null;
    const normalizedProjectName = project.name
        .replace(/\s+\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}$/, '')
        .trim();
    const workspaceProjectLabel = normalizedProjectName.length > 24
        ? `${normalizedProjectName.slice(0, 21).trimEnd()}...`
        : normalizedProjectName;
    const workspaceDomainLabel = (() => {
        if (project.subdomain) {
            return `${project.subdomain}.${baseDomain}`;
        }

        if (!previewTarget) {
            return previewStatusLabel;
        }

        try {
            const url = previewTarget.startsWith('http')
                ? new URL(previewTarget)
                : new URL(previewTarget, 'http://localhost');
            return url.host || previewStatusLabel;
        } catch {
            return previewStatusLabel;
        }
    })();
    const workspacePlanLabel = credits.usingOwnKey
        ? t('Own key')
        : credits.isUnlimited
            ? t('Unlimited')
            : t('Plan');
    const creditSummaryLabel = credits.isUnlimited
        ? t('Unlimited')
        : t(':count left', { count: credits.remaining });
    const creditDetailLabel = credits.usingOwnKey
        ? t('Using your own API key')
        : credits.isUnlimited
            ? t('Unlimited builds on your current plan')
            : t('Monthly limit: :count builds', { count: credits.monthlyLimit });
    const creditUsagePercent = credits.isUnlimited || credits.monthlyLimit <= 0
        ? 100
        : Math.max(6, Math.min(100, (credits.remaining / credits.monthlyLimit) * 100));
    const handleShareWorkspace = useCallback(async () => {
        const shareUrl = previewTarget ?? (typeof window !== 'undefined' ? `${window.location.origin}/app/${project.id}` : `/app/${project.id}`);

        try {
            if (typeof navigator === 'undefined' || !navigator.clipboard) {
                throw new Error('clipboard-unavailable');
            }
            await navigator.clipboard.writeText(shareUrl);
            toast.success(t('Link copied to clipboard'));
        } catch {
            toast.error(t('Could not copy link'));
        }
    }, [previewTarget, project.id, t]);

    const handleGoBack = useCallback(() => {
        router.visit('/projects');
    }, []);
    const handleOpenPreviewSite = useCallback(() => {
        if (typeof window === 'undefined' || !previewTarget) {
            return;
        }

        window.open(previewTarget, '_blank', 'noopener,noreferrer');
    }, [previewTarget]);

    const handleUpgrade = useCallback(() => {
        if (typeof window === 'undefined') {
            return;
        }

        window.location.assign('/billing/plans');
    }, []);

    // Element selection handler for inspect mode
    const handleElementSelect = useCallback((element: ElementMention | null) => {
        if (!element) {
            setActiveLibraryItem(null);
            clearBuilderSelection();

            if (viewMode === 'inspect') {
                setBuilderPaneMode('elements');
                postBuilderCommand({
                    type: 'builder:set-sidebar-mode',
                    mode: 'elements',
                });
                postBuilderCommand({
                    type: 'builder:clear-selected-section',
                });
            }

            return;
        }

        inspectLog('handleElementSelect', element);
        const {
            resolvedLocalId,
            resolvedSectionKey,
            target: nextTarget,
        } = resolveMentionBuilderTarget({
            element,
            builderStructureItems,
            currentBreakpoint: previewViewport,
            currentInteractionState: previewInteractionState,
        });
        const pageAwareTarget = nextTarget
            ? {
                ...nextTarget,
                pageId: activeBuilderPageIdentity.pageId,
                pageSlug: activeBuilderPageIdentity.pageSlug,
                pageTitle: activeBuilderPageIdentity.pageTitle,
            }
            : null;
        selectBuilderTarget(pageAwareTarget);

        if (viewMode !== 'inspect') {
            return;
        }

        setActiveLibraryItem(null);
        setBuilderPaneMode('settings');
        postBuilderCommand({
            type: 'builder:set-sidebar-mode',
            mode: 'settings',
        });
        const nextSelectionPayload = buildCanonicalBridgeSelectedTargetPayload({
            pageIdentity: activeBuilderPageIdentity,
            target: pageAwareTarget,
            fallback: resolvedLocalId || resolvedSectionKey
                ? {
                    sectionLocalId: resolvedLocalId ?? null,
                    sectionKey: resolvedSectionKey ?? null,
                    componentType: resolvedSectionKey ?? null,
                }
                : null,
            currentBreakpoint: previewViewport,
            currentInteractionState: previewInteractionState,
        });

        if (nextSelectionPayload) {
            postBuilderCommand({
                type: 'builder:set-selected-target',
                ...nextSelectionPayload,
            });
        }
    }, [activeBuilderPageIdentity.pageId, activeBuilderPageIdentity.pageSlug, activeBuilderPageIdentity.pageTitle, builderStructureItems, clearBuilderSelection, postBuilderCommand, previewInteractionState, previewViewport, selectBuilderTarget, setActiveLibraryItem, viewMode]);

    // Handler for inline edits from inspect mode
    const handleElementEdit = useCallback((edit: PendingEdit) => {
        if (isGlobalThemeElementMention(edit.element)) {
            toast.info(t('Header / Footer / Menu are global theme elements. Use Builder settings to edit them.'));
            return;
        }
        setPendingEdits(prev => {
            const existingIndex = prev.findIndex(
                e => e.element.cssSelector === edit.element.cssSelector && e.field === edit.field
            );
            if (existingIndex >= 0) {
                const updated = [...prev];
                updated[existingIndex] = edit;
                return updated;
            }
            return [...prev, edit];
        });
    }, [isGlobalThemeElementMention, t]);

    const handleSaveAllEdits = useCallback(async () => {
        if (pendingEdits.length === 0) return;

        const editablePageEdits = pendingEdits.filter((edit) => !isGlobalThemeElementMention(edit.element));
        const blockedGlobalEdits = pendingEdits.length - editablePageEdits.length;

        if (blockedGlobalEdits > 0) {
            toast.info(t('Global theme edits were skipped. AI can change only page content blocks from chat.'));
        }

        if (editablePageEdits.length === 0) {
            return;
        }

        const editLines = editablePageEdits.map((edit, i) => {
            if (edit.field === 'text') {
                return `${i + 1}. <${edit.element.tagName}${edit.element.cssSelector}>: "${edit.originalValue}" → "${edit.newValue}"`;
            }
            return `${i + 1}. <${edit.element.tagName}> ${edit.field}: "${edit.originalValue}" → "${edit.newValue}"`;
        }).join('\n');

        const message = `[BATCH_EDIT] Update page content elements only (do not modify global theme, header, footer, menus, or layout structure):\n${editLines}`;
        await sendMessage(message);
        setPendingEdits((prev) => prev.filter((edit) => isGlobalThemeElementMention(edit.element)));
    }, [isGlobalThemeElementMention, pendingEdits, sendMessage, t]);

    const handleDiscardAllEdits = useCallback(() => {
        setPendingEdits([]);
    }, []);

    const handleRemoveEdit = useCallback((id: string) => {
        setPendingEdits(prev => prev.filter(e => e.id !== id));
    }, []);

    const handleBuilderShowElements = useCallback(() => {
        setBuilderPaneMode('elements');
        setActiveLibraryItem(null);
        clearBuilderSelection();
        postBuilderCommand({
            type: 'builder:set-sidebar-mode',
            mode: 'elements',
        });
        postBuilderCommand({
            type: 'builder:clear-selected-section',
        });
    }, [clearBuilderSelection, postBuilderCommand]);

    const handleLibraryItemActivate = useCallback((item: BuilderLibraryItem) => {
        setBuilderPaneMode('elements');
        setActiveLibraryItem((current) => current?.key === item.key ? null : item);
    }, []);

    const handleLibraryItemPlace = useCallback((sectionKey: string, target: ElementMention | null) => {
        if (pendingBuilderStructureMutation) {
            return;
        }

        const requestId = createBuilderBridgeRequestId('builder-add-section');
        const sectionLocalId = createBuilderBridgeRequestId('builder-section');
        const normalizedSectionKey = sectionKey.trim().toLowerCase();
        const matchingLibraryItem = builderLibraryItems.find((item) => item.key.trim().toLowerCase() === normalizedSectionKey) ?? null;
        inspectLog('handleLibraryItemPlace', { sectionKey, target: target ? { sectionLocalId: target.sectionLocalId, placement: target.placement } : null });
        setPendingBuilderStructureMutation({
            requestId,
            mutation: 'add-section',
            previewItems: buildOptimisticInsertedStructureItems(builderStructureItems, {
                localId: sectionLocalId,
                sectionKey,
                label: matchingLibraryItem?.label ?? sectionKey,
                previewText: '',
                props: {},
            }, {
                afterSectionLocalId: target?.sectionLocalId ?? null,
                placement: target?.placement ?? null,
            }),
            selectionSnapshot: createPendingBuilderSelectionSnapshot({
                sectionLocalId: effectiveSelectedBuilderSectionLocalId,
                sectionKey: effectiveSelectedPreviewSectionKey,
                target: selectedBuilderTarget,
            }),
        });
        postBuilderCommand({
            type: 'builder:add-section-by-key',
            requestId,
            sectionKey,
            sectionLocalId,
            afterSectionLocalId: target?.sectionLocalId ?? null,
            targetSectionKey: target?.sectionKey ?? null,
            placement: target?.placement ?? null,
        });
        setActiveLibraryItem(null);
        // Keep sidebar on component list; do not switch to settings so layout/design stays stable
        justPlacedSectionRef.current = true;
        if (typeof window !== 'undefined') {
            window.setTimeout(() => {
                justPlacedSectionRef.current = false;
            }, 600);
        }
    }, [builderLibraryItems, builderStructureItems, effectiveSelectedBuilderSectionLocalId, effectiveSelectedPreviewSectionKey, pendingBuilderStructureMutation, postBuilderCommand, selectedBuilderTarget]);

    const handleBuilderViewportChange = useCallback((viewport: PreviewViewport) => {
        setPreviewViewport(viewport);
        postBuilderCommand({
            type: 'builder:set-viewport',
            viewport,
        });
    }, [postBuilderCommand]);

    const handleBuilderRefresh = useCallback(() => {
        setPreviewRefreshTrigger(Date.now());
    }, []);

    const handleBuilderSaveDraft = useCallback(() => {
        if (isSavingBuilderDraft) {
            return;
        }

        setIsSavingBuilderDraft(true);
        postBuilderCommand({
            type: 'builder:save-draft',
        });
    }, [isSavingBuilderDraft, postBuilderCommand]);

    const headerSaveAction = useMemo(() => {
        if (viewMode === 'inspect' || viewMode === 'preview' || viewMode === 'design') {
            return {
                onClick: handleBuilderSaveDraft,
                disabled: isSavingBuilderDraft,
                busy: isSavingBuilderDraft,
                title: t('Save draft'),
            };
        }

        if (viewMode === 'code') {
            return {
                onClick: () => codeEditorRef.current?.save(),
                disabled: false,
                busy: false,
                title: t('Save'),
            };
        }

        return null;
    }, [handleBuilderSaveDraft, isSavingBuilderDraft, t, viewMode]);

    const handleBuilderStructureToggle = useCallback(() => {
        preferPersistedStructureStateRef.current = false;
        setStructurePanelOpen(!isBuilderStructureOpen);
    }, [isBuilderStructureOpen, setStructurePanelOpen]);

    const cancelStructurePanelClose = useCallback(() => {
        if (typeof window === 'undefined' || structurePanelCloseTimeoutRef.current === null) {
            return;
        }

        window.clearTimeout(structurePanelCloseTimeoutRef.current);
        structurePanelCloseTimeoutRef.current = null;
    }, []);

    const scheduleStructurePanelClose = useCallback(() => {
        if (typeof window === 'undefined' || !isBuilderStructureOpen) {
            return;
        }

        cancelStructurePanelClose();
        structurePanelCloseTimeoutRef.current = window.setTimeout(() => {
            setStructurePanelOpen(false);
            structurePanelCloseTimeoutRef.current = null;
        }, 120);
    }, [cancelStructurePanelClose, isBuilderStructureOpen, setStructurePanelOpen]);

    useEffect(() => {
        return () => {
            if (typeof window !== 'undefined' && structurePanelCloseTimeoutRef.current !== null) {
                window.clearTimeout(structurePanelCloseTimeoutRef.current);
            }
        };
    }, []);

    useEffect(() => {
        if (!isBuilderStructureOpen || typeof window === 'undefined') {
            return;
        }

        const handlePointerDown = (event: MouseEvent) => {
            const target = event.target as Node | null;
            if (target && structureDropdownRef.current?.contains(target)) {
                cancelStructurePanelClose();
                return;
            }

            setStructurePanelOpen(false);
        };

        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setStructurePanelOpen(false);
            }
        };

        window.addEventListener('mousedown', handlePointerDown);
        window.addEventListener('keydown', handleKeyDown);

        return () => {
            window.removeEventListener('mousedown', handlePointerDown);
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [cancelStructurePanelClose, isBuilderStructureOpen, setStructurePanelOpen]);

    const handleStructureSectionSelect = useCallback((item: BuilderStructureItem) => {
        const selection = buildStructureItemSelection(item);
        const nextTarget = {
            ...selection.target,
            pageId: activeBuilderPageIdentity.pageId,
            pageSlug: activeBuilderPageIdentity.pageSlug,
            pageTitle: activeBuilderPageIdentity.pageTitle,
        };
        const nextMention = editableTargetToMention(nextTarget);
        setExpandedStructureItemIds((current) => (
            current.includes(item.localId)
                ? current
                : [...current, item.localId]
        ));
        setActiveLibraryItem(null);
        setBuilderPaneMode('settings');
        selectBuilderTarget(nextTarget);
        postBuilderCommand({
            type: 'builder:set-sidebar-mode',
            mode: 'settings',
        });
        postBuilderCommand({
            type: 'builder:set-selected-target',
            ...selection.payload,
        });
    }, [
        activeBuilderPageIdentity.pageId,
        activeBuilderPageIdentity.pageSlug,
        activeBuilderPageIdentity.pageTitle,
        postBuilderCommand,
        setActiveLibraryItem,
        setBuilderPaneMode,
        selectBuilderTarget,
    ]);

    const handleStructureItemReorder = useCallback((
        activeLocalId: string,
        targetLocalId: string,
        position: 'before' | 'after',
    ) => {
        if (activeLocalId === targetLocalId || pendingBuilderStructureMutation) {
            return;
        }

        const requestId = createBuilderBridgeRequestId('builder-move-section');
        setPendingBuilderStructureMutation({
            requestId,
            mutation: 'move-section',
            previewItems: reorderStructureCollection(builderStructureItems, activeLocalId, targetLocalId, position),
            selectionSnapshot: createPendingBuilderSelectionSnapshot({
                sectionLocalId: effectiveSelectedBuilderSectionLocalId,
                sectionKey: effectiveSelectedPreviewSectionKey,
                target: selectedBuilderTarget,
            }),
        });
        postBuilderCommand({
            type: 'builder:move-section',
            requestId,
            sectionLocalId: activeLocalId,
            targetSectionLocalId: targetLocalId,
            position,
        });
    }, [builderStructureItems, effectiveSelectedBuilderSectionLocalId, effectiveSelectedPreviewSectionKey, pendingBuilderStructureMutation, postBuilderCommand, selectedBuilderTarget]);

    const handleStructureItemDragStart = useCallback((event: ReactDragEvent<HTMLButtonElement>, localId: string) => {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', localId);
        setDraggingStructureItemId(localId);
        setStructureDropIndicator(null);
    }, []);

    const handleStructureItemDragOver = useCallback((event: ReactDragEvent<HTMLDivElement>, localId: string) => {
        if (pendingBuilderStructureMutation) {
            return;
        }
        const activeLocalId = draggingStructureItemId || event.dataTransfer.getData('text/plain');
        if (!activeLocalId || activeLocalId === localId) {
            return;
        }

        event.preventDefault();
        const rect = event.currentTarget.getBoundingClientRect();
        const position = event.clientY < rect.top + (rect.height / 2) ? 'before' : 'after';
        setStructureDropIndicator((current) => (
            current?.localId === localId && current.position === position
                ? current
                : { localId, position }
        ));
    }, [draggingStructureItemId, pendingBuilderStructureMutation]);

    const handleStructureItemDrop = useCallback((event: ReactDragEvent<HTMLDivElement>, localId: string) => {
        event.preventDefault();
        if (pendingBuilderStructureMutation) {
            setDraggingStructureItemId(null);
            setStructureDropIndicator(null);
            return;
        }
        const activeLocalId = draggingStructureItemId || event.dataTransfer.getData('text/plain');

        if (!activeLocalId || activeLocalId === localId) {
            setDraggingStructureItemId(null);
            setStructureDropIndicator(null);
            return;
        }

        const rect = event.currentTarget.getBoundingClientRect();
        const position = event.clientY < rect.top + (rect.height / 2) ? 'before' : 'after';
        handleStructureItemReorder(activeLocalId, localId, position);
        setDraggingStructureItemId(null);
        setStructureDropIndicator(null);
    }, [draggingStructureItemId, handleStructureItemReorder, pendingBuilderStructureMutation]);

    const handleStructureItemDragEnd = useCallback(() => {
        setDraggingStructureItemId(null);
        setStructureDropIndicator(null);
    }, []);

    const lazyPanelFallback = (
        <div className="flex h-full items-center justify-center p-6 text-sm text-muted-foreground">
            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
            {t('Loading panel...')}
        </div>
    );
    const isInspectBuilderMode = viewMode === 'inspect';
    const shouldRenderChatWorkspace = viewMode !== 'inspect';

    const workspaceSidebarContent = (
        <div className="workspace-sidebar workspace-sidebar--default flex w-full min-w-0 shrink-0 flex-col md:w-auto">
            {startError && (
                <div className="mx-6 mt-4 flex shrink-0 items-center justify-between gap-3 rounded-[16px] border border-amber-300/50 bg-amber-50 px-4 py-3">
                    <p className="text-sm text-amber-950">
                        {t('Builder is offline. Start it with "composer dev" or run "bash scripts/start-local-builder.sh".')}
                    </p>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => void retryStart()}
                        className="rounded-full border-amber-300 bg-white text-amber-900 hover:bg-amber-100"
                    >
                        {t('Retry')}
                    </Button>
                </div>
            )}

            <div className="workspace-sidebar-content">
                {isInspectBuilderMode ? (
                    <div className="workspace-builder-pane">
                        <div className="workspace-builder-inline-shell">
                            <div
                                className={cn(
                                    'workspace-builder-library-shell',
                                    builderPaneMode !== 'elements' && 'workspace-builder-library-shell--hidden',
                                )}
                            >
                                {groupedBuilderLibraryItems.length > 0 ? (
                                    <div className="workspace-builder-library-list">
                                        {groupedBuilderLibraryItems.map((group) => (
                                            <section key={group.category} className="workspace-builder-library-group">
                                                <div className="workspace-builder-library-group-header">
                                                    <div className="workspace-builder-library-group-title">
                                                        <Layers className="h-4 w-4" />
                                                        <span>{group.categoryLabel}</span>
                                                    </div>
                                                    <span className="workspace-builder-library-group-count">
                                                        {group.items.length}
                                                    </span>
                                                </div>

                                                <div className="workspace-builder-library-grid">
                                                    {group.items.map((item) => {
                                                        const IconComponent = resolveBuilderWidgetIcon(item.key, item.category);

                                                        return (
                                                            <button
                                                                key={item.key}
                                                                type="button"
                                                                draggable
                                                                onPointerDown={(event) => {
                                                                    if (event.button === 0) {
                                                                        inspectLog('sidebar: setActiveLibraryItem (pointer)', item.key);
                                                                        setActiveLibraryItem(item);
                                                                    }
                                                                }}
                                                                onClick={() => handleLibraryItemActivate(item)}
                                                                onDragStart={(event) => {
                                                                    event.dataTransfer.effectAllowed = 'copy';
                                                                    event.dataTransfer.setData('text/plain', item.key);
                                                                    inspectLog('sidebar: dragStart', item.key);
                                                                    setActiveLibraryItem(item);
                                                                }}
                                                                onDragEnd={() => {
                                                                    inspectLog('sidebar: dragEnd');
                                                                    setActiveLibraryItem(null);
                                                                }}
                                                                className={cn(
                                                                    'workspace-builder-library-card flex flex-col items-center justify-center text-center gap-1.5 min-h-[72px]',
                                                                    activeLibraryItem?.key === item.key && 'workspace-builder-library-card--active',
                                                                )}
                                                                title={item.label}
                                                            >
                                                                <span className="workspace-builder-library-card-icon shrink-0">
                                                                    <IconComponent className="h-4 w-4" />
                                                                </span>
                                                                <span className="workspace-builder-library-card-label text-center text-xs leading-tight line-clamp-2 w-full min-w-0">
                                                                    {getShortDisplayName(item.key, item.label || item.key)}
                                                                </span>
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                            </section>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="workspace-builder-library-empty">
                                        {t('Components loading...')}
                                    </div>
                                )}
                            </div>

                            <div
                                className={cn(
                                    'workspace-builder-settings-shell',
                                    builderPaneMode !== 'settings' && 'workspace-builder-settings-shell--hidden',
                                )}
                            >
                                <div className="workspace-builder-settings-toolbar">
                                    <button
                                        type="button"
                                        onClick={handleBuilderShowElements}
                                        className="workspace-builder-settings-back"
                                    >
                                        <ArrowLeft className="h-3.5 w-3.5" />
                                        <span>{t('Components')}</span>
                                    </button>
                                </div>
                                <iframe
                                    ref={builderSidebarFrameRef}
                                    src={visualBuilderSidebarUrl}
                                    title={t('Visual Builder Sidebar')}
                                    className="workspace-builder-frame workspace-builder-frame--sidebar"
                                    sandbox="allow-same-origin allow-scripts allow-forms allow-modals allow-popups allow-downloads"
                                    onLoad={handleBuilderSidebarFrameLoad}
                                />
                            </div>
                        </div>
                    </div>
                ) : null}

                {shouldRenderChatWorkspace ? (
                    <div className="workspace-sidebar-chat-column">
                        <div className="flex min-h-0 flex-1 flex-col">
                            <div className="shrink-0 border-b border-[#e8e6e3] px-4 py-2">
                                <div className="flex items-center gap-2">
                                    <Sparkles className="h-4 w-4 text-amber-500 shrink-0" />
                                    <span className="font-semibold text-[#1c1917] truncate">{t('Webu')}</span>
                                </div>
                            </div>
                            <ScrollArea className="workspace-chat-scroll">
                                <div className="workspace-scroll-mask flex flex-col py-2">
                                    {initialLoading && visibleMessages.length === 0 ? (
                                        <div className="workspace-thread-shell workspace-thread-shell--skeleton space-y-4">
                                            <MessageListSkeleton count={3} />
                                        </div>
                                    ) : visibleMessages.length === 0 && !isLoading ? (
                                        <div className="workspace-empty-state">
                                            <h2 className="workspace-empty-state-title">
                                                {t('Webu')}
                                            </h2>
                                            <p className="workspace-empty-state-text">
                                                {t('Describe what you want to build or change')}
                                            </p>
                                        </div>
                                    ) : (
                                        <>
                                            {visibleMessages.map((msg) => (
                                                <MessageBubble
                                                    key={msg.id}
                                                    message={msg}
                                                    currentUser={_user}
                                                    shouldType={typingAssistantMessageIds.includes(msg.id)}
                                                    onTypingComplete={() => handleAssistantTypingComplete(msg.id)}
                                                />
                                            ))}
                                            {(isAiSiteEditorBusy && agentPhaseLabel) && (
                                                <PendingAssistantBubble
                                                    progress={{
                                                        status: 'running',
                                                        iterations: 0,
                                                        tokensUsed: 0,
                                                        hasFileChanges: false,
                                                        statusMessage: agentPhaseLabel,
                                                        messages: [],
                                                        actions: [],
                                                        toolCalls: [],
                                                        toolResults: [],
                                                        thinkingContent: null,
                                                        thinkingStartTime: null,
                                                        error: null,
                                                        previewUrl: null,
                                                    }}
                                                    label={agentPhaseLabel}
                                                    showPipelineSteps={false}
                                                />
                                            )}
                                        </>
                                    )}

                                    {failedMessages.map((failed) => (
                                        <div key={failed.timestamp} className="workspace-thread-shell workspace-thread-shell--error animate-fade-in">
                                            <div className="flex justify-start">
                                                <div className="max-w-[78%] rounded-[28px] border border-red-200 bg-[#fff4f2] px-6 py-4 text-[#3f3f46]">
                                                    <p className="whitespace-pre-wrap break-words text-sm">{failed.message}</p>
                                                    <p className="mt-1.5 text-xs text-red-500">{t('Builder offline, message not sent')}</p>
                                                </div>
                                            </div>
                                        </div>
                                    ))}

                                    {isLoading && (
                                        <PendingAssistantBubble
                                            progress={progress}
                                            label={progress.thinkingContent ?? progress.statusMessage}
                                        />
                                    )}

                                    <div ref={scrollEndRef} />
                                </div>
                            </ScrollArea>

                            {agentRunFailedAt && !isAiSiteEditorBusy && (
                                <div className="px-6 pb-2">
                                    <AgentProgressInline
                                        progress={{
                                            status: 'failed',
                                            iterations: 0,
                                            tokensUsed: 0,
                                            hasFileChanges: false,
                                            statusMessage: t('Failed'),
                                            messages: [],
                                            actions: [],
                                            toolCalls: [],
                                            toolResults: [],
                                            thinkingContent: null,
                                            thinkingStartTime: null,
                                            error: 'Failed',
                                            previewUrl: null,
                                        }}
                                        currentStepLabel={t('Failed')}
                                    />
                                </div>
                            )}

                            <div className="workspace-input-wrap">
                                <ChatInputWithMentions
                                    projectId={project.id}
                                    value={prompt}
                                    onChange={setPrompt}
                                    onSubmit={handleSubmit}
                                    disabled={isLoading}
                                    selectedElement={selectedElementMention}
                                    onClearElement={() => {
                                        clearBuilderSelection();
                                        postBuilderCommand({
                                            type: 'builder:set-sidebar-mode',
                                            mode: 'elements',
                                        });
                                        postBuilderCommand({
                                            type: 'builder:clear-selected-section',
                                        });
                                    }}
                                    placeholder={t('Write to Webu')}
                                    isLoading={isLoading}
                                    onCancel={cancelBuild}
                                    variant="workspace"
                                    footerStartSlot={
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={handleVisualBuilderToggle}
                                            className={cn(
                                                'workspace-visual-edit-button',
                                                isVisualBuilderOpen && 'workspace-visual-edit-button--active',
                                            )}
                                        >
                                            {isVisualBuilderOpen ? (
                                                <MessageSquare className="h-4 w-4" />
                                            ) : (
                                                <MousePointerClick className="h-4 w-4" />
                                            )}
                                            {isVisualBuilderOpen ? t('Chat') : visualBuilderLabel}
                                        </Button>
                                    }
                                />
                            </div>
                        </div>
                    </div>
                ) : null}
            </div>
        </div>
    );

    const hiddenBuilderBridgeHost = !isInspectBuilderMode ? (
        <div
            aria-hidden="true"
            className="h-0 w-0 overflow-hidden opacity-0 pointer-events-none"
        >
            <iframe
                ref={builderSidebarFrameRef}
                src={visualBuilderSidebarUrl}
                title={t('Visual Builder Bridge')}
                className="h-0 w-0 border-0"
                sandbox="allow-same-origin allow-scripts allow-forms allow-modals allow-popups allow-downloads"
                onLoad={handleBuilderSidebarFrameLoad}
                tabIndex={-1}
            />
        </div>
    ) : null;

    const workspacePreviewContent = (
        <BuilderPreviewSurface
            isSidebarVisible={isSidebarVisible}
            viewMode={viewMode}
            settingsContent={(
                <div className="h-full p-4">
                    <div className="workspace-surface h-full">
                        <Suspense fallback={lazyPanelFallback}>
                            <ProjectSettingsPanel
                                project={project}
                                baseDomain={baseDomain}
                                canUseSubdomains={canUseSubdomains}
                                canCreateMoreSubdomains={canCreateMoreSubdomains}
                                canUsePrivateVisibility={canUsePrivateVisibility}
                                subdomainUsage={subdomainUsage}
                                suggestedSubdomain={suggestedSubdomain}
                                firebase={firebase}
                                storage={storage}
                                moduleRegistry={moduleRegistry}
                            />
                        </Suspense>
                    </div>
                </div>
            )}
            codeContent={(
                <div className="flex h-full min-h-0 gap-4 p-4">
                    <div className="workspace-surface flex h-full min-h-0 w-60 shrink-0 flex-col">
                        <Suspense fallback={lazyPanelFallback}>
                            <FileTree
                                projectId={project.id}
                                onFileSelect={handleCodeFileSelect}
                                selectedFile={selectedFile}
                                refreshTrigger={fileRefreshTrigger}
                                virtualFiles={generatedVirtualFiles.map((file) => ({
                                    path: file.path,
                                    displayName: file.displayName,
                                    sourceLabel: t('Derived preview'),
                                }))}
                                onRegenerateFromSite={handleRegenerateCodeFromSite}
                                isRegenerating={isRegeneratingCode}
                            />
                        </Suspense>
                    </div>
                    <div className="workspace-surface flex min-h-0 min-w-0 flex-1 flex-col">
                        <Suspense fallback={lazyPanelFallback}>
                            <div className="flex h-full flex-col">
                                <div className="border-b px-3 py-2 text-xs text-muted-foreground">
                                    {t('Workspace = real editable files (AI edits these). Derived preview = read-only CMS projection (AI never sees it).')}
                                </div>
                                <div className="min-h-0 flex-1">
                                    <CodeEditor
                                        ref={codeEditorRef}
                                        projectId={project.id}
                                        selectedFile={selectedFile}
                                        selectedFileMeta={selectedFileMeta}
                                        onSave={refreshWorkspaceAfterChange}
                                        virtualFiles={generatedVirtualFiles}
                                    />
                                </div>
                            </div>
                        </Suspense>
                    </div>
                </div>
            )}
            previewContent={(
                <Suspense fallback={lazyPanelFallback}>
                    <InspectPreview
                        projectId={project.id}
                        mode={viewMode as 'preview' | 'inspect' | 'design'}
                        viewport={previewViewport}
                        previewUrl={builderPreviewUrl}
                        refreshTrigger={previewRefreshTrigger}
                        isBuilding={isBuildingPreview}
                        captureThumbnailTrigger={captureThumbnailTrigger}
                        onElementSelect={handleElementSelect}
                        onElementEdit={handleElementEdit}
                        pendingEdits={pendingEdits}
                        onSaveAllEdits={handleSaveAllEdits}
                        onDiscardAllEdits={handleDiscardAllEdits}
                        onRemoveEdit={handleRemoveEdit}
                        onThemeSelect={applyThemeToPreview}
                        isSavingTheme={isSavingTheme}
                        currentTheme={appliedTheme}
                        highlightSectionKey={viewMode === 'inspect' ? effectiveSelectedPreviewSectionKey : null}
                        highlightSectionLocalId={viewMode === 'inspect' ? (agentHighlightLocalId ?? effectiveSelectedBuilderSectionLocalId) : null}
                        liveStructureItems={viewMode === 'design' ? [] : visibleBuilderStructureItems}
                        selectedElementMention={viewMode === 'design' ? null : selectedElementMention}
                        pendingLibraryItem={viewMode === 'inspect' ? activeLibraryItem : null}
                        onLibraryItemPlace={handleLibraryItemPlace}
                        onPreviewReadyChange={viewMode === 'inspect' ? markBuilderPreviewReady : undefined}
                        themeDesignerSlot={(
                            <Suspense fallback={lazyPanelFallback}>
                                <ThemeDesigner
                                    currentTheme={appliedTheme}
                                    onThemeSelect={applyThemeToPreview}
                                    onApply={async (presetId) => {
                                        setIsSavingTheme(true);
                                        try {
                                            const response = await axios.put(`/project/${project.id}/theme`, {
                                                theme_preset: presetId,
                                            });
                                            if (response.data.success) {
                                                setAppliedTheme(presetId);
                                                if (response.data.warning) {
                                                    toast.warning(response.data.warning);
                                                } else {
                                                    toast.success(t('Theme applied successfully'));
                                                }
                                                setPreviewRefreshTrigger(Date.now());
                                                setCaptureThumbnailTrigger(Date.now());
                                            }
                                        } catch {
                                            toast.error(t('Failed to apply theme'));
                                        } finally {
                                            setIsSavingTheme(false);
                                        }
                                    }}
                                    isSaving={isSavingTheme}
                                />
                            </Suspense>
                        )}
                    />
                </Suspense>
            )}
        />
    );

    /** სანამ აგენტი მუშაობს (build_status === 'building'), საიტი დახურული — იგივე ლოადერი როგორც პროექტის შექმნისას. */
    if (project.build_status === 'building') {
        return (
            <>
                <Head title={project.name} />
                <Toaster />
                <div className="fixed inset-0 z-[100] bg-background" aria-busy="true" aria-label={t('Building your project...')}>
                    <ChatPageSkeleton />
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={project.name} />
            <Toaster />

            <div className="workspace-shell flex h-screen flex-col">
                <nav className="workspace-header hidden shrink-0 md:flex">
                    <div
                        className={cn(
                            'workspace-header-grid',
                            isSidebarVisible
                                ? 'workspace-header-grid--with-sidebar'
                                : 'grid-cols-[minmax(0,1fr)]'
                        )}
                    >
                        {isSidebarVisible && (
                            <div className="flex min-w-0 items-center gap-2.5 pe-2">
                                <button
                                    type="button"
                                    onClick={handleGoBack}
                                    className="workspace-back-button"
                                    title={t('Go back')}
                                >
                                    <ArrowLeft className="h-3.5 w-3.5" />
                                </button>
                                <a
                                    href={`/project/${project.id}/cms`}
                                    className="workspace-icon-button"
                                    title={t('Open project CMS')}
                                    aria-label={t('Open project CMS')}
                                >
                                    <PanelLeft className="h-4 w-4" />
                                </a>

                                <div className="flex min-w-0 items-center gap-2.5">
                                    {brandImageUrl ? (
                                        <img
                                            src={brandImageUrl}
                                            alt={project.name}
                                            className="h-8 w-8 shrink-0 rounded-[12px] object-cover"
                                        />
                                    ) : (
                                        <div className="workspace-brand-fallback">
                                            <Sparkles className="h-4 w-4" />
                                        </div>
                                    )}

                                    <Popover open={isProjectMenuOpen} onOpenChange={setIsProjectMenuOpen}>
                                        <PopoverTrigger asChild>
                                            <button type="button" className="workspace-project-trigger">
                                                <div className="flex min-w-0 items-center gap-1.5">
                                                    <span className="workspace-project-title">
                                                        {workspaceProjectLabel}
                                                    </span>
                                                    <ChevronDown
                                                        className={cn(
                                                            'workspace-project-chevron h-3.5 w-3.5 shrink-0 text-[#8a857d]',
                                                            isProjectMenuOpen && 'workspace-project-chevron--open'
                                                        )}
                                                    />
                                                </div>
                                                <div className="workspace-project-meta">
                                                    <span className="workspace-status-dot" />
                                                    <span className="truncate">{workspaceDomainLabel}</span>
                                                    <span className="workspace-credit-pill">
                                                        {creditLabel}
                                                    </span>
                                                </div>
                                            </button>
                                        </PopoverTrigger>
                                        <PopoverContent align="start" sideOffset={12} className="workspace-project-popover">
                                            <button
                                                type="button"
                                                className="workspace-project-popover-back"
                                                onClick={() => {
                                                    setIsProjectMenuOpen(false);
                                                    handleGoBack();
                                                }}
                                            >
                                                <ArrowLeft className="h-4 w-4" />
                                                <span>{t('Go back')}</span>
                                            </button>

                                            <div className="workspace-project-popover-section">
                                                <div className="workspace-project-account-row">
                                                    {_user.avatar ? (
                                                        <img
                                                            src={_user.avatar}
                                                            alt={_user.name}
                                                            className="h-10 w-10 rounded-[12px] object-cover"
                                                        />
                                                    ) : (
                                                        <span className="workspace-project-account-avatar">
                                                            {_user.name.charAt(0).toUpperCase()}
                                                        </span>
                                                    )}

                                                    <div className="min-w-0 flex-1">
                                                        <div className="workspace-project-account-name">
                                                            {_user.name}
                                                        </div>
                                                        <div className="workspace-project-account-domain">
                                                            {workspaceDomainLabel}
                                                        </div>
                                                    </div>

                                                    <span className="workspace-project-plan-pill">
                                                        {workspacePlanLabel}
                                                    </span>
                                                </div>

                                                <div className="workspace-project-credit-card">
                                                    <div className="workspace-project-credit-header">
                                                        <div className="workspace-project-credit-title">
                                                            <CreditCard className="h-4 w-4" />
                                                            <span>{t('Credits')}</span>
                                                        </div>
                                                        <span className="workspace-project-credit-value">
                                                            {creditSummaryLabel}
                                                        </span>
                                                    </div>
                                                    <progress
                                                        className="workspace-project-credit-progress"
                                                        max={100}
                                                        value={creditUsagePercent}
                                                    />
                                                    <div className="workspace-project-credit-note">
                                                        {creditDetailLabel}
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="workspace-project-popover-section">
                                                <button
                                                    type="button"
                                                    className="workspace-project-popover-action"
                                                    onClick={() => {
                                                        setIsProjectMenuOpen(false);
                                                        handleWorkspaceModeChange('settings');
                                                    }}
                                                >
                                                    <BarChart3 className="h-4 w-4" />
                                                    <span>{t('Project settings')}</span>
                                                </button>
                                                <button
                                                    type="button"
                                                    className="workspace-project-popover-action"
                                                    onClick={() => {
                                                        setIsProjectMenuOpen(false);
                                                        void handleShareWorkspace();
                                                    }}
                                                >
                                                    <Share2 className="h-4 w-4" />
                                                    <span>{t('Share project')}</span>
                                                </button>
                                                <button
                                                    type="button"
                                                    className="workspace-project-popover-action"
                                                    onClick={() => {
                                                        setIsProjectMenuOpen(false);
                                                        if (previewTarget) {
                                                            handleOpenPreviewSite();
                                                        } else {
                                                            setPublishModalOpen(true);
                                                        }
                                                    }}
                                                >
                                                    <ExternalLink className="h-4 w-4" />
                                                    <span>{previewTarget ? t('Open live site') : t('Publish site')}</span>
                                                </button>
                                            </div>
                                        </PopoverContent>
                                    </Popover>
                                </div>
                            </div>
                        )}

                        <div className="flex min-w-0 items-center justify-between gap-3">
                            <div className="flex min-w-0 items-center gap-1.5">
                                <button type="button" onClick={() => setPreviewRefreshTrigger(Date.now())} className="workspace-icon-button" title={t('View history')}>
                                    <History className="h-3.5 w-3.5" />
                                </button>
                                <button
                                    type="button"
                                    onClick={handleSidebarToggle}
                                    className="workspace-icon-button"
                                    title={isSidebarVisible ? t('Close sidebar') : t('Open sidebar')}
                                >
                                    <Columns2 className="h-3.5 w-3.5" />
                                </button>

                                {toolbarTabs.map((tab) => {
                                    const isInspectToggle = tab.key === 'inspect' && viewMode === 'inspect';
                                    const Icon = isInspectToggle ? MessageSquare : tab.icon;
                                    const isActive = viewMode === tab.key;
                                    const tabLabel = isInspectToggle ? t('Chat') : tab.label;

                                    return (
                                        <button
                                            key={tab.key}
                                            type="button"
                                            onClick={() => handleWorkspaceModeChange(tab.key)}
                                            title={tabLabel}
                                            className={cn(
                                                isActive
                                                    ? 'workspace-preview-button workspace-preview-button--active'
                                                    : 'workspace-icon-button'
                                            )}
                                        >
                                            <Icon className="h-3.5 w-3.5" />
                                            {isActive && <span>{tabLabel}</span>}
                                        </button>
                                    );
                                })}

                                <button
                                    type="button"
                                    onClick={() => toast(t('More options coming soon'))}
                                    className="workspace-icon-button"
                                    title={t('More options')}
                                >
                                    <Ellipsis className="h-3.5 w-3.5" />
                                </button>

                            </div>

                            <div className="flex shrink-0 items-center gap-1.5">
                                {viewMode === 'inspect' ? (
                                    <div className="workspace-builder-header-actions">
                                        <PreviewViewportMenu
                                            value={previewViewport}
                                            onChange={handleBuilderViewportChange}
                                        />
                                        <button
                                            type="button"
                                            onClick={handleBuilderRefresh}
                                            className="workspace-icon-button"
                                            aria-label={t('Refresh Preview')}
                                            title={t('Refresh Preview')}
                                        >
                                            <RefreshCw className="h-3.5 w-3.5" />
                                        </button>
                                        <div
                                            ref={structureDropdownRef}
                                            className="workspace-structure-dropdown"
                                            onMouseEnter={cancelStructurePanelClose}
                                            onMouseLeave={scheduleStructurePanelClose}
                                        >
                                            <button
                                                type="button"
                                                onClick={handleBuilderStructureToggle}
                                                className={cn(
                                                    'workspace-icon-button',
                                                    isBuilderStructureOpen && 'workspace-icon-button--active'
                                                )}
                                                aria-label={t('Open Structure')}
                                                title={t('Open Structure')}
                                            >
                                                <Layers className="h-3.5 w-3.5" />
                                            </button>

                                            {isBuilderStructureOpen && visibleBuilderStructureItems.length > 0 && (
                                                <div className="workspace-floating-structure-panel">
                                                    <div className="workspace-floating-structure-card">
                                                        <div className="workspace-floating-structure-header">
                                                            <div className="flex min-w-0 items-center gap-2">
                                                                <Layers className="h-4 w-4 text-[#6d6862]" />
                                                                <p className="truncate text-xs font-semibold text-[#35322d]">
                                                                    {t('Structure')}
                                                                </p>
                                                                <span className="workspace-floating-structure-badge">
                                                                    {visibleBuilderStructureItems.length}
                                                                </span>
                                                            </div>
                                                            <button
                                                                type="button"
                                                                data-structure-panel-control="true"
                                                                className="workspace-floating-structure-close"
                                                                onClick={() => {
                                                                    preferPersistedStructureStateRef.current = false;
                                                                    setStructurePanelOpen(false);
                                                                }}
                                                                aria-label={t('Collapse structure panel')}
                                                                title={t('Collapse structure panel')}
                                                            >
                                                                <ArrowUp className="h-3.5 w-3.5" />
                                                            </button>
                                                        </div>

                                                        <div className="workspace-floating-structure-body">
                                                            {visibleBuilderStructureItems.map((item, index) => {
                                                                const isExpanded = expandedStructureItemIds.includes(item.localId);
                                                                const isDragging = draggingStructureItemId === item.localId;
                                                                const isDropBefore = structureDropIndicator?.localId === item.localId && structureDropIndicator.position === 'before';
                                                                const isDropAfter = structureDropIndicator?.localId === item.localId && structureDropIndicator.position === 'after';

                                                                return (
                                                                    <div
                                                                        key={item.localId}
                                                                        className={cn(
                                                                            'workspace-floating-structure-item',
                                                                            effectiveSelectedBuilderSectionLocalId === item.localId && 'workspace-floating-structure-item--active',
                                                                            isDragging && 'workspace-floating-structure-item--dragging',
                                                                            isDropBefore && 'workspace-floating-structure-item--drop-before',
                                                                            isDropAfter && 'workspace-floating-structure-item--drop-after',
                                                                        )}
                                                                        onDragOver={(event) => handleStructureItemDragOver(event, item.localId)}
                                                                        onDrop={(event) => handleStructureItemDrop(event, item.localId)}
                                                                    >
                                                                        <div className="workspace-floating-structure-item-row">
                                                                            <button
                                                                                type="button"
                                                                                draggable={!pendingBuilderStructureMutation}
                                                                                onDragStart={(event) => handleStructureItemDragStart(event, item.localId)}
                                                                                onDragEnd={handleStructureItemDragEnd}
                                                                                className="workspace-floating-structure-item-handle"
                                                                                title={t('Drag to reorder')}
                                                                                aria-label={t('Drag to reorder')}
                                                                            >
                                                                                <GripVertical className="h-3.5 w-3.5" />
                                                                            </button>
                                                                            <button
                                                                                type="button"
                                                                                onClick={() => handleStructureSectionSelect(item)}
                                                                                className="workspace-floating-structure-item-click"
                                                                            >
                                                                                <div className="workspace-floating-structure-item-top">
                                                                                    <span className="workspace-floating-structure-item-index">
                                                                                        #{index + 1}
                                                                                    </span>
                                                                                    <span className="workspace-floating-structure-item-label">
                                                                                        {item.label}
                                                                                    </span>
                                                                                </div>
                                                                            </button>
                                                                            <button
                                                                                type="button"
                                                                                onClick={(event) => {
                                                                                    event.preventDefault();
                                                                                    event.stopPropagation();
                                                                                    toggleStructureItemExpanded(item.localId);
                                                                                }}
                                                                                className={cn(
                                                                                    'workspace-floating-structure-item-toggle',
                                                                                    isExpanded && 'workspace-floating-structure-item-toggle--open',
                                                                                )}
                                                                                aria-label={isExpanded ? t('Collapse item details') : t('Expand item details')}
                                                                                title={isExpanded ? t('Collapse item details') : t('Expand item details')}
                                                                                aria-expanded={isExpanded}
                                                                            >
                                                                                <ChevronDown className="h-3.5 w-3.5" />
                                                                            </button>
                                                                            <button
                                                                                type="button"
                                                                                onClick={(e) => {
                                                                                    e.preventDefault();
                                                                                    e.stopPropagation();
                                                                                    if (pendingBuilderStructureMutation) {
                                                                                        return;
                                                                                    }
                                                                                    const localId = item.localId;
                                                                                    const requestId = createBuilderBridgeRequestId('builder-remove-section');
                                                                                    setPendingBuilderStructureMutation({
                                                                                        requestId,
                                                                                        mutation: 'remove-section',
                                                                                        previewItems: buildOptimisticRemovedStructureItems(builderStructureItems, localId),
                                                                                        selectionSnapshot: createPendingBuilderSelectionSnapshot({
                                                                                            sectionLocalId: effectiveSelectedBuilderSectionLocalId,
                                                                                            sectionKey: effectiveSelectedPreviewSectionKey,
                                                                                            target: selectedBuilderTarget,
                                                                                        }),
                                                                                    });
                                                                                    if (effectiveSelectedBuilderSectionLocalId === localId) {
                                                                                        clearBuilderSelection();
                                                                                    }
                                                                                    postBuilderCommand({
                                                                                        type: 'builder:remove-section',
                                                                                        requestId,
                                                                                        sectionLocalId: localId,
                                                                                        sectionIndex: index,
                                                                                        sectionKey: item.sectionKey,
                                                                                    });
                                                                                }}
                                                                                className="workspace-floating-structure-item-remove"
                                                                                title={t('Remove section')}
                                                                                aria-label={t('Remove section')}
                                                                                disabled={pendingBuilderStructureMutation !== null}
                                                                            >
                                                                                <Trash2 className="h-3.5 w-3.5" />
                                                                            </button>
                                                                        </div>

                                                                        {isExpanded && (
                                                                            <div className="workspace-floating-structure-item-content">
                                                                                <span className="workspace-floating-structure-item-preview">
                                                                                    {item.previewText || t('No preview text')}
                                                                                </span>
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                );
                                                            })}
                                                        </div>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ) : viewMode === 'code' ? null : (
                                    <PreviewViewportMenu
                                        value={previewViewport}
                                        onChange={setPreviewViewport}
                                    />
                                )}
                                {headerSaveAction && (
                                    <button
                                        type="button"
                                        onClick={headerSaveAction.onClick}
                                        disabled={headerSaveAction.disabled}
                                        className={cn(
                                            'workspace-preview-button',
                                            headerSaveAction.busy && 'workspace-preview-button--active',
                                            'disabled:cursor-not-allowed disabled:opacity-60'
                                        )}
                                        aria-label={headerSaveAction.title}
                                        title={headerSaveAction.title}
                                    >
                                        {headerSaveAction.busy ? (
                                            <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                        ) : (
                                            <Save className="h-3.5 w-3.5" />
                                        )}
                                        <span>{t('Save')}</span>
                                    </button>
                                )}
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={handleUpgrade}
                                    className="workspace-upgrade-button"
                                >
                                    <Sparkles className="me-1 h-3.5 w-3.5" />
                                    {t('Upgrade')}
                                </Button>
                                <Button
                                    size="sm"
                                    onClick={() => setPublishModalOpen(true)}
                                    className="workspace-publish-button"
                                >
                                    {project.subdomain ? t('Published') : t('Publish')}
                                </Button>
                            </div>
                        </div>
                    </div>
                </nav>

                {hiddenBuilderBridgeHost}
                <BuilderWorkspaceShell
                    isSidebarVisible={isSidebarVisible}
                    sidebarContent={workspaceSidebarContent}
                    previewContent={workspacePreviewContent}
                />
            </div>

            {/* Publish Modal */}
            <Suspense fallback={null}>
                <PublishModal
                    open={publishModalOpen}
                    onOpenChange={setPublishModalOpen}
                    project={project}
                    baseDomain={baseDomain}
                    canUseSubdomains={canUseSubdomains}
                    canCreateMoreSubdomains={canCreateMoreSubdomains}
                    canUsePrivateVisibility={canUsePrivateVisibility}
                    suggestedSubdomain={suggestedSubdomain}
                    onPublished={(url) => {
                        toast.success(t('Published to :url', { url }));
                    }}
                />
            </Suspense>
        </>
    );
}
