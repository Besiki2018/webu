# Schema-Driven Builder Architecture — Verification Summary

This document confirms that the **schema-driven builder is wired into the real Webu Builder runtime**. Completion is based on **runtime behavior**, not only on the presence of schema, defaults, or registry files.

---

## Runtime criteria (all verified)

| Criterion | Status | How verified |
|-----------|--------|--------------|
| Canvas renders components **through** the registry | ✅ | BuilderCanvas uses only `getComponentRuntimeEntry(section.type)` and `getCentralRegistryEntry(section.type)`; no direct Header/Footer/Hero imports. Tests: Phase 9 #3, BuilderCanvas.test.tsx, registryIntegration.test.tsx, legacyDetection.test.ts. |
| Sidebar generates controls **from** schema | ✅ | Cms builds `sectionSchemaByKey` from `builderSectionLibrary` **plus** `getAvailableComponents()` → `getComponentSchemaJson(id)` so every registry id has schema. Fields from `collectSchemaPrimitiveFields(selectedSectionSchemaProperties)`; control type from `getSchemaFieldControlType(field)`. Tests: Phase 9 #4, #4b. |
| Props updates **rerender** components | ✅ | `updateSectionPathProp` → `updateComponentProps` → `applyBuilderUpdatePipeline` → `applyMutationState(result.state)` updates Zustand store → `sectionsDraft` → BuilderCanvas receives new `sections` → DOM updates. Tests: Phase 9 #5, #5b, runtimeVerification.test.tsx, updatePipeline.test.ts. |
| Components render **purely from props** | ✅ | Canvas passes `ensureFullComponentProps(defaults, mapBuilderProps(props))` to central components; Hero/Header/Footer use only props. Tests: Phase 9 #3 (DOM shows only prop-driven content). |
| Update pipeline is **unified** | ✅ | Sidebar: `updateSectionPathProp` → `updateComponentProps`. Chat: `builder:apply-change-set` → `applyBuilderChangeSetPipeline` → `applyBuilderUpdatePipeline`. Same validation and patch path. Tests: updatePipeline.test.ts. |
| Ready for **chat-driven editing** and **AI generation** | ✅ | Chat sends change set; pipeline converts to builder ops and applies; same state/rerender path as sidebar. |

---

## Phase-by-phase verification

### Phase 1 — Component registry integration

- **Central registry** (`builder/centralComponentRegistry.ts`): `REGISTRY_ID_TO_KEY` + `componentRegistry` with **header**, **footer**, **hero**. Each entry has `component`, `schema`, `defaults`, `mapBuilderProps`.
- **Main registry** (`builder/componentRegistry.ts`): `REGISTRY` keyed by registry id (e.g. `webu_header_01`, `webu_footer_01`, `webu_general_hero_01`, `webu_general_heading_01`, `webu_general_cta_01`, `webu_general_card_01`, …). `getComponentRuntimeEntry(id)` returns component (central or Builder*CanvasSection), schema, defaults.
- **Tests:** `registryIntegration.test.tsx`, `architectureValidation.test.ts`, Phase 9 #1.

### Phase 2 — Canvas renderer uses registry

- **File:** `builder/visual/BuilderCanvas.tsx`.
- **Pattern:** For each section, `runtimeEntry = getComponentRuntimeEntry(section.type)`; `props = resolveComponentProps(section.type, section.props ?? section.propsText)`; if `getCentralRegistryEntry(section.type)` → `<Component {...componentProps} />`, else → `<CanvasComponent ... props={props} />`. No direct imports of section components.
- **Tests:** Phase 9 #3, BuilderCanvas.test.tsx, registryIntegration “canvas does not import Header/Footer/Hero directly”, legacyDetection.test.ts.

### Phase 3 — Props flow

- **Default + saved + responsive:** `resolveComponentProps(registryId, propsInput)` in `componentRegistry.ts` merges `getDefaultProps(registryId)` with `parseComponentProps(propsInput)` (supports string or object). Responsive overrides live in merged props (e.g. `responsive.desktop.padding_top`).
- **Tests:** architectureValidation #2, #8; Phase 9 #2; runtimeVerification “Responsive overrides work”.

### Phase 4 — Sidebar inspector uses schema

- **File:** `Pages/Project/Cms.tsx`.
- **Schema source:** `sectionSchemaByKey` = library items (with `getComponentSchemaJson` fallback) **plus** every `getAvailableComponents()` id via `getComponentSchemaJson(registryId)` so any section on the page has schema.
- **Field list:** `selectedSectionSchemaProperties` from `sectionSchemaByKey.get(type)` → `collectSchemaPrimitiveFields(...)` → `selectedSectionSchemaFields`.
- **Control type:** `getSchemaFieldControlType(field)` (e.g. text, textarea, color, image, link, menu, select, toggle, number, spacing, alignment).
- **Tests:** Phase 9 #4, #4b; architectureValidation #3, #9.

