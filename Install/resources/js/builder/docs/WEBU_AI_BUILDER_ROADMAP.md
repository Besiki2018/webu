# Webu AI Builder — Full Development Roadmap

**Vision:** Webu = AI Website Builder + Visual Editor + Code Generator + AI Designer + Component Platform — comparable to Framer AI, Lovable, Builder.io, Webflow AI.

## Goal

Transform Webu into a **fully AI-powered website builder** capable of:

| Capability | Description |
|------------|-------------|
| **Visual editing** | Click/hover selection, drag-and-drop, canvas rendering from builder state |
| **Sidebar editing** | Schema-driven controls; edit selected component props in a panel |
| **Chat editing** | Natural-language and AI tool edits using the same update pipeline |
| **Automatic site generation** | AI generates page/section structure from prompts or templates |
| **Automatic component refactoring** | Context-aware refactors (e.g. “Optimize for ecommerce”) |
| **Full website export** | Serializable page model; export to JSON/HTML or publish |

---

## PHASE 1 — Stable Builder Architecture (Foundation)

### Objective

Create a **scalable architecture** where every component can be controlled by builder state.

### Core principles

| Principle | Meaning |
|-----------|---------|
| **Schema-driven components** | Every component has a schema (props, types, labels, variants); canvas and sidebar never hardcode component list |
| **Props-driven rendering** | All content comes from props (and variant); no hidden copy inside components |
| **Centralized registry** | Single registry: `getEntry(componentKey)` → component, schema, defaults |
| **Serializable page structure** | Page = tree of nodes (id, componentKey, variant, props, children); JSON-serializable for save/export/AI |

### Required structure (roadmap)

```
builder/
  components/        # Optional: colocate components here
    sections/
    layouts/
  schemas/           # Optional: central schemas; or colocated per component
  registry/          # Central registry
  renderer/          # Canvas renderer
  state/             # Builder state (store, update pipeline)
```

### Component structure (per component)

```
Hero/
  Hero.tsx           # React component; receives all content via props
  Hero.schema.ts      # Name, category, props, variants, projectTypes, capabilities
  Hero.defaults.ts    # Default prop values
  Hero.variants.ts    # Variant ids (e.g. hero-1, hero-2)
```

### Builder data model

```
Page
 ├─ Sections (root nodes)
 │   ├─ Components (nodes)
 │   │   ├─ id
 │   │   ├─ componentKey
 │   │   ├─ variant
 │   │   ├─ props
 │   │   ├─ responsive (optional)
 │   │   └─ children (nested sections/components)
```

### Result

- Builder becomes **predictable**: one data model, one registry, one render path.
- **AI can understand** component structure via schemas and serializable tree.

---

## Phase 1 — Current implementation status

The codebase **already implements Phase 1** in substance. Mapping below.

### Core principles ✅

| Principle | Status | Implementation |
|-----------|--------|----------------|
| **Schema-driven components** | ✅ | Each section/layout has a `*.schema.ts` (e.g. `Hero.schema.ts`). Registry entry includes `schema`. `SidebarInspector` and chat targeting derive fields from `getEntry(key).schema`. No hardcoded component list in canvas or sidebar. |
| **Props-driven rendering** | ✅ | Components receive all content via props. Canvas merges `defaults` + `node.props`, passes to `entry.component`. Variant and responsive handled via props/state. |
| **Centralized registry** | ✅ | `builder/registry/componentRegistry.ts`: `getEntry(registryId)`, `REGISTRY_ID_TO_KEY`, `componentRegistry`. Entry: `component`, `schema`, `defaults`, optional `mapBuilderProps`. |
| **Serializable page structure** | ✅ | `builder/core/pageModel.ts`: `BuilderPageModel` = array of `BuilderComponentInstance` (id, componentKey, variant, props, children). `toSerializableNode`, `serializePageModel`, `parsePageModel`. Store keeps only serializable data. |

### Structure mapping

| Roadmap path | Current path | Notes |
|--------------|--------------|--------|
| `builder/registry/` | `builder/registry/componentRegistry.ts` | ✅ Present |
| `builder/renderer/` | `builder/renderer/CanvasRenderer.tsx` | ✅ Present |
| `builder/state/` | `builder/store/` (Zustand) + `builder/state/` (updatePipeline, etc.) | ✅ State split between store and state modules |
| `builder/schemas/` | Schemas colocated: `components/sections/*/*.schema.ts`, `components/layout/*/*.schema.ts`; `builder/componentSchemaFormat.ts` | ✅ Schemas live next to components; format in builder |
| `builder/components/sections/` | `resources/js/components/sections/` | ✅ Same contract (Hero, Features, CTA, Cards, Grid); location outside `builder/` |
| `builder/components/layouts/` | `resources/js/components/layout/` | ✅ Same contract (Header, Footer, Navigation); folder named `layout` not `layouts` |

### Component structure ✅

Each section/layout follows the roadmap pattern:

- **Sections:** Hero, Features, CTA, Cards, Grid — each has `*.tsx`, `*.schema.ts`, `*.defaults.ts`, `*.variants.ts`.
- **Layouts:** Header, Footer, Navigation — same.

### Data model ✅

- **Types:** `builder/core/types.ts` — `BuilderComponentInstance` (id, componentKey, variant?, props, children?, responsive?, metadata?).
- **Page model:** `builder/core/pageModel.ts` — `BuilderPageModel`, serialization, parsing.
- **Store:** `builder/store/builderStore.ts` — `componentTree`, `setComponentTree`, `injectTreeMetadata` on set.

### Optional alignment (if you want to match roadmap layout exactly)

- Move or symlink `resources/js/components/sections` and `resources/js/components/layout` under `builder/components/` (e.g. `builder/components/sections/`, `builder/components/layouts/`) and update registry imports; or
- Keep current layout (components next to other app components); architecture is unchanged.

---

## PHASE 2 — Visual Canvas Editing

### Goal

User must **interact directly with the page**: hover → highlight, click → select, canvas overlay with border, drag handles, edit icon, delete icon. Selection ID must sync with Sidebar, Chat, and Builder state.

### Required features

| Feature | Description |
|--------|-------------|
| **Hover detection** | Hover → highlight component (visual feedback). |
| **Click selection** | Click → select component (single selection). |
| **Canvas overlay** | When selected: **border**, **drag handles** (reorder), **edit icon**, **delete icon**. |
| **Selection sync** | `selectedComponentId` (or equivalent) is the single source of truth and syncs with **Sidebar**, **Chat**, **Builder state**. |

