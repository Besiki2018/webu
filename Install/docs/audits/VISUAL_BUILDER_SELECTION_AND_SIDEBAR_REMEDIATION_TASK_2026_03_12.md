# Task: Stabilize Visual Builder Selection, Sidebar Authority, and Runtime Performance

## Objective

Refactor the visual builder so component selection is deterministic, the left sidebar always shows the selected component's full parameters, and runtime state cannot fall into selection loops, ghost selections, or click-driven draft mutations.

This task is specifically about making the builder behave like a production visual editor:

- click a component
- that exact component becomes selected
- the sidebar opens that component's full editable parameters
- typing in the sidebar does not flicker, jump selection, or lose focus
- the preview/canvas/sidebar all stay in sync

## Current Verified Problems

### Problem 1: Selection mutates draft state

The preview selection flow can create a new section when a clicked section cannot be matched, which means a plain click path is allowed to mutate the draft tree.

- File:
  - `resources/js/Pages/Project/Cms.tsx`
- Verified area:
  - `selectSectionByPreviewKey(...)`

### Problem 2: Duplicate components can still resolve to the wrong section

When `sectionLocalId` is missing or lost, the system falls back to type/key matching and may bind the click to the first matching component instead of the exact instance the user clicked.

- Files:
  - `resources/js/Pages/Project/Cms.tsx`
  - `resources/js/builder/cms/useCmsEmbeddedBuilderSelectionHandlers.ts`

### Problem 3: Selection authority is split across too many channels

The builder currently uses multiple overlapping selection messages and event mirrors:

- `builder:set-selected-section`
- `builder:set-selected-section-key`
- `builder:set-selected-target`
- `builder:selected-section`
- `builder:selected-target`

This makes feedback loops and state divergence much more likely.

- Files:
  - `resources/js/builder/cms/embeddedBuilderBridgeContract.ts`
  - `resources/js/builder/cms/useChatEmbeddedBuilderBridge.ts`
  - `resources/js/builder/cms/useEmbeddedBuilderBridge.ts`

### Problem 4: The system auto-selects the first section

When selection temporarily becomes `null`, the builder can automatically reselect `sectionsDraft[0]`, which creates jumpy behavior and destroys a clean deselect/intermediate state.

- File:
  - `resources/js/Pages/Project/Cms.tsx`

### Problem 5: Field-level metadata is still driving section-level UX

The builder is supposed to select whole components, but field-level metadata and parameter targeting are still deeply wired into preview, inspect, canvas, and bridge flows.

- Files:
  - `resources/js/components/Preview/useInspectSelectionLifecycle.ts`
  - `resources/js/builder/visual/BuilderCanvasSectionSurface.tsx`
  - `resources/js/builder/cms/useCmsEmbeddedBuilderSelectionHandlers.ts`
  - `resources/js/Pages/Project/Cms.tsx`

### Problem 6: Selection state is too heavy

`selectedBuilderTarget` still carries full `props` payloads and deep equality checks compare large nested objects during normal editing, which increases rerenders and makes typing more fragile.

- Files:
  - `resources/js/builder/editingState.ts`
  - `resources/js/builder/state/builderEditingStore.ts`
  - `resources/js/Pages/Project/Cms.tsx`

### Problem 7: Builder canvas still re-annotates the DOM on prop changes

The canvas annotates every editable node on rerender even though selection is now supposed to be component-level, not per-field. That adds unnecessary DOM churn during editing.

- File:
  - `resources/js/builder/visual/BuilderCanvasSectionSurface.tsx`

### Problem 8: The CMS builder page is still a monolith

`Cms.tsx` remains too large and owns preview selection logic, sidebar behavior, bridge behavior, mutation syncing, and canvas callbacks in one place. This makes regressions hard to isolate and fix.

- File:
  - `resources/js/Pages/Project/Cms.tsx`

## Required End State

The visual builder must follow this rule:

