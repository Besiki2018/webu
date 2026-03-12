import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    useBuilderPusher,
    CompleteEvent,
    ActionEvent,
    StatusEvent,
    ErrorEvent,
    MessageEvent as PusherMessageEvent,
    ThinkingEvent,
    ToolCallEvent,
    ToolResultEvent,
    BroadcastConfig,
    SummarizationCompleteEvent,
} from './useBuilderPusher';
import { useBuilderReverb, ReverbConfig } from './useBuilderReverb';
import { useChatHistory, ChatMessage } from './useChatHistory';
import { useSessionReconnection, SessionStatus } from './useSessionReconnection';
import { buildBuilderChatPrompt, type BuilderChatElementContext } from './builderChatPrompt';
import { updateComponentProps } from '@/builder/updates';
import { runOptimizeForProjectType, OPTIMIZE_FOR_PROJECT_TYPE_COMMAND } from '@/builder/commands/optimizeForProjectType';
import { runGenerateSite, GENERATE_SITE_COMMAND } from '@/builder/commands/generateSite';
import axios from 'axios';

interface ServerHistoryEntry {
    role?: string;
    content?: string;
    timestamp?: string;
    category?: string;
    thinking_duration?: number;
}

interface BuilderStatusResponse {
    status?: string;
    preview_url?: string | null;
    build_session_id?: string | null;
    error?: string;
    recent_history?: ServerHistoryEntry[];
}

export interface BuildProgress {
    status: 'idle' | 'connecting' | 'running' | 'completed' | 'failed' | 'cancelled';
    iterations: number;
    tokensUsed: number;
    hasFileChanges: boolean;
    statusMessage: string | null;
    messages: string[];
    actions: ActionEvent[];
    toolCalls: ToolCallEvent[];
    toolResults: ToolResultEvent[];
    thinkingContent: string | null;
    thinkingStartTime: number | null;
    error: string | null;
    previewUrl: string | null;
}

export interface UseBuilderChatOptions {
    pusherConfig: BroadcastConfig;
    initialHistory?: Array<{ role: string; content: string; timestamp: string }>;
    initialPreviewUrl?: string | null;
    // Initial reconnection state from server
    initialSessionId?: string | null;
    initialCanReconnect?: boolean;
    onComplete?: (event: CompleteEvent) => void;
    onError?: (error: string) => void;
    onMessage?: () => void;
    onAction?: () => void;
    autoBuild?: boolean;
    onBuildStart?: () => void;
    onBuildComplete?: (previewUrl: string) => void;
    onBuildError?: (error: string) => void;
}

export interface UseBuilderChatReturn {
    messages: ChatMessage[];
    progress: BuildProgress;
    isLoading: boolean;
    isStarting: boolean;
    isBuildingPreview: boolean;
    sessionId: string | null;
    startError: string | null;
    sendMessage: (content: string, options?: SendMessageOptions) => Promise<void>;
    addMessage: (message: ChatMessage) => void;
    cancelBuild: () => void;
    clearHistory: () => void;
    triggerBuild: () => Promise<void>;
    retryBuild: () => Promise<void>;
    retryStart: () => Promise<void>;
    // Reconnection state
    isReconnecting: boolean;
    reconnectAttempt: number;
    manualReconnect: () => Promise<void>;
}

export type ElementMentionContext = BuilderChatElementContext;

export interface SendMessageOptions {
    builderId?: number;
    templateUrl?: string;
    /** Element context for element-specific modifications */
    elementContext?: ElementMentionContext;
}

const initialProgress: BuildProgress = {
    status: 'idle',
    iterations: 0,
    tokensUsed: 0,
    hasFileChanges: false,
    statusMessage: null,
    messages: [],
    actions: [],
    toolCalls: [],
    toolResults: [],
    thinkingContent: null,
    thinkingStartTime: null,
    error: null,
    previewUrl: null,
};

function normalizeRealtimeHost(host: string): string {
    const cleaned = host
        .trim()
        .replace(/^(https?:\/\/|wss?:\/\/)/i, '')
        .replace(/\/+$/, '');

    if (cleaned.startsWith('[')) {
        const endBracketIndex = cleaned.indexOf(']');
        if (endBracketIndex !== -1) {
            return cleaned.slice(1, endBracketIndex);
        }
    }

    const colonCount = (cleaned.match(/:/g) ?? []).length;
    if (colonCount === 1) {
        return cleaned.split(':')[0] ?? cleaned;
    }

    return cleaned;
}