### Phase 2 — Current implementation status

| Requirement | Status | Implementation |
|-------------|--------|----------------|
| **Hover detection** | ✅ | `hoveredComponentId` / `hoveredElementId` / `hoveredBuilderTarget`; canvas sets on mouse enter/leave. **CanvasRenderer:** outline-dashed on hover. **BuilderCanvas + EditableNodeWrapper:** `ring-1 ring-primary/40` + label when hovered. |
| **Click selection** | ✅ | Click sets `selectedComponentId` (schema-driven path) or `selectedSectionLocalId` / `selectedBuilderTarget` (Cms path). Empty canvas click clears selection. |
| **Border (overlay)** | ✅ | Selected: `ring-2 ring-primary ring-offset-2` (EditableNodeWrapper) or `outline outline-2 outline-blue-500` (CanvasRenderer). |
| **Drag handles** | ✅ | **Reorder:** Drop zones before/after/inside (EditableNodeWrapper) for drag-from-library and reorder. **Selected overlay:** Grip icon in overlay toolbar when selected (Phase 2 addition below). |
| **Edit icon** | ✅ | Overlay toolbar when selected: Edit (pencil) icon — focuses edit in sidebar or opens props (Phase 2 addition below). |
| **Delete icon** | ✅ | Overlay toolbar when selected: Delete (trash) icon — removes section and updates state (Phase 2 addition below). |
| **selectedComponentId sync** | ✅ | **Schema-driven path:** `builderStore.selectedComponentId` → CanvasRenderer, SidebarInspector, `getSelectionContext()` / chat use same store. **Cms path:** `builderEditingStore.selectedSectionLocalId` + `selectedBuilderTarget` → BuilderCanvas, Cms sidebar, and chat all read/write the same store; selection is single source of truth. |

### Files

- **Canvas (registry-driven):** `builder/renderer/CanvasRenderer.tsx` — hover/click, outlines, `data-builder-id` for targeting.
- **Canvas (Cms):** `builder/visual/BuilderCanvas.tsx` + `builder/visual/EditableNodeWrapper.tsx` — hover/click, ring border, label, drop zones, overlay toolbar (edit, delete, drag handle).
- **State:** `builder/store/builderStore.ts` (`selectedComponentId`, `hoveredComponentId`); `builder/state/builderEditingStore.ts` (`selectedSectionLocalId`, `selectedBuilderTarget`).
- **Sidebar:** `builder/inspector/SidebarInspector.tsx` — reads `selectedComponentId` from store.
- **Chat:** `builder/updates/chatTargeting.ts` — `getSelectionContext()` uses store `selectedComponentId`; same pipeline for edits.

---

## PHASE 3 — Dynamic Sidebar Inspector

### Goal

Sidebar must be **generated automatically from schema**. No manual inspector coding per component.

### Example schema (equivalent forms)

**Grouped props (current format):** each prop has `type` and `group` (`content` | `style` | `layout` | `advanced`).

```ts
HeroSchema = {
  props: {
    title:       { type: 'text',       label: 'Title',       group: 'content' },
    subtitle:   { type: 'text',       label: 'Subtitle',    group: 'content' },
    buttonText: { type: 'text',       label: 'Button text', group: 'content' },
    backgroundColor: { type: 'color', label: 'Background',  group: 'style' },
    textColor:   { type: 'color',     label: 'Text color',  group: 'style' },
    spacing:    { type: 'spacing',    label: 'Spacing',     group: 'style' },
    alignment:  { type: 'alignment',  label: 'Alignment',  options: [...], group: 'layout' },
  },
}
```

Conceptually: **content** = [title, subtitle, buttonText, ...], **style** = [backgroundColor, textColor, spacing]. The sidebar groups fields by `group` and renders the right control per `type`.

### Sidebar UI generated dynamically by type

| Schema type   | Control rendered        |
|---------------|-------------------------|
| `text`        | Text input              |
| `richtext`    | Textarea                |
| `number`      | Number input            |
| `color`       | Color picker + hex text |
| `image` / `icon` | Image URL / upload  |
| `link`        | URL input               |
| `select` / `alignment` | Dropdown (options) |
| `spacing`     | Spacing control (presets or CSS value) |
| `toggle` / `boolean` | Checkbox           |
| `menu` / `repeater` / `grid` | JSON or specialized UI |

### Phase 3 — Current implementation status

| Requirement | Status | Implementation |
|-------------|--------|----------------|
| **Schema-driven sidebar** | ✅ | `SidebarInspector` reads `getEntry(componentKey).schema`, uses `getFieldsFromSchema(schema)` to get fields from `schema.props` or `schema.fields`. No component-specific branches. |
| **Groups (content / style)** | ✅ | Fields have `group`; `byGroup` groups them; sidebar renders sections per group (Content, Style, Layout, Advanced). |
| **Text input** | ✅ | Default and explicit `text` → `<input type="text">`. |
| **Color picker** | ✅ | `type === 'color'` → color input + hex text input. |
| **Image upload** | ✅ | `type === 'image'` | `'icon'` → URL text input (placeholder "URL or upload"); upload can be wired by app. |
| **Spacing control** | ✅ | `type === 'spacing'` → dedicated control (preset select or CSS value input). |
| **Alignment control** | ✅ | `type === 'alignment'` with `options` → `<select>`. |
| **No manual inspector per component** | ✅ | All controls come from `FieldControl` by `field.type`; adding a new component = add schema only. |

### Files

- **Schema format:** `builder/componentSchemaFormat.ts` — `ComponentSchemaDef`, `PropDef`, `PropType`, `group`.
- **Field types:** `builder/core/fieldTypes.ts` — `BuilderFieldType`, `BUILDER_FIELD_GROUPS`.
- **Inspector:** `builder/inspector/SidebarInspector.tsx` — `getFieldsFromSchema`, `FieldControl` (by type), grouping by `group`.

---

## PHASE 4 — Chat Editing Engine

### Goal

**Chat must control the builder.** User says "Change hero title" or "Replace image" → AI intent → component id → field → update payload → **same update pipeline as Sidebar** → builder state update.

### Example commands