`sectionLocalId` is the only canonical selection identity for regular page components.

Correct selection flow:

1. User clicks a component in preview or canvas.
2. The click resolves to one `sectionLocalId`.
3. Builder state stores that `sectionLocalId` as the single selected component.
4. Sidebar reads that exact component from the draft tree.
5. Sidebar shows the full parameter set for that component.
6. Sidebar edits mutate only that component in the draft tree.
7. Preview updates without changing selection identity.

## Scope

### Task 1: Make selection read-only and identity-safe

- Remove any draft creation or draft mutation from selection handlers.
- Do not allow preview click handlers to add/link/create sections.
- Make regular component selection resolve only by `sectionLocalId`.
- Keep `sectionKey` fallback only where fixed header/footer selection genuinely requires it.

Files:

- `resources/js/Pages/Project/Cms.tsx`
- `resources/js/builder/cms/useCmsEmbeddedBuilderSelectionHandlers.ts`

Acceptance:

- Clicking a component never creates a new section.
- Clicking one of two identical sections always selects the exact clicked instance.
- Clicking a component above/below no longer selects the wrong duplicate.

### Task 2: Collapse bridge selection protocol to one canonical model

- Replace overlapping selection messages with one authoritative component-selection message for normal sections.
- Keep section-key based messaging only for fixed layout parts if needed.
- Remove duplicated mirror flows where possible.
- Make bridge dedupe operate on stable identity and minimal payload, not rich target snapshots.

Files:

- `resources/js/builder/cms/embeddedBuilderBridgeContract.ts`
- `resources/js/builder/cms/useChatEmbeddedBuilderBridge.ts`
- `resources/js/builder/cms/useEmbeddedBuilderBridge.ts`

Acceptance:

- One user click produces one stable selection update path.
- No `Maximum update depth exceeded` loop is triggered by selection echo.
- Parent and iframe cannot fight over the same selection state.

### Task 3: Make sidebar authority strictly component-level

- Sidebar must open from the selected section draft, not from field-level DOM detail.
- `focusedParameterPath` may remain as ephemeral UI focus help, but must not drive selection identity.
- Clicking text/button/image inside a component must still select the whole component.
- Full component parameter groups must be visible in the sidebar.

Files:

- `resources/js/components/Preview/useInspectSelectionLifecycle.ts`
- `resources/js/Pages/Project/Cms.tsx`
- `resources/js/builder/cms/useCmsEmbeddedBuilderSelectionHandlers.ts`
- `resources/js/builder/chat/chatBuilderSelection.ts`

Acceptance:

- Text/image/button clicks select the parent component, not a child field target.
- Left sidebar shows the complete component controls, not partial field-specific controls.
- Focus can move to a relevant field, but selected component remains unchanged.

### Task 4: Slim down selection state and reduce rerender pressure

- Remove full `props` objects from authoritative selection identity where not required.
- Keep heavy component props in the draft tree, not in every selection message.
- Compare selection state with minimal stable fields:
  - `sectionLocalId`
  - `sectionKey` only where needed
  - active sidebar tab
  - optional focused parameter path
- Reduce deep equality checks on large nested payloads during typing.

Files:

- `resources/js/builder/editingState.ts`
- `resources/js/builder/state/builderEditingStore.ts`
- `resources/js/Pages/Project/Cms.tsx`

Acceptance:

- Typing in the sidebar does not flicker or lose focus due to selection object churn.
- Selection updates do not deep-compare full component prop trees on every keystroke.
- Builder state transitions stay cheap during normal edits.

### Task 5: Remove unnecessary field-level DOM annotation churn

- Stop re-annotating the full component subtree on every prop change when not required.
- If field metadata is still needed for focus hints, isolate it from component selection behavior.
- Keep hover/selection overlays bound to component scope.

Files:

- `resources/js/builder/visual/BuilderCanvasSectionSurface.tsx`

