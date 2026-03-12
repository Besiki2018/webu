/**
 * Tab 9 A2: In dev mode, intercept console.error and console.warn and report to bugfixer.
 * Does not replace the original console; calls it then sends payload to /api/bugfixer/report.
 */
function serializeArgs(args: unknown[]): string {
    return args
        .map((a) => {
            if (a instanceof Error) return a.message + (a.stack ? '\n' + a.stack : '');
            if (typeof a === 'object' && a !== null) return JSON.stringify(a);
            return String(a);
        })
        .join(' ');
}

const BUGFIXER_THROTTLE_MS = 5000;
let lastReportTime = 0;
let reportQueue: string | null = null;

function reportToBugfixer(level: 'error' | 'warn', args: unknown[]): void {
    const message = serializeArgs(args);
    if (!message.trim()) return;
    if (message.includes('[WebuInspect]')) return;
    const now = Date.now();
    if (now - lastReportTime < BUGFIXER_THROTTLE_MS) {
        reportQueue = `[console.${level}] ${message.slice(0, 1500)}`;
        return;
    }
    lastReportTime = now;
    const toSend = reportQueue ?? `[console.${level}] ${message.slice(0, 1500)}`;
    reportQueue = null;
    try {
        fetch('/api/bugfixer/report', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({
                message: toSend,
                route: typeof window !== 'undefined' ? window.location.pathname + window.location.search : undefined,
            }),
            credentials: 'same-origin',
        }).catch(() => {});
    } catch {
        // ignore
    }
}

export function installConsoleInterceptor(): void {
    if (typeof window === 'undefined') return;
    if (import.meta.env.PROD) return;

    const origError = console.error;
    const origWarn = console.warn;

    console.error = function (...args: unknown[]) {
        origError.apply(console, args);
        reportToBugfixer('error', args);
    };
    console.warn = function (...args: unknown[]) {
        origWarn.apply(console, args);
        reportToBugfixer('warn', args);
    };
}
