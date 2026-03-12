import { Component, type ErrorInfo, type ReactNode } from 'react';

interface BugfixerErrorBoundaryProps {
    children: ReactNode;
    fallback?: ReactNode;
}

interface BugfixerErrorBoundaryState {
    hasError: boolean;
    error: Error | null;
}

/**
 * Global error boundary that captures React runtime errors and reports them
 * to the bugfixer backend (audit/bugfixer/events) for the self-healing pipeline.
 */
export class BugfixerErrorBoundary extends Component<BugfixerErrorBoundaryProps, BugfixerErrorBoundaryState> {
    constructor(props: BugfixerErrorBoundaryProps) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error: Error): Partial<BugfixerErrorBoundaryState> {
        return { hasError: true, error };
    }

    componentDidCatch(error: Error, errorInfo: ErrorInfo): void {
        const now = Date.now();
        const last = (BugfixerErrorBoundary as { _lastReport?: number })._lastReport ?? 0;
        if (now - last < 10000) return;
        (BugfixerErrorBoundary as { _lastReport?: number })._lastReport = now;
        const payload = {
            message: error.message,
            stack: error.stack ?? undefined,
            route: typeof window !== 'undefined' ? window.location.pathname + window.location.search : undefined,
            componentStack: errorInfo.componentStack ?? undefined,
            projectId: (window as Window & { __WEBU_PROJECT_ID__?: string }).__WEBU_PROJECT_ID__ ?? undefined,
            sectionId: (window as Window & { __WEBU_SECTION_ID__?: string }).__WEBU_SECTION_ID__ ?? undefined,
        };
        try {
            fetch('/api/bugfixer/report', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(payload),
                credentials: 'same-origin',
            }).catch(() => {});
        } catch {
            // ignore report failures
        }
    }

    render(): ReactNode {
        if (this.state.hasError && this.state.error) {
            if (this.props.fallback) return this.props.fallback;
            return (
                <div className="min-h-[200px] flex items-center justify-center p-6 bg-muted/50 rounded-lg border border-destructive/30">
                    <div className="text-center space-y-2">
                        <p className="font-medium text-destructive">Something went wrong</p>
                        <p className="text-sm text-muted-foreground max-w-md">{this.state.error.message}</p>
                        <p className="text-xs text-muted-foreground">This error has been reported for debugging.</p>
                    </div>
                </div>
            );
        }
        return this.props.children;
    }
}
