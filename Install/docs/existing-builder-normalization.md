# Existing Builder Normalization

## Scope

This pass keeps only the legacy inspect builder used at:

- `/project/{project}?tab=inspect`

No standalone builder route or same-runtime V2 shell is part of the active product flow.

## Active Legacy Flow

1. `resources/js/Pages/Chat.tsx`
   Chat owns the workspace shell, opens inspect mode, mounts the preview surface, and hosts the embedded CMS sidebar iframe.
2. `resources/js/builder/chat/useBuilderWorkspace.ts`
   Central chat-side workspace hook for preview/sidebar state, selected target state, embedded bridge wiring, and structure panel state.
3. `resources/js/components/Preview/InspectPreview.tsx`
   Preview iframe surface, overlay layer, selection callbacks, and builder-facing preview behavior.
4. `resources/js/components/Preview/useInspectSelectionLifecycle.ts`
   Inspect hover, click, selection overlay, library placement interactions, and blank-area fallback behavior.
5. `resources/js/components/Preview/inspectPreviewTargets.ts`
   Canonical DOM target resolution for field, scope, and section selection inside the preview iframe.
6. `resources/js/Pages/Project/Cms.tsx`
   Embedded sidebar/editor runtime, schema-driven inspector rendering, mutation dispatch, and preview/sidebar bridge integration.
7. `resources/js/builder/cms/useChatEmbeddedBuilderBridge.ts`
   Chat-to-sidebar bridge transport and optimistic mutation synchronization.
8. `resources/js/builder/cms/useEmbeddedBuilderBridge.ts`
   Sidebar-to-chat bridge transport and authoritative state sync from the embedded CMS.
9. `resources/js/builder/state/builderEditingStore.ts`
   Single legacy selection/editing store for selected target, hovered target, preview/sidebar readiness, and mutation sync metadata.
10. `resources/js/builder/editingState.ts`
    Canonical target normalization for preview mentions, bridge payloads, schema-backed allowed update paths, and sidebar tab routing.

## Transitional Layer Classification

### `resources/js/Pages/Chat.tsx`
- Classification: core and required
- Action: kept as the workspace shell and inlined the trivial frame wrappers so the active runtime is easier to trace.

### `resources/js/builder/chat/BuilderPreviewFrame.tsx`
- Classification: transitional and removable
- Reason: it only computed `previewUrl` and forwarded props to `InspectPreview`.
- Action: merged into `Chat.tsx` and removed.

### `resources/js/builder/chat/BuilderSidebarFrame.tsx`
- Classification: transitional and removable
- Reason: it only rendered the sidebar iframe element.
- Action: merged into `Chat.tsx` and removed.

### `resources/js/builder/chat/useBuilderWorkspace.ts`
- Classification: required but should be simplified
- Reason: it is the real chat-side builder orchestration hook, but it previously sat behind trivial wrapper components.
- Action in this pass: kept as the core workspace hook after removing the wrapper layer above it.

### `resources/js/builder/cms/chatBuilderStructureMutations.ts`
- Classification: core and required
- Reason: it contains the optimistic structure mutation helpers shared by the legacy bridge flow.
- Action: kept unchanged.

## Parameter Binding Audit

### Stable binding path

The active binding path is:

1. Preview target DOM markers (`data-webu-field`, `data-webu-field-url`, `data-webu-field-scope`)
2. `inspectPreviewTargets.ts` resolves the clicked/hovered preview target
3. `editingState.ts` normalizes the target into a `BuilderEditableTarget`
4. `Cms.tsx` calls `buildSelectedSectionInspectorState(...)`
5. `SelectedSectionEditableFields` renders controls from schema field paths
6. `renderSchemaFieldEditorControl(...)` reads the current value via `getValueAtPath(parsedProps, field.path)`
7. `updateSectionPathProp(...)` writes back through the mutation pipeline
8. Preview rerenders from section props merged through `resolveComponentProps(...)`

### Field families audited

| Editable concern | Sidebar current value source | Preview render source | Current canonical path |
| --- | --- | --- | --- |
| Title | `Cms.tsx` → `renderSchemaFieldEditorControl` → `getValueAtPath(parsedProps, field.path)` | component renderers consume `resolveComponentProps(section.type, section.props ?? section.propsText)` | `title` or canonical schema alias |
| Subtitle / text | same schema-path read as above | same resolved props path in renderers | `subtitle`, `body`, or canonical alias group |
| Button label | same schema-path read as above | preview target markers may emit `button`, `buttonText`, `buttonLabel`, `ctaLabel` | canonicalized to schema-backed button text path |
| Button link | same schema-path read as above | preview target markers may emit `button_url`, `buttonLink`, `buttonUrl`, `ctaUrl` | canonicalized to schema-backed button link path |
| Image / media | same schema-path read as above | preview target markers emit `image`, `image_url`, `backgroundImage`, etc. depending component | canonicalized to schema-backed image/media path |

