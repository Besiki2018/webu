import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import { useSessionReconnection } from '../useSessionReconnection';
import axios from 'axios';

vi.mock('axios');

describe('useSessionReconnection', () => {
    const mockedAxios = vi.mocked(axios);
    const projectId = 'project-123';

    beforeEach(() => {
        mockedAxios.get.mockReset();
    });

    it('429 backoff: checkSessionStatus does not retry until backoff has passed', async () => {
        mockedAxios.get.mockRejectedValueOnce({
            isAxiosError: true,
            response: { status: 429, headers: { 'retry-after': '2' } },
        });
        vi.mocked(axios.isAxiosError).mockReturnValue(true);

        const { result } = renderHook(() =>
            useSessionReconnection({
                projectId,
                initialSessionId: null,
                initialCanReconnect: false,
            })
        );

        await act(async () => {
            await result.current.checkSessionStatus();
        });
        expect(mockedAxios.get).toHaveBeenCalledTimes(1);

        await act(async () => {
            await result.current.checkSessionStatus();
        });
        expect(mockedAxios.get).toHaveBeenCalledTimes(1);
    });

    it('reconnect path: onReconnected called when checkSessionStatus returns active session', async () => {
        mockedAxios.get.mockResolvedValueOnce({
            data: {
                has_session: true,
                can_reconnect: true,
                build_session_id: 'session-456',
                status: 'running',
                preview_url: '/preview/123',
            },
        });

        const onReconnected = vi.fn();
        const { result } = renderHook(() =>
            useSessionReconnection({
                projectId,
                initialSessionId: null,
                initialCanReconnect: false,
                onReconnected,
            })
        );

        await act(async () => {
            await result.current.reconnect();
        });

        await waitFor(() => {
            expect(onReconnected).toHaveBeenCalledWith(
                expect.objectContaining({
                    sessionId: 'session-456',
                    status: 'running',
                    canReconnect: true,
                    previewUrl: '/preview/123',
                })
            );
        });
    });

    it('reconnect path: onSessionNotFound called when no active session', async () => {
        mockedAxios.get.mockResolvedValueOnce({
            data: { has_session: false, can_reconnect: false },
        });

        const onSessionNotFound = vi.fn();
        const { result } = renderHook(() =>
            useSessionReconnection({
                projectId,
                initialSessionId: null,
                initialCanReconnect: false,
                onSessionNotFound,
            })
        );

        await act(async () => {
            await result.current.reconnect();
        });

        await waitFor(() => {
            expect(onSessionNotFound).toHaveBeenCalled();
        });
    });
});
