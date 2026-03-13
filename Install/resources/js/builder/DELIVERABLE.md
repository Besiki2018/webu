# Builder Schema-Driven Architecture — Deliverable

This document is the formal deliverable for the migration to a schema-driven, scalable AI website builder. It satisfies the execution rules, migration strategy, and quality bar specified for the project.

---

## 1. Migrated components (full schema-driven flow)

These components are **fully migrated**: schema + defaults + canonical registry render entry + canvas render from props + sidebar from schema + single update pipeline. They are the source of truth.

| Component   | Registry ID              | Schema location                    | Defaults location                 | Variants                          |
|------------|---------------------------|------------------------------------|-----------------------------------|-----------------------------------|
| Header     | `webu_header_01`          | `layout/Header/Header.schema.ts`   | `layout/Header/Header.defaults.ts`| header-1 … header-6               |
| Footer     | `webu_footer_01`          | `layout/Footer/Footer.schema.ts`   | `layout/Footer/Footer.defaults.ts`| footer-1 … footer-4               |
| Hero       | `webu_general_hero_01`    | `sections/Hero/Hero.schema.ts`     | `sections/Hero/Hero.defaults.ts` | hero-1 … hero-7                   |

**Verification (per-component checklist):**

- Has schema: yes (Component.schema.ts)
- Has defaults: yes (Component.defaults.ts)
- In registry: yes (canonical REGISTRY + full-fidelity render entry)
- Renders from props only: yes (no hidden JSX; mapBuilderProps / ensureFullComponentProps)
- Editable values not hardcoded: yes (props-driven)
- Variant logic centralized: yes (single component + design-system variants)
- Sidebar reads schema fields: yes (getComponentSchema → fields / contentGroups / styleGroups)
- Builder can update props: yes (update pipeline; set-field, merge-props)
- Serializable: yes (BuilderSection / BuilderComponentInstance: id, componentKey, variant, props)

---

## 2. Legacy components (registry only, not full-fidelity)

These section types are **in the canonical REGISTRY** and have normalized schema + defaults from `componentRegistry` (parameters + buildFoundationFields). They do not have a full-fidelity render entry yet; the canvas renders them via `Builder*CanvasSection` from `registryComponents.tsx`. They remain usable and editable; future migration follows the same pattern as Header/Footer/Hero.

| Registry ID                        | Category / use              | Canvas component (fallback)     |
|------------------------------------|-----------------------------|---------------------------------|
| webu_general_heading_01             | Feature / Heading            | BuilderHeadingCanvasSection     |
| webu_general_text_01                | Content                     | BuilderTextCanvasSection        |
| webu_general_image_01               | Content                     | BuilderImageCanvasSection       |
| webu_general_button_01              | CTA / Button                | BuilderButtonCanvasSection      |
| webu_general_spacer_01              | Layout                      | BuilderSpacerCanvasSection      |
| webu_general_section_01             | Section / Container         | BuilderSectionCanvasSection     |
| webu_general_newsletter_01          | CTA / Newsletter            | BuilderNewsletterCanvasSection  |
| webu_general_cta_01                | CTA                         | BuilderCTACanvasSection         |
| webu_general_card_01                | Cards                       | BuilderCardCanvasSection        |
| webu_general_form_wrapper_01        | Form                        | BuilderFormCanvasSection        |
| webu_ecom_product_grid_01           | Grids                       | BuilderCollectionCanvasSection  |
| webu_ecom_featured_categories_01    | Ecom                        | Builder*CanvasSection           |
| webu_ecom_category_list_01         | Ecom                        | Builder*CanvasSection           |
| webu_ecom_cart_page_01             | Ecom                        | Builder*CanvasSection           |
| webu_ecom_product_detail_01         | Ecom                        | Builder*CanvasSection           |
| webu_general_video_01               | Content                     | BuilderVideoCanvasSection       |

**Policy:** No conflicting parallel implementations. `componentRegistry.ts` is the single source of truth. Legacy entries are not removed; they simply do not have a full-fidelity render entry yet. Sidebar and update pipeline still use the same schema (normalized) and the same update path.

---

## 3. Registry structure summary

- **Canonical registry** (`componentRegistry.ts`):
  - `REGISTRY`: map of registry ID → ComponentDefinition (id, name, category, parameters or schema, metadata, optional canvasComponent).
  - Every section type the builder can add or display is keyed by registry ID.
  - `getComponentRuntimeEntry(registryId)` returns `BuilderComponentRuntimeEntry`: componentKey, component, schema, defaults. Component is either a full-fidelity render entry or a Builder*CanvasSection.

- **Full-fidelity render subset** (`componentRegistry.ts`):
  - `getCentralRegistryEntry(registryId)` returns the full-fidelity entry or null.
  - Today the subset is Header, Footer, and Hero. Canvas uses it for full-fidelity rendering; all others use runtime entry from the same REGISTRY + `resolveCanvasComponent`.

- **Lookup flow:** Section type → `getComponentRuntimeEntry(type)` → if `getCentralRegistryEntry(type)` resolves → use the full-fidelity component + `mapBuilderProps`; else → use `CanvasComponent` from `resolveCanvasComponent` with `resolveComponentProps(type, props)`.

---

## 4. Schema format summary

