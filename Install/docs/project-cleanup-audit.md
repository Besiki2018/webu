# Project Cleanup Audit

Date: 2026-03-13

Scope: `resources/js/builder/*` plus the preview/bridge files that participate in the active inspect builder runtime.

Audit rule:
- Folder-first classification where every file in the folder shares the same status.
- File-level exceptions are listed explicitly when a folder mixes active and inactive surfaces.
- Nothing in `SAFE TO DELETE` or `INACTIVE / ISOLATED V2` should be removed until repo-wide import/reference checks stay clear of the active inspect flow.

## Categories

1. `ACTIVE LEGACY CORE`
   Reason: directly required by `/project/{project}?tab=inspect`.
2. `ACTIVE LEGACY SUPPORT`
   Reason: not the central runtime loop, but imported by Chat/Cms/preview/registry/schema/state and required for the current builder to function.
3. `INACTIVE / ISOLATED V2`
   Reason: old standalone builder surfaces, self-contained editor UI, or legacy document/node architecture not used by the inspect runtime.
4. `SAFE TO DELETE`
   Reason: empty folders or isolated files with no live imports in the current repo scan.
5. `KEEP TEMPORARILY BUT NOT USED`
   Reason: appears inactive for inspect runtime, but still documents or supports nearby systems and is low-risk to keep until a second pass.
6. `HIGH RISK / REQUIRES MANUAL REVIEW`
   Reason: broad compatibility/helper surface with unclear downstream use; do not delete in this pass.

## Active Legacy Core

### Folder: `resources/js/builder/cms`
Category: `ACTIVE LEGACY CORE`

Reason:
- This is the heart of the embedded inspect builder bridge, preview/sidebar sync, mutation dispatch, and CMS selection lifecycle.
- Chat/Cms active flow imports these files directly.

Files covered by this classification:
- `CmsInspectorPanel.tsx`
- `CmsMutationDispatcher.ts`
- `CmsSchemaResolver.ts`
- `canonicalSelectionPayload.ts`
- `chatBuilderMutationFlow.ts`
- `chatBuilderStructureMutations.ts`
- `chatEmbeddedBuilderUtils.ts`
- `embeddedBuilderBridgeContract.ts`
- `hydratePageFromCMS.ts`
- `nestedSectionTree.ts`
- `pageHydration.ts`
- `scheduleDraftPersist.ts`
- `schedulePreviewRefresh.ts`
- `useChatEmbeddedBuilderBridge.ts`
- `useCmsCanvasInteractionHandlers.ts`
- `useCmsEmbeddedBuilderMutationHandlers.ts`
- `useCmsEmbeddedBuilderSelectionHandlers.ts`
- `useCmsEmbeddedBuilderSync.ts`
- `useCmsFixedSectionVariantController.ts`
- `useCmsPageSelectionLifecycle.ts`
- `useCmsPreviewIframeBinding.ts`
- `useCmsPreviewSelectionController.ts`
- `useCmsSelectionStateSync.ts`
- `useCmsSidebarSelectionActions.ts`
- `useCmsStructureMutationHandlers.ts`
- `useDraftPersistSchedule.ts`
- `useEmbeddedBuilderBridge.ts`
- `usePreviewRefreshSchedule.ts`
- `workspaceBuilderSync.ts`
- `__tests__/*`

### Folder: `resources/js/builder/visual`
Category: `ACTIVE LEGACY CORE`

Reason:
- Current inspect builder renders and manipulates sections through the legacy visual tree here.
- `Cms.tsx` imports `BuilderCanvas`, `StructurePanel`, `treeUtils`, and `types` directly.

Files covered by this classification:
- `BuilderCanvas.tsx`
- `BuilderCanvasSectionSurface.tsx`
- `EditableNodeWrapper.tsx`
- `RootDropZone.tsx`
- `SectionBlockPlaceholder.tsx`
- `StructurePanel.tsx`
- `registryComponents.tsx`
- `treeUtils.ts`
- `types.ts`
- `__tests__/*`