### Normalization fix in this pass

The critical drift fix is now in `resources/js/builder/componentRegistry.ts` + `resources/js/builder/editingState.ts`:

- preview alias paths such as `buttonLabel`, `buttonUrl`, `ctaLabel`, `ctaUrl`, `imageUrl`, and older root aliases are normalized against the canonical schema field path
- allowed update paths are generated from schema-backed canonical fields instead of loose family fallback
- stale preview targets that still do not map to the selected schema are ignored safely and emit one clear dev-only warning

This removes the old behavior where inspector filtering could silently rely on broad family matching and accidentally surface unrelated fields.

### Runtime hardening follow-up

The follow-up hardening pass added one more schema-backed guard in the preview mutation path:

- `Cms.tsx` now resolves preview-applied section props through `resolveComponentProps(...)` before mutating preview DOM
- preview text fallback now uses `resolveSchemaPreferredStringProp(...)` so schema-backed `title` wins over stale legacy aliases such as `headline` when the schema only defines `title`
- preview CTA hydration now reads canonical `buttonText` / `buttonLink` and `ctaText` / `ctaLink` combinations before falling back to older loose `button` payloads

## Cleanup Performed

- Removed the trivial wrapper layer:
  - `resources/js/builder/chat/BuilderPreviewFrame.tsx`
  - `resources/js/builder/chat/BuilderSidebarFrame.tsx`
- Extracted maintainable legacy-core helpers without changing the runtime shape:
  - `resources/js/builder/inspector/InspectorFieldResolver.ts`
  - `resources/js/builder/inspector/InspectorRenderer.ts`
  - `resources/js/builder/mutations/applyInspectorMutation.ts`
  - `resources/js/builder/schema/schemaBindingResolver.ts`
  - `resources/js/builder/workspace/BuilderWorkspaceShell.tsx`
  - `resources/js/builder/workspace/BuilderPreviewSurface.tsx`
- Removed dead standalone-builder documentation that no longer describes the active product flow:
  - `docs/builder-architecture-v2.md`
  - `docs/builder-component-registry.md`
  - `docs/builder-mutation-system.md`
- Removed dead standalone-builder tests that targeted the retired V2 surface:
  - `resources/js/builder/__tests__/builderV2MutationPipeline.test.ts`
  - `resources/js/builder/__tests__/canvasSelection.test.tsx`
  - `resources/js/builder/__tests__/componentInsertion.test.ts`
  - `resources/js/builder/__tests__/schemaResolution.test.tsx`
  - `resources/js/builder/__tests__/aiMutationAdapter.test.ts`

## Risky Areas Preserved

- The embedded iframe bridge remains part of the active architecture and must not be removed until the legacy builder itself is replaced.
- `Cms.tsx` still contains large amounts of schema, preview, and editor logic in one file. This pass only tightened binding and mutation determinism without redesigning the runtime.
- The broader `resources/js/builder/*` tree still contains inactive code from earlier experiments. It is isolated from the active product flow, but broad deletion should be done only in a dedicated cleanup pass.
- Repeated unsaved placeholder insertions still make the legacy preview harder to reason about because draft-only placeholders accumulate in the preview surface until the page is refreshed or persisted.

## Browser Runtime Verification

Browser smoke verification was run directly against:

- `http://127.0.0.1:8001/project/019cbe17-dd88-728e-940f-da7443accae2?tab=inspect`

Verified in the active legacy route:

- preview iframe and embedded sidebar iframe both load correctly
- clicking preview text enters inspector mode for the matching section
- Product Grid selection resolves deterministically and the sidebar reads the selected `title` value
- hover over another preview target does not replace the selected section in the sidebar
- blank preview clicks keep section-level selection stable instead of switching to unrelated state
- inserting `Hero Split Image` auto-selects the inserted section in the sidebar inspector
- the legacy image field opens the embedded media library and applies uploaded media successfully
- full page reload restores the inspect shell and reselecting a saved preview target rehydrates the correct sidebar inspector

Observed residual runtime note:

- live preview updates for repeatedly edited unsaved placeholders remain sensitive to legacy bridge/refresh timing and should be the next cleanup target if deeper stabilization is required

## Verification Targets

The inspect builder is considered normalized when:

- preview clicks resolve one deterministic legacy target path
- sidebar fields read and write the same canonical schema path the preview renderers use
- hover never overrides selection
- insert/delete flows leave no stale selected target
- no standalone builder route or wrapper UI remains in the active flow
- extracted helper modules reduce `Chat.tsx` and `Cms.tsx` without changing the legacy route or iframe architecture
