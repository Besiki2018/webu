# Phase 2 — Canvas Renderer Uses the Registry

## Canvas renderer file

**Primary file:** `Install/resources/js/builder/visual/BuilderCanvas.tsx`

(No `CanvasRenderer.tsx` or `PageRenderer.tsx`; the builder uses `BuilderCanvas.tsx`.)

---

## Verification: no direct component imports

`BuilderCanvas.tsx` does **not** import any section components (Header, Footer, Hero, CTA, etc.) directly. It only imports:

- React, local UI (EditableNodeWrapper, BuilderCanvasSectionSurface, RootDropZone, SectionBlockPlaceholder)
- Registry APIs: `getComponentRuntimeEntry`, `resolveComponentProps` from `../componentRegistry`
- Full-fidelity registry helper: `getCentralRegistryEntry` from `../componentRegistry`
- `ensureFullComponentProps` from `../builderCompatibility`
- Types: BuilderSection, DropTarget, BuilderEditableTarget

Section components are resolved at runtime via the canonical registry.

---

## Correct pattern (implemented)

Rendering follows the required pattern:

1. **Lookup registry entry**  
   `getComponentRuntimeEntry(section.type)` → runtime entry (component, schema, defaults).  
  Optional: `getCentralRegistryEntry(section.type)` from `componentRegistry.ts` for full-fidelity components (Header, Footer, Hero).

2. **Merge defaults + saved props**  
   `props = resolveComponentProps(section.type, section.props ?? section.propsText)`  
   For central entries: `componentProps = ensureFullComponentProps(centralEntry.defaults, mapBuilderProps(props))`.

3. **Render component dynamically**  
   - If central entry: `const Component = centralEntry.component` → `<Component {...componentProps} />`
   - Else: `const CanvasComponent = runtimeEntry.component` → `<CanvasComponent ... props={props} schema={...} />`

Equivalent to the required pattern:

```ts
const entry = componentRegistry[node.componentKey]   // → getCentralRegistryEntry or runtime entry
const Component = entry.component
const props = mergeDefaults(entry.defaults, node.props)  // → resolveComponentProps / ensureFullComponentProps
return <Component {...props} />
```

---

## Code reference (BuilderCanvas.tsx)

```ts
const renderRegistrySection = useCallback((section: BuilderSection, displayLabel: string) => {
    const runtimeEntry = getComponentRuntimeEntry(section.type);  // 1. Lookup
    if (!runtimeEntry) return <SectionBlockPlaceholder section={section} />;

    const props = resolveComponentProps(section.type, section.props ?? section.propsText);  // 2. Merge

    const centralEntry = getCentralRegistryEntry(section.type);
    if (centralEntry) {
        const Component = centralEntry.component;
        const mapped = centralEntry.mapBuilderProps ? centralEntry.mapBuilderProps(props) : props;
        const componentProps = ensureFullComponentProps(centralEntry.defaults, mapped);  // 2. Merge (central)
        return (
            <BuilderCanvasSectionSurface ...>
                <Component {...componentProps} />   {/* 3. Render */}
            </BuilderCanvasSectionSurface>
        );
    }

    const CanvasComponent = runtimeEntry.component;
    return (
        <BuilderCanvasSectionSurface ...>
            <CanvasComponent sectionKey={...} props={props} schema={runtimeEntry.schema} />   {/* 3. Render */}
        </BuilderCanvasSectionSurface>
    );
}, [...]);
```

Default section content: `renderSectionContent ? renderSectionContent(section) : renderRegistrySection(section, getLabel(section))`. So by default every section is rendered through the registry.

---

## Summary

| Requirement | Status |
|-------------|--------|
| Canvas file uses registry (no direct Header/Footer/Hero imports) | Yes |
| Lookup registry entry | Yes (`getComponentRuntimeEntry`, `getCentralRegistryEntry`) |
| Merge defaults + saved props | Yes (`resolveComponentProps`, `ensureFullComponentProps`) |
| Render component dynamically | Yes (`<Component {...componentProps} />` / `<CanvasComponent ... />`) |

No refactor needed; the canvas already uses the registry for all section rendering.
