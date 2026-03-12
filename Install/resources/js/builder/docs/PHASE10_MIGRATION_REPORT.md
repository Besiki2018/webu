# Phase 10 — Migration Report

Report generated after completion of schema-driven architecture verification and fixes (Phases 1–9).

---

## Important: completion is based on runtime wiring

**The architecture is only considered complete when runtime behavior is verified, not when schema/defaults/registry files merely exist.**

Criteria checked:

- **Canvas** renders components **through** the registry (no direct section imports).
- **Sidebar** generates controls **from** schema (registry + server; schema available for every registry id on the page).
- **Props updates** flow through the pipeline and **rerender** the canvas (state → store → BuilderCanvas → DOM).
- **Components** render **purely from props** (no hardcoded editable content).
- **Update pipeline** is **unified** (sidebar and chat use the same path).
- System is **ready for chat-driven editing** and future AI generation (change set → pipeline → state).

See **`builder/docs/RUNTIME_VERIFICATION.md`** for code paths and how each criterion is verified (including Phase 9 tests #3, #4, #4b, #5, #5b and updatePipeline / BuilderCanvas / runtimeVerification tests).

---

## 1. Components confirmed working with schema-driven architecture

These components are **fully migrated**: dedicated schema + defaults files, central registry entry, canvas render from props only, sidebar driven by schema, single update pipeline. All Phase 9 validation tests pass for them.

| Component | Registry ID | Schema | Defaults | Variants |
|-----------|-------------|--------|----------|----------|
| **Header** | `webu_header_01` | `layout/Header/Header.schema.ts` | `layout/Header/Header.defaults.ts` | header-1 … header-6 |
| **Footer** | `webu_footer_01` | `layout/Footer/Footer.schema.ts` | `layout/Footer/Footer.defaults.ts` | footer-1 … footer-4 |
| **Hero** | `webu_general_hero_01` | `sections/Hero/Hero.schema.ts` | `sections/Hero/Hero.defaults.ts` | hero-1 … hero-7 |

**Verification checklist (runtime, not just file presence):**

- Has schema (Component.schema.ts): **yes**
- Has defaults (Component.defaults.ts): **yes**
- In main REGISTRY + central registry: **yes**
- **Canvas resolves via registry only** (getComponentRuntimeEntry / getCentralRegistryEntry): **yes** — Phase 9 #3, BuilderCanvas.test, legacyDetection.
- **Renders from props only** (DOM reflects props from state; no hardcoded content): **yes** — Phase 9 #3, #5b.
- **Sidebar gets schema** from sectionSchemaByKey (registry fallback for all getAvailableComponents()): **yes** — Phase 9 #4, #4b; Cms builds sectionSchemaByKey with registry fallback.
- **Updates go through single pipeline**; state update triggers canvas rerender: **yes** — Phase 9 #5, #5b; updateSectionPathProp → updateComponentProps → applyMutationState.
- Serializable (BuilderSection / BuilderComponentInstance): **yes**

---

## 2. Components still using legacy structure

These section types are in the **main REGISTRY** only (not in the central registry). They have **normalized** schema and defaults from `componentRegistry` (parameters + `buildFoundationFields`). The canvas renders them via **Builder*CanvasSection** from `registryComponents.tsx`. They are editable and use the same update pipeline; they do not yet have a dedicated schema file, defaults file, or real React component in the central registry.

| Registry ID | Category / use | Canvas component (fallback) |
|-------------|----------------|-----------------------------|
| `webu_general_heading_01` | Feature / Heading | BuilderHeadingCanvasSection |
| `webu_general_text_01` | Content | BuilderTextCanvasSection |
| `webu_general_image_01` | Content | BuilderImageCanvasSection |
| `webu_general_button_01` | CTA / Button | BuilderButtonCanvasSection |
| `webu_general_spacer_01` | Layout | BuilderSpacerCanvasSection |
| `webu_general_section_01` | Section / Container | BuilderSectionCanvasSection |
| `webu_general_newsletter_01` | CTA / Newsletter | BuilderNewsletterCanvasSection |
| `webu_general_cta_01` | CTA | BuilderCTACanvasSection |
| `webu_general_card_01` | Cards | BuilderCardCanvasSection |
| `webu_general_form_wrapper_01` | Form | BuilderFormCanvasSection |
| `webu_ecom_product_grid_01` | Grids | BuilderCollectionCanvasSection |
| `webu_ecom_featured_categories_01` | Ecom | Builder*CanvasSection |
| `webu_ecom_category_list_01` | Ecom | Builder*CanvasSection |
| `webu_ecom_cart_page_01` | Ecom | Builder*CanvasSection |
| `webu_ecom_product_detail_01` | Ecom | Builder*CanvasSection |
| `webu_general_video_01` | Content | BuilderVideoCanvasSection |

**Policy:** No conflicting parallel implementations. The main builder registry is the single source of truth. Legacy entries remain in the main REGISTRY; they are excluded from the **central** registry until migrated. Sidebar and update pipeline use the same normalized schema and the same update path. Future migration: add Component.schema.ts + Component.defaults.ts + real component, then register in `REGISTRY_ID_TO_KEY` and `centralComponentRegistry.componentRegistry`.

---

## 3. Component registry summary

- **Main registry** (`builder/componentRegistry.ts`):
  - **REGISTRY:** map of registry ID → `ComponentDefinition` (id, name, category, parameters or schema, metadata, optional canvasComponent).
  - Every section type the builder can add or display is keyed by registry ID.
  - **getAvailableComponents()** returns all registry IDs.
  - **getComponentRuntimeEntry(registryId)** returns `BuilderComponentRuntimeEntry`: componentKey, component, schema, defaults. Component is either from the central registry (Header/Footer/Hero) or a Builder*CanvasSection from `resolveCanvasComponent`.

- **Central registry** (`builder/centralComponentRegistry.ts`):
  - **REGISTRY_ID_TO_KEY:** maps registry ID → short key (e.g. `webu_header_01` → `'header'`). Only three entries: header, footer, hero.
  - **componentRegistry:** map short key → `ComponentRegistryEntry` (component, schema, defaults, mapBuilderProps).
  - **getCentralRegistryEntry(registryId)** returns the central entry or null. Canvas uses it for full-fidelity rendering when present; otherwise uses main REGISTRY runtime entry.

- **Lookup flow:** Section `type` → `getComponentRuntimeEntry(type)` → if central has entry → use central component + mapBuilderProps + ensureFullComponentProps; else → use CanvasComponent from main REGISTRY with `resolveComponentProps(type, props)`.

---

## 4. Schema format summary

- **Canonical schema type:** `BuilderComponentSchema` (in `componentRegistry.ts`; re-exported from `builder/types.ts`).
  - Fields: componentKey, displayName, category, **fields** (BuilderFieldDefinition[]), defaultProps, variants / variantDefinitions, responsiveSupport, contentGroups, styleGroups, advancedGroups, editableFields, etc.

- **Field definition:** `BuilderFieldDefinition`: path, type (BuilderFieldType), label, group (BuilderFieldGroup), default, options, responsive, etc.

- **Field groups (standard):** content | style | advanced | responsive | state (see `FIELD_GROUP_STANDARD` in `builder/types.ts`).

- **Migrated components:** Each has `Component.schema.ts` exporting a schema compatible with BuilderComponentSchema (e.g. from ComponentSchemaDef). Defaults in `Component.defaults.ts`; both wired into the central registry.

- **Legacy components:** For REGISTRY entries without a dedicated schema file, `normalizeSchema(def)` builds a BuilderComponentSchema from parameters + buildFoundationFields; defaults from parameters and schema.defaultProps.

- **JSON for sidebar:** `getComponentSchemaJson(registryId)` returns a record with **properties** (nested keys with per-field definitions, including builder_field_type for control generation). Sidebar uses this (or server-provided schema_json) to build field lists and control types (e.g. getSchemaFieldControlType in Cms).

---

## 5. Builder data model summary

- **Section in state/tree:** `BuilderSection` (`builder/visual/treeUtils.ts`):
  - localId, **type** (registry id), **props?**, **propsText**, propsError, bindingMeta?.
  - Props for rendering come from `section.props ?? section.propsText` (parsed), merged with schema defaults via `resolveComponentProps(section.type, section.props ?? section.propsText)`.

- **Serializable instance (builder contract):** `BuilderComponentInstance` / `BuilderSerializableInstance` (`builder/types.ts`):
  - id, componentKey, variant?, props, children?, responsive? / responsiveOverrides?, metadata?.
  - **sectionToComponentInstance(section)** maps BuilderSection → BuilderComponentInstance.
  - **toSerializableInstance(instance)** normalizes for export/save.

- **Update operations:** `BuilderUpdateOperation` (set-field, unset-field, merge-props, insert-section, delete-section, reorder-section). Single pipeline: `applyBuilderUpdatePipeline(initialState, operations, options)` in `builder/state/updatePipeline.ts`. Sidebar and Chat both use it (updateComponentProps, applyBuilderChangeSetPipeline). No bypass; all prop edits are validated against schema.

---

## 6. Files modified (Phases 1–9)

**Created:**

| File | Purpose |
|------|---------|
| `builder/types.ts` | Shared types: BuilderComponentSchema, BuilderFieldDefinition, BuilderComponentInstance, sectionToComponentInstance, FIELD_GROUP_STANDARD, etc. |
| `builder/__tests__/architectureValidation.test.ts` | Registry integrity, schema/defaults, sidebar fields, variants, legacy isolation, props-only render, prop update path, Phase 3/4/6 tests |
| `builder/__tests__/phase9ArchitectureValidation.test.tsx` | Phase 9: seven architecture validation tests (registry, schema/defaults, render-from-props, sidebar fields, prop update rerender, variant, legacy compatibility) |
| `builder/__tests__/registryIntegration.test.tsx` | Central registry structure, REGISTRY_ID_TO_KEY, main REGISTRY presence |
| `builder/__tests__/runtimeVerification.test.tsx` | Phase 7: select, sidebar params, change value, rerender, state sync, variant, responsive, canvas render |
| `builder/__tests__/legacyDetection.test.ts` | Phase 8: canvas no direct component imports; only central/componentRegistry import section paths |
| `builder/state/__tests__/updatePipeline.test.ts` | Pipeline: set-field, merge-props, schema validation, change set, updateComponentProps |
| `builder/visual/__tests__/BuilderCanvas.test.tsx` | Canvas registry rendering, placeholder fallback, production previews, builder metadata |
| `builder/docs/PHASE1_REGISTRY_VERIFICATION.md` … `PHASE9_TESTING.md` | Phase verification docs |
| `builder/DELIVERABLE.md` | Formal deliverable: migrated vs legacy, registry, schema, data model, files, blockers |

**Updated (key files):**

| File | Change |
|------|--------|
| `builder/state/updatePipeline.ts` | Added `updateComponentProps`; pipeline used by Sidebar and Chat |
| `builder/visual/BuilderCanvas.tsx` | Registry-only resolution (getComponentRuntimeEntry, getCentralRegistryEntry); no direct Header/Footer/Hero imports |
| `builder/visual/treeUtils.ts` | BuilderSection interface (existing; referenced as canonical) |
| `builder/componentRegistry.ts` | REGISTRY, getComponentSchema, getComponentRuntimeEntry, resolveComponentProps, getDefaultProps, getComponentSchemaJson, normalizeSchema |
| `builder/centralComponentRegistry.ts` | REGISTRY_ID_TO_KEY, componentRegistry, getCentralRegistryEntry (Header, Footer, Hero) |
| `builder/builderCompatibility.ts` | ensureFullComponentProps (existing) |
| `Pages/Project/Cms.tsx` | Sidebar: schema from getComponentSchemaJson; **sectionSchemaByKey** populated with **registry fallback** (getAvailableComponents + getComponentSchemaJson) so every registry id has schema for controls; updateSectionPathProp → updateComponentProps; getSchemaFieldControlType; builder:apply-change-set → applyBuilderChangeSetPipeline |

**Relevant (unchanged or reference):**

- `builder/componentSchemaFormat.ts` — ComponentSchemaDef, PropDef.
- `builder/ARCHITECTURE.md`, `builder/README.md` — Architecture and criteria.

---

## 7. Remaining blockers

- **None.** The base schema-driven architecture is in place. Header, Footer, and Hero are fully migrated and validated. Legacy section types work through the same main registry and update pipeline; they can be migrated one-by-one to the central registry when their real component + schema + defaults are added.

**Notes:**

- **Runtime over file presence:** Completion is judged by runtime wiring (see RUNTIME_VERIFICATION.md). Tests assert: canvas renders from registry and from props (#3, #5b); sidebar can get schema for every registry id (#4b); pipeline updates state and that state drives canvas (#5, #5b).
- **Migration adapter:** Current builder state uses `section.type` = registry ID and `section.props` / `section.propsText`. This matches the data model (componentKey = type, props = merged props). No adapter is required for existing content. If a future backend sends a different shape, a small adapter can map it to BuilderSection / BuilderComponentInstance before passing to the canvas.
- **Tests:** Phase 9 suite (including #4b, #5b), architectureValidation, registryIntegration, runtimeVerification, legacyDetection, updatePipeline, BuilderCanvas, and componentValidation. They cover registry integrity, schema/defaults consistency, sidebar field generation from schema for all ids, variant rendering, canvas render from props, **prop update → state → canvas rerender**, and legacy compatibility.

**Quality bar:** The result is usable for manual visual editing, sidebar parameter editing, chat-driven editing, future AI generation, and future export of full website code. New components can be added in the same pattern (schema → defaults → registry → central optional → canvas/sidebar/pipeline) without redesigning the system.
