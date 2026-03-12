import type { BuilderDocument } from '@/builder/types/builderDocument';
import { cloneBuilderDocument } from '@/builder/utils/document';

export function serializeHistoryDocument(document: BuilderDocument): BuilderDocument {
    return cloneBuilderDocument(document);
}
