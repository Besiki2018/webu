import { useCallback } from 'react';
import { EditableNodeWrapper } from './EditableNodeWrapper';
import { BuilderCanvasSectionSurface } from './BuilderCanvasSectionSurface';
import { RootDropZone } from './RootDropZone';
import { SectionBlockPlaceholder } from './SectionBlockPlaceholder';
import type { BuilderSection } from './treeUtils';
import type { DropTarget } from './types';
import { getComponentRuntimeEntry, resolveComponentProps } from '../componentRegistry';
import { getCentralRegistryEntry } from '../registry/componentRegistry';
import { mergeDefaults } from '../utils';
import type { BuilderEditableTarget } from '../editingState';

function parseSectionProps(input: unknown): Record<string, unknown> {
    if (input !== null && typeof input === 'object' && !Array.isArray(input)) {
        return input as Record<string, unknown>;
    }
    if (typeof input === 'string' && input.trim() !== '') {
        try {
            const parsed: unknown = JSON.parse(input);
            return (parsed !== null && typeof parsed === 'object' && !Array.isArray(parsed))
                ? (parsed as Record<string, unknown>)
                : {};
        } catch {
            return {};
        }
    }
    return {};
}

export interface BuilderCanvasProps {
    sections: BuilderSection[];
    selectedElementId: string | null;
    hoveredElementId: string | null;
    selectedTarget?: BuilderEditableTarget | null;
    hoveredTarget?: BuilderEditableTarget | null;
    draggingComponentType: string | null;
    currentDropTarget: DropTarget | null;
    sectionDisplayLabelByKey: Map<string, string>;
    onSelect: (localId: string) => void;
    onHover: (localId: string | null) => void;
    onSelectTarget?: (target: BuilderEditableTarget) => void;
    onHoverTarget?: (target: BuilderEditableTarget | null) => void;
    /** Called when user clicks empty canvas area (deselect). */
    onDeselect?: () => void;
    /** Phase 2: called when user clicks edit icon on overlay (e.g. focus sidebar). */
    onEditSection?: (localId: string) => void;
    /** Phase 2: called when user clicks delete icon on overlay (remove section). */
    onDeleteSection?: (localId: string) => void;
    t: (key: string) => string;
    /** Optional custom renderer for section content. Defaults to registry-driven rendering with placeholder fallback. */
    renderSectionContent?: (section: BuilderSection) => React.ReactNode;
}

/**
 * Renders the visual builder canvas from the section tree (Task 9).
 * Stable wrapper: builder-root > builder-canvas > builder-page so layout never shrinks on delete.
 * Each section is wrapped in EditableNodeWrapper for hover, click, and drop zones.
 * Insertion preview is driven by currentDropTarget. Section content is resolved from the centralized builder registry.
 */
