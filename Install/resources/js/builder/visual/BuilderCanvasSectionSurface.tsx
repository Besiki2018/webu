import { useCallback, useEffect, useMemo, useRef } from 'react';
import type { MouseEvent as ReactMouseEvent, ReactNode } from 'react';

import { buildSectionScopedEditableTarget, type BuilderEditableTarget } from '@/builder/editingState';
import { buildElementId, getComponentShortName } from '@/builder/componentParameterMetadata';
import type { BuilderComponentRuntimeEntry } from '@/builder/componentRegistry';

export interface BuilderCanvasSectionSurfaceProps {
    sectionLocalId: string;
    sectionKey: string;
    displayLabel: string;
    runtimeEntry: BuilderComponentRuntimeEntry | null;
    selectedTarget: BuilderEditableTarget | null;
    hoveredTarget: BuilderEditableTarget | null;
    onSelectTarget?: (target: BuilderEditableTarget) => void;
    onHoverTarget?: (target: BuilderEditableTarget | null) => void;
    children: ReactNode;
}

interface OverlayState {
    label: string;
    rect: { top: number; left: number; width: number; height: number };
}

function escapeAttributeValue(value: string): string {
    return value.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
}

function buildOverlayLabel(target: BuilderEditableTarget | null, fallbackComponentName: string): string | null {
    if (!target) {
        return null;
    }

    const componentName = target.componentName ?? fallbackComponentName;
    const fieldName = target.fieldLabel ?? target.path;

    return fieldName ? `${componentName}.${fieldName}` : componentName;
}

function buildOverlayState(root: HTMLElement, target: BuilderEditableTarget | null, fallbackComponentName: string): OverlayState | null {
    const builderId = target?.builderId;
    const label = buildOverlayLabel(target, fallbackComponentName);
    if (!builderId || !label) {
        return null;
    }

    const node = root.dataset.builderId === builderId
        ? root
        : root.querySelector<HTMLElement>(`[data-builder-id="${escapeAttributeValue(builderId)}"]`);
    if (!node) {
        return null;
    }

    const rootRect = root.getBoundingClientRect();
    const rect = node.getBoundingClientRect();

    return {
        label,
        rect: {
            top: rect.top - rootRect.top,
            left: rect.left - rootRect.left,
            width: rect.width,
            height: rect.height,
        },
    };
}

