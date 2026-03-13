# Phase 8 — Legacy System Detection

Search for legacy rendering paths: direct component imports in the renderer, hardcoded inspector controls, and parallel component configuration. Either migrate or isolate as legacy and disable from the main builder registry.

---

## 1. Direct component imports in renderer

**Builder canvas renderer:** `builder/visual/BuilderCanvas.tsx`

- **Finding:** The canvas does **not** import Header, Footer, Hero, or any section component directly. It imports only registry APIs (`getComponentRuntimeEntry`, `getCentralRegistryEntry`), `resolveComponentProps`, and `ensureFullComponentProps`. The component to render is always resolved at runtime via `getCentralRegistryEntry(section.type)` or `getComponentRuntimeEntry(section.type).component`.
- **Conclusion:** No legacy path. No migration needed.

**Canonical registry:** `builder/componentRegistry.ts`

- The builder now resolves all runtime entries from this file.
- `getCentralRegistryEntry()` remains as a helper exported from the canonical registry file for the full-fidelity subset; it is not a second registry layer.
- No standalone central registry file remains in the active architecture.

---

## 2. Hardcoded inspector controls

**Sidebar / inspector:** `Pages/Project/Cms.tsx` (builder settings panel)

- **Finding:** Controls are built from **schema**: `collectSchemaPrimitiveFields(selectedSectionSchemaProperties)` and `renderSchemaFieldEditorControl(field, ...)`. Control type is driven by **`getSchemaFieldControlType(field)`** (reads `field.definition.builder_field_type`) when present, with fallbacks by path/format for legacy or server-only schema. There are no per-component `if (sectionKey === 'webu_header_01') { return <HeaderFields /> }` blocks.
- **Conclusion:** Inspector is schema-driven. No legacy hardcoded per-component inspector blocks to migrate.

---

## 3. Parallel component configuration logic

**Builder:**

- **Single REGISTRY:** `builder/componentRegistry.ts` — one `REGISTRY` object keyed by registry id; `getComponentRuntimeEntry(id)` returns the same runtime entry (schema, defaults, component) for each id.
- **Single resolve path:** `resolveCanvasComponent(definition, schema)` returns one of the Builder*CanvasSection components by category/key. For `webu_header_01`, `webu_footer_01`, `webu_general_hero_01` the canvas uses the full-fidelity helper exported by the canonical registry file, so there is no parallel active configuration layer.
- **Conclusion:** No parallel component configuration in the builder. No migration or isolation needed.

---

## 4. Other renderers (outside main builder)

These use direct component imports and their own mapping, but they are **not** used by the Cms visual builder. They are separate systems:

| Location | Purpose | Used by builder canvas? |
|----------|--------|--------------------------|
| `renderer/componentRegistry.tsx` | LayoutRenderer: maps layout JSON "component" keys to WebuHeader, WebuHero, WebuFooter, etc. | No |
| `ai-layout/componentRegistry.tsx` | Ecommerce layout: Header, HeroBanner, Footer from ecommerce components | No |
| `ecommerce/renderer/SectionRenderer.tsx` | Ecommerce section rendering: direct Header, Footer, HeroBanner imports | No |

The **main builder** (project Cms with tab=editor / embedded=sidebar) uses only:

- `builder/componentRegistry.ts` (REGISTRY + full-fidelity helper + getComponentRuntimeEntry + resolveCanvasComponent)
- `builder/visual/BuilderCanvas.tsx` (registry-only resolution)

So the above renderers are **not** legacy paths for the builder; they are alternate renderers for other contexts (layout preview, ecommerce, etc.). They are **not** in the main builder registry and do not need to be disabled from it — the main builder registry is already separate.

---

## 5. Summary

| Check | Result |
|-------|--------|
| Direct component imports in builder canvas | None; canvas uses canonical registry only |
| Hardcoded inspector controls (per-component) | None; sidebar is schema-driven |
| Parallel component configuration in builder | None; single canonical REGISTRY file |
| Other renderers (renderer/, ai-layout/, ecommerce/) | Exist but are not used by Cms visual builder; no change to main builder registry |

**No legacy rendering paths found in the main builder.** No migration or isolation was required. The builder canvas, sidebar, and update pipeline all use the schema-driven registry architecture.
