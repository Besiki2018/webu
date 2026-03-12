import { Skeleton } from '@/components/ui/skeleton';

export function PreviewSkeleton() {
    return (
        <div
            className="relative flex h-full w-full items-center justify-center overflow-hidden bg-[#f5f1ea]"
            data-testid="preview-skeleton"
        >
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(255,255,255,0.9),transparent_52%)]" />
            <div className="absolute inset-6 rounded-[32px] border border-[#e7ddd0] bg-white/55" />
            <div
                className="relative z-10 mx-auto w-full max-w-md px-8"
                data-testid="browser-mockup"
            >
                <div className="rounded-lg border border-border bg-card shadow-lg overflow-hidden">
                    <div className="h-8 bg-muted/50 border-b border-border flex items-center gap-1.5 px-3">
                        <Skeleton className="h-2.5 w-2.5 rounded-full" />
                        <Skeleton className="h-2.5 w-2.5 rounded-full" />
                        <Skeleton className="h-2.5 w-2.5 rounded-full" />
                        <Skeleton className="h-4 w-32 rounded ml-4" />
                    </div>
                    <div className="p-4 space-y-3">
                        <Skeleton className="h-6 w-3/4" />
                        <Skeleton className="h-4 w-full" />
                        <Skeleton className="h-4 w-5/6" />
                        <Skeleton className="h-4 w-4/5" />
                        <div className="pt-2">
                            <Skeleton className="h-8 w-24 rounded-md" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