### Folder: `resources/js/builder/workspace`
Category: `ACTIVE LEGACY CORE`

Reason:
- This is the active Chat workspace shell for preview/settings/code switching.

Files covered by this classification:
- `BuilderPreviewSurface.tsx`
- `BuilderWorkspaceShell.tsx`

### Folder: `resources/js/builder/schema`
Category: `ACTIVE LEGACY CORE`

Reason:
- Current inspector binding and preview target resolution use canonical schema path matching from this folder.

Files covered by this classification:
- `index.ts`
- `schemaBindingResolver.ts`
- `__tests__/*`

### Folder: `resources/js/builder/inspector`
Category: mixed

Core files:
- `InspectorFieldResolver.ts` — `ACTIVE LEGACY CORE`; schema primitive normalization used by `Cms.tsx` and `CmsSchemaResolver.ts`.
- `InspectorRenderer.ts` — `ACTIVE LEGACY CORE`; current Cms inspector grouping/render bucketing.
- `SelectedSectionEditableFields.tsx` — `ACTIVE LEGACY CORE`; current Cms inspector panel body.
- `SidebarInspector.tsx` — `ACTIVE LEGACY CORE`; legacy schema-backed sidebar surface still exported and referenced in builder tests.
- `filterInspectorSchemaFields.ts` — `ACTIVE LEGACY CORE`; current selected section field filtering.
- `selectedSectionInspectorState.ts` — `ACTIVE LEGACY CORE`; still used by `CmsSchemaResolver.ts`.
- `index.ts` — `ACTIVE LEGACY SUPPORT`; thin export surface.
- `__tests__/filterInspectorSchemaFields.test.ts` — `ACTIVE LEGACY SUPPORT`; validates current filtering behavior.
- `__tests__/selectedSectionInspectorState.test.ts` — `ACTIVE LEGACY SUPPORT`; validates current selected-section schema state behavior.

Inactive V2 files inside this folder:
- `InspectorPanel.tsx` — `INACTIVE / ISOLATED V2`; unused standalone inspector shell that depends on old builder API/state.
- `InspectorFieldRenderer.tsx` — `INACTIVE / ISOLATED V2`; old field renderer surface, not used by the active Cms inspector.
- `fields/ColorField.tsx` — `INACTIVE / ISOLATED V2`; only referenced by `InspectorFieldRenderer.tsx`.
- `fields/ImagePickerField.tsx` — `INACTIVE / ISOLATED V2`; only referenced by `InspectorFieldRenderer.tsx`.
- `fields/LinkField.tsx` — `INACTIVE / ISOLATED V2`; only referenced by `InspectorFieldRenderer.tsx`.
- `fields/SelectField.tsx` — `INACTIVE / ISOLATED V2`; only referenced by `InspectorFieldRenderer.tsx`.
- `fields/SpacingField.tsx` — `INACTIVE / ISOLATED V2`; only referenced by `InspectorFieldRenderer.tsx`.
- `fields/TextAreaField.tsx` — `INACTIVE / ISOLATED V2`; only referenced by `InspectorFieldRenderer.tsx`.
- `fields/TextField.tsx` — `INACTIVE / ISOLATED V2`; only referenced by `InspectorFieldRenderer.tsx`.
- `fields/ToggleField.tsx` — `INACTIVE / ISOLATED V2`; only referenced by `InspectorFieldRenderer.tsx`.

### Folder: `resources/js/builder/state`
Category: mixed

Core/support files:
- `builderEditingStore.ts` — `ACTIVE LEGACY CORE`; authoritative inspect builder editing state.
- `sectionProps.ts` — `ACTIVE LEGACY CORE`; canonical section prop parsing/preview text helpers.
- `updatePipeline.ts` — `ACTIVE LEGACY CORE`; current mutation pipeline for insert/delete/edit behavior.
- `useBuilderCanvasState.ts` — `ACTIVE LEGACY CORE`; Cms local state bundle around sections/selection/preview sync.
- `__tests__/builderEditingStore.test.ts` — `ACTIVE LEGACY SUPPORT`
- `__tests__/sectionProps.test.ts` — `ACTIVE LEGACY SUPPORT`
- `__tests__/updatePipeline.test.ts` — `ACTIVE LEGACY SUPPORT`
- `__tests__/useBuilderCanvasState.test.ts` — `ACTIVE LEGACY SUPPORT`

