# Final Deletion / Isolation Map

This pass prioritizes production safety. Files were only removed previously when the import graph was already proven safe. In this pass, the remaining builder tree is classified so future deletions can be performed without risking the live runtime.

## Active Runtime And Must Keep

- `resources/js/Pages/Chat.tsx`
- `resources/js/Pages/Project/Cms.tsx`
- `resources/js/components/Preview/InspectPreview.tsx`
- `resources/js/builder/componentRegistry.ts`
- `resources/js/builder/state/updatePipeline.ts`
- `resources/js/builder/state/builderEditingStore.ts`
- `resources/js/builder/domMapper/*`
- `resources/js/builder/cms/*`
- `resources/js/builder/inspector/*`
- `resources/js/lib/builderBridge.ts`
- `resources/js/builder/workspace/*`
- `resources/js/builder/visual/*`
- `resources/js/builder/schema/*`

Reason:
- these files are on the active chat/inspect runtime import path
- they own preview rendering, mutation validation, bridge sync, targeting, inspector state, or live draft rendering

## Support-Only But Still Needed

- `resources/js/builder/ai/*`
  - Used by live product flows in `Chat.tsx` and `Cms.tsx` for AI planning, generation, optimization, and targeted builder assistance.
- `resources/js/builder/designSystem/*`
  - Used by `Cms.tsx` and preview binding for live theme/design-system editing.
- `resources/js/builder/layout/*`
  - `HeaderFooterLayoutForm.tsx` is mounted from `Cms.tsx`.
- `resources/js/builder/model/*`
  - `pageModel.ts` is imported by `Cms.tsx` and `builderEditingStore.ts`.
- `resources/js/builder/store/*`
  - `builderStore.ts` is still used by `Cms.tsx`.

These are not deletion candidates until the runtime no longer imports them.

## Transitional Compatibility

- `resources/js/builder/state/useBuilderCanvasState.ts`
  - Still consumed by `Cms.tsx`, but increasingly derives view state from canonical target/mode state instead of owning separate truth.
- `resources/js/builder/cms/CmsInspectorPanel.tsx`
  - Thin wrapper retained to keep `Cms.tsx` orchestration smaller.
- `resources/js/builder/cms/CmsMutationDispatcher.ts`
  - Thin wrapper retained to keep inspector mutations reviewable.
- `resources/js/builder/cms/CmsSchemaResolver.ts`
  - Thin wrapper retained around selected inspector-state resolution.
- `resources/js/builder/preview/*`
  - New extracted helper layer under `InspectPreview.tsx`; intended to isolate responsibilities without introducing a second preview architecture.

These should remain until a later cleanup can either inline them back or consolidate them further with confidence.

## Safe To Isolate From Production Review

- `resources/js/builder/docs/*`
  - Documentation only; not imported by runtime.
- `resources/js/builder/DELIVERABLE.md`
  - Historical doc artifact.
- `resources/js/builder/README.md`
  - Human-facing architecture guide, not runtime.
- `resources/js/builder/ARCHITECTURE.md`
  - Human-facing architecture guide, not runtime.
- `resources/js/builder/**/__tests__/*`
  - Verification only.
- `resources/js/builder/chat/ARCHITECTURE_CHECKLIST.md`
  - Checklist only.

These files should not affect release behavior and can be excluded from runtime-focused review.

## Safe To Delete Later

- `resources/js/builder/commands/*`
  - No active runtime imports from `Chat.tsx`, `Cms.tsx`, `InspectPreview.tsx`, `componentRegistry.ts`, `updatePipeline.ts`, `builderEditingStore.ts`, or `builder/cms/*`.
  - Delete only in a dedicated cleanup commit with repo-wide confirmation.
- `resources/js/builder/templates/*`
  - Not imported by the active runtime boundary.
  - Keep only if external tooling or future support flows still depend on it.
- `resources/js/builder/core/*`
  - Not imported by the active chat/inspect runtime boundary.
  - May still be useful as reference material; isolate before deletion.

## Removed In Earlier Cleanup Passes

Already removed before this pass:

- retired standalone/V2 canvas surfaces
- old `builder/registry/*` runtime ownership
- old `centralComponentRegistry.ts` runtime ownership
- old V2 state slices and mutation handler branches
- old standalone autosave/api surfaces

Those removals remain valid because the active runtime now resolves through:

- `resources/js/builder/componentRegistry.ts`
- `resources/js/builder/state/updatePipeline.ts`
- `resources/js/builder/state/builderEditingStore.ts`

## This Pass

No additional runtime files were deleted in this pass.

Reason:
- the current production pass is focused on decomposition, boundary clarity, and runtime-safe isolation
- remaining delete candidates should be removed only after a dedicated import-audit commit, not during final stabilization