Acceptance:

- Component selection remains correct without field-level selection semantics.
- DOM annotation does not rerun unnecessarily during sidebar typing.
- Canvas hover/selection overlays stay stable while editing.

### Task 6: Break `Cms.tsx` into clear runtime boundaries

Extract at least these responsibilities out of the page monolith:

- preview selection controller
- builder bridge sync controller
- sidebar selection state controller
- component parameter focus controller
- builder canvas interaction controller

Files:

- `resources/js/Pages/Project/Cms.tsx`
- new extracted modules under `resources/js/builder/` or `resources/js/components/Preview/`

Acceptance:

- `Cms.tsx` no longer owns all selection, bridge, sidebar, and canvas logic directly.
- Selection regressions can be tested in smaller modules.
- Builder runtime behavior is easier to reason about and debug.

## Jira-Style Subtasks

### P0

#### VB-01: Remove draft mutation from preview selection

- Summary:
  Make preview/canvas selection read-only. Clicking a section must never create, link, or append draft sections.
- Primary files:
  - `resources/js/Pages/Project/Cms.tsx`
- Dependencies:
  - none
- Done when:
  - unresolved selection no longer writes to `sectionsDraft`
  - selection code cannot call section creation paths
  - manual click testing shows no ghost section creation

#### VB-02: Make `sectionLocalId` the canonical section identity

- Summary:
  Resolve normal page-component selection strictly by `sectionLocalId`. Keep `sectionKey` fallback only for fixed layout parts.
- Primary files:
  - `resources/js/Pages/Project/Cms.tsx`
  - `resources/js/builder/cms/useCmsEmbeddedBuilderSelectionHandlers.ts`
- Dependencies:
  - `VB-01`
- Done when:
  - duplicate components no longer resolve to the first match
  - click/highlight/sidebar all track the same local id
  - tests cover identical section types on one page

#### VB-03: Remove auto-reselect of the first section

- Summary:
  Stop the builder from silently selecting `sectionsDraft[0]` whenever selection briefly becomes `null`.
- Primary files:
  - `resources/js/Pages/Project/Cms.tsx`
- Dependencies:
  - `VB-02`
- Done when:
  - deselect/intermediate state can exist without forced reselection
  - selection does not jump to the top component after transient state changes

#### VB-04: Collapse bridge selection to one canonical message

- Summary:
  Simplify the bridge contract so normal section selection goes through one authoritative selection message/event path.
- Primary files:
  - `resources/js/builder/cms/embeddedBuilderBridgeContract.ts`
  - `resources/js/builder/cms/useChatEmbeddedBuilderBridge.ts`
  - `resources/js/builder/cms/useEmbeddedBuilderBridge.ts`
- Dependencies:
  - `VB-02`
  - `VB-03`
- Done when:
  - one user click maps to one selection sync path
  - selection echo no longer causes loop warnings
  - contract comments/types clearly separate regular sections from fixed layout selection

#### VB-05: Make preview and inspect selection strictly component-level

- Summary:
  Clicking text/image/button inside a component must select the parent component only, while preserving optional field focus as UI guidance.
- Primary files:
  - `resources/js/components/Preview/useInspectSelectionLifecycle.ts`
  - `resources/js/Pages/Project/Cms.tsx`
  - `resources/js/builder/chat/chatBuilderSelection.ts`
  - `resources/js/builder/cms/useCmsEmbeddedBuilderSelectionHandlers.ts`
- Dependencies:
  - `VB-02`
  - `VB-04`
- Done when:
  - child-node clicks no longer produce authoritative field-level selection
  - sidebar opens full component controls
  - selection remains stable while moving between inner nodes of the same component

#### VB-06: Decouple `focusedParameterPath` from selection identity

- Summary:
  Keep parameter focus as ephemeral sidebar UX only. It must never decide which component is selected.