- **Canonical schema type:** `BuilderComponentSchema` (in `componentRegistry.ts` and re-exported from `builder/types.ts`).
  - componentKey, displayName, category, fields (BuilderFieldDefinition[]), defaultProps, variants / variantDefinitions, responsiveSupport, contentGroups, styleGroups, advancedGroups, editableFields, etc.

- **Field definition:** `BuilderFieldDefinition`: path, type (BuilderFieldType), label, group (BuilderFieldGroup), default, options, responsive, etc.

- **Field group standard:** content | style | advanced | responsive | state (and optionally layout, states, data, bindings, meta). Standard set is in `builder/types.ts` as `FIELD_GROUP_STANDARD`.

- **Component schema files (migrated):** Each migrated component has a `Component.schema.ts` that exports a schema compatible with BuilderComponentSchema (e.g. built from ComponentSchemaDef via conversion). Defaults in `Component.defaults.ts`; both wired into the canonical registry.

- **Normalized schema (legacy):** For REGISTRY entries without a dedicated schema file, `normalizeSchema(def)` builds a BuilderComponentSchema from parameters + buildFoundationFields; defaults from parameters + schema.defaultProps.

---

## 5. Data model summary

- **Section in state/tree:** `BuilderSection` (in `visual/treeUtils.ts`): localId, type (registry id), props?, propsText, propsError, bindingMeta?.

- **Serializable instance (builder contract):** `BuilderComponentInstance` (in `builder/types.ts`): id, componentKey, variant?, props, children?, responsive?, metadata?. Helper: `sectionToComponentInstance(section)` maps BuilderSection → BuilderComponentInstance.

- **No hidden JSX assumptions:** All editable state is in props (or responsive overrides). Component receives merged props from resolveComponentProps / ensureFullComponentProps.

- **Responsive:** ResponsiveValue&lt;T&gt; and per-breakpoint overrides (e.g. responsive.desktop.padding) where schema marks fields as responsive. responsiveFieldDefinitions and responsiveProps utilities support sidebar and merge.

- **Update payload:** `BuilderUpdatePayload` = `BuilderUpdateOperation` (set-field, merge-props, etc.). Single pipeline in `builder/state/updatePipeline.ts`; sidebar and chat both use it. No chat-specific mutation format that bypasses the pipeline.

---

## 6. Files created/updated (this phase)

**Created:**

- `builder/types.ts` — Shared base types: BuilderComponentSchema, BuilderFieldDefinition, BuilderComponentDefaults, BuilderComponentVariant, BuilderComponentRegistryEntry, BuilderComponentInstance, ResponsiveValue&lt;T&gt;, BuilderUpdatePayload, FIELD_GROUP_STANDARD, sectionToComponentInstance.
- `builder/__tests__/architectureValidation.test.ts` — Registry integrity, schema/defaults consistency, sidebar field generation, variant rendering, legacy isolation, props-only render, prop update path.
- `builder/DELIVERABLE.md` — This document.

**Updated:**

- (No breaking changes to existing files; shared runtime types continue to come from canonical registry exports and related builder types. Existing runtime imports remain valid.)

**Relevant existing files (reference):**

- `builder/componentRegistry.ts` — REGISTRY, BuilderComponentSchema, getComponentSchema, getComponentRuntimeEntry, resolveComponentProps, getDefaultProps, normalizeSchema.
- `builder/componentRegistry.ts` — REGISTRY, getComponentRenderEntry, getCentralRegistryEntry, getComponentRuntimeEntry.
- `builder/componentSchemaFormat.ts` — ComponentSchemaDef, PropDef, ResponsiveSupportDef.
- `builder/state/updatePipeline.ts` — applyBuilderUpdatePipeline, BuilderUpdateOperation, buildBuilderUpdateOperationsFromChangeSet.
- `builder/visual/BuilderCanvas.tsx` — renderRegistrySection, full-fidelity vs canvas component resolution.
- `builder/visual/treeUtils.ts` — BuilderSection.
- `builder/builderCompatibility.ts` — ensureFullComponentProps.
- `builder/ARCHITECTURE.md` — High-level architecture and “Adding a new component”.
- `builder/README.md` — Final result table and criteria.

---

## 7. Known blockers / notes

- **None.** The base architecture is in place; Header, Footer, and Hero are fully migrated and validated. Legacy section types work through the same registry and update pipeline; they can later gain full-fidelity render entries when their real component + schema + defaults are added.

- **Migration adapter:** Current builder state uses `section.type` = registry ID and `section.props` (or propsText). This matches the data model (componentKey = type, props = merged props). No adapter is required for existing content; if a future backend sends a different shape, a small adapter can map it to BuilderSection/BuilderComponentInstance before passing to the canvas.

- **Tests:** All of the following pass: componentValidation.test.tsx (8 tests), architectureValidation.test.ts (7 tests), updatePipeline.test.ts (6 tests), BuilderCanvas.test.tsx. These cover registry integrity, schema/defaults consistency, sidebar field generation from schema, variant rendering, canvas render from props, and prop update rerender.

---

**Quality bar:** The result is usable for manual visual editing, sidebar parameter editing, chat-driven editing, future AI generation, and future export of full website code. New components can be added in the same pattern (schema → defaults → canonical registry → optional full-fidelity render entry → canvas/sidebar/pipeline) without redesigning the system.
