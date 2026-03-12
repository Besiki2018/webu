# Phase 18 — Migration Report

Report generated after Phases 15–17: page model, component migration (Header, Footer, Hero, Features, CTA, Navigation, Cards, Grid), and builder tests.

---

## 1. Migrated components

These components are **fully migrated** to the schema-driven builder: dedicated schema, defaults, real React component, and entry in **`builder/registry/componentRegistry.ts`**. The canvas renders them via `CanvasRenderer` (lookup → merge defaults → render); no `Builder*CanvasSection` fallback.

| Component   | Registry ID                  | Short key   | Schema / defaults location                          | Variants / notes                    |
|------------|------------------------------|-------------|-----------------------------------------------------|-------------------------------------|
| **Header** | `webu_header_01`             | `header`    | `layout/Header/Header.schema.ts`, `Header.defaults.ts` | header-1 … header-6                 |
| **Footer** | `webu_footer_01`             | `footer`    | `layout/Footer/Footer.schema.ts`, `Footer.defaults.ts` | footer-1 … footer-4                 |
| **Hero**   | `webu_general_hero_01`       | `hero`      | `sections/Hero/Hero.schema.ts`, `Hero.defaults.ts`   | hero-1 … hero-7                     |
| **Features** | `webu_general_features_01` | `features`  | `sections/Features/Features.schema.ts`, `.defaults.ts` | features-1 … features-4             |
| **CTA**    | `webu_general_cta_01`        | `cta`       | `sections/CTA/CTA.schema.ts`, `CTA.defaults.ts`      | cta-1 … cta-4                       |
| **Navigation** | `webu_general_navigation_01` | `navigation` | `layout/Navigation/Navigation.schema.ts`, `.defaults.ts` | navigation-1, navigation-2          |
| **Cards**  | `webu_general_cards_01`       | `cards`     | `sections/Cards/Cards.schema.ts`, `Cards.defaults.ts`| cards-1, cards-2                   |
| **Grid**   | `webu_general_grid_01`        | `grid`      | `sections/Grid/Grid.schema.ts`, `Grid.defaults.ts`   | grid-1, grid-2                     |

**Total migrated:** 8 components.

All migrated components:

- Have **schema** with `props` (and optionally `defaults`) used by SidebarInspector and update pipeline.
- Have **defaults** merged with node props at render time.
- Are **registered** in `REGISTRY_ID_TO_KEY` and `componentRegistry` with `component`, `schema`, `defaults`, and `mapBuilderProps` where needed.
- Render **from props only** (no hardcoded content).
- Are covered by **Phase 17** tests (registry integrity, schema defaults, component render, sidebar fields, prop update, variant switching).

---

## 2. Legacy components

These section types exist in the **main REGISTRY** (`builder/componentRegistry.ts`) only. They use **normalized** schema/defaults from parameters and are rendered by **Builder*CanvasSection** in `builder/visual/registryComponents.tsx`. They are **not** in the schema-driven registry (`builder/registry/componentRegistry.ts`).

| Registry ID                       | Display name / category | Canvas fallback                  |
|-----------------------------------|--------------------------|----------------------------------|
| `webu_general_heading_01`         | Heading / Feature        | BuilderHeadingCanvasSection      |
| `webu_general_text_01`            | Text                     | BuilderTextCanvasSection         |
| `webu_general_image_01`           | Image                    | BuilderImageCanvasSection        |
| `webu_general_button_01`          | Button                   | BuilderButtonCanvasSection       |
| `webu_general_spacer_01`          | Spacer                   | BuilderSpacerCanvasSection       |
| `webu_general_section_01`         | Section / Container      | BuilderSectionCanvasSection      |
| `webu_general_newsletter_01`      | Newsletter               | BuilderNewsletterCanvasSection   |
| `webu_general_form_wrapper_01`    | Form                     | BuilderFormCanvasSection         |
| `webu_general_video_01`           | Video                    | BuilderVideoCanvasSection        |
| `webu_general_card_01`            | Card (legacy)            | BuilderCardCanvasSection         |
| `webu_ecom_product_grid_01`       | Product grid             | BuilderCollectionCanvasSection   |
| `webu_ecom_featured_categories_01`| Featured categories      | Builder*CanvasSection            |
| `webu_ecom_category_list_01`      | Category list            | Builder*CanvasSection            |
| `webu_ecom_cart_page_01`          | Cart page                | Builder*CanvasSection            |
| `webu_ecom_product_detail_01`     | Product detail           | Builder*CanvasSection            |

