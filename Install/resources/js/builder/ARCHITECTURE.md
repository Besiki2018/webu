# Builder Component Architecture — Final Result

Stable, reusable architecture for schema-driven builder components. All existing components follow this pattern; future components (hundreds) are added the same way.

---

## 1. All existing components converted to builder components

- **Header, Footer, Hero**: Single component each (`layout/Header`, `layout/Footer`, `sections/Hero`) that accepts props and delegates to design-system variants by `variant` prop. Used by canvas via central registry.
- **Feature, CTA, Cards, Grids, etc.**: Each has a **registry ID** in `componentRegistry.ts` (e.g. `webu_general_heading_01`, `webu_general_button_01`, `webu_general_card_01`, `webu_ecom_product_grid_01`). Canvas resolves them via `getComponentRuntimeEntry(section.type)` and renders either the central-registry component (Header/Footer/Hero) or the **canvas component** from `registryComponents.tsx` (BuilderHeadingCanvasSection, BuilderButtonCanvasSection, BuilderCardCanvasSection, BuilderCollectionCanvasSection, etc.).
- **No standalone “page-only” components**: Builder canvas and preview both use the same registry; section type → runtime entry → component.

**Location**: `builder/componentRegistry.ts` (REGISTRY), `builder/centralComponentRegistry.ts` (Header, Footer, Hero), `builder/visual/registryComponents.tsx` (canvas components).

---

## 2. All versions converted into variants

- **Header**: One `Header` component; variants `header-1` … `header-6` in `design-system/webu-header/variants/`. Selected via `props.variant`.
- **Footer**: One `Footer` component; variants `footer-1` … `footer-4` in `design-system/webu-footer/variants/`.
- **Hero**: One `Hero` component; variants `hero-1` … `hero-7` in `design-system/webu-hero/variants/`.
- No duplicate top-level components (e.g. no separate Header2.tsx); all are variants under the single component.

**Location**: `components/layout/Header/Header.tsx`, `layout/Footer/Footer.tsx`, `sections/Hero/Hero.tsx` (switch on `variant`); `components/design-system/webu-*/variants/*.tsx`.

---

## 3. No hardcoded editable values in JSX

- Variants receive **all content and layout** via props (e.g. `menu`, `logo`, `title`, `subtitle`, `backgroundColor`). No fallback arrays or default copy in JSX for editable content.
- Defaults live in **schema/defaults** and are applied by `resolveComponentProps` and `ensureFullComponentProps`; components render `props.title` etc., not `props.title || 'Hardcoded'` for builder-editable fields.
- Canvas placeholders that remain (e.g. BuilderGenericCanvasSection) use props only; no hardcoded menus or labels for registered section types.

**Location**: Design-system variant files; `centralComponentRegistry.ts` (mapBuilderProps); `builder/visual/registryComponents.tsx`.

---

## 4. Each component has schema + defaults

- **Header, Footer, Hero**: Canonical schema in `Component.schema.ts` (ComponentSchemaDef + BuilderComponentSchema); defaults in `Component.defaults.ts`; exported from `index.ts`.
- **All other sections**: In `componentRegistry.ts`, each REGISTRY entry has either an explicit `schema` (e.g. HEADER_SCHEMA, HERO_SCHEMA) or a **normalized schema** built from `parameters` + `buildFoundationFields(definition)` (defaults from parameters and foundation fields).
- **resolveComponentProps(sectionType, props)** merges user props with `getDefaultProps(sectionType)` so every section has a full props object.

**Location**: `layout/Header/Header.schema.ts` + `Header.defaults.ts`; same for Footer, Hero; `builder/componentRegistry.ts` (normalizeSchema, getDefaultProps, resolveComponentProps).

---

## 5. All components registered in component registry

- **Full registry**: `componentRegistry.ts` → `REGISTRY` object. Every section type the builder can add or display is keyed by **registry ID** (e.g. `webu_header_01`, `webu_footer_01`, `webu_general_hero_01`, `webu_general_heading_01`, `webu_general_button_01`, `webu_general_card_01`, `webu_ecom_product_grid_01`, …).
- **Central registry** (optional): `centralComponentRegistry.ts` maps a subset (e.g. `webu_header_01`, `webu_footer_01`, `webu_general_hero_01`) to the real React component + schema + defaults + `mapBuilderProps`. Used by the canvas for full-fidelity rendering.
- **Runtime entry**: `getComponentRuntimeEntry(registryId)` returns `{ componentKey, component, schema, defaults }` for every REGISTRY id; the `component` is either the central-registry component or a Builder*CanvasSection.

**Location**: `builder/componentRegistry.ts` (REGISTRY, getComponent, getComponentRuntimeEntry, normalizeSchema), `builder/centralComponentRegistry.ts` (componentRegistry, getCentralRegistryEntry).

---

## 6. Canvas renders components using registry

