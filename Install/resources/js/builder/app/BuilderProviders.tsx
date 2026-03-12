import { PropsWithChildren, useEffect } from 'react';
import type { BuilderDocument } from '@/builder/types/builderDocument';
import type { BuilderApiEndpoints } from '@/builder/api/builderApi';
import { useBuilderAutosave } from '@/builder/persistence/useBuilderAutosave';
import { useBuilderStore } from '@/builder/state/builderStore';

interface BuilderProvidersProps extends PropsWithChildren {
    initialDocument: BuilderDocument;
    publishedDocument?: BuilderDocument | null;
    endpoints: BuilderApiEndpoints;
}

export function BuilderProviders({
    initialDocument,
    publishedDocument = null,
    endpoints,
    children,
}: BuilderProvidersProps) {
    const initialize = useBuilderStore((state) => state.initialize);

    useEffect(() => {
        initialize(initialDocument, publishedDocument);
    }, [initialDocument, initialize, publishedDocument]);

    useBuilderAutosave(endpoints);

    return <>{children}</>;
}