**Note:** `webu_general_cta_01` and `webu_general_cards_01` / `webu_general_grid_01` exist in **both** the main REGISTRY (legacy) and the schema-driven registry (migrated). When the **schema-driven** canvas path is used (`CanvasRenderer` + `builder/registry/componentRegistry`), the migrated entry wins. The main REGISTRY entries remain for `BuilderCanvas` / legacy flows until fully switched over.

**Policy:** Migrate legacy sections by adding a real component + `Component.schema.ts` + `Component.defaults.ts`, then registering in `builder/registry/componentRegistry.ts` and `REGISTRY_ID_TO_KEY`.

---

## 3. Registry summary

### Schema-driven registry (primary for new builder)

- **Location:** `builder/registry/componentRegistry.ts`
- **Exports:** `REGISTRY_ID_TO_KEY`, `componentRegistry`, `getEntry`, `getRegistryKeyByComponentId`, `getCentralRegistryEntry`, `hasEntry`
- **Used by:** `CanvasRenderer`, `SidebarInspector`, `updateComponentProps`, `chatTargeting`, Phase 17 tests
- **Entry shape:** `{ component, schema, defaults, mapBuilderProps? }`
- **Lookup:** `getEntry(registryId)` → short key from `REGISTRY_ID_TO_KEY` → `componentRegistry[key]`
- **Count:** 8 entries (header, footer, hero, features, cta, navigation, cards, grid)

### Main REGISTRY (legacy / full builder)

- **Location:** `builder/componentRegistry.ts`
- **Purpose:** Full list of section types (add/display), schema normalization, `getComponentRuntimeEntry`, `resolveComponentProps`, optional `centralComponentRegistry` for Header/Footer/Hero in legacy canvas
- **Used by:** `BuilderCanvas` (visual), legacy tests, section library, CMS
- **Count:** All section types (header, footer, hero, heading, text, image, button, spacer, section, newsletter, cta, card, form, product grid, ecom*, video, etc.)

### Dual usage

- **Schema-driven path:** Store `componentTree` (BuilderPageModel) → `CanvasRenderer` → `getEntry(node.componentKey)` from `builder/registry/componentRegistry.ts` → merge defaults → render component.
- **Legacy path:** `BuilderCanvas` with `sections` (BuilderSection[]) → `getComponentRuntimeEntry` / `getCentralRegistryEntry` from main registry + central registry → render.

---

## 4. Schema summary

- **Migrated components:** Each has a schema file exposing a **props object** (and optionally `defaults`). Sidebar and update pipeline support:
  - **schema.props:** `{ [key]: { type, label, default, options?, group? } }`
  - **schema.fields:** array of `{ path/key, type, label, default, options?, group? }`
- **Field groups:** content, style, layout, advanced, responsive (per schema).
- **Types used:** text, textarea, number, color, image, link, menu, repeater, select, alignment, spacing, boolean, etc.
- **Defaults:** Each migrated component has a `*Defaults` object (e.g. `HERO_DEFAULTS`) used by the registry and merged with node props via `mergeDefaults(entry.defaults, node.props)` at render time.
- **Variants:** Stored canonically on `node.variant`; passed into props; schema often includes a `variant` select field.
- **Docs:** `builder/docs/PAGE_MODEL.md`, `VARIANT_SYSTEM.md`, `RESPONSIVE_SUPPORT.md`, `CHAT_TARGETING.md`.

---

## 5. Data model summary

- **Page model:** `BuilderPageModel` = array of root nodes (`BuilderPageNode[]`). Each node = `BuilderComponentInstance`.
- **Node shape (serializable):**
  - `id`, `componentKey`, `variant?`, `props`, `children?`
  - Optional: `responsive`, `metadata`