- Primary files:
  - `resources/js/Pages/Project/Cms.tsx`
  - `resources/js/components/Preview/useInspectSelectionLifecycle.ts`
- Dependencies:
  - `VB-05`
- Done when:
  - changing focused parameter does not produce selection churn
  - sidebar can scroll/focus a field without changing selected component

### P1

#### VB-07: Slim selection payloads and remove heavy prop-based equality

- Summary:
  Move heavy `props` ownership back to draft state and stop using full props snapshots as the main selection equality input.
- Primary files:
  - `resources/js/builder/editingState.ts`
  - `resources/js/builder/state/builderEditingStore.ts`
  - `resources/js/Pages/Project/Cms.tsx`
- Dependencies:
  - `VB-04`
  - `VB-05`
- Done when:
  - typing does not regenerate large selection payload comparisons
  - selection equality depends on minimal stable fields
  - console/runtime no longer shows selection-loop behavior during input editing

#### VB-08: Remove unnecessary DOM annotation churn from the canvas

- Summary:
  Stop full subtree re-annotation on normal prop edits unless strictly needed for field-focus hints.
- Primary files:
  - `resources/js/builder/visual/BuilderCanvasSectionSurface.tsx`
- Dependencies:
  - `VB-05`
  - `VB-07`
- Done when:
  - canvas does not re-annotate on every sidebar keystroke
  - component hover/selection overlay remains stable during typing

#### VB-09: Extract selection and bridge logic out of `Cms.tsx`

- Summary:
  Split the monolith into smaller controllers/hooks for preview selection, sidebar selection state, bridge sync, and canvas interaction.
- Primary files:
  - `resources/js/Pages/Project/Cms.tsx`
  - new modules under `resources/js/builder/` and `resources/js/components/Preview/`
- Dependencies:
  - `VB-01` through `VB-08`
- Done when:
  - `Cms.tsx` loses the main selection/bridge orchestration burden
  - extracted modules have focused responsibilities and tests

### P2

#### VB-10: Lock the fixes with regression tests and manual QA script

- Summary:
  Add test coverage and a short repeatable QA checklist for the exact failure modes found in this audit.
- Primary files:
  - `resources/js/components/Preview/__tests__/useInspectSelectionLifecycle.test.ts`
  - `resources/js/builder/cms/__tests__/useChatEmbeddedBuilderBridge.test.ts`
  - `resources/js/builder/chat/__tests__/chatBuilderSelection.test.ts`
  - `resources/js/builder/__tests__/editingState.test.ts`
  - `resources/js/builder/visual/__tests__/BuilderCanvas.test.tsx`
  - optional Playwright builder flow coverage
- Dependencies:
  - `VB-01` through `VB-09`
- Done when:
  - duplicate-component selection is covered
  - no-mutation-on-click is covered
  - no-loop-while-typing is covered
  - manual QA can be repeated without tribal knowledge

## Recommended Execution Order

1. `VB-01`
2. `VB-02`
3. `VB-03`
4. `VB-04`
5. `VB-05`
6. `VB-06`
7. `VB-07`
8. `VB-08`
9. `VB-09`
10. `VB-10`

## Milestone Definition

### Milestone A: Deterministic Selection

Includes:

- `VB-01`
- `VB-02`
- `VB-03`

Outcome:

- clicks no longer mutate state
- duplicate components select correctly
- selection no longer jumps to the first section

### Milestone B: Stable Sidebar Sync

Includes:

- `VB-04`
- `VB-05`
- `VB-06`

Outcome:

- one canonical selection sync path
- whole-component selection only
- sidebar focus no longer changes component identity

### Milestone C: Performance and Maintainability

Includes:

- `VB-07`
- `VB-08`
- `VB-09`
- `VB-10`

Outcome:

- no prop-heavy selection churn
- reduced DOM annotation overhead
- smaller runtime boundaries
- regression coverage for future fixes

## Non-Goals

