/**
 * Builder canvas renderer.
 * Renders from the serializable page model (BuilderPageModel): each node has id, componentKey, variant, props, children.
 * Lookup → merge defaults → render; no direct component imports.
 * Injects builder metadata (data-builder-id, data-component-key, data-variant) for hover, selection, chat targeting.
 */

import type { ReactNode } from 'react';
import type { BuilderComponentInstance, BuilderPageModel } from '../core';
import { getEntry } from '../registry/componentRegistry';
import { useBuilderStore } from '../store/builderStore';
import { mergeDefaults } from '../utils';

export interface CanvasRendererProps {
  /** Serializable page model: nodes with id, componentKey, variant, props, children. */
  componentTree: BuilderPageModel;
  /** Optional wrapper className for the root. */
  className?: string;
}

const OUTLINE_SELECTED = 'outline outline-2 outline-blue-500 outline-offset-1 rounded-[1px]';
const OUTLINE_HOVER = 'outline outline-1 outline-dashed outline-slate-400 outline-offset-1 rounded-[1px]';

function BuilderMetadataWrapper({
  node,
  children,
  isSelected,
  isHovered,
  onSelect,
  onHoverIn,
  onHoverOut,
}: {
  node: BuilderComponentInstance;
  children: ReactNode;
  isSelected: boolean;
  isHovered: boolean;
  onSelect: (id: string) => void;
  onHoverIn: (id: string) => void;
  onHoverOut: () => void;
}) {
  const outlineClass = isSelected ? OUTLINE_SELECTED : isHovered ? OUTLINE_HOVER : '';
  return (
    <div
      data-builder-id={node.id}
      data-component-key={node.componentKey}
      data-variant={node.variant ?? undefined}
      className={`min-h-[1px] cursor-pointer ${outlineClass}`}
      onClick={(e) => {
        e.stopPropagation();
        onSelect(node.id);
      }}
      onMouseEnter={() => onHoverIn(node.id)}
      onMouseLeave={onHoverOut}
      role="button"
      tabIndex={0}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          onSelect(node.id);
        }
      }}
    >
      {children}
    </div>
  );
}

function Placeholder({
  componentKey,
  id,
  variant,
  isSelected,
  isHovered,
  onSelect,
  onHoverIn,
  onHoverOut,
}: {
  componentKey: string;
  id: string;
  variant?: string;
  isSelected: boolean;
  isHovered: boolean;
  onSelect: (id: string) => void;
  onHoverIn: (id: string) => void;
  onHoverOut: () => void;
}) {
  const outlineClass = isSelected ? OUTLINE_SELECTED : isHovered ? OUTLINE_HOVER : '';
  return (
    <div
      data-builder-id={id}
      data-component-key={componentKey}
      data-variant={variant}
      data-builder-placeholder
      className={`min-h-[48px] flex items-center justify-center border border-dashed border-slate-300 rounded-md bg-slate-50/80 text-slate-500 text-sm cursor-pointer ${outlineClass}`}
      onClick={(e) => {
        e.stopPropagation();
        onSelect(id);
      }}
      onMouseEnter={() => onHoverIn(id)}
      onMouseLeave={onHoverOut}
      role="button"
      tabIndex={0}
    >
      {componentKey}
    </div>
  );
}

/**
 * Renders the component tree by looking up each node in the registry,
 * merging defaults with node.props, then rendering the registry component.
 *
 * Props contract (Phase 6): props = saved component props + default props.
 * - saved: node.props (from builder state)
 * - defaults: entry.defaults (from registry)
 * - merged = mergeDefaults(defaults, saved) → componentProps = mapBuilderProps(merged)
 * - Render: <Component {...componentProps} />
 *
 * Click on element → store.selectedComponentId = elementId. Hover/selected outlines applied.
 * Flow: componentTree → lookup componentRegistry → merge defaults → render component.
 */
export function CanvasRenderer({ componentTree, className }: CanvasRendererProps) {
  const selectedComponentId = useBuilderStore((s) => s.selectedComponentId);
  const hoveredComponentId = useBuilderStore((s) => s.hoveredComponentId);
  const setSelectedComponentId = useBuilderStore((s) => s.setSelectedComponentId);
  const setHoveredComponentId = useBuilderStore((s) => s.setHoveredComponentId);

  const handleSelect = (elementId: string) => setSelectedComponentId(elementId);
  const handleHoverIn = (elementId: string) => setHoveredComponentId(elementId);
  const handleHoverOut = () => setHoveredComponentId(null);

  if (!componentTree?.length) {
    return (
      <div
        className={className}
        data-builder-canvas
        onClick={() => setSelectedComponentId(null)}
        role="region"
        aria-label="Builder canvas"
      >
        <div className="min-h-[120px] flex items-center justify-center text-slate-400 text-sm" data-builder-empty>
          No sections
        </div>
      </div>
    );
  }

  return (
    <div
      className={`cursor-default ${className ?? ''}`}
      data-builder-canvas
      role="region"
      aria-label="Builder canvas"
      onClick={(e) => {
        if (e.target === e.currentTarget) setSelectedComponentId(null);
      }}
    >
      <div className="flex flex-col gap-4">
        {componentTree.map((node) => {
          const entry = getEntry(node.componentKey);
          const isSelected = selectedComponentId === node.id;
          const isHovered = hoveredComponentId === node.id;
          if (!entry) {
            return (
              <Placeholder
                key={node.id}
                componentKey={node.componentKey}
                id={node.id}
                variant={node.variant}
                isSelected={isSelected}
                isHovered={isHovered}
                onSelect={handleSelect}
                onHoverIn={handleHoverIn}
                onHoverOut={handleHoverOut}
              />
            );
          }
          const Component = entry.component;
          // Saved component props + default props (Phase 6)
          const savedProps = (node.props ?? {}) as Record<string, unknown>;
          const propsWithVariant = { ...savedProps, variant: node.variant ?? savedProps.variant };
          const mergedProps = mergeDefaults(entry.defaults as Record<string, unknown>, propsWithVariant);
          const componentProps = entry.mapBuilderProps ? entry.mapBuilderProps(mergedProps) : mergedProps;
          return (
            <BuilderMetadataWrapper
              key={node.id}
              node={node}
              isSelected={isSelected}
              isHovered={isHovered}
              onSelect={handleSelect}
              onHoverIn={handleHoverIn}
              onHoverOut={handleHoverOut}
            >
              <Component {...(componentProps as object)} />
            </BuilderMetadataWrapper>
          );
        })}
      </div>
    </div>
  );
}