Inactive V2 files:
- `builderStore.ts` — `INACTIVE / ISOLATED V2`; old document/node editor store tied to `types/*`, `history/*`, and `mutations/*`.
- `aiStore.ts` — `INACTIVE / ISOLATED V2`; no active imports in current inspect runtime.
- `historyStore.ts` — `INACTIVE / ISOLATED V2`; no active imports in current inspect runtime.
- `inspectorStore.ts` — `INACTIVE / ISOLATED V2`; no active imports in current inspect runtime.
- `selectionStore.ts` — `INACTIVE / ISOLATED V2`; no active imports in current inspect runtime.
- `structureStore.ts` — `INACTIVE / ISOLATED V2`; no active imports in current inspect runtime.
- `uiStore.ts` — `INACTIVE / ISOLATED V2`; no active imports in current inspect runtime.

### Top-level builder files
Category:
- `componentRegistry.ts` — `ACTIVE LEGACY CORE`; source of truth for current component/schema/governance/runtime entry mapping.
- `editingState.ts` — `ACTIVE LEGACY CORE`; canonical editable target/selection helpers used across Chat/Cms/preview/store.
- `projectTypes.ts` — `ACTIVE LEGACY SUPPORT`; current normalized project/site type logic.
- `selectedTargetContext.ts` — `ACTIVE LEGACY SUPPORT`; current AI/editor target context.
- `types.ts` — `ACTIVE LEGACY SUPPORT`; shared serializable builder type surface used by active code.

## Active Legacy Support

### Folder: `resources/js/builder/chat`
Category: `ACTIVE LEGACY SUPPORT`

Reason:
- Chat page still drives the workspace shell, generated preview, selected target propagation, and bridge page identity through this folder.

Files covered by this classification:
- `chatBuilderSelection.ts`
- `chatPageUtils.ts`
- `useBuilderWorkspace.ts`
- `useGeneratedCodePreview.ts`
- `ARCHITECTURE_CHECKLIST.md` — `KEEP TEMPORARILY BUT NOT USED`; documentation, not runtime.
- `__tests__/*`

### Folder: `resources/js/builder/model`
Category: `ACTIVE LEGACY SUPPORT`

Reason:
- The active runtime normalizes section drafts through page-model helpers used by `Cms.tsx` and `builderEditingStore.ts`.

Files covered by this classification:
- `pageModel.ts`
- `__tests__/*`

### Folder: `resources/js/builder/registry`
Category: `ACTIVE LEGACY SUPPORT`

Reason:
- Active builder still uses central registry entries for mapped React components and compatibility checks.

Files covered by this classification:
- `componentRegistry.ts`
- `index.ts`

### Folder: `resources/js/builder/store`
Category: `ACTIVE LEGACY SUPPORT`

Reason:
- `useBuilderStore` is still used by `Cms.tsx`, `DesignImportPanel`, `AIWebsitePromptPanel`, and chat command handlers.

Files covered by this classification:
- `builderStore.ts`
- `index.ts`

### Folder: `resources/js/builder/domMapper`
Category: `ACTIVE LEGACY SUPPORT`

Reason:
- Preview annotation and target lookup depend on DOM map invalidation/observation.

Files covered by this classification:
- `domMapper.ts`
- `index.ts`
- `__tests__/*`

### Folder: `resources/js/builder/designSystem`
Category: `ACTIVE LEGACY SUPPORT`

Reason:
- Current Cms inspect flow uses the design system panel and token helpers directly.

Files covered by this classification:
- `DesignSystemPanel.tsx`
- `designSystemApplier.ts`
- `index.ts`
- `tokens.ts`
- `__tests__/*`

### Folder: `resources/js/builder/commands`
Category: mixed

