# Runtime verification — schema-driven architecture

**The architecture is only considered complete when runtime wiring is verified, not when schema/defaults/registry files merely exist.**

Completion criteria:

1. Canvas renders components **through** the registry
2. Sidebar generates controls **from** schema
3. Props updates **rerender** components
4. Components render **purely from props**
5. Update pipeline is **unified** (sidebar + chat same path)
6. System is ready for chat-driven editing and future AI generation

Below: code paths and how each is verified.

---

## 1. Canvas renders through registry

**Requirement:** No direct imports of section components in the canvas. Every section is resolved by `section.type` → registry → component + props.

**Code path:**

- `BuilderCanvas.tsx` → `renderRegistrySection(section, displayLabel)`:
  - `runtimeEntry = getComponentRuntimeEntry(section.type)` — component comes from canonical `componentRegistry.ts` (full-fidelity or Builder*CanvasSection).
  - `props = resolveComponentProps(section.type, section.props ?? section.propsText)` — props from state + registry defaults.
  - If `getCentralRegistryEntry(section.type)` from `componentRegistry.ts` resolves → `<Component {...componentProps} />` (Header/Footer/Hero).
  - Else → `<CanvasComponent ... props={props} />` (legacy Builder*CanvasSection).

**Verification:**

- No direct imports of Header, Footer, Hero, or any section component in `BuilderCanvas.tsx` (only canonical registry helpers such as `getComponentRuntimeEntry`, `getCentralRegistryEntry`, `resolveComponentProps`).
- Test: Phase 9 #3 — render Hero section with custom props; assert DOM shows those prop values (no placeholder).
- Test: `BuilderCanvas.test.tsx` — "renders registry-backed component content instead of the generic placeholder".
- Test: `legacyDetection.test.ts` — canvas does not import section components.

---

## 2. Sidebar generates controls from schema

**Requirement:** Field list and control types come from schema (registry or server), not hardcoded per component.

**Code path:**

- `Cms.tsx`:
  - `builderSectionLibrary` — each item’s `schema_json` = server `schema_json` or **fallback:** `getComponentSchemaJson(normalizedKey)`.
  - `sectionSchemaByKey` — built from `builderSectionLibrary`; **plus** for every `getAvailableComponents()` id not already in the map, `getComponentSchemaJson(registryId)` is added. So any section on the page has schema available for the sidebar even if the server library didn’t include it.
  - For selected section: `selectedSectionSchemaProperties = sectionSchemaByKey.get(typeToUse)` → `selectedSectionSchemaFields = collectSchemaPrimitiveFields(selectedSectionSchemaProperties)`.
  - Control type: `getSchemaFieldControlType(field)` from `field.definition.builder_field_type` (schema-driven).

**Verification:**

- Test: Phase 9 #4 — Hero schema has fields with path/type/group; `getComponentSchemaJson` has `properties`.
- Test: Phase 9 #4b — Every registry id has `getComponentSchemaJson(id)` returning usable schema (so sidebar can generate controls for any section type).
- Phase 4 doc: sidebar uses `getComponentSchemaJson` / section schema; control type from `builder_field_type`.

---

## 3. Props updates rerender components

**Requirement:** Editing a prop in the sidebar (or via chat) updates state; canvas receives new sections and rerenders with new content.

**Code path:**

- Sidebar: `updateSectionPathProp(localId, path, value)` → `updateComponentProps({ sectionsDraft, selectedSectionLocalId, selectedBuilderTarget }, { path, value }, 'sidebar')` → `applyBuilderUpdatePipeline` → `result.state`.
- Then: `sectionsDraftRef.current = result.state.sectionsDraft`; `applyMutationState(result.state)`.
- `applyMutationState` (in `builderEditingStore.ts`) updates Zustand store: `sectionsDraft`, `selectedSectionLocalId`, `selectedBuilderTarget`.
- `useBuilderCanvasState()` exposes `sectionsDraft` from the store; Cms passes `sections={sectionsDraft}` to `<BuilderCanvas />`.
- So: pipeline → new state → store update → React rerender → BuilderCanvas gets new `sections` → `resolveComponentProps(section.type, section.props ?? section.propsText)` produces new props → component rerenders with new content.

**Verification:**