| User command        | Intent            | Component / field     | Update payload example                          |
|---------------------|-------------------|------------------------|-------------------------------------------------|
| Change hero title   | Edit prop         | hero → `title`        | `{ componentId: "hero-123", field: "title", value: "Build websites with AI" }` |
| Replace image       | Edit prop         | hero → `image`        | `{ componentId: "hero-123", field: "image", value: "https://…" }`             |
| Increase padding    | Edit prop         | section → `padding`   | `{ componentId: "cta-1", field: "padding", value: "2rem" }`                    |
| Add CTA section     | Add component     | (structural)          | Future: add node to tree                        |
| Remove search       | Edit prop         | header → `showSearch` | `{ componentId: "header-1", field: "showSearch", value: false }`              |

### Chat pipeline

```
chat message
    ↓
AI intent detection (edit prop / add section / remove element / …)
    ↓
component id detection (selected node or from context)
    ↓
field detection (schema path: title, image, padding, showSearch, …)
    ↓
update payload { componentId, field/path, value }
    ↓
builder state update (same pipeline as Sidebar)
```

- **Update payload** can use `field` or `path` (both map to schema prop key). Client calls **`updateComponentProps(componentId, { path: field ?? path, value })`**.
- **Same pipeline as Sidebar:** validation (component in tree, field in schema) and store update happen inside `updateComponentProps`; Chat and Sidebar both use this function.

### Example update payload

```json
{
  "componentId": "hero-123",
  "field": "title",
  "value": "Build websites with AI"
}
```

Equivalent: `{ componentId, path: "title", value }`. Backend can send either; client normalizes to `updateComponentProps(componentId, { path, value })`.

### Phase 4 — Current implementation status

| Requirement              | Status | Implementation |
|--------------------------|--------|----------------|
| **Chat controls builder** | ✅     | Chat sends tool_call `updateComponentProps`; client `handleToolResult` applies via `updateComponentProps`. |
| **AI intent → component id** | ✅   | Selection context: `useChatTargeting()` / `getSelectionContext()` provide `selectedComponentId`, `editableFields`, `allowedUpdates`. Backend uses selection or resolves from message. |
| **Field detection**      | ✅     | Schema defines editable paths; `allowedUpdates` = schema prop keys; backend maps intent to path (e.g. "title" → `title`). |
| **Update payload**       | ✅     | Params: `componentId`, `path` or `field`, `value`. Client accepts both `path` and `field`. |
| **Same pipeline as Sidebar** | ✅  | `updateComponentProps(componentId, payload)` used by Sidebar and Chat; validation and store update in one place. |

### Files

- **Update pipeline (shared):** `builder/updates/updateComponentProps.ts` — `updateComponentProps(componentId, { path, value })`, validation, store update.
- **Selection context for chat:** `builder/updates/chatTargeting.ts` — `getSelectionContext()`, `useChatTargeting()`, `editableFields`, `allowedUpdates`.
- **Chat application:** `hooks/useBuilderChat.ts` — `handleToolResult`: on `updateComponentProps` tool_result, calls `updateComponentProps(componentId, { path: params.path ?? params.field, value })`.
- **Docs:** `builder/docs/CHAT_TARGETING.md`, `builder/docs/PHASE7_CHAT_EDITING.md`.

---

## PHASE 5 — Component Library System

### Goal

The **component library must contain categories**. Each component schema must include **category**, **projectTypes**, and **capabilities** so the library can group and filter components.

### Library categories (canonical)

| Category       | Description / usage        |
|----------------|----------------------------|
| Layout         | Layout primitives, structure |
| Hero           | Hero sections              |
| Features       | Feature grids, highlights  |
| Pricing        | Pricing tables, plans      |
| Testimonials   | Testimonials, reviews     |
| Forms          | Contact, newsletter, etc.  |
| Footers        | Footer components         |
| Navigation     | Nav bars, menus           |
| Ecommerce      | Product, cart, checkout   |
| Blog           | Posts, articles           |
| Restaurant     | Menu, booking, food       |

### Schema requirements

Each component schema must include:

| Field          | Type     | Purpose |
|----------------|----------|---------|
| **category**   | string   | Library category id (e.g. `hero`, `features`, `layout`, `footers`, `navigation`). Used to group in the library. |
| **projectTypes** | ProjectType[] | Which project types can use this component (e.g. `['business', 'saas', 'landing']`). Omit or empty = show for all. |
| **capabilities** | string[] | Functional tags (e.g. `headline`, `callToAction`, `navigation`, `product`). Used for filtering and AI. |

### Example: Hero.schema.ts

```ts
HeroSchema = {
  name: 'Hero Section',
  category: 'hero',
  componentKey: 'webu_general_hero_01',
  // ...
  projectTypes: ['business', 'saas', 'landing'],
  capabilities: ['headline', 'callToAction', 'image'],
}
```

### Phase 5 — Current implementation status

| Requirement | Status | Implementation |
|-------------|--------|----------------|
| **Library contains categories** | ✅ | Canonical list: `builder/componentLibraryCategories.ts` — `COMPONENT_LIBRARY_CATEGORIES`, `COMPONENT_LIBRARY_CATEGORY_LABELS`, `getCategoryLabel()`. Library UI (e.g. Cms) can group by `schema.category`. |
| **Schema includes category** | ✅ | `ComponentSchemaDef.category` (string). Hero uses `category: 'hero'`; others use `sections` / `layout` / `header` / `footer`; can align to canonical categories. |
| **Schema includes projectTypes** | ✅ | `ComponentSchemaDef.projectTypes?: ProjectType[]`. All central-registry schemas (Hero, Features, CTA, Cards, Grid, Header, Footer, Navigation) define projectTypes. |
| **Schema includes capabilities** | ✅ | `ComponentSchemaDef.capabilities?: string[]`. All central-registry schemas define capabilities. Compatibility and filtering use them. |

### Files

- **Categories:** `builder/componentLibraryCategories.ts` — `COMPONENT_LIBRARY_CATEGORIES`, `COMPONENT_LIBRARY_CATEGORY_LABELS`, `getCategoryLabel()`, `isComponentLibraryCategory()`.
- **Schema format:** `builder/componentSchemaFormat.ts` — `ComponentSchemaDef` with `category`, `projectTypes`, `capabilities`.
- **Filtering:** `builder/componentCompatibility.ts` — `isComponentCompatibleWithProjectType()` uses schema.projectTypes and capabilities.
- **Registry:** `builder/registry/componentRegistry.ts` — entries expose schema; `getRegistryIdsForProjectType()`.