Support files:
- `generateSite.ts` — `ACTIVE LEGACY SUPPORT`; still used by `useBuilderChat.ts`.
- `optimizeForProjectType.ts` — `ACTIVE LEGACY SUPPORT`; still used by `useBuilderChat.ts`.
- `index.ts` — `KEEP TEMPORARILY BUT NOT USED`; export surface with low runtime impact.

Candidate delete:
- `exportWebsite.ts` — `KEEP TEMPORARILY BUT NOT USED`; not part of inspect runtime, but not removed in this pass because it is adjacent to active command utilities and may still be used manually.

### Folder: `resources/js/builder/ai`
Category: mixed

Active support files:
- `aiBrandGenerator.ts`
- `buttonStyleGenerator.ts`
- `chatImprovementCommands.ts`
- `componentScoring.ts`
- `componentSelector.ts`
- `contentGenerator.ts`
- `designStyleAnalyzer.ts`
- `designSystemGenerator.ts`
- `designUpgrade.ts`
- `generateLayoutFromDesign.ts`
- `generateSiteFromPrompt.ts`
- `imageGenerator.ts`
- `layoutRefine.ts`
- `projectTypeIntegration.ts`
- `promptAnalyzer.ts`
- `propsGenerator.ts`
- `siteBuilder.ts`
- `siteOptimizer.ts`
- `sitePlanner.ts`
- `smartVariants.ts`
- `__tests__/generateSiteFromPrompt.test.ts`
- `__tests__/sitePlanner.test.ts`
- related tests for prompt/layout generation still imported by active features

Inactive or isolated AI/component-generator files:
- `AIEditPanel.tsx` — `INACTIVE / ISOLATED V2`; old standalone AI panel, no active imports.
- `aiContextBuilder.ts` — `INACTIVE / ISOLATED V2`; tied to old document model.
- `aiMutationAdapter.ts` — `INACTIVE / ISOLATED V2`; only references old mutation layer.
- `aiTypes.ts` — `INACTIVE / ISOLATED V2`; only feeds deleted builder API surface.
- `componentCodeGenerator.ts` — `KEEP TEMPORARILY BUT NOT USED`; generator utility, not active inspect runtime.
- `componentFolderGenerator.ts` — `KEEP TEMPORARILY BUT NOT USED`; generator utility with tests, not active inspect runtime.
- `componentImprover.ts` — `KEEP TEMPORARILY BUT NOT USED`; not active inspect runtime.
- `componentRequestDetector.ts` — `KEEP TEMPORARILY BUT NOT USED`; detection helper not in current inspect flow.
- `componentSpecGenerator.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `componentValidation.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `createComponentPipeline.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `defaultsGenerator.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `designConsistencyAnalyzer.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `designContentExtractor.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `designImageProcessor.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `duplicateComponentChecker.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `layoutAnalyzer.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `layoutBuilder.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `layoutDetector.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `radiusGenerator.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `registryInjectionGenerator.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `schemaGenerator.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `sectionGapDetector.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `sectionMapper.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `shadowGenerator.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `siteAnalyzer.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `spacingScaleGenerator.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `typographyGenerator.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `uxRules.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `variantMatcher.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `variantsGenerator.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `EDITABLE_OUTPUT.md`, `ERROR_HANDLING.md`, `FINAL_RESULT.md` — `KEEP TEMPORARILY BUT NOT USED`

### Top-level builder support files
Category:
- `aiDesignGeneration.ts` — `KEEP TEMPORARILY BUT NOT USED`; design-generation helper outside current inspect flow.
- `aiProjectProcessor.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `aiPromptToSite.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `aiRefactorEngine.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `aiSiteGeneration.ts` — `ACTIVE LEGACY SUPPORT`; still used by current AI site generation path.
- `builderCompatibility.ts` — `HIGH RISK / REQUIRES MANUAL REVIEW`; active visual renderers still import it.
- `centralComponentRegistry.ts` — `ACTIVE LEGACY SUPPORT`
- `componentCompatibility.ts` — `ACTIVE LEGACY SUPPORT`
- `componentLibraryCategories.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `componentMetadataInjection.ts` — `ACTIVE LEGACY SUPPORT`; store/build tree helpers still depend on it.
- `componentParameterMetadata.ts` — `ACTIVE LEGACY SUPPORT`
- `componentSchemaFormat.ts` — `ACTIVE LEGACY SUPPORT`
- `designTokens.ts` — `ACTIVE LEGACY SUPPORT`
- `exportWebsite.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `performanceOptimization.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `projectProcessor.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `refactorActions.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `refactorEngine.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `responsiveFieldDefinitions.ts` — `ACTIVE LEGACY SUPPORT`
- `responsiveProps.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `safeRefactorRules.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `smartImageSystem.ts` — `KEEP TEMPORARILY BUT NOT USED`
- `smartLayoutEngine.ts` — `KEEP TEMPORARILY BUT NOT USED`