### Phase 5 — Update pipeline

- **Unified entry:** `updateComponentProps(state, componentId, payload, source)` and `applyBuilderUpdatePipeline(state, operations, options)` in `builder/state/updatePipeline.ts`.
- **Sidebar:** `updateSectionPathProp(localId, path, value)` → `updateComponentProps(..., 'sidebar')` → then `sectionsDraftRef.current = result.state.sectionsDraft`; `applyMutationState(result.state)`.
- **Chat:** Message `builder:apply-change-set` → `applyBuilderChangeSetPipeline(state, changeSet, { createSection })` → same state update and `applyMutationState`.
- **Pipeline:** Validates section existence, field against schema, value type; patches props; returns new state. Caller applies state and triggers rerender.
- **Tests:** updatePipeline.test.ts (sidebar + change set + updateComponentProps); Phase 9 #5, #5b.

### Phase 6 — Builder data model

- **Section in state:** `BuilderSection` (`builder/visual/treeUtils.ts`): localId, type, props?, propsText, propsError, bindingMeta?.
- **Serializable instance:** `BuilderComponentInstance` (`builder/types.ts`): id, componentKey, variant?, props, children?, responsive?, metadata?. `sectionToComponentInstance(section)` maps BuilderSection → BuilderComponentInstance.
- **Canvas** depends only on section + registry (no hidden JSX assumptions).
- **Tests:** architectureValidation #10; Phase 9 #6, #7.

### Phase 7 — Runtime verification

- **Tests in** `runtimeVerification.test.tsx`: select component, sidebar loads params, change value, component rerenders, state sync, variant switching, responsive overrides, canvas renders from state.
- **Phase 9 #3, #5b:** Canvas receives sections; pipeline result.state.sectionsDraft → BuilderCanvas → DOM shows updated value.

### Phase 8 — Legacy detection

- **Canvas** does not import Header/Footer/Hero; only `centralComponentRegistry` and `componentRegistry` (for schema/resolution) reference section paths. Legacy section types use Builder*CanvasSection from main REGISTRY; no parallel conflicting implementations.
- **Tests:** legacyDetection.test.ts.

### Phase 9 — Testing

- **File:** `builder/__tests__/phase9ArchitectureValidation.test.tsx` (9 tests).
- **Coverage:** Registry integrity, schema/defaults consistency, render-from-props, sidebar field generation (+ every registry id has schema), prop update rerender (+ flow to canvas), variant rendering, legacy compatibility.
- **All 101 builder tests pass** (componentRegistry, BuilderCanvas, updatePipeline, runtimeVerification, legacyDetection, architectureValidation, componentValidation, etc.).

### Phase 10 — Migration report

- **File:** `builder/docs/PHASE10_MIGRATION_REPORT.md`.
- **Contents:** Components confirmed working (Header, Footer, Hero); legacy components list; registry summary; schema format; data model; files modified; remaining blockers (none). Emphasizes runtime verification and references `RUNTIME_VERIFICATION.md`.

---

## Key file locations

| Purpose | Path |
|---------|------|
| Main registry | `Install/resources/js/builder/componentRegistry.ts` |
| Central registry (Header, Footer, Hero) | `Install/resources/js/builder/centralComponentRegistry.ts` |
| Canvas renderer | `Install/resources/js/builder/visual/BuilderCanvas.tsx` |
| Update pipeline | `Install/resources/js/builder/state/updatePipeline.ts` |
| Sidebar (schema + pipeline) | `Install/resources/js/Pages/Project/Cms.tsx` |
| Builder state (applyMutationState) | `Install/resources/js/builder/state/builderEditingStore.ts` |
| Data model types | `Install/resources/js/builder/types.ts`, `builder/visual/treeUtils.ts` |
| Runtime verification (code paths) | `Install/resources/js/builder/docs/RUNTIME_VERIFICATION.md` |
| Phase 10 report | `Install/resources/js/builder/docs/PHASE10_MIGRATION_REPORT.md` |

---

## How to re-verify

1. **Run builder tests:**  
   `npx vitest run resources/js/builder --reporter=verbose`  
   Expect: 18 test files, 101 tests passed.

2. **Manual runtime check (optional):**  
   In the app, open a page in the builder → add a Hero → select it → change title in sidebar → confirm canvas title updates. Confirm sidebar shows schema-driven fields (title, subtitle, button, variant, etc.).

3. **Code-path check:**  
   See `RUNTIME_VERIFICATION.md` for exact call chains (canvas → registry, sidebar → sectionSchemaByKey, updateSectionPathProp → updateComponentProps → applyMutationState).

---

**Conclusion:** The schema-driven builder architecture is **verified and fully wired** into the real Webu Builder runtime. Canvas, sidebar, and update pipeline use the registry and schema; props flow and rerender are validated by tests and code paths.
