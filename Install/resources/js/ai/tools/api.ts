/**
 * Call the backend AI tools execute endpoint.
 */

import type { AiToolContext } from './types';

export async function executeToolRequest(
  tool: string,
  args: Record<string, unknown>,
  context: AiToolContext
): Promise<{ success: boolean; error?: string; data?: unknown }> {
  const base = (context.apiBase ?? '').replace(/\/$/, '');
  const url = `${base}/panel/projects/${context.projectId}/ai-tools/execute`;
  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
    body: JSON.stringify({
      tool,
      args,
      user_prompt: context.userPrompt ?? undefined,
    }),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) {
    return { success: false, error: json.error ?? 'Request failed' };
  }
  return {
    success: json.success === true,
    error: json.error,
    data: json.data,
  };
}