## Inactive / Isolated V2

### Folder: `resources/js/builder/canvas`
Category: `INACTIVE / ISOLATED V2`

Reason:
- No active Chat/Cms/preview imports.
- Depends on old `state/builderStore.ts` document/node store.

Files:
- `CanvasNode.tsx`
- `CanvasRenderer.tsx`
- `CanvasViewport.tsx`
- `CanvasWorkspace.tsx`
- `hooks/useCanvasNodeRect.ts`
- `overlays/DropIndicators.tsx`
- `overlays/HoverOverlay.tsx`
- `overlays/InlineToolbar.tsx`
- `overlays/SelectionOverlay.tsx`

### Folder: `resources/js/builder/layers`
Category: `INACTIVE / ISOLATED V2`

Reason:
- Old standalone layers panel tree; no live imports into active inspect flow.

Files:
- `LayersPanel.tsx`
- `LayersTree.tsx`
- `LayersTreeNode.tsx`

### Folder: `resources/js/builder/library`
Category: `INACTIVE / ISOLATED V2`

Reason:
- Old standalone component library panel; not used by `Cms.tsx` inspect elements flow.

Files:
- `ComponentCard.tsx`
- `ComponentsLibraryPanel.tsx`
- `componentInsertHelpers.ts`

### Folder: `resources/js/builder/api`
Category: `INACTIVE / ISOLATED V2`

Reason:
- Old builder document/assets/autosave/AI panel API surface.
- Current inspect builder uses Cms APIs and bridge sync, not this client.

Files:
- `builderApi.ts`

### Folder: `resources/js/builder/assets`
Category: `INACTIVE / ISOLATED V2`

Reason:
- Only consumed by old inspector field/API surface; current Cms media picker path does not use this folder.

Files:
- `AssetPickerModal.tsx`
- `AssetsPanel.tsx`
- `assetsStore.ts`

### Folder: `resources/js/builder/history`
Category: `INACTIVE / ISOLATED V2`

Reason:
- Only used by old document mutation store.

Files:
- `historyActions.ts`
- `historySerializer.ts`

### Folder: `resources/js/builder/renderer`
Category: `INACTIVE / ISOLATED V2`

Reason:
- Old standalone renderer abstraction, not imported by Chat/Cms inspect flow.

Files:
- `CanvasRenderer.tsx`
- `index.ts`

### Folder: `resources/js/builder/components`
Category: `INACTIVE / ISOLATED V2`

Reason:
- Old builder component definition registry separate from the active `componentRegistry.ts`.

Files:
- `categories.ts`
- `defaults.ts`
- `registry.ts`
- `renderers/index.tsx`
- `schemas.ts`

### Folder: `resources/js/builder/types`
Category: `INACTIVE / ISOLATED V2`

Reason:
- Old document/node/page/schema model only used by isolated V2 state/canvas/library layers.

Files:
- `builderComponent.ts`
- `builderDocument.ts`
- `builderNode.ts`
- `builderPage.ts`
- `builderSchema.ts`

### Folder: `resources/js/builder/persistence`
Category: `INACTIVE / ISOLATED V2`

Reason:
- Old autosave hook tied to `builderApi.ts` and `state/builderStore.ts`.

Files:
- `useBuilderAutosave.ts`

