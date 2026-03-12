# Builder — Final Result & Architecture

This document confirms that the builder supports the required capabilities and that the architecture scales to hundreds of components without redesign.

---

## Project context & intelligent refactor (Cursor-style)

The builder **understands project context**, **filters components by project type**, **identifies component capabilities**, **refactors components intelligently**, and **modifies structures safely** — similar to how Cursor refactors code, but applied to visual components.

| Requirement | Supported | How |
|-------------|-----------|-----|
| **Understand project context** | ✅ | `projectTypes.ts`: `projectType` (business, ecommerce, saas, portfolio, restaurant, hotel, blog, landing, education). Store holds `projectType`; `BuilderProject` and `isProjectType()` used everywhere. Metadata injection adds `projectTypes` and `capabilities` to each node. |
| **Filter components by project type** | ✅ | `componentCompatibility.ts`: `isComponentCompatibleWithProjectType()` uses schema `projectTypes` + capability exclusions (`excludedCapabilitiesByProjectType`). Cms uses it for `filteredSectionLibrary` so the component library only shows compatible sections. |
| **Identify component capabilities** | ✅ | Schemas define `capabilities` (e.g. navigation, search, product, cart, menu, booking). `componentMetadataInjection.ts`: `injectTreeMetadata()` attaches capabilities to every node; `getInstanceMetadata()` reads them. Registry and compatibility engine use capabilities for filtering and rules. |
| **Refactor components intelligently** | ✅ | `aiRefactorEngine.ts`: rules by project type (e.g. business + header with search → remove search; ecommerce + header → product search + cart). `analyzeTreeForRefactor()` scans tree and produces `RefactorSuggestion[]` with `propPatch`. `aiProjectProcessor.ts`: `processProjectComponents()` analyzes type, scans tree, runs compatibility, collects suggestions, builds safe updates. |
| **Modify component structures safely** | ✅ | `safeRefactorRules.ts`: only `props`, `child_elements`, `layout_variants` allowed; never delete entire components; suggest replacement when unsafe. Engine emits only prop patches; `isSafeRefactorSuggestion()` gates which suggestions become `updates`. `applyProjectComponentUpdates()` applies patches to the tree without removing nodes. |
| **Cursor-like refactor for visual components** | ✅ | Single flow: **analyze** (project type + tree) → **compatibility** (per node) → **suggest** (refactor rules) → **apply** (safe prop patches). Command `optimize_for_project_type` (e.g. “Optimize site for ecommerce”) runs `runOptimizeForProjectType()` → `processAndApplyProjectComponents()` → store `projectType` + `setComponentTree(updatedTree)`. Chat handles tool result and updates UI. |

---

## Required capabilities — status

| Capability | Supported | How |
|------------|-----------|-----|
| **Visual editing** | ✅ | Canvas: click selects node → `store.selectedComponentId`; hover sets `hoveredComponentId`. Selection/hover outlines on nodes. Empty canvas click clears selection. `CanvasRenderer` wraps each node with `data-builder-id`, `data-component-key`, `data-variant` for targeting. |
| **Sidebar editing** | ✅ | `SidebarInspector`: reads `getEntry(node.componentKey).schema`, derives fields from `schema.props` or `schema.fields`, renders controls by type (text, textarea, number, color, select, toggle, etc.). Changes go through `updateComponentProps(node.id, { path, value })` → store update → rerender. |
| **Chat editing** | ✅ | `getSelectionContext()` / `useChatTargeting()` expose selected node, schema, `editableFields`, `allowedUpdates`. Chat calls **same pipeline**: `updateComponentProps(selectedComponentId, { path, value })`. Validation (field in schema) inside pipeline. See `builder/docs/CHAT_TARGETING.md`. |
| **Schema-driven components** | ✅ | Every component is **registry-driven**: entry has `component`, `schema`, `defaults`, optional `mapBuilderProps`. Canvas and sidebar never hardcode component list; they iterate `componentTree` and lookup `getEntry(node.componentKey)`. New component = new registry entry. |
| **Variants** | ✅ | `node.variant` is canonical; passed into props; `updateComponentProps` keeps `node.variant` in sync when `path === 'variant'`. Schema can define variant select options. Canvas passes `node.variant ?? node.props.variant` into merged props. See `builder/docs/VARIANT_SYSTEM.md`. |
| **Responsive props** | ✅ | `ResponsiveValue<T>` type and helpers in `builder/utils/responsiveValue.ts`: `getResponsiveValue`, `getResponsiveValueOr`, `setResponsiveValue`, `isResponsiveValue`. Store has `currentBreakpoint`. Schema can mark fields responsive; components can read by breakpoint. See `builder/docs/RESPONSIVE_SUPPORT.md`. |
| **Future AI generation** | ✅ | **Same data model**: page = serializable `BuilderPageModel` (array of nodes with `id`, `componentKey`, `variant`, `props`, `children`). AI can output a tree or delta; apply via `setComponentTree` or `updateComponentProps`. **Same pipeline**: no special path for AI. Chat targeting already provides `editableFields` / `allowedUpdates` for natural-language edits. |
| **Full site export** | ✅ | **Serialization**: `serializePageModel(componentTree)` → JSON string. `parsePageModel(json)` → `BuilderPageModel`. `toSerializableNode(node)` normalizes for export. Page model is JSON-serializable; no functions or non-data. Export = `JSON.stringify(componentTree)` or `serializePageModel(useBuilderStore.getState().componentTree)`. |

---

## Architecture — adding hundreds of components

The system is **registry-centric**. Adding a new component does **not** require changing canvas, sidebar, store, or update logic.

