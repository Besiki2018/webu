import { ArrowLeft } from 'lucide-react';

import {
    DesignSystemPanel,
    generatedSystemToOverrides,
    type DesignSystemOverrides,
} from '@/builder/designSystem/DesignSystemPanel';
import { Button } from '@/components/ui/button';

type TranslationFn = (key: string, params?: Record<string, string>) => string;

interface CmsVisualBuilderDesignSystemSidebarProps {
    onChange: (overrides: DesignSystemOverrides) => void;
    onOpenElementsSidebar: () => void;
    overrides: DesignSystemOverrides;
    t: TranslationFn;
}

export function CmsVisualBuilderDesignSystemSidebar({
    onChange,
    onOpenElementsSidebar,
    overrides,
    t,
}: CmsVisualBuilderDesignSystemSidebarProps) {
    return (
        <div className="rounded-lg border p-2 space-y-2">
            <Button
                type="button"
                size="sm"
                variant="outline"
                className="w-full"
                onClick={onOpenElementsSidebar}
            >
                <ArrowLeft className="h-3.5 w-3.5 mr-1.5" />
                {t('Elements')}
            </Button>
            <p className="text-xs font-medium text-muted-foreground">{t('Design System')}</p>
            <DesignSystemPanel
                overrides={overrides}
                onChange={onChange}
                t={t}
                onRegenerate={async () => {
                    const { generateDesignSystemFromPrompt } = await import('@/builder/ai/aiBrandGenerator');
                    const system = generateDesignSystemFromPrompt('');
                    onChange(generatedSystemToOverrides(system));
                }}
            />
        </div>
    );
}