---

## PHASE 6 — Project Type Intelligence

### Goal

**Projects must define `projectType`.** The builder **filters the component library** by project type so users only see relevant components (e.g. ecommerce → product/cart/checkout; restaurant → menu/booking/food).

### Project types (canonical)

| projectType  | Description / focus        |
|--------------|----------------------------|
| business     | Corporate, services        |
| ecommerce    | Products, cart, checkout   |
| saas         | App, login, features       |
| portfolio    | Work, projects, image      |
| restaurant   | Menu, reservation, food    |
| hotel        | Booking, menu              |
| blog         | Posts, content             |
| education    | Courses, content           |
| landing      | Marketing, headline, CTA   |

### Builder filters component library

- **Rule:** A component is shown only if (1) its schema allows the current project type (`projectTypes` includes it or is empty) and (2) it has no capability that is **excluded** for that project type.
- **Ecommerce project** shows components with product, cart, checkout, filters capabilities (e.g. **ProductGrid**, **Cart**, **Checkout**, **Filters**). Components with menu, food, booking are hidden.
- **Restaurant project** shows components with menu, food, booking capabilities (e.g. **Menu**, **Reservation**, **FoodGallery**). Components with product, cart, checkout, filters are hidden.

### Examples

| Project type  | Library shows (examples)     | Library hides (examples)        |
|---------------|-----------------------------|---------------------------------|
| **Ecommerce** | ProductGrid, Cart, Checkout, Filters | Menu, Reservation, FoodGallery |
| **Restaurant**| Menu, Reservation, FoodGallery       | ProductGrid, Cart, Checkout, Filters |

(Exact component names depend on registry; filtering is by schema `projectTypes` and capability exclusions.)

### Phase 6 — Current implementation status

| Requirement | Status | Implementation |
|-------------|--------|----------------|
| **Projects define projectType** | ✅ | `builder/projectTypes.ts` — `projectTypes[]`, `ProjectType`, `BuilderProject.projectType`. Store: `projectType`, `setProjectType`; default `landing`. |
| **Builder filters library by project type** | ✅ | `builder/componentCompatibility.ts` — `excludedCapabilitiesByProjectType` (e.g. ecommerce excludes menu, food, booking; restaurant excludes product, cart, checkout, filters), `isComponentCompatibleWithProjectType()`, `getCompatibleRegistryIds()`. |
| **Library uses filtered list** | ✅ | Cms: `projectType` from store; `filteredSectionLibrary` filters central-registry components with `isComponentCompatibleWithProjectType(normalizedKey, projectType)`. |

### Files

- **Project types:** `builder/projectTypes.ts` — `projectTypes`, `ProjectType`, `defaultProjectType`, `BuilderProject`.
- **Filtering:** `builder/componentCompatibility.ts` — `excludedCapabilitiesByProjectType`, `relevantCapabilitiesByProjectType`, `isComponentCompatibleWithProjectType()`, `getCompatibleRegistryIds()`.
- **Store:** `builder/store/builderStore.ts` — `projectType`, `setProjectType`.
- **Library:** Cms uses `projectType` + `filteredSectionLibrary` (see Phase 5 categories).

---

## PHASE 7 — AI Component Refactor Engine

### Goal

**Webu must behave like Cursor for UI:** context-aware refactor suggestions. Example: Header contains a search bar → by project type, AI suggests removing search (business) or replacing it with product search + cart icon + wishlist (ecommerce).

### Example: Header + search bar

| Project type  | AI refactor suggestion |
|---------------|-------------------------|
| **business**  | Remove search (simplify header). |
| **ecommerce** | Replace with **product search**, **cart icon**, **wishlist**. |

Refactors are applied as **prop patches** (e.g. `showSearch`, `searchMode`, `showCartIcon`, `showWishlistIcon`) so the same Header component adapts by project type without deleting or swapping components.

### Supported refactor actions

| Action              | Description | Implementation |
|---------------------|-------------|----------------|
| **remove element**  | Remove a component or sub-element (e.g. remove search → set `showSearch: false`). | `remove_element`; engine emits prop patch. |
| **replace element** | Replace with another behavior or variant (e.g. generic search → product search + cart). | `replace_element`; engine emits prop patch. |
| **add element**     | Add a widget or sub-element (e.g. add product search + cart when header has no search). | `add_element`; engine emits prop patch. |
| **change variant**  | Switch layout/design variant (e.g. header-1 → header-2). | `modify_element_props` (variant) or `restructure_layout`. |
| **modify props**    | Change props (text, visibility, images, etc.). | `modify_element_props`; engine emits prop patch. |

Canonical kinds in code: `remove_element`, `replace_element`, `add_element`, `modify_element_props`, `restructure_layout` (see `refactorActions.ts`).

### Phase 7 — Current implementation status

| Requirement | Status | Implementation |
|-------------|--------|----------------|
| **Cursor-like refactor for UI** | ✅ | `aiRefactorEngine.ts`: rules by project type (e.g. business + header with search → remove search; ecommerce + header → product search + cart + wishlist). |
| **Header: business → remove search** | ✅ | Rule: `projectType: 'business'`, `webu_header_01`, `whenHasSearch: true` → `remove_search`, prop patch `showSearch: false`, `searchMode: 'none'`. |
| **Header: ecommerce → product search, cart, wishlist** | ✅ | Rule: `projectType: 'ecommerce'`, `webu_header_01` → `replace_with_product_search_and_cart` or `add_product_search_and_cart`; prop patch `showSearch: true`, `searchMode: 'product'`, `showCartIcon: true`, `showWishlistIcon: true`. |
| **Supported action kinds** | ✅ | `refactorActions.ts`: `remove_element`, `replace_element`, `add_element`, `modify_element_props`, `restructure_layout` with labels and examples. |
| **Safe application** | ✅ | `safeRefactorRules.ts`: only props / child_elements / layout_variants; no automatic component deletion. `aiProjectProcessor.ts`: builds updates from safe suggestions only. |
| **Optimize command** | ✅ | `optimize_for_project_type` runs `processAndApplyProjectComponents()` → applies refactor patches and updates store. |

### Files

