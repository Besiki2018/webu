import { ReactNode } from 'react';

interface AdminPageHeaderProps {
    title: string;
    subtitle?: string;
    description?: string;
    action?: ReactNode;
}

export function AdminPageHeader({ title, subtitle, description, action }: AdminPageHeaderProps) {
    const sub = subtitle ?? description ?? '';
    return (
        <div className="flex items-center justify-between mb-6">
            <div className="prose prose-sm dark:prose-invert">
                <h1 className="text-2xl font-bold text-foreground">{title}</h1>
                {sub ? <p className="text-sm text-muted-foreground mt-1">{sub}</p> : null}
            </div>
            {action && <div>{action}</div>}
        </div>
    );
}
