# Existing Builder Audit

## Scope

This audit covers the existing inspect builder flow only:

`/project/{project}?tab=inspect`

The supported runtime path is:

`Chat.tsx`
-> `InspectPreview.tsx`
-> `useInspectSelectionLifecycle.ts`
-> `inspectPreviewTargets.ts`
-> embedded `Cms.tsx`
-> `useChatEmbeddedBuilderBridge.ts`
-> `useEmbeddedBuilderBridge.ts`
-> `builderEditingStore.ts`
-> `editingState.ts`

## Current Builder Flow

1. `resources/js/Pages/Chat.tsx` is the workspace shell.
2. In inspect mode, `Chat.tsx` mounts the preview iframe and sidebar iframe wrappers.
3. `resources/js/components/Preview/InspectPreview.tsx` owns the preview iframe, overlay frames, DOM annotation, and preview readiness.
4. `resources/js/components/Preview/useInspectSelectionLifecycle.ts` resolves hover, selection, blank-area fallback, and library placement targets from the preview iframe.
5. `resources/js/components/Preview/inspectPreviewTargets.ts` maps pointer coordinates and DOM nodes inside the preview iframe to builder targets.
6. `resources/js/Pages/Project/Cms.tsx` is the embedded sidebar/editor runtime.
7. `resources/js/builder/cms/useChatEmbeddedBuilderBridge.ts` and `resources/js/builder/cms/useEmbeddedBuilderBridge.ts` are the bridge layer between parent chat and embedded CMS.
8. `resources/js/builder/state/builderEditingStore.ts` and `resources/js/builder/editingState.ts` are the canonical inspect-selection/editing state helpers currently shared across the legacy flow.

## Core Files Required

- `resources/js/Pages/Chat.tsx`
- `resources/js/Pages/Project/Cms.tsx`
- `resources/js/components/Preview/InspectPreview.tsx`
- `resources/js/components/Preview/useInspectSelectionLifecycle.ts`
- `resources/js/components/Preview/inspectPreviewTargets.ts`
- `resources/js/builder/cms/useChatEmbeddedBuilderBridge.ts`
- `resources/js/builder/cms/useEmbeddedBuilderBridge.ts`
- `resources/js/builder/state/builderEditingStore.ts`
- `resources/js/builder/editingState.ts`
- Supporting legacy inspect helpers used by those files:
  - `resources/js/builder/cms/embeddedBuilderBridgeContract.ts`
  - `resources/js/builder/cms/canonicalSelectionPayload.ts`
  - `resources/js/builder/cms/workspaceBuilderSync.ts`
  - `resources/js/builder/cms/chatBuilderStructureMutations.ts`
  - `resources/js/builder/cms/chatEmbeddedBuilderUtils.ts`

## Unused Or Product-Unnecessary V2 Surface

The following V2-specific product entrypoints still exist in `Install/` but are not required for the existing builder:

- `/project/{project}/builder` route in `routes/web.php`
- `/api/projects/{project}/builder-document`
- `/api/projects/{project}/builder-mutations`
- `/api/projects/{project}/builder-ai/suggest`
- `app/Http/Controllers/ProjectBuilderController.php`
- `app/Services/BuilderV2/BuilderDocumentService.php`
- `resources/js/Pages/Builder.tsx`
- `resources/js/builder/app/*`
- `resources/js/builder/canvas/*`
- `resources/js/builder/components/*`
- `resources/js/builder/inspector/*`
- `resources/js/builder/layers/*`
- `resources/js/builder/library/*`
- `resources/js/builder/assets/*`
- `resources/js/builder/state/{builderStore,structureStore,selectionStore,historyStore,uiStore,aiStore,inspectorStore}.ts`
- `resources/js/builder/mutations/*`
- `resources/js/builder/history/*`
- `resources/js/builder/api/builderApi.ts`
- `resources/js/builder/persistence/useBuilderAutosave.ts`

These can be removed or, where large-scale deletion is risky in one pass, isolated so they are not reachable from routes or active product flow.

### Cleanup Applied

- Removed the standalone `/project/{project}/builder` route.
- Removed the V2 JSON API endpoints from `routes/web.php`.
- Deleted `ProjectBuilderController.php` and `BuilderDocumentService.php`.
- Deleted `resources/js/Pages/Builder.tsx`.
- Deleted `resources/js/builder/app/BuilderApp.tsx`, `BuilderLayout.tsx`, and `BuilderProviders.tsx`.
- Left the broader V2 helper tree under `resources/js/builder/*` isolated and unreachable from active product flow to avoid risky broad deletions in the same pass.

## Duplicate Logic / Cleanup Opportunities

- `Chat.tsx` still contains explicit V2 retirement TODO comments that are no longer actionable for the current direction.
- `ProjectBuilderController::show()` currently exists only to redirect back to inspect mode. That route/controller pair is unnecessary if the standalone builder is retired.
- `InspectPreview.tsx` and `useInspectSelectionLifecycle.ts` both contain iframe access patterns and fallback lookups that need to stay consistent; duplicate selection-resolution behavior is a stability risk.
- `Cms.tsx` is very large and mixes sidebar/editor concerns with unrelated builder AI, design-system, theme, and template-management code. Full decomposition is out of scope for one pass, but inspect-specific cleanup should reduce dead branches and keep builder responsibilities clearer.
- `builderEditingStore.ts` and bridge hooks must remain the single source of truth for selection/editing payloads; any parallel selection state in UI-local state is a regression risk.
- Debug logging remains in the inspect path (`console.warn`, `console.debug`) and should be reduced to guarded diagnostics only where still valuable.

## Dead Code / Isolation Candidates

- V2 page mount and route surface can be removed from product flow.
- V2 backend document endpoints can be removed if no existing builder path consumes them.
- V2 docs/tests under `resources/js/builder/__tests__` and `resources/js/builder/docs` are unrelated to the active inspect builder and should not influence product behavior.
- Standalone V2 UI files under `resources/js/builder/app`, `canvas`, `layers`, `library`, `assets`, `ai`, `mutations`, and `types` are not used by the inspect route.

## Risky Areas To Preserve

- Preview iframe selection must keep deterministic blank-area fallback and stable hover/selection separation.
- Sidebar/preview postMessage protocol must remain backward compatible between `Chat.tsx` and embedded `Cms.tsx`.
- `builderEditingStore.ts` selection clearing and remapping rules must remain correct after add/delete/move flows.
- Canvas width and overlay measurement logic in `InspectPreview.tsx` must remain stable; careless cleanup can cause canvas collapse or overlay drift.
- `Cms.tsx` still hosts multiple builder mutation paths; cleanup must not break add/delete/update flows or theme persistence.

## Planned Cleanup Sequence

1. Remove standalone V2 route/product entrypoints from `Install/`.
2. Remove or isolate direct imports that only supported the standalone builder.
3. Clean inspect-specific logic in the core legacy flow files listed above.
4. Add or update focused tests for selection, bridge stability, and mutation reliability.
5. Verify only the existing inspect flow with typecheck and production build.
