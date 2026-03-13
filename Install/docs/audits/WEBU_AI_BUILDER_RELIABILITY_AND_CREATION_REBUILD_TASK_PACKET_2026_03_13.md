# Task Packet: Webu AI Builder Reliability and Creation-Flow Rebuild

## Objective

Rebuild the project-creation and AI-builder experience so Webu behaves like a production-grade AI website builder, not a mixed prototype.

The target is simple:

- project creation must always enter the AI builder flow
- the user must not see a half-ready canvas or broken preview while generation is still running
- generated projects must be real, editable, persisted CMS projects, not demo/test fallback output
- chat edits, inspect edits, preview, and saved state must all operate from one authoritative content model
- the implementation must be technically clean: deterministic routing, explicit state machine, one source of truth, and hard verification before success is shown

This packet is the execution source of truth for that rebuild.

## Non-Negotiable Product Requirements

### R1. `/create` must open the AI builder, not a split or ambiguous flow

After prompt submission, the user must be redirected into the project AI builder surface as soon as the project shell exists.

Target behavior:

- `/create` submit
- backend provisions project shell + generation job/session
- frontend redirects to `/project/{id}` AI builder route
- AI builder opens in "generation in progress" mode
- preview/canvas iframe stays unmounted or replaced with a controlled loading surface until generation is complete
- once generation is complete and persisted, the builder hydrates the real CMS content tree and only then mounts the preview

### R2. Never mount a live site preview before generation is complete

Current partial rendering creates false confidence and exposes broken/demo/test states.

Target behavior:

- no preview iframe mount before `generation_state === ready`
- no stale site tree from previous draft
- no placeholder site that looks "done" while backend is still generating
- loading surface must be explicit, branded, and truthful: progress steps, current stage, retry/cancel behavior, and failure state

### R3. Production generation must never fall back to demo/test content

The following are unacceptable in normal create flow:

- `My Website`
- `Demo Experience`
- `Generic demo section rendered with backend test payload`
- question-mark image blocks
- template/demo filler copy unless user explicitly starts from a template preview/demo mode

### R4. AI edits must be diff-verified before UI says success

No assistant success message unless:

- a persisted revision changed, or
- a verified builder mutation changed persisted state, and
- preview/canvas reflects that saved change

### R5. CMS content is the only authority for generated builder state

Preview, inspect, chat, and builder sync must all hydrate from the same persisted CMS revision tree.

Workspace/generated files are projections, not competing authorities.

### R6. The create/builder UX must feel fast, but never fake

Speed matters, but false success is worse than slower truth.

## Verified Current Problems

This packet is based on the March 13, 2026 audit run in the real app/browser.

### Product/runtime failures

1. Chat edit reliability is not trustworthy.
   - Real prompt `Change the hero headline to QA Hero Audit` returned `422 validation_failed`.
   - The preview did not change.
   - Diagnostic output showed alias/path mismatch behavior (`title => headline`).

2. Create flow can generate projects that still render demo/test fallback output.
   - Generated projects showed `My Website`, `Demo Experience`, placeholder imagery, and generic backend-test copy.

3. Preview runtime can point at the wrong origin and break asset/loading fidelity.
   - Real preview links/assets referenced `127.0.0.1:8000` while the app was served on `127.0.0.1:8001`.
   - Browser emitted `ERR_BLOCKED_BY_RESPONSE.NotSameOrigin`.

4. Error UX is too developer-facing.
   - User-facing chat bubbles expose raw debug logs.
   - Failure copy is truthful but not product-grade.

5. Existing route ownership is still split.
   - `/create` submits generation.
   - controller redirects to chat route with `tab=inspect`.
   - chat embeds a hidden builder bridge iframe.
   - preview and builder authority remain conceptually split.

### Test/harness failures that block confidence

1. Several E2E specs still assume `/create` is public.
2. Some builder/browser specs still target legacy route or selector assumptions.
3. Current coverage does not prove the new create -> AI builder -> generation -> ready flow.

