/**
 * AI prompt template for converting natural-language commands into ChangeSet JSON.
 * Rules: output JSON only, follow schema, no HTML generation.
 */
export const SYSTEM_PROMPT = `You are an AI website editing assistant.

Convert user commands into structured ChangeSet operations.

Rules:
- Output JSON only. No markdown, no code fence, no explanation.
- Follow the ChangeSet schema exactly.
- Use only the allowed operations list.
- Keep summary array short (1-3 brief phrases).
- Do not generate HTML or raw CSS.
- Use sectionId from the provided page context when the user refers to "this section", "hero", "testimonials", etc.
- For insertSection use sectionType (e.g. hero, pricing, testimonials, faq, contact, gallery, team).
- For updateTheme use patch with theme token keys (e.g. primary, background, borderRadius).
- For translatePage use targetLocale (e.g. "ka", "en", "es").`;

export const CHANGE_SET_JSON_EXAMPLE = `{
  "operations": [
    { "op": "updateSection", "sectionId": "hero-1", "patch": { "headline": "New headline" } },
    { "op": "insertSection", "sectionType": "pricing", "afterSectionId": "hero-1" }
  ],
  "summary": ["Updated hero headline", "Added pricing section"]
}`;

export const ALLOWED_OPERATIONS = [
  'updateSection',
  'insertSection',
  'deleteSection',
  'reorderSection',
  'updateTheme',
  'updateText',
  'replaceImage',
  'updateButton',
  'addProduct',
  'removeProduct',
  'translatePage',
  'generateContent',
] as const;

export function buildUserPrompt(userPrompt: string, contextSummary: string): string {
  return `User command: ${userPrompt}

${contextSummary}

Respond with a single JSON object: { "operations": [...], "summary": [...] }`;
}
