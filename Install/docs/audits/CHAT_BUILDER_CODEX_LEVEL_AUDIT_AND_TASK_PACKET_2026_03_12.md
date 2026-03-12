# Task Packet: Chat Builder Codex-Level Audit and Remediation

## Objective

Bring the chat builder from a mixed CMS-patch / builder-bridge / project-file-edit flow to one Codex-level agent that can:

- understand the selected component and the wider project
- choose the correct execution mode automatically (`CMS`, `builder`, or `workspace code`)
- create and update section parameters, nested details, and project files
- verify the visible result after execution
- keep chat, builder sidebar, preview, and saved draft in sync

## Verified Current State

### Visual builder remediation

- Existing visual-builder packet is complete: all `VB-01` through `VB-10` are done in [VISUAL_BUILDER_SELECTION_AND_SIDEBAR_REMEDIATION_TASK_2026_03_12.md](/Users/besikiekseulidze/web-development/webu/Install/docs/audits/VISUAL_BUILDER_SELECTION_AND_SIDEBAR_REMEDIATION_TASK_2026_03_12.md#L1).
- Builder-focused unit/regression suite is green:
  - `vitest`: `18/18` files, `77/77` tests passing
  - `typecheck`: passing
  - `build`: passing

### Browser/runtime verification run on March 12, 2026

- `tests/e2e/flows/nested-inspect-sidebar.spec.ts`
  - Result: `skipped`
  - Reason: target preview fixture did not expose `data-webu-*` inspect markers, so the smoke could not assert component-level selection.
- `tests/e2e/flows/builder-authoritative-sync.spec.ts`
  - Result: `failed`
  - Failure mode: after refresh the test expected `iframe[title="Preview"]`, but that selector was not present.
- `tests/e2e/flows/builder-critical.spec.ts`
  - Result: `9 failed`
  - Failure mode: spec targets `/project/{id}/cms` root and old selectors; current UI opens CMS dashboard/navigation instead of the old builder layout.
- `tests/e2e/flows/add-section-by-chat.spec.ts`
  - Result: `2 failed`
  - Failure mode: spec assumes `/create` is public, but current app redirects to `/login`.

## High-Level Audit

### What already works

- Visual builder selection/state regression fixes are now locked by tests.
- Chat-to-builder bridge has a canonical selected-target sync path and structure mutation acknowledgements.
- There is already a real project file editing path with scan, plan, rollback, and verification in [AiProjectFileEditService.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/AiProjectFileEditService.php#L22).

### What is not Codex-level yet

- The primary chat agent path is still a CMS change-set agent, not a project-aware multi-tool agent.
- Code-aware project editing exists, but only as an optional fallback, not as the main orchestration path.
- Runtime browser coverage for real chat-to-builder behavior is stale and does not currently prove the critical flows.

## Findings

### 1. Primary unified agent is still CMS-only in practice

The main interpret/execute path only supports CMS-style operations such as `updateSection`, `insertSection`, `deleteSection`, `reorderSection`, `updateTheme`, and `updateGlobalComponent`; it does not model project-file creation, component/schema creation, parameter creation, or reusable block generation.

- Evidence:
  - [AiInterpretCommandService.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/AiInterpretCommandService.php#L15)
  - [AiAgentExecutorService.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/AiAgentExecutorService.php#L127)

Impact:

- Chat cannot be truly Codex-level while its main agent cannot choose file-edit operations as first-class tools.
- Requests like “add a new prop to this section”, “create a badge field and wire it into the component”, or “create a new reusable block in the project” are outside the primary execution model.

### 2. Workspace/code scan is collected but dropped before interpretation

The unified context collector loads `workspaceScan`, including scanned pages, sections, and component parameters, but `toPageContextForInterpret()` drops that data and only forwards CMS page/theme/selected-target data into the interpreter.

- Evidence:
  - [ContextCollector.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/UnifiedAgent/ContextCollector.php#L45)
  - [UnifiedProjectContext.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/UnifiedAgent/UnifiedProjectContext.php#L69)

Impact:

- The main agent is not reasoning over the actual project workspace even though that scan already exists.
- This blocks Codex-style edits that depend on real file structure, component source, local imports, or generated workspace metadata.

### 3. Chat execution routing is fragmented and contains dead flow

`Chat.tsx` still mixes multiple execution paths:

- unified agent
- AI project edit
- legacy patch flow
- legacy builder transport

There is also unreachable fallback code after an unconditional `return`, which means parts of the old routing logic are dead but still present.

- Evidence:
  - [Chat.tsx](/Users/besikiekseulidze/web-development/webu/Install/resources/js/Pages/Chat.tsx#L1416)

Impact:

- It is difficult to reason about which agent actually owns a request.
- Failures and no-op behavior are harder to diagnose because responsibility is split across multiple paths.
- This is below the bar for a deterministic Codex-like assistant.

### 4. Structure-panel selection payload is too weak for high-precision chat edits

`WorkspaceBuilderStructureItem` only carries `localId`, `sectionKey`, `label`, `previewText`, and `props`. When a structure item is selected, the produced payload does not include `editableFields`, `allowedUpdates`, `variants`, or responsive editing metadata.

- Evidence:
  - [workspaceBuilderSync.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/cms/workspaceBuilderSync.ts#L8)
  - [chatBuilderSelection.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/chat/chatBuilderSelection.ts#L73)

Impact:

- Chat gets a much poorer contract when the user selects from structure/sidebar instead of clicking a rich DOM target.
- Precision drops for parameter-scoped instructions and for safe same-section changes.

### 5. Builder bridge mutation model is still too narrow

The bridge only supports `apply-change-set`, `add-section`, `remove-section`, and `move-section`. There is no first-class mutation contract for:

- duplicate section
- wrap/unwrap or nest/un-nest
- create page
- create repeater/detail item
- create parameter/schema field
- convert section to reusable block

- Evidence:
  - [embeddedBuilderBridgeContract.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/cms/embeddedBuilderBridgeContract.ts#L7)
  - [useCmsEmbeddedBuilderMutationHandlers.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/cms/useCmsEmbeddedBuilderMutationHandlers.ts#L157)

Impact:

- Even if the agent interprets a richer intent, the live builder channel has no authoritative mutation type for many required operations.

### 6. Project file edit path is real, but still fallback-oriented and field-scoped

The project edit controller accepts rich `selected_element` metadata, but when selection is present it still requires `section_id`, `parameter_path`, and `element_id`. The service is capable of real file edits, but it is not the primary unified execution path.

- Evidence:
  - [ProjectAiProjectEditController.php](/Users/besikiekseulidze/web-development/webu/Install/app/Http/Controllers/ProjectAiProjectEditController.php#L24)
  - [AiProjectFileEditService.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/AiProjectFileEditService.php#L36)

Impact:

- Workspace code edits are still “secondary mode”, not a normal tool the main agent can select.
- Requests that should broaden from “edit this selected thing” into “add missing prop, update component code, and expose it in the UI” are not cleanly modeled.

### 7. E2E coverage for real chat-builder behavior is stale

The current Playwright coverage is not validating the actual production-critical flows:

- [add-section-by-chat.spec.ts](/Users/besikiekseulidze/web-development/webu/Install/tests/e2e/flows/add-section-by-chat.spec.ts#L8) assumes `/create` is public.
- [builder-critical.spec.ts](/Users/besikiekseulidze/web-development/webu/Install/tests/e2e/flows/builder-critical.spec.ts#L26) assumes `/project/{id}/cms` root is the builder surface.
- [builder-authoritative-sync.spec.ts](/Users/besikiekseulidze/web-development/webu/Install/tests/e2e/flows/builder-authoritative-sync.spec.ts#L61) depends on a specific preview iframe selector.
- [nested-inspect-sidebar.spec.ts](/Users/besikiekseulidze/web-development/webu/Install/tests/e2e/flows/nested-inspect-sidebar.spec.ts#L14) requires `data-webu-*` markers that were not present in the tested preview fixtures.

Impact:

- Browser confidence for the most important flows is weaker than the unit coverage suggests.
- Regressions in chat-to-builder behavior can still slip through.

### 8. Visual builder is functionally stabilized, but still architecturally heavy

Build still emits a large `Cms` chunk and unresolved font asset warnings.

- Evidence:
  - `npm run build` on March 12, 2026 produced `public/build/assets/Cms-DIXMPNew.js` at `1,068.16 kB`
  - unresolved `TBCContractica` font asset warnings remained

Impact:

- Not a blocker for the completed selection remediation, but still a performance and maintainability concern.

## Codex-Level End State

The target chat builder should behave like this:

1. User selects a component, section, page, or nothing.
2. Agent inspects both builder state and project workspace.
3. Agent chooses the right tool chain:
   - `CMS content patch`
   - `builder structural mutation`
   - `workspace file edit`
   - `schema/parameter generation`
4. Agent executes a multi-step plan.
5. Agent verifies the visible result in builder/preview.
6. Agent returns a concrete action log and keeps selection/sync stable.

For requests like:

- “Add a small eyebrow label above this hero title”
- “Create a new badge parameter for this card component and show it in the sidebar”
- “Add FAQ section under pricing and wire its content”
- “Create a new testimonial card detail model in this project”

the assistant should be able to complete the full path, not just partially patch existing fields.

## Jira-Style Task Breakdown

### P0

#### CB-01: Make unified agent truly multi-tool

- Summary:
  Merge `CMS change-set`, `builder mutation`, and `project file edit` into one orchestration path instead of treating file edit as fallback.
- Primary files:
  - [UnifiedWebuSiteAgentOrchestrator.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/UnifiedAgent/UnifiedWebuSiteAgentOrchestrator.php#L20)
  - [useAiSiteEditor.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/hooks/useAiSiteEditor.ts#L231)
  - [Chat.tsx](/Users/besikiekseulidze/web-development/webu/Install/resources/js/Pages/Chat.tsx#L1416)
- Done when:
  - one request has one authoritative planner
  - planner can select file-edit tools without leaving the unified path
  - dead fallback routing is removed

#### CB-02: Pass workspace scan and component metadata into interpretation

- Summary:
  Use the already-collected workspace scan during planning so the agent sees real project files, extracted component parameters, and relevant page/component paths.
- Primary files:
  - [ContextCollector.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/UnifiedAgent/ContextCollector.php#L45)
  - [UnifiedProjectContext.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/UnifiedAgent/UnifiedProjectContext.php#L69)
  - [AiInterpretCommandService.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/AiInterpretCommandService.php#L41)
- Done when:
  - interpret prompt receives workspace scan summary
  - agent can cite real project files/components in its plan
  - code-aware tasks stop being blind CMS-only requests

#### CB-03: Expand the authoritative operation model

- Summary:
  Add first-class operations for parameter creation, nested-detail creation, duplication, page creation, and reusable block generation.
- Primary files:
  - [AiInterpretCommandService.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/AiInterpretCommandService.php#L15)
  - [AiAgentExecutorService.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/AiAgentExecutorService.php#L127)
  - [embeddedBuilderBridgeContract.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/cms/embeddedBuilderBridgeContract.ts#L46)
- New ops to add:
  - `duplicateSection`
  - `createPage`
  - `createNestedItem`
  - `deleteNestedItem`
  - `reorderNestedItem`
  - `createParameter`
  - `updateParameterSchema`
  - `createReusableBlock`
  - `applyWorkspaceFilePatch`
- Done when:
  - these operations can be planned, executed, acknowledged, and verified

#### CB-04: Unify selection context into a schema-rich target contract

- Summary:
  Make structure-panel and chat selection carry enough metadata for safe Codex-level edits.
- Primary files:
  - [workspaceBuilderSync.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/cms/workspaceBuilderSync.ts#L8)
  - [chatBuilderSelection.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/chat/chatBuilderSelection.ts#L73)
- Add to selection payload:
  - `editableFields`
  - `allowedUpdates`
  - `variants`
  - `responsiveContext`
  - `component schema/version`
- Done when:
  - structure-panel selection yields the same edit precision as DOM-target selection

#### CB-05: Add parameter-generation workflow for existing project components

- Summary:
  Let chat create a new prop/parameter inside the project and expose it end-to-end.
- Required workflow:
  1. create/update component prop type
  2. update default values
  3. update render usage
  4. update extracted metadata/schema
  5. expose new field in builder sidebar
  6. verify preview changed
- Primary files:
  - [AiProjectFileEditService.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/AiProjectFileEditService.php#L36)
  - [CodebaseScanner.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/WebuCodex/CodebaseScanner.php#L96)
  - existing workspace metadata scanners under `app/Services/ProjectWorkspace/`
- Done when:
  - a prompt like “add badge text parameter above the hero title” completes end-to-end

### P1

#### CB-06: Add nested detail/entity generation inside project components

- Summary:
  Support prompts that create repeatable inner details such as cards, testimonials, FAQ items, nav links, features, or pricing rows.
- Done when:
  - agent can create new list item schema/data
  - preview and sidebar both expose the new detail
  - reorder/remove of generated details is supported

#### CB-07: Promote project file edit into a first-class tool inside unified execution

- Summary:
  Keep [AiProjectFileEditService.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/AiProjectFileEditService.php#L22), but call it as a planner-selected tool instead of a separate fallback path.
- Done when:
  - unified agent can decide “this request requires file edits”
  - chat response still returns one coherent action log

#### CB-08: Add post-execution verification loop

- Summary:
  A Codex-level agent must verify that the requested result actually happened.
- Verification layers:
  - structural diff
  - selected component prop diff
  - preview DOM marker check
  - optional screenshot/visual diff
- Done when:
  - failed/no-op edits are caught before “Done” is shown to the user

#### CB-09: Add builder-side mutation acknowledgements for new ops

- Summary:
  Extend bridge/result handling so every new operation returns structured success/failure and selection reconciliation.
- Primary files:
  - [embeddedBuilderBridgeContract.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/cms/embeddedBuilderBridgeContract.ts#L7)
  - [useCmsEmbeddedBuilderMutationHandlers.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/cms/useCmsEmbeddedBuilderMutationHandlers.ts#L157)
  - [useChatEmbeddedBuilderBridge.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/cms/useChatEmbeddedBuilderBridge.ts#L1)
- Done when:
  - chat can reliably know whether each mutation changed builder state

### P2

#### CB-10: Replace stale Playwright coverage with real chat-builder smoke tests

- Summary:
  Rewrite current E2E specs so they hit the real routes, auth model, and current UI.
- Replace/upgrade:
  - [add-section-by-chat.spec.ts](/Users/besikiekseulidze/web-development/webu/Install/tests/e2e/flows/add-section-by-chat.spec.ts#L1)
  - [builder-critical.spec.ts](/Users/besikiekseulidze/web-development/webu/Install/tests/e2e/flows/builder-critical.spec.ts#L1)
  - [builder-authoritative-sync.spec.ts](/Users/besikiekseulidze/web-development/webu/Install/tests/e2e/flows/builder-authoritative-sync.spec.ts#L1)
- New smoke flows:
  - authenticated chat page load
  - select component from builder, send targeted chat edit, verify sidebar + preview
  - create section by chat, verify structure + preview + save
  - create new parameter by chat, verify sidebar field appears

#### CB-11: Add conversational regression pack for Codex-level requests

- Summary:
  Cover the exact natural-language flows that matter.
- Required prompts:
  - “Change this title”
  - “Add FAQ section below pricing”
  - “Create badge text parameter for this hero and set it to New”
  - “Add one more pricing card with enterprise tier”
  - “Create a testimonials page and add it to navigation”
- Done when:
  - prompts map to correct execution modes and verified changes

## Recommended Execution Order

1. `CB-01`
2. `CB-02`
3. `CB-03`
4. `CB-04`
5. `CB-05`
6. `CB-06`
7. `CB-07`
8. `CB-08`
9. `CB-09`
10. `CB-10`
11. `CB-11`

## Definition of Done

This packet is complete only when all of the following are true:

- chat has one authoritative planner/orchestrator
- workspace/code context is part of normal planning
- chat can create and wire new parameters in project code
- chat can create nested details/items and structural blocks
- chat can choose between CMS mutation and workspace file edits automatically
- each applied change is verified before success is reported
- browser E2E coverage proves the current routes and UI, not old fixtures

## Status

- `Audit completed`: March 12, 2026
- `Automated code verification`: Completed
- `Browser smoke execution`: Completed with failures/skip documented above
- `Implementation`: Not started