## Root Problem

Webu still behaves like multiple systems stitched together:

- create flow
- chat agent flow
- inspect/builder flow
- preview flow
- workspace projection flow
- template/demo fallback flow

The product needs one explicit lifecycle and one authoritative state model.

## Target End State

## One Clean Lifecycle

### Phase A: Create request

- User submits prompt on `/create`
- backend validates prompt and entitlements
- backend provisions a project shell immediately
- backend creates a generation session/job row with deterministic status
- frontend redirects to `/project/{id}` AI builder route

### Phase B: Builder generation mode

The project page opens immediately, but in a controlled builder loading state.

Required characteristics:

- no mounted live preview iframe yet
- no stale canvas content
- no hidden CMS bridge causing side effects before ready
- explicit generation progress surface
- event-driven status updates or polled job state
- safe refresh/reconnect behavior
- resume generation if the tab reloads

### Phase C: Persisted generation completion

- backend finishes CMS/site/page/section generation
- required previews/assets/runtime metadata are built
- persisted revision becomes authoritative
- builder route receives/observes `generation_state=ready`
- only now does preview iframe mount
- builder/sidebar/chat hydrate from the saved CMS revision

### Phase D: Editing mode

- chat edits use the unified agent
- inspect edits use the same content authority
- all success/failure states are verified against saved revision diffs

## Architecture Rules

### Rule 1: Explicit generation state machine

Introduce a first-class generation lifecycle, for example:

- `queued`
- `provisioning`
- `planning`
- `generating_content`
- `materializing_cms`
- `building_preview`
- `ready`
- `failed`
- `cancelled`

No UI should infer readiness from route changes alone.

### Rule 2: One project builder entry route

Project creation and editing must converge into one authoritative AI builder entry route.

Preferred target:

- `/project/{project}` for AI builder/chat workspace

Supporting rule:

- inspect/visual/canvas mode is a builder mode inside the same runtime, not a separate ownership model

### Rule 3: Preview mount is gated by readiness

Preview iframe mount must be conditional on:

- persisted CMS revision exists
- preview source URL is resolved
- generation state is `ready`
- preview authority points to the correct origin/runtime

### Rule 4: Demo fallback code must not leak into production generation

Template preview/demo utilities may exist, but must never be part of the normal generated-project path unless explicitly requested by a demo/test mode flag.

### Rule 5: Unified edit contract must use schema-valid field names

Interpreter, schema, builder update pipeline, and preview bindings must agree on field names.

No implicit alias mapping should reach executor without validation against the current component schema.

### Rule 6: User-facing errors are product copy; diagnostics are secondary

Default user message:

- short
- truthful
- actionable

Diagnostics:

- hidden behind expandable developer detail
- optionally only shown in admin/dev mode

## Mandatory Workstreams

## P0: Creation Flow Rebuild

### WB-01: Create a first-class generation session model

Problem:
Current create flow redirects after synchronous generation, but the UI has no durable generation-session contract.

Scope:

- add a persisted generation session or job model for project creation
- record project id, user id, prompt, status, stage, started_at, finished_at, failure payload
- expose read endpoint for builder route hydration and reconnect

Primary files:

- `app/Http/Controllers/GenerateWebsiteController.php`
- generation services under `app/Services/AiWebsiteGeneration/`
- any job/event/status infrastructure

Done when:

- project generation status survives refresh
- frontend can resume progress state from persisted session
- ready/failed states are not inferred from redirect timing

### WB-02: Redirect `/create` into AI builder shell immediately after project shell provisioning

Problem:
Create currently lands on a project route, but the experience is still shaped like "redirect into whatever is there", not "open builder in generation mode".

Scope:

- split shell provisioning from full generation completion
- redirect as soon as project + generation session exist
- standardize destination route and query contract

Primary files:

- `resources/js/Pages/Create.tsx`
- `app/Http/Controllers/GenerateWebsiteController.php`
- relevant route/controller wiring

