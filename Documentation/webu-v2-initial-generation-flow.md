# Webu v2 Initial Generation Flow

## Goal

Replace the old startup path:

`prompt -> draft builder state -> inspect opens immediately`

with the code-first path now used by the v2 rollout:

`prompt -> AI planning -> CMS/site scaffold -> workspace files -> preview readiness -> chat workspace -> optional inspect`

## Runtime Changes

### 1. Project creation now lands in Chat, not inspect

The initial create entrypoints now redirect to the chat workspace without `tab=inspect`:

- `Install/app/Http/Controllers/GenerateWebsiteController.php`
- `Install/app/Http/Controllers/ProjectController.php`
- `Install/app/Http/Controllers/ProjectCmsController.php`

This preserves the current product shell but removes the old assumption that inspect mode is safe before code generation finishes.

### 2. Generation run phases are explicit

`ProjectGenerationRun` now supports a richer phase model for initial code-first orchestration:

- `queued`
- `planning`
- `scaffolding`
- `writing_files`
- `building_preview`
- `ready`
- `failed`

Legacy statuses (`generating`, `finalizing`, `completed`) are still recognized so older runs remain readable.

### 3. Workspace manifest is part of initial readiness

`Install/app/Services/ProjectWorkspace/ProjectWorkspaceService.php` now writes and updates `.webu/workspace-manifest.json` during initial generation.

The manifest is now used to track:

- generated pages
- initial file ownership
- component provenance
- active generation run id while the run is still blocking
- preview phase and preview readiness

`initializeProjectCodebase()` now writes a baseline manifest from the generated projection instead of leaving the initial run without canonical workspace metadata.

### 4. Ready means more than “run completed”

Both:

- `Install/app/Http/Controllers/ChatController.php`
- `Install/app/Http/Controllers/ProjectGenerationStatusController.php`

now expose generation payloads with readiness metadata:

- `ready_for_builder`
- `workspace_manifest_exists`
- `workspace_preview_ready`
- `workspace_preview_phase`
- `active_generation_run_id`

The UI no longer treats a terminal run status by itself as enough to unlock preview/inspect.

## Frontend Behavior

### Generation overlay phases

`Install/resources/js/builder/state/builderGenerationState.ts` now renders the initial startup phases expected by v2:

- Planning
- Generating site structure
- Writing files
- Building preview
- Ready

### Preview freeze and inspect lock

`Install/resources/js/Pages/Chat.tsx` now keeps the full-screen generation state active until both conditions are true:

1. backend says `ready_for_builder === true`
2. chat props already contain a valid preview URL

This prevents the old gap where the poll could see `completed` and briefly reveal a half-ready workspace before the Inertia reload finished.

### No auto-open inspect

`Install/resources/js/builder/chat/useBuilderWorkspace.ts` now accepts `canOpenInspectMode`.

That blocks:

- toolbar inspect switches
- visual builder toggle
- stale `?tab=inspect` reloads

until the project is actually ready. When blocked, the page stays in preview/chat mode.

## Reconnect / Reload Rules

Reload during generation now works like this:

1. `ChatController` returns the latest `project.generation` payload.
2. `Chat.tsx` resumes polling `project.generation.status_url`.
3. Polling continues until `ready_for_builder` becomes true, not merely until `is_active` flips false.
4. After readiness is confirmed, `router.reload()` fetches the real preview URL, generated pages, and builder library data.

This avoids spawning a second draft run and avoids resetting the UI into an empty builder shell.

## Duplicate Run Protection

Initial create endpoints still enforce a single active generation per user, but the new flow also fixes the more visible duplicate-draft behavior by:

- not sending the initial prompt into the legacy chat-builder startup path when `project.generation` exists
- reconnecting to the existing generation payload on refresh
- removing the automatic inspect redirect that used to look like a fresh builder draft

## Files Touched

Backend:

- `Install/app/Models/ProjectGenerationRun.php`
- `Install/app/Services/ProjectGenerationRunner.php`
- `Install/app/Services/AiWebsiteGeneration/GenerateWebsiteProjectService.php`
- `Install/app/Services/ProjectWorkspace/ProjectWorkspaceService.php`
- `Install/app/Http/Controllers/GenerateWebsiteController.php`
- `Install/app/Http/Controllers/ProjectController.php`
- `Install/app/Http/Controllers/ProjectCmsController.php`
- `Install/app/Http/Controllers/ChatController.php`
- `Install/app/Http/Controllers/ProjectGenerationStatusController.php`

Frontend:

- `Install/resources/js/Pages/Chat.tsx`
- `Install/resources/js/builder/chat/useBuilderWorkspace.ts`
- `Install/resources/js/builder/state/builderGenerationState.ts`

Tests:

- `Install/tests/Feature/Cms/GenerateWebsiteControllerTest.php`
- `Install/tests/Feature/Project/ManualProjectBuilderTest.php`
- `Install/tests/Feature/Project/ProjectWorkspaceCodeGenerationTest.php`
- `Install/resources/js/builder/state/__tests__/builderGenerationState.test.ts`
- `Install/tests/e2e/flows/generate-website.spec.ts`
- `Install/tests/e2e/flows/webu-v2-rollout.spec.ts`

## Verification

Verified in this task with:

- `npm run typecheck`
- `npm run test:run -- resources/js/builder/state/__tests__/builderGenerationState.test.ts`
- `php artisan test tests/Feature/Cms/GenerateWebsiteControllerTest.php tests/Feature/Project/ManualProjectBuilderTest.php tests/Feature/Project/ProjectWorkspaceCodeGenerationTest.php`

Playwright specs were updated for the new route/inspect expectations, but not executed in this task.
