# Webu v2 Rollout Checklist

## Scope

Webu v2 rollout is controlled by four shared flags:

- `WEBU_V2_CODE_FIRST_INITIAL_GENERATION`
- `WEBU_V2_WORKSPACE_BACKED_VISUAL_BUILDER`
- `WEBU_V2_IMAGE_TO_SITE_IMPORT`
- `WEBU_V2_ADVANCED_AI_WORKSPACE_EDITS`

These flags are exposed to the frontend through Inertia shared props and enforced at the `GenerateWebsiteController` entrypoint for code-first initial generation.

## What Changes When Flags Are On

- `code_first_initial_generation`
  - `/create` prefers `projects.generate-website`
  - project creation enters the generation-run flow and keeps preview blocked until ready
- `workspace_backed_visual_builder`
  - `resources/js/Pages/Project/Cms.tsx` persists builder edits through the workspace-backed adapter
- `image_to_site_import`
  - design import uses the new image-import lane and outputs project-graph/workspace-backed plans
- `advanced_ai_workspace_edits`
  - `resources/js/Pages/Chat.tsx` enables layered AI edit routing (`prop_patch`, `structure_change`, `page_change`, `file_change`, `regeneration_request`)

## Rollback Behavior

- If `WEBU_V2_CODE_FIRST_INITIAL_GENERATION=false`
  - `/create` falls back to legacy `/projects` creation
  - direct calls to `projects.generate-website` are blocked
- If `WEBU_V2_WORKSPACE_BACKED_VISUAL_BUILDER=false`
  - CMS keeps the current visual-builder flow
  - workspace adapter stays side-effect free during draft saves
- If `WEBU_V2_IMAGE_TO_SITE_IMPORT=false`
  - CMS design import falls back to `generateLayoutFromDesign()`
- If `WEBU_V2_ADVANCED_AI_WORKSPACE_EDITS=false`
  - chat keeps safe prop patches
  - explicit code/file requests fall back to legacy `ai-project-edit`

## Migrations

- No database migration is required for this rollout.
- No new storage migration is required.
- Workspace metadata files are runtime-generated under `storage/workspaces/{project_id}/.webu/`.

## Config And Env

Set these env vars in the target environment:

```dotenv
WEBU_V2_CODE_FIRST_INITIAL_GENERATION=true
WEBU_V2_WORKSPACE_BACKED_VISUAL_BUILDER=true
WEBU_V2_IMAGE_TO_SITE_IMPORT=true
WEBU_V2_ADVANCED_AI_WORKSPACE_EDITS=true
```

Recommended staged rollout:

1. Enable `WEBU_V2_CODE_FIRST_INITIAL_GENERATION` in staging first.
2. Enable `WEBU_V2_WORKSPACE_BACKED_VISUAL_BUILDER` after CMS draft-save verification.
3. Enable `WEBU_V2_IMAGE_TO_SITE_IMPORT` after screenshot/design imports are validated.
4. Enable `WEBU_V2_ADVANCED_AI_WORKSPACE_EDITS` last, after chat conflict handling is verified.

## Seed And Builder Service Requirements

- App must be installed: `installation_completed=true`
- An AI provider, builder, and plan must exist for the test user
- Realtime is optional for local verification, but generation status polling must work
- Preview/build worker requirements:
  - Laravel queue worker running for `RunProjectGeneration`
  - workspace scaffold/generation services available
  - preview build path reachable from `/panel/projects/{project}/workspace/regenerate`

Recommended local prep:

```bash
php artisan migrate
php artisan db:seed
php artisan queue:work
npm run dev
```

## Smoke Tests

Run before release:

```bash
cd /Users/besikiekseulidze/web-development/webu/Install
npm run typecheck
npm run test:run -- resources/js/lib/__tests__/webuV2FeatureFlags.test.ts resources/js/hooks/__tests__/useSessionReconnection.test.ts resources/js/builder/codegen/__tests__/generationPhases.test.ts resources/js/builder/codegen/__tests__/workspaceBackedBuilderAdapter.test.ts resources/js/builder/ai/__tests__/editIntentRouter.test.ts resources/js/hooks/__tests__/useAiSiteEditor.test.ts resources/js/builder/workspace/__tests__/workspaceFileClient.test.ts resources/js/builder/workspace/__tests__/workspaceFileState.test.ts
php artisan test tests/Feature/Cms/GenerateWebsiteControllerTest.php tests/Feature/Project/ProjectWorkspaceCodeGenerationTest.php
```

Optional end-to-end smoke:

```bash
WEBU_E2E_RUN_V2_ROLLOUT=1 npx playwright test tests/e2e/flows/webu-v2-rollout.spec.ts
```

The Playwright rollout flow expects authenticated access and a working AI generation environment.

## Manual Verification

1. Open `/create` and submit a prompt.
2. Confirm redirect goes to `/project/{id}?tab=inspect`.
3. Confirm preview iframe stays blocked while generation is active.
4. Wait for ready state and confirm inspect mode becomes usable.
5. Edit hero or other visible text in the visual builder, save draft, confirm preview updates.
6. Switch to Code tab and confirm workspace files load.
7. Confirm `.webu/workspace-manifest.json` reflects updated ownership metadata.
8. Send one safe prop-patch chat command and one workspace/code command.
9. Reload the project during an active generation and confirm reconnect resumes status.

## Rollback Steps

Immediate rollback order:

1. Set `WEBU_V2_ADVANCED_AI_WORKSPACE_EDITS=false`
2. Set `WEBU_V2_IMAGE_TO_SITE_IMPORT=false`
3. Set `WEBU_V2_WORKSPACE_BACKED_VISUAL_BUILDER=false`
4. Set `WEBU_V2_CODE_FIRST_INITIAL_GENERATION=false`

After changing env flags:

```bash
php artisan config:clear
php artisan cache:clear
```

Post-rollback validation:

- `/create` uses legacy project creation
- CMS draft save still works
- design import still generates builder sections through the legacy lane
- chat still handles safe prop patches and legacy project-edit requests