function annotateEditableNodes(
    root: HTMLElement,
    runtimeEntry: BuilderComponentRuntimeEntry | null,
    sectionLocalId: string,
    sectionKey: string,
    displayLabel: string
): void {
    const editableFields = runtimeEntry?.schema.editableFields ?? runtimeEntry?.schema.fields.map((field) => field.path) ?? [];
    const componentName = runtimeEntry?.displayName ?? displayLabel;
    const shortName = getComponentShortName(sectionKey);
    const fieldDefinitions = runtimeEntry?.schema.fields ?? [];
    const fieldIndexByPath = new Map<string, number>();

    root.dataset.builderChrome = 'false';
    root.dataset.builderId = sectionLocalId;
    root.dataset.builderSectionId = sectionLocalId;
    root.dataset.builderInstanceId = sectionLocalId;
    root.dataset.builderComponentType = sectionKey;
    root.dataset.builderComponentName = componentName;
    root.dataset.builderEditableFields = editableFields.join(',');

    Array.from(root.querySelectorAll<HTMLElement>('[data-builder-chrome="true"]')).forEach((node) => {
        node.dataset.builderChrome = 'true';
    });

    const nodes = Array.from(root.querySelectorAll<HTMLElement>('[data-webu-field-scope], [data-webu-field], [data-webu-field-url]'));
    nodes.forEach((node) => {
        const exactFieldPath = (node.getAttribute('data-webu-field') || node.getAttribute('data-webu-field-url') || '').trim();
        const scopePath = (node.getAttribute('data-webu-field-scope') || '').trim();
        const parentScopeEl = node.parentElement?.closest<HTMLElement>('[data-webu-field-scope]');
        const parentScopePath = (parentScopeEl?.getAttribute('data-webu-field-scope') || '').trim();
        const primaryPath = (() => {
            if (exactFieldPath && parentScopePath && exactFieldPath !== parentScopePath && !exactFieldPath.startsWith(`${parentScopePath}.`)) {
                return `${parentScopePath}.${exactFieldPath}`;
            }
            return exactFieldPath || scopePath;
        })();
        if (primaryPath === '') {
            return;
        }

        const siblingPaths = Array.from(new Set([
            node.getAttribute('data-webu-field'),
            node.getAttribute('data-webu-field-url'),
        ].map((item) => (item ?? '').trim()).filter(Boolean)));
        const fieldDefinition = fieldDefinitions.find((field) => field.path === primaryPath)
            ?? fieldDefinitions
                .filter((field) => primaryPath === field.path || primaryPath.startsWith(`${field.path}.`))
                .sort((left, right) => right.path.length - left.path.length)[0]
            ?? fieldDefinitions.find((field) => siblingPaths.includes(field.path))
            ?? null;
        const occurrenceKey = `${primaryPath}::${exactFieldPath ? 'field' : 'scope'}`;
        const occurrence = fieldIndexByPath.get(occurrenceKey) ?? 0;
        fieldIndexByPath.set(occurrenceKey, occurrence + 1);

        const builderId = `${sectionLocalId}::${exactFieldPath ? 'field' : 'scope'}::${primaryPath}::${occurrence}`;
        const parentEditable = node.parentElement?.closest<HTMLElement>('[data-builder-id][data-builder-path]');
        const elementId = buildElementId(sectionKey, primaryPath);

        node.dataset.builderChrome = 'false';
        node.dataset.builderId = builderId;
        node.dataset.builderPath = primaryPath;
        node.dataset.builderScopePath = scopePath || primaryPath;
        node.dataset.builderSectionId = sectionLocalId;
        node.dataset.builderInstanceId = builderId;
        node.dataset.builderParentId = parentEditable?.dataset.builderId ?? sectionLocalId;
        node.dataset.builderComponentType = sectionKey;
        node.dataset.builderComponentName = componentName;
        node.dataset.builderEditableFields = siblingPaths.length > 0 ? siblingPaths.join(',') : editableFields.join(',');
        node.dataset.builderElementId = elementId;
        node.dataset.builderShortName = shortName;
        node.dataset.builderFieldLabel = fieldDefinition?.label ?? primaryPath;
        node.dataset.builderFieldGroup = fieldDefinition?.group ?? 'content';
    });
}

function SelectionOverlay({
    state,
    tone,
}: {
    state: OverlayState | null;
    tone: 'hover' | 'selected';
}) {
    if (!state) {
        return null;
    }

    const outline = tone === 'selected' ? 'rgba(15, 23, 42, 0.95)' : 'rgba(79, 70, 229, 0.95)';
    const background = tone === 'selected' ? 'rgba(15, 23, 42, 0.08)' : 'rgba(79, 70, 229, 0.08)';
    const labelBackground = tone === 'selected' ? 'rgba(15, 23, 42, 0.96)' : 'rgba(79, 70, 229, 0.96)';

    return (
        <div
            className="pointer-events-none absolute inset-0 z-20"
            data-builder-chrome="true"
            aria-hidden="true"
        >
            <div
                className="absolute rounded-md shadow-[0_0_0_1px_rgba(255,255,255,0.7)]"
                style={{
                    top: `${state.rect.top}px`,
                    left: `${state.rect.left}px`,
                    width: `${Math.max(state.rect.width, 8)}px`,
                    height: `${Math.max(state.rect.height, 8)}px`,
                    outline: `2px solid ${outline}`,
                    outlineOffset: '1px',
                    background,
                }}
            />
            <div
                className="absolute max-w-[220px] truncate rounded-full px-2 py-1 text-[10px] font-semibold tracking-wide text-white shadow-lg"
                style={{
                    top: `${Math.max(state.rect.top - 26, 4)}px`,
                    left: `${Math.max(state.rect.left, 4)}px`,
                    background: labelBackground,
                }}
            >
                {state.label}
            </div>
        </div>
    );
}

