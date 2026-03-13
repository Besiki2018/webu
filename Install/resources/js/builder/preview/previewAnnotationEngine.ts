import { annotateEditableElements, invalidateDOMMapCache } from '@/builder/domMapper';

import type { LivePreviewStructureItem } from './previewRenderSync';
import { isPreviewPlaceholderSection } from './previewPlaceholderReconciler';

const INJECTED_WRAPPER_ATTR = 'data-webu-injected';

export function injectSectionWrappersWhenMissing(
    doc: Document,
    items: LivePreviewStructureItem[],
): void {
    if (items.length === 0) {
        return;
    }

    const existing = Array.from(doc.querySelectorAll<HTMLElement>('[data-webu-section]'))
        .filter((node) => !isPreviewPlaceholderSection(node));
    if (existing.length >= items.length) {
        return;
    }

    const container =
        doc.querySelector<HTMLElement>('main') ??
        doc.querySelector<HTMLElement>('.main_content') ??
        doc.body;
    if (!container) {
        return;
    }

    const children = Array.from(container.children).filter(
        (el): el is HTMLElement => el.nodeType === Node.ELEMENT_NODE && !isPreviewPlaceholderSection(el)
    );
    if (children.length === 0) {
        return;
    }

    container.querySelectorAll<HTMLElement>(`section[${INJECTED_WRAPPER_ATTR}="true"]`).forEach((wrapper) => {
        while (wrapper.firstChild) {
            container.insertBefore(wrapper.firstChild, wrapper);
        }
        wrapper.remove();
    });

    const freshChildren = Array.from(container.children).filter(
        (el): el is HTMLElement => el.nodeType === Node.ELEMENT_NODE && !isPreviewPlaceholderSection(el)
    );
    if (freshChildren.length !== items.length) {
        return;
    }

    for (let index = 0; index < items.length; index += 1) {
        const item = items[index];
        const child = freshChildren[index];
        if (!item?.localId || !item?.sectionKey || !child) {
            continue;
        }

        const section = doc.createElement('section');
        section.setAttribute('data-webu-section', item.sectionKey);
        section.setAttribute('data-webu-section-local-id', item.localId);
        section.setAttribute(INJECTED_WRAPPER_ATTR, 'true');
        container.insertBefore(section, child);
        section.appendChild(child);
    }
}

export function applyPreviewAnnotationEngine(options: {
    iframeDoc: Document;
    liveStructureItems: LivePreviewStructureItem[];
    selectionEnabled: boolean;
    onRenderSync: () => void;
    onPlaceholderReconcile: () => void;
}): void {
    const {
        iframeDoc,
        liveStructureItems,
        selectionEnabled,
        onRenderSync,
        onPlaceholderReconcile,
    } = options;

    if (selectionEnabled && liveStructureItems.length > 0) {
        injectSectionWrappersWhenMissing(iframeDoc, liveStructureItems);
    }

    annotateEditableElements(iframeDoc, liveStructureItems);
    invalidateDOMMapCache();
    onRenderSync();
    onPlaceholderReconcile();
}
