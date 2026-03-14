import { useEffect, useRef, useCallback, useState, useMemo } from 'react';
import Pusher from 'pusher-js';
import type { Channel } from 'pusher-js';

// Builder event types
export interface StatusEvent {
    status: string;
    message: string;
    session_id?: string | null;
    build_id?: string | null;
}

export interface ThinkingEvent {
    content: string;
    iteration: number;
    session_id?: string | null;
    build_id?: string | null;
}


export interface ActionEvent {
    action: string;
    target: string;
    details: string;
    category: string;
    session_id?: string | null;
    build_id?: string | null;
}

export interface ToolCallEvent {
    id: string;
    tool: string;
    params: Record<string, unknown>;
    session_id?: string | null;
    build_id?: string | null;
}

export interface ToolResultEvent {
    id: string;
    tool: string;
    success: boolean;
    output: string;
    duration_ms?: number;
    iteration?: number;
    session_id?: string | null;
    build_id?: string | null;
}

export interface MessageEvent {
    content: string;
    session_id?: string | null;
    build_id?: string | null;
}

export interface ErrorEvent {
    error: string;
    session_id?: string | null;
    build_id?: string | null;
}

export interface CompleteEvent {
    iterations: number;
    tokens_used: number;
    files_changed: boolean;
    message?: string;
    build_status?: string;
    build_message?: string;
    build_required?: boolean;
    prompt_tokens?: number;
    completion_tokens?: number;
    model?: string;
    session_id?: string | null;
    build_id?: string | null;
}

export interface SummarizationCompleteEvent {
    old_tokens: number;
    new_tokens: number;
    reduction_percent: number;
    turns_compacted: number;
    turns_kept: number;
    message: string;
}

export type BuilderEvent =
    | { type: 'status'; data: StatusEvent }
    | { type: 'thinking'; data: ThinkingEvent }
    | { type: 'action'; data: ActionEvent }
    | { type: 'tool_call'; data: ToolCallEvent }
    | { type: 'tool_result'; data: ToolResultEvent }
    | { type: 'message'; data: MessageEvent }
    | { type: 'error'; data: ErrorEvent }
    | { type: 'complete'; data: CompleteEvent }
    | { type: 'summarization_complete'; data: SummarizationCompleteEvent };

export interface ReverbConfig {
    key: string;
    host: string;
    port: number;
    scheme: 'http' | 'https';
}

export interface UseBuilderReverbOptions {
    reverbConfig: ReverbConfig;
    enabled?: boolean;
    onStatus?: (data: StatusEvent) => void;
    onThinking?: (data: ThinkingEvent) => void;
    onAction?: (data: ActionEvent) => void;
    onToolCall?: (data: ToolCallEvent) => void;
    onToolResult?: (data: ToolResultEvent) => void;
    onMessage?: (data: MessageEvent) => void;
    onError?: (data: ErrorEvent) => void;
    onComplete?: (data: CompleteEvent) => void;
    onSummarizationComplete?: (data: SummarizationCompleteEvent) => void;
    onAnyEvent?: (event: BuilderEvent) => void;
    onDisconnected?: () => void;
    onReconnected?: () => void;
}

export interface UseBuilderReverbReturn {
    isConnected: boolean;
    subscribe: (sessionId: string) => void;
    unsubscribe: () => void;
    error: string | null;
}

// Cache Pusher instances by config key
const pusherInstances = new Map<string, Pusher>();

function normalizeHost(host: string): string {
    const cleaned = host
        .trim()
        .replace(/^(https?:\/\/|wss?:\/\/)/i, '')
        .replace(/\/+$/, '');

    // Handle bracketed IPv6 with optional port: [::1]:8002
    if (cleaned.startsWith('[')) {
        const endBracketIndex = cleaned.indexOf(']');
        if (endBracketIndex !== -1) {
            return cleaned.slice(1, endBracketIndex);
        }
    }

    // Strip single host:port form while preserving raw IPv6 forms like ::1
    const colonCount = (cleaned.match(/:/g) ?? []).length;
    if (colonCount === 1) {
        return cleaned.split(':')[0];
    }

    return cleaned;
}

function isLoopbackHost(host: string): boolean {
    const normalized = normalizeHost(host).toLowerCase();

    return normalized === 'localhost'
        || normalized === '127.0.0.1'
        || normalized === '0.0.0.0'
        || normalized === '::1';
}

function shouldSkipRealtimeConnection(config: ReverbConfig): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    try {
        if (window.localStorage.getItem('webu:enable-local-realtime') === '1') {
            return false;
        }
    } catch {
        // Ignore localStorage access issues (private mode/security policy).
    }

    return isLoopbackHost(window.location.hostname) && isLoopbackHost(config.host);
}

function getPusher(config: ReverbConfig): Pusher {
    const host = normalizeHost(config.host);
    const configKey = `${config.key}:${host}:${config.port}`;

    if (pusherInstances.has(configKey)) {
        return pusherInstances.get(configKey)!;
    }

    const pageProtocol = typeof window !== 'undefined' ? window.location.protocol : 'http:';
    const shouldForceLocalWs = isLoopbackHost(host) && pageProtocol !== 'https:';
    const useTLS = config.scheme === 'https' && !shouldForceLocalWs;
    const pusher = new Pusher(config.key, {
        wsHost: host,
        wsPort: useTLS ? undefined : config.port,
        wssPort: useTLS ? config.port : undefined,
        forceTLS: useTLS,
        enabledTransports: useTLS ? ['wss'] : ['ws'],
        disableStats: true,
        cluster: '', // Required by Pusher but unused for custom hosts
    });

    pusherInstances.set(configKey, pusher);
    return pusher;
}

