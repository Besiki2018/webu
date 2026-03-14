# Webu v2 Visual Builder Adapter

## Purpose

Task Pack 4 turns the existing visual builder into a workspace-aware editor without replacing the current CMS builder UX.

The runtime now preserves this conceptual path for builder edits:

`canvas edit -> builder mutation -> project graph patch -> workspace manifest/file ownership update -> workspace regenerate -> preview refresh`

The existing schema-driven inspector, registry metadata, iframe bridge, structure panel, and selection model remain intact.

## Main integration points

### Adapter layer

`Install/resources/js/builder/codegen/workspaceBackedBuilderAdapter.ts`

This file is the bridge between the current builder state and the new code-first domain model.

It now:

- derives a generated page/project graph from live `SectionDraft[]`
- translates builder `BuilderUpdateOperation[]` into `ProjectGraphPatchInstruction[]`
- resolves affected workspace files from projection metadata
- updates `.webu/workspace-manifest.json` ownership metadata
- tracks `lastEditor`, `sectionLocalIds`, `componentKeys`, dirty file paths, and revision cursor state
- persists workspace-backed builder state after draft save by calling:
  - `POST /panel/projects/{project}/workspace/regenerate`
  - `POST /panel/projects/{project}/workspace/file` for `.webu/workspace-manifest.json`

### CMS wiring

`Install/resources/js/Pages/Project/Cms.tsx`

The CMS page now uses the adapter in four places:

1. `useWorkspaceBackedBuilderAdapter(...)` initializes page-scoped workspace state.
2. `applyBuilderMutationPipeline(...)` records inspector/sidebar mutations as graph patches.
3. `useCmsStructureMutationHandlers(...)` and embedded builder mutations report add/remove/reorder operations to the adapter.
4. `performDraftRevisionSave(...)` persists workspace-backed state after the CMS draft revision succeeds.

Additionally, `handleSaveBuilderGlobalLayout(...)` marks layout-owned workspace files dirty so header/footer variant overrides and related layout edits are represented in the manifest.

## Supported edit scope

The first implementation covers:

- text updates
- image/style/variant prop updates that flow through builder field mutations
- section reorder
- section add/remove/duplicate
- header/footer edits that map to layout-owned files
- embedded builder change-set mutations through the existing iframe bridge

Nested section operations are still marked as unsupported graph patch instructions. They continue to work in the current builder runtime, but they are not yet translated into precise workspace graph patches.

## Mixed-mode safety

The adapter keeps a page-scoped revision cursor and a persisted content signature.

This provides two protections:

- builder operations are recorded with editor ownership (`ai` vs `visual_builder`)
- direct draft mutations that bypass explicit operation hooks still trigger fallback dirty-file detection on save

That means chat edits followed by visual edits do not silently drop the workspace sync path just because a mutation came from a non-pipeline state update.

## Workspace manifest access

`Install/app/Services/WebuCodex/PathRules.php` now explicitly allows:

- `.webu/workspace-manifest.json`

`Install/app/Http/Controllers/ProjectWorkspaceController.php` error messages were updated accordingly so manifest read/write failures are easier to diagnose.

## Tests

Added:

- `Install/resources/js/builder/codegen/__tests__/workspaceBackedBuilderAdapter.test.ts`

The test coverage verifies:

- text edit -> graph patch -> page/section dirty path mapping
- reorder -> move patch without unnecessary layout dirtying
- header edit -> layout file ownership dirtying

Also updated:

- `Install/tests/Unit/PathRulesTest.php`

## Current limitations

- The adapter persists manifest metadata and triggers workspace regeneration, but it does not yet patch source files directly from visual edits.
- Nested section graph patch round-trip is still deferred.
- Workspace regeneration is still CMS-projection based, so visual builder persistence currently synchronizes through draft save first and code projection second.

This keeps the current product behavior stable while moving the visual builder onto the canonical workspace-backed model.