### Folder: `resources/js/builder/mutations`
Category: mixed

Active file:
- `applyInspectorMutation.ts` — `ACTIVE LEGACY SUPPORT`; current Cms mutation dispatcher wraps this.

Inactive files:
- `dispatchBuilderMutation.ts` — `INACTIVE / ISOLATED V2`; old document mutation engine.
- `mutationHandlers.ts` — `INACTIVE / ISOLATED V2`
- `normalizers.ts` — `INACTIVE / ISOLATED V2`
- `validators.ts` — `INACTIVE / ISOLATED V2`

## Safe To Delete

Reason:
- Empty folders with no files and no runtime role in the current repo scan.

Paths:
- `resources/js/builder/app`
- `resources/js/builder/projects`
- `resources/js/builder/sections`
- `resources/js/builder/ux`
- `resources/js/builder/content`

## Keep Temporarily But Not Used

Reason:
- Appears outside the active inspect runtime, but deletion would expand this cleanup into secondary systems (AI generation, exports, docs, or developer tooling) beyond the production-critical path.

Paths:
- `resources/js/builder/ARCHITECTURE.md`
- `resources/js/builder/DELIVERABLE.md`
- `resources/js/builder/README.md`
- `resources/js/builder/core/*`
- `resources/js/builder/docs/*`
- `resources/js/builder/layout/HeaderFooterLayoutForm.tsx`
- `resources/js/builder/templates/matrix.ts`
- inactive AI generator/support files listed above
- top-level helper files listed above as `KEEP TEMPORARILY BUT NOT USED`

## High Risk / Requires Manual Review

Reason:
- These files are not core to the inspect runtime loop, but they sit on compatibility boundaries that touch active rendering/schema/component paths.

Paths:
- `resources/js/builder/builderCompatibility.ts`
- `resources/js/builder/core/*`
- `resources/js/builder/utils/*`

Why high risk:
- `builderCompatibility.ts` is still imported by active registry renderers.
- `core/*` and `utils/*` are small but shared; removing them without a second dependency pass risks collateral breakage in nearby builder helpers.

## Immediate Cleanup Targets For This Pass

Delete after reference verification:
- `resources/js/builder/canvas/*`
- `resources/js/builder/layers/*`
- `resources/js/builder/library/*`
- `resources/js/builder/api/builderApi.ts`
- `resources/js/builder/assets/*`
- `resources/js/builder/history/*`
- `resources/js/builder/renderer/*`
- `resources/js/builder/components/*`
- `resources/js/builder/types/*`
- `resources/js/builder/persistence/useBuilderAutosave.ts`
- `resources/js/builder/state/builderStore.ts`
- `resources/js/builder/state/aiStore.ts`
- `resources/js/builder/state/historyStore.ts`
- `resources/js/builder/state/inspectorStore.ts`
- `resources/js/builder/state/selectionStore.ts`
- `resources/js/builder/state/structureStore.ts`
- `resources/js/builder/state/uiStore.ts`
- `resources/js/builder/inspector/InspectorPanel.tsx`
- `resources/js/builder/inspector/InspectorFieldRenderer.tsx`
- `resources/js/builder/inspector/fields/*`
- `resources/js/builder/mutations/dispatchBuilderMutation.ts`
- `resources/js/builder/mutations/mutationHandlers.ts`
- `resources/js/builder/mutations/normalizers.ts`
- `resources/js/builder/mutations/validators.ts`
- empty folders listed in `SAFE TO DELETE`

Do not delete in this pass:
- `resources/js/builder/cms/*`
- `resources/js/builder/visual/*`
- `resources/js/builder/workspace/*`
- `resources/js/builder/schema/*`
- `resources/js/builder/componentRegistry.ts`
- `resources/js/builder/editingState.ts`
- `resources/js/builder/state/builderEditingStore.ts`
- `resources/js/builder/inspector/InspectorRenderer.ts`
- `resources/js/builder/inspector/InspectorFieldResolver.ts`
- `resources/js/builder/inspector/selectedSectionInspectorState.ts`
