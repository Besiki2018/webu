import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import { useShallow } from 'zustand/shallow';
import type { BuilderApiEndpoints } from '@/builder/api/builderApi';
import { saveBuilderDocument } from '@/builder/api/builderApi';
import { useBuilderStore } from '@/builder/state/builderStore';

const AUTOSAVE_DEBOUNCE_MS = 900;

export function useBuilderAutosave(endpoints: BuilderApiEndpoints) {
    const timeoutRef = useRef<number | null>(null);
    const { builderDocument, dirty, lastSavedVersion, markSaved } = useBuilderStore(useShallow((state) => ({
        builderDocument: state.builderDocument,
        dirty: state.dirty,
        lastSavedVersion: state.lastSavedVersion,
        markSaved: state.markSaved,
    })));

    useEffect(() => {
        if (! dirty || builderDocument.version === lastSavedVersion) {
            return;
        }

        if (timeoutRef.current !== null) {
            window.clearTimeout(timeoutRef.current);
        }

        timeoutRef.current = window.setTimeout(async () => {
            try {
                const savedDocument = await saveBuilderDocument(endpoints.document, builderDocument);
                markSaved(savedDocument);
            } catch (error) {
                toast.error(error instanceof Error ? error.message : 'Autosave failed');
            }
        }, AUTOSAVE_DEBOUNCE_MS);

        return () => {
            if (timeoutRef.current !== null) {
                window.clearTimeout(timeoutRef.current);
            }
        };
    }, [builderDocument, dirty, endpoints.document, lastSavedVersion, markSaved]);
}
