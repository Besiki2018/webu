# Webu v2 Workspace File Control

## Objective

Webu code mode now treats the generated workspace as a first-class product surface instead of a secondary inspection panel.

The canonical flow is:

`AI or user intent -> guarded workspace file path -> manifest/op-log update -> preview refresh`

This sits alongside the visual-builder adapter introduced in v2:

`canvas edit -> builder mutation -> graph patch -> workspace regenerate/sync -> preview refresh`

## What changed

### Frontend canonical workspace layer

New frontend modules:

- `Install/resources/js/builder/workspace/workspaceFileClient.ts`
- `Install/resources/js/builder/workspace/workspaceFileState.ts`
- `Install/resources/js/builder/codegen/aiWorkspaceOps.ts`

`workspaceFileClient.ts` is now the single frontend entrypoint for workspace file operations:

- list files
- read file
- write file
- create file
- delete file
- rename file
- apply typed AI workspace operations

`workspaceFileState.ts` derives code-mode metadata from:

- real workspace files returned by `/panel/projects/{project}/workspace/files`
- `.webu/workspace-manifest.json`
- `.webu/workspace-operation-log.json`

This produces canonical per-file state for the UI:

- provenance
- dirty/edit state
- generated-vs-user ownership
- locked/template-owned status
- latest diff metadata

### Typed AI workspace ops

`aiWorkspaceOps.ts` defines the v2 contract for AI-side file operations:

- `create_file`
- `update_file`
- `delete_file`
- `move_file`
- `scaffold_project`
- `apply_patch_set`

The frontend client can execute these operations through one code path, rather than ad hoc axios calls spread across chat/code UI.

## Guarded backend write path

Backend writes are now consolidated through `ProjectWorkspaceService` mutation methods:

- `writeFile(...)`
- `deleteFile(...)`
- `moveFile(...)`
- `recordWorkspaceSyncOperations(...)`

These methods now:

- normalize and validate paths
- block writes to protected workspace files such as `src/main.tsx` and `src/index.css`
- keep `.webu/workspace-manifest.json` and `.webu/workspace-operation-log.json` writable only for internal/system-style flows
- update manifest ownership metadata
- append structured operation-log entries
- mark preview state as rebuilding

Controller support lives in:

- `Install/app/Http/Controllers/ProjectWorkspaceController.php`

Routes now include canonical move support:

- `POST /panel/projects/{project}/workspace/file/move`

## Provenance and operation log

Workspace provenance now tracks:

- who originally generated the file
- whether the file is AI-generated, user-edited, or mixed
- latest editor
- locked/template-owned state
- latest operation id and operation kind

The structured log file is:

- `.webu/workspace-operation-log.json`

It records:

- AI generation writes
- AI tool/file-editor writes
- manual code-editor writes
- visual-builder derived workspace syncs

Visual-builder sync logging is recorded after workspace regeneration, using the dirty paths produced by the workspace-backed builder adapter. This gives the log a concrete record of which real files were touched by builder-side edits.

## Code mode UX

Code mode now prioritizes real workspace files and separates them from derived CMS preview artifacts.

Updated UI surfaces:

- `Install/resources/js/components/Code/FileTree.tsx`
- `Install/resources/js/components/Code/CodeEditor.tsx`

Behavior:

- Workspace files render first and carry provenance/diff badges.
- Derived preview files remain read-only and are clearly labeled as non-canonical.
- File selection metadata is now workspace-backed instead of builder-only.
- Save operations go through `workspaceFileClient`, not direct component-local axios writes.

This preserves the current product feel while making code mode operate on the same canonical workspace state that AI uses.

## Visual builder integration

The existing visual builder remains schema/registry-driven in the UI, but persistence is now workspace-aware.

Relevant adapter:

- `Install/resources/js/builder/codegen/workspaceBackedBuilderAdapter.ts`

Current persistence path:

- builder mutations mark dirty workspace paths in manifest state
- workspace regenerate runs with visual-builder context
- dirty paths are recorded into the operation log
- manifest is reloaded and persisted with updated ownership metadata
- preview refresh is triggered

This keeps the structure panel, inspector, and preview bridge intact while moving persistence closer to real workspace ownership.

## Current limitations

- Visual-builder sync logging is path-level, not AST-level. It records which canonical files were touched, but does not yet store semantic diffs for each builder mutation.
- Protected-file enforcement is currently focused on the code-edit surface and metadata files. More granular policy can be added later if template ownership needs stricter locks.
- Code mode now reads manifest/op-log metadata on file load/list, so the metadata fetch pattern is intentionally broader than the old single-request file tree.

## Verification

Validated with:

- `npm run typecheck`
- `npm run test:run -- resources/js/components/Code/__tests__/FileTree.test.tsx resources/js/components/Code/__tests__/CodeEditor.test.tsx resources/js/builder/codegen/__tests__/workspaceBackedBuilderAdapter.test.ts resources/js/builder/workspace/__tests__/workspaceFileState.test.ts resources/js/builder/workspace/__tests__/workspaceFileClient.test.ts`
- `php artisan test tests/Unit/PathRulesTest.php`