- **Types:** `builder/core/types.ts` (`BuilderComponentInstance`), `builder/core/pageModel.ts` (`BuilderPageModel`, `BuilderPageNode`).
- **Serialization:** `serializePageModel(model)`, `parsePageModel(json)`, `toSerializableNode(node)` in `builder/core/pageModel.ts`; re-exported from `builder/core`.
- **Store:** `componentTree: BuilderComponentInstance[]` in `builder/store/builderStore.ts`; canvas receives `componentTree` and passes it to `CanvasRenderer`.
- **Doc:** `builder/docs/PAGE_MODEL.md`.

---

## 6. Files changed (Phases 15–18)

### Phase 15 — Data model

| File | Change |
|------|--------|
| `builder/core/pageModel.ts` | **Added.** Types `BuilderPageModel`, `BuilderPageNode`; `toSerializableNode`, `serializePageModel`, `parsePageModel`. |
| `builder/core/index.ts` | **Modified.** Export page model types and helpers. |
| `builder/renderer/CanvasRenderer.tsx` | **Modified.** Comment + `componentTree: BuilderPageModel`. |
| `builder/docs/PAGE_MODEL.md` | **Added.** Page model and serialization doc. |

### Phase 16 — Migrate components

| File | Change |
|------|--------|
| `components/sections/Features/*` | **Added.** Features.tsx, Features.schema.ts, Features.defaults.ts, Features.variants.ts, index.ts. |
| `components/sections/CTA/*` | **Added.** CTA.tsx, CTA.schema.ts, CTA.defaults.ts, CTA.variants.ts, index.ts. |
| `components/layout/Navigation/*` | **Added.** Navigation.tsx, .schema, .defaults, .variants, index.ts. |
| `components/sections/Cards/*` | **Added.** Cards.tsx, .schema, .defaults, .variants, index.ts. |
| `components/sections/Grid/*` | **Added.** Grid.tsx, .schema, .defaults, .variants, index.ts. |
| `builder/registry/componentRegistry.ts` | **Modified.** Imports for Features, CTA, Navigation, Cards, Grid; `REGISTRY_ID_TO_KEY` + 5 entries; `parseRepeaterItems`; mapBuilderProps for each. |

### Phase 17 — Testing

| File | Change |
|------|--------|
| `builder/__tests__/phase17BuilderTests.test.tsx` | **Added.** 21 tests: registry integrity, schema defaults, component render, sidebar field generation, prop update rerender, variant switching. |
| `builder/renderer/CanvasRenderer.tsx` | **Modified.** Import `getEntry` from `../registry/componentRegistry`. |
| `builder/updates/updateComponentProps.ts` | **Modified.** Import `getEntry` from `../registry/componentRegistry`. |
| `builder/updates/chatTargeting.ts` | **Modified.** Import `getEntry` from `../registry/componentRegistry`. |
| `builder/inspector/SidebarInspector.tsx` | **Modified.** Import `getEntry` from `../registry/componentRegistry`. |

### Phase 18 — Migration report

| File | Change |
|------|--------|
| `builder/docs/PHASE18_MIGRATION_REPORT.md` | **Added.** This report. |

---

## 7. Blockers

- **None critical.** Schema-driven registry, page model, migrated components, and Phase 17 tests are in place and passing.
- **Dual registry:** Two systems coexist (schema-driven `builder/registry/componentRegistry.ts` vs main `builder/componentRegistry.ts` + `centralComponentRegistry.ts`). Unifying or clearly splitting responsibilities (e.g. one canvas path) would reduce confusion.
- **Legacy sections:** Many section types still use Builder*CanvasSection and parameter-based schema. Migrating them follows the same pattern (component + schema + defaults + registry entry).
- **Test env:** Phase 17 uses a **mocked store** and **direct imports** from `../registry/componentRegistry` in renderer/updates/inspector so that `getEntry` and store resolve correctly under Vitest. Production behavior is unchanged.

---

## Summary table

| Item | Count / status |
|------|----------------|
| Migrated components | 8 (Header, Footer, Hero, Features, CTA, Navigation, Cards, Grid) |
| Legacy section types (main REGISTRY only) | 15+ |
| Schema-driven registry entries | 8 |
| Page model types | BuilderPageModel, BuilderPageNode; serialize/parse helpers |
| Phase 17 tests | 21 passing |
| Files added (Phases 15–18) | ~25+ (core pageModel, 5 section/layout modules, test file, docs) |
| Files modified | core/index, CanvasRenderer, registry, updateComponentProps, chatTargeting, SidebarInspector |