Done when:

- after submit, user always lands in project AI builder route
- no intermediate create-page limbo
- create-page pending redirect hacks are minimized or removed if no longer needed

### WB-03: Gate preview/canvas mount on generation readiness

Problem:
The user can currently end up seeing partial, stale, or broken preview content before the project is truly ready.

Scope:

- do not mount preview iframe during generation phases
- render a dedicated builder-generation surface instead
- mount builder bridge/preview only after `ready`

Primary files:

- `resources/js/Pages/Chat.tsx`
- `resources/js/builder/chat/useBuilderWorkspace.ts`
- `resources/js/components/Preview/InspectPreview.tsx`
- builder hydration hooks/state

Done when:

- no site canvas is visible during generation
- no preview requests fire before ready
- refresh during generation returns to the same loading state, not a half-built canvas

### WB-04: Replace fake/demo preview during generation with a real generation workspace UI

Problem:
A blank or broken canvas is not an acceptable loading strategy.

Scope:

- create a generation-progress workspace with:
  - current stage
  - step timeline
  - truthful status text
  - retry on failure
  - recover on refresh
  - optional generated checklist (pages, sections, assets, theme) as they complete

Primary files:

- `resources/js/Pages/Chat.tsx`
- create/builder skeleton/loading components
- generation status transport hook

Done when:

- users see a coherent builder-generation experience
- no misleading preview appears before ready
- failures are recoverable without returning to `/create`

### WB-05: Remove production-path demo/test content fallback

Problem:
Generation can surface demo/test payload content in normal create flow.

Scope:

- identify and isolate template demo utilities from production generation path
- fail generation explicitly if required production content is missing instead of silently injecting demo filler
- keep demo/template preview behavior behind explicit non-production or preview-only code paths

Primary files:

- `app/Services/TemplateDemoService.php`
- `app/Services/AiWebsiteGeneration/GenerateWebsiteProjectService.php`
- any template/default-content glue code

Done when:

- normal generated projects never show demo/test payload text
- missing content becomes a handled generation failure or fallback-to-clarification flow

## P0: Builder Reliability Rebuild

### WB-06: Fix unified-agent field-path/schema mismatches

Problem:
Real edits can fail because interpreter/executor/schema disagree on field names.

Scope:

- audit hero/header/footer/common section schema names
- enforce schema-backed path resolution before executor mutation
- normalize aliases at planner level only
- reject invalid operation mapping before claiming success

Primary files:

- `app/Services/UnifiedAgent/UnifiedWebuSiteAgentOrchestrator.php`
- interpreter/executor services
- builder update pipeline
- section schema definitions

Done when:

- common prompts like hero headline/text/button changes persist successfully
- validation failures expose exact internal reason to logs, but user gets product-safe copy
- contract tests cover alias and canonical field mapping

### WB-07: Make success/failure diff-driven

Problem:
The UI still risks presenting success based on narration rather than persisted change.

Scope:

- compare pre/post persisted revision
- generate assistant summaries from actual diff
- treat empty diff as no-op or failure

Primary files:

- `resources/js/Pages/Chat.tsx`
- `resources/js/lib/agentErrorMessages.ts`
- unified agent controller/service responses

Done when:

- no success message appears without a real persisted diff
- action log and scope label reflect actual saved changes

### WB-08: Replace raw diagnostic bubbles with layered error UX

Problem:
Current error output is too technical for normal users.

Scope:

- keep user-facing failure copy concise
- move diagnostic log into collapsible secondary panel
- optionally restrict raw diagnostics to admin/dev

Primary files:

- `resources/js/components/Chat/MessageBubble.tsx`
- `resources/js/lib/agentErrorMessages.ts`
- `resources/js/Pages/Chat.tsx`

Done when:

- normal users see clean failure states
- debug data remains accessible for QA/admin diagnosis

## P0: Preview Authority and Runtime Integrity

### WB-09: Make CMS persisted revision the only preview authority