function isLoopbackRealtimeHost(host: string): boolean {
    const normalized = normalizeRealtimeHost(host).toLowerCase();

    return normalized === 'localhost'
        || normalized === '127.0.0.1'
        || normalized === '0.0.0.0'
        || normalized === '::1';
}

function shouldSuppressRealtimeTransport(config: BroadcastConfig): boolean {
    if (typeof window === 'undefined' || config.provider !== 'reverb') {
        return false;
    }

    try {
        if (window.localStorage.getItem('webu:enable-local-realtime') === '1') {
            return false;
        }
    } catch {
        // Ignore localStorage access failures.
    }

    return isLoopbackRealtimeHost(window.location.hostname) && isLoopbackRealtimeHost(config.host);
}

export function useBuilderChat(projectId: string, options: UseBuilderChatOptions): UseBuilderChatReturn {
    const { pusherConfig, initialHistory, initialPreviewUrl, onComplete, onError, onMessage, onAction, autoBuild = true, onBuildStart, onBuildComplete, onBuildError } = options;
    const history = useChatHistory({ projectId, initialHistory });
    const {
        messages,
        addMessage: addHistoryMessage,
        getHistoryForApi,
        clearHistory: clearHistoryMessages,
        removeMessage: removeHistoryMessage,
    } = history;
    const historyMessagesRef = useRef(messages);
    const [sessionId, setSessionId] = useState<string | null>(null);
    const [isStarting, setIsStarting] = useState(false);
    const [startError, setStartError] = useState<string | null>(null);
    const [progress, setProgress] = useState<BuildProgress>(() => ({
        ...initialProgress,
        previewUrl: initialPreviewUrl ?? null,
    }));
    const [isBuildingPreview, setIsBuildingPreview] = useState(false);
    const pendingMessageRef = useRef<string | null>(null);
    /** Latest tool calls by id, so we can apply updateComponentProps when tool_result arrives (same pipeline as sidebar). */
    const toolCallsByIdRef = useRef<Map<string, ToolCallEvent>>(new Map());
    const buildTriggeredRef = useRef(false);
    const lastEventTimeRef = useRef<number>(0);
    const quickStatusInFlightRef = useRef(false);
    const quickStatusLastCheckAtRef = useRef(0);
    const quickStatusBackoffUntilRef = useRef(0);
    const fallbackStatusInFlightRef = useRef(false);
    const fallbackStatusLastCheckAtRef = useRef(0);
    const fallbackStatusBackoffUntilRef = useRef(0);
    const callbackRefs = useRef({
        onComplete,
        onError,
        onMessage,
        onAction,
        onBuildStart,
        onBuildComplete,
        onBuildError,
    });
    const hasRealtimeTransport = useMemo(
        () => typeof pusherConfig.key === 'string'
            && pusherConfig.key.trim() !== ''
            && !shouldSuppressRealtimeTransport(pusherConfig),
        [pusherConfig]
    );

    useEffect(() => {
        historyMessagesRef.current = messages;
    }, [messages]);

    useEffect(() => {
        callbackRefs.current = {
            onComplete,
            onError,
            onMessage,
            onAction,
            onBuildStart,
            onBuildComplete,
            onBuildError,
        };
    }, [onAction, onBuildComplete, onBuildError, onBuildStart, onComplete, onError, onMessage]);

    const handleSessionReconnected = useCallback((sessionStatus: SessionStatus) => {
        setSessionId(sessionStatus.sessionId);
        // Set progress to running so the UI shows the correct state
        setProgress(prev => ({ ...prev, status: 'running', statusMessage: 'Reconnected. Continuing…' }));
    }, []);

    const handleSessionReconnectFailed = useCallback((error: string) => {
        callbackRefs.current.onError?.(error);
    }, []);

    // Session reconnection hook
    const sessionReconnection = useSessionReconnection({
        projectId,
        initialSessionId: options.initialSessionId ?? null,
        initialCanReconnect: options.initialCanReconnect ?? false,
        onReconnected: handleSessionReconnected,
        onReconnectFailed: handleSessionReconnectFailed,
        onSessionNotFound: () => {
            // Session no longer available - this is fine, just don't reconnect
        },
    });

    // Reverb event handlers
    const handleStatus = useCallback((data: StatusEvent) => {
        // Show activity message when summarization/compaction starts
        if (data.status === 'compacting') {
            const activityMessage: ChatMessage = {
                id: `activity-compacting-${Date.now()}`,
                type: 'activity',
                content: 'Summarizing conversation...',
                timestamp: new Date(),
                activityType: 'compacting',
            };
            addHistoryMessage(activityMessage);
        }

        // Check if this is a terminal status that should clear thinking state
        const isTerminalStatus = ['cancelled', 'completed', 'failed'].includes(data.status);

        setProgress(prev => ({
            ...prev,
            status: data.status as BuildProgress['status'],
            statusMessage: typeof data.message === 'string' && data.message.trim() !== '' ? data.message.trim() : prev.statusMessage,
            // Clear thinking state for terminal statuses
            ...(isTerminalStatus && {
                thinkingContent: null,
                thinkingStartTime: null,
            }),
        }));
    }, [addHistoryMessage]);

    const handleThinking = useCallback((data: ThinkingEvent) => {
        // Only update thinking state for UI display - don't add to history
        // The final message will be added via handleComplete after it's persisted to DB
        setProgress(prev => ({
            ...prev,
            status: 'running',
            thinkingContent: data.content,
            statusMessage: null,
            thinkingStartTime: prev.thinkingStartTime ?? Date.now(),
            iterations: data.iteration,
        }));
    }, []);

    const handleAction = useCallback((data: ActionEvent) => {
        // Add action as activity message (stacks in chat like prototype)
        const activityMessage: ChatMessage = {
            id: `activity-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
            type: 'activity',
            content: `${data.action} ${data.target}`,
            timestamp: new Date(),
            activityType: data.category || data.action.toLowerCase(),
        };
        addHistoryMessage(activityMessage);

        setProgress(prev => ({
            ...prev,
            status: 'running',
            actions: [...prev.actions, data],
            statusMessage: [data.action, data.target].filter(Boolean).join(' ').trim() || data.details || prev.statusMessage,
            // Clear thinking when we get an action
            thinkingContent: null,
        }));

        // Notify parent about action (e.g., for sound effects)
        callbackRefs.current.onAction?.();
    }, [addHistoryMessage]);

    const handleToolCall = useCallback((data: ToolCallEvent) => {
        toolCallsByIdRef.current.set(data.id, data);
        setProgress(prev => ({
            ...prev,
            status: 'running',
            toolCalls: [...prev.toolCalls, data],
            statusMessage: `Running ${data.tool}...`,
            thinkingContent: null,
        }));
    }, []);

    const handleToolResult = useCallback((data: ToolResultEvent) => {
        // Phase 7 — Chat editing: use same update pipeline as sidebar. When backend reports updateComponentProps success, apply it locally.
        if (data.tool === 'updateComponentProps' && data.success) {
            const toolCall = toolCallsByIdRef.current.get(data.id);
            const params = (toolCall?.params ?? {}) as Record<string, unknown>;
            const componentId = (params.componentId ?? params.component_id) as string | undefined;
            const path = (params.path ?? params.field) as string | string[] | undefined;
            const value = params.value;
            if (componentId && path != null) {
                const result = updateComponentProps(componentId, { path, value });
                if (!result.ok) {
                    // Store may have no componentTree (e.g. Chat page without canvas); validation errors are expected.
                    if (typeof console !== 'undefined' && console.warn) {
                        console.warn('[useBuilderChat] updateComponentProps failed:', result.error, result.message);
                    }
                }
            }
        }
        // Optimize for project type: analyze components, apply compatibility rules, refactor (AI command).
        if (data.tool === OPTIMIZE_FOR_PROJECT_TYPE_COMMAND && data.success) {
            const toolCall = toolCallsByIdRef.current.get(data.id);
            const params = (toolCall?.params ?? {}) as Record<string, unknown>;
            const projectType = (params.projectType ?? params.project_type) as string | undefined;
            if (projectType) {
                runOptimizeForProjectType(projectType);
            }
        }
        // Phase 8 — AI website generation: create page from structure (Header, Hero, Features, etc.).
        if (data.tool === GENERATE_SITE_COMMAND && data.success) {
            const toolCall = toolCallsByIdRef.current.get(data.id);
            const params = (toolCall?.params ?? {}) as Record<string, unknown>;
            const projectType = (params.projectType ?? params.project_type) as string | undefined;
            const structure = params.structure as Array<{ componentKey: string; variant?: string; props?: Record<string, unknown> }> | undefined;
            runGenerateSite({
                projectType: projectType ?? 'landing',
                ...(Array.isArray(structure) && structure.length > 0 && { structure }),
            });
        }

        setProgress(prev => ({
            ...prev,
            toolResults: [...prev.toolResults, data],
            statusMessage: data.success ? `${data.tool} completed` : `${data.tool} failed`,
        }));
    }, []);

    const handleMessage = useCallback((data: PusherMessageEvent) => {
        // Add message to chat history immediately for real-time display
        // Use setProgress to access thinkingStartTime for duration calculation
        setProgress(prev => {
            // Calculate thinking duration from start time
            let thinkingDuration: number | undefined;
            if (prev.thinkingStartTime) {
                thinkingDuration = Math.round((Date.now() - prev.thinkingStartTime) / 1000);
            }

            // Check for duplicates since same content may arrive via thinking event first
            const existingMessages = historyMessagesRef.current;
            const alreadyHasMessage = existingMessages.some(
                msg => msg.type === 'assistant' && msg.content === data.content
            );

            if (!alreadyHasMessage) {
                addHistoryMessage({
                    id: `assistant-${Date.now()}`,
                    type: 'assistant',
                    content: data.content,
                    timestamp: new Date(),
                    thinkingDuration,
                });

                // Notify parent about new message (e.g., for sound effects)
                callbackRefs.current.onMessage?.();
            }

            return {
                ...prev,
                status: 'running',
                statusMessage: null,
                messages: [...prev.messages, data.content],
                thinkingContent: null,
                thinkingStartTime: null,
            };
        });
    }, [addHistoryMessage]);

    const handleError = useCallback((data: ErrorEvent) => {
        setProgress(prev => ({
            ...prev,
            status: 'failed',
            statusMessage: data.error,
            error: data.error,
            thinkingContent: null,
            thinkingStartTime: null,
        }));

        // Add error to chat history
        addHistoryMessage({
            id: `error-${Date.now()}`,
            type: 'assistant',
            content: `Error: ${data.error}`,
            timestamp: new Date(),
        });

        callbackRefs.current.onError?.(data.error);
    }, [addHistoryMessage]);

    const handleComplete = useCallback((data: CompleteEvent) => {
        setProgress(prev => {
            // Use message from progress.messages if available, otherwise fallback to data.message
            const lastMessage = prev.messages[prev.messages.length - 1] ?? data.message;

            // Add final message to chat history if exists
            if (lastMessage) {
                const existingMessages = historyMessagesRef.current;
                const alreadyHasMessage = existingMessages.some(
                    msg => msg.type === 'assistant' && msg.content === lastMessage
                );

                if (!alreadyHasMessage) {
                    addHistoryMessage({
                        id: `assistant-${Date.now()}`,
                        type: 'assistant',
                        content: lastMessage,
                        timestamp: new Date(),
                    });
                }
            }

            return {
                ...prev,
                status: 'completed',
                iterations: data.iterations,
                tokensUsed: data.tokens_used,
                hasFileChanges: data.files_changed ?? true,  // Default to true to trigger auto-build
                statusMessage: data.build_message ?? data.message ?? 'Completed',
                // previewUrl persists from previous state - not updated from event
                thinkingContent: null,
                thinkingStartTime: null,
            };
        });

        callbackRefs.current.onComplete?.(data);
    }, [addHistoryMessage]);

    const handleSummarizationComplete = useCallback((data: SummarizationCompleteEvent) => {
        // Show completion activity message with results
        const activityMessage: ChatMessage = {
            id: `activity-summarized-${Date.now()}`,
            type: 'activity',
            content: `Compressed ${data.turns_compacted} turns (${Math.round(data.reduction_percent)}% reduction)`,
            timestamp: new Date(),
            activityType: 'compacting',
        };
        addHistoryMessage(activityMessage);
    }, [addHistoryMessage]);

    // Track when any WebSocket event was last received (for activity timeout detection)
    const handleAnyEvent = useCallback(() => {
        lastEventTimeRef.current = Date.now();
    }, []);

    const applyQuickStatus = useCallback((status: string) => {
        if (status !== 'completed' && status !== 'failed' && status !== 'cancelled') {
            return;
        }

        setProgress(prev => ({
            ...prev,
            status: status as BuildProgress['status'],
            hasFileChanges: status === 'completed' ? true : prev.hasFileChanges,
            statusMessage: status === 'completed' ? 'Completed' : prev.statusMessage,
            thinkingContent: null,
            thinkingStartTime: null,
        }));
    }, []);

    const normalizeServerStatus = useCallback((status: string | undefined, fallback: BuildProgress['status']): BuildProgress['status'] => {
        if (status === 'building' || status === 'running') {
            return 'running';
        }

        if (status === 'queued' || status === 'pending' || status === 'connecting') {
            return 'connecting';
        }

        if (status === 'completed' || status === 'failed' || status === 'cancelled') {
            return status;
        }

        return fallback;
    }, []);

    const syncHistoryFromStatus = useCallback((entries: ServerHistoryEntry[] | undefined) => {
        if (!Array.isArray(entries) || entries.length === 0) {
            return;
        }

        entries.forEach((entry) => {
            const role = typeof entry.role === 'string' ? entry.role : '';
            const content = typeof entry.content === 'string' ? entry.content.trim() : '';
            const timestamp = typeof entry.timestamp === 'string' ? entry.timestamp : '';
            const parsedTime = Date.parse(timestamp);

            if (content === '' || (role !== 'assistant' && role !== 'action')) {
                return;
            }

            const nextType: ChatMessage['type'] = role === 'assistant' ? 'assistant' : 'activity';
            const category = typeof entry.category === 'string' ? entry.category : undefined;
            const hasExisting = historyMessagesRef.current.some((message) => (
                message.type === nextType
                && message.content === content
                && (nextType !== 'activity' || message.activityType === category)
                && (Number.isNaN(parsedTime) || Math.abs(message.timestamp.getTime() - parsedTime) < 60000)
            ));

            if (hasExisting) {
                return;
            }

            addHistoryMessage({
                id: `polled-${role}-${timestamp || Date.now().toString()}`,
                type: nextType,
                content,
                timestamp: Number.isNaN(parsedTime) ? new Date() : new Date(parsedTime),
                activityType: category,
                thinkingDuration: role === 'assistant' && typeof entry.thinking_duration === 'number'
                    ? entry.thinking_duration
                    : undefined,
            });

            if (nextType === 'assistant') {
                callbackRefs.current.onMessage?.();
            } else if (nextType === 'activity') {
                callbackRefs.current.onAction?.();
            }
        });
    }, [addHistoryMessage]);

    const pollStatusWithHistory = useCallback(async (minIntervalMs = 0) => {
        const now = Date.now();

        if (fallbackStatusInFlightRef.current) {
            return;
        }

        if (now < fallbackStatusBackoffUntilRef.current) {
            return;
        }

        if (now - fallbackStatusLastCheckAtRef.current < minIntervalMs) {
            return;
        }

        fallbackStatusInFlightRef.current = true;
        fallbackStatusLastCheckAtRef.current = now;

        try {
            const response = await axios.get<BuilderStatusResponse>(`/builder/projects/${projectId}/status`, {
                params: {
                    quick: 1,
                    history: 1,
                },
            });
            const payload = response.data ?? {};
            fallbackStatusBackoffUntilRef.current = 0;
            const previewUrl = typeof payload.preview_url === 'string' && payload.preview_url.trim() !== ''
                ? payload.preview_url
                : null;
            const latestAssistantMessage = Array.isArray(payload.recent_history)
                ? [...payload.recent_history]
                    .reverse()
                    .find((entry) => entry.role === 'assistant' && typeof entry.content === 'string' && entry.content.trim() !== '')
                : null;

            syncHistoryFromStatus(payload.recent_history);

            if (typeof payload.build_session_id === 'string' && payload.build_session_id.trim() !== '' && !sessionId) {
                setSessionId(payload.build_session_id);
            }

            let nextStatusAfterUpdate: BuildProgress['status'] | null = null;
            let nextPreviewUrl: string | null = null;
            let shouldEmitComplete = false;

            setProgress((prev) => {
                const normalizedStatus = normalizeServerStatus(payload.status, prev.status);
                nextStatusAfterUpdate = normalizedStatus;
                nextPreviewUrl = previewUrl ?? prev.previewUrl;
                shouldEmitComplete = prev.status !== 'completed' && normalizedStatus === 'completed';

                return {
                    ...prev,
                    status: normalizedStatus,
                    previewUrl: nextPreviewUrl,
                    hasFileChanges: normalizedStatus === 'completed' ? true : prev.hasFileChanges,
                    statusMessage: normalizedStatus === 'failed'
                        ? (typeof payload.error === 'string' && payload.error.trim() !== '' ? payload.error : prev.statusMessage)
                        : normalizedStatus === 'completed'
                            ? (latestAssistantMessage?.content ?? prev.statusMessage)
                            : prev.statusMessage,
                    error: normalizedStatus === 'failed'
                        ? (typeof payload.error === 'string' && payload.error.trim() !== '' ? payload.error : prev.error)
                        : prev.error,
                    thinkingContent: normalizedStatus === 'running' ? prev.thinkingContent : null,
                    thinkingStartTime: normalizedStatus === 'running' ? prev.thinkingStartTime : null,
                };
            });

            if (shouldEmitComplete) {
                callbackRefs.current.onComplete?.({
                    iterations: 0,
                    tokens_used: 0,
                    files_changed: true,
                    message: latestAssistantMessage?.content,
                });
            }

            if (nextStatusAfterUpdate === 'completed' && nextPreviewUrl) {
                callbackRefs.current.onBuildComplete?.(nextPreviewUrl);
            } else if (nextStatusAfterUpdate === 'failed') {
                const errorMessage = typeof payload.error === 'string' && payload.error.trim() !== ''
                    ? payload.error
                    : 'Build failed';
                callbackRefs.current.onBuildError?.(errorMessage);
                callbackRefs.current.onError?.(errorMessage);
            }
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.status === 429) {
                const retryAfterHeader = error.response.headers?.['retry-after'];
                const retryAfterSeconds = Number.parseInt(
                    Array.isArray(retryAfterHeader)
                        ? (retryAfterHeader[0] ?? '')
                        : String(retryAfterHeader ?? ''),
                    10
                );

                fallbackStatusBackoffUntilRef.current = Date.now() + (
                    Number.isFinite(retryAfterSeconds) && retryAfterSeconds > 0
                        ? retryAfterSeconds * 1000
                        : 15000
                );
            } else {
                // Prevent aggressive retries on transient failures.
                fallbackStatusBackoffUntilRef.current = Date.now() + 5000;
            }
        } finally {
            fallbackStatusInFlightRef.current = false;
        }
    }, [normalizeServerStatus, projectId, sessionId, syncHistoryFromStatus]);

    const pollQuickStatus = useCallback(async (minIntervalMs = 0) => {
        const now = Date.now();

        if (quickStatusInFlightRef.current) {
            return;
        }

        if (now < quickStatusBackoffUntilRef.current) {
            return;
        }

        if (now - quickStatusLastCheckAtRef.current < minIntervalMs) {
            return;
        }

        quickStatusInFlightRef.current = true;
        quickStatusLastCheckAtRef.current = now;

        try {
            const response = await axios.get(`/builder/projects/${projectId}/status?quick=1`);
            quickStatusBackoffUntilRef.current = 0;
            applyQuickStatus(response.data.status as string);
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.status === 429) {
                const retryAfterHeader = error.response.headers?.['retry-after'];
                const retryAfterSeconds = Number.parseInt(
                    Array.isArray(retryAfterHeader)
                        ? (retryAfterHeader[0] ?? '')
                        : String(retryAfterHeader ?? ''),
                    10
                );

                quickStatusBackoffUntilRef.current = Date.now() + (
                    Number.isFinite(retryAfterSeconds) && retryAfterSeconds > 0
                        ? retryAfterSeconds * 1000
                        : 10000
                );
            } else {
                // Prevent aggressive retries on transient failures.
                quickStatusBackoffUntilRef.current = Date.now() + 3000;
            }
        } finally {
            quickStatusInFlightRef.current = false;
        }
    }, [projectId, applyQuickStatus]);

    // When WebSocket reconnects while in 'running' status, immediately poll
    // since events may have been missed during the disconnection
    const handleWsReconnected = useCallback(async () => {
        if (progress.status !== 'running' || !sessionId) return;
        await pollStatusWithHistory(0);
    }, [progress.status, sessionId, pollStatusWithHistory]);

    const isReverb = pusherConfig.provider === 'reverb';

    // Derive ReverbConfig for the reverb hook (memoized to prevent re-renders)
    const reverbConfig: ReverbConfig = useMemo(() => isReverb
        ? { key: pusherConfig.key, host: (pusherConfig as { host: string }).host, port: (pusherConfig as { port: number }).port, scheme: (pusherConfig as { scheme: 'http' | 'https' }).scheme }
        : { key: '', host: '', port: 0, scheme: 'https' as const },
    [isReverb, pusherConfig]);

    // Both hooks are always called (React rules), but only one is active
    const pusher = useBuilderPusher({
        pusherConfig,
        enabled: !isReverb,
        onStatus: handleStatus,
        onThinking: handleThinking,
        onAction: handleAction,
        onToolCall: handleToolCall,
        onToolResult: handleToolResult,
        onMessage: handleMessage,
        onError: handleError,
        onComplete: handleComplete,
        onSummarizationComplete: handleSummarizationComplete,
        onAnyEvent: handleAnyEvent,
        onReconnected: handleWsReconnected,
    });

    const reverb = useBuilderReverb({
        reverbConfig,
        enabled: isReverb,
        onStatus: handleStatus,
        onThinking: handleThinking,
        onAction: handleAction,
        onToolCall: handleToolCall,
        onToolResult: handleToolResult,
        onMessage: handleMessage,
        onError: handleError,
        onComplete: handleComplete,
        onSummarizationComplete: handleSummarizationComplete,
        onAnyEvent: handleAnyEvent,
        onReconnected: handleWsReconnected,
    });

    const broadcaster = isReverb ? reverb : pusher;
    const hasActiveRealtime = hasRealtimeTransport && broadcaster.isConnected;

    // Keep broadcaster ref current to avoid re-subscribing when the object reference changes
    const pusherRef = useRef(broadcaster);
    useEffect(() => {
        pusherRef.current = broadcaster;
    });

    // Subscribe to project channel on mount.
    // The builder broadcasts to session.{projectId} so we can subscribe immediately
    // without waiting for the session_id response (avoids race condition).
    useEffect(() => {
        pusherRef.current.subscribe(projectId);
        return () => {
            pusherRef.current.unsubscribe();
        };
    }, [projectId]);

    // Safety fallback: poll status if session appears stuck in 'running'
    // This handles the case where the Pusher 'complete' event is lost
    useEffect(() => {
        if (!hasActiveRealtime || progress.status !== 'running' || !sessionId) return;

        const intervalId = setInterval(async () => {
            if (typeof document !== 'undefined' && document.hidden) {
                return;
            }
            await pollQuickStatus(4000);
        }, 6000);

        return () => clearInterval(intervalId);
    }, [hasActiveRealtime, progress.status, sessionId, pollQuickStatus]);

    useEffect(() => {
        if (hasActiveRealtime || !sessionId) {
            return;
        }

        if (progress.status !== 'connecting' && progress.status !== 'running') {
            return;
        }

        void pollStatusWithHistory(0);

        const intervalId = setInterval(() => {
            if (typeof document !== 'undefined' && document.hidden) {
                return;
            }

            void pollStatusWithHistory(4000);
        }, 5000);

        return () => clearInterval(intervalId);
    }, [hasActiveRealtime, pollStatusWithHistory, progress.status, sessionId]);

    // Activity timeout: if events were flowing but stopped for 3s while still 'running',
    // immediately check status (the 'complete' event may have been lost)
    useEffect(() => {
        if (!hasActiveRealtime || progress.status !== 'running' || !sessionId) return;

        const intervalId = setInterval(async () => {
            if (typeof document !== 'undefined' && document.hidden) {
                return;
            }
            const lastEvent = lastEventTimeRef.current;
            // Only trigger if we received at least one event and it's been >4s since the last
            if (lastEvent > 0 && Date.now() - lastEvent >= 4000) {
                await pollQuickStatus(7000);
            }
        }, 2000);

        return () => clearInterval(intervalId);
    }, [hasActiveRealtime, progress.status, sessionId, pollQuickStatus]);

    // Reset progress when starting new build
    const resetProgress = useCallback(() => {
        setProgress(prev => ({
            ...initialProgress,
            previewUrl: prev.previewUrl,  // Preserve existing preview
        }));
    }, []);

    const sendMessage = useCallback(async (content: string, sendOptions?: SendMessageOptions) => {
        if (!content.trim()) return;

        const finalPrompt = buildBuilderChatPrompt(content, sendOptions?.elementContext);

        // Add user message to history
        const userMessage: ChatMessage = {
            id: `user-${Date.now()}`,
            type: 'user',
            content: finalPrompt,
            timestamp: new Date(),
        };
        addHistoryMessage(userMessage);

        // Store the pending message for reference
        pendingMessageRef.current = finalPrompt;

        // Reset progress and start
        resetProgress();
        buildTriggeredRef.current = false; // Reset for new session
        lastEventTimeRef.current = 0;
        quickStatusInFlightRef.current = false;
        quickStatusLastCheckAtRef.current = 0;
        quickStatusBackoffUntilRef.current = 0;
        fallbackStatusInFlightRef.current = false;
        fallbackStatusLastCheckAtRef.current = 0;
        fallbackStatusBackoffUntilRef.current = 0;
        setIsStarting(true);
        setStartError(null);
        setProgress(prev => ({ ...prev, status: 'connecting', statusMessage: 'Starting build session...' }));

        try {
            const response = await axios.post(`/builder/projects/${projectId}/start`, {
                prompt: finalPrompt,
                builder_id: sendOptions?.builderId,
                template_url: sendOptions?.templateUrl,
                history: getHistoryForApi(),
            });

            const { session_id } = response.data;
            setSessionId(session_id);
            setProgress(prev => ({ ...prev, status: 'running', statusMessage: 'Connected. Preparing response...' }));
        } catch (error) {
            const errorMessage = axios.isAxiosError(error)
                ? error.response?.data?.error || error.message
                : 'Failed to start build';
            const code = axios.isAxiosError(error) ? error.response?.data?.code : undefined;
            const isBuilderOffline = code === 'builder_offline' || (typeof errorMessage === 'string' && /offline|unreachable/i.test(errorMessage));

            // Keep the user message when builder is offline so they can retry without retyping
            if (!isBuilderOffline) {
                removeHistoryMessage(userMessage.id);
            }

            setStartError(errorMessage);
            setProgress(prev => ({ ...prev, status: 'failed', statusMessage: errorMessage, error: errorMessage }));
            callbackRefs.current.onError?.(errorMessage);
        } finally {
            setIsStarting(false);
        }
    }, [addHistoryMessage, getHistoryForApi, projectId, removeHistoryMessage, resetProgress]);

    const cancelBuild = useCallback(async () => {
        if (!sessionId) return;

        try {
            await axios.post(`/builder/projects/${projectId}/cancel`);
            setProgress(prev => ({
                ...prev,
                status: 'cancelled',
                statusMessage: 'Cancelled',
                thinkingContent: null,
                thinkingStartTime: null,
            }));
        } catch (error) {
            console.error('Failed to cancel build:', error);
        }

        pendingMessageRef.current = null;
    }, [projectId, sessionId]);

    const clearHistory = useCallback(() => {
        clearHistoryMessages();
        resetProgress();
        setSessionId(null);
        setStartError(null);
    }, [clearHistoryMessages, resetProgress]);

    const performPreviewBuild = useCallback(async (endpoint: 'build' | 'build/retry') => {
        if (isBuildingPreview) return;

        setIsBuildingPreview(true);
        callbackRefs.current.onBuildStart?.();

        try {
            const health = await axios.get(`/builder/projects/${projectId}/health`);
            if (health.data?.online !== true) {
                const offlineMessage = typeof health.data?.message === 'string' && health.data.message.trim() !== ''
                    ? health.data.message
                    : 'Builder is offline. Start it with "composer dev" or run "bash scripts/start-local-builder.sh".';
                callbackRefs.current.onBuildError?.(offlineMessage);
                return;
            }

            const response = await axios.post(`/builder/projects/${projectId}/${endpoint}`);
            const previewUrl = response.data.preview_url || `/preview/${projectId}`;

            setProgress(prev => ({ ...prev, previewUrl }));
            callbackRefs.current.onBuildComplete?.(previewUrl);
        } catch (error) {
            const errorMessage = axios.isAxiosError(error)
                ? error.response?.data?.error || error.message
                : 'Failed to build preview';

            callbackRefs.current.onBuildError?.(errorMessage);
        } finally {
            setIsBuildingPreview(false);
        }
    }, [projectId, isBuildingPreview]);

    // Trigger a preview build (works with or without active session)
    const triggerBuild = useCallback(async () => {
        await performPreviewBuild('build');
    }, [performPreviewBuild]);

    // Retry a preview build safely.
    const retryBuild = useCallback(async () => {
        await performPreviewBuild('build/retry');
    }, [performPreviewBuild]);

    // Retry starting a session after builder was offline (resends last user message)
    const retryStart = useCallback(async () => {
        if (!startError) return;
        const lastUser = [...historyMessagesRef.current].reverse().find(m => m.type === 'user');
        if (!lastUser) return;
        removeHistoryMessage(lastUser.id);
        setStartError(null);
        setProgress(prev => ({ ...prev, status: 'idle', error: null }));
        await sendMessage(lastUser.content);
    }, [removeHistoryMessage, sendMessage, startError]);

    // Auto-trigger build when agent completes with file changes (only once per session)
    useEffect(() => {
        if (autoBuild && progress.status === 'completed' && progress.hasFileChanges && !buildTriggeredRef.current) {
            buildTriggeredRef.current = true;
            triggerBuild();
        }
    }, [autoBuild, progress.status, progress.hasFileChanges, triggerBuild]);

    // Compute loading state
    const isLoading = isStarting || progress.status === 'running' || progress.status === 'connecting';

    return {
        messages,
        progress,
        isLoading,
        isStarting,
        isBuildingPreview,
        sessionId,
        startError,
        sendMessage,
        addMessage: addHistoryMessage,
        cancelBuild,
        clearHistory,
        triggerBuild,
        retryBuild,
        retryStart,
        // Reconnection state
        isReconnecting: sessionReconnection.isReconnecting,
        reconnectAttempt: sessionReconnection.reconnectAttempt,
        manualReconnect: sessionReconnection.reconnect,
    };
}

// Re-export types for convenience
export type { CompleteEvent, ActionEvent, BroadcastConfig, PusherConfig } from './useBuilderPusher';