- **Refactor engine:** `builder/aiRefactorEngine.ts` — rules, `analyzeNodeForRefactor()`, `analyzeTreeForRefactor()`, `getRefactorPatchPayload()`.
- **Action kinds:** `builder/refactorActions.ts` — `REFACTOR_ACTION_KINDS`, labels, descriptions, examples.
- **Safe policy:** `builder/safeRefactorRules.ts` — `SAFE_MODIFICATION_TYPES`, `isSafeRefactorSuggestion()`.
- **Processor:** `builder/aiProjectProcessor.ts` — `processProjectComponents()`, `applyProjectComponentUpdates()`, `processAndApplyProjectComponents()`.
- **Command:** `builder/commands/optimizeForProjectType.ts` — `runOptimizeForProjectType()`.

---

## PHASE 8 — AI Website Generation

### Goal

User prompt (e.g. **"Create SaaS landing page"**) → AI generates a full page structure → **builder state creation**. No manual section add required.

### Example: Create SaaS landing page

AI generates (in order):

- **Header**
- **Hero**
- **Features**
- **Pricing**
- **Testimonials**
- **CTA**
- **Footer**

(Pricing and Testimonials appear when corresponding components exist in the registry; otherwise the structure uses Header, Hero, Features, CTA, Footer from the central registry.)

### Process

```
prompt
  ↓
site structure        (ordered list of section types or component keys)
  ↓
component selection   (registry keys: webu_header_01, webu_general_hero_01, …)
  ↓
variant selection     (optional per section: header-1, hero-1, …)
  ↓
props generation      (defaults from registry + optional AI overrides)
  ↓
builder state creation (setProjectType + setComponentTree)
```

### Phase 8 — Current implementation status

| Requirement | Status | Implementation |
|-------------|--------|----------------|
| **Prompt → structure** | ✅ | Backend/AI maps prompt (e.g. "Create SaaS landing page") to a structure; sends `generate_site` tool with `projectType` and optional `structure[]`. |
| **Site structure** | ✅ | `SiteStructureSection[]`: each item has `componentKey`, optional `variant`, optional `props`. |
| **Component selection** | ✅ | `buildTreeFromStructure()` uses registry `getEntry(componentKey)` for defaults; supports all central-registry keys (header, hero, features, cta, footer, navigation, cards, grid). |
| **Variant selection** | ✅ | Per-section `variant` in structure; merged into node. |
| **Props generation** | ✅ | Registry defaults merged with optional section `props`; result is node.props. |
| **Builder state creation** | ✅ | `runGenerateSite({ projectType, structure })` → `buildTreeFromStructure()` → `setProjectType` + `setComponentTree(tree)`. |
| **Chat integration** | ✅ | `handleToolResult`: on `generate_site` tool_result, calls `runGenerateSite(params)`. |

### Files

- **Generation pipeline:** `builder/aiSiteGeneration.ts` — `buildTreeFromStructure()`, `SiteStructureSection`, `DEFAULT_SAAS_LANDING_STRUCTURE`, `DEFAULT_LANDING_STRUCTURE`.
- **Command:** `builder/commands/generateSite.ts` — `GENERATE_SITE_COMMAND`, `runGenerateSite()`.
- **Chat:** `hooks/useBuilderChat.ts` — handles `generate_site` tool_result and calls `runGenerateSite`.

---

## PHASE 9 — Full Code Export

### Goal

User clicks **"Export Website"** → system can generate a full export in one of several formats. Export includes **components**, **assets**, **styles**, **routes**, and **content**.

### Export formats

| Format        | Description        |
|---------------|--------------------|
| **React site**| React app (e.g. Vite + React). |
| **HTML site** | Single or multi-page static HTML. |
| **Next.js site** | Next.js app (SSR/SSG). |
| **Static export** | Static files (HTML/CSS/JS) for any host. |

### Export includes

| Include     | Description |
|------------|-------------|
| **components** | List of used component keys (from tree); backend or bundler includes only these. |
| **assets** | URLs collected from content (images, logos, backgroundImage, etc.) for download or copy. |
| **styles** | Style hints (projectType, breakpoints) for theme/CSS generation. |
| **routes** | Routes array; each route has `path` and `content` (serialized tree). Single-page = one route `/`. |
| **content** | Full serializable page model (component tree with id, componentKey, variant, props). |

### Phase 9 — Current implementation status

| Requirement | Status | Implementation |
|-------------|--------|----------------|
| **Export Website entry point** | ✅ | `runExportWebsite({ format })` builds payload from store and returns payload + JSON. |
| **Format selection** | ✅ | `ExportFormat`: `react`, `html`, `nextjs`, `static`. `EXPORT_FORMATS`, `EXPORT_FORMAT_LABELS`. |
| **Content** | ✅ | Serialized `componentTree` via `toSerializableNode`; included in payload. |
| **Components list** | ✅ | Unique `componentKey`s collected from tree; payload.`components`. |
| **Assets** | ✅ | URLs from props (image, url, src, logo_url, backgroundImage); payload.`assets`. |
| **Styles** | ✅ | payload.`styles`: projectType, breakpoints; extensible for theme. |
| **Routes** | ✅ | payload.`routes`: `[{ path: '/', content }]`; multi-page can add more. |
| **Backend codegen** | — | Payload is JSON; backend or CLI can generate React/HTML/Next.js/static files from it. |

### Files

- **Export pipeline:** `builder/exportWebsite.ts` — `buildWebsiteExportPayload()`, `WebsiteExportPayload`, `ExportFormat`, `serializeWebsiteExportPayload()`, `getWebsiteExportJson()`.
- **Command:** `builder/commands/exportWebsite.ts` — `EXPORT_WEBSITE_COMMAND`, `runExportWebsite()`.
- **Page model:** `builder/core/pageModel.ts` — `serializePageModel`, `toSerializableNode` (used by export).

---

## PHASE 10 — AI Design Generation

### Goal

User can provide a **design source** (screenshot, Figma link, template, design inspiration). **AI converts** the design into **component layout**, **component props**, and **builder structure**, which is then applied to the builder (same as site generation).

### Design inputs (user provides)

| Input | Description |
|-------|--------------|
| **Screenshot** | Image (upload or URL) — AI/vision infers layout and sections. |
| **Figma link** | Figma file or frame URL — AI/Figma API extracts structure and styles. |
| **Template** | Predefined template id — map to a known structure. |
| **Design inspiration** | URL or image of reference design — AI suggests similar layout and sections. |

### AI output (converts design into)

