# Builder Architecture V2

## Overview

Webu Builder V2 runs on `/project/{project}/builder` as a same-runtime visual editor.

The V2 surface is builder-centered:

- `BuilderApp` is the product root.
- `BuilderLayout` owns the shell.
- `CanvasWorkspace`, `InspectorPanel`, `LayersPanel`, `ComponentsLibraryPanel`, `AssetsPanel`, and `AIEditPanel` all run inside one React runtime.
- No preview iframe, sidebar iframe, or `postMessage` bridge is used inside the V2 path.

Legacy builder systems remain in place for coexistence, but V2 does not import or depend on them.

## Runtime Flow

1. Laravel routes `/project/{project}/builder` to `ProjectBuilderController@show`.
2. Inertia renders `resources/js/Pages/Builder.tsx`.
3. `BuilderApp` mounts `BuilderProviders` and `BuilderLayout`.
4. `BuilderProviders` initializes the central zustand store with the draft and published V2 documents and starts autosave.
5. All builder UI reads from the shared store and writes through the mutation pipeline.

## Canonical Data Model

The builder document is the single source of truth.

- `BuilderDocument` stores project-level draft state.
- `BuilderPage` maps pages to their root nodes.
- `BuilderNode` stores the editable tree structure, props, styles, bindings, and metadata.

The DOM is only a rendered projection of that model. Selection overlays query rendered node rectangles, but editing state is never sourced from the DOM.

## Core Modules

- `resources/js/builder/state/builderStore.ts`
  Central V2 state for structure, selection, inspector, history, UI, and AI.
- `resources/js/builder/mutations/dispatchBuilderMutation.ts`
  Single mutation entrypoint for validation, normalization, application, history, versioning, and dirty tracking.
- `resources/js/builder/components/registry.ts`
  Canonical component registry for canvas rendering and inspector schema generation.
- `resources/js/builder/canvas/*`
  Same-runtime renderer and overlay system.
- `resources/js/builder/api/builderApi.ts`
  Draft load/save, AI suggestions, publish, and assets API layer.

## Legacy Isolation

The following legacy files are intentionally not part of the V2 runtime:

- `resources/js/Pages/Chat.tsx`
- `resources/js/Pages/Project/Cms.tsx`
- `resources/js/components/Preview/InspectPreview.tsx`

V2 is designed to reach parity before those legacy surfaces are retired.