- Test: Phase 9 #5 — Pipeline set-field updates `sectionsDraft`; `resolveComponentProps(section)` returns the new value.
- Test: Phase 9 #5b — Take `result.state.sectionsDraft` after set-field, pass to BuilderCanvas; assert DOM shows the updated value ("Updated by pipeline").
- Test: `runtimeVerification.test.tsx` — change value → assert state and resolved props reflect it; canvas rerender test.
- Test: `updatePipeline.test.ts` — "updates sidebar field edits through one validated pipeline".

---

## 4. Components render purely from props

**Requirement:** No hardcoded editable values in JSX; all content comes from props (defaults + overrides from state).

**Code path:**

- Canvas passes to central components: `componentProps = ensureFullComponentProps(centralEntry.defaults, mapBuilderProps(props))`; then `<Component {...componentProps} />`.
- Migrated components (e.g. Hero): receive `props`; render `props.title`, `props.subtitle`, `props.variant`, etc. No `title || 'Hardcoded fallback'` for builder-editable fields.

**Verification:**

- Test: Phase 9 #3 — Canvas with one Hero section and custom title/subtitle/buttonText in props; assert exactly those strings appear in the DOM.
- Code: Hero component uses only `props` and variant switch; no fallback strings for content.
- Phase 9 #6 — Variant from props; `mapBuilderProps` passes variant through.

---

## 5. Update pipeline is unified

**Requirement:** Sidebar and Chat use the same pipeline; no separate code paths that bypass validation.

**Code path:**

- Sidebar: `updateSectionPathProp` → `updateComponentProps(..., 'sidebar')` → `applyBuilderUpdatePipeline`.
- Chat: Message handler for `payload.type === 'builder:apply-change-set'` → `applyBuilderChangeSetPipeline(state, payload.changeSet, { createSection })` → converts change set ops to `BuilderUpdateOperation[]` → `applyBuilderUpdatePipeline`. Same validation (section, schema field, value type), same patch path.
- Both on success: `sectionsDraftRef.current = result.state.sectionsDraft`; `applyMutationState(result.state)`.

**Verification:**

- Test: `updatePipeline.test.ts` — set-field and change set tests; schema validation and field_not_found.
- Phase 5 doc: Sidebar and Chat both go through `applyBuilderUpdatePipeline` / `applyBuilderChangeSetPipeline`.

---

## 6. Ready for chat-driven editing and AI generation

**Requirement:** Chat (and future AI) can send change sets; pipeline validates and applies; canvas and sidebar stay in sync.

**Code path:**

- Chat sends `builder:apply-change-set` with `changeSet.operations` (e.g. setField, replaceImage, updateSection).
- `applyBuilderChangeSetPipeline` maps to builder operations and calls `applyBuilderUpdatePipeline`.
- Same state update and rerender path as sidebar (see #3 and #5).

**Verification:**

- Cms message handler uses `applyBuilderChangeSetPipeline`; no separate chat-only mutation.
- Tests: updatePipeline change set test; runtime verification flow.

---

## Summary

| Criterion | Code path | Verified by |
|-----------|-----------|-------------|
| Canvas through registry | BuilderCanvas: canonical `componentRegistry.ts` helpers (`getComponentRuntimeEntry`, `getCentralRegistryEntry`, `resolveComponentProps`) only | Phase 9 #3, BuilderCanvas.test, legacyDetection |
| Sidebar from schema | Cms: sectionSchemaByKey (library + getAvailableComponents registry fallback), collectSchemaPrimitiveFields, getSchemaFieldControlType | Phase 9 #4, #4b |
| Props update rerender | updateSectionPathProp → updateComponentProps → applyMutationState → store → sectionsDraft → BuilderCanvas | Phase 9 #5, #5b, runtimeVerification, updatePipeline.test |
| Components from props only | ensureFullComponentProps + mapBuilderProps; Hero etc. use only props | Phase 9 #3, component code |
| Unified pipeline | Sidebar: updateComponentProps; Chat: applyBuilderChangeSetPipeline → applyBuilderUpdatePipeline | updatePipeline.test, Phase 5 doc |
| Chat / AI ready | Same pipeline; change set → operations → applyBuilderUpdatePipeline | Cms handler, updatePipeline tests |

**Do not assume the architecture works from file presence alone.** Use the tests and code paths above to confirm runtime behavior.