| Output | Description |
|--------|-------------|
| **Component layout** | Ordered list of sections (Hero, Features, CTA, etc.). |
| **Component props** | Per-section props (title, image, copy, colors) inferred from design. |
| **Builder structure** | Same as Phase 8: `structure: SiteStructureSection[]` with `componentKey`, `variant`, `props`. |

### Example

**Screenshot** → AI returns structure:

- Hero (with inferred title, subtitle, image)
- Features (with inferred feature items)
- CTA (with inferred headline, button text)

Frontend applies via `runGenerateSite({ projectType, structure })` so the builder shows the generated page.

### Phase 10 — Current implementation status

| Requirement | Status | Implementation |
|-------------|--------|----------------|
| **Design input types** | ✅ | `builder/aiDesignGeneration.ts` — `DesignInputType`: screenshot, figma_link, template, design_inspiration; `DESIGN_INPUT_LABELS`, `DesignConversionInput`. |
| **Output contract** | ✅ | `DesignConversionOutput` = `SiteStructureSection[]`; same as generate_site. Backend returns structure; frontend applies with existing `runGenerateSite({ projectType, structure })`. |
| **Apply to builder** | ✅ | Reuse Phase 8: when backend sends `generate_site` tool_result with `structure` (from design conversion), chat handler calls `runGenerateSite()` → builder state updated. |
| **Backend conversion** | — | Backend/AI: vision for screenshot, Figma API for link, template map for template, vision for inspiration; returns `structure` in tool or API response. |

### Files

- **Design generation contract:** `builder/aiDesignGeneration.ts` — `DesignInputType`, `DesignConversionInput`, `DesignConversionOutput`, `DesignConversionResult`.
- **Apply to builder:** Same as Phase 8 — `runGenerateSite({ projectType, structure })`, `buildTreeFromStructure()`; chat handles `generate_site` with structure from design or prompt.

---

## PHASE 11 — Design Token System

### Goal

**Global design tokens** for colors, fonts, spacing, radius, shadows. **Components use tokens instead of raw CSS** (e.g. `color: primary`, `spacing: lg`).

### Token categories

| Category | Examples | Use in components |
|----------|----------|--------------------|
| **colors** | primary, secondary, accent, muted, background, foreground | `color: primary`, `backgroundColor: muted` |
| **fonts** | sans, serif, mono, heading, body | `fontFamily: sans` |
| **fontSizes** | xs, sm, base, lg, xl, 2xl, 3xl | `fontSize: lg` |
| **spacing** | none, xs, sm, md, lg, xl, 2xl, 3xl, 4xl | `padding: lg`, `margin: md` |
| **radius** | none, sm, md, lg, xl, 2xl, full | `borderRadius: lg` |
| **shadows** | none, sm, md, lg, xl | `boxShadow: md` |

### Example

Components reference tokens by name; runtime or build resolves to CSS:

- `color: primary` → `var(--color-primary)` or `#0f172a`
- `spacing: lg` → `var(--spacing-lg)` or `1.5rem`

### Phase 11 — Current implementation status

| Requirement | Status | Implementation |
|-------------|--------|----------------|
| **Global design tokens** | ✅ | `builder/designTokens.ts` — `DESIGN_TOKEN_COLORS`, `DESIGN_TOKEN_FONTS`, `DESIGN_TOKEN_FONT_SIZES`, `DESIGN_TOKEN_SPACING`, `DESIGN_TOKEN_RADIUS`, `DESIGN_TOKEN_SHADOWS`. |
| **Token resolution** | ✅ | `getTokenValue(category, name)` → CSS value; `resolveToken('color.primary')` / `resolveToken('spacing.lg')` for path-style refs. |
| **CSS custom properties** | ✅ | `getDesignTokensAsCssVars()` returns `{ '--color-primary': '#0f172a', '--spacing-lg': '1.5rem', ... }` for :root or theme. |
| **Components use tokens** | — | Schema/component props can reference token names (e.g. `backgroundColor: 'primary'`); resolver used at render or export to substitute values or emit var() refs. |

### Files

- **Tokens:** `builder/designTokens.ts` — token maps, `DESIGN_TOKEN_CATEGORIES`, `getTokenValue()`, `resolveToken()`, `getDesignTokensAsCssVars()`, `DESIGN_TOKENS`.

---

## PHASE 12 — Smart Layout Engine

### Goal

**AI must understand layout.** Canonical vocabulary: **grid**, **columns**, **spacing**, **alignment**, **responsive stacking**. Allows AI to **restructure sections** (e.g. change to 3-column grid, add spacing, center align, stack on mobile).

### Layout concepts (AI-understandable)

| Concept | Description | Maps to |
|--------|-------------|--------|
| **grid** | Use grid layout. | Component supports grid; columns/spacing apply. |
| **columns** | Number of columns (1–6). | `columns` or `gridColumns` prop. |
| **spacing** | Gap between items (token: xs, sm, md, lg, xl). | `spacing`, `gap`, or `padding` prop. |
| **alignment** | Horizontal alignment: left, center, right. | `alignment` prop. |
| **responsive stacking** | On mobile: stack to 1 column, wrap, or none. | `responsive` / `gridColumns` per breakpoint (e.g. mobile: 1, tablet: 2). |

### Example

AI restructure: "Make the features section a 3-column grid with large spacing, centered, and stack on mobile."

- Intent: `{ columns: 3, spacing: 'lg', alignment: 'center', responsiveStacking: 'stack' }`
- Engine: `layoutIntentToPropPatch(intent, 'webu_general_features_01')` → props patch
- Apply: `updateComponentProps(nodeId, { patch })` → builder state updated

### Phase 12 — Current implementation status

| Requirement | Status | Implementation |
|-------------|--------|----------------|
| **AI understands layout** | ✅ | `builder/smartLayoutEngine.ts` — `LayoutIntent` (grid, columns, spacing, alignment, responsiveStacking, responsiveColumns), `LAYOUT_VOCABULARY`, `LAYOUT_ALIGNMENT_OPTIONS`, `LAYOUT_COLUMN_OPTIONS`. |
| **Layout → props** | ✅ | `layoutIntentToPropPatch(intent, componentKey)` — schema-aware; only sets props the component accepts (columns, alignment, spacing/gap/padding, responsive). |
| **Read current layout** | ✅ | `getLayoutSummary(node)` returns `LayoutIntent` from node props (for AI to read state). |
| **Restructure sections** | ✅ | Chat/refactor can call `updateComponentProps(nodeId, { patch: layoutIntentToPropPatch(intent, node.componentKey) })`; refactor action `restructure_layout` already exists. |

