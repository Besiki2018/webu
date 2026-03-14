import { act, renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { resetBuilderEditingStore } from '@/builder/state/builderEditingStore';
import { useBuilderStore } from '@/builder/store/builderStore';
import axios from 'axios';

const mocks = vi.hoisted(() => ({
    pusherOptions: null as Record<string, ((payload: unknown) => void) | undefined> | null,
    reverbOptions: null as Record<string, ((payload: unknown) => void) | undefined> | null,
    runGenerateSite: vi.fn(),
}));

vi.mock('../useBuilderPusher', () => ({
    useBuilderPusher: (options: Record<string, unknown>) => {
        mocks.pusherOptions = options as typeof mocks.pusherOptions;
        return {
            isConnected: false,
            subscribe: vi.fn(),
            unsubscribe: vi.fn(),
            error: null,
        };
    },
}));

vi.mock('../useBuilderReverb', () => ({
    useBuilderReverb: (options: Record<string, unknown>) => {
        mocks.reverbOptions = options as typeof mocks.reverbOptions;
        return {
            isConnected: false,
            subscribe: vi.fn(),
            unsubscribe: vi.fn(),
            error: null,
        };
    },
}));

vi.mock('@/builder/commands/generateSite', () => ({
    GENERATE_SITE_COMMAND: 'generate_site',
    runGenerateSite: mocks.runGenerateSite,
}));

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        isAxiosError: vi.fn(() => false),
    },
}));

import { useBuilderChat } from '../useBuilderChat';

const mockPusherConfig = {
    provider: 'pusher' as const,
    key: 'test-key',
    cluster: 'mt1',
};

function emitToolCall(payload: Record<string, unknown>): void {
    act(() => {
        mocks.pusherOptions?.onToolCall?.(payload);
    });
}

function emitToolResult(payload: Record<string, unknown>): void {
    act(() => {
        mocks.pusherOptions?.onToolResult?.(payload);
    });
}