- This task is not about redesigning the visual UI.
- This task is not about adding new component types.
- This task is not about chat content generation quality.
- This task is not about public-site iframe sandbox warnings unless they directly block builder behavior.

## Verification Requirements

### Automated

- `npm run typecheck`
- targeted `vitest` coverage for:
  - preview click selection
  - duplicate component selection
  - bridge dedupe
  - sidebar typing without reselection loops
  - component-level selection behavior

### Manual

Verify all of the following in the visual builder:

1. Open a page with at least two identical component types.
2. Click the lower one, then the upper one.
3. Confirm the exact clicked component is highlighted each time.
4. Confirm the left sidebar always shows the full parameter set for the highlighted component.
5. Click text/button/image inside the same component and confirm selection does not jump to another component.
6. Type into text parameters and confirm:
   - no flicker
   - no focus loss
   - no sidebar reset
   - no highlight jump
7. Confirm browser console shows no selection-loop warnings.

## Definition of Done

This task is complete only when all of the following are true:

- component selection is deterministic
- sidebar authority is component-level
- preview click does not mutate the draft tree
- bridge selection protocol is simplified and loop-safe
- duplicate component selection is exact
- typing does not flicker or reseat selection
- `Cms.tsx` is materially reduced in responsibility
- automated coverage exists for the regression paths above

## Status

- `Audit completed`: March 12, 2026
- `Implementation`: Completed on March 12, 2026
- `Automated verification`: Completed
- `Manual QA checklist`: `docs/audits/VISUAL_BUILDER_SELECTION_QA_CHECKLIST_2026_03_12.md`
- `Manual browser QA execution`: Pending
- `Playwright smoke coverage`: `tests/e2e/flows/nested-inspect-sidebar.spec.ts`
- `Playwright smoke execution`: Attempted on March 12, 2026 against local projects `019cbe17-dd88-728e-940f-da7443accae2` and `019cdcff-b68a-72dc-a9d3-6d31071007b6`; skipped because those preview fixtures did not expose `data-webu-*` inspect markers.

## Completion Checklist

- [x] `VB-01` Remove draft mutation from preview selection
- [x] `VB-02` Make `sectionLocalId` the canonical section identity
- [x] `VB-03` Remove auto-reselect of the first section
- [x] `VB-04` Collapse bridge selection to one canonical message
- [x] `VB-05` Make preview and inspect selection strictly component-level
- [x] `VB-06` Decouple `focusedParameterPath` from selection identity
- [x] `VB-07` Slim selection payloads and remove heavy prop-based equality
- [x] `VB-08` Remove unnecessary DOM annotation churn from the canvas
- [x] `VB-09` Extract selection and bridge logic out of `Cms.tsx`
- [x] `VB-10` Lock the fixes with regression tests and manual QA script

## Completion Notes

- `VB-01` through `VB-03` completed:
  preview selection is read-only, `sectionLocalId` is the canonical identity for normal sections, and first-section auto-reselect was removed.
- `VB-04` through `VB-06` completed:
  bridge selection was collapsed to a canonical selected-target model, component-level selection is authoritative, and focused parameter state no longer defines selection identity.
- `VB-07` completed:
  selection equality now uses a stable signature instead of deep prop-tree comparison, while explicit target prop refreshes still update store state.
- `VB-08` completed:
  canvas annotation no longer carries the unused `props` churn path and does not re-annotate on normal sidebar typing.
- `VB-09` completed:
  selection, bridge, preview, sidebar, lifecycle, fixed-section, and structure mutation orchestration were extracted out of `Cms.tsx` into focused hooks/controllers.
- `VB-10` completed:
  regression coverage was added across builder selection/store/bridge/canvas paths, the nested inspect Playwright smoke was updated to assert component-level sidebar behavior, and the repeatable browser checklist now lives in `docs/audits/VISUAL_BUILDER_SELECTION_QA_CHECKLIST_2026_03_12.md`.