### Files

- **Layout engine:** `builder/smartLayoutEngine.ts` — `LayoutIntent`, `layoutIntentToPropPatch()`, `getLayoutSummary()`, `LAYOUT_VOCABULARY`, alignment/column options.
- **Refactor actions:** `builder/refactorActions.ts` — `restructure_layout` kind for move/reorder/layout change.
- **Apply:** Same as Phase 4 — `updateComponentProps(nodeId, payload)` with patch from layout engine.

---

## PHASE 13 — Smart Image System

### Goal

**Image fields support** multiple sources: **upload**, **Unsplash**, **AI generation**, **media library**. User can say e.g. **"Generate hero image"** and the system sets the image prop from the chosen source.

### Image field sources

| Source | Description |
|--------|-------------|
| **Upload** | User uploads a file; stored and URL (or asset id) set on the image prop. |
| **Unsplash** | Search/select from Unsplash; URL set on the image prop. |
| **AI generation** | AI generates an image from a prompt; result URL set on the image prop. |
| **Media library** | Pick from project media library; asset id or URL set on the image prop. |

### Example

**"Generate hero image"** → Backend (or UI) uses AI generation / Unsplash / media library → returns image URL → frontend calls `updateComponentProps(heroNodeId, { path: 'image', value: url })` → hero section shows the new image.

### Phase 13 — Current implementation status

| Requirement | Status | Implementation |
|-------------|--------|----------------|
| **Image source types** | ✅ | `builder/smartImageSystem.ts` — `ImageFieldSource`: upload, unsplash, ai_generation, media_library; `IMAGE_FIELD_SOURCE_LABELS`. |
| **Discover image props** | ✅ | `getImagePropPathsForComponent(componentKey)` returns schema props that are type image/icon or image-like (image, backgroundImage, logo_url, etc.). |
| **Primary image prop** | ✅ | `getPrimaryImagePropForComponent(componentKey)` — e.g. Hero → `'image'`; used when AI/UI needs "set the main image". |
| **Set image on component** | ✅ | Same as Phase 4: `updateComponentProps(componentId, { path: 'image', value: url })`. Chat/backend sends tool with path + value; optional `SetImagePayload` with source. |
| **Upload / Unsplash / AI / library** | — | Backend or existing app: upload endpoint, Unsplash API, AI image API, media library API; return URL (or asset id) and caller uses updateComponentProps. |

### Files

- **Smart image system:** `builder/smartImageSystem.ts` — `ImageFieldSource`, `getImagePropPathsForComponent()`, `getPrimaryImagePropForComponent()`, `SetImagePayload`.
- **Apply:** Same as Phase 4 — `updateComponentProps(componentId, { path, value })` for image path and URL.

---

## PHASE 14 — Performance Optimization

### Goal

Builder must support **lazy components**, **virtual canvas**, **fast drag**, and **fast rerender**.  
**Target: &lt;50ms interaction time** (from user action to visible response).

### Requirements

| Area | Description |
|------|-------------|
| **Lazy components** | Section components load on demand (e.g. React.lazy) to reduce initial bundle and TTI. |
| **Virtual canvas** | Only sections in (or near) the viewport are mounted; scroll position drives which items render. |
| **Fast drag** | Drag start and reorder feel instant: pointer sensor with activation distance, transform-based overlay, minimal layout work. |
| **Fast rerender** | Selection/hover/prop updates don’t cause full canvas re-renders: stable keys, memoized section rows, scoped updates. |

### Phase 14 — Current implementation status

| Requirement | Status | Implementation |
|-------------|--------|----------------|
| **Target &lt;50ms** | ✅ | `builder/performanceOptimization.ts` — `INTERACTION_TARGET_MS = 50`. |
| **Lazy components** | ✅ | `LazySectionFactory` type, `lazySectionRegistry`, `getLazySectionFactory(componentKey)`; use with `React.lazy()` in canvas or wrapper. |
| **Virtual canvas** | ✅ | `getVisibleRange(containerHeight, scrollTop, itemCount, getItemHeight?)` → `startIndex`, `endIndex`, `offsetTop`, `totalHeight`; render only `sections.slice(startIndex, endIndex + 1)` with spacer above. |
| **Fast drag** | ✅ | `DRAG_ACTIVATION_DISTANCE_PX = 5`, `DRAG_ACTIVATION_DELAY_MS = 0`; use PointerSensor with this activation; DragOverlay with CSS transform. |
| **Fast rerender** | ✅ | `getSectionRowKey(localId)`; canvas uses `key={section.localId}`; memoize section row (e.g. EditableNodeWrapper or wrapper) and pass stable props. |

### Files

- **Performance module:** `builder/performanceOptimization.ts` — target constant, lazy registry, `getVisibleRange()`, drag constants, `getSectionRowKey()`.
- **Canvas:** `builder/visual/BuilderCanvas.tsx` — already uses stable `key={section.localId}`; can integrate virtual range and lazy components when enabled.
- **Drag:** Cms uses `@dnd-kit`; apply PointerSensor + activation distance and transform-based overlay to meet &lt;50ms.

---

## PHASE 15 — AI Prompt to Site Engine

### Goal

**Ultimate feature:** User writes one natural-language prompt → AI generates the **full site**.

Example: *"Create a modern ecommerce website for a furniture store"* → AI returns project type + full structure with content (hero title, features, CTAs) → builder shows the complete page.

### Flow

1. **User:** Types a single prompt (e.g. "Create a modern ecommerce website for a furniture store").
2. **Backend AI:** Parses intent (ecommerce, furniture, modern) → chooses `projectType` and section structure → fills section `props` (title, subtitle, feature copy, etc.) from context.
3. **Tool:** AI responds with `generate_site` tool result: `{ projectType, structure }` where `structure[]` includes `componentKey`, optional `variant`, and optional `props` per section.
4. **Frontend:** Existing chat handler receives tool result → `runGenerateSite({ projectType, structure })` → `buildTreeFromStructure()` → builder state updated → full site in canvas.

### Phase 15 — Current implementation status

