# Phase 6 — Builder Data Model Verification

## Serializable structure

Page/section/component instances use a **serializable** structure. No hidden JSX assumptions; canvas rendering depends only on this shape and registry definitions.

### Canonical shape (BuilderSerializableInstance / BuilderComponentInstance)

```ts
{
  id: string;                    // unique instance id (e.g. section localId)
  componentKey: string;          // registry key (e.g. webu_header_01)
  variant?: string;              // design variant (e.g. header-1, hero-2)
  props: Record<string, unknown>; // merged props (defaults + overrides)
  children?: BuilderSerializableInstance[];  // nested sections when relevant
  responsiveOverrides?: Record<string, Record<string, unknown>>; // per-breakpoint (desktop, tablet, mobile)
  metadata?: Record<string, unknown>;        // e.g. bindingMeta
}
```

- **BuilderSerializableInstance** uses `responsiveOverrides` (canonical serialized name).
- **BuilderComponentInstance** uses `responsive` (same data); **toSerializableInstance()** maps to `responsiveOverrides` for export/serialization.

### Runtime section shape (BuilderSection)

The tree stored in state is **BuilderSection** (flat list of sections):

- **localId** → id  
- **type** → componentKey  
- **props** / **propsText** → parsed as props (variant and responsive live inside props)  
- **bindingMeta** → metadata  

Nested sections live inside **props.sections** for container sections; **sectionToComponentInstance()** maps them to **children**. Responsive overrides live inside **props.responsive**; **sectionToComponentInstance()** exposes them as **responsive** (and **toSerializableInstance()** as **responsiveOverrides**).

**Location:** `builder/types.ts` (BuilderSerializableInstance, BuilderComponentInstance, sectionToComponentInstance, toSerializableInstance), `builder/visual/treeUtils.ts` (BuilderSection).

---

## Canvas depends only on this structure + registry

Canvas rendering **does not** use component-specific imports or branch on section type in JSX. It uses only:

1. **section.type** (componentKey) → lookup **getComponentRuntimeEntry(section.type)** and optionally **getCentralRegistryEntry(section.type)**.
2. **section.localId** (id) → for selection, drop zones, and surface metadata.
3. **section.props** or **section.propsText** → merged with schema defaults via **resolveComponentProps(section.type, section.props ?? section.propsText)**; result is the **props** passed to the resolved component.

So the canvas needs only **id** (localId), **componentKey** (type), and **props** (from props/propsText + registry defaults). Variant and responsive are inside props; the registry component receives the merged props and renders accordingly. No hidden JSX: the component is always **registry entry . component** or the central registry component.

**Code reference:** `builder/visual/BuilderCanvas.tsx` — `renderRegistrySection(section, displayLabel)` uses only `section.type`, `section.localId`, `section.props ?? section.propsText`; component comes from **getComponentRuntimeEntry** / **getCentralRegistryEntry**.

---

## Mapping: BuilderSection ↔ serializable instance

- **Section → instance:** **sectionToComponentInstance(section)** produces BuilderComponentInstance (id, componentKey, variant, props, children from props.sections, responsive from props.responsive, metadata from bindingMeta).
- **Instance → serialized:** **toSerializableInstance(instance)** produces BuilderSerializableInstance with **responsiveOverrides** instead of responsive for the canonical serialized form.

---

## Summary

| Requirement | Status |
|-------------|--------|
| Serializable structure with id, componentKey, variant, props, children, responsiveOverrides, metadata | Yes — BuilderSerializableInstance + BuilderComponentInstance |
| Canvas uses only this structure + registry | Yes — canvas uses section.type, section.localId, section.props/propsText; component from registry |
| No hidden JSX assumptions | Yes — no component-specific imports in canvas; component resolved by componentKey from registry |
