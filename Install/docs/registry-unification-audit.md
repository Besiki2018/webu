# Registry Unification Audit

## Canonical Registry

Authoritative runtime registry:

- `resources/js/builder/componentRegistry.ts`

It is now the only runtime source for:

- renderer lookup
- schema lookup
- default props
- editable field metadata
- unknown-component detection

## Migration Map

Direct runtime migrations completed:

- `resources/js/builder/visual/BuilderCanvas.tsx`
  - from `@/builder/registry/componentRegistry`
  - to `@/builder/componentRegistry`
  - now uses canonical `getComponentRenderEntry(...)`
- `resources/js/builder/visual/registryComponents.tsx`
  - from `../centralComponentRegistry`
  - to `../componentRegistry`
  - now uses canonical `getComponentRenderEntry(...)`
- `resources/js/builder/editingState.ts`
  - removed fallback read from `./registry/componentRegistry`
  - now resolves schema only through canonical `getComponentSchema(...)`
- `resources/js/Pages/Project/Cms.tsx`
  - removed direct read from `@/builder/registry/componentRegistry`
  - now uses canonical `getComponentRenderEntry(...)`, `getComponentSchema(...)`, and `getComponent(...)`
- `resources/js/builder/cms/useCmsStructureMutationHandlers.ts`
  - removed direct read from `@/builder/registry/componentRegistry`
  - now uses canonical `getComponentRenderEntry(...)` for render-entry defaults fallback

Already canonical before this pass:

- `resources/js/builder/inspector/selectedSectionInspectorState.ts`
- `resources/js/builder/schema/schemaBindingResolver.ts`
- `resources/js/components/Preview/InspectPreview.tsx`

## Compatibility Layers

These files were converted into thin transitional shims and no longer own registry data:

- `resources/js/builder/centralComponentRegistry.ts`
- `resources/js/builder/registry/componentRegistry.ts`

They now forward compatibility exports to `resources/js/builder/componentRegistry.ts`.

## Remaining Non-Runtime Consumers

Non-runtime or legacy-support files still importing compatibility shims were intentionally left in place for this pass:

- `resources/js/builder/ai/*`
- `resources/js/builder/updates/*`
- `resources/js/builder/componentCompatibility.ts`
- `resources/js/builder/componentMetadataInjection.ts`
- `resources/js/builder/inspector/SidebarInspector.tsx`
- related architecture / legacy tests

Those imports now resolve through the shim into the canonical registry, so runtime authority is unified even where call sites were not migrated yet.