| Requirement | Status | Implementation |
|-------------|--------|----------------|
| **Single-prompt entry** | ✅ | User message goes to chat → backend; backend returns `generate_site` with full payload. |
| **Prompt → project type + structure** | ✅ | Backend infers from prompt; frontend applies via `runGenerateSite()`. Contract and hints in `builder/aiPromptToSite.ts`. |
| **Ecommerce default structure** | ✅ | `DEFAULT_ECOMMERCE_STRUCTURE` in `aiSiteGeneration.ts`; `getDefaultStructureForProjectType('ecommerce')` in `generateSite.ts` uses it. |
| **Prompt-to-site contract** | ✅ | `PromptToSiteInput`, `PromptToSiteOutput`, `PROMPT_TO_SITE_HINTS`, `getDefaultStructureForPrompt(projectType)` — backend uses these to fill structure. |
| **Full site in builder** | ✅ | Same as Phase 8: `runGenerateSite()` → `setProjectType` + `setComponentTree`; chat already handles `generate_site` tool. |

### Files

- **Prompt-to-site engine:** `builder/aiPromptToSite.ts` — `PromptToSiteInput`/`PromptToSiteOutput`, `PROMPT_TO_SITE_HINTS`, `getDefaultStructureForPrompt()`.
- **Structures:** `builder/aiSiteGeneration.ts` — `DEFAULT_ECOMMERCE_STRUCTURE`; `buildTreeFromStructure()`.
- **Command:** `builder/commands/generateSite.ts` — `runGenerateSite()` uses ecommerce default when `projectType === 'ecommerce'`.
- **Chat:** `hooks/useBuilderChat.ts` — on `generate_site` tool result, calls `runGenerateSite({ projectType, structure })`.

---

## Summary

- **Phase 1 is done:** schema-driven, props-driven, centralized registry, serializable page structure, and the desired component and data model are in place.
- **Phase 2 is done:** Hover/click, border, drag handles (drop zones + overlay grip), edit/delete icons in canvas overlay; selection synced with Sidebar, Chat, and Builder state.
- **Phase 3 is done:** Sidebar generated from schema only; groups (content, style, layout, advanced); controls by type (text, color, image, spacing, alignment, etc.); no manual inspector coding per component.
- **Phase 4 is done:** Chat editing engine — chat message → AI intent → component id → field → update payload → `updateComponentProps` (same pipeline as Sidebar); payload supports `componentId` + `field` or `path` + `value`.
- **Phase 5 is done:** Component library system — canonical categories (Layout, Hero, Features, Pricing, Testimonials, Forms, Footers, Navigation, Ecommerce, Blog, Restaurant); each schema includes category, projectTypes, capabilities; Hero example aligned.
- **Phase 6 is done:** Project type intelligence — projects define projectType (business, ecommerce, saas, portfolio, restaurant, hotel, blog, education, landing); builder filters component library by project type (e.g. ecommerce → product/cart/checkout; restaurant → menu/booking/food).
- **Phase 7 is done:** AI component refactor engine — Cursor-like refactors for UI (e.g. Header: business → remove search; ecommerce → product search, cart icon, wishlist); supported actions: remove element, replace element, add element, change variant, modify props; safe application via prop patches and optimize command.
- **Phase 8 is done:** AI website generation — prompt → site structure → component selection → variant selection → props generation → builder state creation; `generate_site` command and `buildTreeFromStructure()`; chat handles tool and runs `runGenerateSite()`.
- **Phase 9 is done:** Full code export — "Export Website" with formats (React, HTML, Next.js, Static); payload includes components, assets, styles, routes, content; `runExportWebsite()` and `buildWebsiteExportPayload()`.
- **Phase 10 is done:** AI design generation — user provides screenshot, Figma link, template, or design inspiration; AI converts to component layout + props + builder structure; output contract in `aiDesignGeneration.ts`; apply via existing `runGenerateSite({ structure })`.
- **Phase 11 is done:** Design token system — global tokens for colors, fonts, fontSizes, spacing, radius, shadows; `getTokenValue()`, `resolveToken()`, `getDesignTokensAsCssVars()`; components can use token names (e.g. color: primary, spacing: lg).
- **Phase 12 is done:** Smart layout engine — AI-understandable layout (grid, columns, spacing, alignment, responsive stacking); `LayoutIntent`, `layoutIntentToPropPatch()`, `getLayoutSummary()`; allows AI to restructure sections via `updateComponentProps` + restructure_layout.
- **Phase 13 is done:** Smart image system — image fields support upload, Unsplash, AI generation, media library; `getImagePropPathsForComponent()`, `getPrimaryImagePropForComponent()`; "Generate hero image" flows via `updateComponentProps` with image path + URL.
- **Phase 14 is done:** Performance optimization — target &lt;50ms interaction; lazy components (registry + React.lazy), virtual canvas (`getVisibleRange()`), fast drag (activation distance/delay constants), fast rerender (stable keys, `getSectionRowKey()`).
- **Phase 15 is done:** AI prompt to site engine — user writes one prompt (e.g. "Create a modern ecommerce website for a furniture store") → AI returns generate_site with projectType + structure + content → runGenerateSite() → full site; `aiPromptToSite.ts` contract and hints, `DEFAULT_ECOMMERCE_STRUCTURE`, ecommerce default in generateSite.
- **Roadmap doc:** Use this file as the single reference for the full AI builder vision and for **Phase 2+** (e.g. automatic site generation, more AI tools, export formats).
- **Next phases** (to be detailed when needed): automatic site generation, deeper chat/AI integration, and full website export options.

---

## FINAL RESULT — Webu Product Vision

**Webu becomes:**

| Pillar | Meaning |
|--------|--------|
| **AI Website Builder** | One prompt → full site (Phase 15). Generate structure, content, and layout from natural language. |
| **Visual Editor** | Click/hover selection, drag-and-drop, schema-driven sidebar, canvas overlay (Phases 1–2, 3). Edit in place with &lt;50ms response (Phase 14). |
| **Code Generator** | Serializable page model; export to React, HTML, Next.js, static (Phase 9). Design tokens and clean output. |
| **AI Designer** | Design from screenshot/Figma/inspiration → structure + props (Phase 10). Refactor for project type (Phase 7). Smart layout and images (Phases 12–13). |
| **Component Platform** | Central registry, schema-driven library, project-type filtering (Phases 1, 5–6). Add components without touching canvas/sidebar/chat. |

**Comparable to:** Framer AI · Lovable · Builder.io · Webflow AI