export function BuilderCanvas({
    sections,
    selectedElementId,
    hoveredElementId,
    selectedTarget = null,
    hoveredTarget = null,
    draggingComponentType,
    currentDropTarget,
    sectionDisplayLabelByKey,
    onSelect,
    onHover,
    onSelectTarget,
    onHoverTarget,
    onDeselect,
    onEditSection,
    onDeleteSection,
    t,
    renderSectionContent,
}: BuilderCanvasProps) {
    const getLabel = useCallback(
        (section: BuilderSection) => {
            const key = section.type?.trim().toLowerCase() || '';
            return sectionDisplayLabelByKey.get(key) ?? sectionDisplayLabelByKey.get(section.type) ?? section.type;
        },
        [sectionDisplayLabelByKey]
    );

    const getDropPositionForSection = useCallback(
        (sectionLocalId: string): 'before' | 'after' | 'inside' | null => {
            if (!currentDropTarget) return null;
            if (currentDropTarget.sectionLocalId !== sectionLocalId) return null;
            return currentDropTarget.position;
        },
        [currentDropTarget]
    );

    const isRootDropActive =
        currentDropTarget &&
        currentDropTarget.sectionLocalId === null &&
        (currentDropTarget.position === 'before' || currentDropTarget.position === 'after');

    const renderRegistrySection = useCallback(
        (section: BuilderSection, displayLabel: string) => {
            const runtimeEntry = getComponentRuntimeEntry(section.type);
            if (!runtimeEntry) {
                return <SectionBlockPlaceholder section={section} />;
            }

            const props = resolveComponentProps(section.type, section.props ?? section.propsText);

            // Use central component registry when available (Header, Footer, Hero).
            // Phase 6: props = saved component props + default props; render <Component {...props} />.
            const centralEntry = getCentralRegistryEntry(section.type);
            if (centralEntry) {
                const Component = centralEntry.component;
                const savedProps = parseSectionProps(section.props ?? section.propsText);
                const mergedProps = mergeDefaults(
                    centralEntry.defaults as Record<string, unknown>,
                    savedProps
                );
                const componentProps = centralEntry.mapBuilderProps
                    ? centralEntry.mapBuilderProps(mergedProps)
                    : mergedProps;
                return (
                    <BuilderCanvasSectionSurface
                        sectionLocalId={section.localId}
                        sectionKey={runtimeEntry.componentKey}
                        displayLabel={displayLabel}
                        runtimeEntry={runtimeEntry}
                        selectedTarget={selectedTarget}
                        hoveredTarget={hoveredTarget}
                        onSelectTarget={onSelectTarget}
                        onHoverTarget={onHoverTarget}
                    >
                        <Component {...(componentProps as object)} />
                    </BuilderCanvasSectionSurface>
                );
            }

            const CanvasComponent = runtimeEntry.component;
            return (
                <BuilderCanvasSectionSurface
                    sectionLocalId={section.localId}
                    sectionKey={runtimeEntry.componentKey}
                    displayLabel={displayLabel}
                    runtimeEntry={runtimeEntry}
                    selectedTarget={selectedTarget}
                    hoveredTarget={hoveredTarget}
                    onSelectTarget={onSelectTarget}
                    onHoverTarget={onHoverTarget}
                >
                    <CanvasComponent
                        sectionKey={runtimeEntry.componentKey}
                        sectionLocalId={section.localId}
                        displayName={displayLabel}
                        props={props}
                        schema={runtimeEntry.schema}
                    />
                </BuilderCanvasSectionSurface>
            );
        },
        [hoveredTarget, onHoverTarget, onSelectTarget, selectedTarget]
    );

    return (
        <div className="builder-root w-full min-h-[100vh] flex justify-center" role="region" aria-label="Builder canvas">
            <div className="builder-canvas w-full min-h-[200px] flex flex-col">
                <div
                    className="builder-page flex flex-col gap-0 min-h-[200px] w-full max-w-[1200px] mx-auto p-3 cursor-default"
                    onClick={(e) => {
                        if (e.target === e.currentTarget && onDeselect) onDeselect();
                    }}
                >
                    <RootDropZone
                        isActive={!!isRootDropActive}
                        isDraggingFromLibrary={!!draggingComponentType}
                        isEmpty={sections.length === 0}
                        t={t}
                        onEmptyAreaClick={sections.length === 0 ? onDeselect : undefined}
                    />

                    {sections.map((section, index) => (
                        <EditableNodeWrapper
                            key={section.localId}
                            node={section}
                            index={index}
                            isSelected={selectedTarget?.sectionLocalId === section.localId || selectedElementId === section.localId}
                            isHovered={hoveredTarget?.sectionLocalId === section.localId || hoveredElementId === section.localId}
                            isDraggingFromLibrary={!!draggingComponentType}
                            displayLabel={getLabel(section)}
                            dropPosition={getDropPositionForSection(section.localId)}
                            onClick={onSelect}
                            onMouseEnter={onHover}
                            onMouseLeave={() => onHover(null)}
                            onEdit={onEditSection}
                            onDelete={onDeleteSection}
                        >
                            {renderSectionContent ? renderSectionContent(section) : renderRegistrySection(section, getLabel(section))}
                        </EditableNodeWrapper>
                    ))}
                </div>
            </div>
        </div>
    );
}