Problem:
Chat, inspect, preview, and workspace projection still behave like competing authorities.

Scope:

- define one authoritative preview source: persisted CMS revision
- ensure builder preview, inspect preview, and chat preview all hydrate from it
- make workspace/project files downstream projection only

Primary files:

- `resources/js/Pages/Chat.tsx`
- `resources/js/Pages/Project/Cms.tsx`
- preview URL generation and hydration services
- workspace generation/projection services

Done when:

- same project revision drives every builder surface
- a saved edit appears consistently across chat, inspect, and CMS

### WB-10: Fix preview/runtime origin resolution

Problem:
Preview/runtime URLs can resolve to the wrong host/port and break assets or in-frame navigation.

Scope:

- remove hardcoded or stale base-url composition
- derive runtime preview/public URLs from active app origin or canonical config
- test local/staging/prod origin correctness

Primary files:

- `app/Services/Builder/BuilderProjectContextService.php`
- preview URL builders in frontend
- runtime/public site route helpers

Done when:

- preview never points to the wrong origin in local or deployed environments
- same-origin preview assets load without browser security errors

### WB-11: Harden iframe sandbox and bridge model

Problem:
Current hidden bridge iframe permissions are too loose and noisy.

Scope:

- review bridge iframe necessity
- minimize sandbox permissions
- reduce hidden iframe side effects during generation/loading states

Primary files:

- `resources/js/Pages/Chat.tsx`
- `resources/js/components/Preview/InspectPreview.tsx`
- bridge messaging utilities

Done when:

- browser warnings are removed or intentionally justified
- hidden bridge does not initialize before builder readiness

## P1: Generation Quality Rebuild

### WB-12: Replace generic brand/content fallback with structured clarification and deterministic defaults

Problem:
Generation falls back to generic names and generic copy too early.

Scope:

- improve brief extraction
- if prompt is too vague, ask a focused clarification before project materialization or use strict deterministic business-safe defaults
- separate "safe default content" from "demo content"

Primary files:

- `app/Services/AiWebsiteGeneration/WebsiteBriefExtractor.php`
- `app/Services/AiWebsiteGeneration/ContentGenerator.php`
- `app/Services/AiWebsiteGeneration/GenerateWebsiteProjectService.php`

Done when:

- common business prompts produce credible brand and section copy
- generic `My Website` fallback is exceptional, not normal

### WB-13: Add a post-generation cleanup/verification pass

Problem:
Even when generation completes, the result may contain placeholders, broken media, empty sections, or fallback copy.

Scope:

- validate generated site before `ready`
- block readiness if critical content is still placeholder-grade
- run cleanup/fix pass for:
  - empty images
  - empty CTA labels
  - generic demo text
  - malformed page/section trees

Primary files:

- generation services
- design/quality evaluator services
- any cleanup services already present

Done when:

- "ready" means visually usable first output, not just technically persisted output

## P1: UX Coherence and Product Polish

### WB-14: Unify create, chat, inspect, and visual-edit language

Problem:
The product still exposes multiple mental models for one builder.

Scope:

- normalize naming across `/create`, builder chat, inspect, preview, and sidebar
- make it obvious when the user is in generation mode vs edit mode
- remove contradictory labels/routes that imply separate products

Done when:

- first-time users can predict what happens after submit
- the UI describes one builder system, not multiple tools

### WB-15: Add recovery UX for failed generation

Problem:
A failed generation should not strand the user.

Scope:

- failed builder-generation state on project route
- retry generation
- return to prompt editing
- inspect error detail for admin/QA

Done when:

- user can recover from generation failure without creating a new project manually

## P2: Performance and Code Health

### WB-16: Reduce builder runtime and CMS chunk size

Problem:
Current CMS bundle is too large and builder boot cost is heavier than it should be.

Scope:

- route-level and mode-level code splitting
- lazy load heavy builder panels and tooling
- keep generation-mode UI minimal before preview readiness

Primary files:

