# Final Builder Consolidation Audit

## Scope

This audit covers the final consolidation pass after:

- legacy inspect builder cleanup
- registry unification
- structure mutation pipeline unification

Route in scope:

- `/project/{project}?tab=inspect`

Canonical registry:

- `resources/js/builder/componentRegistry.ts`

## Phase 1 — Compatibility Shim Audit

### Removed shims

The following compatibility files are no longer needed by the active inspect runtime or any remaining TypeScript module under `resources/js`:

- `resources/js/builder/centralComponentRegistry.ts`
- `resources/js/builder/registry/componentRegistry.ts`
- `resources/js/builder/registry/index.ts`

### Why removal is safe

Before removal, repo-wide import checks showed:

- no active inspect runtime file imported either shim
- no support subsystem file needed the shim after import migration
- all runtime/test/support imports can resolve from `resources/js/builder/componentRegistry.ts`

### Migrated references

These files were moved from shim imports to the canonical registry:

- `resources/js/builder/ai/siteBuilder.ts`
- `resources/js/builder/ai/componentRequestDetector.ts`
- `resources/js/builder/ai/componentSelector.ts`
- `resources/js/builder/ai/layoutBuilder.ts`
- `resources/js/builder/ai/layoutRefine.ts`
- `resources/js/builder/ai/sectionMapper.ts`
- `resources/js/builder/ai/sitePlanner.ts`
- `resources/js/builder/ai/designStyleAnalyzer.ts`
- `resources/js/builder/ai/variantMatcher.ts`
- `resources/js/builder/ai/registryInjectionGenerator.ts`
- `resources/js/builder/aiRefactorEngine.ts`
- `resources/js/builder/aiSiteGeneration.ts`
- `resources/js/builder/componentCompatibility.ts`
- `resources/js/builder/componentMetadataInjection.ts`
- `resources/js/builder/refactorEngine.ts`
- `resources/js/builder/smartImageSystem.ts`
- `resources/js/builder/smartLayoutEngine.ts`
- `resources/js/builder/updates/chatTargeting.ts`
- `resources/js/builder/updates/updateComponentProps.ts`
- `resources/js/builder/inspector/SidebarInspector.tsx`
- `resources/js/builder/__tests__/phase9ArchitectureValidation.test.tsx`
- `resources/js/builder/__tests__/architectureValidation.test.ts`
- `resources/js/builder/__tests__/componentValidation.test.tsx`
- `resources/js/builder/__tests__/runtimeVerification.test.tsx`
- `resources/js/builder/__tests__/registryIntegration.test.tsx`
- `resources/js/builder/__tests__/legacyDetection.test.ts`

### Remaining transitional registry API

No registry shim file remains.

The only transitional element left is naming, not ownership:

- `getCentralRegistryEntry(...)`
- `isInCentralRegistry(...)`
- `REGISTRY_ID_TO_KEY`

These still exist in `resources/js/builder/componentRegistry.ts` for compatibility with existing call sites, but they now resolve from the canonical registry file and do not create a second source of truth.

## Phase 2 — Active Runtime vs Support Subsystem Isolation

### 1. ACTIVE INSPECT RUNTIME

Core route entry and live runtime:

- `resources/js/Pages/Chat.tsx`
- `resources/js/Pages/Project/Cms.tsx`
- `resources/js/components/Preview/InspectPreview.tsx`
- `resources/js/components/Preview/useInspectSelectionLifecycle.ts`
- `resources/js/components/Preview/inspectPreviewTargets.ts`
- `resources/js/lib/builderBridge.ts`
- `resources/js/builder/cms/*`
- `resources/js/builder/state/*`
- `resources/js/builder/schema/*`
- `resources/js/builder/visual/*`
- `resources/js/builder/workspace/*`
- `resources/js/builder/domMapper/*`
- `resources/js/builder/componentRegistry.ts`
- `resources/js/builder/editingState.ts`
- `resources/js/builder/inspector/InspectorRenderer.tsx`
- `resources/js/builder/inspector/InspectorFieldResolver.ts`
- `resources/js/builder/inspector/selectedSectionInspectorState.ts`
- `resources/js/builder/schemaBindingResolver.ts`

Why active:

- these files own preview rendering, bridge sync, selection state, schema resolution, mutation application, and inspector field rendering for `/project/{project}?tab=inspect`

### 2. SUPPORT / AI / OFFLINE TOOLS

Support-only or auxiliary systems, not part of the core inspect runtime state loop:

- `resources/js/builder/ai/*`
- `resources/js/builder/commands/*`
- `resources/js/builder/designSystem/*`
- `resources/js/builder/updates/*`
- `resources/js/builder/componentCompatibility.ts`
- `resources/js/builder/componentMetadataInjection.ts`
- `resources/js/builder/aiSiteGeneration.ts`
- `resources/js/builder/aiProjectProcessor.ts`
- `resources/js/builder/aiPromptToSite.ts`
- `resources/js/builder/aiRefactorEngine.ts`
- `resources/js/builder/projectProcessor.ts`
- `resources/js/builder/refactorEngine.ts`
- `resources/js/builder/smartLayoutEngine.ts`
- `resources/js/builder/smartImageSystem.ts`
- `resources/js/builder/performanceOptimization.ts`

