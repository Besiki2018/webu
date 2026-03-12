# Phase 8 — Legacy System Detection

Search for legacy rendering paths: direct component imports in the renderer, hardcoded inspector controls, and parallel component configuration. Either migrate or isolate as legacy and disable from the main builder registry.

---

## 1. Direct component imports in renderer

**Builder canvas renderer:** `builder/visual/BuilderCanvas.tsx`

- **Finding:** The canvas does **not** import Header, Footer, Hero, or any section component directly. It imports only registry APIs (`getComponentRuntimeEntry`, `getCentralRegistryEntry`), `resolveComponentProps`, and `ensureFullComponentProps`. The component to render is always resolved at runtime via `getCentralRegistryEntry(section.type)` or `getComponentRuntimeEntry(section.type).component`.
- **Conclusion:** No legacy path. No migration needed.

**Central registry:** `builder/centralComponentRegistry.ts`

- Imports Header, Footer, Hero from `@/components/layout/Header`, `@/layout/Footer`, `@/sections/Hero`. This is the **single** place that wires real components for the builder. The canvas never imports those; it receives them via `getCentralRegistryEntry()`. So this is the intended architecture, not legacy.

**Main registry:** `builder/componentRegistry.ts`

- Imports only **schemas** (`HEADER_SCHEMA`, `FOOTER_SCHEMA`, `HERO_SCHEMA`) from component folders for REGISTRY definitions. No component imports for rendering. No legacy path.

---

## 2. Hardcoded inspector controls

**Sidebar / inspector:** `Pages/Project/Cms.tsx` (builder settings panel)

- **Finding:** Controls are built from **schema**: `collectSchemaPrimitiveFields(selectedSectionSchemaProperties)` and `renderSchemaFieldEditorControl(field, ...)`. Control type is driven by **`getSchemaFieldControlType(field)`** (reads `field.definition.builder_field_type`) when present, with fallbacks by path/format for legacy or server-only schema. There are no per-component `if (sectionKey === 'webu_header_01') { return <HeaderFields /> }` blocks.
- **Conclusion:** Inspector is schema-driven. No legacy hardcoded per-component inspector blocks to migrate.

---

## 3. Parallel component configuration logic

**Builder:**

- **Single REGISTRY:** `builder/componentRegistry.ts` — one `REGISTRY` object keyed by registry id; `getComponentRuntimeEntry(id)` returns the same runtime entry (schema, defaults, component) for each id.
- **Single central registry:** `builder/centralComponentRegistry.ts` — one map (header, footer, hero) for full-fidelity components. Canvas uses central first, then falls back to `resolveCanvasComponent` in the main registry.
- **Single resolve path:** `resolveCanvasComponent(definition, schema)` returns one of the Builder*CanvasSection components by category/key. For `webu_header_01`, `webu_footer_01`, `webu_general_hero_01` the canvas uses the **central** registry (real Header/Footer/Hero), so the BuilderHeaderCanvasSection/BuilderFooterCanvasSection/BuilderHeroCanvasSection from `resolveCanvasComponent` are only used if an id were not in the central registry. No duplicate active configuration.
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

- `builder/componentRegistry.ts` (REGISTRY + getComponentRuntimeEntry, resolveCanvasComponent)
- `builder/centralComponentRegistry.ts` (Header, Footer, Hero for webu_header_01, webu_footer_01, webu_general_hero_01)
- `builder/visual/BuilderCanvas.tsx` (registry-only resolution)

So the above renderers are **not** legacy paths for the builder; they are alternate renderers for other contexts (layout preview, ecommerce, etc.). They are **not** in the main builder registry and do not need to be disabled from it — the main builder registry is already separate.

---

## 5. Summary

| Check | Result |
|-------|--------|
| Direct component imports in builder canvas | None; canvas uses registry only |
| Hardcoded inspector controls (per-component) | None; sidebar is schema-driven |
| Parallel component configuration in builder | None; single REGISTRY and single central registry |
| Other renderers (renderer/, ai-layout/, ecommerce/) | Exist but are not used by Cms visual builder; no change to main builder registry |

**No legacy rendering paths found in the main builder.** No migration or isolation was required. The builder canvas, sidebar, and update pipeline all use the schema-driven registry architecture.
