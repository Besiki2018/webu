import { describe, expect, it, vi, beforeEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import axios from 'axios';
import { useAiSiteEditor } from '../useAiSiteEditor';

vi.mock('axios', async (importOriginal) => {
  const actual = await importOriginal<typeof import('axios')>();
  const Axios = actual.default;
  return {
    ...actual,
    default: Object.assign(vi.fn(Axios), Axios, { post: vi.fn() }),
  };
});
const mockedAxios = vi.mocked(axios);

const { AxiosError: AxiosErrorClass } = await vi.importActual<typeof import('axios')>('axios');

describe('useAiSiteEditor.execute', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('Case 1: HTTP 200 with success:false, error_code:no_effect, diagnostic_log - returns normal failure result, not exception', async () => {
    mockedAxios.post.mockResolvedValueOnce({
      status: 200,
      data: {
        success: false,
        error: 'No changes applied.',
        error_code: 'no_effect',
        diagnostic_log: [
          '[Step 1] Resolved section hero-1',
          '[Step 2] Field not found in schema',
        ],
      },
    });

    const { result } = renderHook(() => useAiSiteEditor('proj-1'));

    let executeResult: Awaited<ReturnType<typeof result.current.execute>>;
    await act(async () => {
      executeResult = await result.current.execute(
        { operations: [{ op: 'setField', sectionId: 'hero-1', path: 'title', value: 'New' }] }
      );
    });

    expect(executeResult!.success).toBe(false);
    expect(executeResult!.error).toBe('No changes applied.');
    expect(executeResult!.error_code).toBe('no_effect');
    expect(executeResult!.diagnostic_log).toEqual([
      '[Step 1] Resolved section hero-1',
      '[Step 2] Field not found in schema',
    ]);
  });

  it('Case 2: HTTP 422 validation error goes through exception/error path with diagnostic_log preserved', async () => {
    const err = new AxiosErrorClass!(
      'Request failed with status code 422',
      'ERR_BAD_REQUEST',
      undefined,
      undefined,
      {
        status: 422,
        data: {
          message: 'Validation failed',
          errors: { path: ['Invalid path'] },
          diagnostic_log: ['[Pre-validate] Section hero-1 not found'],
        },
      }
    );
    mockedAxios.post.mockRejectedValueOnce(err);

    const { result } = renderHook(() => useAiSiteEditor('proj-1'));

    let executeResult: Awaited<ReturnType<typeof result.current.execute>>;
    await act(async () => {
      executeResult = await result.current.execute(
        { operations: [{ op: 'setField', sectionId: 'hero-1', path: 'title', value: 'New' }] }
      );
    });

    expect(executeResult!.success).toBe(false);
    expect(executeResult!.error).toBe('Validation failed');
    expect(executeResult!.diagnostic_log).toEqual(['[Pre-validate] Section hero-1 not found']);
  });

  it('Case 3: diagnostic_log preserved in both success:false (200) and 422 cases', async () => {
    const diagnosticLines = ['[Trace] Step A', '[Trace] Step B'];

    mockedAxios.post.mockResolvedValueOnce({
      status: 200,
      data: {
        success: false,
        error: 'No effect',
        error_code: 'no_effect',
        diagnostic_log: diagnosticLines,
      },
    });

    const { result } = renderHook(() => useAiSiteEditor('proj-1'));

    let r1: Awaited<ReturnType<typeof result.current.execute>>;
    await act(async () => {
      r1 = await result.current.execute({ operations: [] });
    });
    expect(r1!.diagnostic_log).toEqual(diagnosticLines);

    const err422 = new AxiosErrorClass(
      '422',
      'ERR_BAD_REQUEST',
      undefined,
      undefined,
      {
        status: 422,
        data: {
          message: 'Bad',
          diagnostic_log: diagnosticLines,
        },
      }
    );
    mockedAxios.post.mockRejectedValueOnce(err422);

    let r2: Awaited<ReturnType<typeof result.current.execute>>;
    await act(async () => {
      r2 = await result.current.execute({ operations: [] });
    });
    expect(r2!.diagnostic_log).toEqual(diagnosticLines);
  });
});
