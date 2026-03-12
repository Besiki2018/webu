/**
 * Command interpreter: converts natural-language user instructions
 * into structured ChangeSet operations for editing websites.
 */
import { validateChangeSet, type ChangeSet } from '../changes/changeSet.schema';
import type { PageContext } from './context';
import { summarizePageContextForPrompt } from './context';
import { buildUserPrompt, SYSTEM_PROMPT, CHANGE_SET_JSON_EXAMPLE } from './prompts';

export interface InterpretCommandOptions {
  /** Max validation retries when AI returns invalid JSON/schema */
  maxRetries?: number;
}

export interface InterpretCommandResult {
  success: true;
  changeSet: ChangeSet;
}

export interface InterpretCommandError {
  success: false;
  error: string;
  /** Raw AI response when parse/validation failed */
  rawResponse?: string;
}

export type InterpretCommandOutput = InterpretCommandResult | InterpretCommandError;

/**
 * AI completion function: (systemPrompt, userPrompt) => Promise<string>.
 * Inject your backend AI (e.g. call to InternalAiService / OpenAI).
 */
export type AiCompleteFn = (
  systemPrompt: string,
  userPrompt: string,
  options?: { maxTokens?: number }
) => Promise<string | null>;

/**
 * Interprets a natural-language command into a ChangeSet.
 * Validates with Zod; retries up to maxRetries if invalid.
 */
export async function interpretCommand(
  userPrompt: string,
  pageContext: PageContext,
  aiComplete: AiCompleteFn,
  options: InterpretCommandOptions = {}
): Promise<InterpretCommandOutput> {
  const { maxRetries = 2 } = options;
  const trimmed = userPrompt.trim();
  if (!trimmed) {
    return { success: false, error: 'Command is required.' };
  }

  const contextSummary = summarizePageContextForPrompt(pageContext);
  const userMessage = buildUserPrompt(trimmed, contextSummary);

  let lastRaw: string | undefined;
  for (let attempt = 0; attempt <= maxRetries; attempt++) {
    const response = await aiComplete(SYSTEM_PROMPT, userMessage, { maxTokens: 4000 });
    if (response == null || response.trim() === '') {
      return {
        success: false,
        error: 'AI did not return a response. Try again.',
        rawResponse: lastRaw,
      };
    }

    lastRaw = response;
    const parsed = parseJsonChangeSet(response);
    if (parsed == null) {
      if (attempt < maxRetries) continue;
      return {
        success: false,
        error: 'Could not parse AI response as ChangeSet JSON.',
        rawResponse: response,
      };
    }

    const validated = validateChangeSet(parsed);
    if (validated.success) {
      return { success: true, changeSet: validated.data };
    }

    if (attempt < maxRetries) continue;
    return {
      success: false,
      error: validated.error.errors.map((e) => e.message).join('; ') || 'Validation failed.',
      rawResponse: response,
    };
  }

  return {
    success: false,
    error: 'Could not produce a valid ChangeSet.',
    rawResponse: lastRaw,
  };
}

/**
 * Extracts a JSON object from AI response (handles markdown code blocks and trailing text).
 */
function parseJsonChangeSet(response: string): unknown | null {
  let text = response.trim();
  const codeBlock = /```(?:json)?\s*([\s\S]*?)```/.exec(text);
  if (codeBlock) {
    text = codeBlock[1].trim();
  }
  const objectMatch = /\{[\s\S]*\}/.exec(text);
  if (objectMatch) {
    text = objectMatch[0];
  }
  try {
    const parsed = JSON.parse(text) as unknown;
    return parsed && typeof parsed === 'object' ? parsed : null;
  } catch {
    return null;
  }
}

/**
 * Build the full system prompt including schema example (for providers that support long system messages).
 */
export function getFullSystemPrompt(): string {
  return `${SYSTEM_PROMPT}

Example output format:
${CHANGE_SET_JSON_EXAMPLE}`;
}
