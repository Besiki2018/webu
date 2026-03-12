# Builder Remediation Roadmap

## Objective

Bring the project creation, chat editing, inspect editing, and preview surfaces onto a single reliable flow that behaves like a production-grade AI website builder instead of a split CMS/workspace prototype.

## Current Status

- `Started`: create-page prompt submission now prefers the CMS-first generation endpoint when the page provides `onQuickGenerate`.
- `Started`: Inertia redirects for `/projects` and `/projects/generate-website` now use `Inertia::location(...)` so SPA form posts leave `/create` and land on the created project.
- `Started`: authenticated users no longer see the cookie consent banner inside editor surfaces.
- `Started`: yoga/fitness prompts now have keyword coverage and an anti-ecommerce override in template selection.
- `Pending`: preview authority is still split between CMS-driven content and builder/workspace-derived surfaces.
- `Pending`: builder completion is still too trusting of assistant summaries and file-change flags.
- `Pending`: inspect-mode click-to-edit still needs deterministic selection, property editing, and preview refresh.

## P0 Tasks

### Task 1: Make `/create` use one primary generation path

- Problem:
  The main create-page prompt previously used `POST /projects` shell-project creation even though the CMS-first generator already existed.
- Scope:
  Route default create-page prompt submission through `POST /projects/generate-website`.
- Files:
  - `resources/js/components/Dashboard/PromptInput.tsx`
  - `resources/js/Pages/Create.tsx`
  - `app/Http/Controllers/GenerateWebsiteController.php`
- Deliverables:
  - Prompt submit on `/create` creates a real generated website instead of an empty AI chat shell.
  - Manual builder mode remains explicit and separate.
- Acceptance:
  - Authenticated prompt submission leaves `/create`.
  - User lands on `/project/{id}/cms` for the generated project.
  - Generated project already has pages/sections in CMS when opened.
- Status:
  - `Started` in this iteration.

### Task 2: Make Inertia redirects deterministic for project creation flows

- Problem:
  Standard Laravel redirects on Inertia requests can leave the user visually stranded on the old page.
- Scope:
  Use Inertia-aware redirect responses for all create/generate entry points.
- Files:
  - `app/Http/Controllers/ProjectController.php`
  - `app/Http/Controllers/GenerateWebsiteController.php`
- Deliverables:
  - Inertia form submits receive `409 + X-Inertia-Location` where appropriate.
  - Manual create, AI create, and generate-website all navigate correctly.
- Acceptance:
  - `/projects` with `X-Inertia: true` redirects to chat or CMS as intended.
  - `/projects/generate-website` with `X-Inertia: true` redirects to `project.cms`.
- Status:
  - `Started` in this iteration.

### Task 3: Remove editor-blocking overlays from authenticated builder surfaces

- Problem:
  Cookie consent UI was mounting across create/chat/inspect pages and embedded sidebars, blocking clicks and causing false UX failures.
- Scope:
  Suppress cookie consent banner for authenticated users and editor iframes.
- Files:
  - `resources/js/components/CookieConsentBanner.tsx`
  - `resources/js/app.tsx`
- Deliverables:
  - No cookie banner on authenticated create/chat/CMS/inspect surfaces.
  - Public marketing pages keep their consent flow.
- Acceptance:
  - Builder CTA buttons are clickable without dismissing an overlay.
  - Embedded sidebar iframe has no bottom consent obstruction.
- Status:
  - `Started` in this iteration.

### Task 4: Stop obvious ecommerce false positives during template selection

- Problem:
  Verticals like yoga/fitness were under-classified and often drifted into ecommerce-focused templates.
- Scope:
  Expand keyword coverage and add a safety override when AI says `ecommerce` but deterministic keyword matching says otherwise.
- Files:
  - `app/Services/TemplateClassifierService.php`
  - `app/Http/Controllers/BuilderProxyController.php`
- Deliverables:
  - Better category recognition for service-business verticals.
  - Reduced ecommerce template selection for non-store prompts.
- Acceptance:
  - Yoga/fitness prompts no longer fall back to ecommerce unless store intent is explicit.
  - Classifier tests cover at least one yoga/fitness scenario.
- Status:
  - `Started` in this iteration.

### Task 5: Unify preview authority across chat, inspect, and CMS

- Problem:
  Chat/code/builder can mutate one surface while inspect/preview renders another.
- Scope:
  Pick one source of truth, preferably CMS content, and make every preview surface render from it.
- Files:
  - `resources/js/Pages/Chat.tsx`
  - `app/Http/Controllers/ChatController.php`
  - `app/Http/Controllers/ProjectCmsController.php`
  - preview runtime / iframe URL generation paths
- Deliverables:
  - Same content tree drives chat preview, inspect preview, and CMS editor preview.
  - Workspace/derived code becomes downstream output, not a competing authority.
- Acceptance:
  - A chat text edit changes DB content and the visible preview on the next refresh cycle.
  - The structure panel and preview show the same sections.
- Status:
  - `Pending`