### 1. Single registry contract

- **Entry shape:** `{ component, schema, defaults, mapBuilderProps? }`.
- **Lookup:** `getEntry(registryId)` → entry or null.
- **Canvas:** For each node, `getEntry(node.componentKey)` → merge defaults with node.props → render `entry.component` with props. If no entry → show placeholder.
- **Sidebar:** For selected node, `getEntry(node.componentKey).schema` → derive fields → render controls → `updateComponentProps(node.id, { path, value })`.
- **Chat:** Same: selection context uses `getEntry(selectedNode.componentKey).schema` for editable fields; updates use `updateComponentProps`.

No component-specific branches in canvas or sidebar. All behavior is driven by **schema** and **registry**.

### 2. Adding a new component (repeatable)

1. **Implement the component** — React component that receives all content via props (no hardcoded copy).
2. **Define schema** — `schema.props` or `schema.fields` (keys, types, labels, defaults, groups).
3. **Define defaults** — default prop values (merged at render time).
4. **Register** — Add one entry to `REGISTRY_ID_TO_KEY` and `componentRegistry` in `builder/registry/componentRegistry.ts` (and optional `mapBuilderProps` if builder prop names differ from component API).

No changes to:

- `CanvasRenderer` (it already loops `componentTree` and calls `getEntry`)
- `SidebarInspector` (it already reads `entry.schema` and builds controls)
- `updateComponentProps` (it already validates against schema and patches props)
- Chat targeting (it already derives editable fields from schema)
- Store or page model (they already hold a tree of `{ id, componentKey, variant, props, children }`)

### 3. Scale

- **Hundreds of components:** Add hundreds of registry entries. Canvas and sidebar stay the same; they only need `getEntry(node.componentKey)` to return an entry.
- **Categories / filtering:** Can be added later (e.g. `schema.category`) for library UI; does not change core architecture.
- **Lazy loading:** Registry could later load component/schema/defaults on demand by registryId; contract (getEntry → entry) unchanged.

### 4. Data model stability

- **Page = list of nodes.** Each node = `id`, `componentKey`, `variant?`, `props`, `children?` (and optional `responsive`, `metadata`).
- **No hidden UI state in the tree.** Everything the canvas needs to render is in the tree. Export/save = serialize that tree.
- **AI / import:** Accept a tree (or patch) and call `setComponentTree` or apply updates; no second “source of truth”.

---

## File map (quick reference)

| Concern | Location |
|--------|----------|
| Registry (schema-driven) | `builder/registry/componentRegistry.ts` |
| Page model & serialization | `builder/core/pageModel.ts`, `builder/docs/PAGE_MODEL.md` |
| Canvas (visual editing) | `builder/renderer/CanvasRenderer.tsx` |
| Sidebar (schema-driven UI) | `builder/inspector/SidebarInspector.tsx` |
| Update pipeline (sidebar + chat) | `builder/updates/updateComponentProps.ts` |
| Chat targeting | `builder/updates/chatTargeting.ts`, `builder/docs/CHAT_TARGETING.md` |
| Variants | `node.variant` + schema; `builder/docs/VARIANT_SYSTEM.md` |
| Responsive | `builder/utils/responsiveValue.ts`, `builder/docs/RESPONSIVE_SUPPORT.md` |
| Store | `builder/store/builderStore.ts` |
| **Project context & refactor** | |
| Project types | `builder/projectTypes.ts` |
| Component compatibility (filter by type) | `builder/componentCompatibility.ts` |
| Capabilities & metadata on nodes | `builder/componentMetadataInjection.ts` |
| AI refactor engine (rules, suggestions) | `builder/aiRefactorEngine.ts` |
| Safe refactor policy | `builder/safeRefactorRules.ts` |
| Project processor (analyze → apply) | `builder/aiProjectProcessor.ts` |
| Optimize command (builder + chat) | `builder/commands/optimizeForProjectType.ts`, `hooks/useBuilderChat.ts` |
| Tests | `builder/__tests__/phase17BuilderTests.test.tsx`, `builder/commands/__tests__/optimizeForProjectType.test.ts` |
| Migration report | `builder/docs/PHASE18_MIGRATION_REPORT.md` |
| **Full AI builder roadmap** | `builder/docs/WEBU_AI_BUILDER_ROADMAP.md` (goal, Phase 1 status, future phases) |

---

## Summary

- **Visual editing:** Click/hover selection, outlines, single source of truth in store.
- **Sidebar editing:** Schema-driven controls; same update pipeline.
- **Chat editing:** Selection context + same `updateComponentProps`; no separate path.
- **Schema-driven components:** Registry-only; no hardcoded component list in canvas/sidebar.
- **Variants:** Canonical on node; schema + pipeline support.
- **Responsive props:** Types and helpers; store breakpoint; schema can mark fields.
- **Future AI:** Same tree shape and update pipeline; chat targeting ready.
- **Full site export:** Serializable page model + `serializePageModel` / `parsePageModel`.

**Adding hundreds of components:** Add registry entries (component + schema + defaults); no redesign of canvas, sidebar, store, or pipeline.

**Project context & refactor (final result):** The builder understands project context (project type in store + metadata on nodes), filters the component library by project type and capabilities, identifies capabilities from schemas and injects them into the tree, refactors components intelligently via rule-based suggestions (e.g. header search/cart by project type), and applies only safe modifications (props, child elements, layout variants; never deletes components). The flow is Cursor-like: analyze → suggest → apply, exposed as the “Optimize for project type” command (e.g. “Optimize site for ecommerce”) from chat or future UI.