export function BuilderCanvasSectionSurface({
    sectionLocalId,
    sectionKey,
    displayLabel,
    runtimeEntry,
    selectedTarget,
    hoveredTarget,
    onSelectTarget,
    onHoverTarget,
    children,
}: BuilderCanvasSectionSurfaceProps) {
    const rootRef = useRef<HTMLDivElement | null>(null);
    const lastHoveredTargetIdRef = useRef<string | null>(null);
    const componentName = runtimeEntry?.displayName ?? displayLabel;
    const sectionTarget = useMemo(() => buildSectionScopedEditableTarget({
        sectionLocalId,
        sectionKey,
        componentType: sectionKey,
        componentName,
        textPreview: displayLabel,
    }), [componentName, displayLabel, sectionKey, sectionLocalId]);

    useEffect(() => {
        const root = rootRef.current;
        if (!root) {
            return;
        }

        annotateEditableNodes(root, runtimeEntry, sectionLocalId, sectionKey, displayLabel);
    }, [displayLabel, runtimeEntry, sectionKey, sectionLocalId]);

    const hoveredOverlay = useMemo(() => {
        const root = rootRef.current;
        if (!root || hoveredTarget?.sectionLocalId !== sectionLocalId) {
            return null;
        }

        return buildOverlayState(root, hoveredTarget, componentName);
    }, [componentName, hoveredTarget, sectionLocalId]);

    const selectedOverlay = useMemo(() => {
        const root = rootRef.current;
        if (!root || selectedTarget?.sectionLocalId !== sectionLocalId) {
            return null;
        }

        return buildOverlayState(root, selectedTarget, componentName);
    }, [componentName, sectionLocalId, selectedTarget]);

    const handleMouseMoveCapture = useCallback((event: ReactMouseEvent<HTMLDivElement>) => {
        if (!(event.target instanceof HTMLElement) || event.target.closest('[data-builder-chrome="true"]')) {
            return;
        }

        const nextTarget = sectionTarget;
        const nextTargetId = nextTarget?.targetId ?? null;
        if (lastHoveredTargetIdRef.current === nextTargetId) {
            return;
        }

        lastHoveredTargetIdRef.current = nextTargetId;
        onHoverTarget?.(nextTarget);
    }, [onHoverTarget, sectionTarget]);

    const handleMouseLeave = useCallback(() => {
        lastHoveredTargetIdRef.current = null;
        onHoverTarget?.(null);
    }, [onHoverTarget]);

    const handleClickCapture = useCallback((event: ReactMouseEvent<HTMLDivElement>) => {
        if (!(event.target instanceof HTMLElement) || event.target.closest('[data-builder-chrome="true"]')) {
            return;
        }

        const nextTarget = sectionTarget;
        if (!nextTarget) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        onSelectTarget?.(nextTarget);
    }, [onSelectTarget, sectionTarget]);

    return (
        <div
            ref={rootRef}
            className="relative"
            onMouseMoveCapture={handleMouseMoveCapture}
            onMouseLeave={handleMouseLeave}
            onClickCapture={handleClickCapture}
            data-builder-surface="true"
            data-builder-section-id={sectionLocalId}
            data-builder-component-type={sectionKey}
            data-builder-component-name={componentName}
        >
            {children}
            <SelectionOverlay state={hoveredOverlay} tone="hover" />
            <SelectionOverlay state={selectedOverlay} tone="selected" />
        </div>
    );
}

export default BuilderCanvasSectionSurface;