- `vite.config.js`
- builder/chat/CMS entry points

Done when:

- generation route becomes fast to enter
- builder hydration cost drops meaningfully

### WB-17: Fix asset pipeline warnings and font resolution

Problem:
Build still emits unresolved font warnings.

Scope:

- make font asset loading build-safe
- clean up preload vs CSS asset contract

Primary files:

- `resources/views/app.blade.php`
- `resources/css/app.css`
- `vite.config.js`

Done when:

- build is clean
- no unresolved font warnings remain

## Required Test Matrix

Nothing in this packet is done until the following are real and green.

### Unit/integration

- generation session state machine tests
- create redirect contract tests
- unified-agent schema/path contract tests
- diff-verification tests
- preview-origin resolution tests
- no-demo-fallback tests

### Browser E2E

1. Authenticated `/create` submit redirects into project AI builder generation mode
2. During generation, no preview iframe is mounted
3. Refresh during generation resumes the same builder generation state
4. When generation completes, preview mounts and shows real persisted CMS content
5. Generated project does not contain demo/test fallback copy
6. Chat hero headline edit persists and visibly updates preview
7. Inspect click-to-edit updates the same persisted content tree
8. Failure state shows retry UX and does not pretend success
9. Preview URLs stay on the correct origin

### Manual QA gates

- slow network create flow
- hard refresh during generation
- browser reopen/session resume
- same-origin asset loading on local/staging
- service-business prompt
- ecommerce prompt
- Georgian prompt
- English prompt

## Execution Order

Recommended order:

1. `WB-01` to `WB-04`
   Build the generation session and builder loading shell first.
2. `WB-05`, `WB-09`, `WB-10`
   Remove demo leakage and fix authority/origin issues.
3. `WB-06` to `WB-08`
   Make editing trustworthy and product-grade.
4. `WB-12` to `WB-15`
   Improve generation quality and recovery UX.
5. `WB-16` to `WB-17`
   Finish code health and performance cleanup.

## Definition of Done

This rebuild is done only when all of the following are true:

- `/create` always opens the AI builder flow for generation
- no live canvas/site preview is shown before generation is finished
- generated projects never leak demo/test payloads in the production path
- AI edits are schema-valid and diff-verified
- chat, inspect, preview, and CMS all reflect the same persisted content tree
- failures are honest and recoverable
- preview/runtime URLs are origin-correct
- build is clean enough to ship
- the new E2E flow proves the end-to-end behavior

## Reference Files

- [Create.tsx](/Users/besikiekseulidze/web-development/webu/Install/resources/js/Pages/Create.tsx)
- [GenerateWebsiteController.php](/Users/besikiekseulidze/web-development/webu/Install/app/Http/Controllers/GenerateWebsiteController.php)
- [Chat.tsx](/Users/besikiekseulidze/web-development/webu/Install/resources/js/Pages/Chat.tsx)
- [useBuilderWorkspace.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/chat/useBuilderWorkspace.ts)
- [InspectPreview.tsx](/Users/besikiekseulidze/web-development/webu/Install/resources/js/components/Preview/InspectPreview.tsx)
- [UnifiedWebuSiteAgentOrchestrator.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/UnifiedAgent/UnifiedWebuSiteAgentOrchestrator.php)
- [BuilderProjectContextService.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/Builder/BuilderProjectContextService.php)
- [TemplateDemoService.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/TemplateDemoService.php)
- [GenerateWebsiteProjectService.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/AiWebsiteGeneration/GenerateWebsiteProjectService.php)
- [ContentGenerator.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/AiWebsiteGeneration/ContentGenerator.php)
- [WebsiteBriefExtractor.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/AiWebsiteGeneration/WebsiteBriefExtractor.php)
- [MessageBubble.tsx](/Users/besikiekseulidze/web-development/webu/Install/resources/js/components/Chat/MessageBubble.tsx)
- [agentErrorMessages.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/lib/agentErrorMessages.ts)
