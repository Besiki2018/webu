# Final Runtime Boundary

Scope locked for production review on the current product routes:

- `/project/{project}` -> chat builder
- `/project/{project}?tab=inspect` -> visual builder

This document marks what is part of the active builder/CMS runtime and what is not.

## Active Core

These files own the live product runtime and must remain behaviorally stable.

- `resources/js/Pages/Chat.tsx`
  - Chat builder route shell, header/save action, preview mounting, chat-mode targeting.
- `resources/js/Pages/Project/Cms.tsx`
  - Visual builder/CMS orchestration, inspector composition, mutation dispatch, draft persistence, runtime UI wiring.
- `resources/js/components/Preview/InspectPreview.tsx`
  - Preview iframe orchestration, overlays, live draft render reconciliation, chat/inspect targeting.
- `resources/js/builder/componentRegistry.ts`
  - Canonical registry for renderer/schema/defaults/component metadata.
- `resources/js/builder/state/updatePipeline.ts`
  - Canonical mutation pipeline for validated builder updates.
- `resources/js/builder/state/builderEditingStore.ts`
  - Shared builder state for selection, hover, readiness, mutation metadata, save-facing UI state.
- `resources/js/builder/domMapper/*`
  - DOM <-> schema binding layer used by live preview reconciliation and targeting.
- `resources/js/builder/cms/*`
  - Embedded bridge, CMS synchronization, draft persistence scheduling, selection sync, structure mutation handlers.
- `resources/js/builder/inspector/*`
  - Schema-driven inspector field resolution and selected-section state building.
- `resources/js/lib/builderBridge.ts`
  - Cross-frame bridge contract, signatures, source guards, message envelope handling.

## Active Support

These are not the minimal runtime spine, but they are imported by the active routes and must remain available.

- `resources/js/builder/workspace/*`
  - Shared workspace shell and preview surface used by the chat builder route.
- `resources/js/builder/visual/*`
  - Visual builder canvas, structure panel, runtime section surfaces, drag/drop primitives.
- `resources/js/builder/schema/*`
  - Canonical schema binding helpers used by inspector and preview reconciliation.
- `resources/js/builder/model/pageModel.ts`
  - Draft <-> content model normalization used by CMS and store reconciliation.
- `resources/js/builder/layout/HeaderFooterLayoutForm.tsx`
  - Global header/footer layout editor used from Cms.
- `resources/js/builder/designSystem/*`
  - Active support for the design-system panel and preview theme application.
- `resources/js/builder/store/builderStore.ts`
  - Project-level builder support store used by Cms orchestration.
- `resources/js/builder/ai/*`
  - Active AI builder support for prompt planning, site generation, targeted editing context, and chat-driven improvement flows.
- `resources/js/builder/chat/useBuilderWorkspace.ts`
  - Chat-route workspace controller.
- `resources/js/builder/updates/chatTargeting.ts`
  - Chat-side selected-target context helpers still used by the active chat builder flow.

## Transitional

These are still present because they provide compatibility or reviewable support, but they should not be treated as canonical runtime ownership.

- `resources/js/builder/state/useBuilderCanvasState.ts`
  - Compatibility hook over `builderEditingStore`; runtime consumers should prefer canonical target/mode state over derived duplicates.
- `resources/js/builder/cms/CmsInspectorPanel.tsx`
  - Thin wrapper around selected-section inspector rendering.
- `resources/js/builder/cms/CmsMutationDispatcher.ts`
  - Thin wrapper around inspector mutation handler creation.
- `resources/js/builder/cms/CmsSchemaResolver.ts`
  - Thin wrapper over selected-section inspector state composition.
- `resources/js/builder/cms/CmsMediaFieldControl.tsx`
  - Builder-only media upload control extracted from `Cms.tsx`; now the stable boundary for builder image/video fields.
- `resources/js/builder/preview/*`
  - Extracted preview helper modules. `InspectPreview.tsx` remains the orchestrator; these files isolate annotation/render/placeholder logic for review.
- Derived compatibility fields inside `builderEditingStore.ts`
  - `selectedTargetId`, `selectedNodeId`, `selectedComponentKey`, `selectedComponentProps`, `builderSidebarMode`, and related aliases still exist for compatibility, but active runtime consumers now resolve increasingly from `selectedBuilderTarget`, `hoveredBuilderTarget`, and `builderMode`.

## Safe To Isolate

These are not part of the live inspect/chat runtime and can be treated as support-only or isolated from production review.

- `resources/js/builder/docs/*`
  - Documentation only.
- `resources/js/builder/DELIVERABLE.md`
  - Historical delivery artifact, not runtime.
- `resources/js/builder/chat/ARCHITECTURE_CHECKLIST.md`
  - Checklist, not runtime.
- `resources/js/builder/__tests__/*`, `resources/js/builder/**/__tests__/*`
  - Validation/support coverage only.
- `resources/js/builder/commands/*`
  - Command helpers not imported by the active chat/inspect runtime.
- `resources/js/builder/templates/*`
  - Static support matrix data; not part of current runtime execution.
- `resources/js/builder/core/*`
  - Architectural support/reference layer, not imported by current active runtime routes.

## Safe To Delete

None in this pass without a dedicated import-audit commit. The runtime boundary is locked first; deletion should happen only after an explicit proof that the file is not imported by:

1. `Chat.tsx`
2. `Cms.tsx`
3. `InspectPreview.tsx`
4. `componentRegistry.ts`
5. `updatePipeline.ts`
6. `builderEditingStore.ts`
7. `builder/cms/*`
8. `builder/inspector/*`
9. `builder/domMapper/*`
10. `builder/workspace/*`

## Runtime Ownership Summary

The current product model is intentionally split into two surfaces that share one builder runtime:

- Chat builder route
  - visible chat
  - visible preview
  - preview targeting enabled
  - no visible inspector sidebar
- Visual builder route
  - visible preview
  - visible inspector/library sidebar
  - no visible chat UI

Both routes still share:

- canonical registry: `resources/js/builder/componentRegistry.ts`
- canonical mutation pipeline: `resources/js/builder/state/updatePipeline.ts`
- canonical bridge protocol: `resources/js/lib/builderBridge.ts`
- canonical draft state source: `resources/js/builder/state/builderEditingStore.ts`
