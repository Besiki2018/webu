import { forwardRef } from 'react';
import type { PropsWithChildren } from 'react';
import { cn } from '@/lib/utils';
import type { BuilderDevicePreset } from '@/builder/state/builderStore';

interface CanvasViewportProps extends PropsWithChildren {
    devicePreset: BuilderDevicePreset;
}

const WIDTHS: Record<BuilderDevicePreset, string> = {
    desktop: 'min(1180px, 100%)',
    tablet: '820px',
    mobile: '420px',
};

export const CanvasViewport = forwardRef<HTMLDivElement, CanvasViewportProps>(function CanvasViewport(
    { devicePreset, children },
    ref,
) {
    return (
        <div className="relative flex h-full min-h-0 flex-1 items-start justify-center overflow-auto p-10">
            <div
                ref={ref}
                className={cn('relative min-h-[720px] rounded-[32px] border border-slate-300 bg-white shadow-[0_24px_80px_rgba(15,23,42,0.12)]')}
                style={{
                    width: WIDTHS[devicePreset],
                }}
            >
                <div className="grid gap-8 p-8 md:p-12">
                    {children}
                </div>
            </div>
        </div>
    );
});
