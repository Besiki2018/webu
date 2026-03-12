# Phase 5 — Update Pipeline Verification

## Unified update function

Both **Sidebar** and **Chat** update component props through the same pipeline. The canonical entry points are:

- **`applyBuilderUpdatePipeline(initialState, operations, options)`** — applies one or more builder operations (set-field, merge-props, insert-section, delete-section, reorder-section).
- **`updateComponentProps(initialState, componentId, payload, source)`** — single-prop or patch update: builds a set-field or merge-props op and calls `applyBuilderUpdatePipeline`. Use for “update this component’s props” from Sidebar or any caller.
- **`applyBuilderChangeSetPipeline(initialState, changeSet, options)`** — converts chat/AI change set (e.g. setField, replaceImage, updateSection) into pipeline operations and calls `applyBuilderUpdatePipeline`. Chat edits go through this.

**Location:** `Install/resources/js/builder/state/updatePipeline.ts`

---

## What the pipeline does

For prop updates (set-field, merge-props), the pipeline:

1. **Validates component existence** — `resolveSectionTarget(sectionsDraft, sectionLocalId, nestedSectionPath)`; returns `section_not_found` if the section does not exist.
2. **Validates field key against schema** — `resolveSchemaField(componentType, path)` using `getComponentSchema(componentType)`; returns `schema_not_found` or `field_not_found` if the path is not in the component schema.
3. **Validates value type** — `validateValueAgainstField(field, relativePath, value)`; returns `invalid_value_type` if the value is incompatible with the field type.
4. **Patches props** — `setValueAtPath(target.props, path, value)` (or unset for unset-field); then `target.writeProps(sections, nextProps)` to write back into the section tree.
5. **Returns new state** — `BuilderUpdateStateSnapshot` with updated `sectionsDraft` and refreshed `selectedBuilderTarget` (so selection stays in sync).
6. **Rerender / sync** — **caller’s responsibility:** the CMS (or any consumer) applies the returned state (e.g. `applyMutationState(result.state)`), updates refs, and schedules auto-save / preview refresh. So “trigger rerender” and “sync builder state” are done by the caller after a successful pipeline run.

---

## Sidebar uses the pipeline

- **Flow:** User edits a field in the sidebar → `updateSectionPathProp(localId, path, value)` is called → it calls **`updateComponentProps(state, localId, { path, value }, 'sidebar')`** → pipeline validates and returns new state → CMS updates `sectionsDraftRef`, calls `applyMutationState(result.state)`, and schedules save + preview refresh.
- **No bypass:** Sidebar does not mutate section props directly; it always goes through `updateComponentProps` (which uses `applyBuilderUpdatePipeline`).

**Code reference (Cms.tsx):** `updateSectionPathProp` → `updateComponentProps(..., localId, { path, value }, 'sidebar')` then apply state and schedule refresh.

---

## Chat uses the pipeline

- **Flow:** Chat sends a message to the builder iframe with `builder:apply-change-set` and a change set (e.g. `{ operations: [{ op: 'setField', sectionId, path, value }] }`). The CMS message handler calls **`applyBuilderChangeSetPipeline(state, payload.changeSet, { createSection })`**, which converts chat ops to builder operations and calls **`applyBuilderUpdatePipeline`**. Same validation (section, schema field, value type), same patch path.
- **No bypass:** Chat does not send raw patches that skip validation; all edits go through `applyBuilderChangeSetPipeline` → `applyBuilderUpdatePipeline`.

**Code reference (Cms.tsx):** Message handler for `payload.type === 'builder:apply-change-set'` → `applyBuilderChangeSetPipeline({ sectionsDraft, selectedSectionLocalId, selectedBuilderTarget }, { operations: payload.changeSet.operations }, { createSection })`; on success, applies `result.state` and notifies parent.

---

## Example: updateComponentProps

```ts
const result = updateComponentProps(
    { sectionsDraft, selectedSectionLocalId, selectedBuilderTarget },
    componentId,
    { path: 'title', value: 'New title' },
    'sidebar'
);
if (result.ok && result.changed) {
    sectionsDraftRef.current = result.state.sectionsDraft;
    applyMutationState(result.state);
    scheduleAutoSave();
    schedulePreviewRefresh();
}
```

Payload can be:

- **Single field:** `{ path: 'title', value: 'New title' }` or `{ path: ['advanced', 'padding_top'], value: '20px' }`
- **Patch:** `{ patch: { title: 'New title', buttonText: 'Click' } }`

---

## Summary

| Requirement              | Status |
|--------------------------|--------|
| Single unified update path | Yes — `applyBuilderUpdatePipeline` (and helpers `updateComponentProps`, `applyBuilderChangeSetPipeline`) |
| Validate component existence | Yes — `resolveSectionTarget` → `section_not_found` |
| Validate field against schema | Yes — `resolveSchemaField` → `field_not_found` / `schema_not_found` |
| Validate value type      | Yes — `validateValueAgainstField` → `invalid_value_type` |
| Patch props              | Yes — `setValueAtPath` / merge, then `writeProps` |
| Trigger rerender / sync state | Yes — caller applies `result.state` and schedules refresh |
| Sidebar uses pipeline    | Yes — `updateSectionPathProp` → `updateComponentProps` |
| Chat uses pipeline       | Yes — `builder:apply-change-set` → `applyBuilderChangeSetPipeline` |

No refactor was required for bypass removal; both Sidebar and Chat already used the pipeline. Sidebar was refactored to call **`updateComponentProps`** explicitly so there is a single named entry point for “update component props” that both documentation and future callers can use.
