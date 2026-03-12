/**
 * Redact secrets and PII from strings and objects.
 */
const SECRET_PATTERNS = [
  /\bsk-[a-zA-Z0-9_-]{20,}\b/g,
  /\bBearer\s+[a-zA-Z0-9_.-]+\b/gi,
  /\b(?:api[_-]?key|apikey|secret|password|token|jwt)\s*[:=]\s*["']?[^\s"']+["']?/gi,
  /session[_-]?(?:id|cookie)\s*[:=]\s*["']?[^\s"']+["']?/gi,
  /\b[A-Za-z0-9+/]{40,}={0,2}\b/g,
];
const REDACT_PLACEHOLDER = '[REDACTED]';

export function redactString(value: string): string {
  let out = value;
  for (const re of SECRET_PATTERNS) {
    out = out.replace(re, REDACT_PLACEHOLDER);
  }
  return out;
}

export function redactObject<T extends Record<string, unknown>>(obj: T): T {
  const out = { ...obj } as Record<string, unknown>;
  for (const [k, v] of Object.entries(out)) {
    const keyLower = k.toLowerCase();
    if (
      keyLower.includes('key') || keyLower.includes('secret') || keyLower.includes('password') ||
      keyLower.includes('token') || keyLower.includes('cookie') || keyLower.includes('authorization')
    ) {
      out[k] = REDACT_PLACEHOLDER;
    } else if (typeof v === 'string') {
      out[k] = redactString(v);
    } else if (v && typeof v === 'object' && !Array.isArray(v)) {
      out[k] = redactObject(v as Record<string, unknown>);
    }
  }
  return out as T;
}
