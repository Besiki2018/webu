# Mutation Pipeline Audit

Legacy inspect builder route: `/project/{project}?tab=inspect`

Goal of this audit: identify every active structure mutation in the legacy builder runtime and classify whether it already uses `resources/js/builder/state/updatePipeline.ts` or still mutates `sectionsDraft` directly.

## Canonical pipeline status before migration

`updatePipeline.ts` already owns:

- schema-aware prop edits (`set-field`, `unset-field`, `merge-props`)
- top-level insert (`insert-section`)
- top-level delete (`delete-section`)
- top-level reorder by index (`reorder-section`)

It does **not** yet own:

- duplicate
- nested add/remove/reorder
- paste
- drag reorder entrypoints
- embedded bridge move entrypoint

## Active structure mutations

| Mutation | Current entrypoint | Uses `updatePipeline.ts` now | Direct `sectionsDraft` mutation | Selection / validation notes |
| --- | --- | --- | --- | --- |
| Insert component | `useCmsStructureMutationHandlers.ts` `addSectionByKey` | Yes | No | Registry + project-type validation happens before pipeline; caller currently selects inserted section manually after pipeline result. |
| Delete component | `useCmsStructureMutationHandlers.ts` `handleRemoveSection` | Yes | No | Pipeline deletes section, but sidebar reset / nested-selection cleanup still happens outside pipeline. |
| Duplicate component | `useCmsStructureMutationHandlers.ts` `handleDuplicateSection` | No | Yes | Uses `duplicateSection(...)`; selection of duplicate is rebuilt manually in handler. No canonical validation/idempotency path. |
| Add nested child to top-level layout section | `useCmsStructureMutationHandlers.ts` `handleAddSectionInside` | No | Yes | Parses `propsText`, appends to `props.sections`, writes `propsText` manually. No canonical validation or reconcile signal. |
| Add nested child to nested layout path | `Cms.tsx` `handleAddSectionInsideAtPath` via `updateNestedSectionPropsObject(...)` | No | Yes | Writes nested `props.sections` directly through local setter path. No canonical structure validation or selection repair. |
| Remove nested child | `useCmsStructureMutationHandlers.ts` `handleRemoveNestedSection` | No | Yes | Removes nested item by path inside parsed props. Nested selection repair is not centralized. |
| Move nested child up/down | `useCmsStructureMutationHandlers.ts` `handleMoveNestedSection` | No | Yes | Reorders nested `props.sections` by path. No canonical idempotency or repair path. |
| Paste component | `useCmsStructureMutationHandlers.ts` `handlePasteSection` | No | Yes | Clipboard payload validation happens in handler, then section is inserted directly and selected manually. |
| Move component up/down | `useCmsStructureMutationHandlers.ts` `handleMoveSection` | No | Yes | Uses `moveSection(...)` directly. Preserves selection only because local id stays stable. |
| Drag reorder top-level components | `useCmsStructureMutationHandlers.ts` `handleBuilderDragEnd` | Partially | Yes | Library insert path delegates to `addSectionByKey`, but existing-section reorder still uses `moveSection(...)` directly. |
| Embedded builder add section | `useCmsEmbeddedBuilderMutationHandlers.ts` `handleEmbeddedBuilderAddSection` | Indirectly yes | No for top-level insert | Delegates to `addSectionByKey` or `handleAddSectionInside`; nested add still inherits direct mutation path today. |
| Embedded builder remove section | `useCmsEmbeddedBuilderMutationHandlers.ts` `handleEmbeddedBuilderRemoveSection` | Indirectly yes | No | Delegates to `handleRemoveSection`. |
| Embedded builder move section | `useCmsEmbeddedBuilderMutationHandlers.ts` `handleEmbeddedBuilderMoveSection` | No | Yes | Uses `moveSection(...)` directly and bypasses canonical selection/preview reconciliation. |

## Direct-mutation hotspots

### `resources/js/builder/cms/useCmsStructureMutationHandlers.ts`

Direct structure writes still exist in:

- `handleDuplicateSection`
- `handleAddSectionInside`
- `handleRemoveNestedSection`
- `handleMoveNestedSection`
- `handlePasteSection`
- `handleMoveSection`
- drag reorder branch inside `handleBuilderDragEnd`

Helpers currently used for those direct writes:

- `duplicateSection(...)`
- `moveSection(...)`
- `getSectionsArrayAtPath(...)`
- `replaceNestedSectionsAtPath(...)`
- local `parseRecordJson(...)`

### `resources/js/Pages/Project/Cms.tsx`

Direct nested structure write exists in:

- `handleAddSectionInsideAtPath`
- local helper `updateNestedSectionPropsObject(...)` when used for nested insert

### `resources/js/builder/cms/useCmsEmbeddedBuilderMutationHandlers.ts`

Direct top-level reorder write exists in:

- `handleEmbeddedBuilderMoveSection`

## Migration target

After migration, all active structure mutations should resolve to one canonical call path:

`entrypoint -> applyBuilderUpdatePipeline(...) -> applyMutationState(...) -> preview reconcile`

Planned structure operations to cover in the canonical pipeline:

- `insert-section`
- `delete-section`
- `duplicate-section`
- `reorder-section`
- `insert-nested-section`
- `delete-nested-section`
- `reorder-nested-section`

`paste component` will be normalized onto `insert-section` with validated clipboard payload instead of owning a separate mutation path.