### Task 6: Verify builder success against actual persisted changes

- Problem:
  Builder completion currently trusts optimistic frontend signals and assistant summaries too early.
- Scope:
  Compare builder-reported changes against persisted CMS revisions and visible preview state before presenting success.
- Files:
  - `resources/js/hooks/useBuilderChat.ts`
  - builder completion endpoints / verification services
  - assistant-summary generation path
- Deliverables:
  - No "success" message unless content or sections actually changed.
  - Assistant summary is produced from a real diff, not from model narration alone.
- Acceptance:
  - A completion without content change is shown as failed/no-op.
  - Files/sections reported in UI match the saved revision.
- Status:
  - `Pending`

## P1 Tasks

### Task 7: Make inspect click-to-edit deterministic

- Problem:
  Clicking elements in preview does not reliably switch the sidebar into editable property mode.
- Scope:
  Stabilize element selection messaging between preview iframe and embedded sidebar.
- Files:
  - `resources/js/components/Preview/InspectPreview.tsx`
  - `resources/js/Pages/Project/Cms.tsx`
  - inspect selection hooks/utilities
- Deliverables:
  - Click element -> highlight -> sidebar property pane -> save -> preview refresh.
- Acceptance:
  - The same selected element ID is visible in preview and property editor.
  - Save updates preview without manual reload gymnastics.
- Status:
  - `Pending`

### Task 8: Expand template coverage matrix beyond core four buckets

- Problem:
  Current classifier/product focus is too coarse for real-world prompts.
- Scope:
  Add targeted support for `fitness`, `studio`, `salon`, `clinic`, `agency`, `course`, `portfolio`, and `landing` prompt families.
- Files:
  - `app/Services/TemplateClassifierService.php`
  - create-template catalog and focused fallback logic
- Deliverables:
  - Better template fit before the builder starts generating content.
- Acceptance:
  - Smoke prompts for at least 8 business types resolve to sane template categories.
- Status:
  - `Pending`

### Task 9: Separate empty-builder and generated-site entry points in UX

- Problem:
  Users currently have one input that mixes two product modes: generate a real site and open a blank AI editing shell.
- Scope:
  Make those two intents explicit in create-page UI.
- Files:
  - `resources/js/Pages/Create.tsx`
  - `resources/js/components/Dashboard/PromptInput.tsx`
  - any mode picker / CTA components
- Deliverables:
  - Clear "Generate Website" path.
  - Clear "Open Empty Builder" path.
- Acceptance:
  - A new user can predict where they will land before submitting.
  - Analytics can distinguish generation vs empty-builder sessions.
- Status:
  - `Pending`

### Task 10: Make assistant summaries diff-driven and user-facing truthful

- Problem:
  Assistant messages can claim sections or changes that never persisted.
- Scope:
  Summaries should be assembled from actual revision diffs, created sections, updated props, and publish state.
- Files:
  - unified agent summary builders
  - verification services
  - frontend message rendering that consumes builder completion payloads
- Deliverables:
  - "What changed" summaries reflect real saved state.
- Acceptance:
  - Summary text, structure panel, and preview all match after each edit.
- Status:
  - `Pending`

## P2 Tasks

### Task 11: Add undo/redo, change history, and publish diff

- Problem:
  There is no resilient recovery path when AI or manual edits go wrong.
- Scope:
  Surface revision history and reversible edits in builder/CMS UI.
- Files:
  - CMS page revision tooling
  - builder/inspect UI state
  - publish dialogs
- Deliverables:
  - Undo/redo for local editing.
  - Revision history with timestamps and change summaries.
  - Publish diff before release.
- Acceptance:
  - User can revert an AI change without using chat prompts.
- Status:
  - `Pending`

### Task 12: Add golden-path E2E coverage for create -> inspect -> chat -> publish

- Problem:
  The system regresses across surface boundaries because end-to-end coverage is too shallow.
- Scope:
  Add Playwright coverage for the real product journey.
- Files:
  - `tests/e2e/flows/*`
  - seeded auth/project fixtures as needed
- Deliverables:
  - Stable E2E tests for:
    - create generated site
    - inspect edit
    - chat edit
    - preview verification
    - publish
- Acceptance:
  - CI catches cross-surface breakage before release.
- Status:
  - `Pending`

## Iteration Log

### Iteration 1

- Routed create-page prompt submission to quick generation when available.
- Added Inertia redirect regression coverage for AI create flow.
- Added Inertia redirect regression coverage for CMS-first generate flow.
- Added yoga studio classifier regression coverage.
- Confirmed targeted PHP and Vitest suites pass.

## Recommended Next Execution Order

1. Finish `Task 5` and `Task 6` together, because preview authority and success verification are coupled.
2. Then do `Task 7`, because inspect reliability depends on the same content identity model.
3. After that, finish `Task 8` and `Task 9` to improve input quality and user expectation-setting.
4. Close with `Task 10`, `Task 11`, and `Task 12` for polish, resilience, and regression safety.
