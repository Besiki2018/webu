# Webu v2 CMS Authority Integration

## Non-negotiable rule

Webu v2 keeps the existing authority rule already documented in `Install/resources/js/builder/README.md`:

- CMS `PageRevision` remains authoritative for visual-builder state.
- Workspace code mirrors CMS for preview/build, AI code editing, and advanced functionality.
- The visual builder hydrates from CMS revisions and persists back to CMS revisions first.
- Workspace files are never allowed to become the sole content source of truth.

This task does not replace the CMS with a code-only architecture. It layers code-first generation and workspace control underneath the existing CMS-managed product.

## Current CMS authority model in this repo

The current repo already has the core authority boundary in place:

- `Install/resources/js/Pages/Project/Cms.tsx` is the orchestration center for visual builder + CMS flows.
- `Install/resources/js/builder/componentRegistry.ts` remains the canonical metadata and inspector schema registry.
- `Install/resources/js/builder/state/updatePipeline.ts` remains the canonical mutation pipeline for builder edits.
- `Install/resources/js/builder/cms/pageHydration.ts` already treats saved CMS revisions as authoritative and only falls back for legacy unrevised pages.
- `Install/app/Cms/Services/CmsPanelPageService.php` hydrates section bindings into `content_json` when revisions are saved.
- `Install/app/Services/ProjectWorkspace/ProjectWorkspaceService.php` already states that CMS/PageRevision is the source of truth for visual builder state and workspace code is an auxiliary projection.

The problem before this task was not that CMS authority was missing. The problem was that the new code-first/runtime work from Task Packs 2-8 could drift into a workspace-first interpretation unless CMS bindings, ownership metadata, and sync rules were made explicit everywhere.

## Why code-first generation must not bypass CMS

If prompt generation only wrote workspace files:

- hero/footer/header copy would be trapped in static code
- visual edits could become temporary builder-only mutations
- reload could rehydrate from code instead of CMS revision content
- AI code edits could silently overwrite CMS-managed content
- image-generated or imported designs could land as dead static pages

That would break Webu's product contract. Generated sites in this repo must still be editable through:

- the current CMS page list and page detail flows
- the current visual builder route
- the current registry-driven inspector
- existing revision history and publish flows

## New v2 integration model

This task adds a dedicated CMS integration layer under `Install/resources/js/builder/cmsIntegration/`:

- `types.ts`
- `contentAuthorityRules.ts`
- `cmsFieldResolver.ts`
- `cmsSectionBinding.ts`
- `cmsPageBinding.ts`
- `cmsBindingModel.ts`
- `cmsSyncPlan.ts`
- `workspaceCmsSync.ts`
- `editRouting.ts`
- `editExecutors.ts`

This layer formalizes one explicit split:

- content -> CMS authority
- layout / page composition -> CMS revision-backed builder structure
- logic / functionality -> workspace code authority
- preview/build output -> derived from CMS revision content plus workspace code state

## Responsibility split

### Content authority

Content-oriented fields are now treated as CMS-managed by convention and metadata:

- hero title/subtitle/body/button labels
- header logo text and navigation labels
- footer copy and link labels
- features/FAQ/testimonial item text
- image URLs and alt text when they are content selections
- form headings, placeholder copy, submit labels
- page SEO title and SEO description

These fields persist through `PageRevision.content_json` or page CMS metadata and are mirrored into workspace metadata for AI/code context.

### Layout / structure authority

Visual composition stays revision-backed:

- section order
- section add/remove
- page composition
- header/footer placement
- builder-visible variants and layout toggles
- structure-oriented props such as `variant`, spacing, alignment, layout tokens

These are still saved through `Cms.tsx -> POST /panel/sites/{site}/pages/{page}/revisions` and remain represented in CMS revision payloads.

### Code authority

Workspace code remains authoritative for functionality:

- custom component files
- route files
- generated utilities and hooks
- API integrations
- submission handlers / webhooks / provider logic
- scaffold-level behavior not represented in schema-driven section props

Those changes continue to flow through the guarded workspace write path in `Install/app/Services/ProjectWorkspace/ProjectWorkspaceService.php`.

## What changed in the runtime

### 1. CMS binding model is now explicit

`cmsBindingModel.ts` builds a canonical CMS binding model from the current builder page model. Each section now gets normalized ownership metadata:

- `cms_backed`
- `content_owner`
- `content_fields`
- `visual_fields`
- `code_owned_fields`
- `sync_direction`
- `conflict_status`
- provenance metadata

Section binding metadata is stored inside the existing `binding` payload under `binding.webu_v2`, so it survives round-trips through existing CMS revision APIs without a new table or migration.

Page-level authority metadata is stored at `content_json.webu_cms_binding`.

### 2. Visual builder saves remain CMS-first

`Install/resources/js/Pages/Project/Cms.tsx` still saves revisions first. The only change is that `buildContentJsonPayload()` now enriches the outgoing payload with the CMS binding model before POSTing the revision.

The save order is still:

1. builder state -> `content_json`
2. POST new `PageRevision`
3. workspace-backed adapter mirrors the canonical result to workspace manifest / preview

### 3. Workspace-backed builder now carries CMS ownership