export function useBuilderReverb(options: UseBuilderReverbOptions): UseBuilderReverbReturn {
    const { reverbConfig, enabled = true } = options;
    const [isConnected, setIsConnected] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const currentChannelRef = useRef<Channel | null>(null);
    const currentChannelNameRef = useRef<string | null>(null);
    const optionsRef = useRef(options);

    // Keep options ref updated
    useEffect(() => {
        optionsRef.current = options;
    }, [options]);

    // Monitor connection state
    useEffect(() => {
        if (!enabled || !reverbConfig.key || shouldSkipRealtimeConnection(reverbConfig)) {
            return;
        }

        const pusher = getPusher(reverbConfig);
        const connection = pusher.connection;

        const handleConnected = () => {
            setIsConnected(true);
            optionsRef.current.onReconnected?.();
        };

        const handleDisconnected = () => {
            setIsConnected(false);
            optionsRef.current.onDisconnected?.();
        };

        const handleError = () => {
            setIsConnected(false);
        };

        connection.bind('connected', handleConnected);
        connection.bind('disconnected', handleDisconnected);
        connection.bind('error', handleError);

        // Set initial state without synchronous effect-state update.
        const initialStateTimer = connection.state === 'connected'
            ? window.setTimeout(() => setIsConnected(true), 0)
            : null;

        return () => {
            if (initialStateTimer !== null) {
                window.clearTimeout(initialStateTimer);
            }
            connection.unbind('connected', handleConnected);
            connection.unbind('disconnected', handleDisconnected);
            connection.unbind('error', handleError);
        };
    }, [reverbConfig, enabled]);

    const subscribe = useCallback((sessionId: string) => {
        if (!enabled || !sessionId) {
            return;
        }

        if (shouldSkipRealtimeConnection(reverbConfig)) {
            setIsConnected(false);
            setError(null);
            return;
        }

        if (!reverbConfig.key) {
            setError(null);
            return;
        }

        const channelName = `session.${sessionId}`;

        // Don't resubscribe if already subscribed to this channel
        if (currentChannelNameRef.current === channelName) {
            return;
        }

        // Unsubscribe from previous channel if any
        if (currentChannelRef.current && currentChannelNameRef.current) {
            getPusher(reverbConfig).unsubscribe(currentChannelNameRef.current);
            currentChannelRef.current = null;
            currentChannelNameRef.current = null;
        }

        try {
            const pusher = getPusher(reverbConfig);
            const channel = pusher.subscribe(channelName);

            channel.bind('status', (data: StatusEvent) => {
                optionsRef.current.onStatus?.(data);
                optionsRef.current.onAnyEvent?.({ type: 'status', data });
            });
            channel.bind('thinking', (data: ThinkingEvent) => {
                optionsRef.current.onThinking?.(data);
                optionsRef.current.onAnyEvent?.({ type: 'thinking', data });
            });
            channel.bind('action', (data: ActionEvent) => {
                optionsRef.current.onAction?.(data);
                optionsRef.current.onAnyEvent?.({ type: 'action', data });
            });
            channel.bind('tool_call', (data: ToolCallEvent) => {
                optionsRef.current.onToolCall?.(data);
                optionsRef.current.onAnyEvent?.({ type: 'tool_call', data });
            });
            channel.bind('tool_result', (data: ToolResultEvent) => {
                optionsRef.current.onToolResult?.(data);
                optionsRef.current.onAnyEvent?.({ type: 'tool_result', data });
            });
            channel.bind('message', (data: MessageEvent) => {
                optionsRef.current.onMessage?.(data);
                optionsRef.current.onAnyEvent?.({ type: 'message', data });
            });
            channel.bind('error', (data: ErrorEvent) => {
                optionsRef.current.onError?.(data);
                optionsRef.current.onAnyEvent?.({ type: 'error', data });
            });
            channel.bind('complete', (data: CompleteEvent) => {
                optionsRef.current.onComplete?.(data);
                optionsRef.current.onAnyEvent?.({ type: 'complete', data });
            });
            channel.bind('summarization_complete', (data: SummarizationCompleteEvent) => {
                optionsRef.current.onSummarizationComplete?.(data);
                optionsRef.current.onAnyEvent?.({ type: 'summarization_complete', data });
            });

            currentChannelRef.current = channel;
            currentChannelNameRef.current = channelName;
            setError(null);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to connect');
            setIsConnected(false);
        }
    }, [reverbConfig, enabled]);

    const unsubscribe = useCallback(() => {
        if (currentChannelNameRef.current && reverbConfig.key) {
            getPusher(reverbConfig).unsubscribe(currentChannelNameRef.current);
            currentChannelRef.current = null;
            currentChannelNameRef.current = null;
            setIsConnected(false);
        }
    }, [reverbConfig]);

    // Cleanup on unmount
    useEffect(() => {
        return () => {
            if (currentChannelNameRef.current && reverbConfig.key) {
                getPusher(reverbConfig).unsubscribe(currentChannelNameRef.current);
            }
        };
    }, [reverbConfig]);

    // Memoize return object to prevent useEffect re-runs in consumers
    return useMemo(() => ({
        isConnected,
        subscribe,
        unsubscribe,
        error,
    }), [isConnected, subscribe, unsubscribe, error]);
}
