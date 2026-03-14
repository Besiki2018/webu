# Webu v2 AI Editing Lanes

## Goal

Webu chat should no longer treat every AI edit as a safe prop patch.

This pass keeps the existing builder-safe patch flow for low-risk edits, and adds two higher-order execution lanes on top of the new workspace-backed runtime:

1. `prop_patch`
2. `graph-level edit`
3. `workspace file edit`

The routing and execution logic now lives in:

- `Install/resources/js/builder/ai/editIntentRouter.ts`
- `Install/resources/js/builder/ai/graphEditExecutor.ts`
- `Install/resources/js/builder/ai/workspaceEditExecutor.ts`

The chat entry point in `Install/resources/js/Pages/Chat.tsx` now routes requests through those lanes before falling back to the legacy unified agent prop-patch flow.

## Lane Model

### 1. Builder-safe prop patch

Used for:

- text updates
- image swap
- style tweak
- variant switch
- selected-element scoped edits

Execution path:

`chat request -> routeAiEditIntent() -> prop_patch -> existing runUnifiedEdit() / builder-safe change set path`

This preserves the current schema-driven safety guarantees and the existing `updatePipeline.ts` behavior.

### 2. Graph-level edit

Used for:

- add/remove/reorder section requests
- larger structure changes
- page-level mutations that conceptually change the project graph

Execution path:

`chat request -> routeAiEditIntent() -> graphEditExecutor -> revision guard -> backend execution -> builder/preview sync`

Repo-specific behavior:

- `structure_change` currently executes through the existing unified agent (`runUnifiedEdit`) because it already returns a change set that the embedded builder bridge can replay safely.
- `page_change` currently normalizes workspace-backed page creation/duplication responses from `POST /panel/projects/{project}/ai-project-edit` into the graph lane result model.

This means page creation is already treated as a code-backed project change, even though the backend implementation still materializes it through workspace file edits.

### 3. Workspace file edit

Used for:

- custom component file creation
- route file updates
- shared utility changes
- scaffold adjustments
- explicit regenerate-from-site requests

Execution path:

`chat request -> routeAiEditIntent() -> workspaceEditExecutor -> revision guard -> ai-project-edit or workspace/regenerate -> workspace refresh`

Repo-specific behavior:

- `file_change` uses `POST /panel/projects/{project}/ai-project-edit`
- `regeneration_request` uses `POST /panel/projects/{project}/workspace/regenerate`

## Intent Routing

`editIntentRouter.ts` classifies requests into:

- `prop_patch`
- `structure_change`
- `page_change`
- `file_change`
- `regeneration_request`

Routing signals include:

- selected builder element scope
- explicit section/page/file keywords
- code-mode context
- selected real workspace file

This replaces the older coarse `shouldPreferProjectEdit()` decision in chat for the main AI editing path.

## Conflict-safe Revision Guard

Both executors now compare a captured edit context with the latest live context before execution.

Current comparison sources:

- workspace manifest `updatedAt`
- workspace manifest `activeGenerationRunId`
- embedded builder `stateVersion`
- embedded builder `revisionVersion`
- active page identity

If any of those move ahead, the executor returns a `conflicted` result instead of silently applying a stale edit.

This is currently enforced in:

- `detectAiLayeredEditConflict()` in `graphEditExecutor.ts`
- reused by `workspaceEditExecutor.ts`

## Assistant Messaging

Chat responses now differentiate what happened:

- changed page structure
- changed pages in the project
- modified workspace files
- regenerated workspace code
- could not safely apply

The final user-facing message is still built in `Install/resources/js/Pages/Chat.tsx`, but the lane executors now normalize the underlying result shape first, so chat no longer has to guess whether the AI changed props, structure, or files.

## Current Limitations

- `page_change` is normalized as a graph-lane result, but the backend implementation still materializes those edits through the workspace project-edit service.
- Conflict checks happen before execution starts; they do not yet provide a mid-flight server-side compare-and-swap token.
- `prop_patch` still uses the existing unified-agent/backend change-set flow; it is intentionally preserved rather than reimplemented.

## Verification

Run:

```bash
cd Install
npm run typecheck
npm run test:run -- \
  resources/js/builder/ai/__tests__/editIntentRouter.test.ts \
  resources/js/builder/ai/__tests__/graphEditExecutor.test.ts \
  resources/js/builder/ai/__tests__/workspaceEditExecutor.test.ts
```