`Install/resources/js/builder/codegen/workspaceBackedBuilderAdapter.ts` now mirrors CMS binding data into:

- generated page graph metadata
- generated section metadata
- workspace manifest page entries
- workspace manifest file ownership entries

This means the workspace mirror is CMS-aware instead of being only file/path aware.

### 4. Project graph is CMS-aware

`Install/resources/js/builder/codegen/types.ts` now lets generated pages/sections declare:

- `cmsBacked`
- `contentOwner`
- `cmsFieldPaths`
- `visualFieldPaths`
- `codeFieldPaths`
- `syncDirection`
- `conflictStatus`

This is the repo-specific bridge between code-first generation and CMS authority.

### 5. Image-import output also becomes CMS-manageable

`Install/resources/js/builder/image-import/imageToProjectGraph.ts` now attaches the same CMS binding model before it returns:

- `GeneratedProjectGraph`
- workspace plan
- builder-compatible models

So screenshot/design generation no longer produces a dead static graph. It produces a CMS-bindable graph that the existing builder can edit safely.

## Initial AI generation flow

Initial prompt generation now creates both CMS and workspace state:

- `Install/app/Services/AiWebsiteGeneration/GenerateWebsiteProjectService.php`
  - still creates pages and revisions
  - now builds section payloads through `CmsSectionBindingService`
  - now injects `binding.webu_v2` section metadata
  - now injects page-level `webu_cms_binding`

- `Install/app/Services/ProjectWorkspace/ProjectWorkspaceService.php`
  - projects CMS authority metadata into `.webu/workspace-projection.json`
  - carries authority fields into `.webu/workspace-manifest.json`

So the first AI-generated revision is already CMS-driven before the builder becomes ready.

## Edit routing

The new routing layer in `editRouting.ts` and `editExecutors.ts` classifies edits into:

- `content_change`
- `structure_change`
- `code_change`
- `mixed_change`

Repo-specific interpretation:

- content changes persist to CMS revision content
- structure changes persist to CMS revision-backed page composition
- code changes stay on the guarded workspace path
- mixed changes require coordinated CMS + workspace mirroring

Current integration priority is the visual builder save/mirror path. Existing AI layered edit lanes from Task Pack 7 remain in place, but they now have a CMS authority vocabulary to build on.

## Workspace <-> CMS synchronization rules

The new sync plan is explicit:

- CMS -> preview hydration: authoritative for content and builder-visible structure
- CMS -> workspace mirror: allowed and expected
- workspace -> CMS: only for explicitly content-compatible and safe projections
- builder structure -> CMS revision -> workspace mirror
- AI scaffold -> initial CMS revision + workspace scaffold before ready state

Silent destructive overwrite is not allowed. Conflict metadata is tracked via:

- `content_owner`
- `sync_direction`
- `conflict_status`
- provenance flags in binding metadata and workspace manifest entries

## Provenance and locking

The system now records provenance in two places:

- CMS revision payload binding metadata
- workspace manifest ownership metadata

Tracked concepts:

- created by AI
- last edited by visual builder / AI / CMS / code mode
- generated default
- user customized
- requires manual merge

This complements the existing workspace operation log and does not replace it.

## Integration points touched in this repo

Frontend:

- `Install/resources/js/Pages/Project/Cms.tsx`
- `Install/resources/js/builder/componentRegistry.ts`
- `Install/resources/js/builder/state/updatePipeline.ts`
- `Install/resources/js/builder/cms/workspaceBuilderSync.ts`
- `Install/resources/js/builder/cms/pageHydration.ts`
- `Install/resources/js/builder/cms/useEmbeddedBuilderBridge.ts`
- `Install/resources/js/components/Preview/InspectPreview.tsx`
- `Install/resources/js/builder/codegen/workspaceBackedBuilderAdapter.ts`
- `Install/resources/js/builder/codegen/projectGraphToBuilderModel.ts`
- `Install/resources/js/builder/image-import/imageToProjectGraph.ts`

Backend:

- `Install/app/Services/AiWebsiteGeneration/GenerateWebsiteProjectService.php`
- `Install/app/Services/ProjectWorkspace/ProjectWorkspaceService.php`
- existing CMS revision APIs via `Install/app/Cms/Services/CmsPanelPageService.php`

## Acceptance mapping

This implementation satisfies the task pack target as follows:

- newly AI-generated sites still create CMS pages and revisions first
- generated hero/header/footer/content copy is not trapped in static code
- visual content edits still persist through CMS revisions first
- workspace code remains available for Codex-style edits and preview/build flows
- image-generated sites now carry CMS binding metadata as well
- existing builder UX, registry-driven inspector, and bridge mechanics remain intact
- legacy unrevised pages still hydrate through the existing fallback path in `pageHydration.ts`

## Practical outcome

Webu v2 is still a CMS-managed platform.

The difference after this task is that:

- code-first generation now produces CMS-aware artifacts instead of bypassing CMS
- workspace files know which parts are CMS-backed and which parts are code-owned
- the visual builder remains familiar, but its edits now sit on top of an explicit content authority model

That keeps the current product intact while making the v2 architecture defensible.