- **BuilderCanvas** (`builder/visual/BuilderCanvas.tsx`): For each section, calls `getComponentRuntimeEntry(section.type)`. If no entry → `SectionBlockPlaceholder`. If **central registry** has an entry for `section.type` → renders `<Component {...componentProps} />` (Header/Footer/Hero) with `componentProps = ensureFullComponentProps(defaults, mapBuilderProps(props))`. Otherwise → renders `CanvasComponent` (e.g. BuilderHeroCanvasSection) with `props` from `resolveComponentProps(section.type, section.props ?? section.propsText)`.
- **Single path**: All section types flow through the registry; no ad-hoc component lookup or hardcoded section-type → component maps in the canvas.

**Location**: `builder/visual/BuilderCanvas.tsx` (renderRegistrySection), `builder/builderCompatibility.ts` (ensureFullComponentProps).

---

## 7. Sidebar parameters driven by schema

- **Schema source**: Sidebar and parameter panels use **schema from the registry**: `getComponentSchema(registryId)` / `getComponentSchemaJson(registryId)` return normalized schema and JSON with `properties`, `fields`, `editable_fields`, `content_groups`, `style_groups`, etc.
- **Cms**: Uses `getComponentSchemaJson` when building section library and for fallback schema; control definitions can be overridden per site but the base is registry schema.
- **BuilderCanvasSectionSurface**: Uses `runtimeEntry.schema.fields` and `runtimeEntry.schema.editableFields` for field-level metadata and selection. So canvas and sidebar both consume the same schema.

**Location**: `builder/componentRegistry.ts` (getComponentSchema, getComponentSchemaJson), `builder/visual/BuilderCanvasSectionSurface.tsx` (fieldDefinitions from schema), Cms (sectionLibrary + getComponentSchemaJson).

---

## 8. Chat edits component props safely

- **Unified pipeline**: All edits (sidebar, chat, toolbar, drag-drop) go through **applyBuilderUpdatePipeline** / **applyBuilderChangeSetPipeline** in `builder/state/updatePipeline.ts`.
- **Chat → pipeline**: `buildBuilderUpdateOperationsFromChangeSet(changeSet)` turns chat operations (`updateText`, `setField`, `replaceImage`, `updateSection`, etc.) into pipeline ops (`set-field`, `merge-props`). Paths are **validated against component schema** (resolveSchemaField, validateValueAgainstField). Invalid paths or types produce errors; no raw patch applied without validation.
- **Schema prop names**: Chat and AI use `editableFields` / `chatTargets` from schema (e.g. from `getComponentSchema(id)` or backend context).

**Location**: `builder/state/updatePipeline.ts`, `builder/README.md` (Chat editing compatibility), `ai/changes/changeSet.schema.ts` (setField op).

---

## 9. System ready for adding hundreds of future components

- **Single pattern** for new components:
  1. **Add to REGISTRY** in `componentRegistry.ts`: `id`, `name`, `category`, `parameters` (or full `schema`), optional `metadata`, optional `canvasComponent`. NormalizeSchema + buildFoundationFields provide schema and defaults if not provided.
  2. **Optional – full-fidelity component**: If the section should render the real React component (not a canvas stub), add an entry to **centralComponentRegistry** (`componentRegistry` + `REGISTRY_ID_TO_KEY`), with `component`, `schema`, `defaults`, and `mapBuilderProps` if builder prop names differ from component API.
  3. **Canvas fallback**: If not in central registry, `resolveCanvasComponent(definition, schema)` in componentRegistry picks a Builder*CanvasSection by category/key (e.g. card → BuilderCardCanvasSection). For a new category, add a branch or a new Builder*CanvasSection and wire it in resolveCanvasComponent.
- **No temporary hacks**: New sections do not require changes to canvas render logic, sidebar, or chat; they only require registry (+ optional central registry) and optionally a new canvas component for preview.

**Location**: `builder/componentRegistry.ts` (REGISTRY, resolveCanvasComponent, buildFoundationFields), `builder/centralComponentRegistry.ts`, `builder/visual/registryComponents.tsx`.

---

## Adding a new component (reusable pattern)

1. **Define the component** (if full-fidelity): e.g. `components/sections/MySection/MySection.tsx` with variants under `design-system/webu-my-section/variants/`. Component receives all data via props; no hardcoded editable content.
2. **Schema + defaults**: Add `MySection.schema.ts` and `MySection.defaults.ts`; export from `MySection/index.ts`.
3. **Register in REGISTRY**: In `componentRegistry.ts`, add an entry to `REGISTRY` with `id: 'webu_general_mysection_01'`, `name`, `category`, `parameters` or `schema`, `metadata`.
4. **Optional – central registry**: In `centralComponentRegistry.ts`, add to `REGISTRY_ID_TO_KEY` and `componentRegistry` with `component`, `schema`, `defaults`, `mapBuilderProps`. Then the canvas will render `<MySection {...componentProps} />` instead of a canvas placeholder.
5. **Optional – canvas placeholder**: If not in central registry, ensure `resolveCanvasComponent` maps your category/key to an existing Builder*CanvasSection or add a new one in `registryComponents.tsx`.

No changes needed to BuilderCanvas render logic, update pipeline, or chat beyond using the new registry id.
