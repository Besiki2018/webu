# Webu v2 Domain Model

This document summarizes the new isolated code-generation domain layer added under `Install/resources/js/builder/codegen/`.

## Added files

- `types.ts`
  Canonical code-first entities for generated projects, pages, sections, component instances, files, assets, dependencies, generation runs, and workspace manifests.

- `generationPhases.ts`
  Shared lifecycle states and preview gating helpers.

- `projectGraph.ts`
  Normalizers and constructors for the canonical `GeneratedProjectGraph`.

- `workspacePlan.ts`
  Converts a project graph into filesystem/dependency work plus manifest output.

- `projectGraphToBuilderModel.ts`
  Narrow adapter from the new project graph into the current builder page model.

- `builderModelToProjectGraphPatch.ts`
  Narrow adapter from current builder mutation operations into graph patch instructions.

- `fileOperations.ts`
  Typed workspace file and dependency operations.

- `workspaceManifest.ts`
  `.webu/workspace-manifest.json` helpers and normalization.

## What the new layer does

The new codegen layer introduces a canonical code-first domain without changing the current runtime authority yet.

It can now represent:

- pages
- layouts
- sections
- component instances
- assets
- routes
- code files
- dependencies
- generation phases
- preview readiness
- workspace manifest metadata

## Current integration boundary

This layer is intentionally isolated.

It does not yet:

- replace `componentRegistry.ts`
- replace `updatePipeline.ts`
- replace CMS hydration
- wire the whole app to workspace-first generation

It does:

- define the future workspace-first source-of-truth model
- provide preview gating rules for code-first generation
- provide a builder compatibility bridge for supported sections
- define a manifest format for `.webu/workspace-manifest.json`

## Supported builder mapping scope

Current graph-to-builder mapping intentionally focuses on:

- header
- hero
- features
- CTA
- footer
- generic content sections

Nested sections and perfect round-trip fidelity are explicitly not solved yet.

## Intended next integration steps

1. Backend generation can start emitting a workspace manifest compatible with this model.
2. `ProjectGenerationRun` UI gating can switch to the new generation phase helpers.
3. Workspace-first generation can write a `GeneratedProjectGraph`, then derive current builder models from it.
4. Builder edits can be compiled into project-graph patch instructions before touching real files.