Why support-only:

- these modules drive AI generation, AI refinement, offline transforms, design-system utilities, or legacy builder-store tooling
- they are not required for the inspect preview/selection/bridge/render loop to function

### Explicit support dependencies still attached to the route

The active route still intentionally imports support modules in the UI layer:

- `resources/js/Pages/Chat.tsx`
  - `@/builder/ai/chatImprovementCommands`
  - `@/builder/ai/designUpgrade`
- `resources/js/Pages/Project/Cms.tsx`
  - `@/builder/ai/layoutRefine`
  - `@/builder/ai/siteOptimizer`
  - `@/builder/ai/chatImprovementCommands`
  - `@/builder/ai/componentScoring`
  - `@/builder/ai/aiBrandGenerator`
  - `@/builder/ai/generateSiteFromPrompt`
  - `@/builder/ai/generateLayoutFromDesign`
  - `@/ai`

Why this is acceptable:

- these are explicit product features layered on the route
- the underlying inspect runtime no longer depends on registry shims or AI subsystems for preview rendering, bridge sync, or state ownership

### 3. TRANSITIONAL COMPATIBILITY

Remaining transitional pieces:

- `resources/js/builder/componentRegistry.ts`
  - keeps legacy-named helpers such as `getCentralRegistryEntry(...)`
  - still exposes alias resolution for old section identifiers to preserve inspect compatibility

Why transitional:

- these are compatibility APIs inside the canonical registry, not parallel registries

### 4. SAFE TO DELETE LATER

Likely removable in a future pass after product confirmation:

- `resources/js/builder/inspector/SidebarInspector.tsx`
- `resources/js/builder/updates/*`
- `resources/js/builder/componentCompatibility.ts`
- `resources/js/builder/componentMetadataInjection.ts`
- `resources/js/builder/aiProjectProcessor.ts`
- `resources/js/builder/aiPromptToSite.ts`
- `resources/js/builder/aiRefactorEngine.ts`
- `resources/js/builder/projectProcessor.ts`
- `resources/js/builder/refactorEngine.ts`
- `resources/js/builder/smartLayoutEngine.ts`
- `resources/js/builder/smartImageSystem.ts`
- `resources/js/builder/performanceOptimization.ts`

Why not deleted now:

- they are no longer part of the canonical inspect runtime boundary, but they still support AI/editor tooling, docs, or legacy test coverage

## Phase 3 — Final Runtime Boundary

### Canonical inspect runtime flow

`Chat.tsx`
-> `builder/chat/useBuilderWorkspace.ts`
-> `builder/workspace/*`
-> `InspectPreview.tsx`
-> `builder/cms/useChatEmbeddedBuilderBridge.ts`
-> `builder/cms/useEmbeddedBuilderBridge.ts`
-> `Pages/Project/Cms.tsx`
-> `builder/state/builderEditingStore.ts`
-> `builder/state/updatePipeline.ts`
-> `builder/inspector/selectedSectionInspectorState.ts`
-> `builder/inspector/InspectorRenderer.tsx`
-> `builder/domMapper/domMapper.ts`
-> `builder/componentRegistry.ts`

### Runtime ownership summary

- component lookup, schema lookup, defaults, renderer resolution:
  - `resources/js/builder/componentRegistry.ts`
- structure mutations:
  - `resources/js/builder/state/updatePipeline.ts`
- inspect selection / hover / preview state:
  - `resources/js/builder/state/builderEditingStore.ts`
  - `resources/js/components/Preview/useInspectSelectionLifecycle.ts`
- preview DOM reconciliation:
  - `resources/js/components/Preview/InspectPreview.tsx`
  - `resources/js/builder/domMapper/domMapper.ts`
- bridge protocol:
  - `resources/js/lib/builderBridge.ts`
  - `resources/js/builder/cms/useChatEmbeddedBuilderBridge.ts`
  - `resources/js/builder/cms/useEmbeddedBuilderBridge.ts`

## Result

After this pass:

- there is one authoritative runtime registry file
- no active runtime file imports a secondary registry path
- support/AI tooling is isolated from the core inspect runtime by import boundary
- remaining compatibility drift is inside the canonical registry API surface, not in duplicate files

## Residual Risks

- `resources/js/Pages/Project/Cms.tsx` remains very large and still mixes core runtime behavior with support/AI surfaces
- historical docs under `resources/js/builder/docs/*` still describe the removed central/shim registry files
- support modules still preserve old conceptual terminology such as “central registry” in comments and test names even though the backing file is now canonicalized