describe('useBuilderChat generate_site handling', () => {
    beforeEach(() => {
        mocks.pusherOptions = null;
        mocks.reverbOptions = null;
        mocks.runGenerateSite.mockReset();
        useBuilderStore.getState().reset();
        resetBuilderEditingStore();
        vi.mocked(axios.get).mockReset();
        vi.mocked(axios.post).mockReset();
        vi.mocked(axios.isAxiosError).mockImplementation(() => false);
        vi.mocked(localStorage.getItem).mockReturnValue(null);
        vi.mocked(localStorage.setItem).mockReset();
        vi.mocked(localStorage.removeItem).mockReset();
    });

    it('drops stale preview and builder tree when a new generation scope mounts', async () => {
        useBuilderStore.setState({
            componentTree: [{
                id: 'hero-old',
                componentKey: 'webu_general_hero_01',
                props: {},
                children: [],
            }],
        });

        const { result } = renderHook(() => useBuilderChat('1', {
            pusherConfig: mockPusherConfig,
            initialPreviewUrl: '/preview/old-project',
            initialPreviewBuildId: 'old-build',
            initialProjectGenerationVersion: 'new-generation',
            initialSourceGenerationType: 'new',
        }));

        await waitFor(() => {
            expect(result.current.progress.previewUrl).toBeNull();
        });

        expect(result.current.progress.previewBuildId).toBe('old-build');
        expect(useBuilderStore.getState().componentTree).toEqual([]);
    });

    it('shows a clear generation error when generate_site cannot build without blueprint or structure', async () => {
        const onBuildError = vi.fn();
        const onError = vi.fn();
        mocks.runGenerateSite.mockReturnValue({
            ok: false,
            projectType: 'landing',
            nodeCount: 0,
            trace: {
                requestedMode: null,
                resolvedMode: 'error',
                projectType: 'landing',
                hasBlueprint: false,
                structureCount: 0,
            },
            error: 'Generation failed: no site blueprint or direct structure was provided. Emergency fallback must be requested explicitly.',
        });

        const { result } = renderHook(() => useBuilderChat('1', {
            pusherConfig: mockPusherConfig,
            onBuildError,
            onError,
        }));

        emitToolCall({
            id: 'tool-1',
            tool: 'generate_site',
            params: {},
        });
        emitToolResult({
            id: 'tool-1',
            tool: 'generate_site',
            success: true,
            output: '{}',
        });

        await waitFor(() => {
            expect(result.current.progress.status).toBe('failed');
        });

        expect(result.current.progress.error).toBe('Generation failed: missing blueprint or structure');
        expect(result.current.messages.some((message) => (
            message.type === 'assistant'
            && message.content === 'Generation failed: missing blueprint or structure'
        ))).toBe(true);
        expect(mocks.runGenerateSite).not.toHaveBeenCalled();
        expect(onBuildError).toHaveBeenCalledWith('Generation failed: missing blueprint or structure');
        expect(onError).toHaveBeenCalledWith('Generation failed: missing blueprint or structure');
    });

    it('adds repair guidance when generated output fails validation', async () => {
        mocks.runGenerateSite.mockReturnValue({
            ok: false,
            projectType: 'landing',
            nodeCount: 0,
            trace: {
                requestedMode: 'blueprint',
                resolvedMode: 'error',
                projectType: 'landing',
                hasBlueprint: true,
                structureCount: 0,
            },
            error: 'Generated site validation failed: Unknown component key "broken_hero" at planned section 2.',
            diagnostics: {
                prompt: 'Create a landing page',
                generationMode: 'blueprint',
                selectedProjectType: 'landing',
                selectedBusinessType: 'Vet clinic',
                selectedSectionTypes: ['header', 'hero', 'footer'],
                validationPassed: false,
                emergencyFallbackUsed: false,
                selectedSections: ['header', 'hero', 'footer'],
                selectedComponentKeys: ['webu_header_01', 'broken_hero', 'webu_footer_01'],
                fallbackUsed: false,
                failedStep: 'validation',
                rootCause: 'Generated site validation failed: Unknown component key "broken_hero" at planned section 2.',
                events: [],
            },
        });

        const { result } = renderHook(() => useBuilderChat('1', {
            pusherConfig: mockPusherConfig,
        }));

        emitToolCall({
            id: 'tool-validation',
            tool: 'generate_site',
            params: {
                blueprint: { projectType: 'landing' },
            },
        });
        emitToolResult({
            id: 'tool-validation',
            tool: 'generate_site',
            success: true,
            output: '{}',
        });

        await waitFor(() => {
            expect(result.current.progress.status).toBe('failed');
        });

        expect(result.current.messages.some((message) => (
            message.type === 'assistant'
            && message.content.includes('Ask me to repair the structure and retry.')
        ))).toBe(true);
        expect(result.current.progress.generationDiagnostics?.failedStep).toBe('validation');
        expect(result.current.progress.generationDiagnostics?.selectedComponentKeys).toContain('broken_hero');
    });

    it('records the generation path when generate_site succeeds', async () => {
        mocks.runGenerateSite.mockReturnValue({
            ok: true,
            projectType: 'saas',
            nodeCount: 6,
            generationMode: 'blueprint',
            trace: {
                requestedMode: null,
                resolvedMode: 'blueprint',
                projectType: 'saas',
                hasBlueprint: true,
                structureCount: 0,
            },
            diagnostics: {
                prompt: 'Create a SaaS landing page',
                generationMode: 'blueprint',
                selectedProjectType: 'saas',
                selectedBusinessType: 'Finance Software',
                selectedSectionTypes: ['header', 'hero', 'features', 'footer'],
                validationPassed: true,
                emergencyFallbackUsed: false,
                selectedSections: ['header', 'hero', 'features', 'footer'],
                selectedComponentKeys: ['webu_header_01', 'webu_general_hero_01', 'webu_general_features_01', 'webu_footer_01'],
                fallbackUsed: false,
                failedStep: null,
                rootCause: null,
                events: [
                    {
                        step: 'prompt',
                        status: 'info',
                        message: 'prompt received',
                        payload: { prompt: 'Create a SaaS landing page' },
                    },
                ],
            },
        });

        const { result } = renderHook(() => useBuilderChat('1', {
            pusherConfig: mockPusherConfig,
        }));

        emitToolCall({
            id: 'tool-2',
            tool: 'generate_site',
            params: {
                blueprint: { projectType: 'saas' },
            },
        });
        emitToolResult({
            id: 'tool-2',
            tool: 'generate_site',
            success: true,
            output: '{}',
        });

        await waitFor(() => {
            expect(result.current.progress.statusMessage).toBe('Generated via blueprint pipeline');
        });

        expect(result.current.progress.error).toBeNull();
        expect(result.current.messages.some((message) => (
            message.type === 'activity'
            && message.content === 'Generated via blueprint pipeline'
        ))).toBe(true);
        expect(result.current.progress.generationDiagnostics?.selectedProjectType).toBe('saas');
        expect(result.current.progress.generationDiagnostics?.selectedBusinessType).toBe('Finance Software');
    });

    it('marks structure-only generation as legacy and resets the source type for the new build', async () => {
        mocks.runGenerateSite.mockReturnValue({
            ok: true,
            projectType: 'landing',
            nodeCount: 3,
            generationMode: 'direct-structure',
            trace: {
                requestedMode: null,
                resolvedMode: 'direct-structure',
                projectType: 'landing',
                hasBlueprint: false,
                structureCount: 3,
            },
            diagnostics: {
                prompt: 'Legacy structure',
                generationMode: 'direct-structure',
                selectedProjectType: 'landing',
                selectedBusinessType: null,
                selectedSectionTypes: ['header', 'hero', 'footer'],
                validationPassed: true,
                emergencyFallbackUsed: false,
                selectedSections: ['header', 'hero', 'footer'],
                selectedComponentKeys: ['webu_header_01', 'webu_general_hero_01', 'webu_footer_01'],
                fallbackUsed: false,
                failedStep: null,
                rootCause: null,
                events: [],
            },
        });

        const { result } = renderHook(() => useBuilderChat('1', {
            pusherConfig: mockPusherConfig,
        }));

        emitToolCall({
            id: 'tool-legacy',
            tool: 'generate_site',
            params: {
                structure: [
                    { componentKey: 'webu_header_01' },
                    { componentKey: 'webu_general_hero_01' },
                    { componentKey: 'webu_footer_01' },
                ],
            },
        });
        emitToolResult({
            id: 'tool-legacy',
            tool: 'generate_site',
            success: true,
            output: '{}',
        });

        await waitFor(() => {
            expect(result.current.progress.statusMessage).toBe('Generated via legacy structure path');
        });

        expect(result.current.progress.sourceGenerationType).toBe('legacy');
        expect(result.current.messages.some((message) => (
            message.type === 'activity'
            && message.content === 'Generated via legacy structure path'
        ))).toBe(true);
        expect(mocks.runGenerateSite).toHaveBeenCalledWith({
            structure: [
                { componentKey: 'webu_header_01' },
                { componentKey: 'webu_general_hero_01' },
                { componentKey: 'webu_footer_01' },
            ],
        });
    });

    it('ignores tool results from a stale build after a new build starts', async () => {
        const mockedAxios = vi.mocked(axios);
        mockedAxios.post.mockResolvedValueOnce({
            data: { session_id: 'build-new', build_id: 'build-new' },
        });

        const { result } = renderHook(() => useBuilderChat('1', {
            pusherConfig: mockPusherConfig,
            initialPreviewUrl: '/preview/old-project',
            initialBuildId: 'build-old',
            initialPreviewBuildId: 'build-old',
            initialProjectGenerationVersion: 'generation-1',
            initialSourceGenerationType: 'new',
        }));

        await act(async () => {
            await result.current.sendMessage('Create a completely different site');
        });

        expect(result.current.progress.previewUrl).toBeNull();
        expect(result.current.progress.buildId).toBe('build-new');

        emitToolCall({
            id: 'tool-old',
            tool: 'generate_site',
            build_id: 'build-old',
            session_id: 'build-old',
            params: {
                blueprint: { projectType: 'business' },
            },
        });
        emitToolResult({
            id: 'tool-old',
            tool: 'generate_site',
            build_id: 'build-old',
            session_id: 'build-old',
            success: true,
            output: '{}',
        });

        expect(mocks.runGenerateSite).not.toHaveBeenCalled();
        expect(result.current.progress.toolCalls).toEqual([]);
        expect(result.current.progress.toolResults).toEqual([]);
    });

    it('resets preview, pending tool calls, and diagnostics when a new build starts', async () => {
        const mockedAxios = vi.mocked(axios);
        mockedAxios.post.mockResolvedValueOnce({
            data: { session_id: 'build-fresh', build_id: 'build-fresh' },
        });

        const { result } = renderHook(() => useBuilderChat('1', {
            pusherConfig: mockPusherConfig,
            initialPreviewUrl: '/preview/stale',
            initialBuildId: 'build-stale',
            initialPreviewBuildId: 'build-stale',
            initialProjectGenerationVersion: 'generation-stale',
            initialSourceGenerationType: 'new',
        }));

        emitToolCall({
            id: 'old-tool',
            tool: 'generate_site',
            build_id: 'build-stale',
            session_id: 'build-stale',
            params: {
                blueprint: { projectType: 'landing' },
            },
        });

        expect(result.current.progress.toolCalls).toHaveLength(1);

        await act(async () => {
            await result.current.sendMessage('Create a fresh build');
        });

        expect(result.current.progress.previewUrl).toBeNull();
        expect(result.current.progress.toolCalls).toEqual([]);
        expect(result.current.progress.toolResults).toEqual([]);
        expect(result.current.progress.buildId).toBe('build-fresh');
        expect(result.current.progress.generationDiagnostics?.events.some((entry) => (
            entry.step === 'session' && entry.message === 'build session requested'
        ))).toBe(true);
    });

    it('retries preview generation once after a timeout and keeps diagnostics for the retry path', async () => {
        const mockedAxios = vi.mocked(axios);
        const onBuildComplete = vi.fn();
        const timeoutError = {
            code: 'ECONNABORTED',
            message: 'timeout of 30000ms exceeded',
        };

        mockedAxios.get.mockResolvedValue({
            data: { online: true },
        });
        mockedAxios.post
            .mockRejectedValueOnce(timeoutError)
            .mockResolvedValueOnce({
                data: {
                    preview_url: '/preview/1?retry=1',
                    preview_build_id: 'preview-build-2',
                },
            });
        vi.mocked(axios.isAxiosError).mockImplementation((value) => value === timeoutError);

        const { result } = renderHook(() => useBuilderChat('1', {
            pusherConfig: mockPusherConfig,
            initialBuildId: 'preview-build-2',
            onBuildComplete,
        }));

        await act(async () => {
            await result.current.triggerBuild();
        });

        expect(mockedAxios.post).toHaveBeenNthCalledWith(
            1,
            '/builder/projects/1/build',
            undefined,
            expect.objectContaining({ timeout: expect.any(Number) }),
        );
        expect(mockedAxios.post).toHaveBeenNthCalledWith(
            2,
            '/builder/projects/1/build/retry',
            undefined,
            expect.objectContaining({ timeout: expect.any(Number) }),
        );
        expect(result.current.progress.generationDiagnostics?.events.some((entry) => (
            entry.step === 'preview' && entry.message === 'partial generation retry scheduled'
        ))).toBe(true);
        expect(result.current.progress.generationDiagnostics?.events.some((entry) => (
            entry.step === 'preview' && entry.message === 'preview rendered'
        ))).toBe(true);
        expect(onBuildComplete).toHaveBeenCalledWith('/preview/1?retry=1');
    });

    it('ignores a late start response from an older build request', async () => {
        const mockedAxios = vi.mocked(axios);
        let resolveFirstRequest: ((value: unknown) => void) | null = null;

        mockedAxios.post
            .mockImplementationOnce(() => new Promise((resolve) => {
                resolveFirstRequest = resolve;
            }))
            .mockResolvedValueOnce({
                data: { session_id: 'build-second', build_id: 'build-second' },
            });

        const { result } = renderHook(() => useBuilderChat('1', {
            pusherConfig: mockPusherConfig,
        }));

        await act(async () => {
            const first = result.current.sendMessage('First build');
            const second = result.current.sendMessage('Second build');
            await second;
            expect(resolveFirstRequest).not.toBeNull();
            resolveFirstRequest?.({
                data: { session_id: 'build-first', build_id: 'build-first' },
            });
            await first;
        });

        expect(result.current.progress.buildId).toBe('build-second');
        expect(result.current.progress.statusMessage).toBe('Connected. Preparing response...');
    });
});
